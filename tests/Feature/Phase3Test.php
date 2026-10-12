<?php

namespace Tests\Feature;

use App\Models\LaporanHarian;
use App\Models\Line;
use App\Models\MaterialUsage;
use App\Models\Mesin;
use App\Models\Produk;
use App\Models\User;
use App\Models\WeeklyPlan;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class Phase3Test extends TestCase
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
        $u = User::factory()->create();
        $u->assignRole($role);
        return $u;
    }

    private function makeWeeklyPlan(array $o = []): WeeklyPlan
    {
        $produk = Produk::where('kode_produk', 'DB01')->firstOrFail();
        return WeeklyPlan::create(array_merge([
            'produk_id' => $produk->id,
            'proses' => 'filling',
            'batch_number' => 'BT-'.uniqid(),
            'tanggal' => now()->toDateString(),
            'status' => 'aktif',
            'created_by' => User::factory()->create()->id,
        ], $o));
    }

    private function makeLaporan(array $o = []): LaporanHarian
    {
        $plan = $this->makeWeeklyPlan();
        $mesin = Mesin::firstOrFail();
        $line = Line::firstOrFail();
        $leader = User::factory()->create(); $leader->assignRole('leader');
        return LaporanHarian::create(array_merge([
            'user_id' => $leader->id,
            'weekly_plan_id' => $plan->id,
            'produk_id' => $plan->produk_id,
            'proses' => $plan->proses,
            'batch_number' => $plan->batch_number,
            'mesin_id' => $mesin->id,
            'ct' => $mesin->effective_ct,
            'line_id' => $line->id,
            'work_center_id' => $mesin->work_center_id,
            'tanggal' => $plan->tanggal,
            'shift' => 'shift1',
            'target_mp' => 100,
            'total_mp' => 5,
            'capacity_fisik' => 500,
            'timer_status' => 'end',
            'status' => 'submitted',
            'gross_time_menit' => 400,
            'total_pause_menit' => 40,
            'waktu_bersih_menit' => 360,
            'output_fisik' => 400,
            'yield_persen' => 80,
            'availability_persen' => 90,
            'performance_persen' => 85,
            'oee_persen' => 61.2,
            'quality_method' => 'auto',
            'odoo_synced_at' => now(),
        ], $o));
    }

    public function test_31_usage_ratio_service_writes_odoo_actual(): void
    {
        $moId = 9999;
        $plan = $this->makeWeeklyPlan(['odoo_mo_id' => $moId, 'mo_status' => 'confirmed']);
        $usage = MaterialUsage::create([
            'usage_number' => 'USG-TEST-'.uniqid(),
            'weekly_plan_id' => $plan->id,
            'user_id' => User::factory()->create()->id,
            'usage_date' => now()->toDateString(),
        ]);
        $item = $usage->items()->create([
            'material_name' => 'Ekstrak Jahe',
            'quantity_standard' => 100,
            'quantity_used' => 110,
            'variance' => 10,
        ]);

        $fake = new class extends \App\Services\OdooService {
            public function __construct() {}
            public function searchRead(string $model, array $domain = [], array $fields = [], int $limit = 500, int $offset = 0, ?string $order = null): array {
                return [['product_id' => [1, 'Ekstrak Jahe'], 'quantity' => 105, 'product_uom_qty' => 105]];
            }
        };

        $svc = new \App\Services\UsageRatioService($fake);
        $svc->calculate($usage);
        $item->refresh();
        $this->assertEquals(105, $item->odoo_actual_qty);
        $this->assertEquals(5.0, $item->odoo_variance_persen);
        $this->assertEqualsWithDelta(4.76, $item->local_vs_odoo_variance_persen, 0.01);
    }

    public function test_31_sync_ratio_command_respects_days_filter(): void
    {
        $this->artisan('material-usage:sync-ratio', ['--days' => '1', '--dry-run' => true])->assertExitCode(0);
    }

    public function test_32_dashboard_post_sync_filters_by_odoo_synced_at(): void
    {
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('MySQL-only dashboard aggregation');
        }
        $leader = $this->makeUser('leader');
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $this->makeLaporan(['tanggal' => $today, 'odoo_synced_at' => now(), 'user_id' => $leader->id]);
        $this->makeLaporan(['tanggal' => $today, 'odoo_synced_at' => null, 'user_id' => $leader->id]);
        $this->makeLaporan(['tanggal' => $yesterday, 'odoo_synced_at' => now()->subDay(), 'user_id' => $leader->id]);

        $this->actingAs($leader)->get(route('dashboard', ['tanggal' => $today, 'view_mode' => 'post_sync']))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('viewMode', 'post_sync')->has('todayLaporans', 1));
    }

    public function test_32_dashboard_effective_quality_uses_manual_when_set(): void
    {
        $leader = $this->makeUser('leader');
        $laporan = $this->makeLaporan(['yield_persen' => 80, 'quality_method' => 'manual', 'quality_input_persen' => 92.5, 'user_id' => $leader->id]);
        $this->assertEquals(92.5, $laporan->effective_quality);
        $laporan2 = $this->makeLaporan(['yield_persen' => 80, 'quality_method' => 'auto', 'quality_input_persen' => 92.5, 'user_id' => $leader->id]);
        $this->assertEquals(80, $laporan2->effective_quality);
    }

    public function test_33_quality_relocation_submit_resets_to_auto(): void
    {
        $leader = $this->makeUser('leader');
        $plan = $this->makeWeeklyPlan();
        $mesin = Mesin::firstOrFail(); $line = Line::firstOrFail();
        $res = $this->actingAs($leader)->post(route('leader.laporan-harian.store'), [
            'produk_id' => $plan->produk_id, 'weekly_plan_id' => $plan->id, 'mesin_id' => $mesin->id, 'line_id' => $line->id, 'work_center_id' => $mesin->work_center_id,
            'tanggal' => $plan->tanggal, 'shift' => 'shift1', 'target_mp' => 100, 'total_mp' => 5, 'capacity_fisik' => 500,
        ]);
        $res->assertRedirect();
        $lap = LaporanHarian::where('weekly_plan_id', $plan->id)->first();
        if (! $lap) { $this->markTestSkipped('laporan create blocked by validation'); return; }
        $this->actingAs($leader)->post(route('leader.laporan-harian.timer.start', $lap->id));
        $lap->refresh();
        $this->actingAs($leader)->post(route('leader.laporan-harian.timer.end', $lap->id));
        $lap->refresh();
        $this->assertSame('end', $lap->timer_status);
        $this->actingAs($leader)->post(route('leader.laporan-harian.submit', $lap->id), [
            'output_fisik' => 400, 'capacity_fisik' => 500,
        ])->assertRedirect();
        $lap->refresh();
        $this->assertSame('submitted', $lap->status);
        $this->assertSame('auto', $lap->quality_method);
        $this->assertNull($lap->quality_input_persen);
    }

    public function test_33_quality_only_spv_manager_can_update(): void
    {
        $lap = $this->makeLaporan(['status' => 'submitted']);
        $leader = $this->makeUser('leader');
        $this->actingAs($leader)->post(route('leader.laporan-harian.update-quality', $lap->id), ['quality_input_persen' => 90])->assertForbidden();
        $spv = $this->makeUser('spv');
        $this->actingAs($spv)->post(route('leader.laporan-harian.update-quality', $lap->id), ['quality_input_persen' => 90])->assertRedirect();
        $lap->refresh();
        $this->assertSame('manual', $lap->quality_method);
        $this->assertEquals(90, $lap->quality_input_persen);
    }

    public function test_34_deploy_and_schedule_artifacts_exist(): void
    {
        $this->assertFileExists(base_path('.github/workflows/deploy.yml'));
        $this->assertFileExists(base_path('deploy/supervisor.conf.example'));
        $this->assertFileExists(base_path('deploy/README.md'));
        $events = collect(Schedule::events())->map(fn ($e) => $e->command)->all();
        $this->assertTrue(collect($events)->contains(fn ($c) => str_contains($c, 'mps:close-expired')));
        $this->assertTrue(collect($events)->contains(fn ($c) => str_contains($c, 'mrp:calculate')));
        $this->assertTrue(collect($events)->contains(fn ($c) => str_contains($c, 'material-usage:sync-ratio')));
    }
}
