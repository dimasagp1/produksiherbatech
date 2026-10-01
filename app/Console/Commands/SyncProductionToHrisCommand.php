<?php

namespace App\Console\Commands;

use App\Services\HrisProductionSyncService;
use Illuminate\Console\Command;

class SyncProductionToHrisCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hris:sync-production {--period= : Periode format YYYY-MM (default: bulan ini)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kirim metrik capaian produksi, OEE mesin & yield ke HRIS Sasaran Mutu';

    /**
     * Execute the console command.
     */
    public function handle(HrisProductionSyncService $syncService): int
    {
        $period = $this->option('period') ?: date('Y-m');

        $this->info('==========================================================');
        $this->info('🚀 SINKRONISASI METRIK PRODUKSI & MESIN KE HRIS SASARAN MUTU');
        $this->info('==========================================================');
        $this->line("Periode Target : {$period}");

        $result = $syncService->syncProductionMetricsToHris($period);

        if ($result['success']) {
            $this->info("\n✓ ".$result['message']);
            $this->line('Endpoint Target : '.$result['endpoint']);
            $this->table(
                ['Variabel Metrik', 'Nilai Terkirim'],
                collect($result['metrics_sent'])->map(fn ($v, $k) => [$k, is_numeric($v) ? $v : json_encode($v)])->toArray()
            );
            $this->info("\n🎉 Sinkronisasi selesai dengan sukses!");

            return self::SUCCESS;
        } else {
            $this->error("\n❌ ".$result['message']);
            if (! empty($result['error'])) {
                $this->line('Detail Error : '.$result['error']);
            }

            return self::FAILURE;
        }
    }
}
