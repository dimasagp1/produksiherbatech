<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        Setting::firstOrCreate(
            ['key' => 'target_output_multiplier'],
            ['value' => '2000', 'group' => 'production']
        );

        Setting::firstOrCreate(
            ['key' => 'odoo_mo_sync_enabled'],
            ['value' => '1', 'group' => 'odoo']
        );

        Setting::firstOrCreate(
            ['key' => 'odoo_seed_enabled'],
            ['value' => '0', 'group' => 'odoo']
        );
    }
}
