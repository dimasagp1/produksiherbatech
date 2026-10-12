<?php

namespace App\Console\Commands;

use App\Models\MaterialUsage;
use App\Services\UsageRatioService;
use Illuminate\Console\Command;

class SyncUsageRatio extends Command
{
    protected $signature = 'material-usage:sync-ratio {--days=7 : Number of days back to sync} {--dry-run : Run without making changes}';

    protected $description = 'Sync material usage ratio with Odoo actual usage from stock.move';

    public function handle(UsageRatioService $svc): int
    {
        $days = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');
        $this->info("Starting Usage Ratio sync for last {$days} days... Dry run: ".($dryRun ? 'YES' : 'NO'));
        $usages = MaterialUsage::with(['weeklyPlan'])->where('usage_date', '>=', now()->subDays($days)->toDateString())->whereNotNull('weekly_plan_id')->get();
        $this->info("Found {$usages->count()} records");
        $processed = 0;
        $updated = 0;
        $errors = 0;
        foreach ($usages as $usage) {
            if (! $usage->weeklyPlan?->odoo_mo_id) {
                continue;
            }
            try {
                $n = $svc->syncForUsage($usage, $dryRun);
                $updated += $n;
                $processed++;
            } catch (\Throwable $e) {
                $this->error("  Error for Usage #{$usage->id}: ".$e->getMessage());
                $errors++;
            }
        }
        $this->info("Sync complete: processed {$processed}, items {$updated}, errors {$errors}");
        if ($dryRun) {
            $this->warn('DRY RUN - No changes made.');
        }

        return 0;
    }
}
