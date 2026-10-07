<?php

namespace App\Console\Commands;

use App\Models\MaterialUsage;
use App\Services\OdooService;
use Illuminate\Console\Command;

class SyncUsageRatio extends Command
{
    protected $signature = 'material-usage:sync-ratio 
                            {--days=7 : Number of days back to sync}
                            {--dry-run : Run without making changes}';

    protected $description = 'Sync material usage ratio with Odoo actual usage from stock.move';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        $this->info("Starting Usage Ratio sync for last {$days} days...");
        $this->info('Dry run: ' . ($dryRun ? 'YES' : 'NO'));

        $dateFrom = now()->subDays($days)->toDateString();

        $usages = MaterialUsage::with(['weeklyPlan'])
            ->where('usage_date', '>=', $dateFrom)
            ->whereNotNull('weekly_plan_id')
            ->get();

        $this->info("Found {$usages->count()} Material Usage records to process");

        $odooService = new OdooService();
        $odooService->reloadConfig();

        $processed = 0;
        $updated = 0;
        $errors = 0;

        foreach ($usages as $usage) {
            $odooMoId = $usage->weeklyPlan?->odoo_mo_id;
            if (! $odooMoId) {
                continue;
            }

            try {
                // Fetch actual usage from Odoo
                $moves = $odooService->searchRead('stock.move', [
                    ['raw_material_production_id', '=', $odooMoId],
                    ['state', 'in', ['done', 'progress']],
                ], [
                    'id', 'product_id', 'quantity', 'product_uom_qty', 'product_uom',
                ], 1000);

                if (empty($moves)) {
                    continue;
                }

                // Aggregate by material name
                $odooActual = [];
                foreach ($moves as $m) {
                    $matName = is_array($m['product_id'] ?? null) ? $m['product_id'][1] : 'Material';
                    $qtyUsed = (float) ($m['quantity'] ?? $m['product_uom_qty'] ?? 0);
                    $odooActual[$matName] = ($odooActual[$matName] ?? 0) + $qtyUsed;
                }

                // Update each material usage item with Odoo actual
                foreach ($usage->items as $item) {
                    $odooQty = $odooActual[$item->material_name] ?? null;

                    if (! $dryRun && $odooQty !== null) {
                        $item->update([
                            'odoo_actual_qty' => $odooQty,
                            'odoo_variance_persen' => $item->quantity_standard > 0
                                ? round(($odooQty - $item->quantity_standard) / $item->quantity_standard * 100, 2)
                                : null,
                            'local_vs_odoo_variance_persen' => $odooQty > 0
                                ? round(($item->quantity_used - $odooQty) / $odooQty * 100, 2)
                                : null,
                        ]);
                        $updated++;
                    }
                }

                $processed++;
            } catch (\Exception $e) {
                $this->error("  Error for Usage #{$usage->id}: " . $e->getMessage());
                $errors++;
            }
        }

        $this->info("\nSync complete:");
        $this->info("  Processed: {$processed}");
        $this->info("  Items updated: {$updated}");
        $this->info("  Errors: {$errors}");

        if ($dryRun) {
            $this->warn("DRY RUN - No changes made.");
        }

        return 0;
    }
};