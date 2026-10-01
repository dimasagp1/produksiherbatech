<?php

namespace App\Http\Controllers\SCM;

use App\Http\Controllers\Controller;
use App\Models\Bom;
use App\Models\Produk;
use App\Models\ScmUom;
use App\Services\OdooService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BomController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', '');

        $boms = Bom::with(['produk', 'items.uom', 'items.materialProduk'])
            ->when($search, function ($q, $s) {
                $q->whereHas('produk', fn ($qq) => $qq->where('nama_produk', 'like', "%{$s}%"))
                    ->orWhere('version', 'like', "%{$s}%");
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('SCM/MasterData/BOMs/Index', [
            'boms' => $boms,
            'search' => $search,
            'produks' => Produk::aktif()->get(['id', 'kode_produk', 'nama_produk', 'item_type']),
            'uoms' => ScmUom::all(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya memiliki akses baca.');
        }

        $validated = $request->validate([
            'produk_id' => 'required|exists:produks,id',
            'version' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.material_produk_id' => 'nullable|exists:produks,id',
            'items.*.material_name' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.uom_id' => 'nullable|exists:scm_uoms,id',
        ]);

        $hasActive = Bom::where('produk_id', $validated['produk_id'])->where('is_active', true)->exists();

        $bom = DB::transaction(function () use ($validated, $hasActive) {
            if ($hasActive) {
                Bom::where('produk_id', $validated['produk_id'])->where('is_active', true)->update(['is_active' => false]);
            }

            $bom = Bom::create([
                'produk_id' => $validated['produk_id'],
                'version' => $validated['version'] ?? '1',
                'is_active' => true,
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                $bom->items()->create([
                    'material_produk_id' => $item['material_produk_id'] ?? null,
                    'material_name' => $item['material_name'],
                    'quantity' => $item['quantity'],
                    'uom_id' => $item['uom_id'] ?? null,
                ]);
            }

            return $bom;
        });

        return redirect()->route('scm.bom.index')->with('success', "BOM v{$bom->version} untuk produk berhasil dibuat");
    }

    public function update(Request $request, Bom $bom)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya memiliki akses baca.');
        }

        $validated = $request->validate([
            'version' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'items' => 'sometimes|array|min:1',
            'items.*.material_produk_id' => 'nullable|exists:produks,id',
            'items.*.material_name' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.uom_id' => 'nullable|exists:scm_uoms,id',
        ]);

        DB::transaction(function () use ($bom, $validated) {
            if (array_key_exists('is_active', $validated) && $validated['is_active']) {
                Bom::where('produk_id', $bom->produk_id)->where('id', '!=', $bom->id)->update(['is_active' => false]);
            }

            $bom->update(array_intersect_key($validated, array_flip(['version', 'notes', 'is_active'])));

            if (isset($validated['items'])) {
                $bom->items()->delete();
                foreach ($validated['items'] as $item) {
                    $bom->items()->create([
                        'material_produk_id' => $item['material_produk_id'] ?? null,
                        'material_name' => $item['material_name'],
                        'quantity' => $item['quantity'],
                        'uom_id' => $item['uom_id'] ?? null,
                    ]);
                }
            }
        });

        return redirect()->route('scm.bom.index')->with('success', 'BOM berhasil diupdate');
    }

    public function destroy(Bom $bom)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya memiliki akses baca.');
        }

        $bom->update(['is_active' => false]);
        $bom->delete();

        return redirect()->route('scm.bom.index')->with('success', 'BOM berhasil dihapus');
    }

    public function syncFromOdoo(OdooService $odooService)
    {
        if (! auth()->user()->hasAnyRole(['warehouse_admin', 'superadmin', 'admin', 'ppic', 'spv'])) {
            abort(403, 'Akses ditolak.');
        }

        try {
            $summary = $odooService->syncBoms();
            $msg = "Sync BOM Odoo selesai: {$summary['created']} dibuat, {$summary['updated']} diperbarui, {$summary['skipped']} dilewati.";
            if (! empty($summary['errors'])) {
                $msg .= ' Error: '.implode(', ', $summary['errors']);
            }

            return back()->with('success', $msg);
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal sync BOM Odoo: '.$e->getMessage());
        }
    }
}
