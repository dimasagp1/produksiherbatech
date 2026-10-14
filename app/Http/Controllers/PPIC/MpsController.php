<?php

namespace App\Http\Controllers\PPIC;

use App\Exports\MpsPlanExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\PPIC\StoreMpsItemRequest;
use App\Http\Requests\PPIC\StoreMpsPlanRequest;
use App\Http\Requests\PPIC\UpdateMpsItemRequest;
use App\Models\Line;
use App\Models\MpsItem;
use App\Models\MpsPlan;
use App\Models\Produk;
use App\Models\WeeklyPlan;
use App\Models\WorkCenter;
use App\Services\MpsWeeklyPlanGenerator;
use App\Services\MrpCalculationService;
use App\Services\TargetCalculationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class MpsController extends Controller
{
    public function index(Request $request)
    {
        $monthYear = $request->input('month_year', now()->format('Y-m'));
        $status = $request->input('status', 'all');

        $plans = MpsPlan::with(['produk', 'workCenter', 'creator', 'items.line'])
            ->when($monthYear, fn ($q, $m) => $q->where('month_year', $m))
            ->when($status !== 'all', fn ($q, $s) => $q->where('status', $s))
            ->latest('month_year')
            ->orderBy('revision', 'desc')
            ->paginate(15)
            ->withQueryString();

        $produks = Produk::aktif()->get(['id', 'kode_produk', 'nama_produk']);
        $workCenters = WorkCenter::active()->get(['id', 'code', 'name', 'type', 'is_active']);
        $odooBatches = WeeklyPlan::whereNotNull('batch_number')
            ->select(['id', 'produk_id', 'batch_number', 'proses', 'odoo_mo_id', 'mo_status', 'target_output'])
            ->orderByDesc('id')
            ->get();

        return Inertia::render('PPIC/Mps/Index', [
            'plans' => $plans,
            'monthYear' => $monthYear,
            'status' => $status,
            'produks' => $produks,
            'workCenters' => $workCenters,
            'odooBatches' => $odooBatches,
        ]);
    }

    /**
     * Check if current user has read-only access (manager role)
     */
    private function checkReadOnly(): void
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya memiliki akses baca (read-only).');
        }
    }

    public function create()
    {
        $this->checkReadOnly();

        $produks = Produk::aktif()->get(['id', 'kode_produk', 'nama_produk']);
        $workCenters = WorkCenter::active()->get(['id', 'code', 'name', 'type', 'is_active']);
        $odooBatches = WeeklyPlan::whereNotNull('batch_number')
            ->select(['id', 'produk_id', 'batch_number', 'proses', 'odoo_mo_id', 'mo_status', 'target_output'])
            ->orderByDesc('id')
            ->get();

        return Inertia::render('PPIC/Mps/Create', [
            'produks' => $produks,
            'workCenters' => $workCenters,
            'odooBatches' => $odooBatches,
            'defaultMonth' => now()->format('Y-m'),
        ]);
    }

    public function store(StoreMpsPlanRequest $request)
    {
        $this->checkReadOnly();
        $validated = $request->validated();
        $batchNumber = ! empty($validated['batch_number']) ? trim($validated['batch_number']) : null;

        // Check if plan already exists for this product + WC + month + batch_number
        $existsQuery = MpsPlan::where('produk_id', $validated['produk_id'])
            ->where('work_center_id', $validated['work_center_id'])
            ->where('month_year', $validated['month_year']);

        if ($batchNumber) {
            $existsQuery->where('batch_number', $batchNumber);
        } else {
            $existsQuery->whereNull('batch_number');
        }

        if ($existsQuery->exists()) {
            return back()->withErrors([
                'month_year' => 'MPS Plan untuk produk, Work Center'.($batchNumber ? ', dan batch ('.$batchNumber.')' : '').' ini pada bulan tersebut sudah ada. Buka plan tersebut untuk melihat atau membuat revisi.',
            ]);
        }

        $plan = MpsPlan::create([
            'produk_id' => $validated['produk_id'],
            'work_center_id' => $validated['work_center_id'],
            'batch_number' => $batchNumber,
            'month_year' => $validated['month_year'],
            'revision' => 1,
            'is_active_revision' => true,
            'status' => 'draft',
            'notes' => $validated['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);
        $plan->snapshotBeginningStock();

        return redirect()->route('ppic.mps.show', $plan->id)
            ->with('success', 'MPS Plan berhasil dibuat. Silakan isi grid harian.');
    }

    public function show(MpsPlan $mps)
    {
        $mps->load(['produk', 'workCenter', 'creator', 'items.line', 'items.workCenter', 'parentPlan', 'revisions']);

        $rootPlanId = $mps->parent_plan_id ?: $mps->id;
        $allRevisions = MpsPlan::where('id', $rootPlanId)
            ->orWhere('parent_plan_id', $rootPlanId)
            ->orderBy('revision', 'asc')
            ->get(['id', 'revision', 'status', 'is_active_revision', 'created_at']);

        $lines = Line::aktif()->with('defaultWorkCenter')->get();
        $workCenters = WorkCenter::active()->get();
        $daysInMonth = now()->parse($mps->month_year.'-01')->daysInMonth;

        return Inertia::render('PPIC/Mps/Show', [
            'plan' => $mps,
            'revisions' => $allRevisions,
            'lines' => $lines,
            'workCenters' => $workCenters,
            'daysInMonth' => $daysInMonth,
            'beginningStockOH' => $mps->beginning_stock_oh,
            'liveStockOH' => $mps->getLiveStockOh(),
            'isSnapshotStale' => $mps->status === 'draft' ? $mps->isSnapshotStale() : false,
        ]);
    }

    public function grid(MpsPlan $mps)
    {
        $mps->load(['produk', 'workCenter', 'items.line', 'items.workCenter']);

        $items = $mps->items()
            ->with(['line', 'workCenter'])
            ->orderBy('tanggal')
            ->orderBy('shift')
            ->get()
            ->groupBy(function ($item) {
                return is_string($item->tanggal) ? substr($item->tanggal, 0, 10) : $item->tanggal->format('Y-m-d');
            });

        $lines = Line::aktif()->with('defaultWorkCenter')->get();
        $workCenters = WorkCenter::active()->get();

        $daysInMonth = Carbon::parse($mps->month_year.'-01')->daysInMonth;

        return Inertia::render('PPIC/Mps/Grid', [
            'plan' => $mps,
            'itemsByDate' => $items,
            'lines' => $lines,
            'workCenters' => $workCenters,
            'daysInMonth' => $daysInMonth,
        ]);
    }

    public function storeItem(StoreMpsItemRequest $request, MpsPlan $mps)
    {
        $this->checkReadOnly();
        if ($mps->status !== 'draft') {
            if ($request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json(['message' => 'Hanya plan draft yang bisa diedit. Untuk plan approved, silakan buat revisi baru.'], 422);
            }

            return back()->withErrors(['error' => 'Hanya plan draft yang bisa diedit. Untuk plan approved, silakan buat revisi baru.']);
        }

        $validated = $request->validated();

        if (! empty($validated['cleaning']) && $validated['cleaning']) {
            $validated['mp_count'] = 0;
            $validated['target_qty'] = 0;
            $validated['adjusted_qty'] = null;
            if (empty($validated['gap_reason'])) {
                $validated['gap_reason'] = 'cleaning';
            }
        }

        // Validate line can run the selected work center
        if (! empty($validated['line_id']) && ! empty($validated['work_center_id'])) {
            $line = Line::find($validated['line_id']);
            $wc = WorkCenter::find($validated['work_center_id']);
            if ($line && $wc && ! $line->canRunWorkCenter($wc->type)) {
                $msg = "Line {$line->nama_line} tidak bisa menjalankan {$wc->name} ({$wc->type})";
                if ($request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                    return response()->json(['message' => $msg, 'errors' => ['work_center_id' => [$msg]]], 422);
                }

                return back()->withErrors(['work_center_id' => $msg]);
            }
        }

        // Validate technical capacity limit (DEV-208)
        $targetWc = (! empty($validated['work_center_id']) ? WorkCenter::find($validated['work_center_id']) : null) ?? $mps->workCenter;
        if ($targetWc && $targetWc->standard_ct_seconds > 0) {
            $isSaturday = Carbon::parse($validated['tanggal'])->isSaturday();
            $customHours = $isSaturday ? ($targetWc->saturday_shift_hours ?: $targetWc->shift_hours) : $targetWc->shift_hours;
            $maxCapacity = TargetCalculationService::getMaxLineCapacity($targetWc, $customHours);

            if (! empty($validated['adjusted_qty']) && $validated['adjusted_qty'] > ($maxCapacity * 1.5)) {
                $maxFormatted = number_format($maxCapacity, 0, ',', '.');
                $adjFormatted = number_format($validated['adjusted_qty'], 0, ',', '.');
                $msg = "Target penyesuaian ({$adjFormatted} pcs) melebihi batas kapasitas teknis mesin/lini ({$maxFormatted} pcs/shift).";
                if ($request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                    return response()->json(['message' => $msg, 'errors' => ['adjusted_qty' => [$msg]]], 422);
                }

                return back()->withErrors(['adjusted_qty' => $msg]);
            }
        }

        $exists = MpsItem::where('mps_plan_id', $mps->id)
            ->where('tanggal', $validated['tanggal'])
            ->where('line_id', $validated['line_id'])
            ->where('shift', $validated['shift'])
            ->exists();

        if ($exists) {
            $msg = 'Item untuk tanggal, line, dan shift tersebut sudah ada.';
            if ($request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json(['message' => $msg, 'errors' => ['tanggal' => [$msg]]], 422);
            }

            return back()->withErrors(['tanggal' => $msg]);
        }

        $item = MpsItem::create(array_merge($validated, ['mps_plan_id' => $mps->id]));
        if (empty($validated['cleaning'])) {
            $item->recalculateTarget();
        }

        if (! empty($validated['cleaning']) && $validated['cleaning']) {
            MpsItem::where('mps_plan_id', $mps->id)
                ->where('tanggal', $validated['tanggal'])
                ->where('line_id', $validated['line_id'])
                ->where('shift', $validated['shift'] === 'shift1' ? 'shift2' : 'shift1')
                ->where('id', '!=', $item->id)
                ->delete();

            MpsItem::updateOrCreate(
                ['mps_plan_id' => $mps->id, 'tanggal' => $validated['tanggal'], 'line_id' => $validated['line_id'], 'shift' => $validated['shift'] === 'shift1' ? 'shift2' : 'shift1'],
                ['work_center_id' => $validated['work_center_id'] ?? null, 'mp_count' => 0, 'target_qty' => 0, 'cleaning' => true, 'adjusted_qty' => null, 'gap_reason' => 'cleaning', 'notes' => 'Auto cleaning full-day']
            );
        }

        app(MrpCalculationService::class)->calculate($mps);

        if ($request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json(['success' => true, 'item' => $item->fresh(['line', 'workCenter'])]);
        }

        return back()->with('success', 'MPS item disimpan.');
    }

    public function updateItem(UpdateMpsItemRequest $request, MpsItem $item)
    {
        $this->checkReadOnly();
        if ($item->plan && $item->plan->status !== 'draft') {
            if ($request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json(['message' => 'Hanya plan draft yang bisa diedit. Untuk plan approved, silakan buat revisi baru.'], 422);
            }

            return back()->withErrors(['error' => 'Hanya plan draft yang bisa diedit. Untuk plan approved, silakan buat revisi baru.']);
        }

        $validated = $request->validated();

        if (! empty($validated['cleaning']) && $validated['cleaning']) {
            $validated['mp_count'] = 0;
            $validated['target_qty'] = 0;
            $validated['adjusted_qty'] = null;
            if (empty($validated['gap_reason'])) {
                $validated['gap_reason'] = 'cleaning';
            }
        }

        // Validate line can run the selected work center
        if (! empty($validated['line_id']) && ! empty($validated['work_center_id'])) {
            $line = Line::find($validated['line_id']);
            $wc = WorkCenter::find($validated['work_center_id']);

            if ($line && $wc && ! $line->canRunWorkCenter($wc->type)) {
                $msg = "Line {$line->nama_line} tidak bisa menjalankan {$wc->name} ({$wc->type})";
                if ($request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                    return response()->json([
                        'message' => $msg,
                        'errors' => ['work_center_id' => [$msg]],
                    ], 422);
                }

                return back()->withErrors([
                    'work_center_id' => $msg,
                ]);
            }
        }

        // Validate technical capacity limit (DEV-208)
        $targetWc = (! empty($validated['work_center_id']) ? WorkCenter::find($validated['work_center_id']) : null) ?? $item->workCenter ?? $item->plan?->workCenter;
        if ($targetWc && $targetWc->standard_ct_seconds > 0) {
            $isSaturday = Carbon::parse($item->tanggal)->isSaturday();
            $customHours = $isSaturday ? ($targetWc->saturday_shift_hours ?: $targetWc->shift_hours) : $targetWc->shift_hours;
            $maxCapacity = TargetCalculationService::getMaxLineCapacity($targetWc, $customHours);

            if (! empty($validated['adjusted_qty']) && $validated['adjusted_qty'] > ($maxCapacity * 1.5)) {
                $maxFormatted = number_format($maxCapacity, 0, ',', '.');
                $adjFormatted = number_format($validated['adjusted_qty'], 0, ',', '.');
                $msg = "Target penyesuaian ({$adjFormatted} pcs) melebihi batas kapasitas teknis mesin/lini ({$maxFormatted} pcs/shift).";
                if ($request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                    return response()->json(['message' => $msg, 'errors' => ['adjusted_qty' => [$msg]]], 422);
                }

                return back()->withErrors(['adjusted_qty' => $msg]);
            }
        }

        $item->update($validated);

        if (! empty($validated['cleaning']) && $validated['cleaning']) {
            $item->update(['target_qty' => 0, 'mp_count' => 0]);
            $other = $item->shift === 'shift1' ? 'shift2' : 'shift1';
            MpsItem::where('mps_plan_id', $item->mps_plan_id)->where('tanggal', $item->tanggal)->where('line_id', $item->line_id)->where('shift', $other)->delete();
            MpsItem::updateOrCreate(['mps_plan_id' => $item->mps_plan_id, 'tanggal' => $item->tanggal, 'line_id' => $item->line_id, 'shift' => $other], ['work_center_id' => $validated['work_center_id'] ?? $item->work_center_id, 'mp_count' => 0, 'target_qty' => 0, 'cleaning' => true, 'adjusted_qty' => null, 'gap_reason' => 'cleaning', 'notes' => 'Auto cleaning full-day']);
        } elseif (isset($validated['mp_count']) || isset($validated['work_center_id'])) {
            $item->refresh();
            $item->recalculateTarget();
        }

        $mrpPlan = $item->plan ?? MpsPlan::find($item->mps_plan_id);
        if ($mrpPlan) {
            app(MrpCalculationService::class)->calculate($mrpPlan);
        }

        if ($request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'success' => true,
                'item' => $item->fresh(['line', 'workCenter']),
            ]);
        }

        return back()->with('success', 'MPS item disimpan.');
    }

    public function approve(MpsPlan $mps)
    {
        $this->checkReadOnly();

        if ($mps->status !== 'draft') {
            return back()->withErrors(['error' => 'Hanya plan draft yang bisa di-approve']);
        }

        if ($mps->isSnapshotStale()) {
            $live = $mps->getLiveStockOh();
            $snap = (float) $mps->beginning_stock_snapshot;

            return back()->withErrors(['error' => 'Stock OH berubah (snapshot '.number_format($snap, 0, ',', '.').' → live '.number_format($live, 0, ',', '.').'). Silakan Resnapshot sebelum approve.']);
        }

        $totalQty = (int) $mps->items()->where('cleaning', false)->get()->sum(fn ($i) => $i->adjusted_qty ?? $i->target_qty);
        if ($totalQty <= 0) {
            return back()->withErrors(['error' => 'Grid masih kosong (total 0). Isi MPS terlebih dahulu sebelum approve.']);
        }

        // Set all other revisions of this plan group as inactive
        $rootPlanId = $mps->parent_plan_id ?: $mps->id;
        MpsPlan::where(function ($q) use ($rootPlanId) {
            $q->where('id', $rootPlanId)->orWhere('parent_plan_id', $rootPlanId);
        })->where('id', '!=', $mps->id)->update(['is_active_revision' => false]);

        $mps->update(['status' => 'approved', 'is_active_revision' => true]);

        return redirect()->back()->with('success', 'MPS Plan (Rev '.$mps->revision.') di-approve. Siap untuk generate Weekly Plans.');
    }

    public function createRevision(MpsPlan $mps)
    {
        $this->checkReadOnly();

        if ($mps->status === 'draft') {
            return back()->withErrors(['error' => 'Plan masih berstatus draft. Tidak perlu membuat revisi baru.']);
        }

        $newPlan = $mps->createNextRevision();

        return redirect()->route('ppic.mps.show', $newPlan->id)
            ->with('success', "Revisi {$newPlan->revision} berhasil dibuat sebagai draft. Silakan sesuaikan grid.");
    }

    public function activate(MpsPlan $mps)
    {
        $this->checkReadOnly();

        if (! in_array($mps->status, ['draft', 'approved'])) {
            return back()->withErrors(['error' => 'Hanya plan draft/approved yang bisa diaktifkan']);
        }

        $mps->update(['status' => 'active']);

        return redirect()->back()->with('success', 'MPS Plan diaktifkan.');
    }

    public function generateWeeklyPlans(MpsPlan $mps, MpsWeeklyPlanGenerator $generator, MrpCalculationService $mrp)
    {
        $this->checkReadOnly();

        if (! in_array($mps->status, ['approved', 'active'])) {
            return back()->withErrors(['error' => 'Plan harus approved atau active untuk generate Weekly Plans']);
        }

        $result = $generator->generate($mps);
        $mrp->calculate($mps);

        $message = "Weekly Plans generated: {$result['created']} created, {$result['skipped']} skipped";
        if (! empty($result['errors'])) {
            $message .= '. Errors: '.implode('; ', $result['errors']);
        }

        return redirect()->back()->with('success', $message);
    }

    public function close(MpsPlan $mps)
    {
        $this->checkReadOnly();
        if (! in_array($mps->status, ['active', 'approved'])) {
            return back()->withErrors(['error' => 'Hanya plan active/approved yang bisa di-close']);
        }
        $mps->update(['status' => 'closed', 'closed_at' => now()]);

        return back()->with('success', 'MPS Plan di-close.');
    }

    public function resnapshot(MpsPlan $mps)
    {
        $this->checkReadOnly();
        if ($mps->status !== 'draft') {
            return back()->withErrors(['error' => 'Snapshot hanya untuk draft']);
        }
        $val = $mps->snapshotBeginningStock();

        return back()->with('success', 'Snapshot Stock OH diperbarui: '.number_format($val, 0, ',', '.'));
    }

    public function export(MpsPlan $mps)
    {
        $mps->load(['produk', 'workCenter']);

        return Excel::download(new MpsPlanExport($mps), 'MPS-'.$mps->produk->kode_produk.'-'.$mps->month_year.'-Rev'.$mps->revision.'.xlsx');
    }

    public function destroy(MpsPlan $mps)
    {
        $this->checkReadOnly();

        if ($mps->status !== 'draft') {
            return back()->withErrors(['error' => 'Hanya plan draft yang bisa dihapus']);
        }

        $mps->delete();

        return redirect()->route('ppic.mps.index')
            ->with('success', 'MPS Plan dihapus');
    }
}
