<?php

namespace App\Console\Commands;

use App\Models\InventoryStock;
use App\Services\OdooService;
use Illuminate\Console\Command;

class SnapshotBeginningStock extends Command
{
    protected $signature = 'inventory:snapshot-beginning 
                            {--dry-run : Run without making changes}';

    protected $description = 'Create monthly beginning stock snapshot from current inventory';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('Starting Beginning Stock snapshot...');
        $this->info('Dry run: ' . ($dryRun ? 'YES' : 'NO'));

        $snapshotDate = now()->startOfMonth()->toDateString();

        // Clear existing baseline for this month
        if (! $dryRun) {
            InventoryStock::where('snapshot_date', $snapshotDate)
                ->where('is_baseline', true)
                ->update(['is_baseline' => false]);
        }

        // Get all current stocks at main location
        $stocks = InventoryStock::where('location', 'GUDANG-UTAMA')
            ->whereNull('batch_number')
            ->get();

        $this->info("Found {$stocks->count()} stock records to snapshot");

        $created = 0;
        $updated = 0;

        foreach ($stocks as $stock) {
            $this->line("  Produk #{$stock->produk_id}: qty={$stock->quantity}");

            if (! $dryRun) {
                $result = InventoryStock::updateOrCreate(
                    [
                        'produk_id' => $stock->produk_id,
                        'location' => 'GUDANG-UTAMA',
                        'batch_number' => null,
                        'snapshot_date' => $snapshotDate,
                    ],
                    [
                        'quantity' => $stock->quantity,
                        'beginning_stock_monthly' => $stock->quantity,
                        'is_baseline' => true,
                        'expired_date' => $stock->expired_date,
                    ]
                );

                if ($result->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            } else {
                $existing = InventoryStock::where('produk_id', $stock->produk_id)
                    ->where('location', 'GUDANG-UTAMA')
                    ->whereNull('batch_number')
                    ->where('snapshot_date', $snapshotDate)
                    ->exists();
                if ($existing) {
                    $updated++;
                } else {
                    $created++;
                }
            }
        }

        $this->info("\nSnapshot complete:");
        $this->info("  Created: {$created}");
        $this->info("  Updated: {$updated}");

        if ($dryRun) {
            $this->warn("DRY RUN - No changes made.");
        }

        return 0;
    }
};