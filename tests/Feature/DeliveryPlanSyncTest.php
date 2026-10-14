<?php

namespace Tests\Feature;

use App\Models\DeliveryPlan;
use App\Models\Produk;
use App\Models\User;
use App\Services\OdooService;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeOdooService;
use Tests\TestCase;

class DeliveryPlanSyncTest extends TestCase
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

    public function test_sync_sale_orders_from_odoo_populates_delivery_items(): void
    {
        $admin = $this->makeUser('warehouse_admin');
        $produk = Produk::firstOrFail();
        $produk->update(['odoo_id' => 379]);

        $this->fakeOdoo->saleOrderPayload = [
            [
                'id' => 991,
                'name' => 'SO0991',
                'partner_id' => [42, 'PT Distribusi Herbal Sehat'],
                'date_order' => '2026-10-15',
                'amount_total' => 15000000,
                'state' => 'sale',
                'lines' => [
                    [
                        'id' => 8801,
                        'product_id' => [$produk->odoo_id, $produk->nama_produk],
                        'name' => $produk->nama_produk,
                        'product_uom_qty' => 350.0,
                        'product_uom' => [1, 'Pcs'],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($admin)->post(route('scm.delivery.sync-odoo'));
        $response->assertSessionHasNoErrors();
        $response->assertSessionMissing('error');
        $response->assertSessionHas('success');

        $plan = DeliveryPlan::where('odoo_so_id', 991)->first();
        $this->assertNotNull($plan);
        $this->assertEquals('PT Distribusi Herbal Sehat', $plan->customer_name);

        // Verifikasi delivery items terbuat dan terhubung ke produk lokal
        $this->assertDatabaseHas('delivery_items', [
            'delivery_plan_id' => $plan->id,
            'produk_id' => $produk->id,
            'product_name' => $produk->nama_produk,
            'quantity' => 350.0,
        ]);

        $this->assertCount(1, $plan->items);
    }

    public function test_sync_sale_orders_matches_product_by_name_fallback_when_odoo_id_differs(): void
    {
        $admin = $this->makeUser('warehouse_admin');
        $produk = Produk::firstOrFail();
        $produk->update(['odoo_id' => 100]);

        $this->fakeOdoo->saleOrderPayload = [
            [
                'id' => 992,
                'name' => 'SO0992',
                'partner_id' => [43, 'PT Apotek Sehat Prima'],
                'date_order' => '2026-10-16',
                'amount_total' => 5000000,
                'state' => 'sale',
                'lines' => [
                    [
                        'id' => 8802,
                        // Odoo ID berbeda (misal 999), tapi nama produk cocok persis
                        'product_id' => [999, $produk->nama_produk],
                        'name' => $produk->nama_produk,
                        'product_uom_qty' => 120.0,
                        'product_uom' => [1, 'Pcs'],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($admin)->post(route('scm.delivery.sync-odoo'));
        $response->assertSessionHasNoErrors();
        $response->assertSessionMissing('error');

        $plan = DeliveryPlan::where('odoo_so_id', 992)->first();
        $this->assertNotNull($plan);

        // Harus tetap terhubung ke $produk->id lewat fallback nama_produk
        $this->assertDatabaseHas('delivery_items', [
            'delivery_plan_id' => $plan->id,
            'produk_id' => $produk->id,
            'product_name' => $produk->nama_produk,
            'quantity' => 120.0,
        ]);
    }
}
