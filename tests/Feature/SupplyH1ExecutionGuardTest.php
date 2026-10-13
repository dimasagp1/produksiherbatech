<?php

namespace Tests\Feature;

use App\Models\LaporanHarian;
use App\Models\Line;
use App\Models\MaterialSupply;
use App\Models\Mesin;
use App\Models\Produk;
use App\Models\User;
use App\Models\WeeklyPlan;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplyH1ExecutionGuardTest extends TestCase
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

    public function test_show_laporan_includes_material_supply_status(): void
    {
        $leader = $this->makeUser('leader');
        $produk = Produk::firstOrFail();
        $mesin = Mesin::firstOrFail();
        $line = Line::firstOrFail();

        $plan = WeeklyPlan::create([
            'produk_id' => $produk->id,
            'proses' => 'mixing',
            'batch_number' => 'BT-SUPPLY-01',
            'tanggal' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $leader->id,
        ]);

        $supply = MaterialSupply::create([
            'supply_number' => 'SUP-TEST-0001',
            'weekly_plan_id' => $plan->id,
            'required_date' => now()->toDateString(),
            'status' => 'pending',
            'created_by' => $leader->id,
        ]);

        $laporan = LaporanHarian::create([
            'user_id' => $leader->id,
            'weekly_plan_id' => $plan->id,
            'produk_id' => $produk->id,
            'proses' => 'mixing',
            'batch_number' => $plan->batch_number,
            'mesin_id' => $mesin->id,
            'ct' => $mesin->effective_ct,
            'line_id' => $line->id,
            'tanggal' => $plan->tanggal,
            'shift' => 'shift1',
            'target_mp' => 100,
            'total_mp' => 4,
            'capacity_fisik' => 500,
            'timer_status' => 'draft',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($leader)->get(route('leader.laporan-harian.show', $laporan->id));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Leader/LaporanHarian/Show')
            ->has('materialSupply')
            ->where('materialSupply.status', 'pending')
        );

        // Saat start timer, muncul warning notice
        $startResponse = $this->actingAs($leader)->post(route('leader.laporan-harian.timer.start', $laporan->id));
        $startResponse->assertSessionHas('warning');
    }
}
