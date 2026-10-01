<?php

namespace App\Http\Controllers;

use App\Models\Mesin;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MesinController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $mesins = Mesin::query()
            ->when($search, fn ($q, $s) => $q->where('nama_mesin', 'like', "%{$s}%"))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Mesin/Index', [
            'mesins' => $mesins,
        ]);
    }

    public function create()
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }

        return Inertia::render('Admin/Mesin/Create');
    }

    public function store(Request $request)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }
        $validated = $request->validate([
            'nama_mesin' => 'required|string|max:255',
            'ct' => 'required|numeric|min:0',
        ]);

        Mesin::create($validated);

        return redirect()->route('admin.mesin.index')
            ->with('success', 'Mesin berhasil ditambahkan');
    }

    public function edit(Mesin $mesin)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }

        return Inertia::render('Admin/Mesin/Edit', [
            'mesin' => $mesin,
        ]);
    }

    public function update(Request $request, Mesin $mesin)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }
        $validated = $request->validate([
            'nama_mesin' => 'required|string|max:255',
            'ct' => 'required|numeric|min:0',
            'status_aktif' => 'boolean',
        ]);

        $mesin->update($validated);

        return redirect()->route('admin.mesin.index')
            ->with('success', 'Mesin berhasil diupdate');
    }

    public function destroy(Mesin $mesin)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }
        $mesin->delete();

        return redirect()->route('admin.mesin.index')
            ->with('success', 'Mesin berhasil dihapus');
    }
}
