<?php

namespace App\Http\Controllers\SCM;

use App\Http\Controllers\Controller;
use App\Models\InventoryStock;
use App\Models\MaterialScrap;
use App\Models\Produk;
use App\Models\ScmUom;
use App\Models\WeeklyPlan;
use App\Services\OdooService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MaterialScrapController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $source = $request->input('source');

        $scraps = MaterialScrap::with(['produk', 'uom', 'weeklyPlan', 'creator'])
            ->when($search, function ($q, $s) {
                $q->where('batch_number', 'like', "%{$s}%")
                    ->orWhere('material_name', 'like', "%{$s}%");
            })
            ->when(in_array($source, ['odoo', 'manual'], true), fn ($q) => $q->where('source', $source))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('SCM/Inventory/ScrapMaterial/Index', [
            'scraps' => $scraps,
            'search' => $search,
            'source' => $source,
            'plans' => WeeklyPlan::with('produk')->latest('tanggal')->limit(50)->get(),
            'materials' => Produk::aktif()->whereIn('item_type', ['rm', 'pm'])->get(['id', 'kode_produk', 'nama_produk']),
            'uoms' => ScmUom::all(['id', 'code', 'name']),
        ]);
    }

    public function create()
    {
        $plans = WeeklyPlan::with('produk')->latest('tanggal')->limit(50)->get();
        $materials = Produk::aktif()->whereIn('item_type', ['rm', 'pm'])->get(['id', 'kode_produk', 'nama_produk']);
        $uoms = ScmUom::all(['id', 'code', 'name']);

        return Inertia::render('SCM/Inventory/ScrapMaterial/Create', [
            'plans' => $plans,
            'materials' => $materials,
            'uoms' => $uoms,
        ]);
    }

    public function store(Request $request)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya memiliki akses baca.');
        }

        $validated = $request->validate([
            'weekly_plan_id' => 'nullable|exists:weekly_plans,id',
            'batch_number' => 'required|string|max:255',
            'produk_id' => 'nullable|exists:produks,id',
            'material_name' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:0.001',
            'uom_id' => 'nullable|exists:scm_uoms,id',
            'defect_reason' => 'nullable|string|max:255',
        ]);

        $scrap = DB::transaction(function () use ($validated) {
            $scrap = MaterialScrap::create([
                ...$validated,
                'source' => 'manual',
                'odoo_scrap_id' => null,
                'created_by' => auth()->id(),
            ]);

            if (($validated['produk_id'] ?? null) && $validated['quantity'] > 0) {
                $stock = InventoryStock::where('produk_id', $validated['produk_id'])
                    ->where('batch_number', $validated['batch_number'])
                    ->lockForUpdate()
                    ->first()
                    ?? InventoryStock::where('produk_id', $validated['produk_id'])->orderBy('quantity', 'desc')->lockForUpdate()->first();

                if ($stock) {
                    $stock->decrement('quantity', $validated['quantity']);
                } else {
                    InventoryStock::create([
                        'produk_id' => $validated['produk_id'],
                        'quantity' => -$validated['quantity'],
                        'batch_number' => $validated['batch_number'],
                        'location' => 'GUDANG-UTAMA',
                    ]);
                }
            }

            return $scrap;
        });

        return redirect()->route('scm.scrap-material.index')->with('success', 'Scrap material (manual) tersimpan');
    }

    /**
     * Pull scrap material dari Odoo (arah Odoo → lokal).
     */
    public function pullFromOdoo(OdooService $odoo)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya memiliki akses baca.');
        }

        try {
            $result = $odoo->syncMoScraps();

            return redirect()->route('scm.scrap-material.index', ['source' => 'odoo'])
                ->with('success', sprintf(
                    'Pull scrap Odoo: %d baru, %d dilewati',
                    $result['inserted'],
                    $result['skipped']
                ));
        } catch (\Exception $e) {
            return back()->with('error', 'Pull scrap Odoo gagal: '.$e->getMessage());
        }
    }

    public function destroy(MaterialScrap $materialScrap)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya memiliki akses baca.');
        }

        if ($materialScrap->source === 'odoo') {
            return back()->withErrors(['error' => 'Scrap dari Odoo tidak bisa dihapus lokal — koreksi di Odoo.']);
        }

        DB::transaction(function () use ($materialScrap) {
            if ($materialScrap->produk_id) {
                $stock = InventoryStock::where('produk_id', $materialScrap->produk_id)
                    ->where('batch_number', $materialScrap->batch_number)
                    ->lockForUpdate()
                    ->first();
                $stock?->increment('quantity', $materialScrap->quantity);
            }
            $materialScrap->delete();
        });

        return back()->with('success', 'Scrap material dihapus dan stok dikembalikan');
    }
}
