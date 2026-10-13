<?php

namespace App\Jobs\Odoo;

use App\Models\OdooSyncJob;
use App\Services\OdooService;
use Illuminate\Support\Facades\Log;

class PushBatchToOdooJob extends PushToOdooJob
{
    protected function odooModel(): ?string
    {
        return 'mrp.production';
    }

    protected function perform(OdooSyncJob $job, OdooService $odoo): array
    {
        $payload = $job->payload ?? [];
        $odooMoId = $payload['odoo_mo_id'] ?? null;
        $outputFisik = (float) ($payload['output_fisik'] ?? 0);

        if (! $odoo->isConfigured()) {
            return [
                'skipped' => true,
                'message' => 'Odoo tidak terkonfigurasi, job dilewati.',
            ];
        }

        if (! $odooMoId) {
            return [
                'skipped' => true,
                'message' => 'Tidak ada keterkaitan dengan Odoo MO ID.',
            ];
        }

        try {
            $mo = $odoo->searchRead('mrp.production', [['id', '=', $odooMoId]], ['id', 'state', 'qty_producing', 'product_qty'], 1);
            if (empty($mo)) {
                return [
                    'skipped' => true,
                    'message' => "MO #{$odooMoId} tidak ditemukan di Odoo.",
                ];
            }

            // Update qty_producing pada MO di Odoo
            $updateValues = [
                'qty_producing' => $outputFisik,
            ];

            $odoo->executeKw('mrp.production', 'write', [[$odooMoId], $updateValues]);

            return [
                'success' => true,
                'odoo_res_id' => $odooMoId,
                'message' => "Berhasil mencatat output {$outputFisik} ke MO #{$odooMoId} Odoo.",
            ];
        } catch (\Throwable $e) {
            Log::error("PushBatchToOdooJob failed for MO #{$odooMoId}: ".$e->getMessage());
            throw $e;
        }
    }
}
