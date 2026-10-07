<?php

namespace App\Console\Commands;

use App\Models\LaporanHarian;
use App\Models\Mesin;
use App\Models\Produk;
use App\Models\WeeklyPlan;
use App\Models\WorkCenter;
use Illuminate\Console\Command;

class MigrateWorkCenterData extends Command
{
    protected $signature = 'migrate:work-center-data 
                            {--dry-run : Run without making changes}
                            {--force : Force migration even if work_center_id already populated}';

    protected $description = 'Map existing proses to work_center_id for weekly_plans, laporan_harians, and produks';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('Starting Work Center data migration...');
        $this->info('Dry run: ' . ($dryRun ? 'YES' : 'NO'));
        $this->info('Force: ' . ($force ? 'YES' : 'NO'));

        // Get work centers
        $wcMixing = WorkCenter::where('type', 'mixing')->first();
        $wcFilling = WorkCenter::where('type', 'filling')->first();
        $wcSecondary = WorkCenter::where('type', 'secondary')->first();

        if (! $wcMixing || ! $wcFilling || ! $wcSecondary) {
            $this->error('Work Centers not found. Please run WorkCenterSeeder first.');
            return 1;
        }

        $this->info("Work Centers found:");
        $this->info("  Mixing: {$wcMixing->name} (ID: {$wcMixing->id})");
        $this->info("  Filling: {$wcFilling->name} (ID: {$wcFilling->id})");
        $this->info("  Secondary: {$wcSecondary->name} (ID: {$wcSecondary->id})");

        $prosesMap = [
            'mixing' => $wcMixing->id,
            'filling' => $wcFilling->id,
            'packing' => $wcSecondary->id,
            'secondary' => $wcSecondary->id,
        ];

        // 1. Migrate weekly_plans
        $this->info("\n1. Migrating weekly_plans...");
        $weeklyPlans = WeeklyPlan::all();
        $wpUpdated = 0;
        foreach ($weeklyPlans as $wp) {
            $newWcId = $prosesMap[$wp->proses] ?? null;
            if ($newWcId && ($force || ! $wp->work_center_id)) {
                $this->line("  WP #{$wp->id} ({$wp->batch_number}) - proses: {$wp->proses} -> WC: {$newWcId}");
                if (! $dryRun) {
                    $wp->update(['work_center_id' => $newWcId]);
                }
                $wpUpdated++;
            }
        }
        $this->info("  Updated: {$wpUpdated} weekly plans");

        // 2. Migrate laporan_harians
        $this->info("\n2. Migrating laporan_harians...");
        $laporans = LaporanHarian::all();
        $lhUpdated = 0;
        foreach ($laporans as $lh) {
            $newWcId = $prosesMap[$lh->proses] ?? null;
            if ($newWcId && ($force || ! $lh->work_center_id)) {
                $this->line("  LH #{$lh->id} ({$lh->batch_number}) - proses: {$lh->proses} -> WC: {$newWcId}");
                if (! $dryRun) {
                    $lh->update(['work_center_id' => $newWcId]);
                }
                $lhUpdated++;
            }
        }
        $this->info("  Updated: {$lhUpdated} laporan harians");

        // 3. Migrate produks (set default work_center_id based on proses_default)
        $this->info("\n3. Migrating produks...");
        $produks = Produk::all();
        $prodUpdated = 0;
        foreach ($produks as $prod) {
            $newWcId = $prosesMap[$prod->proses_default] ?? null;
            if ($newWcId && ($force || ! $prod->work_center_id)) {
                $this->line("  Produk #{$prod->id} ({$prod->nama_produk}) - proses_default: {$prod->proses_default} -> WC: {$newWcId}");
                if (! $dryRun) {
                    $prod->update(['work_center_id' => $newWcId]);
                }
                $prodUpdated++;
            }
        }
        $this->info("  Updated: {$prodUpdated} produks");

        // 4. Migrate mesins (set work_center_id based on proses from related laporan_harians or name heuristic)
        $this->info("\n4. Migrating mesins...");
        $mesins = Mesin::all();
        $mesinUpdated = 0;
        foreach ($mesins as $mesin) {
            // Try to infer from existing laporan_harians using this mesin
            $lh = LaporanHarian::where('mesin_id', $mesin->id)->first();
            $newWcId = null;
            if ($lh && $lh->work_center_id) {
                $newWcId = $lh->work_center_id;
            } elseif ($lh) {
                $newWcId = $prosesMap[$lh->proses] ?? null;
            } else {
                // Fallback: map by name heuristic
                $name = strtolower($mesin->nama_mesin);
                if (str_contains($name, 'mixer') || str_contains($name, 'mix')) {
                    $newWcId = $wcMixing->id;
                } elseif (str_contains($name, 'packer') || str_contains($name, 'pack') || str_contains($name, 'packing')) {
                    $newWcId = $wcSecondary->id;
                } elseif (str_contains($name, 'sachet') || str_contains($name, 'filling') || str_contains($name, 'fill')) {
                    $newWcId = $wcFilling->id;
                }
            }
            
            if ($newWcId && ($force || ! $mesin->work_center_id)) {
                $this->line("  Mesin #{$mesin->id} ({$mesin->nama_mesin}) -> WC: {$newWcId}");
                if (! $dryRun) {
                    $mesin->update(['work_center_id' => $newWcId]);
                }
                $mesinUpdated++;
            }
        }
        $this->info("  Updated: {$mesinUpdated} mesins");

        // 5. Recalculate target_output for weekly_plans using new formula
        if (! $dryRun) {
            $this->info("\n4. Recalculating target_output for weekly_plans...");
            $recalcCount = 0;
            foreach (WeeklyPlan::whereNotNull('work_center_id')->where('mp_count', '>', 0)->get() as $wp) {
                $oldTarget = $wp->target_output;
                $wp->load('workCenter');
                if ($wp->workCenter) {
                    $newTarget = \App\Services\TargetCalculationService::calculateTarget($wp->workCenter, $wp->mp_count);
                    if ($oldTarget != $newTarget) {
                        $this->line("  WP #{$wp->id}: {$oldTarget} -> {$newTarget}");
                        $wp->update(['target_output' => $newTarget]);
                        $recalcCount++;
                    }
                }
            }
            $this->info("  Recalculated: {$recalcCount} weekly plans");
        }

        if ($dryRun) {
            $this->warn("\nDRY RUN COMPLETE - No changes made. Run without --dry-run to apply changes.");
        } else {
            $this->info("\nMIGRATION COMPLETE!");
        }

        return 0;
    }
};