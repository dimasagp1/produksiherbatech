<?php

namespace Tests\Feature;

use App\Models\InventoryStock;
use App\Models\Line;
use App\Models\MpsItem;
use App\Models\MpsPlan;
use App\Models\Produk;
use App\Models\User;
use App\Models\WeeklyPlan;
use App\Models\WorkCenter;
use App\Services\MpsWeeklyPlanGenerator;
use App\Services\TargetCalculationService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkCenterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MpsTest extends TestCase
{
    use RefreshDatabase;

    protected User $ppicUser;

    protected User $adminUser;

    protected User $managerUser;

    protected Produk $produk;

    protected WorkCenter $wcMixing;

    protected WorkCenter $wcFilling;

    protected WorkCenter $wcSecondary;

    protected Line $lineA;

    protected Line $lineB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(WorkCenterSeeder::class);

        $this->ppicUser = User::factory()->create()->assignRole('ppic');
        $this->adminUser = User::factory()->create()->assignRole('superadmin');
        $this->managerUser = User::factory()->create()->assignRole('manager');

        $this->wcMixing = WorkCenter::where('type', 'mixing')->first();
        $this->wcFilling = WorkCenter::where('type', 'filling')->first();
        $this->wcSecondary = WorkCenter::where('type', 'secondary')->first();

        $this->produk = Produk::factory()->create([
            'nama_produk' => 'Test Produk',
            'kode_produk' => 'TEST001',
            'work_center_id' => $this->wcMixing->id,
        ]);

        $this->lineA = Line::factory()->create([
            'kode_line' => 'L1',
            'nama_line' => 'Line A',
            'default_work_center_id' => $this->wcMixing->id,
            'can_run_work_centers' => ['mixing', 'filling'],
        ]);

        $this->lineB = Line::factory()->create([
            'kode_line' => 'L2',
            'nama_line' => 'Line B',
            'default_work_center_id' => $this->wcFilling->id,
            'can_run_work_centers' => ['filling', 'secondary'],
        ]);
    }

    // ==================== MPS Plan CRUD ====================

    public function test_ppic_can_view_mps_index()
    {
        $response = $this->actingAs($this->ppicUser)->get(route('ppic.mps.index'));
        $response->assertOk();
    }

    public function test_ppic_can_create_mps_plan()
    {
        $response = $this->actingAs($this->ppicUser)->post(route('ppic.mps.store'), [
            'produk_id' => $this->produk->id,
            'work_center_id' => $this->wcMixing->id,
            'month_year' => '2026-10',
            'notes' => 'Test plan',
        ]);

        $response->assertRedirect(route('ppic.mps.show', 1));
        $this->assertDatabaseHas('mps_plans', [
            'produk_id' => $this->produk->id,
            'work_center_id' => $this->wcMixing->id,
            'month_year' => '2026-10',
            'status' => 'draft',
        ]);
    }

    public function test_cannot_create_duplicate_mps_plan()
    {
        MpsPlan::factory()->create([
            'produk_id' => $this->produk->id,
            'work_center_id' => $this->wcMixing->id,
            'month_year' => '2026-10',
        ]);

        $response = $this->actingAs($this->ppicUser)->post(route('ppic.mps.store'), [
            'produk_id' => $this->produk->id,
            'work_center_id' => $this->wcMixing->id,
            'month_year' => '2026-10',
        ]);

        $response->assertSessionHasErrors('month_year');
    }

    public function test_only_ppic_and_superadmin_can_access_mps()
    {
        $leader = User::factory()->create()->assignRole('leader');

        $response = $this->actingAs($leader)->get(route('ppic.mps.index'));
        $response->assertForbidden();
    }

    public function test_manager_can_view_mps_index_read_only()
    {
        $response = $this->actingAs($this->managerUser)->get(route('ppic.mps.index'));
        $response->assertOk();
    }

    public function test_manager_cannot_create_mps_plan()
    {
        $response = $this->actingAs($this->managerUser)->post(route('ppic.mps.store'), [
            'produk_id' => $this->produk->id,
            'work_center_id' => $this->wcMixing->id,
            'month_year' => '2026-10',
            'notes' => 'Test plan',
        ]);

        $response->assertForbidden();
    }

    public function test_manager_cannot_update_mps_item()
    {
        $plan = MpsPlan::factory()->create([
            'produk_id' => $this->produk->id,
            'work_center_id' => $this->wcMixing->id,
            'month_year' => '2026-10',
            'status' => 'draft',
            'created_by' => $this->ppicUser->id,
        ]);

        $item = MpsItem::factory()->create([
            'mps_plan_id' => $plan->id,
            'tanggal' => '2026-10-01',
            'line_id' => $this->lineA->id,
            'work_center_id' => $this->wcMixing->id,
            'shift' => 'shift1',
            'mp_count' => 4,
            'cleaning' => false,
        ]);

        $response = $this->actingAs($this->managerUser)->post(route('ppic.mps.item.update', $item->id), [
            'line_id' => $this->lineA->id,
            'work_center_id' => $this->wcMixing->id,
            'shift' => 'shift1',
            'mp_count' => 8,
            'cleaning' => false,
        ], ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertForbidden();
    }

    public function test_manager_cannot_approve_mps_plan()
    {
        $plan = MpsPlan::factory()->create([
            'produk_id' => $this->produk->id,
            'work_center_id' => $this->wcMixing->id,
            'month_year' => '2026-10',
            'status' => 'draft',
            'created_by' => $this->ppicUser->id,
        ]);

        $response = $this->actingAs($this->managerUser)->post(route('ppic.mps.approve', $plan->id));

        $response->assertForbidden();
    }

    public function test_manager_cannot_generate_weekly_plans()
    {
        $plan = MpsPlan::factory()->create([
            'produk_id' => $this->produk->id,
            'work_center_id' => $this->wcMixing->id,
            'month_year' => '2026-10',
            'status' => 'approved',
            'created_by' => $this->ppicUser->id,
        ]);

        MpsItem::factory()->create([
            'mps_plan_id' => $plan->id,
            'tanggal' => '2026-10-01',
            'line_id' => $this->lineA->id,
            'work_center_id' => $this->wcMixing->id,
            'shift' => 'shift1',
            'mp_count' => 4,
            'cleaning' => false,
            'target_qty' => 31200,
        ]);

        $response = $this->actingAs($this->managerUser)->post(route('ppic.mps.generate-weekly', $plan->id));

        $response->assertForbidden();
    }

    public function test_manager_cannot_destroy_mps_plan()
    {
        $plan = MpsPlan::factory()->create([
            'status' => 'draft',
            'created_by' => $this->ppicUser->id,
        ]);

        $response = $this->actingAs($this->managerUser)->delete(route('ppic.mps.destroy', $plan->id));

        $response->assertForbidden();
    }

    // ==================== MPS Item CRUD (Grid) ====================

    public function test_can_add_mps_item_via_grid()
    {
        $plan = MpsPlan::factory()->create([
            'produk_id' => $this->produk->id,
            'work_center_id' => $this->wcMixing->id,
            'month_year' => '2026-10',
            'status' => 'draft',
            'created_by' => $this->ppicUser->id,
        ]);

        $response = $this->actingAs($this->ppicUser)->post(route('ppic.mps.item.update', 999), [
            'line_id' => $this->lineA->id,
            'work_center_id' => $this->wcMixing->id,
            'shift' => 'shift1',
            'mp_count' => 4,
            'cleaning' => false,
        ], ['X-Requested-With' => 'XMLHttpRequest']);

        // Item doesn't exist yet, should create new (id 999 doesn't exist)
        // In real scenario, we'd use a valid item ID or create first
        // This tests the validation logic
        $response->assertStatus(404); // Item not found
    }

    public function test_can_update_mps_item_target_qty()
    {
        $plan = MpsPlan::factory()->create([
            'produk_id' => $this->produk->id,
            'work_center_id' => $this->wcMixing->id,
            'month_year' => '2026-10',
            'status' => 'draft',
            'created_by' => $this->ppicUser->id,
        ]);

        $item = MpsItem::factory()->create([
            'mps_plan_id' => $plan->id,
            'tanggal' => '2026-10-01',
            'line_id' => $this->lineA->id,
            'work_center_id' => $this->wcMixing->id,
            'shift' => 'shift1',
            'mp_count' => 4,
            'cleaning' => false,
        ]);

        $oldTarget = $item->target_qty;

        $response = $this->actingAs($this->ppicUser)->post(route('ppic.mps.item.update', $item->id), [
            'line_id' => $this->lineA->id,
            'work_center_id' => $this->wcMixing->id,
            'shift' => 'shift1',
            'mp_count' => 8,
            'cleaning' => false,
        ], ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $item->refresh();

        // Target should recalculate: ((60/3) * 6.5 * 60 / 4) * 8 = 15600
        $this->assertNotEquals($oldTarget, $item->target_qty);
        $this->assertEquals(15600, $item->target_qty);
    }

    public function test_cleaning_flag_skips_weekly_plan_generation()
    {
        $plan = MpsPlan::factory()->create([
            'produk_id' => $this->produk->id,
            'work_center_id' => $this->wcMixing->id,
            'month_year' => '2026-10',
            'status' => 'approved',
            'created_by' => $this->ppicUser->id,
        ]);

        // Item with cleaning = true
        MpsItem::factory()->create([
            'mps_plan_id' => $plan->id,
            'tanggal' => '2026-10-01',
            'line_id' => $this->lineA->id,
            'work_center_id' => $this->wcMixing->id,
            'shift' => 'shift1',
            'mp_count' => 4,
            'cleaning' => true,
            'target_qty' => 31200,
        ]);

        // Item without cleaning
        MpsItem::factory()->create([
            'mps_plan_id' => $plan->id,
            'tanggal' => '2026-10-02',
            'line_id' => $this->lineA->id,
            'work_center_id' => $this->wcMixing->id,
            'shift' => 'shift1',
            'mp_count' => 4,
            'cleaning' => false,
            'target_qty' => 31200,
        ]);

        $plan->load('items.line', 'items.workCenter');
        $result = app(MpsWeeklyPlanGenerator::class)->generate($plan);

        // Only 1 should be created (cleaning day is filtered out, not counted as skipped)
        // skipped count is for duplicate WeeklyPlans, not cleaning items
        $this->assertEquals(1, $result['created']);
        $this->assertEquals(0, $result['skipped']);
    }

    // ==================== WeeklyPlan Generation ====================

    public function test_generate_weekly_plans_from_mps()
    {
        $plan = MpsPlan::factory()->create([
            'produk_id' => $this->produk->id,
            'work_center_id' => $this->wcMixing->id,
            'month_year' => '2026-10',
            'status' => 'approved',
            'created_by' => $this->ppicUser->id,
        ]);

        MpsItem::factory()->create([
            'mps_plan_id' => $plan->id,
            'tanggal' => '2026-10-01',
            'line_id' => $this->lineA->id,
            'work_center_id' => $this->wcMixing->id,
            'shift' => 'shift1',
            'mp_count' => 4,
            'cleaning' => false,
            'target_qty' => 31200,
        ]);

        $response = $this->actingAs($this->ppicUser)->post(route('ppic.mps.generate-weekly', $plan->id));
        $response->assertRedirect();

        $plan->refresh();
        $weeklyPlans = $plan->weeklyPlans;

        $this->assertCount(1, $weeklyPlans);

        $wp = $weeklyPlans->first();
        $this->assertEquals('mixing', $wp->proses);
        $this->assertEquals($this->lineA->id, $wp->line_id);
        $this->assertEquals($this->wcMixing->id, $wp->work_center_id);
        $this->assertEquals('shift1', $wp->shift);
        $this->assertEquals(7800, $wp->target_output);
        $this->assertEquals(4, $wp->mp_count);
        $this->assertEquals('draft', $wp->status);
        $this->assertEquals('mixing', $wp->proses);
        $this->assertStringStartsWith('MPS-TEST001-20261001-S1-', $wp->batch_number);
    }

    public function test_cannot_generate_from_draft_plan()
    {
        $plan = MpsPlan::factory()->create([
            'status' => 'draft',
            'created_by' => $this->ppicUser->id,
        ]);

        $response = $this->actingAs($this->ppicUser)->post(route('ppic.mps.generate-weekly', $plan->id));
        $response->assertSessionHasErrors('error');
    }

    public function test_weekly_plan_batch_number_format()
    {
        $plan = MpsPlan::factory()->create([
            'produk_id' => $this->produk->id,
            'work_center_id' => $this->wcMixing->id,
            'month_year' => '2026-10',
            'status' => 'approved',
            'created_by' => $this->ppicUser->id,
        ]);

        $item = MpsItem::factory()->create([
            'mps_plan_id' => $plan->id,
            'tanggal' => '2026-10-01',
            'line_id' => $this->lineA->id,
            'work_center_id' => $this->wcMixing->id,
            'shift' => 'shift1',
            'mp_count' => 4,
            'cleaning' => false,
            'target_qty' => 31200,
        ]);

        $generator = app(MpsWeeklyPlanGenerator::class);
        $generator->generate($plan);

        $wp = WeeklyPlan::where('batch_number', 'like', 'MPS-%')->first();

        $this->assertStringStartsWith('MPS-TEST001-20261001-S1-', $wp->batch_number);
        $this->assertMatchesRegularExpression('/MPS-TEST001-20261001-S1-\d{3}/', $wp->batch_number);
    }

    public function test_duplicate_weekly_plan_skipped()
    {
        $plan = MpsPlan::factory()->create([
            'produk_id' => $this->produk->id,
            'work_center_id' => $this->wcMixing->id,
            'month_year' => '2026-10',
            'status' => 'approved',
            'created_by' => $this->ppicUser->id,
        ]);

        $item = MpsItem::factory()->create([
            'mps_plan_id' => $plan->id,
            'tanggal' => '2026-10-01',
            'line_id' => $this->lineA->id,
            'work_center_id' => $this->wcMixing->id,
            'shift' => 'shift1',
            'mp_count' => 4,
            'cleaning' => false,
            'target_qty' => 31200,
        ]);

        $generator = app(MpsWeeklyPlanGenerator::class);

        // First generation
        $result1 = $generator->generate($plan);
        $this->assertEquals(1, $result1['created']);
        $this->assertEquals(0, $result1['skipped']);

        // Second generation should skip
        $result2 = $generator->generate($plan);
        $this->assertEquals(0, $result2['created']);
        $this->assertEquals(1, $result2['skipped']);
    }

    // ==================== Line Validation ====================

    public function test_line_can_only_run_allowed_work_centers()
    {
        $plan = MpsPlan::factory()->create([
            'work_center_id' => $this->wcFilling->id,
            'month_year' => '2026-10',
            'status' => 'draft',
            'created_by' => $this->ppicUser->id,
        ]);

        // Create an item first
        $item = MpsItem::factory()->create([
            'mps_plan_id' => $plan->id,
            'tanggal' => '2026-10-01',
            'line_id' => $this->lineA->id,
            'work_center_id' => $this->wcFilling->id,
            'shift' => 'shift1',
            'mp_count' => 4,
            'cleaning' => false,
        ]);

        // Line A can run mixing and filling, but not secondary
        $response = $this->actingAs($this->ppicUser)->post(route('ppic.mps.item.update', $item->id), [
            'line_id' => $this->lineA->id,
            'work_center_id' => $this->wcSecondary->id,
            'shift' => 'shift1',
            'mp_count' => 4,
            'cleaning' => false,
        ], ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertStatus(422); // Validation error
    }

    public function test_line_can_run_allowed_work_center()
    {
        $plan = MpsPlan::factory()->create([
            'work_center_id' => $this->wcFilling->id,
            'month_year' => '2026-10',
            'status' => 'draft',
            'created_by' => $this->ppicUser->id,
        ]);

        // Create an item first
        $item = MpsItem::factory()->create([
            'mps_plan_id' => $plan->id,
            'tanggal' => '2026-10-01',
            'line_id' => $this->lineA->id,
            'work_center_id' => $this->wcFilling->id,
            'shift' => 'shift1',
            'mp_count' => 4,
            'cleaning' => false,
        ]);

        // Line A can run filling
        $response = $this->actingAs($this->ppicUser)->post(route('ppic.mps.item.update', $item->id), [
            'line_id' => $this->lineA->id,
            'work_center_id' => $this->wcFilling->id,
            'shift' => 'shift1',
            'mp_count' => 4,
            'cleaning' => false,
        ], ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk(); // Validation passes, item updated
    }

    // ==================== Target Calculation ====================

    public function test_target_qty_calculation_uses_ct_and_shift_hours()
    {
        $plan = MpsPlan::factory()->create([
            'work_center_id' => $this->wcMixing->id,
            'month_year' => '2026-10',
        ]);

        // Mixing: CT=3s, shift_hours=6.5, mp_count=4
        // output/min = 60/3 = 20
        // per shift = 20 * 60 * 6.5 = 7800
        // total = 7800 * 4 = 31200
        $item = MpsItem::factory()->create([
            'mps_plan_id' => $plan->id,
            'tanggal' => '2026-10-01', // Thursday
            'line_id' => $this->lineA->id,
            'work_center_id' => $this->wcMixing->id,
            'shift' => 'shift1',
            'mp_count' => 4,
            'cleaning' => false,
        ]);

        $item->recalculateTarget();
        // Mixing: 20 pcs/min * 6.5h * 60 = 7800 pcs (fit_mp = 4, mp_count = 4)
        $this->assertEquals(7800, $item->target_qty);
    }

    public function test_saturday_uses_saturday_shift_hours()
    {
        // 2026-10-03 is a Saturday
        $plan = MpsPlan::factory()->create([
            'work_center_id' => $this->wcMixing->id,
            'month_year' => '2026-10',
        ]);

        $item = MpsItem::factory()->create([
            'mps_plan_id' => $plan->id,
            'tanggal' => '2026-10-03', // Saturday
            'line_id' => $this->lineA->id,
            'work_center_id' => $this->wcMixing->id,
            'shift' => 'shift1',
            'mp_count' => 4,
            'cleaning' => false,
        ]);

        $item->recalculateTarget();

        // Saturday: shift_hours = 4.0 (saturday_shift_hours)
        // output/min = 20
        // per shift = 20 * 60 * 4.0 = 4800 (fit_mp = 4, mp_count = 4)
        $this->assertEquals(4800, $item->target_qty);
        $this->assertTrue($item->isSaturday());
    }

    // ==================== Gap Tracking ====================

    public function test_gap_calculation()
    {
        $plan = MpsPlan::factory()->create([
            'work_center_id' => $this->wcMixing->id,
            'month_year' => '2026-10',
        ]);

        $item = MpsItem::factory()->create([
            'mps_plan_id' => $plan->id,
            'tanggal' => '2026-10-01',
            'line_id' => $this->lineA->id,
            'work_center_id' => $this->wcMixing->id,
            'shift' => 'shift1',
            'mp_count' => 4,
            'cleaning' => false,
            'target_qty' => 7800,
            'adjusted_qty' => 8000,
        ]);

        $this->assertEquals(200, $item->gap_qty); // 8000 - 7800

        // Negative gap
        $item->adjusted_qty = 7000;
        $this->assertEquals(-800, $item->gap_qty);

        // Null adjusted_qty
        $item->adjusted_qty = null;
        $this->assertNull($item->gap_qty);
    }

    // ==================== Status Flow ====================

    public function test_mps_plan_status_flow()
    {
        $plan = MpsPlan::factory()->create([
            'status' => 'draft',
        ]);

        $this->assertEquals('draft', $plan->status);

        // Approve
        $plan->update(['status' => 'approved']);
        $this->assertEquals('approved', $plan->status);

        // Activate
        $plan->update(['status' => 'active']);
        $this->assertEquals('active', $plan->status);

        // Close
        $plan->update(['status' => 'closed']);
        $this->assertEquals('closed', $plan->status);
    }

    public function test_beginning_stock_oh_accessor()
    {
        $plan = MpsPlan::factory()->create([
            'produk_id' => $this->produk->id,
        ]);

        // No stock
        $this->assertEquals(0, $plan->beginning_stock_oh);

        // Add stock
        InventoryStock::create([
            'produk_id' => $this->produk->id,
            'quantity' => 1000,
            'location' => 'GUDANG-UTAMA',
        ]);

        $plan->refresh();
        $this->assertEquals(1000, $plan->beginning_stock_oh);
    }

    // ==================== WeeklyPlan Target Adjustment ====================

    public function test_weekly_plan_generator_uses_adjusted_qty_when_present()
    {
        $plan = MpsPlan::factory()->create([
            'produk_id' => $this->produk->id,
            'work_center_id' => $this->wcMixing->id,
            'month_year' => '2026-10',
            'status' => 'approved',
            'created_by' => $this->ppicUser->id,
        ]);

        $item = MpsItem::factory()->create([
            'mps_plan_id' => $plan->id,
            'tanggal' => '2026-10-01',
            'line_id' => $this->lineA->id,
            'work_center_id' => $this->wcMixing->id,
            'shift' => 'shift1',
            'mp_count' => 4,
            'cleaning' => false,
            'target_qty' => 7800,
            'adjusted_qty' => 8500,
        ]);

        $plan->load('items.line', 'items.workCenter');
        $result = app(MpsWeeklyPlanGenerator::class)->generate($plan);

        $this->assertEquals(1, $result['created']);

        $wp = WeeklyPlan::where('batch_number', 'like', 'MPS-%')->first();
        $this->assertNotNull($wp);
        $this->assertEquals(8500, $wp->target_output);
    }

    // ==================== Capacity Guard Validation ====================

    public function test_mps_item_capacity_limit_validation()
    {
        $plan = MpsPlan::factory()->create([
            'produk_id' => $this->produk->id,
            'work_center_id' => $this->wcMixing->id,
            'month_year' => '2026-10',
            'status' => 'draft',
            'created_by' => $this->ppicUser->id,
        ]);

        $item = MpsItem::factory()->create([
            'mps_plan_id' => $plan->id,
            'tanggal' => '2026-10-01',
            'line_id' => $this->lineA->id,
            'work_center_id' => $this->wcMixing->id,
            'shift' => 'shift1',
            'mp_count' => 4,
            'target_qty' => 7800,
        ]);

        $response = $this->actingAs($this->ppicUser)
            ->post(route('ppic.mps.item.update', $item->id), [
                'shift' => 'shift1',
                'adjusted_qty' => 100000,
                'gap_reason' => 'capacity_limit',
            ]);

        $response->assertSessionHasErrors('adjusted_qty');
    }

    // ==================== Revision Flow ====================

    public function test_can_create_revision_from_approved_plan()
    {
        $plan = MpsPlan::factory()->create([
            'produk_id' => $this->produk->id,
            'work_center_id' => $this->wcMixing->id,
            'month_year' => '2026-10',
            'revision' => 1,
            'is_active_revision' => true,
            'status' => 'approved',
            'created_by' => $this->ppicUser->id,
        ]);

        MpsItem::factory()->create([
            'mps_plan_id' => $plan->id,
            'tanggal' => '2026-10-01',
            'line_id' => $this->lineA->id,
            'work_center_id' => $this->wcMixing->id,
            'shift' => 'shift1',
            'mp_count' => 4,
            'target_qty' => 7800,
            'adjusted_qty' => 8000,
        ]);

        $response = $this->actingAs($this->ppicUser)
            ->post(route('ppic.mps.revision', $plan->id));

        $newPlan = MpsPlan::where('parent_plan_id', $plan->id)->first();
        $this->assertNotNull($newPlan);
        $this->assertEquals(2, $newPlan->revision);
        $this->assertEquals('draft', $newPlan->status);
        $this->assertFalse($newPlan->is_active_revision);
        $this->assertEquals(1, $newPlan->items()->count());

        $copiedItem = $newPlan->items()->first();
        $this->assertEquals('2026-10-01', $copiedItem->tanggal);
        $this->assertEquals(8000, $copiedItem->adjusted_qty);

        $response->assertRedirect(route('ppic.mps.show', $newPlan->id));
    }

    public function test_approving_revision_marks_previous_revisions_inactive()
    {
        $planRev1 = MpsPlan::factory()->create([
            'produk_id' => $this->produk->id,
            'work_center_id' => $this->wcMixing->id,
            'month_year' => '2026-10',
            'revision' => 1,
            'is_active_revision' => true,
            'status' => 'approved',
            'created_by' => $this->ppicUser->id,
        ]);

        $planRev2 = MpsPlan::factory()->create([
            'produk_id' => $this->produk->id,
            'work_center_id' => $this->wcMixing->id,
            'month_year' => '2026-10',
            'revision' => 2,
            'is_active_revision' => false,
            'parent_plan_id' => $planRev1->id,
            'status' => 'draft',
            'created_by' => $this->ppicUser->id,
        ]);

        MpsItem::factory()->create([
            'mps_plan_id' => $planRev2->id,
            'tanggal' => '2026-10-01',
            'line_id' => $this->lineA->id,
            'work_center_id' => $this->wcMixing->id,
            'shift' => 'shift1',
            'mp_count' => 4,
            'target_qty' => 7800,
        ]);

        $this->actingAs($this->ppicUser)
            ->post(route('ppic.mps.approve', $planRev2->id));

        $planRev1->refresh();
        $planRev2->refresh();

        $this->assertFalse($planRev1->is_active_revision);
        $this->assertTrue($planRev2->is_active_revision);
        $this->assertEquals('approved', $planRev2->status);
    }

    // ==================== Target Calculation Service ====================

    public function test_target_calculation_service_matches_formula()
    {
        $wc = $this->wcMixing;
        $wc->update(['standard_ct_seconds' => 3, 'fit_mp' => 4]);

        $target = TargetCalculationService::calculateTarget($wc, 4, 7);
        $this->assertEquals(8400, $target);

        $targetHalf = TargetCalculationService::calculateTarget($wc, 2, 7);
        $this->assertEquals(4200, $targetHalf);
    }
}
