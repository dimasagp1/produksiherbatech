<?php

namespace App\Http\Controllers;

use App\Models\Line;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LineController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $lines = Line::query()
            ->when($search, fn ($q, $s) => $q->where('kode_line', 'like', "%{$s}%")
                ->orWhere('nama_line', 'like', "%{$s}%"))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Line/Index', [
            'lines' => $lines,
        ]);
    }

    public function create()
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }

        return Inertia::render('Admin/Line/Create');
    }

    public function store(Request $request)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }
        $validated = $request->validate([
            'kode_line' => 'required|string|max:255|unique:lines,kode_line',
            'nama_line' => 'required|string|max:255',
        ]);

        Line::create($validated);

        return redirect()->route('admin.line.index')
            ->with('success', 'Line berhasil ditambahkan');
    }

    public function edit(Line $line)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }

        return Inertia::render('Admin/Line/Edit', [
            'line' => $line,
        ]);
    }

    public function update(Request $request, Line $line)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }
        $validated = $request->validate([
            'kode_line' => 'required|string|max:255|unique:lines,kode_line,'.$line->id,
            'nama_line' => 'required|string|max:255',
            'status_aktif' => 'boolean',
        ]);

        $line->update($validated);

        return redirect()->route('admin.line.index')
            ->with('success', 'Line berhasil diupdate');
    }

    public function destroy(Line $line)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }
        $line->delete();

        return redirect()->route('admin.line.index')
            ->with('success', 'Line berhasil dihapus');
    }
}
