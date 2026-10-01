<?php

namespace App\Services\Odoo;

use App\Models\Setting;

/**
 * Dataset seed Odoo — mirror doc/ODOO_SEEDER_PLAN.md §5.
 * Dipakai command untuk display/validasi; record create dikerjakan odoo_seed.py.
 */
class OdooSeedPayloadBuilder
{
    public function dataset(): array
    {
        return [
            'partners' => [
                ['ref' => 'VND-001', 'name' => 'PT Bahan Herbal Nusantara', 'is_vendor' => true],
                ['ref' => 'CUS-001', 'name' => 'PT Distribusi Sehat Indonesia', 'is_customer' => true],
            ],
            'products' => [
                ['default_code' => 'HB-001', 'name' => 'Herbal Juice Lemon 1000ml', 'category' => 'FG', 'uom' => 'PCS', 'sale_ok' => true],
                ['default_code' => 'HB-002', 'name' => 'Herbal Juice Jahe 1000ml', 'category' => 'FG', 'uom' => 'PCS', 'sale_ok' => true],
                ['default_code' => 'HB-003', 'name' => 'Herbal Powder Kunyit 200g', 'category' => 'FG', 'uom' => 'PCS', 'sale_ok' => true],
                ['default_code' => 'RM-001', 'name' => 'Ekstrak Jahe', 'category' => 'RM', 'uom' => 'KG', 'sale_ok' => false],
                ['default_code' => 'RM-002', 'name' => 'Gula Aren', 'category' => 'RM', 'uom' => 'KG', 'sale_ok' => false],
                ['default_code' => 'RM-003', 'name' => 'Air Mineral', 'category' => 'RM', 'uom' => 'L', 'sale_ok' => false],
                ['default_code' => 'PM-001', 'name' => 'Botol PET 1000ml', 'category' => 'PM', 'uom' => 'PCS', 'sale_ok' => false],
                ['default_code' => 'PM-002', 'name' => 'Label Produk', 'category' => 'PM', 'uom' => 'PCS', 'sale_ok' => false],
                ['default_code' => 'PM-003', 'name' => 'Kardus Outer', 'category' => 'PM', 'uom' => 'PCS', 'sale_ok' => false],
            ],
            'boms' => [
                ['product_default_code' => 'HB-001', 'lines' => [
                    ['material' => 'RM-001', 'qty' => 0.15],
                    ['material' => 'RM-002', 'qty' => 0.08],
                    ['material' => 'PM-001', 'qty' => 1.0],
                    ['material' => 'PM-002', 'qty' => 1.0],
                ]],
            ],
            'manufacturing_orders' => [
                ['name' => 'MO/SEED/0001', 'origin' => 'Batch: BATCH-001', 'product' => 'HB-001', 'qty' => 2000, 'state' => 'confirmed'],
                ['name' => 'MO/SEED/0002', 'origin' => 'Batch: BATCH-002', 'product' => 'HB-002', 'qty' => 1500, 'state' => 'progress'],
                ['name' => 'MO/SEED/0003', 'origin' => 'Batch: BATCH-003', 'product' => 'HB-003', 'qty' => 1000, 'state' => 'cancel'],
            ],
            'scraps' => [
                ['origin' => 'Batch: BATCH-001', 'product' => 'RM-001', 'qty' => 2.5, 'state' => 'done'],
                ['origin' => 'Batch: BATCH-001', 'product' => 'PM-002', 'qty' => 15.0, 'state' => 'done'],
            ],
            'sale_orders' => [
                ['name' => 'SO/SEED/0001', 'partner' => 'CUS-001', 'state' => 'sale', 'line_product' => 'HB-001', 'line_qty' => 100.0, 'price_unit' => 250000.0],
            ],
        ];
    }

    /**
     * Payload stdin untuk odoo_seed.py (kredensial + flags). Dataset ada di script Python.
     */
    public function stdinPayload(string $only, bool $dryRun, bool $force, ?array $override = null): array
    {
        $override = $override ?? [];

        return [
            'host' => $override['host'] ?? rtrim((string) Setting::get('odoo_host', config('odoo.host', '')), '/'),
            'db' => $override['db'] ?? (string) Setting::get('odoo_db', config('odoo.db', '')),
            'username' => $override['username'] ?? (string) Setting::get('odoo_username', config('odoo.username', '')),
            'api_key' => $override['api_key'] ?? (string) Setting::get('odoo_api_key', config('odoo.api_key', '')),
            'timeout' => (int) ($override['timeout'] ?? Setting::get('odoo_timeout', config('odoo.timeout', 30))),
            'only' => $only,
            'dry_run' => $dryRun,
            'force' => $force,
        ];
    }
}
