<?php

namespace Database\Seeders;

use App\Models\Produk;
use App\Models\User;
use App\Models\WeeklyPlan;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class WeeklyPlanSeeder extends Seeder
{
    public function run(): void
    {
        $ppic = User::where('email', 'ppic@herbatech.com')->first();
        if (! $ppic) {
            $this->command?->warn('WeeklyPlanSeeder: no PPIC user found, skipping');

            return;
        }

        $produkMap = Produk::pluck('id', 'nama_produk');
        if ($produkMap->isEmpty()) {
            $this->command?->warn('WeeklyPlanSeeder: no produk found, run MasterDataSeeder first');

            return;
        }

        // ==========================================
        // TEST DATA DESIGN:
        // ==========================================
        // Week 1 (Sep 14-19, 2026) - Current week
        // - Non-parallel: Diabalance (filling only), Eyovit (packing only), FitTea (filling only)
        // - Parallel: Vitablend (mixing + filling), HerbaMix (mixing + filling + packing)
        // - CurcumaBlend (mixing only)
        // ==========================================

        $monday = Carbon::create(2026, 9, 14); // Monday Sep 14
        $plans = [
            // ===== SENIN (Sep 14) =====
            // Non-parallel
            ['produk' => 'Diabalance',    'proses' => 'filling', 'batch' => 'DB0926001', 'day' => 0, 'status' => 'aktif'],
            ['produk' => 'Eyovit',        'proses' => 'packing', 'batch' => 'EY0926001', 'day' => 0, 'status' => 'aktif'],
            ['produk' => 'FitTea',        'proses' => 'filling', 'batch' => 'FT0926001', 'day' => 0, 'status' => 'aktif'],
            // Parallel: Vitablend mixing + filling
            ['produk' => 'Vitablend',     'proses' => 'mixing',  'batch' => 'VB0926001', 'day' => 0, 'status' => 'aktif'],
            ['produk' => 'Vitablend',     'proses' => 'filling', 'batch' => 'VB0926002', 'day' => 0, 'status' => 'aktif'],
            // Parallel: HerbaMix mixing + filling + packing
            ['produk' => 'HerbaMix',      'proses' => 'mixing',  'batch' => 'HM0926001', 'day' => 0, 'status' => 'aktif'],
            ['produk' => 'HerbaMix',      'proses' => 'filling', 'batch' => 'HM0926002', 'day' => 0, 'status' => 'aktif'],
            ['produk' => 'HerbaMix',      'proses' => 'packing', 'batch' => 'HM0926003', 'day' => 0, 'status' => 'aktif'],

            // ===== SELASA (Sep 15) =====
            // Non-parallel
            ['produk' => 'Diabalance Forte', 'proses' => 'filling', 'batch' => 'DBF0926001', 'day' => 1, 'status' => 'aktif'],
            ['produk' => 'Eyovit Gold',        'proses' => 'packing', 'batch' => 'EYG0926001', 'day' => 1, 'status' => 'aktif'],
            // Parallel: Vitablend Plus mixing + filling
            ['produk' => 'Vitablend Plus',     'proses' => 'mixing',  'batch' => 'VBP0926001', 'day' => 1, 'status' => 'aktif'],
            ['produk' => 'Vitablend Plus',     'proses' => 'filling', 'batch' => 'VBP0926002', 'day' => 1, 'status' => 'aktif'],
            // Parallel: CurcumaBlend mixing + filling
            ['produk' => 'CurcumaBlend',       'proses' => 'mixing',  'batch' => 'CB0926001', 'day' => 1, 'status' => 'aktif'],
            ['produk' => 'CurcumaBlend',       'proses' => 'filling', 'batch' => 'CB0926002', 'day' => 1, 'status' => 'aktif'],
            // Non-parallel: CurcumaBlend X packing only
            ['produk' => 'CurcumaBlend X',     'proses' => 'packing', 'batch' => 'CBX0926001', 'day' => 1, 'status' => 'aktif'],

            // ===== RABU (Sep 16) =====
            // Non-parallel
            ['produk' => 'Diabalance Lite',   'proses' => 'filling', 'batch' => 'DBL0926001', 'day' => 2, 'status' => 'aktif'],
            ['produk' => 'Eyovit Advance',    'proses' => 'packing', 'batch' => 'EYA0926001', 'day' => 2, 'status' => 'aktif'],
            // Parallel: Vitablend Kids mixing + filling + packing
            ['produk' => 'Vitablend Kids',    'proses' => 'mixing',  'batch' => 'VBK0926001', 'day' => 2, 'status' => 'aktif'],
            ['produk' => 'Vitablend Kids',    'proses' => 'filling', 'batch' => 'VBK0926002', 'day' => 2, 'status' => 'aktif'],
            ['produk' => 'Vitablend Kids',    'proses' => 'packing', 'batch' => 'VBK0926003', 'day' => 2, 'status' => 'aktif'],
            // Non-parallel
            ['produk' => 'FitTea Detox',      'proses' => 'filling', 'batch' => 'FTD0926001', 'day' => 2, 'status' => 'aktif'],

            // ===== KAMIS (Sep 17) =====
            // Non-parallel
            ['produk' => 'ImunMax',           'proses' => 'packing', 'batch' => 'IM0926001', 'day' => 3, 'status' => 'aktif'],
            // Parallel: HerbaMix Premium mixing + filling
            ['produk' => 'HerbaMix Premium',  'proses' => 'mixing',  'batch' => 'HMP0926001', 'day' => 3, 'status' => 'aktif'],
            ['produk' => 'HerbaMix Premium',  'proses' => 'filling', 'batch' => 'HMP0926002', 'day' => 3, 'status' => 'aktif'],
            // Non-parallel
            ['produk' => 'FitTea Green',      'proses' => 'filling', 'batch' => 'FTG0926001', 'day' => 3, 'status' => 'aktif'],
            ['produk' => 'SlimBio',           'proses' => 'filling', 'batch' => 'SB0926001', 'day' => 3, 'status' => 'aktif'],

            // ===== JUMAT (Sep 18) =====
            // Non-parallel
            ['produk' => 'ImunMax Plus',      'proses' => 'packing', 'batch' => 'IMP0926001', 'day' => 4, 'status' => 'aktif'],
            ['produk' => 'SlimBio Pro',       'proses' => 'filling', 'batch' => 'SBP0926001', 'day' => 4, 'status' => 'aktif'],
            // Parallel: NutriKit mixing + filling
            ['produk' => 'NutriKit',          'proses' => 'mixing',  'batch' => 'NK0926001', 'day' => 4, 'status' => 'aktif'],
            ['produk' => 'NutriKit',          'proses' => 'filling', 'batch' => 'NK0926002', 'day' => 4, 'status' => 'aktif'],
            // Non-parallel
            ['produk' => 'NutriKit Junior',   'proses' => 'mixing',  'batch' => 'NKJ0926001', 'day' => 4, 'status' => 'aktif'],

            // ===== SABTU (Sep 19) =====
            // Non-parallel
            ['produk' => 'ObatHerbal Plus',   'proses' => 'packing', 'batch' => 'OHP0926001', 'day' => 5, 'status' => 'aktif'],
            ['produk' => 'ObatHerbal Max',    'proses' => 'packing', 'batch' => 'OHM0926001', 'day' => 5, 'status' => 'aktif'],
            // Parallel: GreenExtract mixing + filling
            ['produk' => 'GreenExtract',      'proses' => 'mixing',  'batch' => 'GE0926001', 'day' => 5, 'status' => 'aktif'],
            ['produk' => 'GreenExtract',      'proses' => 'filling', 'batch' => 'GE0926002', 'day' => 5, 'status' => 'aktif'],
            // Non-parallel
            ['produk' => 'CleanVita',         'proses' => 'filling', 'batch' => 'CV0926001', 'day' => 5, 'status' => 'aktif'],
            ['produk' => 'RexPower',          'proses' => 'packing', 'batch' => 'RP0926001', 'day' => 5, 'status' => 'aktif'],
        ];

        foreach ($plans as $p) {
            if (! isset($produkMap[$p['produk']])) {
                continue;
            }
            $tanggal = $monday->copy()->addDays($p['day'])->toDateString();

            WeeklyPlan::updateOrCreate(
                ['batch_number' => $p['batch']],
                [
                    'produk_id' => $produkMap[$p['produk']],
                    'proses' => $p['proses'],
                    'tanggal' => $tanggal,
                    'status' => $p['status'],
                    'created_by' => $ppic->id,
                ]
            );
        }

        // ==========================================
        // HISTORICAL DATA (5 months back) for monthly chart
        // ==========================================
        $monthsBack = 5;
        for ($m = 1; $m <= $monthsBack; $m++) {
            $month = Carbon::now()->subMonths($m)->month;
            $year = Carbon::now()->subMonths($m)->year;
            $date = Carbon::create($year, $month, 15)->toDateString(); // Mid-month

            // 3-4 historical plans per month
            $histPlans = [
                ['produk' => 'Diabalance',     'proses' => 'filling', 'batch' => "HIST{$year}{$month}01"],
                ['produk' => 'Vitablend',      'proses' => 'mixing',  'batch' => "HIST{$year}{$month}02"],
                ['produk' => 'Vitablend',      'proses' => 'filling', 'batch' => "HIST{$year}{$month}03"],
                ['produk' => 'Eyovit',         'proses' => 'packing', 'batch' => "HIST{$year}{$month}04"],
            ];

            foreach ($histPlans as $hp) {
                if (! isset($produkMap[$hp['produk']])) {
                    continue;
                }
                WeeklyPlan::updateOrCreate(
                    ['batch_number' => $hp['batch']],
                    [
                        'produk_id' => $produkMap[$hp['produk']],
                        'proses' => $hp['proses'],
                        'tanggal' => $date,
                        'status' => 'aktif',
                        'created_by' => $ppic->id,
                    ]
                );
            }
        }
    }
}
