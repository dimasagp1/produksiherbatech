<?php

namespace Tests\Feature;

use App\Jobs\Odoo\PushBatchToOdooJob;
use App\Models\LaporanHarian;
use App\Models\Line;
use App\Models\Mesin;
use App\Models\Produk;
use App\Models\User;
use App\Models\WeeklyPlan;
use App\Services\OdooService;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeOdooService;
use Tests\TestCase;

class PushToOdooJobTest extends TestCase
{
    use RefreshDatabase;

    private FakeOdooService $fakeOdoo;

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder)->run();
        (new SettingSeeder)->run();
        (new MasterDataSeeder)->run();

        $this->fakeOdoo = new FakeOdooService;
        $this->app->instance(OdooService::class, $this->fakeOdoo);
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_submit_laporan_enqueues_odoo_outbox_job(): void
    {
        Queue::fake();

        $leader = $this->makeUser('leader');
        $produk = Produk::firstOrFail();
        $mesin = Mesin::firstOrFail();
        $line = Line::firstOrFail();

        $plan = WeeklyPlan::create([
            'produk_id' => $produk->id,
            'proses' => 'mixing',
            'batch_number' => 'BT-OUTBOX-01',
            'odoo_mo_id' => 7712,
            'tanggal' => now()->toDateString(),
            'status' => 'aktif',
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
            'timer_status' => 'end',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'gross_time_menit' => 480,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($leader)->post(route('leader.laporan-harian.submit', $laporan->id), [
            'output_fisik' => 450,
            'capacity_fisik' => 500,
        ]);

        $response->assertSessionHasNoErrors();

        // Verifikasi outbox sync job tercatat di DB
        $this->assertDatabaseHas('odoo_sync_jobs', [
            'entity_type' => 'laporan_harian',
            'entity_id' => $laporan->id,
            'operation' => 'batch_finish',
        ]);

        Queue::assertPushed(PushBatchToOdooJob::class);
    }

    public function test_store_reject_enqueues_reject_outbox_job(): void
    {
        Queue::fake();

        $leader = $this->makeUser('leader');
        $produk = Produk::firstOrFail();
        $mesin = Mesin::firstOrFail();
        $line = Line::firstOrFail();

        $plan = WeeklyPlan::create([
            'produk_id' => $produk->id,
            'proses' => 'mixing',
            'batch_number' => 'BT-REJECT-OUTBOX-01',
            'odoo_mo_id' => 7713,
            'tanggal' => now()->toDateString(),
            'status' => 'aktif',
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
            'output_fisik' => 400,
            'timer_status' => 'end',
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($leader)->post(route('reject.store'), [
            'laporan_harian_id' => $laporan->id,
            'alasan_reject' => 'Bocor segel plastik botol',
            'qty_reject' => 5,
            'jenis_reject' => 'process',
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('odoo_sync_jobs', [
            'entity_type' => 'reject_detail',
            'operation' => 'push_reject',
        ]);
    }
}
