<?php

namespace App\Http\Controllers;

use App\Models\Line;
use App\Models\Produk;
use App\Models\Setting;
use App\Models\WeeklyPlan;
use App\Models\WorkCenter;
use App\Services\TargetCalculationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WeeklyPlanController extends Controller
{
    public function index()
    {
        $weeklyPlans = WeeklyPlan::with(['produk', 'creator', 'line', 'workCenter'])
            ->boardVisible()
            ->latest('tanggal')
            ->get();

        $produks = Produk::aktif()->get();
        $workCenters = WorkCenter::active()->get();

        return Inertia::render('PPIC/WeeklyPlan/Index', [
            'weeklyPlans' => $weeklyPlans,
            'produks' => $produks,
            'lines' => Line::aktif()->get(),
            'workCenters' => $workCenters,
            'targetOutputMultiplier' => (int) Setting::get('target_output_multiplier', 2000),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'produk_id' => 'required|exists:produks,id',
            'line_id' => 'nullable|exists:lines,id',
            'work_center_id' => 'required|exists:work_centers,id',
            'proses' => 'required|in:mixing,filling,secondary',
            'batch_number' => 'required|string|max:255',
            'tanggal' => 'required|date',
            'mp_count' => 'nullable|integer|min:0',
            'target_output' => 'nullable|integer|min:0',
            'odoo_mo_id' => 'nullable|integer',
            'mo_status' => 'nullable|string',
        ]);

        // Audit gap: process chain Mixing → Filling → Secondary harus urut
        $processOrder = WeeklyPlan::processOrderMap();
        $currentOrder = $processOrder[$validated['proses']] ?? -1;
        if ($currentOrder > 0) {
            $prevProses = array_search($currentOrder - 1, $processOrder, true);
            $prevExists = WeeklyPlan::where('produk_id', $validated['produk_id'])
                ->where('tanggal', $validated['tanggal'])
                ->where('proses', $prevProses)
                ->whereIn('status', ['draft', 'aktif'])
                ->exists();
            if (! $prevExists) {
                return back()->withErrors([
                    'proses' => ucfirst($prevProses).' harus direncanakan terlebih dahulu sebelum '.ucfirst($validated['proses']).'.',
                ]);
            }
        }

        // Jika batch ini berasal dari Odoo MO yang tersimpan di draft tanggal lain, pindahkan / petakan tanggalnya
        $existingDraft = WeeklyPlan::where('batch_number', $validated['batch_number'])
            ->where('produk_id', $validated['produk_id'])
            ->where('status', 'draft')
            ->first();

        $workCenter = WorkCenter::findOrFail($validated['work_center_id']);
        $mpCount = (int) ($validated['mp_count'] ?? 0);
        $targetOutput = ! empty($validated['target_output'])
            ? (int) $validated['target_output']
            : ($existingDraft?->target_output ?: TargetCalculationService::calculateTarget($workCenter, $mpCount));

        if ($existingDraft && $existingDraft->tanggal !== $validated['tanggal']) {
            $existingDraft->update([
                'tanggal' => $validated['tanggal'],
                'line_id' => $validated['line_id'] ?? null,
                'work_center_id' => $validated['work_center_id'],
                'proses' => $validated['proses'],
                'mp_count' => $mpCount,
                'target_output' => $targetOutput,
            ]);

            return redirect()->route('ppic.weekly-plan.index')
                ->with('success', 'Batch Odoo berhasil dipetakan ke tanggal '.$validated['tanggal']);
        }

        // Batch number unik per tanggal (boleh sama di tanggal berbeda)
        $isDuplicate = WeeklyPlan::where('batch_number', $validated['batch_number'])
            ->where('tanggal', $validated['tanggal'])
            ->whereIn('status', ['draft', 'aktif'])
            ->exists();
        if ($isDuplicate) {
            return back()->withErrors(['batch_number' => 'Batch number sudah digunakan pada tanggal tersebut.']);
        }

        // Validasi: produk + tanggal maksimal 3 proses (mixing+filling+secondary) — hitung draft+aktif, ignore soft-deleted & selesai
        $existingCount = WeeklyPlan::where('produk_id', $validated['produk_id'])
            ->where('tanggal', $validated['tanggal'])
            ->whereIn('status', ['draft', 'aktif'])
            ->count();
        if ($existingCount >= 3) {
            return back()->withErrors(['produk_id' => 'Produk tersebut pada tanggal ini sudah memiliki maksimal 3 proses.']);
        }

        // Validasi: proses tidak boleh duplikat untuk produk+tanggal yang sama (hanya hitung draft/aktif)
        $duplicateProses = WeeklyPlan::where('produk_id', $validated['produk_id'])
            ->where('tanggal', $validated['tanggal'])
            ->where('proses', $validated['proses'])
            ->whereIn('status', ['draft', 'aktif'])
            ->exists();
        if ($duplicateProses) {
            return back()->withErrors(['proses' => 'Proses '.ucfirst($validated['proses']).' sudah ada untuk produk ini pada tanggal tersebut.']);
        }

        $validated['created_by'] = auth()->id();
        $validated['mp_count'] = $mpCount;
        $validated['multiplier'] = $workCenter->fit_mp ?: 1;
        $validated['line_id'] = $validated['line_id'] ?? null;
        $validated['mo_status'] = $validated['mo_status'] ?? WeeklyPlan::MO_STATUS_PENDING;
        $validated['target_output'] = $targetOutput;

        WeeklyPlan::create($validated);

        return redirect()->route('ppic.weekly-plan.index')
            ->with('success', 'Weekly plan berhasil ditambahkan');
    }

    public function update(Request $request, WeeklyPlan $weeklyPlan)
    {
        // Human edit plan aktif: terbatas — MP, line, packing hold (± tanggal jika packing hold)
        if ($weeklyPlan->status === 'aktif') {
            return $this->updateActivePlan($request, $weeklyPlan);
        }

        if ($weeklyPlan->status !== 'draft') {
            return back()->withErrors(['error' => 'Hanya plan draft yang bisa diupdate']);
        }

        $validated = $request->validate([
            'produk_id' => 'required|exists:produks,id',
            'line_id' => 'nullable|exists:lines,id',
            'work_center_id' => 'nullable|exists:work_centers,id',
            'proses' => 'required|in:mixing,filling,secondary',
            'batch_number' => 'required|string|max:255',
            'tanggal' => 'required|date',
            'mp_count' => 'nullable|integer|min:0',
            'target_output' => 'nullable|integer|min:0',
        ]);

        // Batch number unik per tanggal (boleh sama di tanggal berbeda)
        $isDuplicate = WeeklyPlan::where('batch_number', $validated['batch_number'])
            ->where('tanggal', $validated['tanggal'])
            ->whereIn('status', ['draft', 'aktif'])
            ->where('id', '!=', $weeklyPlan->id)
            ->exists();
        if ($isDuplicate) {
            return back()->withErrors(['batch_number' => 'Batch number sudah digunakan pada tanggal tersebut.']);
        }

        // Validasi max 3 jika produk/tanggal/proses berubah — exclude diri sendiri
        $isSameProdukTanggal = $validated['produk_id'] == $weeklyPlan->produk_id && $validated['tanggal'] == $weeklyPlan->tanggal;
        $isSameProses = $validated['proses'] == $weeklyPlan->proses;
        $isSameWorkCenter = ($validated['work_center_id'] ?? null) == ($weeklyPlan->work_center_id ?? null);

        if (! $isSameProdukTanggal || ! $isSameProses || ! $isSameWorkCenter) {
            $existingCount = WeeklyPlan::where('produk_id', $validated['produk_id'])
                ->where('tanggal', $validated['tanggal'])
                ->whereIn('status', ['draft', 'aktif'])
                ->where('id', '!=', $weeklyPlan->id)
                ->count();
            if ($existingCount >= 3) {
                return back()->withErrors(['produk_id' => 'Produk tersebut pada tanggal ini sudah memiliki maksimal 3 proses.']);
            }

            $duplicateProses = WeeklyPlan::where('produk_id', $validated['produk_id'])
                ->where('tanggal', $validated['tanggal'])
                ->where('proses', $validated['proses'])
                ->whereIn('status', ['draft', 'aktif'])
                ->where('id', '!=', $weeklyPlan->id)
                ->exists();
            if ($duplicateProses) {
                return back()->withErrors(['proses' => 'Proses '.ucfirst($validated['proses']).' sudah ada untuk produk ini pada tanggal tersebut.']);
            }
        }

        $validated['mp_count'] = (int) ($validated['mp_count'] ?? $weeklyPlan->mp_count ?? 0);

        // Auto-calculate target_output using Work Center CT if work_center_id provided
        if (isset($validated['work_center_id']) && $validated['work_center_id']) {
            $wc = WorkCenter::find($validated['work_center_id']);
            if ($wc) {
                if (! isset($validated['target_output']) || ! $validated['target_output']) {
                    $validated['target_output'] = TargetCalculationService::calculateTarget($wc, $validated['mp_count']);
                }
                $validated['multiplier'] = $wc->fit_mp ?: 1;
            }
        } elseif (array_key_exists('target_output', $validated) && ! $validated['target_output'] && $validated['mp_count'] > 0) {
            // Fallback to old multiplier
            $validated['target_output'] = $validated['mp_count'] * (int) Setting::get('target_output_multiplier', 2000);
        }

        $weeklyPlan->update($validated);

        return redirect()->route('ppic.weekly-plan.index')
            ->with('success', 'Weekly plan berhasil diupdate');
    }

    /**
     * Human edit path untuk plan aktif — field terbatas sesuai PRD V (keputusan bisnis #4).
     */
    protected function updateActivePlan(Request $request, WeeklyPlan $weeklyPlan)
    {
        $rules = [
            'mp_count' => 'sometimes|nullable|integer|min:0',
            'line_id' => 'sometimes|nullable|exists:lines,id',
            'work_center_id' => 'sometimes|nullable|exists:work_centers,id',
            'packing_hold' => 'sometimes|boolean',
            'target_output' => 'sometimes|nullable|integer|min:0',
        ];

        // Packing hold: plan packing boleh digeser tanggal
        if ($weeklyPlan->proses === 'secondary') {
            $rules['tanggal'] = 'sometimes|date';
        }

        $validated = $request->validate($rules);

        // Auto-calculate target_output using Work Center CT if mp_count changed
        if (array_key_exists('mp_count', $validated)
            && $validated['mp_count'] !== null
            && ! array_key_exists('target_output', $validated)
            && (int) $validated['mp_count'] !== (int) $weeklyPlan->mp_count) {
            $wc = $weeklyPlan->workCenter;
            if ($wc) {
                $validated['target_output'] = TargetCalculationService::calculateTarget($wc, (int) $validated['mp_count']);
            }
        }

        if (array_key_exists('target_output', $validated)
            && ! $validated['target_output']
            && (int) $weeklyPlan->mp_count > 0) {
            $wc = $weeklyPlan->workCenter;
            if ($wc) {
                $validated['target_output'] = TargetCalculationService::calculateTarget($wc, $weeklyPlan->mp_count);
            }
        }

        // Recalculate multiplier if work_center_id changed
        if (array_key_exists('work_center_id', $validated) && $validated['work_center_id']) {
            $wc = WorkCenter::find($validated['work_center_id']);
            if ($wc) {
                $validated['multiplier'] = $wc->fit_mp ?: 1;
            }
        }

        $weeklyPlan->update($validated);

        return redirect()->route('ppic.weekly-plan.index')
            ->with('success', 'Plan aktif diperbarui');
    }

    public function destroy(WeeklyPlan $weeklyPlan)
    {
        if ($weeklyPlan->status !== 'draft') {
            return back()->withErrors(['error' => 'Hanya plan draft yang bisa dihapus']);
        }

        $weeklyPlan->delete();

        return redirect()->route('ppic.weekly-plan.index')
            ->with('success', 'Weekly plan berhasil dihapus');
    }

    public function activate(WeeklyPlan $weeklyPlan)
    {
        if ($weeklyPlan->status !== 'draft') {
            return back()->withErrors(['error' => 'Hanya plan draft yang bisa diaktifkan']);
        }

        $data = ['status' => 'aktif'];

        if (! $weeklyPlan->target_output) {
            $wc = $weeklyPlan->workCenter;
            if ($wc) {
                $data['multiplier'] = $wc->fit_mp ?: 1;
                $data['target_output'] = TargetCalculationService::calculateTarget($wc, $weeklyPlan->mp_count);
            } else {
                $multiplier = (int) Setting::get('target_output_multiplier', 2000);
                $data['multiplier'] = $multiplier;
                $data['target_output'] = (int) $weeklyPlan->mp_count * $multiplier;
            }
        } elseif (! $weeklyPlan->multiplier) {
            $wc = $weeklyPlan->workCenter;
            $data['multiplier'] = $wc?->fit_mp ?: 1;
        }

        $weeklyPlan->update($data);

        return redirect()->route('ppic.weekly-plan.index')
            ->with('success', 'Weekly plan berhasil diaktifkan');
    }

    public function lookup(Request $request)
    {
        $produkId = $request->input('produk_id');
        $tanggal = $request->input('tanggal', now()->toDateString());

        $weeklyPlan = WeeklyPlan::with(['produk', 'workCenter'])
            ->where('produk_id', $produkId)
            ->where('tanggal', $tanggal)
            ->where('status', 'aktif')
            ->first();

        return response()->json($weeklyPlan);
    }

    public function targetPreview(Request $request)
    {
        $request->validate([
            'work_center_id' => 'required|exists:work_centers,id',
            'mp_count' => 'required|integer|min:0',
        ]);

        $workCenter = WorkCenter::findOrFail($request->work_center_id);
        $mpCount = (int) $request->mp_count;

        $target = TargetCalculationService::calculateTarget($workCenter, $mpCount);
        $capacityInfo = TargetCalculationService::getCapacityInfo($workCenter);

        return response()->json([
            'target_output' => $target,
            'capacity_info' => $capacityInfo,
        ]);
    }
};