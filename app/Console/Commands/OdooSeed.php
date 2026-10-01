<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Odoo\OdooPythonRunner;
use App\Services\Odoo\OdooSeedPayloadBuilder;
use App\Services\OdooService;
use Illuminate\Console\Command;

class OdooSeed extends Command
{
    protected $signature = 'odoo:seed
        {--only=all : Scope seed (all|master|transactions)}
        {--dry-run : Lookup + rencana saja tanpa create}
        {--force : Re-create record seed lama}
        {--skip-connection-check : Lewati testConnection Odoo}';

    protected $description = 'Seed data demo/integrasi ke Odoo Online (hybrid PHP → scripts/odoo/odoo_seed.py)';

    public function handle(OdooSeedPayloadBuilder $builder, OdooPythonRunner $runner, OdooService $odoo): int
    {
        $only = (string) $this->option('only');
        if (! in_array($only, ['all', 'master', 'transactions'], true)) {
            $this->error("--only harus all|master|transactions (dapat: {$only})");

            return self::INVALID;
        }

        $enabled = filter_var(
            env('ODOO_SEED_ENABLED', Setting::get('odoo_seed_enabled', '0')),
            FILTER_VALIDATE_BOOLEAN
        );
        if (! $enabled) {
            $this->error('Seeder Odoo dinonaktifkan. Set ODOO_SEED_ENABLED=1 (env) atau update setting odoo_seed_enabled=1.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $payload = $builder->stdinPayload($only, $dryRun, $force);

        $missing = array_filter([
            'host' => $payload['host'],
            'db' => $payload['db'],
            'username' => $payload['username'],
            'api_key' => $payload['api_key'],
        ], fn ($v) => $v === '' || $v === null);
        if ($missing !== []) {
            $this->error('Kredensial Odoo belum lengkap di .env / Settings: '.implode(', ', array_keys($missing)));

            return self::FAILURE;
        }

        if (! $runner->scriptExists()) {
            $this->error('scripts/odoo/odoo_seed.py tidak ditemukan.');

            return self::FAILURE;
        }

        if (! $this->option('skip-connection-check')) {
            $this->line('Test connection Odoo...');
            $conn = $odoo->testConnection();
            if (! ($conn['success'] ?? false)) {
                $this->error('Koneksi Odoo gagal: '.($conn['message'] ?? 'unknown'));

                return self::FAILURE;
            }
            $this->info($conn['message']);
        }

        $dataset = $builder->dataset();
        $this->line(sprintf(
            'Dataset: %d partner, %d produk, %d BOM, %d MO, %d scrap, %d SO · only=%s%s%s',
            count($dataset['partners']),
            count($dataset['products']),
            count($dataset['boms']),
            count($dataset['manufacturing_orders']),
            count($dataset['scraps']),
            count($dataset['sale_orders']),
            $only,
            $dryRun ? ' · DRY-RUN' : '',
            $force ? ' · FORCE' : '',
        ));

        $this->line('Menjalankan odoo_seed.py (API key via stdin, tidak di argv)...');

        try {
            $summary = $runner->run($payload);
        } catch (\Throwable $e) {
            $this->error('Seed gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Seed selesai.'.($dryRun ? ' (dry-run — tidak ada create)' : ''));
        if (! empty($summary['server_version'])) {
            $this->line('Odoo server: '.$summary['server_version']);
        }

        $rows = [];
        foreach (($summary['results'] ?? []) as $r) {
            $rows[] = [
                $r['model'] ?? '-',
                $r['created'] ?? 0,
                $r['updated'] ?? 0,
                $r['skipped'] ?? 0,
                implode('; ', array_slice($r['errors'] ?? [], 0, 3)),
            ];
        }
        if ($rows !== []) {
            $this->table(['Model', 'Created', 'Updated', 'Skipped', 'Errors'], $rows);
        }

        $this->newLine();
        $this->line('Langkah berikutnya (LinePulse):');
        $this->line('  1. Admin → Odoo → Sync Products (pastikan HB-001..PM-003 + odoo_id terisi)');
        $this->line('  2. PPIC → Sync Odoo MO (BATCH-001 draft, BATCH-002 in_progress, BATCH-003 purged)');
        $this->line('  3. SCM → Scrap Material → Pull Odoo');
        $this->line('  4. SCM → Delivery → Sync SO Odoo');

        return self::SUCCESS;
    }
}
