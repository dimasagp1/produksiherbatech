<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ([
            'superadmin',
            'admin',
            'manager',
            'spv',
            'ppic',
            'leader',
            'operator',
            'warehouse_admin',
        ] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
    }
}
