<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OdooService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BomSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $ppicUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->ppicUser = User::factory()->create()->assignRole('ppic');
    }

    public function test_ppic_can_preview_odoo_boms_with_complete_fields(): void
    {
        $mockOdoo = Mockery::mock(OdooService::class);
        $mockOdoo->shouldReceive('fetchBomsPreview')
            ->once()
            ->andReturn([
                [
                    'odoo_bom_id' => 11,
                    'name' => 'Vitasma Ruahan',
                    'product_name' => 'Vitasma Ruahan',
                    'product_id' => 131,
                    'odoo_product_tmpl_id' => 131,
                    'version' => 'Odoo-v1',
                    'code' => 'Odoo-v1',
                    'base_qty' => 1.0,
                    'uom' => 'g',
                    'line_count' => 14,
                    'lines_count' => 14,
                    'items' => [],
                    'exists_in_local' => true,
                    'exists_locally' => false,
                    'local_produk_id' => 1,
                    'local_produk_name' => 'Vitasma Ruahan',
                    'bom_exists' => false,
                    'can_sync' => true,
                ],
            ]);

        $this->app->instance(OdooService::class, $mockOdoo);

        $response = $this->actingAs($this->ppicUser)
            ->getJson(route('scm.bom.preview-odoo'));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'count' => 1,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'odoo_bom_id',
                        'name',
                        'product_name',
                        'version',
                        'code',
                        'line_count',
                        'lines_count',
                        'exists_in_local',
                        'can_sync',
                        'bom_exists',
                    ],
                ],
                'count',
            ]);

        $item = $response->json('data.0');
        $this->assertEquals('Vitasma Ruahan', $item['name']);
        $this->assertEquals('Vitasma Ruahan', $item['product_name']);
        $this->assertTrue($item['can_sync']);
        $this->assertEquals(14, $item['line_count']);
    }

    public function test_ppic_can_sync_selected_odoo_boms(): void
    {
        $mockOdoo = Mockery::mock(OdooService::class);
        $mockOdoo->shouldReceive('syncBoms')
            ->once()
            ->with([11])
            ->andReturn([
                'created' => 1,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [],
            ]);

        $this->app->instance(OdooService::class, $mockOdoo);

        $response = $this->actingAs($this->ppicUser)
            ->postJson(route('scm.bom.sync-odoo'), [
                'selected_ids' => [11],
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'summary' => [
                    'created' => 1,
                    'updated' => 0,
                    'skipped' => 0,
                ],
            ]);
    }
}
