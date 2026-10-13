<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder)->run();
        (new SettingSeeder)->run();
        (new MasterDataSeeder)->run();
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_admin_can_access_audit_log_index(): void
    {
        $admin = $this->makeUser('admin');

        AuditLog::create([
            'auditable_type' => 'App\Models\WeeklyPlan',
            'auditable_id' => 101,
            'event' => 'created',
            'old_values' => null,
            'new_values' => ['batch_number' => 'BT-TEST-AUDIT'],
            'user_id' => $admin->id,
            'reason' => 'Perencanaan batch',
            'ip' => '127.0.0.1',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.audit-log.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/AuditLog/Index')
            ->has('logs.data', 1)
        );
    }

    public function test_non_admin_cannot_access_audit_log_index(): void
    {
        $operator = $this->makeUser('operator');

        $response = $this->actingAs($operator)->get(route('admin.audit-log.index'));
        $response->assertForbidden();
    }
}
