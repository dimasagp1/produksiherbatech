<?php

namespace Tests\Feature;

use App\Models\DeliveryPlan;
use App\Models\Fleet;
use App\Models\InventoryStock;
use App\Models\Produk;
use App\Models\Setting;
use App\Models\StockOpname;
use App\Models\User;
use App\Services\OdooService;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ScmMasterSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeOdooService;
use Tests\TestCase;

class ScmPhaseCTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder)->run();
        (new SettingSeeder)->run();
        (new ScmMasterSeeder)->run();
        (new MasterDataSeeder)->run();
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function seedStock(float $qty = 100): Produk
    {
        $produk = Produk::where('kode_produk', 'DB01')->firstOrFail();

        InventoryStock::create([
            'produk_id' => $produk->id,
            'quantity' => $qty,
            'batch_number' => 'BT-OPN-1',
            'location' => 'GUDANG-UTAMA',
        ]);

        return $produk;
    }

    public function test_stock_opname_flow_initiate_count_recalculate_post_approve(): void
    {
        $warehouse = $this->makeUser('warehouse_admin');
        $manager = $this->makeUser('manager');
        $produk = $this->seedStock(100);

        // Initiate
        $this->actingAs($warehouse)->post(route('scm.stock-opname.store'), [
            'location' => 'GUDANG-UTAMA',
        ])->assertRedirect();

        $opname = StockOpname::latest('id')->firstOrFail();
        $this->assertSame('initiated', $opname->status);
        $this->assertMatchesRegularExpression('/^SO\/\d{2}\/\d{4}\/\d{4}$/', $opname->opname_number);
        $this->assertSame(1, $opname->items()->count());
        $this->assertSame(100.0, (float) $opname->items()->firstOrFail()->system_qty);

        // Input counted (95 dari 100 → selisih -5)
        $this->actingAs($warehouse)->put(route('scm.stock-opname.items', $opname->id), [
            'items' => [
                ['id' => $opname->items()->first()->id, 'counted_qty' => 95, 'discrepancy_reason' => 'Susut wajar'],
            ],
        ])->assertRedirect();

        $opname->refresh();
        $this->assertSame('counted', $opname->status);

        // Recalculate — IRA 0% (1 dari 1 tidak akurat), DVR = 5/100*100 = 5%
        $this->actingAs($warehouse)->post(route('scm.stock-opname.recalculate', $opname->id))->assertRedirect();
        $opname->refresh();
        $this->assertSame('recalculated', $opname->status);
        $this->assertSame(0.0, (float) $opname->ira_persen);
        $this->assertSame(5.0, (float) $opname->discrepancy_value_rate);

        // Post adjustment — ledger lokal sinkron ke counted qty
        $this->actingAs($warehouse)->post(route('scm.stock-opname.post-adjustment', $opname->id))->assertRedirect();
        $opname->refresh();
        $this->assertSame('posted', $opname->status);
        $this->assertSame(95.0, (float) InventoryStock::where('produk_id', $produk->id)->firstOrFail()->quantity);

        // Manager approve
        $this->actingAs($manager)->post(route('scm.stock-opname.approve', $opname->id))->assertRedirect();
        $opname->refresh();
        $this->assertSame('approved', $opname->status);
        $this->assertSame($manager->id, $opname->approved_by);
    }

    public function test_ira_target_met_when_counts_are_accurate(): void
    {
        $warehouse = $this->makeUser('warehouse_admin');
        $this->seedStock(80);

        $this->actingAs($warehouse)->post(route('scm.stock-opname.store'), ['location' => 'GUDANG-UTAMA']);
        $opname = StockOpname::latest('id')->firstOrFail();

        $this->actingAs($warehouse)->put(route('scm.stock-opname.items', $opname->id), [
            'items' => [
                ['id' => $opname->items()->first()->id, 'counted_qty' => 80, 'discrepancy_reason' => null],
            ],
        ]);
        $this->actingAs($warehouse)->post(route('scm.stock-opname.recalculate', $opname->id));

        $opname->refresh();
        $this->assertSame(100.0, (float) $opname->ira_persen);
        $this->assertGreaterThanOrEqual((float) Setting::get('ira_target', 98), (float) $opname->ira_persen);
    }

    public function test_operator_cannot_initiate_stock_opname(): void
    {
        $operator = $this->makeUser('operator');

        $this->actingAs($operator)
            ->post(route('scm.stock-opname.store'), ['location' => 'GUDANG-UTAMA'])
            ->assertForbidden();
    }

    public function test_kartu_stelling_print_download(): void
    {
        $warehouse = $this->makeUser('warehouse_admin');
        $this->seedStock(50);

        $this->actingAs($warehouse)->post(route('scm.stock-opname.store'), ['location' => 'GUDANG-UTAMA']);
        $opname = StockOpname::latest('id')->firstOrFail();

        $this->actingAs($warehouse)
            ->get(route('scm.stock-opname.print-kartu-stelling', $opname->id))
            ->assertOk()
            ->assertDownload();
    }

    public function test_delivery_status_flow_and_print_guard(): void
    {
        $warehouse = $this->makeUser('warehouse_admin');
        $produk = Produk::where('kode_produk', 'DB01')->firstOrFail();
        // Stok FG tidak cukup
        InventoryStock::create([
            'produk_id' => $produk->id,
            'quantity' => 5,
            'batch_number' => 'BT-FG-1',
            'location' => 'GUDANG-UTAMA',
        ]);

        $plan = DeliveryPlan::create([
            'delivery_number' => 'DL/10/2026/0001',
            'customer_name' => 'Toko Sehat',
            'planned_date' => now()->toDateString(),
            'status' => 'planned',
            'created_by' => $warehouse->id,
        ]);
        $plan->items()->create([
            'produk_id' => $produk->id,
            'product_name' => 'Diabalance',
            'quantity' => 100,
            'uom_name' => 'BOX',
        ]);

        // Print diblokir saat stok kurang & belum delivered
        $this->actingAs($warehouse)
            ->get(route('scm.delivery.print.surat-jalan', $plan->id))
            ->assertSessionHasErrors('error');

        // planned → in_transit boleh
        $this->actingAs($warehouse)->put(route('scm.delivery.status', $plan->id), [
            'status' => 'in_transit',
        ])->assertRedirect();
        $this->assertSame('in_transit', $plan->refresh()->status);

        // in_transit → planned tidak boleh (validasi status)
        $this->actingAs($warehouse)->put(route('scm.delivery.status', $plan->id), [
            'status' => 'planned',
        ])->assertSessionHasErrors('status');

        // delivered dengan KPI flags
        $this->actingAs($warehouse)->put(route('scm.delivery.status', $plan->id), [
            'status' => 'delivered',
            'on_time' => true,
            'in_full' => false,
            'damage_free' => true,
            'doc_accuracy' => true,
            'complaint' => false,
            'actual_delivery_date' => now()->toDateString(),
        ])->assertRedirect();

        $plan->refresh();
        $this->assertSame('delivered', $plan->status);
        $this->assertTrue($plan->on_time);
        $this->assertFalse($plan->in_full);

        // Print setelah delivered boleh
        $this->actingAs($warehouse)
            ->get(route('scm.delivery.print.surat-jalan', $plan->id))
            ->assertOk()
            ->assertDownload();
    }

    public function test_delivery_sync_from_odoo_creates_plans(): void
    {
        $ppic = $this->makeUser('ppic');

        $fake = new FakeOdooService;
        $fake->saleOrderPayload = [
            ['id' => 501, 'name' => 'S000501', 'partner_id' => [10, 'CV Melawan'], 'state' => 'sale', 'amount_total' => 1000000],
            ['id' => 502, 'name' => 'S000502', 'partner_id' => [11, 'PT Uji Coba'], 'state' => 'sale', 'amount_total' => 2000000],
        ];
        $this->app->instance(OdooService::class, $fake);

        $this->actingAs($ppic)->post(route('scm.delivery.sync-odoo'))->assertRedirect();
        $this->assertSame(2, DeliveryPlan::whereNotNull('odoo_so_id')->count());

        // Idempoten — sync ulang tidak duplikat
        $this->actingAs($ppic)->post(route('scm.delivery.sync-odoo'));
        $this->assertSame(2, DeliveryPlan::whereNotNull('odoo_so_id')->count());
    }

    public function test_fleet_crud_for_warehouse_admin(): void
    {
        $warehouse = $this->makeUser('warehouse_admin');

        $this->actingAs($warehouse)->post(route('scm.fleet.store'), [
            'kode_armada' => 'TRK-01',
            'nama_armada' => 'Truk Herba 1',
            'type' => 'truk',
            'plat_number' => 'B 1234 XYZ',
            'driver_name' => 'Pak Budi',
            'driver_phone' => '081234567890',
        ])->assertSessionHas('success');

        $fleet = Fleet::where('kode_armada', 'TRK-01')->firstOrFail();

        $this->actingAs($warehouse)->put(route('scm.fleet.update', $fleet->id), [
            'driver_name' => 'Pak Sari',
        ])->assertSessionHas('success');

        $this->assertSame('Pak Sari', $fleet->refresh()->driver_name);
    }

    public function test_delivery_index_shows_kpi_cards(): void
    {
        $warehouse = $this->makeUser('warehouse_admin');

        DeliveryPlan::create([
            'delivery_number' => 'DL/10/2026/0002',
            'customer_name' => 'A',
            'planned_date' => now()->subDay()->toDateString(),
            'status' => 'delivered',
            'on_time' => true,
            'in_full' => true,
            'damage_free' => true,
            'doc_accuracy' => true,
            'complaint' => false,
            'created_by' => $warehouse->id,
        ]);

        $this->actingAs($warehouse)
            ->get(route('scm.delivery.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SCM/Delivery/Index')
                ->where('kpi.otd', fn ($v) => abs((float) $v - 100) < 0.01)
                ->where('kpi.in_full', fn ($v) => abs((float) $v - 100) < 0.01)
            );
    }

    public function test_stock_opname_post_adjustment_calls_odoo_writeback_when_enabled(): void
    {
        $warehouse = $this->makeUser('warehouse_admin');
        Setting::set('odoo_inventory_writeback_enabled', '1', 'odoo');
        $this->seedStock(10);

        $fake = new FakeOdooService;
        $this->app->instance(OdooService::class, $fake);

        $this->actingAs($warehouse)->post(route('scm.stock-opname.store'), ['location' => 'GUDANG-UTAMA']);
        $opname = StockOpname::latest('id')->firstOrFail();
        $itemId = $opname->items()->first()->id;

        $this->actingAs($warehouse)->put(route('scm.stock-opname.items', $opname->id), [
            'items' => [['id' => $itemId, 'counted_qty' => 12, 'discrepancy_reason' => null]],
        ]);
        $this->actingAs($warehouse)->post(route('scm.stock-opname.recalculate', $opname->id));

        $response = $this->actingAs($warehouse)->post(route('scm.stock-opname.post-adjustment', $opname->id));
        $response->assertSessionHas('success');
        $this->assertStringContainsString('fake write-back ok', (string) session('success'));
        $this->assertSame(12.0, (float) InventoryStock::where('batch_number', 'BT-OPN-1')->firstOrFail()->quantity);
    }
}
