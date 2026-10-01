<?php

namespace Database\Seeders;

use App\Models\ScmCategory;
use App\Models\ScmUom;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class ScmMasterSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'RM', 'name' => 'Raw Material'],
            ['code' => 'PM', 'name' => 'Packaging Material'],
            ['code' => 'FG', 'name' => 'Finished Goods'],
        ] as $c) {
            ScmCategory::updateOrCreate(['code' => $c['code']], $c);
        }

        foreach ([
            ['code' => 'PCS', 'name' => 'Pieces'],
            ['code' => 'BOX', 'name' => 'Box'],
            ['code' => 'KG', 'name' => 'Kilogram'],
            ['code' => 'L', 'name' => 'Liter'],
            ['code' => 'MTR', 'name' => 'Meter'],
        ] as $u) {
            ScmUom::updateOrCreate(['code' => $u['code']], $u);
        }

        $settings = [
            'revenue_coa_primary' => ['41000010', 'accounting'],
            'revenue_coa_secondary' => ['41000011', 'accounting'],
            'inventory_coa_1' => ['11300010', 'accounting'],
            'inventory_coa_2' => ['11300030', 'accounting'],
            'inventory_coa_3' => ['11300040', 'accounting'],
            'ira_target' => ['98', 'production'],
            'odoo_inventory_writeback_enabled' => ['0', 'odoo'],
            'odoo_delivery_sync_enabled' => ['1', 'odoo'],
        ];

        foreach ($settings as $key => [$value, $group]) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        }
    }
}
