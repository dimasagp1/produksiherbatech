<?php

namespace App\Console\Commands;

use App\Services\OdooService;
use Illuminate\Console\Command;

class OdooPing extends Command
{
    protected $signature = 'odoo:ping';

    protected $description = 'Ping Odoo common.version';

    public function handle(OdooService $odoo): int
    {
        try {
            $res = $odoo->ping();
            $this->info('Odoo OK: '.($res['version'] ?? 'unknown'));

            return 0;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return 1;
        }
    }
}
