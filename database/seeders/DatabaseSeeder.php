<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Seed roles and master data (Fase 1 MVP)
        $this->call([
            RoleSeeder::class,
            SettingSeeder::class,
            ScmMasterSeeder::class,
            MasterDataSeeder::class,
            WorkCenterSeeder::class,
            // ProdukSeeder::class, // Dinonaktifkan agar menggunakan produk Odoo ERP
        ]);

        // Create default users with email_verified_at
        $admin = User::firstOrCreate(['email' => 'admin@herbatech.com'], [
            'name' => 'Admin',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $admin->assignRole('superadmin');

        $ppic = User::firstOrCreate(['email' => 'ppic@herbatech.com'], [
            'name' => 'PPIC User',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $ppic->assignRole('ppic');

        $leader = User::firstOrCreate(['email' => 'leader@herbatech.com'], [
            'name' => 'Budi',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $leader->assignRole('leader');

        // Additional leaders for dashboard variety
        $leader2 = User::firstOrCreate(['email' => 'leader2@herbatech.com'], [
            'name' => 'Sari',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $leader2->assignRole('leader');

        $spv = User::firstOrCreate(['email' => 'spv@herbatech.com'], [
            'name' => 'SPV User',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $spv->assignRole('spv');

        $manager = User::firstOrCreate(['email' => 'manager@herbatech.com'], [
            'name' => 'Manager User',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $manager->assignRole('manager');

        $operator = User::firstOrCreate(['email' => 'operator@herbatech.com'], [
            'name' => 'Operator Produksi',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $operator->assignRole('operator');

        $warehouse = User::firstOrCreate(['email' => 'warehouse@herbatech.com'], [
            'name' => 'Warehouse Admin',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $warehouse->assignRole('warehouse_admin');

        // Seed weekly plan & laporan (depends on master + users)
        $this->call([
            WeeklyPlanSeeder::class,
            LaporanHarianSeeder::class,
        ]);
    }
};