<?php

namespace Database\Seeders;

use App\Models\AlasanDowntime;
use App\Models\LaporanHarian;
use App\Models\Line;
use App\Models\Mesin;
use App\Models\Produk;
use App\Models\User;
use App\Models\WeeklyPlan;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class LaporanHarianSeeder extends Seeder
{
    public function run(): void
    {
        // Clean previous laporan for idempotent seed
        LaporanHarian::query()->delete();

        $leader = User::where('email', 'leader@herbatech.com')->first();
        if (! $leader) {
            $this->command?->warn('LaporanHarianSeeder: leader not found');

            return;
        }

        $lines = Line::pluck('id', 'nama_line');
        $mesins = Mesin::all()->keyBy('nama_mesin');
        $produkIds = Produk::pluck('id', 'nama_produk');

        $alasan = AlasanDowntime::all()->keyBy('nama_alasan');
        if ($alasan->isEmpty() || $lines->isEmpty() || $mesins->isEmpty()) {
            $this->command?->warn('LaporanHarianSeeder: master data incomplete');

            return;
        }

        // Helper function for time calculation
        $timeToMinutes = function (string $time): int {
            [$h, $m] = explode(':', $time);

            return (int) $h * 60 + (int) $m;
        };

        // ==========================================
        // CURRENT WEEK LAPORAN (Sep 14-19, 2026)
        // ==========================================
        $monday = Carbon::create(2026, 9, 14);

        // Machine assignments per line
        $machineLine = [
            'Line A' => ['Sachet Tiger', 'Mixer Alpha', 'Packer Nova'],
            'Line B' => ['Sachet Kilo', 'Mixer Beta', 'Packer Rho'],
            'Line C' => ['Packer Rho', 'Packer Nova', 'Sachet Tiger'],
        ];

        $defaultDowntimes = [
            'Istirahat' => 60,
            'Briefing' => 15,
            'Line Clearance' => 15,
        ];

        $processMachines = [
            'mixing' => ['Mixer Alpha', 'Mixer Beta'],
            'filling' => ['Sachet Tiger', 'Sachet Kilo'],
            'packing' => ['Packer Nova', 'Packer Rho'],
        ];

        // Create laporan for current week
        foreach ($this->getCurrentWeekLaporan($monday, $mesins, $lines, $alasan, $defaultDowntimes, $processMachines, $machineLine) as $laporanData) {
            $this->createLaporan($laporanData, $leader, $mesins, $lines, $alasan, $timeToMinutes);
        }

        // ==========================================
        // HISTORICAL LAPORAN (5 months back)
        // ==========================================
        $monthsBack = 5;
        for ($m = 1; $m <= $monthsBack; $m++) {
            $date = Carbon::now()->subMonths($m)->setDay(15)->toDateString();

            $histLaporans = [
                ['produk' => 'Diabalance', 'proses' => 'filling', 'batch' => 'HIST'.Carbon::now()->subMonths($m)->format('Ym').'01', 'line' => 'Line A', 'mesin' => 'Sachet Tiger', 'target_mp' => 2000, 'total_mp' => 4, 'start' => '07:30', 'end' => '15:30', 'capacity' => 8000, 'output' => 7500, 'downtimes' => ['Istirahat' => 60, 'Briefing' => 15, 'Line Clearance' => 15, 'Setup / Changeover' => 20]],
                ['produk' => 'Vitablend', 'proses' => 'mixing', 'batch' => 'HIST'.Carbon::now()->subMonths($m)->format('Ym').'02', 'line' => 'Line A', 'mesin' => 'Mixer Alpha', 'target_mp' => 2000, 'total_mp' => 3, 'start' => '07:30', 'end' => '15:30', 'capacity' => 7000, 'output' => 6500, 'downtimes' => ['Istirahat' => 60, 'Briefing' => 15, 'Line Clearance' => 15]],
                ['produk' => 'Vitablend', 'proses' => 'filling', 'batch' => 'HIST'.Carbon::now()->subMonths($m)->format('Ym').'03', 'line' => 'Line B', 'mesin' => 'Sachet Kilo', 'target_mp' => 1900, 'total_mp' => 3, 'start' => '07:30', 'end' => '15:30', 'capacity' => 6900, 'output' => 6200, 'downtimes' => ['Istirahat' => 60, 'Briefing' => 15, 'Line Clearance' => 15, 'Keterlambatan Supply' => 25]],
                ['produk' => 'Eyovit', 'proses' => 'packing', 'batch' => 'HIST'.Carbon::now()->subMonths($m)->format('Ym').'04', 'line' => 'Line C', 'mesin' => 'Packer Rho', 'target_mp' => 2100, 'total_mp' => 4, 'start' => '07:30', 'end' => '15:30', 'capacity' => 8200, 'output' => 7800, 'downtimes' => ['Istirahat' => 60, 'Briefing' => 15, 'Line Clearance' => 15, 'Mesin Rusak' => 10]],
            ];

            foreach ($histLaporans as $s) {
                if (! isset($produkIds[$s['produk']])) {
                    continue;
                }

                $wp = WeeklyPlan::where('batch_number', $s['batch'])->first();
                if (! $wp) {
                    // Create fallback WP
                    $wp = WeeklyPlan::firstOrCreate(
                        ['batch_number' => $s['batch']],
                        [
                            'produk_id' => $produkIds[$s['produk']],
                            'proses' => $s['proses'],
                            'tanggal' => $date,
                            'status' => 'aktif',
                            'created_by' => $leader->id,
                        ]
                    );
                }

                $this->createLaporan([
                    'wp' => $wp,
                    'mesin' => $s['mesin'],
                    'line' => $s['line'],
                    'target_mp' => $s['target_mp'],
                    'total_mp' => $s['total_mp'],
                    'start' => $s['start'],
                    'end' => $s['end'],
                    'capacity' => $s['capacity'],
                    'output' => $s['output'],
                    'downtimes' => $s['downtimes'],
                ], $leader, $mesins, $lines, $alasan, $timeToMinutes);
            }
        }
    }

    private function getCurrentWeekLaporan(Carbon $monday, $mesins, $lines, $alasan, $defaultDowntimes, $processMachines, $machineLine): array
    {
        $laporans = [];
        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

        foreach (range(0, 5) as $dayOffset) {
            $date = $monday->copy()->addDays($dayOffset)->toDateString();
            $dayPlans = WeeklyPlan::with('produk')
                ->where('tanggal', $date)
                ->where('status', 'aktif')
                ->get();

            foreach ($dayPlans as $wp) {
                $proses = $wp->proses;
                $produk = $wp->produk->nama_produk ?? 'Unknown';

                // Assign machine based on process
                $availableMachines = $processMachines[$proses] ?? [];
                $machineName = $availableMachines[array_rand($availableMachines)] ?? 'Sachet Tiger';

                // Assign line based on machine
                $lineName = 'Line A';
                foreach ($machineLine as $ln => $machines) {
                    if (in_array($machineName, $machines)) {
                        $lineName = $ln;
                        break;
                    }
                }

                // Target MP based on process
                $targetMp = match ($proses) {
                    'mixing' => 2000,
                    'filling' => 1900,
                    'packing' => 2100,
                    default => 2000,
                };
                $totalMp = match ($proses) {
                    'mixing' => 3,
                    'filling' => 3,
                    'packing' => 4,
                    default => 3,
                };

                // Times
                $start = '07:30';
                $end = '15:30';

                // Capacity and output (realistic ranges)
                $capacity = match ($proses) {
                    'mixing' => rand(7000, 8000),
                    'filling' => rand(6500, 7500),
                    'packing' => rand(7500, 8500),
                    default => rand(7000, 8000),
                };
                // Output ~90-95% of capacity
                $output = (int) ($capacity * (rand(90, 95) / 100));

                // Downtimes
                $downtimes = $defaultDowntimes + [
                    'Setup / Changeover' => rand(15, 30),
                ];
                // Add random manual downtime occasionally
                if (rand(1, 10) <= 3) {
                    $manualReasons = ['Mesin Rusak', 'Keterlambatan Supply', 'Keterlambatan Material'];
                    $downtimes[$manualReasons[array_rand($manualReasons)]] = rand(10, 30);
                }

                $laporans[] = [
                    'wp' => $wp,
                    'mesin' => $machineName ?? $wp->mesin_id ?? 'Sachet Tiger',
                    'line' => $lineName,
                    'target_mp' => $targetMp,
                    'total_mp' => $totalMp,
                    'start' => $start,
                    'end' => $end,
                    'capacity' => $capacity,
                    'output' => $output,
                    'downtimes' => $downtimes,
                ];
            }
        }

        return $laporans;
    }

    private function createLaporan(array $data, $leader, $mesins, $lines, $alasan, $timeToMinutes): void
    {
        $wp = $data['wp'];
        $mesinName = $data['mesin'];
        $lineName = $data['line'];
        $targetMp = $data['target_mp'];
        $totalMp = $data['total_mp'];
        $start = $data['start'];
        $end = $data['end'];
        $capacity = $data['capacity'];
        $output = $data['output'];
        $downtimes = $data['downtimes'];

        $mesin = $mesins[$mesinName] ?? $mesins->first();
        $lineId = $lines[$lineName] ?? $lines->first()->id;

        // Kalkulasi sesuai LaporanHarianController
        $startMin = $timeToMinutes($start);
        $endMin = $timeToMinutes($end);
        if ($endMin < $startMin) {
            $endMin += 1440;
        }
        $gross = $endMin - $startMin;

        $totalDowntime = array_sum($downtimes);
        $waktuBersih = max(0, $gross - $totalDowntime);
        $targetTeoritis = $waktuBersih * (float) $mesin->ct;
        $yield = $capacity > 0 ? ($output / $capacity) * 100 : 0;
        $availability = $gross > 0 ? ($waktuBersih / $gross) * 100 : 0;
        $performance = $targetTeoritis > 0 ? ($output / $targetTeoritis) * 100 : 0;
        $oee = ($availability / 100) * ($performance / 100) * ($yield / 100) * 100;
        $targetTotal = $targetMp * $totalMp;
        $produktivitas = $targetTotal > 0 ? ($output / $targetTotal) * 100 : 0;

        $laporan = LaporanHarian::create([
            'user_id' => $leader->id,
            'weekly_plan_id' => $wp->id,
            'produk_id' => $wp->produk_id,
            'proses' => $wp->proses,
            'batch_number' => $wp->batch_number,
            'mesin_id' => $mesin->id,
            'ct' => $mesin->ct,
            'line_id' => $lineId,
            'tanggal' => $wp->tanggal,
            'target_mp' => $targetMp,
            'total_mp' => $totalMp,
            'start_time' => $start,
            'end_time' => $end,
            'gross_time_menit' => $gross,
            'capacity_fisik' => $capacity,
            'output_fisik' => $output,
            'waktu_bersih_menit' => $waktuBersih,
            'target_teoritis' => $targetTeoritis,
            'yield_persen' => $yield,
            'availability_persen' => $availability,
            'performance_persen' => $performance,
            'oee_persen' => $oee,
            'produktivitas_persen' => $produktivitas,
            'status' => 'submitted',
            'timer_status' => 'end',
        ]);

        // Save downtime details
        foreach ($downtimes as $nama => $durasi) {
            if (! isset($alasan[$nama])) {
                continue;
            }
            $laporan->downtimeDetails()->create([
                'alasan_downtime_id' => $alasan[$nama]->id,
                'durasi_menit' => $durasi,
            ]);
        }
        // Isi remaining alasan with 0
        foreach ($alasan as $nama => $obj) {
            if (isset($downtimes[$nama])) {
                continue;
            }
            $laporan->downtimeDetails()->create([
                'alasan_downtime_id' => $obj->id,
                'durasi_menit' => 0,
            ]);
        }
    }
}
