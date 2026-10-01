<?php

namespace App\Http\Controllers\SCM;

use App\Http\Controllers\Controller;
use App\Models\DeliveryPlan;
use App\Models\Fleet;
use App\Models\InventoryStock;
use App\Models\Produk;
use App\Models\Setting;
use App\Services\OdooService;
use App\Services\ScmNumberService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DeliveryPlanController extends Controller
{
    public function __construct(private ScmNumberService $numbers) {}

    public function index(Request $request)
    {
        $status = $request->input('status');
        $view = $request->input('view', 'table');

        $plans = DeliveryPlan::with(['items.produk', 'fleet', 'creator'])
            ->when(in_array($status, ['planned', 'in_transit', 'delivered', 'delayed'], true), fn ($q) => $q->where('status', $status))
            ->when($request->filled('date_from'), fn ($q) => $q->where('planned_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->where('planned_date', '<=', $request->input('date_to')))
            ->orderBy('planned_date')
            ->paginate(20)
            ->withQueryString();

        $all = DeliveryPlan::query()->get();
        $delivered = $all->whereIn('status', ['delivered', 'delayed']);
        $total = $all->count() ?: 1;

        return Inertia::render('SCM/Delivery/Index', [
            'plans' => $plans,
            'status' => $status,
            'view' => $view,
            'fleets' => Fleet::where('is_active', true)->get(),
            'kpi' => [
                'otd' => round($delivered->where('on_time', true)->count() / max(1, $delivered->count()) * 100, 1),
                'in_full' => round($delivered->where('in_full', true)->count() / max(1, $delivered->count()) * 100, 1),
                'damage_free' => round($delivered->where('damage_free', true)->count() / max(1, $delivered->count()) * 100, 1),
                'doc_accuracy' => round($delivered->where('doc_accuracy', true)->count() / max(1, $delivered->count()) * 100, 1),
                'complaint_rate' => round($all->where('complaint', true)->count() / $total * 100, 1),
            ],
            'coaRevenue' => [
                Setting::get('revenue_coa_primary', '41000010'),
                Setting::get('revenue_coa_secondary', '41000011'),
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('SCM/Delivery/Create', [
            'fleets' => Fleet::where('is_active', true)->get(),
            'produks' => Produk::aktif()->where('item_type', 'fg')->get(['id', 'kode_produk', 'nama_produk']),
            'coaRevenue' => [
                Setting::get('revenue_coa_primary', '41000010'),
                Setting::get('revenue_coa_secondary', '41000011'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        if (! auth()->user()->hasAnyRole(['warehouse_admin', 'superadmin', 'admin', 'ppic'])) {
            abort(403, 'Anda tidak memiliki akses membuat delivery plan.');
        }

        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'planned_date' => 'required|date',
            'fleet_id' => 'nullable|exists:fleets,id',
            'driver_name' => 'nullable|string|max:255',
            'revenue_coa' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
            'odoo_so_id' => 'nullable|integer',
            'items' => 'required|array|min:1',
            'items.*.produk_id' => 'nullable|exists:produks,id',
            'items.*.product_name' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.uom_name' => 'nullable|string|max:50',
        ]);

        $plan = DB::transaction(function () use ($validated) {
            $plan = DeliveryPlan::create([
                'delivery_number' => $this->numbers->delivery(),
                'odoo_so_id' => $validated['odoo_so_id'] ?? null,
                'customer_name' => $validated['customer_name'],
                'planned_date' => $validated['planned_date'],
                'status' => 'planned',
                'fleet_id' => $validated['fleet_id'] ?? null,
                'driver_name' => $validated['driver_name'] ?? null,
                'revenue_coa' => $validated['revenue_coa'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                $plan->items()->create([
                    'produk_id' => $item['produk_id'] ?? null,
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'uom_name' => $item['uom_name'] ?? null,
                ]);
            }

            return $plan;
        });

        return redirect()->route('scm.delivery.show', $plan->id)
            ->with('success', "Delivery {$plan->delivery_number} dibuat");
    }

    public function show(DeliveryPlan $deliveryPlan)
    {
        $deliveryPlan->load(['items.produk', 'fleet', 'creator']);

        $fgChecks = $deliveryPlan->items->map(function ($item) {
            $stock = $item->produk_id
                ? InventoryStock::where('produk_id', $item->produk_id)->sum('quantity')
                : 0;

            return [
                'product_name' => $item->product_name,
                'requested' => (float) $item->quantity,
                'available_fg' => (float) $stock,
                'sufficient' => (float) $stock >= (float) $item->quantity,
            ];
        });

        return Inertia::render('SCM/Delivery/Show', [
            'plan' => $deliveryPlan,
            'fgChecks' => $fgChecks,
            'allSufficient' => $fgChecks->every(fn ($c) => $c['sufficient']),
        ]);
    }

    /**
     * Status flow: planned → in_transit → delivered|delayed.
     */
    public function updateStatus(Request $request, DeliveryPlan $deliveryPlan)
    {
        if (! auth()->user()->hasAnyRole(['warehouse_admin', 'superadmin', 'admin'])) {
            abort(403, 'Anda tidak memiliki akses update status delivery.');
        }

        $validated = $request->validate([
            'status' => 'required|in:in_transit,delivered,delayed',
            'on_time' => 'required_if:status,delivered|nullable|boolean',
            'in_full' => 'required_if:status,delivered|nullable|boolean',
            'damage_free' => 'required_if:status,delivered|nullable|boolean',
            'doc_accuracy' => 'required_if:status,delivered|nullable|boolean',
            'complaint' => 'nullable|boolean',
            'actual_delivery_date' => 'required_if:status,delivered,delayed|nullable|date',
            'notes' => 'nullable|string',
        ]);

        $allowed = [
            'planned' => ['in_transit'],
            'in_transit' => ['delivered', 'delayed'],
        ];

        if (! in_array($validated['status'], $allowed[$deliveryPlan->status] ?? [], true)) {
            return back()->withErrors(['error' => "Transisi {$deliveryPlan->status} → {$validated['status']} tidak diizinkan."]);
        }

        $data = array_filter([
            'status' => $validated['status'],
            'on_time' => $validated['on_time'] ?? null,
            'in_full' => $validated['in_full'] ?? null,
            'damage_free' => $validated['damage_free'] ?? null,
            'doc_accuracy' => $validated['doc_accuracy'] ?? null,
            'complaint' => $validated['complaint'] ?? null,
            'actual_delivery_date' => $validated['actual_delivery_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ], fn ($v) => $v !== null);

        $deliveryPlan->update($data);

        return redirect()->route('scm.delivery.show', $deliveryPlan->id)
            ->with('success', "Status delivery menjadi {$validated['status']}");
    }

    /**
     * Validasi stok FG sebelum cetak dokumen.
     */
    public function validateStock(DeliveryPlan $deliveryPlan)
    {
        $checks = $deliveryPlan->items->map(function ($item) {
            $stock = $item->produk_id ? InventoryStock::where('produk_id', $item->produk_id)->sum('quantity') : 0;

            return [
                'product_name' => $item->product_name,
                'requested' => (float) $item->quantity,
                'available_fg' => (float) $stock,
                'sufficient' => (float) $stock >= (float) $item->quantity,
            ];
        });

        return response()->json([
            'ok' => $checks->every(fn ($c) => $c['sufficient']),
            'checks' => $checks,
        ]);
    }

    public function printSuratJalan(DeliveryPlan $deliveryPlan)
    {
        [$ok, $message] = $this->guardPrint($deliveryPlan);
        if (! $ok) {
            return back()->withErrors(['error' => $message]);
        }

        $deliveryPlan->load(['items.produk', 'fleet']);

        return Pdf::loadView('pdf.surat-jalan', ['plan' => $deliveryPlan])
            ->download('surat-jalan-'.str_replace('/', '-', $deliveryPlan->delivery_number).'.pdf');
    }

    public function printFaktur(DeliveryPlan $deliveryPlan)
    {
        [$ok, $message] = $this->guardPrint($deliveryPlan);
        if (! $ok) {
            return back()->withErrors(['error' => $message]);
        }

        $deliveryPlan->load(['items.produk']);

        return Pdf::loadView('pdf.faktur', [
            'plan' => $deliveryPlan,
            'coa' => $deliveryPlan->revenue_coa ?: Setting::get('revenue_coa_primary', '41000010'),
        ])->download('faktur-'.str_replace('/', '-', $deliveryPlan->delivery_number).'.pdf');
    }

    public function printCoa(DeliveryPlan $deliveryPlan)
    {
        [$ok, $message] = $this->guardPrint($deliveryPlan);
        if (! $ok) {
            return back()->withErrors(['error' => $message]);
        }

        $deliveryPlan->load(['items.produk', 'fleet']);

        return Pdf::loadView('pdf.coa', ['plan' => $deliveryPlan])
            ->download('coa-'.str_replace('/', '-', $deliveryPlan->delivery_number).'.pdf');
    }

    /**
     * Pull SO unfulfilled dari Odoo → draft delivery plans.
     */
    public function syncFromOdoo(Request $request, OdooService $odoo)
    {
        if (! auth()->user()->hasAnyRole(['warehouse_admin', 'superadmin', 'admin', 'ppic'])) {
            abort(403, 'Anda tidak memiliki akses sync delivery.');
        }

        if (! filter_var(Setting::get('odoo_delivery_sync_enabled', '1'), FILTER_VALIDATE_BOOLEAN)) {
            return back()->with('error', 'Sync delivery Odoo dinonaktifkan.');
        }

        try {
            $orders = $odoo->fetchSaleOrders();
            $created = 0;
            $skipped = 0;

            foreach ($orders as $so) {
                $soId = (int) ($so['id'] ?? 0);
                if ($soId <= 0 || DeliveryPlan::where('odoo_so_id', $soId)->exists()) {
                    $skipped++;

                    continue;
                }

                $partner = is_array($so['partner_id'] ?? null) ? $so['partner_id'][1] : ('SO #'.$soId);

                DeliveryPlan::create([
                    'delivery_number' => $this->numbers->delivery(),
                    'odoo_so_id' => $soId,
                    'customer_name' => $partner,
                    'planned_date' => now()->addDay()->toDateString(),
                    'status' => 'planned',
                    'revenue_coa' => Setting::get('revenue_coa_primary', '41000010'),
                    'notes' => 'Auto dari Odoo SO '.($so['name'] ?? $soId),
                    'created_by' => auth()->id(),
                ]);
                $created++;
            }

            return back()->with('success', "Sync SO Odoo: {$created} baru, {$skipped} dilewati");
        } catch (\Exception $e) {
            return back()->with('error', 'Sync SO Odoo gagal: '.$e->getMessage());
        }
    }

    /**
     * Export delivery performance report (PDF).
     */
    public function exportPerformance(Request $request)
    {
        $from = $request->input('date_from', now()->startOfMonth()->toDateString());
        $to = $request->input('date_to', now()->toDateString());

        $plans = DeliveryPlan::with(['items', 'fleet'])
            ->whereBetween('planned_date', [$from, $to])
            ->orderBy('planned_date')
            ->get();

        $pdf = Pdf::loadView('pdf.delivery-performance', [
            'plans' => $plans,
            'from' => $from,
            'to' => $to,
        ]);

        return $pdf->download("delivery-performance-{$from}-{$to}.pdf");
    }

    /**
     * @return array{0:bool,1:string}
     */
    private function guardPrint(DeliveryPlan $deliveryPlan): array
    {
        if (in_array($deliveryPlan->status, ['delivered', 'delayed'], true)) {
            return [true, ''];
        }

        $sufficient = $deliveryPlan->items->every(function ($item) {
            if (! $item->produk_id) {
                return true;
            }
            $stock = InventoryStock::where('produk_id', $item->produk_id)->sum('quantity');

            return (float) $stock >= (float) $item->quantity;
        });

        if (! $sufficient) {
            return [false, 'Stok FG tidak mencukupi untuk mencetak dokumen delivery ini.'];
        }

        return [true, ''];
    }
}
