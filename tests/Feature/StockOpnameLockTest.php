<?php

namespace Tests\Feature;

use App\Models\Produk;
use App\Models\StockOpname;
use App\Models\User;
use App\Models\WeeklyPlan;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockOpnameLockTest extends TestCase
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

    public function test_material_usage_is_rejected_when_location_is_frozen_by_opname(): void
    {
        $warehouseAdmin = $this->makeUser('warehouse_admin');
        $produk = Produk::firstOrFail();

        // Aktifkan Stock Opname pada GUDANG-UTAMA
        StockOpname::create([
            'opname_number' => 'OPN-20261013-0001',
            'location' => 'GUDANG-UTAMA',
            'status' => 'initiated',
            'initiated_by' => $warehouseAdmin->id,
            'initiated_at' => now(),
        ]);

        $plan = WeeklyPlan::create([
            'produk_id' => $produk->id,
            'proses' => 'mixing',
            'batch_number' => 'BT-LOCK-01',
            'tanggal' => now()->toDateString(),
            'status' => 'aktif',
            'created_by' => $warehouseAdmin->id,
        ]);

        $response = $this->actingAs($warehouseAdmin)->post(route('scm.material-usage.store'), [
            'weekly_plan_id' => $plan->id,
            'usage_date' => now()->toDateString(),
            'items' => [
                [
                    'produk_id' => $produk->id,
                    'material_name' => $produk->nama_produk,
                    'quantity_used' => 10,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('error');
        $this->assertDatabaseMissing('material_usages', [
            'weekly_plan_id' => $plan->id,
        ]);
    }

    public function test_stock_adjustment_is_rejected_when_location_is_frozen_by_opname(): void
    {
        $warehouseAdmin = $this->makeUser('warehouse_admin');
        $produk = Produk::firstOrFail();

        StockOpname::create([
            'opname_number' => 'OPN-20261013-0002',
            'location' => 'GUDANG-UTAMA',
            'status' => 'counted',
            'initiated_by' => $warehouseAdmin->id,
            'initiated_at' => now(),
        ]);

        $response = $this->actingAs($warehouseAdmin)->post(route('scm.saldo-stok.adjust'), [
            'produk_id' => $produk->id,
            'location' => 'GUDANG-UTAMA',
            'quantity' => 50,
        ]);

        $response->assertSessionHasErrors('location');
    }
}
