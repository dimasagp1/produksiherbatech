<?php

namespace App\Services;

use App\Models\MaterialUsage;

class UsageRatioService
{
    public function __construct(private OdooService $odooService) {}

    public function calculate(MaterialUsage $usage): array
    {
        $usage->loadMissing(['items.produk.uom', 'items.uom', 'weeklyPlan.produk']);
        $odooMoId = $usage->weeklyPlan?->odoo_mo_id;
        $odooMap = [];
        if ($odooMoId) {
            try {
                $moves = $this->odooService->searchRead('stock.move', [
                    ['raw_material_production_id', '=', $odooMoId],
                    ['state', 'in', ['done', 'progress']],
                ], ['id', 'product_id', 'quantity', 'product_uom_qty', 'product_uom'], 1000);
                foreach ($moves as $m) {
                    $name = is_array($m['product_id'] ?? null) ? $m['product_id'][1] : 'Material';
                    $qty = (float) ($m['quantity'] ?? $m['product_uom_qty'] ?? 0);
                    $odooMap[$name] = ($odooMap[$name] ?? 0) + $qty;
                }
            } catch (\Throwable $e) {
                \Log::warning('UsageRatioService odoo fetch failed', ['mo' => $odooMoId, 'err' => $e->getMessage()]);
            }
        }

        $items = $usage->items->map(function ($item) use ($odooMap) {
            $odooQty = $odooMap[$item->material_name] ?? null;
            $std = (float) $item->quantity_standard;
            $used = (float) $item->quantity_used;
            $odooVar = $odooQty !== null && $std > 0 ? round(($odooQty - $std) / $std * 100, 2) : null;
            $localVsOdoo = $odooQty !== null && $odooQty != 0 ? round(($used - $odooQty) / $odooQty * 100, 2) : null;
            if ($odooQty !== null) {
                $item->updateQuietly([
                    'odoo_actual_qty' => $odooQty,
                    'odoo_variance_persen' => $odooVar,
                    'local_vs_odoo_variance_persen' => $localVsOdoo,
                ]);
            }

            return [
                ...$item->toArray(),
                'odoo_actual_qty' => $odooQty,
                'odoo_variance_persen' => $odooVar,
                'local_vs_odoo_variance_persen' => $localVsOdoo,
            ];
        });

        return ['items' => $items, 'odooMap' => $odooMap];
    }

    public function syncForUsage(MaterialUsage $usage, bool $dryRun = false): int
    {
        $result = $this->calculate($usage);
        if ($dryRun) {
            foreach ($result['items'] as $row) {
                if ($row['odoo_actual_qty'] !== null) {
                    $usage->items()->where('id', $row['id'])->update([
                        'odoo_actual_qty' => $row['odoo_actual_qty'],
                        'odoo_variance_persen' => $row['odoo_variance_persen'],
                        'local_vs_odoo_variance_persen' => $row['local_vs_odoo_variance_persen'],
                    ]);
                }
            }

            return collect($result['items'])->filter(fn ($r) => $r['odoo_actual_qty'] !== null)->count();
        }

        return collect($result['items'])->filter(fn ($r) => $r['odoo_actual_qty'] !== null)->count();
    }
}
