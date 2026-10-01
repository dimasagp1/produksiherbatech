<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Pastikan role sudah ada
        $this->call(RoleSeeder::class);

        $users = [
            [
                'email' => 'admin@herbatech.com',
                'name' => 'Admin Super',
                'role' => 'superadmin',
            ],
            [
                'email' => 'ppic@herbatech.com',
                'name' => 'PPIC User',
                'role' => 'ppic',
            ],
            [
                'email' => 'leader@herbatech.com',
                'name' => 'Budi Leader',
                'role' => 'leader',
            ],
            [
                'email' => 'leader2@herbatech.com',
                'name' => 'Sari Leader',
                'role' => 'leader',
            ],
            [
                'email' => 'spv@herbatech.com',
                'name' => 'SPV Produksi',
                'role' => 'spv',
            ],
            [
                'email' => 'manager@herbatech.com',
                'name' => 'Manager Produksi',
                'role' => 'manager',
            ],
            [
                'email' => 'operator@herbatech.com',
                'name' => 'Operator Produksi',
                'role' => 'operator',
            ],
            [
                'email' => 'warehouse@herbatech.com',
                'name' => 'Warehouse Admin',
                'role' => 'warehouse_admin',
            ],
        ];

        foreach ($users as $u) {
            $user = User::firstOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );

            if (! $user->hasRole($u['role'])) {
                $user->assignRole($u['role']);
            }
        }
    }
}
