<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Models\MaterialSupply;
use App\Models\MaterialSupplyItem;
use App\Models\Produk;
use App\Models\User;
use App\Models\WeeklyPlan;
use App\Models\WorkCenter;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkCenterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SupplyTest extends TestCase
{
    use RefreshDatabase;

    protected User $warehouse;

    protected User $ppic;

    protected User $manager;

    protected WorkCenter $wc;

    protected Line $line;

    protected Produk $produk;

    protected WeeklyPlan $wp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(WorkCenterSeeder::class);
        $this->warehouse = User::factory()->create()->assignRole('warehouse_admin');
        $this->ppic = User::factory()->create()->assignRole('ppic');
        $this->manager = User::factory()->create()->assignRole('manager');
        $this->wc = WorkCenter::where('type', 'mixing')->first();
        $this->line = Line::factory()->create(['default_work_center_id' => $this->wc->id, 'can_run_work_centers' => ['mixing']]);
        $this->produk = Produk::factory()->create(['nama_produk' => 'FG Supply', 'kode_produk' => 'FG-SUP', 'item_type' => 'fg']);
        $rm = Produk::factory()->create(['nama_produk' => 'RM Supply', 'kode_produk' => 'RM-SUP', 'item_type' => 'rm']);
        $this->wp = WeeklyPlan::create([
            'produk_id' => $this->produk->id,
            'work_center_id' => $this->wc->id,
            'line_id' => $this->line->id,
            'proses' => 'filling',
            'shift' => 'shift1',
            'batch_number' => 'SUP-TEST-'.uniqid(),
            'tanggal' => Carbon::tomorrow()->toDateString(),
            'status' => 'aktif',
            'target_output' => 1000,
            'created_by' => $this->warehouse->id,
        ]);
    }

    public function test_warehouse_can_view_supply_index(): void
    {
        $this->actingAs($this->warehouse)->get(route('scm.supply.index'))->assertOk();
    }

    public function test_ppic_can_view_supply_index(): void
    {
        $this->actingAs($this->ppic)->get(route('scm.supply.index'))->assertOk();
    }

    public function test_operator_cannot_view_supply(): void
    {
        $op = User::factory()->create()->assignRole('operator');
        $this->actingAs($op)->get(route('scm.supply.index'))->assertForbidden();
    }

    public function test_warehouse_can_create_supply(): void
    {
        $rm = Produk::where('kode_produk', 'RM-SUP')->first();
        $response = $this->actingAs($this->warehouse)->post(route('scm.supply.store'), [
            'weekly_plan_id' => $this->wp->id,
            'required_date' => $this->wp->tanggal,
            'notes' => 'H-1 test',
            'items' => [['produk_id' => $rm->id, 'required_qty' => 50, 'uom_id' => null]],
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('material_supplies', ['weekly_plan_id' => $this->wp->id, 'status' => 'pending']);
        $supply = MaterialSupply::first();
        $this->assertMatchesRegularExpression('/^SUP-\d{8}-\d{4}$/', $supply->supply_number);
        $this->assertEquals(Carbon::parse($supply->required_date)->subDay()->toDateString(), $supply->hMinus1()->toDateString());
    }

    public function test_supply_creation_requires_items(): void
    {
        $response = $this->actingAs($this->warehouse)->post(route('scm.supply.store'), [
            'required_date' => Carbon::tomorrow()->toDateString(),
            'items' => [],
        ]);
        $response->assertSessionHasErrors('items');
    }

    public function test_mark_delivered_full_becomes_delivered(): void
    {
        $rm = Produk::where('kode_produk', 'RM-SUP')->first();
        $supply = MaterialSupply::create([
            'supply_number' => MaterialSupply::nextNumber(),
            'weekly_plan_id' => $this->wp->id,
            'work_center_id' => $this->wc->id,
            'required_date' => Carbon::tomorrow()->toDateString(),
            'status' => 'pending',
            'created_by' => $this->warehouse->id,
        ]);
        $item = $supply->items()->create(['produk_id' => $rm->id, 'required_qty' => 100, 'delivered_qty' => 0]);
        $response = $this->actingAs($this->warehouse)->post(route('scm.supply.deliver', $supply->id), [
            'items' => [['id' => $item->id, 'delivered_qty' => 100]],
        ]);
        $response->assertRedirect();
        $supply->refresh();
        $this->assertEquals('delivered', $supply->status);
        $this->assertNotNull($supply->delivered_at);
    }

    public function test_mark_partial_when_some_delivered(): void
    {
        $rm = Produk::where('kode_produk', 'RM-SUP')->first();
        $supply = MaterialSupply::create([
            'supply_number' => MaterialSupply::nextNumber(),
            'weekly_plan_id' => $this->wp->id,
            'work_center_id' => $this->wc->id,
            'required_date' => Carbon::tomorrow()->toDateString(),
            'status' => 'pending',
            'created_by' => $this->warehouse->id,
        ]);
        $a = $supply->items()->create(['produk_id' => $rm->id, 'required_qty' => 100, 'delivered_qty' => 0]);
        $b = $supply->items()->create(['produk_id' => $rm->id, 'required_qty' => 50, 'delivered_qty' => 0]);
        $this->actingAs($this->warehouse)->post(route('scm.supply.deliver', $supply->id), [
            'items' => [['id' => $a->id, 'delivered_qty' => 100], ['id' => $b->id, 'delivered_qty' => 10]],
        ]);
        $this->assertEquals('partial', $supply->fresh()->status);
    }

    public function test_late_when_delivered_after_required_date(): void
    {
        $rm = Produk::where('kode_produk', 'RM-SUP')->first();
        $supply = MaterialSupply::create([
            'supply_number' => MaterialSupply::nextNumber(),
            'weekly_plan_id' => $this->wp->id,
            'work_center_id' => $this->wc->id,
            'required_date' => Carbon::yesterday()->toDateString(),
            'status' => 'pending',
            'created_by' => $this->warehouse->id,
        ]);
        $item = $supply->items()->create(['produk_id' => $rm->id, 'required_qty' => 10, 'delivered_qty' => 0]);
        $this->actingAs($this->warehouse)->post(route('scm.supply.deliver', $supply->id), [
            'items' => [['id' => $item->id, 'delivered_qty' => 10]],
        ]);
        $this->assertEquals('late', $supply->fresh()->status);
    }

    public function test_kpi_accessible(): void
    {
        $this->actingAs($this->warehouse)->get(route('scm.supply.kpi'))->assertOk();
        $this->actingAs($this->manager)->get(route('scm.supply.kpi'))->assertOk();
    }

    public function test_show_supply(): void
    {
        $supply = MaterialSupply::create([
            'supply_number' => MaterialSupply::nextNumber(),
            'weekly_plan_id' => $this->wp->id,
            'work_center_id' => $this->wc->id,
            'required_date' => Carbon::tomorrow()->toDateString(),
            'status' => 'pending',
            'created_by' => $this->warehouse->id,
        ]);
        $supply->items()->create(['produk_id' => $this->produk->id, 'required_qty' => 5, 'delivered_qty' => 0]);
        $this->actingAs($this->warehouse)->get(route('scm.supply.show', $supply->id))->assertOk();
    }

    public function test_create_page_loads_with_weekly_plans(): void
    {
        $this->actingAs($this->warehouse)->get(route('scm.supply.create'))->assertOk();
    }
}
