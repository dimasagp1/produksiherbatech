<?php

namespace App\Console\Commands;

use App\Services\OdooService;
use Illuminate\Console\Command;

class OdooTestConnection extends Command
{
    protected $signature = 'odoo:test-connection';

    protected $description = 'Tes koneksi dan otentikasi ke Odoo ERP';

    public function handle(OdooService $odooService): int
    {
        $this->info('Menguji koneksi ke Odoo ERP...');
        $this->line('Host: '.config('odoo.host'));
        $this->line('Database: '.config('odoo.db'));
        $this->line('User: '.config('odoo.username'));

        $result = $odooService->testConnection();

        if ($result['success']) {
            $this->info('✓ '.$result['message']);

            return Command::SUCCESS;
        } else {
            $this->error('✗ '.$result['message']);

            return Command::FAILURE;
        }
    }
}
