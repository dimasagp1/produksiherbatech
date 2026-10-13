<?php

namespace Tests\Feature;

use App\Models\AlasanDowntime;
use App\Models\DowntimeDetail;
use App\Models\LaporanHarian;
use App\Models\Line;
use App\Models\Mesin;
use App\Models\Produk;
use App\Models\User;
use App\Models\WeeklyPlan;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DowntimeRelasiTest extends TestCase
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

    public function test_resume_with_alasan_downtime_id_persists_foreign_key(): void
    {
        $leader = $this->makeUser('leader');
        $produk = Produk::firstOrFail();
        $mesin = Mesin::firstOrFail();
        $line = Line::firstOrFail();
        $alasan = AlasanDowntime::firstOrCreate(
            ['nama_alasan' => 'Trouble Mesin Sealer'],
            ['tipe_input' => 'manual', 'status_aktif' => true]
        );

        $plan = WeeklyPlan::create([
            'produk_id' => $produk->id,
            'proses' => 'mixing',
            'batch_number' => 'BT-DOWNTIME-01',
            'tanggal' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $leader->id,
        ]);

        $this->actingAs($leader)->post(route('leader.laporan-harian.store'), [
            'produk_id' => $produk->id,
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

        // Start timer lalu pause
        $this->actingAs($leader)->post(route('leader.laporan-harian.timer.start', $laporan->id));
        $this->actingAs($leader)->post(route('leader.laporan-harian.timer.pause', $laporan->id));

        $laporan->refresh();
        $this->assertEquals('pause', $laporan->timer_status);

        // Resume dengan alasan_downtime_id
        $response = $this->actingAs($leader)->post(route('leader.laporan-harian.timer.start', $laporan->id), [
            'alasan_downtime_id' => $alasan->id,
            'alasan_downtime' => 'Catatan: sensor panas rusak',
        ]);

        $response->assertSessionHasNoErrors();

        $downtimeDetail = DowntimeDetail::where('laporan_harian_id', $laporan->id)->latest()->first();
        $this->assertNotNull($downtimeDetail);
        $this->assertEquals($alasan->id, $downtimeDetail->alasan_downtime_id);
        $this->assertEquals('Catatan: sensor panas rusak', $downtimeDetail->keterangan);
        $this->assertEquals($alasan->nama_alasan, $downtimeDetail->alasanDowntime->nama_alasan);
    }
}
