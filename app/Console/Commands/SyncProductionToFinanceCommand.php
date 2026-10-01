<?php

namespace App\Console\Commands;

use App\Services\FinanceProductionSyncService;
use Illuminate\Console\Command;

class SyncProductionToFinanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:sync-production {--period= : Periode format YYYY-MM (default: bulan ini)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kirim data feed capaian produksi (output, target, jam mesin, downtime, reject/loss, SO variance) ke Finance Monitoring';

    /**
     * Execute the console command.
     */
    public function handle(FinanceProductionSyncService $syncService): int
    {
        $period = $this->option('period') ?: date('Y-m');

        $this->info('==========================================================');
        $this->info('🚀 SINKRONISASI DATA FEED PRODUKSI KE FINANCE MONITORING');
        $this->info('==========================================================');
        $this->line("Periode Target : {$period}");

        $result = $syncService->pushToFinance($period);

        if ($result['success']) {
            $this->info("\n✓ ".$result['message']);
            $this->info('Status : Sukses dikirim ke Finance Monitoring');
            $this->info('Generated At: '.now()->toDateTimeString());

            return self::SUCCESS;
        } else {
            $this->error("\n❌ ".$result['message']);

            return self::FAILURE;
        }
    }
}
