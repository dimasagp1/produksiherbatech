<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LaporanHarian;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HistoryController extends Controller
{
    public function index(Request $request)
    {
        $bulan = $request->input('bulan', now()->format('Y-m'));
        $search = $request->input('search', '');
        $shift = $request->input('shift');

        [$year, $month] = explode('-', $bulan);

        $query = LaporanHarian::with(['produk', 'mesin', 'line', 'user', 'downtimeDetails.alasanDowntime'])
            ->withSum('rejectDetails as total_reject', 'jumlah')
            ->whereYear('tanggal', $year)
            ->whereMonth('tanggal', $month)
            ->whereIn('status', ['submitted', 'locked']);

        if (in_array($shift, ['shift1', 'shift2'], true)) {
            $query->where('shift', $shift);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('batch_number', 'like', "%{$search}%")
                    ->orWhereHas('produk', fn ($q) => $q->where('nama_produk', 'like', "%{$search}%"));
            });
        }

        $laporans = $query->orderBy('batch_number')
            ->orderBy('tanggal')
            ->orderByRaw("FIELD(proses, 'mixing', 'filling', 'packing')")
            ->get();

        $batchHistory = $laporans->groupBy(fn ($l) => $l->batch_number.'|'.$l->tanggal->format('Y-m-d'))->map(function ($batchLaporans) {
            $first = $batchLaporans->first();

            $allDowntimes = $batchLaporans->flatMap(fn ($l) => $l->downtimeDetails);
            $totalDowntime = $allDowntimes->sum('durasi_menit');
            $downtimeReasons = $allDowntimes
                ->filter(fn ($d) => $d->durasi_menit > 0)
                ->groupBy(fn ($d) => $d->keterangan ?? $d->alasanDowntime->nama_alasan ?? 'Lainnya')
                ->map(fn ($group) => $group->sum('durasi_menit'))
                ->sortDesc()
                ->toArray();

            return [
                'batch_number' => $first->batch_number,
                'produk' => $first->produk->nama_produk ?? '-',
                'shift' => $first->shift ?? null,
                'tanggal_mulai' => $batchLaporans->min('tanggal'),
                'tanggal_selesai' => $batchLaporans->max('tanggal'),
                'proses_count' => $batchLaporans->pluck('proses')->unique()->count(),
                'proses_list' => $batchLaporans->pluck('proses')->unique()->values()->toArray(),
                'total_output' => $batchLaporans->sum('output_fisik'),
                'total_reject' => (int) $batchLaporans->sum('total_reject'),
                'total_gross_time' => $batchLaporans->sum('gross_time_menit'),
                'total_waktu_bersih' => $batchLaporans->sum('waktu_bersih_menit'),
                'total_downtime' => $totalDowntime,
                'downtime_reasons' => $downtimeReasons,
                'avg_oee' => round($batchLaporans->avg('oee_persen'), 1),
                'avg_availability' => round($batchLaporans->avg('availability_persen'), 1),
                'avg_performance' => round($batchLaporans->avg('performance_persen'), 1),
                'avg_yield' => round($batchLaporans->avg('yield_persen'), 1),
                'status' => $batchLaporans->every(fn ($l) => $l->status === 'locked') ? 'locked' : 'submitted',
                'laporans' => $batchLaporans->map(fn ($l) => [
                    'id' => $l->id,
                    'proses' => $l->proses,
                    'shift' => $l->shift ?? null,
                    'tanggal' => $l->tanggal,
                    'mesin' => $l->mesin->nama_mesin ?? '-',
                    'line' => $l->line->nama_line ?? '-',
                    'leader' => $l->user->name ?? '-',
                    'output_fisik' => $l->output_fisik,
                    'gross_time_menit' => $l->gross_time_menit,
                    'waktu_bersih_menit' => $l->waktu_bersih_menit,
                    'oee_persen' => round($l->oee_persen ?? 0, 1),
                    'downtime_total' => $l->downtimeDetails->sum('durasi_menit'),
                    'downtime_reasons' => $l->downtimeDetails
                        ->filter(fn ($d) => $d->durasi_menit > 0)
                        ->map(fn ($d) => [
                            'alasan' => $d->keterangan ?? $d->alasanDowntime->nama_alasan ?? '-',
                            'durasi' => $d->durasi_menit,
                        ])
                        ->values()
                        ->toArray(),
                ])->values()->toArray(),
            ];
        })->values();

        $summary = [
            'total_batches' => $batchHistory->count(),
            'total_output' => $batchHistory->sum('total_output'),
            'total_reject' => $batchHistory->sum('total_reject'),
            'total_downtime' => $batchHistory->sum('total_downtime'),
            'avg_oee' => round($laporans->avg('oee_persen') ?? 0, 1),
        ];

        return Inertia::render('Admin/History/Index', [
            'batchHistory' => $batchHistory,
            'summary' => $summary,
            'bulan' => $bulan,
            'search' => $search,
            'shift' => $shift,
        ]);
    }
}
