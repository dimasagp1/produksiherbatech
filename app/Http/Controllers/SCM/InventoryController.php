<?php

namespace App\Http\Controllers\SCM;

use App\Http\Controllers\Controller;
use App\Models\InventoryStock;
use App\Models\MaterialScrap;
use App\Models\MaterialUsage;
use App\Models\Produk;
use App\Models\ScmUom;
use App\Services\OdooService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InventoryController extends Controller
{
    /**
     * Saldo stok (read) + widget loss inventory.
     */
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $itemType = $request->input('item_type', 'all');

        $stocks = InventoryStock::with(['produk.uom', 'produk.category'])
            ->when($itemType && $itemType !== 'all', function ($q) use ($itemType) {
                $q->whereHas('produk', fn ($qq) => $qq->where('item_type', $itemType));
            })
            ->when($search, function ($q, $s) {
                $q->whereHas('produk', function ($qq) use ($s) {
                    $qq->where('nama_produk', 'like', "%{$s}%")
                        ->orWhere('kode_produk', 'like', "%{$s}%");
                })->orWhere('batch_number', 'like', "%{$s}%");
            })
            ->orderBy('location')
            ->orderBy('batch_number')
            ->paginate(25)
            ->withQueryString();

        $today = now()->toDateString();
        $lossWindow = now()->subDays(7)->toDateString();

        $totalScrapQty = MaterialScrap::where('created_at', '>=', $lossWindow)->sum('quantity');
        $totalUsageVariance = MaterialUsage::where('usage_date', '>=', $lossWindow)
            ->with('items')
            ->get()
            ->sum(fn ($u) => $u->items->sum('variance'));

        $totalStandard = MaterialUsage::where('usage_date', '>=', $lossWindow)
            ->with('items')
            ->get()
            ->sum(fn ($u) => $u->items->sum('quantity_standard'));

        $lossRatio = $totalStandard > 0
            ? round((($totalUsageVariance + $totalScrapQty) / $totalStandard) * 100, 2)
            : 0.0;

        $counts = [
            'all' => InventoryStock::count(),
            'fg' => InventoryStock::whereHas('produk', fn ($q) => $q->where('item_type', 'fg'))->count(),
            'rm' => InventoryStock::whereHas('produk', fn ($q) => $q->where('item_type', 'rm'))->count(),
            'pm' => InventoryStock::whereHas('produk', fn ($q) => $q->where('item_type', 'pm'))->count(),
            'wip' => InventoryStock::whereHas('produk', fn ($q) => $q->where('item_type', 'wip'))->count(),
        ];

        return Inertia::render('SCM/Inventory/SaldoStok/Index', [
            'stocks' => $stocks,
            'search' => $search,
            'item_type' => $itemType,
            'counts' => $counts,
            'produks' => Produk::aktif()->get(['id', 'kode_produk', 'nama_produk', 'item_type', 'min_stock', 'safety_stock']),
            'lossWidget' => [
                'window_days' => 7,
                'scrap_qty' => round($totalScrapQty, 3),
                'usage_variance' => round($totalUsageVariance, 3),
                'total_standard' => round($totalStandard, 3),
                'loss_ratio' => $lossRatio,
                // Revenue 0 jika belum ada SO — tampilkan qty saja (PRD B12)
                'revenue' => 0,
            ],
            'uoms' => ScmUom::all(['id', 'code', 'name']),
            'today' => $today,
        ]);
    }

    /**
     * Input penyesuaian stok manual (warehouse_admin) — pondasi stock ledger Phase C.
     */
    public function adjust(Request $request)
    {
        if (! auth()->user()->hasAnyRole(['warehouse_admin', 'superadmin', 'admin'])) {
            abort(403, 'Hanya warehouse_admin/admin/superadmin yang bisa menyesuaikan stok.');
        }

        $validated = $request->validate([
            'produk_id' => 'required|exists:produks,id',
            'batch_number' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:100',
            'quantity' => 'required|numeric',
            'expired_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $location = $validated['location'] ?: 'GUDANG-UTAMA';
        $batch = $validated['batch_number'] ?: null;

        DB::transaction(function () use ($validated, $location, $batch) {
            $stock = InventoryStock::where('produk_id', $validated['produk_id'])
                ->where('location', $location)
                ->where(function ($q) use ($batch) {
                    $batch === null ? $q->whereNull('batch_number') : $q->where('batch_number', $batch);
                })
                ->lockForUpdate()
                ->first();

            if ($stock) {
                $stock->update([
                    'quantity' => $stock->quantity + $validated['quantity'],
                    'expired_date' => $validated['expired_date'] ?? $stock->expired_date,
                ]);
            } else {
                InventoryStock::create([
                    'produk_id' => $validated['produk_id'],
                    'quantity' => $validated['quantity'],
                    'batch_number' => $batch,
                    'location' => $location,
                    'expired_date' => $validated['expired_date'] ?? null,
                ]);
            }
        });

        return redirect()->route('scm.saldo-stok.index')->with('success', 'Penyesuaian stok tersimpan');
    }

    /**
     * Lookup material untuk dropdown scrap/usage.
     */
    public function materials()
    {
        return response()->json(
            Produk::aktif()->whereIn('item_type', ['rm', 'pm'])->with('uom')->get(['id', 'kode_produk', 'nama_produk', 'item_type', 'uom_id'])
        );
    }

    /**
     * Preview inventory stock from Odoo (JSON).
     */
    public function previewOdoo(OdooService $odooService)
    {
        if (! auth()->user()->hasAnyRole(['warehouse_admin', 'superadmin', 'admin', 'ppic', 'manager'])) {
            abort(403, 'Akses ditolak.');
        }

        try {
            $stocks = $odooService->fetchInventoryStocksPreview();

            return response()->json([
                'success' => true,
                'data' => $stocks,
                'count' => count($stocks),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data stok dari Odoo: '.$e->getMessage(),
            ], 500);
        }
    }

    public function syncFromOdoo(Request $request, OdooService $odooService)
    {
        if (! auth()->user()->hasAnyRole(['warehouse_admin', 'superadmin', 'admin', 'ppic'])) {
            abort(403, 'Akses ditolak.');
        }

        $selectedIds = $request->input('selected_ids');
        if (is_array($selectedIds) && empty($selectedIds)) {
            $err = 'Silakan pilih setidaknya satu produk/stok untuk disinkronkan.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $err], 422);
            }

            return back()->withErrors(['stock' => $err]);
        }

        try {
            $summary = $odooService->syncInventoryStocks(is_array($selectedIds) ? $selectedIds : null);
            $msg = "Sync Saldo Stok Odoo selesai: {$summary['created']} dibuat, {$summary['updated']} diperbarui, {$summary['skipped']} dilewati.";
            if (! empty($summary['errors'])) {
                $msg .= ' Error: '.implode(', ', $summary['errors']);
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'summary' => $summary,
                ]);
            }

            return back()->with('success', $msg);
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Gagal sync Saldo Stok Odoo: '.$e->getMessage()], 500);
            }

            return back()->with('error', 'Gagal sync Saldo Stok Odoo: '.$e->getMessage());
        }
    }
}
