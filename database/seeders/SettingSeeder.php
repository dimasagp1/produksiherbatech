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

        // Phase 1 - Work Center & Quality settings
        Setting::firstOrCreate(
            ['key' => 'shift_hours'],
            ['value' => '6.5', 'group' => 'production']
        );

        Setting::firstOrCreate(
            ['key' => 'mo_status_filter'],
            ['value' => 'confirmed,progress,in_progress', 'group' => 'odoo']
        );

        Setting::firstOrCreate(
            ['key' => 'use_work_center'],
            ['value' => '1', 'group' => 'production']
        );

        Setting::firstOrCreate(
            ['key' => 'quality_input_method'],
            ['value' => 'auto', 'group' => 'production']
        );
    }
};