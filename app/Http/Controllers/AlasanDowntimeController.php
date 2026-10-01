<?php

namespace App\Http\Controllers;

use App\Models\AlasanDowntime;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AlasanDowntimeController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $alasanDowntimes = AlasanDowntime::query()
            ->when($search, fn ($q, $s) => $q->where('nama_alasan', 'like', "%{$s}%"))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/AlasanDowntime/Index', [
            'alasanDowntimes' => $alasanDowntimes,
        ]);
    }

    public function create()
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }

        return Inertia::render('Admin/AlasanDowntime/Create');
    }

    public function store(Request $request)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }
        $validated = $request->validate([
            'nama_alasan' => 'required|string|max:255',
            'tipe_input' => 'required|in:manual,default_hardcode',
            'durasi_default_menit' => 'nullable|integer|min:0',
        ]);

        AlasanDowntime::create($validated);

        return redirect()->route('admin.alasan-downtime.index')
            ->with('success', 'Alasan downtime berhasil ditambahkan');
    }

    public function edit(AlasanDowntime $alasanDowntime)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }

        return Inertia::render('Admin/AlasanDowntime/Edit', [
            'alasanDowntime' => $alasanDowntime,
        ]);
    }

    public function update(Request $request, AlasanDowntime $alasanDowntime)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }
        $validated = $request->validate([
            'nama_alasan' => 'required|string|max:255',
            'tipe_input' => 'required|in:manual,default_hardcode',
            'durasi_default_menit' => 'nullable|integer|min:0',
            'status_aktif' => 'boolean',
        ]);

        $alasanDowntime->update($validated);

        return redirect()->route('admin.alasan-downtime.index')
            ->with('success', 'Alasan downtime berhasil diupdate');
    }

    public function destroy(AlasanDowntime $alasanDowntime)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }
        $alasanDowntime->delete();

        return redirect()->route('admin.alasan-downtime.index')
            ->with('success', 'Alasan downtime berhasil dihapus');
    }
}
