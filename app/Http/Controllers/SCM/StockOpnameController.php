<?php

namespace App\Http\Controllers\SCM;

use App\Http\Controllers\Controller;
use App\Models\InventoryStock;
use App\Models\Setting;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Services\OdooService;
use App\Services\ScmNumberService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StockOpnameController extends Controller
{
    public function __construct(private ScmNumberService $numbers) {}

    public function index(Request $request)
    {
        $status = $request->input('status');

        $opnames = StockOpname::with(['initiator', 'approver'])
            ->when(in_array($status, ['initiated', 'counted', 'recalculated', 'posted', 'approved', 'cancelled'], true), fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('SCM/StockOpname/Index', [
            'opnames' => $opnames,
            'status' => $status,
            'iraTarget' => (float) Setting::get('ira_target', 98),
        ]);
    }

    /**
     * Initiate opname — snapshot saldo stok sistem per lokasi.
     */
    public function store(Request $request)
    {
        if (! auth()->user()->hasAnyRole(['warehouse_admin', 'superadmin', 'admin'])) {
            abort(403, 'Hanya warehouse_admin/admin/superadmin yang bisa initiate opname.');
        }

        $validated = $request->validate([
            'location' => 'nullable|string|max:100',
            'item_type' => 'nullable|string|in:all,fg,rm,pm,wip',
            'notes' => 'nullable|string',
        ]);

        $location = $validated['location'] ?: 'GUDANG-UTAMA';
        $itemType = $validated['item_type'] ?? 'all';

        $opname = DB::transaction(function () use ($validated, $location, $itemType) {
            $notes = $validated['notes'] ?? null;
            if ($itemType && $itemType !== 'all') {
                $typeLabel = match ($itemType) {
                    'fg' => 'Finished Goods (FG)',
                    'rm' => 'Raw Material (RM / Bahan Baku)',
                    'pm' => 'Packaging Material (PM / Kemas)',
                    'wip' => 'WIP',
                    default => strtoupper($itemType),
                };
                $notes = trim("Kategori: {$typeLabel}. ".($notes ?? ''));
            }

            $opname = StockOpname::create([
                'opname_number' => $this->numbers->stockOpname(),
                'status' => 'initiated',
                'location' => $location,
                'initiated_by' => auth()->id(),
                'initiated_at' => now(),
                'notes' => $notes,
            ]);

            $stocks = InventoryStock::with('produk.uom')
                ->where('location', $location)
                ->when($itemType && $itemType !== 'all', function ($q) use ($itemType) {
                    $q->whereHas('produk', fn ($qq) => $qq->where('item_type', $itemType));
                })
                ->get();

            foreach ($stocks as $stock) {
                $opname->items()->create([
                    'produk_id' => $stock->produk_id,
                    'batch_number' => $stock->batch_number,
                    'system_qty' => $stock->quantity,
                    'counted_qty' => null,
                    'discrepancy' => null,
                    'uom_id' => $stock->produk?->uom_id,
                    'stelling_card' => sprintf('ST-%s-%s', $opname->opname_number, $stock->produk?->kode_produk ?? $stock->produk_id),
                ]);
            }

            return $opname;
        });

        return redirect()->route('scm.stock-opname.show', $opname->id)
            ->with('success', "Opname {$opname->opname_number} dibuat ({$opname->items()->count()} baris snapshot stok Odoo)");
    }

    public function show(StockOpname $stockOpname)
    {
        $stockOpname->load(['items.produk.uom', 'initiator', 'approver']);

        $items = $stockOpname->items;
        $counted = $items->filter(fn ($i) => $i->counted_qty !== null);
        $accurate = $counted->filter(fn ($i) => abs((float) $i->counted_qty - (float) $i->system_qty) < 0.001);

        $ira = $counted->count() > 0 ? round(($accurate->count() / $counted->count()) * 100, 2) : null;

        // Discrepancy Value Rate = nilai selisih / total persediaan (COA 11300010/11300030/11300040)
        $totalSelisihQty = $items->sum(fn ($i) => abs((float) ($i->discrepancy ?? 0)));
        $totalSystemQty = $items->sum('system_qty');
        $dvr = $totalSystemQty > 0 ? round(($totalSelisihQty / $totalSystemQty) * 100, 2) : 0.0;

        $typeBreakdown = [
            'all' => ['total' => $items->count(), 'counted' => $counted->count(), 'accurate' => $accurate->count()],
            'fg' => ['total' => 0, 'counted' => 0, 'accurate' => 0],
            'rm' => ['total' => 0, 'counted' => 0, 'accurate' => 0],
            'pm' => ['total' => 0, 'counted' => 0, 'accurate' => 0],
            'wip' => ['total' => 0, 'counted' => 0, 'accurate' => 0],
        ];

        foreach ($items as $it) {
            $t = $it->produk?->item_type ?: 'fg';
            if (! isset($typeBreakdown[$t])) {
                $typeBreakdown[$t] = ['total' => 0, 'counted' => 0, 'accurate' => 0];
            }
            $typeBreakdown[$t]['total']++;
            if ($it->counted_qty !== null) {
                $typeBreakdown[$t]['counted']++;
                if (abs((float) $it->counted_qty - (float) $it->system_qty) < 0.001) {
                    $typeBreakdown[$t]['accurate']++;
                }
            }
        }

        return Inertia::render('SCM/StockOpname/Show', [
            'opname' => $stockOpname,
            'items' => $items->map(fn ($i) => [
                ...$i->toArray(),
                'produk' => $i->produk,
            ]),
            'typeBreakdown' => $typeBreakdown,
            'metrics' => [
                'ira' => $ira,
                'ira_target' => (float) Setting::get('ira_target', 98),
                'discrepancy_value_rate' => $dvr,
                'counted_count' => $counted->count(),
                'accurate_count' => $accurate->count(),
                'total_items' => $items->count(),
                'coa' => [
                    Setting::get('inventory_coa_1', '11300010'),
                    Setting::get('inventory_coa_2', '11300030'),
                    Setting::get('inventory_coa_3', '11300040'),
                ],
            ],
        ]);
    }

    /**
     * Input counted qty per item → status counted.
     */
    public function updateItems(Request $request, StockOpname $stockOpname)
    {
        if (! auth()->user()->hasAnyRole(['warehouse_admin', 'superadmin', 'admin'])) {
            abort(403, 'Anda tidak memiliki akses menginput hasil hitung.');
        }

        if (! in_array($stockOpname->status, ['initiated', 'counted'], true)) {
            return back()->withErrors(['error' => 'Opname sudah melewati tahap input hitung.']);
        }

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:stock_opname_items,id',
            'items.*.counted_qty' => 'required|numeric|min:0',
            'items.*.discrepancy_reason' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($stockOpname, $validated) {
            foreach ($validated['items'] as $row) {
                $item = StockOpnameItem::where('stock_opname_id', $stockOpname->id)->findOrFail($row['id']);
                $counted = (float) $row['counted_qty'];
                $item->update([
                    'counted_qty' => $counted,
                    'discrepancy' => $counted - (float) $item->system_qty,
                    'discrepancy_reason' => $row['discrepancy_reason'] ?? $item->discrepancy_reason,
                ]);
            }

            $stockOpname->update(['status' => 'counted']);
        });

        return redirect()->route('scm.stock-opname.show', $stockOpname->id)
            ->with('success', 'Hasil hitung tersimpan. Lanjut Recalculate.');
    }

    /**
     * Recalculate IRA + discrepancy value rate → status recalculated.
     */
    public function recalculate(StockOpname $stockOpname)
    {
        if (! in_array($stockOpname->status, ['counted', 'recalculated'], true)) {
            return back()->withErrors(['error' => 'Recalculate hanya setelah counted.']);
        }

        $items = $stockOpname->items()->get();
        $counted = $items->filter(fn ($i) => $i->counted_qty !== null);

        if ($counted->count() === 0) {
            return back()->withErrors(['error' => 'Belum ada baris yang dihitung.']);
        }

        $accurate = $counted->filter(fn ($i) => abs((float) $i->counted_qty - (float) $i->system_qty) < 0.001);
        $ira = round(($accurate->count() / $counted->count()) * 100, 2);

        $totalSelisihQty = $items->sum(fn ($i) => abs((float) ($i->discrepancy ?? 0)));
        $totalSystemQty = $items->sum('system_qty') ?: 1;
        $dvr = round(($totalSelisihQty / $totalSystemQty) * 100, 2);

        $stockOpname->update([
            'status' => 'recalculated',
            'ira_persen' => $ira,
            'discrepancy_value_rate' => $dvr,
        ]);

        return redirect()->route('scm.stock-opname.show', $stockOpname->id)
            ->with('success', "Recalculate selesai — IRA {$ira}% (target ".Setting::get('ira_target', 98)."%), DVR {$dvr}%");
    }

    /**
     * Post Adjustment — sinkronkan inventory_stocks ke hasil hitung (read-only + notif).
     */
    public function postAdjustment(Request $request, StockOpname $stockOpname, OdooService $odoo)
    {
        if (! in_array($stockOpname->status, ['recalculated'], true)) {
            return back()->withErrors(['error' => 'Post Adjustment hanya setelah recalculate.']);
        }

        $writeback = null;

        DB::transaction(function () use ($stockOpname) {
            foreach ($stockOpname->items()->get() as $item) {
                if ($item->counted_qty === null) {
                    continue;
                }

                $stock = InventoryStock::where('produk_id', $item->produk_id)
                    ->where('location', $stockOpname->location)
                    ->where(function ($q) use ($item) {
                        $item->batch_number === null
                            ? $q->whereNull('batch_number')
                            : $q->where('batch_number', $item->batch_number);
                    })
                    ->lockForUpdate()
                    ->first();

                if ($stock) {
                    $stock->update(['quantity' => (float) $item->counted_qty]);
                } elseif ((float) $item->counted_qty != 0.0) {
                    InventoryStock::create([
                        'produk_id' => $item->produk_id,
                        'quantity' => (float) $item->counted_qty,
                        'batch_number' => $item->batch_number,
                        'location' => $stockOpname->location,
                    ]);
                }
            }

            $stockOpname->update(['status' => 'posted']);
        });

        if (filter_var(Setting::get('odoo_inventory_writeback_enabled', '0'), FILTER_VALIDATE_BOOLEAN)) {
            $lines = $stockOpname->items()->whereNotNull('counted_qty')->get()
                ->map(fn ($i) => ['produk_id' => $i->produk_id, 'quantity' => (float) $i->counted_qty])
                ->all();
            $writeback = $odoo->postInventoryAdjustment($lines);
        }

        $message = 'Adjustment diposting ke stock ledger lokal. Menunggu approve manager.';
        if ($writeback !== null) {
            $message .= ' Odoo: '.($writeback['message'] ?? '-');
        }

        return redirect()->route('scm.stock-opname.show', $stockOpname->id)->with('success', $message);
    }

    public function approve(Request $request, StockOpname $stockOpname)
    {
        if (! auth()->user()->hasAnyRole(['manager', 'superadmin'])) {
            abort(403, 'Hanya manager/superadmin yang bisa approve opname.');
        }

        if ($stockOpname->status !== 'posted') {
            return back()->withErrors(['error' => 'Approve hanya setelah Post Adjustment.']);
        }

        $stockOpname->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return redirect()->route('scm.stock-opname.show', $stockOpname->id)
            ->with('success', 'Opname disetujui.');
    }

    /**
     * Cetak Kartu Stelling (PDF).
     */
    public function printKartuStelling(StockOpname $stockOpname)
    {
        $stockOpname->load(['items.produk.uom', 'initiator']);

        $pdf = Pdf::loadView('pdf.kartu-stelling', ['opname' => $stockOpname]);

        return $pdf->download('kartu-stelling-'.str_replace('/', '-', $stockOpname->opname_number).'.pdf');
    }

    public function destroy(StockOpname $stockOpname)
    {
        if (! in_array($stockOpname->status, ['initiated', 'counted'], true)) {
            return back()->withErrors(['error' => 'Opname yang sudah posted tidak bisa dibatalkan.']);
        }

        $stockOpname->update(['status' => 'cancelled']);

        return redirect()->route('scm.stock-opname.index')->with('success', 'Opname dibatalkan');
    }
}
