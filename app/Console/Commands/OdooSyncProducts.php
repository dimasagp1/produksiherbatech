<?php

namespace App\Console\Commands;

use App\Services\OdooService;
use Illuminate\Console\Command;

class OdooSyncProducts extends Command
{
    protected $signature = 'odoo:sync-products';

    protected $description = 'Sinkronisasi master produk dari Odoo ERP ke database lokal';

    public function handle(OdooService $odooService): int
    {
        $this->info('Memulai sinkronisasi master produk dari Odoo...');

        try {
            $stats = $odooService->syncProducts();
            $this->info('✓ Sinkronisasi selesai!');
            $this->table(
                ['Total dari Odoo', 'Produk Baru Dibuat', 'Produk Diperbarui'],
                [[$stats['total_from_odoo'], $stats['created'], $stats['updated']]]
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('✗ Gagal sinkronisasi produk: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
