<?php

namespace App\Http\Controllers;

use App\Models\Produk;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProdukController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $produks = Produk::query()
            ->when($search, fn ($q, $s) => $q->where('kode_produk', 'like', "%{$s}%")
                ->orWhere('nama_produk', 'like', "%{$s}%"))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Produk/Index', [
            'produks' => $produks,
        ]);
    }

    public function create()
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }

        return Inertia::render('Admin/Produk/Create');
    }

    public function store(Request $request)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }
        $validated = $request->validate([
            'kode_produk' => 'required|string|max:255|unique:produks,kode_produk',
            'nama_produk' => 'required|string|max:255',
            'proses_default' => 'required|in:mixing,filling,packing',
        ]);

        Produk::create($validated);

        return redirect()->route('admin.produk.index')
            ->with('success', 'Produk berhasil ditambahkan');
    }

    public function edit(Produk $produk)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }

        return Inertia::render('Admin/Produk/Edit', [
            'produk' => $produk,
        ]);
    }

    public function update(Request $request, Produk $produk)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }
        $validated = $request->validate([
            'kode_produk' => 'required|string|max:255|unique:produks,kode_produk,'.$produk->id,
            'nama_produk' => 'required|string|max:255',
            'proses_default' => 'required|in:mixing,filling,packing',
            'status_aktif' => 'boolean',
        ]);

        $produk->update($validated);

        return redirect()->route('admin.produk.index')
            ->with('success', 'Produk berhasil diupdate');
    }

    public function destroy(Produk $produk)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }

        if ($produk->laporanHarians()->exists() || $produk->weeklyPlans()->exists()) {
            return redirect()->route('admin.produk.index')
                ->withErrors(['error' => "Produk \"{$produk->nama_produk}\" tidak dapat dihapus karena sudah terhubung ke data Laporan Harian atau Rencana Produksi."]);
        }

        try {
            $produk->delete();

            return redirect()->route('admin.produk.index')
                ->with('success', 'Produk berhasil dihapus.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.produk.index')
                ->withErrors(['error' => 'Gagal menghapus produk: '.$e->getMessage()]);
        }
    }
}
