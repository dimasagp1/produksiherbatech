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

        $usages = MaterialUsage::with([
            'items.produk.uom',
            'items.uom',
            'weeklyPlan.produk.uom',
            'weeklyPlan.line',
            'user',
        ])
            ->when($search, function ($q, $s) {
                $q->where('usage_number', 'like', "%{$s}%")
                    ->orWhereHas('weeklyPlan', function ($qq) use ($s) {
                        $qq->where('batch_number', 'like', "%{$s}%")
                            ->orWhereHas('produk', function ($p) use ($s) {
                                $p->where('nama_produk', 'like', "%{$s}%")
                                    ->orWhere('kode_produk', 'like', "%{$s}%");
                            });
                    })
                    ->orWhereHas('items', function ($it) use ($s) {
                        $it->where('material_name', 'like', "%{$s}%");
                    });
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
        $plans = WeeklyPlan::with(['produk.uom', 'line'])
            ->whereIn('status', ['aktif', 'draft'])
            ->latest('tanggal')
            ->limit(50)
            ->get();

        $boms = Bom::with(['produk.uom', 'items.uom', 'items.materialProduk.uom'])
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
            'shift' => 'nullable|in:shift1,shift2,1,2',
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

    public function show(MaterialUsage $materialUsage, OdooService $odooService)
    {
        $materialUsage->load([
            'items.produk.uom',
            'items.uom',
            'weeklyPlan.produk.uom',
            'weeklyPlan.line',
            'user',
        ]);

        $parentProdName = $materialUsage->weeklyPlan?->produk?->nama_produk;
        $targetOutput = (float) ($materialUsage->weeklyPlan?->target_output ?: 1.0);

        $items = $materialUsage->items->map(function ($item) use ($odooService, $parentProdName, $targetOutput) {
            $ratio = $item->quantity_standard > 0
                ? (($item->quantity_used - $item->quantity_standard) / $item->quantity_standard) * 100
                : null;

            $itemName = $item->material_name;
            $breakdown = null;
            $isPrimerOrRuahan = str_contains(strtolower($itemName), 'primer')
                || str_contains(strtolower($itemName), 'ruahan')
                || str_contains(strtolower($itemName), 'bulk')
                || str_contains(strtolower($itemName), 'wip')
                || ($item->produk?->item_type === 'wip');

            if ($isPrimerOrRuahan) {
                try {
                    $breakdown = $odooService->getMaterialRecipeBreakdown($itemName, $targetOutput, $parentProdName);
                } catch (\Throwable $e) {
                    $breakdown = null;
                }
            }

            $detectedCat = Bom::detectCategory($itemName, $item->produk?->item_type);
            $isPm = in_array(strtolower($item->produk?->item_type ?? ''), ['pm', 'bahan_kemas', 'kemas', 'packaging'], true)
                || $detectedCat === 'primary_packaging'
                || $detectedCat === 'secondary_packaging';

            $itemType = $isPm ? 'pm' : ($isPrimerOrRuahan ? 'wip' : 'rm');

            return [
                ...$item->toArray(),
                'item_type' => $itemType,
                'material_code' => $item->produk?->kode_produk ?? '-',
                'uom_code' => $item->uom?->code ?? $item->produk?->uom?->code ?? $item->produk?->odoo_uom ?? 'Pcs',
                'ratio_persen' => $ratio !== null ? round($ratio, 2) : null,
                'recipe_breakdown' => $breakdown,
            ];
        });

        $totalStandard = $materialUsage->items->sum('quantity_standard');
        $totalUsed = $materialUsage->items->sum('quantity_used');
        $totalVariance = $totalUsed - $totalStandard;
        $overallRatio = $totalStandard > 0 ? round(($totalVariance / $totalStandard) * 100, 2) : null;
        $ratioTarget = (float) Setting::get('material_usage_ratio_target', 0.5);

        // Subtotals by category
        $rmItems = $items->filter(fn ($it) => in_array($it['item_type'] ?? '', ['rm', 'wip', 'bahan_baku'], true));
        $pmItems = $items->filter(fn ($it) => in_array($it['item_type'] ?? '', ['pm', 'bahan_kemas'], true));

        $withinTargetCount = $items->filter(function ($it) use ($ratioTarget) {
            if ($it['ratio_persen'] === null) {
                return (float) ($it['variance'] ?? 0) <= 0;
            }
            $r = abs((float) ($it['ratio_persen'] ?? 0));

            return $r <= $ratioTarget;
        })->count();

        $complianceRate = $items->count() > 0
            ? round(($withinTargetCount / $items->count()) * 100, 1)
            : 100.0;

        $rmEfficientCount = $rmItems->filter(fn ($it) => (float) ($it['variance'] ?? 0) <= 0)->count();
        $pmEfficientCount = $pmItems->filter(fn ($it) => (float) ($it['variance'] ?? 0) <= 0)->count();

        return Inertia::render('SCM/Inventory/MaterialUsage/Show', [
            'usage' => $materialUsage,
            'items' => $items,
            'summary' => [
                'total_items_count' => $items->count(),
                'rm_count' => $rmItems->count(),
                'pm_count' => $pmItems->count(),
                'within_target_count' => $withinTargetCount,
                'compliance_rate' => $complianceRate,
                'ratio_target' => $ratioTarget,
                'rm_efficient_count' => $rmEfficientCount,
                'pm_efficient_count' => $pmEfficientCount,
                'rm_used' => round($rmItems->sum('quantity_used'), 4),
                'pm_used' => round($pmItems->sum('quantity_used'), 4),
                'total_standard' => round($totalStandard, 4),
                'total_used' => round($totalUsed, 4),
                'total_variance' => round($totalVariance, 4),
                'overall_ratio' => $overallRatio,
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

    /**
     * Preview Material Usages from Odoo for interactive selection modal.
     */
    public function previewOdoo(OdooService $odooService)
    {
        if (! auth()->user()->hasAnyRole(['warehouse_admin', 'superadmin', 'admin', 'ppic', 'leader', 'spv'])) {
            abort(403, 'Akses ditolak.');
        }

        try {
            $data = $odooService->fetchMaterialUsagesPreview();

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil preview Material Usage dari Odoo: '.$e->getMessage(),
            ], 500);
        }
    }

    public function syncFromOdoo(Request $request, OdooService $odooService)
    {
        if (! auth()->user()->hasAnyRole(['warehouse_admin', 'superadmin', 'admin', 'ppic', 'leader', 'spv'])) {
            abort(403, 'Akses ditolak.');
        }

        $selectedIds = $request->input('selected_ids') ?? $request->input('selected_mo_ids');

        try {
            $summary = $odooService->syncMaterialUsages($selectedIds);
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
