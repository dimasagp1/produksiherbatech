<?php

namespace App\Http\Controllers;

use App\Models\AlasanDowntime;
use App\Models\LaporanHarian;
use App\Models\Line;
use App\Models\Mesin;
use App\Models\Produk;
use App\Models\WeeklyPlan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LaporanHarianController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $search = $request->input('search', '');
        $shift = $request->input('shift');

        $query = LaporanHarian::with(['produk', 'mesin', 'line', 'user']);

        if ($user->hasAnyRole(['leader', 'operator'])) {
            $query->where('user_id', $user->id);
        }

        $query->when($search, function ($q, $s) {
            $q->whereHas('produk', fn ($q) => $q->where('nama_produk', 'like', "%{$s}%"))
                ->orWhere('batch_number', 'like', "%{$s}%");
        });

        $query->forShift($shift);

        $laporans = $query->latest('tanggal')->paginate(20)->withQueryString();

        return Inertia::render('Leader/LaporanHarian/Index', [
            'laporans' => $laporans,
            'search' => $search,
            'shift' => $shift,
        ]);
    }

    public function create(Request $request)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }
        $produks = Produk::aktif()->get();
        $mesins = Mesin::aktif()->get();
        $lines = Line::aktif()->get();
        $alasanDowntimes = AlasanDowntime::aktif()->get();
        $weeklyPlans = WeeklyPlan::with(['produk', 'line'])
            ->whereIn('status', ['aktif', 'draft'])
            ->where('mo_status', '!=', WeeklyPlan::MO_STATUS_CANCELLED)
            ->get();

        return Inertia::render('Leader/LaporanHarian/Create', [
            'produks' => $produks,
            'mesins' => $mesins,
            'lines' => $lines,
            'alasanDowntimes' => $alasanDowntimes,
            'weeklyPlans' => $weeklyPlans,
            'preselect' => [
                'produk_id' => $request->input('produk_id'),
                'weekly_plan_id' => $request->input('weekly_plan_id'),
                'proses' => $request->input('proses'),
            ],
        ]);
    }

    public function weeklyPlanByDate(Request $request)
    {
        $request->validate(['tanggal' => 'required|date']);

        $plans = WeeklyPlan::with(['produk', 'line'])
            ->where('tanggal', $request->tanggal)
            ->whereIn('status', ['aktif', 'draft'])
            ->where('mo_status', '!=', WeeklyPlan::MO_STATUS_CANCELLED)
            ->get();

        return response()->json($plans);
    }

    public function store(Request $request)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }
        $validated = $request->validate([
            'produk_id' => 'required|exists:produks,id',
            'weekly_plan_id' => 'required|exists:weekly_plans,id',
            'mesin_id' => 'required|exists:mesins,id',
            'line_id' => 'required|exists:lines,id',
            'tanggal' => 'required|date',
            'shift' => 'nullable|in:shift1,shift2',
            'target_mp' => 'required|numeric|min:0',
            'total_mp' => 'required|integer|min:1',
            'capacity_fisik' => 'required|integer|min:0',
        ]);

        $weeklyPlan = WeeklyPlan::findOrFail($validated['weekly_plan_id']);

        if ($weeklyPlan->status === 'draft') {
            $weeklyPlan->update(['status' => 'aktif']);
        }

        // Cegah duplikasi: 1 weekly plan (produk+proses+batch+tanggal) hanya boleh 1 laporan
        // Termasuk soft-deleted? cek hanya yang belum dihapus agar laporan yang dihapus bisa dibuat ulang
        $existingLaporan = LaporanHarian::where('weekly_plan_id', $weeklyPlan->id)->exists();
        if ($existingLaporan) {
            return back()->withErrors([
                'produk_id' => 'Produk sudah ada di laporan. Cek kembali atau gunakan produk lain.',
                'weekly_plan_id' => 'Produk sudah ada di laporan. Cek kembali atau gunakan produk lain.',
            ]);
        }

        $mesin = Mesin::findOrFail($validated['mesin_id']);

        // Create laporan with timer_status='start', calculations happen on endTimer
        $laporan = LaporanHarian::create([
            'user_id' => auth()->id(),
            'weekly_plan_id' => $weeklyPlan->id,
            'produk_id' => $validated['produk_id'],
            'proses' => $weeklyPlan->proses,
            'batch_number' => $weeklyPlan->batch_number,
            'mesin_id' => $validated['mesin_id'],
            'ct' => $mesin->ct,
            'line_id' => $validated['line_id'],
            'tanggal' => $validated['tanggal'],
            'shift' => $validated['shift'] ?? 'shift1',
            'target_mp' => $validated['target_mp'],
            'total_mp' => $validated['total_mp'],
            'capacity_fisik' => $validated['capacity_fisik'],
            'timer_status' => 'draft',
            'status' => 'draft',
        ]);

        return redirect()->route('leader.laporan-harian.show', $laporan->id)
            ->with('success', 'Laporan harian berhasil dibuat. Silakan kelola timer di halaman detail.');
    }

    public function show(LaporanHarian $laporanHarian)
    {
        $user = auth()->user();
        if ($user->hasAnyRole(['leader', 'operator']) && $laporanHarian->user_id !== $user->id) {
            return back()->withErrors(['error' => 'Anda tidak memiliki akses ke laporan ini']);
        }

        $laporanHarian->load(['produk', 'mesin', 'line', 'user', 'downtimeDetails.alasanDowntime']);

        $alasanDowntimes = AlasanDowntime::aktif()->get();

        // Get sibling laporans: same produk + same tanggal (parallel processes)
        $siblingLaporans = LaporanHarian::with(['produk', 'mesin', 'line', 'downtimeDetails.alasanDowntime'])
            ->where('produk_id', $laporanHarian->produk_id)
            ->where('tanggal', $laporanHarian->tanggal)
            ->where('id', '!=', $laporanHarian->id)
            ->get();

        // Get all weekly plans for this product + date (for next process lookup)
        $weeklyPlans = WeeklyPlan::with('produk')
            ->where('produk_id', $laporanHarian->produk_id)
            ->where('tanggal', $laporanHarian->tanggal)
            ->where('status', 'aktif')
            ->get();

        $mesins = Mesin::aktif()->get();
        $lines = Line::aktif()->get();

        $totalReject = $laporanHarian->rejectDetails()->sum('jumlah');

        return Inertia::render('Leader/LaporanHarian/Show', [
            'laporan' => $laporanHarian,
            'downtimeDetails' => $laporanHarian->downtimeDetails,
            'alasanDowntimes' => $alasanDowntimes,
            'siblingLaporans' => $siblingLaporans,
            'weeklyPlans' => $weeklyPlans,
            'mesins' => $mesins,
            'lines' => $lines,
            'totalReject' => $totalReject,
        ]);
    }

    public function edit(LaporanHarian $laporanHarian)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }
        if ($laporanHarian->status === 'locked') {
            return back()->withErrors(['error' => 'Laporan yang sudah dikunci tidak bisa diedit']);
        }

        if ($laporanHarian->user_id !== auth()->id()) {
            return back()->withErrors(['error' => 'Anda hanya bisa mengedit laporan sendiri']);
        }

        $laporanHarian->load(['downtimeDetails.alasanDowntime']);

        $produks = Produk::aktif()->get();
        $mesins = Mesin::aktif()->get();
        $lines = Line::aktif()->get();
        $alasanDowntimes = AlasanDowntime::aktif()->get();

        $totalReject = $laporanHarian->rejectDetails()->sum('jumlah');

        return Inertia::render('Leader/LaporanHarian/Edit', [
            'laporan' => $laporanHarian,
            'downtimeDetails' => $laporanHarian->downtimeDetails,
            'produks' => $produks,
            'mesins' => $mesins,
            'lines' => $lines,
            'alasanDowntimes' => $alasanDowntimes,
            'totalReject' => $totalReject,
        ]);
    }

    public function update(Request $request, LaporanHarian $laporanHarian)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }
        if ($laporanHarian->status === 'locked') {
            return back()->withErrors(['error' => 'Laporan yang sudah dikunci tidak bisa diupdate']);
        }

        if ($laporanHarian->user_id !== auth()->id()) {
            return back()->withErrors(['error' => 'Anda hanya bisa mengupdate laporan sendiri']);
        }

        $validated = $request->validate([
            'produk_id' => 'required|exists:produks,id',
            'weekly_plan_id' => 'required|exists:weekly_plans,id',
            'mesin_id' => 'required|exists:mesins,id',
            'line_id' => 'required|exists:lines,id',
            'tanggal' => 'required|date',
            'shift' => 'nullable|in:shift1,shift2',
            'target_mp' => 'required|numeric|min:0',
            'total_mp' => 'required|integer|min:1',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'capacity_fisik' => 'required|integer|min:0',
            'output_fisik' => 'required|integer|min:0|lte:capacity_fisik',
        ]);

        $weeklyPlan = WeeklyPlan::findOrFail($validated['weekly_plan_id']);
        $mesin = Mesin::findOrFail($validated['mesin_id']);

        // Calculate Gross Time (handles overnight shifts)
        $startMinutes = $this->timeToMinutes($validated['start_time']);
        $endMinutes = $this->timeToMinutes($validated['end_time']);
        if ($endMinutes < $startMinutes) {
            $endMinutes += 24 * 60;
        }
        $grossTimeMenit = $endMinutes - $startMinutes;

        // Pause = Downtime
        $totalDowntime = $laporanHarian->total_pause_menit ?? 0;

        if ($totalDowntime > $grossTimeMenit) {
            return back()->withErrors([
                'error' => 'Total downtime tidak boleh melebihi gross time',
            ]);
        }

        $waktuBersih = max(0, $grossTimeMenit - $totalDowntime);

        $totalReject = $laporanHarian->rejectDetails()->sum('jumlah');
        $totalOutput = $validated['output_fisik'] + $totalReject;

        $availability = $grossTimeMenit > 0 ? min(100, ($waktuBersih / $grossTimeMenit) * 100) : 0;
        $performance = $waktuBersih > 0 ? min(100, ($validated['output_fisik'] * $mesin->ct / $waktuBersih) * 100) : 0;
        $quality = $totalOutput > 0 ? min(100, ($validated['output_fisik'] / $totalOutput) * 100) : 0;
        $oee = min(100, ($availability / 100) * ($performance / 100) * ($quality / 100) * 100);
        $targetTotal = $validated['target_mp'] * $validated['total_mp'];
        $produktivitas = $targetTotal > 0 ? ($validated['output_fisik'] / $targetTotal) * 100 : 0;

        $laporanHarian->update([
            'weekly_plan_id' => $weeklyPlan->id,
            'produk_id' => $validated['produk_id'],
            'proses' => $weeklyPlan->proses,
            'batch_number' => $weeklyPlan->batch_number,
            'mesin_id' => $validated['mesin_id'],
            'ct' => $mesin->ct,
            'line_id' => $validated['line_id'],
            'tanggal' => $validated['tanggal'],
            'shift' => $validated['shift'] ?? $laporanHarian->shift ?? 'shift1',
            'target_mp' => $validated['target_mp'],
            'total_mp' => $validated['total_mp'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'gross_time_menit' => $grossTimeMenit,
            'capacity_fisik' => $validated['capacity_fisik'],
            'output_fisik' => $validated['output_fisik'],
            'waktu_bersih_menit' => $waktuBersih,
            'target_teoritis' => $waktuBersih * $mesin->ct,
            'yield_persen' => $quality,
            'availability_persen' => $availability,
            'performance_persen' => $performance,
            'oee_persen' => $oee,
            'produktivitas_persen' => $produktivitas,
            'status' => 'submitted',
        ]);

        return redirect()->route('leader.laporan-harian.index')
            ->with('success', 'Laporan harian berhasil diupdate');
    }

    public function destroy(LaporanHarian $laporanHarian)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }
        if ($laporanHarian->status === 'locked') {
            return back()->withErrors(['error' => 'Laporan yang sudah dikunci tidak bisa dihapus']);
        }

        if ($laporanHarian->user_id !== auth()->id()) {
            return back()->withErrors(['error' => 'Anda hanya bisa menghapus laporan sendiri']);
        }

        $laporanHarian->delete();

        return redirect()->route('leader.laporan-harian.index')
            ->with('success', 'Laporan harian berhasil dihapus');
    }

    public function lock(LaporanHarian $laporanHarian)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }
        if (! auth()->user()->hasRole(['spv', 'superadmin'])) {
            return back()->withErrors(['error' => 'Anda tidak memiliki akses untuk mengunci laporan']);
        }

        $laporanHarian->update([
            'status' => 'locked',
            'locked_by' => auth()->id(),
            'locked_at' => now(),
        ]);

        return redirect()->back()
            ->with('success', 'Laporan berhasil dikunci');
    }

    public function startTimer(Request $request, LaporanHarian $laporanHarian)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }
        $user = auth()->user();
        if ($user->hasAnyRole(['leader', 'operator']) && $laporanHarian->user_id !== $user->id) {
            return back()->withErrors(['error' => 'Anda tidak memiliki akses ke laporan ini']);
        }

        if ($laporanHarian->status === 'locked') {
            return back()->withErrors(['error' => 'Laporan yang sudah dikunci tidak bisa diupdate']);
        }

        // Dependency check: filling must wait for mixing to be finished (End or Submitted)
        $processOrder = ['mixing' => 0, 'filling' => 1, 'packing' => 2];
        $currentOrder = $processOrder[$laporanHarian->proses] ?? -1;
        if ($currentOrder > 0) {
            $prevProses = array_search($currentOrder - 1, $processOrder);
            $prevLaporanExists = LaporanHarian::where('produk_id', $laporanHarian->produk_id)
                ->where('tanggal', $laporanHarian->tanggal)
                ->where('proses', $prevProses)
                ->where(function ($q) {
                    $q->where('status', 'submitted')
                        ->orWhere('timer_status', 'end');
                })
                ->exists();
            if (! $prevLaporanExists) {
                return back()->withErrors([
                    'error' => ucfirst($prevProses).' harus diselesaikan terlebih dahulu sebelum memulai '.$laporanHarian->proses,
                ]);
            }
        }

        // Accumulate current pause session before resuming
        if ($laporanHarian->timer_status === 'pause' && $laporanHarian->pause_started_at) {
            $request->validate([
                'alasan_downtime' => 'required|string|min:3',
            ]);

            $pauseStartedAt = $laporanHarian->pause_started_at instanceof Carbon
                ? $laporanHarian->pause_started_at
                : Carbon::parse($laporanHarian->pause_started_at);
            // PRD V §6.2: real elapsed time — TIDAK ADA cap hardcode
            $pauseDurationSec = max(0, $pauseStartedAt->diffInSeconds(now()));
            $pauseDuration = round($pauseDurationSec / 60, 2);
            $totalPauseMenit = round(($laporanHarian->total_pause_menit ?? 0) + $pauseDuration, 2);

            $laporanHarian->update([
                'timer_status' => 'start',
                'total_pause_menit' => $totalPauseMenit,
                'pause_started_at' => null,
            ]);

            // Simpan downtime detail (pause = downtime)
            $laporanHarian->downtimeDetails()->create([
                'alasan_downtime_id' => null,
                'durasi_menit' => $pauseDuration,
                'keterangan' => $request->input('alasan_downtime'),
            ]);
        } else {
            // Draft → Start: selalu pakai jam real-time saat klik
            $laporanHarian->update([
                'timer_status' => 'start',
                'start_time' => now()->format('H:i:s'),
                'start_time_at' => now()->toIso8601String(),
                'pause_started_at' => null,
            ]);
        }

        return redirect()->back()->with('success', 'Timer dimulai');
    }

    public function pauseTimer(LaporanHarian $laporanHarian)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }
        $user = auth()->user();
        if ($user->hasAnyRole(['leader', 'operator']) && $laporanHarian->user_id !== $user->id) {
            return back()->withErrors(['error' => 'Anda tidak memiliki akses ke laporan ini']);
        }

        if ($laporanHarian->status === 'locked') {
            return back()->withErrors(['error' => 'Laporan yang sudah dikunci tidak bisa diupdate']);
        }

        if ($laporanHarian->timer_status !== 'start') {
            return back()->withErrors(['error' => 'Timer tidak sedang berjalan']);
        }

        $pauseStartedAt = now();
        $laporanHarian->update([
            'timer_status' => 'pause',
            'pause_started_at' => $pauseStartedAt,
        ]);

        return redirect()->back()->with('success', 'Timer dijeda');
    }

    public function endTimer(Request $request, LaporanHarian $laporanHarian)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }
        $user = auth()->user();
        if ($user->hasAnyRole(['leader', 'operator']) && $laporanHarian->user_id !== $user->id) {
            return back()->withErrors(['error' => 'Anda tidak memiliki akses ke laporan ini']);
        }

        if ($laporanHarian->status === 'locked') {
            return back()->withErrors(['error' => 'Laporan yang sudah dikunci tidak bisa diupdate']);
        }

        if (! in_array($laporanHarian->timer_status, ['start', 'pause'])) {
            return back()->withErrors(['error' => 'Timer tidak sedang berjalan atau dijeda']);
        }

        // Calculate end_time and pause duration
        $endTime = now()->format('H:i:s');
        $startMinutes = $this->timeToMinutes($laporanHarian->start_time);
        $endMinutes = $this->timeToMinutes($endTime);
        if ($endMinutes < $startMinutes) {
            $endMinutes += 24 * 60;
        }
        $grossTimeMenit = $endMinutes - $startMinutes;

        $totalPauseMenit = (float) ($laporanHarian->total_pause_menit ?? 0);
        if ($laporanHarian->timer_status === 'pause' && $laporanHarian->pause_started_at) {
            $request->validate([
                'alasan_downtime' => 'required|string|min:3',
            ]);

            $pauseStartedAt = $laporanHarian->pause_started_at instanceof Carbon
                ? $laporanHarian->pause_started_at
                : Carbon::parse($laporanHarian->pause_started_at);
            $pauseDurationSec = max(0, $pauseStartedAt->diffInSeconds(now()));
            $pauseDuration = round($pauseDurationSec / 60, 2);
            $totalPauseMenit = round($totalPauseMenit + $pauseDuration, 2);

            // Simpan downtime detail (pause = downtime)
            $laporanHarian->downtimeDetails()->create([
                'alasan_downtime_id' => null,
                'durasi_menit' => $pauseDuration,
                'keterangan' => $request->input('alasan_downtime'),
            ]);
        }

        // Stop timer only — do NOT submit yet, do NOT calculate KPIs
        $laporanHarian->update([
            'timer_status' => 'end',
            'end_time' => $endTime,
            'gross_time_menit' => $grossTimeMenit,
            'total_pause_menit' => $totalPauseMenit,
            'pause_started_at' => null,
        ]);

        // New flow: stay on same page, auto-create draft for next process (Mixing→Filling) so UI can show both in one page
        $nextPlan = WeeklyPlan::getNextProcess(
            $laporanHarian->produk_id,
            $laporanHarian->tanggal,
            $laporanHarian->proses
        );

        if ($nextPlan && ! $nextPlan->hasLaporan()) {
            // Auto-create draft laporan for next process — copy mesin/line/target from current as placeholder
            // Leader can still start timer for next process; output/downtime will be filled per-process via submit
            LaporanHarian::create([
                'user_id' => $laporanHarian->user_id,
                'weekly_plan_id' => $nextPlan->id,
                'produk_id' => $nextPlan->produk_id,
                'proses' => $nextPlan->proses,
                'batch_number' => $nextPlan->batch_number,
                'mesin_id' => $laporanHarian->mesin_id,
                'ct' => $laporanHarian->ct,
                'line_id' => $laporanHarian->line_id,
                'tanggal' => $laporanHarian->tanggal,
                'target_mp' => $laporanHarian->target_mp,
                'total_mp' => $laporanHarian->total_mp,
                'capacity_fisik' => $laporanHarian->capacity_fisik,
                'timer_status' => 'draft',
                'status' => 'draft',
            ]);
        }

        return redirect()->back()
            ->with('success', 'Timer selesai. Silakan isi Output Fisik dan kirim laporan.');
    }

    public function submitLaporan(Request $request, LaporanHarian $laporanHarian)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya bisa read-only.');
        }
        $user = auth()->user();
        if ($user->hasAnyRole(['leader', 'operator']) && $laporanHarian->user_id !== $user->id) {
            return back()->withErrors(['error' => 'Anda tidak memiliki akses ke laporan ini']);
        }

        if ($laporanHarian->status === 'locked') {
            return back()->withErrors(['error' => 'Laporan yang sudah dikunci tidak bisa diupdate']);
        }

        if ($laporanHarian->timer_status !== 'end') {
            return back()->withErrors(['error' => 'Timer belum diakhiri. Akhiri timer terlebih dahulu sebelum mengirim laporan.']);
        }

        $validated = $request->validate([
            'output_fisik' => 'required|integer|min:1',
            'capacity_fisik' => 'required|integer|min:0',
            'mesin_id' => 'nullable|exists:mesins,id',
            'line_id' => 'nullable|exists:lines,id',
            'target_mp' => 'nullable|numeric|min:0',
            'total_mp' => 'nullable|integer|min:1',
        ]);

        $mesinId = $validated['mesin_id'] ?? $laporanHarian->mesin_id;
        $lineId = $validated['line_id'] ?? $laporanHarian->line_id;
        $targetMp = $validated['target_mp'] ?? $laporanHarian->target_mp;
        $totalMp = $validated['total_mp'] ?? $laporanHarian->total_mp;
        $mesin = Mesin::findOrFail($mesinId);

        $grossTimeMenit = max(1, $laporanHarian->gross_time_menit ?? 0);
        $totalPauseMenit = max(0, $laporanHarian->total_pause_menit ?? 0);

        // Pause = Downtime (tidak ada form downtime lagi)
        $totalDowntime = $totalPauseMenit;
        if ($totalDowntime > $grossTimeMenit) {
            $totalDowntime = $grossTimeMenit;
        }

        $waktuBersih = max(0, $grossTimeMenit - $totalDowntime);
        $capacityFisik = $validated['capacity_fisik'];
        $outputFisik = $validated['output_fisik'];

        $totalReject = $laporanHarian->rejectDetails()->sum('jumlah');
        $totalOutput = $outputFisik + $totalReject;

        $availability = $grossTimeMenit > 0 ? min(100, ($waktuBersih / $grossTimeMenit) * 100) : 0;
        $performance = $waktuBersih > 0 ? min(100, ($outputFisik * $mesin->ct / $waktuBersih) * 100) : 0;
        $quality = $totalOutput > 0 ? min(100, ($outputFisik / $totalOutput) * 100) : 0;
        $oee = min(100, ($availability / 100) * ($performance / 100) * ($quality / 100) * 100);
        $targetTotal = $targetMp * $totalMp;
        $produktivitas = $targetTotal > 0 ? min(999.99, ($outputFisik / $targetTotal) * 100) : 0;

        $laporanHarian->update([
            'mesin_id' => $mesinId,
            'ct' => $mesin->ct,
            'line_id' => $lineId,
            'target_mp' => $targetMp,
            'total_mp' => $totalMp,
            'capacity_fisik' => $capacityFisik,
            'output_fisik' => $outputFisik,
            'waktu_bersih_menit' => $waktuBersih,
            'target_teoritis' => $waktuBersih * $mesin->ct,
            'yield_persen' => $quality,
            'availability_persen' => $availability,
            'performance_persen' => $performance,
            'oee_persen' => $oee,
            'produktivitas_persen' => $produktivitas,
            'status' => 'submitted',
        ]);

        return redirect()->route('leader.laporan-harian.show', $laporanHarian->id)
            ->with('success', 'Laporan harian berhasil dikirim');
    }

    private function timeToMinutes(string $time): int
    {
        $parts = explode(':', $time);

        return (int) $parts[0] * 60 + (int) $parts[1];
    }
}
