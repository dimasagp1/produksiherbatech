<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Models\Mesin;
use App\Models\Produk;
use App\Models\User;
use App\Models\WeeklyPlan;
use App\Models\WorkCenter;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkCenterLineValidationTest extends TestCase
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

    public function test_cannot_assign_incompatible_line_to_work_center_in_weekly_plan(): void
    {
        $ppic = $this->makeUser('ppic');
        $produk = Produk::firstOrFail();

        // Buat Work Center tipe mixing
        $wcMixing = WorkCenter::firstOrCreate(
            ['type' => 'mixing'],
            ['code' => 'WC-MIX-01', 'name' => 'Mixing Center', 'fit_mp' => 4, 'standard_ct_seconds' => 5, 'is_active' => true]
        );

        // Buat Line yang TIDAK bisa mixing (hanya secondary)
        $lineSecondaryOnly = Line::create([
            'kode_line' => 'LN-SEC-ONLY',
            'nama_line' => 'Line Khusus Secondary',
            'can_run_work_centers' => ['secondary'],
            'status_aktif' => true,
        ]);

        $response = $this->actingAs($ppic)->post(route('ppic.weekly-plan.store'), [
            'produk_id' => $produk->id,
            'line_id' => $lineSecondaryOnly->id,
            'work_center_id' => $wcMixing->id,
            'proses' => 'mixing',
            'batch_number' => 'BT-INCOMPATIBLE-01',
            'tanggal' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('line_id');
        $this->assertDatabaseMissing('weekly_plans', [
            'batch_number' => 'BT-INCOMPATIBLE-01',
        ]);
    }

    public function test_cannot_submit_laporan_with_mismatched_mesin_and_work_center(): void
    {
        $leader = $this->makeUser('leader');
        $produk = Produk::firstOrFail();
        $line = Line::firstOrFail();

        $wc1 = WorkCenter::firstOrCreate(
            ['type' => 'mixing'],
            ['code' => 'WC-MIX-01', 'name' => 'WC Mixing Room', 'fit_mp' => 4, 'standard_ct_seconds' => 10, 'is_active' => true]
        );
        $wc2 = WorkCenter::firstOrCreate(
            ['type' => 'filling'],
            ['code' => 'WC-FIL-01', 'name' => 'WC Filling Line', 'fit_mp' => 6, 'standard_ct_seconds' => 3, 'is_active' => true]
        );

        $mesinWc1 = Mesin::create([
            'nama_mesin' => 'Mixer 500L',
            'ct' => 10,
            'status_aktif' => true,
            'work_center_id' => $wc1->id,
        ]);

        $plan = WeeklyPlan::create([
            'produk_id' => $produk->id,
            'proses' => 'filling',
            'batch_number' => 'BT-MISMATCH-MESIN',
            'tanggal' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $leader->id,
        ]);

        // Coba masukkan laporan dengan work_center WC2 tapi mesin WC1
        $response = $this->actingAs($leader)->post(route('leader.laporan-harian.store'), [
            'produk_id' => $produk->id,
            'weekly_plan_id' => $plan->id,
            'mesin_id' => $mesinWc1->id,
            'line_id' => $line->id,
            'work_center_id' => $wc2->id,
            'tanggal' => $plan->tanggal,
            'shift' => 'shift1',
            'target_mp' => 100,
            'total_mp' => 5,
            'capacity_fisik' => 500,
        ]);

        $response->assertSessionHasErrors('mesin_id');
        $this->assertDatabaseMissing('laporan_harians', [
            'weekly_plan_id' => $plan->id,
        ]);
    }
}
