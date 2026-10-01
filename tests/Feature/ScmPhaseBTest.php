<?php

namespace Tests\Feature;

use App\Models\Bom;
use App\Models\InventoryStock;
use App\Models\MaterialScrap;
use App\Models\MaterialUsage;
use App\Models\Produk;
use App\Models\ScmUom;
use App\Models\User;
use App\Models\WeeklyPlan;
use App\Services\OdooService;
use App\Services\ScmNumberService;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ScmMasterSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeOdooService;
use Tests\TestCase;

class ScmPhaseBTest extends TestCase
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

    private function makeBom(Produk $produk, array $items = []): Bom
    {
        $bom = Bom::create([
            'produk_id' => $produk->id,
            'version' => '1',
            'is_active' => true,
            'created_by' => User::factory()->create()->id,
        ]);

        foreach ($items ?: [['material_name' => 'Botol', 'quantity' => 100, 'material_produk_id' => null]] as $item) {
            $bom->items()->create($item);
        }

        return $bom;
    }

    public function test_bom_store_creates_active_bom_and_deactivates_previous(): void
    {
        $ppic = $this->makeUser('ppic');
        $produk = Produk::where('kode_produk', 'DB01')->firstOrFail();

        $this->makeBom($produk);

        $this->actingAs($ppic)->post(route('scm.bom.store'), [
            'produk_id' => $produk->id,
            'version' => '2',
            'items' => [
                ['material_name' => 'Label', 'quantity' => 50, 'material_produk_id' => null, 'uom_id' => ScmUom::where('code', 'PCS')->value('id')],
            ],
        ])->assertRedirect(route('scm.bom.index'));

        $this->assertSame(1, Bom::where('produk_id', $produk->id)->where('is_active', true)->count());
        $this->assertSame('2', Bom::where('produk_id', $produk->id)->where('is_active', true)->value('version'));
    }

    public function test_scms_number_service_formats_mu_sequence(): void
    {
        $svc = app(ScmNumberService::class);
        $first = $svc->materialUsage();
        $this->assertMatchesRegularExpression('/^MU\/\d{2}\/\d{4}\/\d{4}$/', $first);

        MaterialUsage::create([
            'usage_number' => $first,
            'user_id' => User::factory()->create()->id,
            'usage_date' => now()->toDateString(),
        ]);

        $second = $svc->materialUsage();
        $this->assertNotSame($first, $second);
        $this->assertGreaterThan($first, $second);
    }

    public function test_material_usage_ratio_formula_and_stock_ledger_decrement(): void
    {
        $operator = $this->makeUser('operator');
        $produk = Produk::where('kode_produk', 'DB01')->firstOrFail();
        $material = Produk::create([
            'kode_produk' => 'RM-TEST',
            'nama_produk' => 'Bahan Baku Test',
            'proses_default' => 'mixing',
            'item_type' => 'rm',
            'status_aktif' => true,
        ]);
        $this->makeBom($produk, [
            ['material_name' => 'Bahan Baku Test', 'quantity' => 100, 'material_produk_id' => $material->id],
        ]);

        $plan = WeeklyPlan::create([
            'produk_id' => $produk->id,
            'proses' => 'mixing',
            'batch_number' => 'BT-MU-001',
            'tanggal' => now()->toDateString(),
            'status' => 'aktif',
            'created_by' => User::factory()->create()->id,
        ]);

        InventoryStock::create([
            'produk_id' => $material->id,
            'quantity' => 500,
            'batch_number' => 'BT-MU-001',
            'location' => 'GUDANG-UTAMA',
        ]);

        $this->actingAs($operator)->post(route('scm.material-usage.store'), [
            'weekly_plan_id' => $plan->id,
            'usage_date' => now()->toDateString(),
            'shift' => 'shift1',
            'items' => [
                [
                    'produk_id' => $material->id,
                    'material_name' => 'Bahan Baku Test',
                    'quantity_used' => 101,
                    'quantity_standard' => 100,
                    'uom_id' => null,
                ],
            ],
        ])->assertRedirect();

        $usage = MaterialUsage::latest('id')->firstOrFail();
        $this->assertMatchesRegularExpression('/^MU\//', $usage->usage_number);

        $item = $usage->items()->firstOrFail();
        $this->assertSame(100.0, (float) $item->quantity_standard);
        $this->assertSame(101.0, (float) $item->quantity_used);
        $this->assertSame(1.0, (float) $item->variance);

        $stock = InventoryStock::where('produk_id', $material->id)->firstOrFail();
        $this->assertSame(399.0, (float) $stock->quantity);
    }

    public function test_scrap_manual_and_odoo_pull_use_source_flag(): void
    {
        $operator = $this->makeUser('operator');
        $produk = Produk::where('kode_produk', 'DB01')->firstOrFail();
        $material = Produk::create([
            'kode_produk' => 'PM-LABEL',
            'nama_produk' => 'Label Test',
            'proses_default' => 'packing',
            'item_type' => 'pm',
            'status_aktif' => true,
        ]);

        InventoryStock::create([
            'produk_id' => $material->id,
            'quantity' => 100,
            'batch_number' => 'BT-SCRAP-1',
            'location' => 'GUDANG-UTAMA',
        ]);

        $this->actingAs($operator)->post(route('scm.scrap-material.store'), [
            'batch_number' => 'BT-SCRAP-1',
            'produk_id' => $material->id,
            'material_name' => 'Label Test',
            'quantity' => 5,
            'uom_id' => null,
            'defect_reason' => 'Label rusak',
        ])->assertRedirect(route('scm.scrap-material.index'));

        $manual = MaterialScrap::where('source', 'manual')->firstOrFail();
        $this->assertSame(5.0, (float) $manual->quantity);
        $this->assertSame(95.0, (float) InventoryStock::where('produk_id', $material->id)->firstOrFail()->quantity);

        $ppic = $this->makeUser('ppic');
        $fake = new FakeOdooService;
        $fake->scrapPayload = [
            [
                'odoo_scrap_id' => 777,
                'name' => 'SP/777',
                'material_name' => 'Botol Odoo',
                'quantity' => 12,
                'uom' => 'PCS',
                'batch_number' => 'BT-SCRAP-1',
                'origin' => 'Batch: BT-SCRAP-1',
                'date' => now()->toDateTimeString(),
            ],
        ];
        $this->app->instance(OdooService::class, $fake);

        $this->actingAs($ppic)
            ->post(route('scm.scrap-material.pull-odoo'))
            ->assertRedirect();

        $odoo = MaterialScrap::where('source', 'odoo')->where('odoo_scrap_id', 777)->firstOrFail();
        $this->assertSame('Botol Odoo', $odoo->material_name);
        $this->assertSame(12.0, (float) $odoo->quantity);
    }

    public function test_material_usage_show_computes_overall_ratio(): void
    {
        $manager = $this->makeUser('manager');
        $produk = Produk::where('kode_produk', 'DB01')->firstOrFail();
        $user = User::factory()->create();
        $usage = MaterialUsage::create([
            'usage_number' => 'MU/10/2026/0001',
            'user_id' => $user->id,
            'usage_date' => now()->toDateString(),
        ]);
        $usage->items()->create([
            'material_name' => 'X',
            'quantity_standard' => 200,
            'quantity_used' => 201,
            'variance' => 1,
        ]);

        $this->actingAs($manager)
            ->get(route('scm.material-usage.show', $usage->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SCM/Inventory/MaterialUsage/Show')
                ->where('summary.overall_ratio', 0.5)
            );
    }

    public function test_export_material_loss_pdf_route(): void
    {
        $manager = $this->makeUser('manager');

        $this->actingAs($manager)
            ->get(route('scm.material-usage.export', ['pdf', 'date_from' => now()->toDateString(), 'date_to' => now()->toDateString()]))
            ->assertOk()
            ->assertDownload();
    }

    public function test_operator_cannot_adjust_stock_but_can_view_saldo(): void
    {
        $operator = $this->makeUser('operator');
        $produk = Produk::where('kode_produk', 'DB01')->firstOrFail();

        $this->actingAs($operator)->get(route('scm.saldo-stok.index'))->assertForbidden();

        $wh = $this->makeUser('warehouse_admin');
        $this->actingAs($wh)->post(route('scm.saldo-stok.adjust'), [
            'produk_id' => $produk->id,
            'batch_number' => 'BT-ADJ',
            'location' => 'GUDANG-UTAMA',
            'quantity' => 25,
        ])->assertRedirect(route('scm.saldo-stok.index'));

        $this->assertSame(25.0, (float) InventoryStock::where('produk_id', $produk->id)->where('batch_number', 'BT-ADJ')->firstOrFail()->quantity);
    }

    public function test_saldo_stok_loss_widget_tampil(): void
    {
        $wh = $this->makeUser('warehouse_admin');

        $this->actingAs($wh)
            ->get(route('scm.saldo-stok.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SCM/Inventory/SaldoStok/Index')
                ->has('lossWidget')
                ->where('lossWidget.revenue', 0)
            );
    }

    public function test_sync_bom_from_odoo(): void
    {
        $this->app->instance(OdooService::class, new FakeOdooService);
        $ppic = $this->makeUser('ppic');

        $this->actingAs($ppic)
            ->post(route('scm.bom.sync-odoo'))
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_sync_saldo_stok_from_odoo(): void
    {
        $this->app->instance(OdooService::class, new FakeOdooService);
        $wh = $this->makeUser('warehouse_admin');

        $this->actingAs($wh)
            ->post(route('scm.saldo-stok.sync-odoo'))
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_sync_material_usage_from_odoo(): void
    {
        $this->app->instance(OdooService::class, new FakeOdooService);
        $wh = $this->makeUser('warehouse_admin');

        $this->actingAs($wh)
            ->post(route('scm.material-usage.sync-odoo'))
            ->assertRedirect()
            ->assertSessionHas('success');
    }
}
