<?php

namespace App\Http\Controllers;

use App\Models\AlasanDowntime;
use App\Models\LaporanHarian;
use App\Models\Line;
use App\Models\Mesin;
use App\Models\Produk;
use App\Models\RejectDetail;
use App\Models\WeeklyPlan;
use App\Models\WorkCenter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $today = now()->toDateString();

        $selectedDate = $request->input('tanggal', $today);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate) || ! strtotime($selectedDate)) {
            $selectedDate = $today;
        }
        $isLive = $selectedDate === $today;

        $selectedShift = $request->input('shift');
        $shiftValid = in_array($selectedShift, ['shift1', 'shift2'], true);

        $workCenterId = $request->input('work_center_id');
        $viewMode = $request->input('view_mode', 'live'); // 'live' | 'post_sync'

        $currentPlan = WeeklyPlan::with(['produk', 'workCenter'])
            ->where('tanggal', $selectedDate)
            ->where('status', 'aktif')
            ->first();

        // Base query for laporans
        $baseQuery = LaporanHarian::with(['produk', 'line', 'user', 'mesin', 'workCenter'])
            ->withSum('rejectDetails as total_reject', 'jumlah')
            ->where('tanggal', $selectedDate);

        // For post_sync view, filter by odoo_synced_at date
        if ($viewMode === 'post_sync') {
            $baseQuery->whereNotNull('odoo_synced_at')
                ->whereDate('odoo_synced_at', $selectedDate);
        }

        $baseQuery->when($shiftValid, fn ($q) => $q->where('shift', $selectedShift))
            ->when($workCenterId, fn ($q, $wc) => $q->where('work_center_id', $wc))
            ->when($user->hasAnyRole(['leader', 'operator']), function ($query) use ($user) {
                return $query->where('user_id', $user->id);
            });

        $todayLaporans = $baseQuery->get();

        // enrich sisa & available for detail
        $todayLaporans->each(function ($l) {
            $l->total_reject = (int) ($l->total_reject ?? 0);
            $avail = $l->output_fisik ?? $l->capacity_fisik ?? 0;
            $l->available_qty = (int) $avail;
            $l->sisa_qty = max(0, $l->available_qty - $l->total_reject);
            $l->reject_jenis = $l->rejectDetails->groupBy('jenis_reject')->map->sum('jumlah')->sortDesc()->keys()->first() ?? null;
        });

        // KPI Aggregations
        $avgOee = $todayLaporans->avg('oee_persen') ?? 0;
        $avgYield = $todayLaporans->avg('effective_quality') ?? $todayLaporans->avg('yield_persen') ?? 0;
        $avgProduktivitas = $todayLaporans->avg('produktivitas_persen') ?? 0;
        $totalGross = $todayLaporans->sum('gross_time_menit');
        $totalWaktuBersih = $todayLaporans->sum('waktu_bersih_menit');
        $totalDowntime = $totalGross - $totalWaktuBersih;
        $avgAvailability = $todayLaporans->avg('availability_persen') ?? 0;
        $avgPerformance = $todayLaporans->avg('performance_persen') ?? 0;

        // Downtime pareto
        $todayLaporansWithDowntime = LaporanHarian::with(['downtimeDetails.alasanDowntime'])
            ->where('tanggal', $selectedDate)
            ->when($shiftValid, fn ($q) => $q->where('shift', $selectedShift))
            ->when($workCenterId, fn ($q, $wc) => $q->where('work_center_id', $wc))
            ->when($user->hasRole('leader'), fn ($q) => $q->where('user_id', $user->id))
            ->get();
        $downtimePareto = $todayLaporansWithDowntime->flatMap(fn ($l) => $l->downtimeDetails)
            ->filter(fn ($d) => ($d->durasi_menit ?? 0) > 0)
            ->groupBy(fn ($d) => $d->keterangan ?? 'Lainnya')
            ->map(fn ($group) => $group->sum('durasi_menit'))
            ->sortDesc();

        // Heat grid - now grouped by Work Center
        $workCenters = WorkCenter::active()->get()->pluck('name', 'id');
        $prosesList = ['mixing', 'filling', 'secondary'];
        $heatData = [];
        foreach ($workCenters as $wcId => $wcName) {
            foreach ($prosesList as $ps) {
                $val = $todayLaporans->filter(fn ($l) => ($l->work_center_id == $wcId) && $l->proses === $ps)->sum('output_fisik');
                $heatData[$wcName][$ps] = (int) $val;
            }
        }

        // Monitoring by proses
        $monitoringByProses = $todayLaporans
            ->groupBy('proses')
            ->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'avg_oee' => $group->avg('oee_persen'),
                    'total_output' => $group->sum('output_fisik'),
                    'total_reject' => $group->sum('total_reject'),
                ];
            });

        // Monitoring by produk
        $monitoringByProduk = $todayLaporans
            ->groupBy('produk_id')
            ->map(function ($group) {
                return [
                    'produk' => $group->first()->produk->nama_produk ?? '-',
                    'count' => $group->count(),
                    'avg_oee' => $group->avg('oee_persen'),
                    'total_output' => $group->sum('output_fisik'),
                    'total_reject' => $group->sum('total_reject'),
                ];
            });

        // Batch breakdown
        $batchBreakdown = $todayLaporans
            ->groupBy('batch_number')
            ->map(function ($group) {
                $first = $group->first();
                $totalReject = $group->sum('total_reject');
                $totalOutput = $group->sum('output_fisik');
                $sisa = max(0, $totalOutput - $totalReject);

                return [
                    'batch_number' => $first->batch_number,
                    'produk' => $first->produk->nama_produk ?? '-',
                    'proses' => $first->proses ?? '-',
                    'work_center' => $first->workCenter->name ?? '-',
                    'total_output' => $totalOutput,
                    'oee' => $group->avg('oee_persen'),
                    'yield' => $group->avg('yield_persen'),
                    'total_reject' => (int) $totalReject,
                    'sisa_qty' => (int) $sisa,
                ];
            })->values();

        // Monthly output
        $monthlyQuery = LaporanHarian::selectRaw('MONTH(tanggal) as month, SUM(output_fisik) as total')
            ->whereYear('tanggal', now()->year)
            ->whereIn('status', ['submitted', 'locked'])
            ->whereNotNull('output_fisik')
            ->when($user->hasRole('leader'), fn ($q) => $q->where('user_id', $user->id));
        $monthlyOutput = $monthlyQuery->groupBy('month')->pluck('total', 'month')->toArray();

        // Reject stats
        $totalOutputForRate = $todayLaporans->sum('output_fisik');
        $totalReject = (int) $todayLaporans->sum('total_reject');
        $rejectRate = $totalOutputForRate > 0 ? round(($totalReject / $totalOutputForRate) * 100, 2) : 0;

        // Reject by jenis
        $rejectByJenis = RejectDetail::whereHas('laporanHarian', function ($q) use ($selectedDate, $user, $workCenterId) {
            $q->where('tanggal', $selectedDate)
                ->when($user->hasRole('leader'), fn ($qq) => $qq->where('user_id', $user->id))
                ->when($workCenterId, fn ($qq, $wc) => $qq->where('work_center_id', $wc));
        })
            ->selectRaw('jenis_reject, SUM(jumlah) as total')
            ->groupBy('jenis_reject')
            ->pluck('total', 'jenis_reject')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        // Reject by produk
        $rejectByProdukRaw = RejectDetail::with('laporanHarian.produk')
            ->whereHas('laporanHarian', function ($q) use ($selectedDate, $user, $workCenterId) {
                $q->where('tanggal', $selectedDate)
                    ->when($user->hasRole('leader'), fn ($qq) => $qq->where('user_id', $user->id))
                    ->when($workCenterId, fn ($qq, $wc) => $qq->where('work_center_id', $wc));
            })->get();
        $rejectByProduk = $rejectByProdukRaw->groupBy(fn ($r) => $r->laporanHarian->produk->nama_produk ?? '-')
            ->map(fn ($g) => (int) $g->sum('jumlah'))
            ->toArray();

        // Monthly reject
        $monthlyReject = RejectDetail::whereHas('laporanHarian', function ($q) use ($user) {
            $q->whereYear('tanggal', now()->year)
                ->whereIn('status', ['submitted', 'locked'])
                ->whereNotNull('output_fisik')
                ->when($user->hasRole('leader'), fn ($qq) => $qq->where('user_id', $user->id));
        })
            ->join('laporan_harians', 'laporan_harians.id', '=', 'reject_details.laporan_harian_id')
            ->selectRaw('MONTH(laporan_harians.tanggal) as m, SUM(reject_details.jumlah) as total')
            ->groupBy('m')
            ->pluck('total', 'm')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        $rejectStats = [
            'total' => $totalReject,
            'rate' => $rejectRate,
            'byJenis' => $rejectByJenis,
            'byProduk' => $rejectByProduk,
            'totalOutput' => (int) $totalOutputForRate,
        ];

        $masterStats = null;
        if ($user->hasRole(['superadmin', 'admin', 'ppic'])) {
            $masterStats = [
                'produk' => Produk::aktif()->count(),
                'produk_total' => Produk::count(),
                'mesin' => Mesin::aktif()->count(),
                'line' => Line::aktif()->count(),
                'work_center' => WorkCenter::active()->count(),
                'alasan_downtime' => AlasanDowntime::aktif()->count(),
            ];
        }
        $weeklyPlanStats = null;
        if ($user->hasRole(['superadmin', 'ppic'])) {
            $weeklyPlanStats = [
                'aktif' => WeeklyPlan::where('status', 'aktif')->count(),
                'draft' => WeeklyPlan::where('status', 'draft')->count(),
                'hari_ini' => WeeklyPlan::where('tanggal', $selectedDate)->where('status', 'aktif')->count(),
                'minggu_ini' => WeeklyPlan::whereBetween('tanggal', [now()->parse($selectedDate)->startOfWeek(), now()->parse($selectedDate)->endOfWeek()])->where('status', 'aktif')->count(),
            ];
        }

        $thresholds = [
            'produktivitas' => (int) config('linepulse.kpi.produktivitas_target', 90),
            'oee' => (int) config('linepulse.kpi.oee_target', 70),
            'yield' => (int) config('linepulse.kpi.yield_target', 85),
            'heat' => config('linepulse.heat', ['high' => 7000, 'medium' => 5000, 'low' => 3000]),
            'reject_rate_warning' => (int) config('linepulse.kpi.reject_rate_warning', 5),
        ];

        return Inertia::render('Dashboard', [
            'currentPlan' => $currentPlan,
            'todayLaporans' => $todayLaporans,
            'selectedDate' => $selectedDate,
            'selectedShift' => $shiftValid ? $selectedShift : null,
            'isLive' => $isLive,
            'viewMode' => $viewMode,
            'workCenterId' => $workCenterId,
            'workCenters' => WorkCenter::active()->get(),
            'thresholds' => $thresholds,
            'kpis' => [
                'oee' => round($avgOee, 2),
                'yield' => round($avgYield, 2),
                'produktivitas' => round($avgProduktivitas, 2),
                'downtime' => $totalDowntime,
                'availability' => round($avgAvailability, 2),
                'performance' => round($avgPerformance, 2),
                'gross' => $totalGross,
                'waktuBersih' => $totalWaktuBersih,
            ],
            'rejectStats' => $rejectStats,
            'rejectByJenis' => $rejectByJenis,
            'monthlyReject' => $monthlyReject,
            'downtimePareto' => $downtimePareto,
            'heatData' => $heatData,
            'heatLines' => $workCenters,
            'monitoringByProses' => $monitoringByProses,
            'monitoringByProduk' => $monitoringByProduk,
            'batchBreakdown' => $batchBreakdown,
            'monthlyOutput' => $monthlyOutput,
            'masterStats' => $masterStats,
            'weeklyPlanStats' => $weeklyPlanStats,
            'summary' => $todayLaporans->first() ? [
                'leader' => $todayLaporans->first()->user->name ?? $user->name,
                'produk' => $todayLaporans->first()->produk->nama_produk ?? $currentPlan?->produk->nama_produk ?? '-',
                'proses' => $todayLaporans->first()->proses ?? $currentPlan?->proses ?? '-',
                'batch' => $todayLaporans->first()->batch_number ?? $currentPlan?->batch_number ?? '-',
                'line' => $todayLaporans->first()->line->nama_line ?? '-',
                'mesin' => $todayLaporans->first()->mesin->nama_mesin ?? '-',
                'work_center' => $todayLaporans->first()->workCenter->name ?? '-',
            ] : null,
        ]);
    }
};