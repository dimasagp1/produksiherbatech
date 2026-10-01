<?php

namespace Tests\Feature;

use App\Models\LaporanHarian;
use App\Models\Line;
use App\Models\Mesin;
use App\Models\Produk;
use App\Models\Setting;
use App\Models\User;
use App\Models\WeeklyPlan;
use App\Services\OdooService;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\Support\FakeOdooService;
use Tests\TestCase;

class PrdVPhaseATest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder)->run();
        (new SettingSeeder)->run();
        (new MasterDataSeeder)->run();
    }

    private function makeUser(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    private function makeWeeklyPlan(array $overrides = []): WeeklyPlan
    {
        $produk = Produk::where('kode_produk', 'DB01')->firstOrFail();
        $user = User::factory()->create();

        return WeeklyPlan::create(array_merge([
            'produk_id' => $produk->id,
            'proses' => 'mixing',
            'batch_number' => 'BT-TEST-'.uniqid(),
            'tanggal' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $user->id,
        ], $overrides));
    }

    // ===== A0: Roles + Settings =====

    public function test_roles_operator_and_warehouse_admin_are_seeded(): void
    {
        $this->assertSame(
            ['superadmin', 'admin', 'manager', 'spv', 'ppic', 'leader', 'operator', 'warehouse_admin'],
            Role::orderBy('id')->pluck('name')->all()
        );
    }

    public function test_setting_target_output_multiplier_defaults_to_2000(): void
    {
        $this->assertSame('2000', Setting::get('target_output_multiplier'));
        $this->assertSame(2000, (int) Setting::get('target_output_multiplier', 2000));
    }

    // ===== A1: Downtime / Shift / RBAC =====

    public function test_pause_resume_does_not_cap_downtime_at_480(): void
    {
        $leader = $this->makeUser('leader');
        $plan = $this->makeWeeklyPlan();
        $mesin = Mesin::firstOrFail();
        $line = Line::firstOrFail();

        $this->actingAs($leader)->post(route('leader.laporan-harian.store'), [
            'produk_id' => $plan->produk_id,
            'weekly_plan_id' => $plan->id,
            'mesin_id' => $mesin->id,
            'line_id' => $line->id,
            'tanggal' => $plan->tanggal,
            'shift' => 'shift1',
            'target_mp' => 100,
            'total_mp' => 5,
            'capacity_fisik' => 500,
        ])->assertRedirect();

        $laporan = LaporanHarian::where('weekly_plan_id', $plan->id)->firstOrFail();
        $this->assertSame('shift1', $laporan->shift);

        $this->actingAs($leader)->post(route('leader.laporan-harian.timer.start', $laporan->id));
        $laporan->refresh();
        $this->assertSame('start', $laporan->timer_status);

        $this->actingAs($leader)->post(route('leader.laporan-harian.timer.pause', $laporan->id));
        $laporan->refresh();
        $this->assertSame('pause', $laporan->timer_status);

        // Simulasikan pause panjang (600 menit > cap lama 480)
        DB::table('laporan_harians')->where('id', $laporan->id)->update([
            'pause_started_at' => now()->subMinutes(600),
        ]);

        $this->actingAs($leader)->post(route('leader.laporan-harian.timer.start', $laporan->id), [
            'alasan_downtime' => 'Mesin rusak berat',
        ]);

        $laporan->refresh();
        $this->assertSame('start', $laporan->timer_status);
        $this->assertGreaterThanOrEqual(600, $laporan->total_pause_menit, 'Downtime harus real elapsed, bukan capped 480');
        $this->assertNull($laporan->pause_started_at);
        $this->assertDatabaseHas('downtime_details', [
            'laporan_harian_id' => $laporan->id,
            'keterangan' => 'Mesin rusak berat',
        ]);
    }

    public function test_resume_without_alasan_downtime_is_rejected(): void
    {
        $leader = $this->makeUser('leader');
        $plan = $this->makeWeeklyPlan();
        $mesin = Mesin::firstOrFail();
        $line = Line::firstOrFail();

        $this->actingAs($leader)->post(route('leader.laporan-harian.store'), [
            'produk_id' => $plan->produk_id,
            'weekly_plan_id' => $plan->id,
            'mesin_id' => $mesin->id,
            'line_id' => $line->id,
            'tanggal' => $plan->tanggal,
            'shift' => 'shift1',
            'target_mp' => 100,
            'total_mp' => 5,
            'capacity_fisik' => 500,
        ]);

        $laporan = LaporanHarian::where('weekly_plan_id', $plan->id)->firstOrFail();
        $this->actingAs($leader)->post(route('leader.laporan-harian.timer.start', $laporan->id));
        $this->actingAs($leader)->post(route('leader.laporan-harian.timer.pause', $laporan->id));

        $this->actingAs($leader)
            ->from(route('leader.laporan-harian.show', $laporan->id))
            ->post(route('leader.laporan-harian.timer.start', $laporan->id), [])
            ->assertSessionHasErrors('alasan_downtime');
    }

    public function test_laporan_index_filters_by_shift(): void
    {
        $leader = $this->makeUser('leader');
        $plan1 = $this->makeWeeklyPlan();
        $plan2 = $this->makeWeeklyPlan(['proses' => 'mixing', 'batch_number' => 'BT-S2-'.uniqid()]);
        $mesin = Mesin::firstOrFail();
        $line = Line::firstOrFail();

        $this->actingAs($leader)->post(route('leader.laporan-harian.store'), [
            'produk_id' => $plan1->produk_id,
            'weekly_plan_id' => $plan1->id,
            'mesin_id' => $mesin->id,
            'line_id' => $line->id,
            'tanggal' => $plan1->tanggal,
            'shift' => 'shift1',
            'target_mp' => 100,
            'total_mp' => 5,
            'capacity_fisik' => 500,
        ]);
        $this->actingAs($leader)->post(route('leader.laporan-harian.store'), [
            'produk_id' => $plan2->produk_id,
            'weekly_plan_id' => $plan2->id,
            'mesin_id' => $mesin->id,
            'line_id' => $line->id,
            'tanggal' => $plan2->tanggal,
            'shift' => 'shift2',
            'target_mp' => 100,
            'total_mp' => 5,
            'capacity_fisik' => 500,
        ]);

        $this->actingAs($leader)
            ->get(route('leader.laporan-harian.index', ['shift' => 'shift2']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Leader/LaporanHarian/Index')
                ->where('shift', 'shift2')
                ->has('laporans.data', 1)
                ->where('laporans.data.0.batch_number', $plan2->batch_number)
            );
    }

    public function test_operator_can_access_timer_and_laporan_routes(): void
    {
        $operator = $this->makeUser('operator');
        $plan = $this->makeWeeklyPlan();
        $mesin = Mesin::firstOrFail();
        $line = Line::firstOrFail();

        $this->actingAs($operator)->get(route('leader.laporan-harian.index'))->assertOk();
        $this->actingAs($operator)->get(route('leader.laporan-harian.create'))->assertOk();

        $this->actingAs($operator)->post(route('leader.laporan-harian.store'), [
            'produk_id' => $plan->produk_id,
            'weekly_plan_id' => $plan->id,
            'mesin_id' => $mesin->id,
            'line_id' => $line->id,
            'tanggal' => $plan->tanggal,
            'shift' => 'shift2',
            'target_mp' => 100,
            'total_mp' => 5,
            'capacity_fisik' => 500,
        ])->assertRedirect();

        $laporan = LaporanHarian::where('weekly_plan_id', $plan->id)->firstOrFail();
        $this->actingAs($operator)->post(route('leader.laporan-harian.timer.start', $laporan->id))->assertRedirect();
        $laporan->refresh();
        $this->assertSame('start', $laporan->timer_status);
        $this->assertSame('shift2', $laporan->shift);
    }

    public function test_operator_cannot_access_mo_sync_route(): void
    {
        $operator = $this->makeUser('operator');

        $this->actingAs($operator)
            ->post(route('ppic.odoo.mo-sync'))
            ->assertForbidden();
    }

    // ===== A2: WPS + Odoo MO Sync =====

    public function test_weekly_plan_activate_computes_target_output_from_multiplier(): void
    {
        Setting::set('target_output_multiplier', 1500, 'production');

        $ppic = $this->makeUser('ppic');
        $plan = $this->makeWeeklyPlan(['mp_count' => 3]);

        $this->actingAs($ppic)->post(route('ppic.weekly-plan.activate', $plan->id));

        $plan->refresh();
        $this->assertSame('aktif', $plan->status);
        $this->assertSame(1500, $plan->multiplier);
        $this->assertSame(4500, $plan->target_output);
    }

    public function test_weekly_plan_aktif_human_edit_is_limited(): void
    {
        $ppic = $this->makeUser('ppic');
        $plan = $this->makeWeeklyPlan([
            'status' => 'aktif',
            'mp_count' => 2,
            'target_output' => 4000,
            'mo_status' => 'confirmed',
        ]);
        $originalProdukId = $plan->produk_id;
        $originalBatch = $plan->batch_number;

        // Update field terbatas (mp_count) — boleh
        $this->actingAs($ppic)->put(route('ppic.weekly-plan.update', $plan->id), [
            'mp_count' => 4,
        ])->assertRedirect();

        $plan->refresh();
        $this->assertSame(4, $plan->mp_count);
        $this->assertSame(8000, $plan->target_output, 'target_output recompute mp_count × multiplier');
        $this->assertSame($originalProdukId, $plan->produk_id);
        $this->assertSame($originalBatch, $plan->batch_number);

        // Update field terlarang (produk/batch) — diabaikan, tidak berubah
        $otherProduk = Produk::where('kode_produk', 'VB01')->firstOrFail();
        $this->actingAs($ppic)->put(route('ppic.weekly-plan.update', $plan->id), [
            'produk_id' => $otherProduk->id,
            'batch_number' => 'HACK-999',
        ]);

        $plan->refresh();
        $this->assertSame($originalProdukId, $plan->produk_id);
        $this->assertSame($originalBatch, $plan->batch_number);
        $this->assertSame(4, $plan->mp_count);
    }

    public function test_weekly_plan_store_requires_previous_process(): void
    {
        $ppic = $this->makeUser('ppic');

        $this->actingAs($ppic)->post(route('ppic.weekly-plan.store'), [
            'produk_id' => Produk::where('kode_produk', 'DB01')->firstOrFail()->id,
            'proses' => 'filling',
            'batch_number' => 'BT-URUT-'.uniqid(),
            'tanggal' => now()->toDateString(),
        ])->assertSessionHasErrors('proses');
    }

    public function test_odoo_mo_sync_creates_updates_and_cancels_plans(): void
    {
        $ppic = $this->makeUser('ppic');

        $diabalance = Produk::where('kode_produk', 'DB01')->firstOrFail();
        $diabalance->update(['odoo_id' => 101]);
        $vitablend = Produk::where('kode_produk', 'VB01')->firstOrFail();
        $vitablend->update(['odoo_id' => 102]);

        // Plan existing yang akan di-cancel
        $cancelledPlan = $this->makeWeeklyPlan([
            'odoo_mo_id' => 13,
            'mo_status' => 'confirmed',
            'batch_number' => 'BT-003',
            'tanggal' => '2026-10-05',
        ]);

        // Plan existing yang akan di-update (in_progress)
        $existingPlan = $this->makeWeeklyPlan([
            'odoo_mo_id' => 12,
            'mo_status' => 'confirmed',
            'produk_id' => $vitablend->id,
            'batch_number' => 'BT-002',
            'tanggal' => '2026-10-05',
        ]);

        $fake = new FakeOdooService;
        $fake->moPayload = [
            [
                'id' => 11,
                'name' => 'MO/0001',
                'origin' => 'Batch: BT-001',
                'state' => 'confirmed',
                'product_id' => [101, 'Diabalance'],
                'product_qty' => 5000,
                'bom_id' => false,
                'date_start' => '2026-10-05 08:00:00',
                'date_finished' => null,
            ],
            [
                'id' => 12,
                'name' => 'MO/0002',
                'origin' => 'Batch: BT-002',
                'state' => 'progress',
                'product_id' => [102, 'Vitablend'],
                'product_qty' => 3000,
                'bom_id' => false,
                'date_start' => '2026-10-05 08:00:00',
                'date_finished' => null,
            ],
            [
                'id' => 13,
                'name' => 'MO/0003',
                'origin' => 'Batch: BT-003',
                'state' => 'cancel',
                'product_id' => [101, 'Diabalance'],
                'product_qty' => 1000,
                'bom_id' => false,
                'date_start' => '2026-10-05 08:00:00',
                'date_finished' => null,
            ],
        ];

        $this->app->instance(OdooService::class, $fake);

        $this->actingAs($ppic)
            ->post(route('ppic.odoo.mo-sync'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $newPlan = WeeklyPlan::where('odoo_mo_id', 11)->firstOrFail();
        $this->assertSame('draft', $newPlan->status);
        $this->assertSame('confirmed', $newPlan->mo_status);
        $this->assertSame('BT-001', $newPlan->batch_number);
        $this->assertSame(5000, $newPlan->target_output);
        $this->assertSame($diabalance->id, $newPlan->produk_id);

        $existingPlan->refresh();
        $this->assertSame('in_progress', $existingPlan->mo_status);
        $this->assertSame(3000, $existingPlan->target_output);

        $this->assertSoftDeleted('weekly_plans', ['id' => $cancelledPlan->id]);
        $cancelledPlanRefreshed = WeeklyPlan::withTrashed()->findOrFail($cancelledPlan->id);
        $this->assertSame('cancelled', $cancelledPlanRefreshed->mo_status);
    }

    public function test_mo_sync_disabled_by_setting_blocks_sync(): void
    {
        $ppic = $this->makeUser('ppic');
        Setting::set('odoo_mo_sync_enabled', '0', 'odoo');

        $this->app->instance(OdooService::class, new FakeOdooService);

        $this->actingAs($ppic)
            ->post(route('ppic.odoo.mo-sync'))
            ->assertSessionHas('error');
    }

    public function test_mo_list_route_returns_json_for_ppic(): void
    {
        $ppic = $this->makeUser('ppic');

        $fake = new FakeOdooService;
        $fake->moPayload = [['id' => 1, 'name' => 'MO/1', 'state' => 'confirmed']];
        $this->app->instance(OdooService::class, $fake);

        $this->actingAs($ppic)
            ->get(route('ppic.odoo.mo-list'))
            ->assertOk()
            ->assertJson(['success' => true]);
    }
}
