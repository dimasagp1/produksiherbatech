<?php

namespace App\Http\Controllers\SCM;

use App\Http\Controllers\Controller;
use App\Models\Fleet;
use Illuminate\Http\Request;

class FleetController extends Controller
{
    public function index()
    {
        return inertia('SCM/Delivery/Fleet', [
            'fleets' => Fleet::latest('id')->paginate(20),
        ]);
    }

    public function store(Request $request)
    {
        if (! auth()->user()->hasAnyRole(['warehouse_admin', 'superadmin', 'admin'])) {
            abort(403, 'Anda tidak memiliki akses mengelola armada.');
        }

        $validated = $request->validate([
            'kode_armada' => 'required|string|max:50|unique:fleets,kode_armada',
            'nama_armada' => 'required|string|max:255',
            'type' => 'nullable|string|max:50',
            'plat_number' => 'nullable|string|max:30',
            'driver_name' => 'nullable|string|max:255',
            'driver_phone' => 'nullable|string|max:30',
            'is_active' => 'sometimes|boolean',
        ]);

        Fleet::create($validated);

        return back()->with('success', 'Armada ditambahkan');
    }

    public function update(Request $request, Fleet $fleet)
    {
        if (! auth()->user()->hasAnyRole(['warehouse_admin', 'superadmin', 'admin'])) {
            abort(403, 'Anda tidak memiliki akses mengelola armada.');
        }

        $validated = $request->validate([
            'nama_armada' => 'sometimes|string|max:255',
            'type' => 'nullable|string|max:50',
            'plat_number' => 'nullable|string|max:30',
            'driver_name' => 'nullable|string|max:255',
            'driver_phone' => 'nullable|string|max:30',
            'is_active' => 'sometimes|boolean',
        ]);

        $fleet->update($validated);

        return back()->with('success', 'Armada diperbarui');
    }

    public function destroy(Fleet $fleet)
    {
        if (! auth()->user()->hasAnyRole(['warehouse_admin', 'superadmin', 'admin'])) {
            abort(403, 'Anda tidak memiliki akses mengelola armada.');
        }

        $fleet->update(['is_active' => false]);
        $fleet->delete();

        return back()->with('success', 'Armada dihapus');
    }
}
