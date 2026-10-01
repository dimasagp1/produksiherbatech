<?php

namespace App\Http\Controllers\SCM;

use App\Exports\MaterialLossExport;
use App\Http\Controllers\Controller;
use App\Models\Bom;
use App\Models\InventoryStock;
use App\Models\MaterialUsage;
use App\Models\Produk;
use App\Models\Setting;
use App\Models\WeeklyPlan;
use App\Services\OdooService;
use App\Services\ScmNumberService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class MaterialUsageController extends Controller
{
    public function __construct(private ScmNumberService $numbers) {}

    public function index(Request $request)
    {
        $search = $request->input('search', '');

        $usages = MaterialUsage::with(['items.produk', 'items.uom', 'weeklyPlan.produk', 'user'])
            ->when($search, function ($q, $s) {
                $q->where('usage_number', 'like', "%{$s}%")
                    ->orWhereHas('weeklyPlan', fn ($qq) => $qq->where('batch_number', 'like', "%{$s}%"));
            })
            ->latest('usage_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $ratioTarget = (float) Setting::get('material_usage_ratio_target', 0.5);

        return Inertia::render('SCM/Inventory/MaterialUsage/Index', [
            'usages' => $usages,
            'search' => $search,
            'ratioTarget' => $ratioTarget,
        ]);
    }

    public function create()
    {
        $plans = WeeklyPlan::with(['produk', 'line'])
            ->whereIn('status', ['aktif', 'draft'])
            ->latest('tanggal')
            ->limit(50)
            ->get();

        $boms = Bom::with(['produk', 'items.uom', 'items.materialProduk'])
            ->where('is_active', true)
            ->get()
            ->keyBy('produk_id');

        $materials = Produk::aktif()
            ->whereIn('item_type', ['rm', 'pm'])
            ->with('uom')
            ->get();

        $stocks = InventoryStock::with('produk')
            ->whereIn('produk_id', $materials->pluck('id'))
            ->get()
            ->groupBy('produk_id');

        return Inertia::render('SCM/Inventory/MaterialUsage/Create', [
            'plans' => $plans,
            'boms' => $boms,
            'materials' => $materials,
            'stocks' => $stocks,
            'ratioTarget' => (float) Setting::get('material_usage_ratio_target', 0.5),
        ]);
    }

    public function store(Request $request)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya memiliki akses baca.');
        }

        $validated = $request->validate([
            'weekly_plan_id' => 'required|exists:weekly_plans,id',
            'usage_date' => 'required|date',
            'shift' => 'nullable|in:shift1,shift2',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.produk_id' => 'nullable|exists:produks,id',
            'items.*.material_name' => 'required|string|max:255',
            'items.*.quantity_used' => 'required|numeric|min:0',
            'items.*.quantity_standard' => 'nullable|numeric|min:0',
            'items.*.uom_id' => 'nullable|exists:scm_uoms,id',
        ]);

        $plan = WeeklyPlan::findOrFail($validated['weekly_plan_id']);
        $bom = Bom::with('items')->where('produk_id', $plan->produk_id)->where('is_active', true)->first();

        $usage = DB::transaction(function () use ($validated, $plan, $bom) {
            $usage = MaterialUsage::create([
                'usage_number' => $this->numbers->materialUsage(),
                'weekly_plan_id' => $plan->id,
                'user_id' => auth()->id(),
                'usage_date' => $validated['usage_date'],
                'shift' => $validated['shift'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $standard = $item['quantity_standard'] ?? null;
                if ($standard === null && $bom) {
                    $bomItem = $bom->items()
                        ->when($item['produk_id'] ?? null, fn ($q, $pid) => $q->where('material_produk_id', $pid))
                        ->when(! ($item['produk_id'] ?? null), fn ($q) => $q->where('material_name', $item['material_name']))
                        ->first();
                    $standard = $bomItem?->quantity ?? 0;
                }
                $standard = (float) ($standard ?? 0);
                $used = (float) $item['quantity_used'];

                $usage->items()->create([
                    'produk_id' => $item['produk_id'] ?? null,
                    'material_name' => $item['material_name'],
                    'quantity_used' => $used,
                    'quantity_standard' => $standard,
                    'variance' => $used - $standard,
                    'uom_id' => $item['uom_id'] ?? null,
                ]);

                // Stock ledger: kurangi stok material terpakai
                if (($item['produk_id'] ?? null) && $used > 0) {
                    $stock = InventoryStock::where('produk_id', $item['produk_id'])
                        ->where('batch_number', $plan->batch_number)
                        ->lockForUpdate()
                        ->first()
                        ?? InventoryStock::where('produk_id', $item['produk_id'])->orderBy('quantity', 'desc')->lockForUpdate()->first();

                    if ($stock) {
                        $stock->decrement('quantity', $used);
                    } else {
                        InventoryStock::create([
                            'produk_id' => $item['produk_id'],
                            'quantity' => -$used,
                            'batch_number' => $plan->batch_number,
                            'location' => 'GUDANG-UTAMA',
                        ]);
                    }
                }
            }

            return $usage;
        });

        return redirect()->route('scm.material-usage.show', $usage->id)
            ->with('success', "Material Usage {$usage->usage_number} tersimpan");
    }

    public function show(MaterialUsage $materialUsage)
    {
        $materialUsage->load(['items.produk', 'items.uom', 'weeklyPlan.produk', 'user']);

        $items = $materialUsage->items->map(function ($item) {
            $ratio = $item->quantity_standard > 0
                ? (($item->quantity_used - $item->quantity_standard) / $item->quantity_standard) * 100
                : null;

            return [
                ...$item->toArray(),
                'ratio_persen' => $ratio !== null ? round($ratio, 2) : null,
            ];
        });

        $totalStandard = $materialUsage->items->sum('quantity_standard');
        $totalUsed = $materialUsage->items->sum('quantity_used');
        $totalVariance = $totalUsed - $totalStandard;
        $overallRatio = $totalStandard > 0 ? round(($totalVariance / $totalStandard) * 100, 2) : null;

        return Inertia::render('SCM/Inventory/MaterialUsage/Show', [
            'usage' => $materialUsage,
            'items' => $items,
            'summary' => [
                'total_standard' => $totalStandard,
                'total_used' => $totalUsed,
                'total_variance' => $totalVariance,
                'overall_ratio' => $overallRatio,
                'ratio_target' => (float) Setting::get('material_usage_ratio_target', 0.5),
            ],
        ]);
    }

    /**
     * Material Loss Report — PDF + Excel.
     */
    public function export(Request $request, string $format)
    {
        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $from = $validated['date_from'] ?? now()->toDateString();
        $to = $validated['date_to'] ?? now()->toDateString();

        $rows = MaterialUsage::with(['items.produk', 'items.uom', 'weeklyPlan'])
            ->whereBetween('usage_date', [$from, $to])
            ->get()
            ->flatMap(function ($usage) {
                return $usage->items->map(fn ($item) => [
                    'usage_number' => $usage->usage_number,
                    'usage_date' => $usage->usage_date->toDateString(),
                    'batch_number' => $usage->weeklyPlan?->batch_number ?? '-',
                    'material_name' => $item->material_name,
                    'standard' => $item->quantity_standard,
                    'used' => $item->quantity_used,
                    'variance' => $item->variance,
                    'ratio' => $item->quantity_standard > 0
                        ? round((($item->quantity_used - $item->quantity_standard) / $item->quantity_standard) * 100, 2)
                        : null,
                ]);
            })
            ->values();

        $summary = [
            'date_from' => $from,
            'date_to' => $to,
            'total_items' => $rows->count(),
            'total_standard' => $rows->sum('standard'),
            'total_used' => $rows->sum('used'),
            'total_variance' => $rows->sum('variance'),
        ];

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('pdf.material-loss', [
                'rows' => $rows,
                'summary' => $summary,
            ]);

            return $pdf->download("material-loss-{$from}-{$to}.pdf");
        }

        if ($format === 'excel') {
            return Excel::download(
                new MaterialLossExport($rows->all(), $summary),
                "material-loss-{$from}-{$to}.xlsx"
            );
        }

        return back()->with('error', 'Format export tidak dikenal');
    }

    public function syncFromOdoo(OdooService $odooService)
    {
        if (! auth()->user()->hasAnyRole(['warehouse_admin', 'superadmin', 'admin', 'ppic', 'leader', 'spv'])) {
            abort(403, 'Akses ditolak.');
        }

        try {
            $summary = $odooService->syncMaterialUsages();
            $msg = "Sync Material Usage Odoo selesai: {$summary['created']} dibuat, {$summary['updated']} diperbarui, {$summary['skipped']} dilewati.";
            if (! empty($summary['errors'])) {
                $msg .= ' Error: '.implode(', ', $summary['errors']);
            }

            return back()->with('success', $msg);
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal sync Material Usage Odoo: '.$e->getMessage());
        }
    }
}
