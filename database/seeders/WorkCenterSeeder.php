<?php

namespace Database\Seeders;

use App\Models\WorkCenter;
use Illuminate\Database\Seeder;

class WorkCenterSeeder extends Seeder
{
    public function run(): void
    {
        $workCenters = [
            [
                'code' => 'WC-MIX',
                'name' => 'Mixing',
                'type' => 'mixing',
                'standard_ct_seconds' => 3, // Example: 3 seconds/pcs = 20 pcs/min
                'fit_mp' => 4,
                'shift_hours' => 6.5,
                'is_active' => true,
                'notes' => 'Area pencampuran bahan baku (ruahan/primer)',
            ],
            [
                'code' => 'WC-FILL',
                'name' => 'Filling',
                'type' => 'filling',
                'standard_ct_seconds' => 2, // Example: 2 seconds/pcs = 30 pcs/min
                'fit_mp' => 6,
                'shift_hours' => 6.5,
                'is_active' => true,
                'notes' => 'Area pengisian ke kemasan primer (botol/sachet/tube)',
            ],
            [
                'code' => 'WC-SEC',
                'name' => 'Secondary (Packing/Labeling)',
                'type' => 'secondary',
                'standard_ct_seconds' => 4, // Example: 4 seconds/pcs = 15 pcs/min
                'fit_mp' => 8,
                'shift_hours' => 6.5,
                'is_active' => true,
                'notes' => 'Area kemas sekunder: labeling, karton, shrink, kotak master',
            ],
        ];

        foreach ($workCenters as $wc) {
            WorkCenter::firstOrCreate(
                ['code' => $wc['code']],
                $wc
            );
        }
    }
};