<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkCenter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkCenterController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', '');

        $workCenters = WorkCenter::with(['mesins', 'lines'])
            ->when($search, function ($q, $s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%")
                    ->orWhere('type', 'like', "%{$s}%");
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/WorkCenter/Index', [
            'workCenters' => $workCenters,
            'search' => $search,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/WorkCenter/Create', [
            'types' => [
                ['value' => 'mixing', 'label' => 'Mixing'],
                ['value' => 'filling', 'label' => 'Filling'],
                ['value' => 'secondary', 'label' => 'Secondary (Packing/Labeling)'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:work_centers,code',
            'name' => 'required|string|max:100',
            'type' => 'required|in:mixing,filling,secondary',
            'standard_ct_seconds' => 'nullable|integer|min:0',
            'fit_mp' => 'nullable|integer|min:0',
            'shift_hours' => 'nullable|numeric|min:0|max:24',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        WorkCenter::create($validated);

        return redirect()->route('admin.work-center.index')
            ->with('success', 'Work Center berhasil dibuat');
    }

    public function show(WorkCenter $workCenter)
    {
        $workCenter->load(['mesins', 'lines', 'weeklyPlans.produk', 'laporanHarians.produk']);

        return Inertia::render('Admin/WorkCenter/Show', [
            'workCenter' => $workCenter,
        ]);
    }

    public function edit(WorkCenter $workCenter)
    {
        return Inertia::render('Admin/WorkCenter/Edit', [
            'workCenter' => $workCenter,
            'types' => [
                ['value' => 'mixing', 'label' => 'Mixing'],
                ['value' => 'filling', 'label' => 'Filling'],
                ['value' => 'secondary', 'label' => 'Secondary (Packing/Labeling)'],
            ],
        ]);
    }

    public function update(Request $request, WorkCenter $workCenter)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:work_centers,code,'.$workCenter->id,
            'name' => 'required|string|max:100',
            'type' => 'required|in:mixing,filling,secondary',
            'standard_ct_seconds' => 'nullable|integer|min:0',
            'fit_mp' => 'nullable|integer|min:0',
            'shift_hours' => 'nullable|numeric|min:0|max:24',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $workCenter->update($validated);

        return redirect()->route('admin.work-center.index')
            ->with('success', 'Work Center berhasil diperbarui');
    }

    public function destroy(WorkCenter $workCenter)
    {
        $workCenter->delete();

        return redirect()->route('admin.work-center.index')
            ->with('success', 'Work Center berhasil dihapus');
    }

    public function capacityInfo(WorkCenter $workCenter)
    {
        $info = \App\Services\TargetCalculationService::getCapacityInfo($workCenter);

        return response()->json($info);
    }
};