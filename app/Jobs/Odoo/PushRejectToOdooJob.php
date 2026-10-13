<?php

namespace App\Jobs\Odoo;

use App\Models\OdooSyncJob;
use App\Models\RejectDetail;
use App\Services\OdooService;
use Illuminate\Support\Facades\Log;

class PushRejectToOdooJob extends PushToOdooJob
{
    protected function odooModel(): ?string
    {
        return 'stock.scrap';
    }

    protected function perform(OdooSyncJob $job, OdooService $odoo): array
    {
        $payload = $job->payload ?? [];
        $rejectDetailId = $payload['reject_detail_id'] ?? $job->entity_id;

        if (! $odoo->isConfigured()) {
            return [
                'skipped' => true,
                'message' => 'Odoo tidak terkonfigurasi, job dilewati.',
            ];
        }

        $reject = RejectDetail::with('laporanHarian.produk')->find($rejectDetailId);
        if (! $reject) {
            return [
                'skipped' => true,
                'message' => "RejectDetail #{$rejectDetailId} tidak ditemukan.",
            ];
        }

        if ($reject->odoo_scrap_id) {
            return [
                'skipped' => true,
                'odoo_res_id' => $reject->odoo_scrap_id,
                'message' => 'Reject sudah pernah dipush ke Odoo Scrap.',
            ];
        }

        try {
            $result = $odoo->pushRejectToOdoo($reject);

            return [
                'success' => $result['success'] ?? false,
                'odoo_res_id' => $result['odoo_scrap_id'] ?? null,
                'response' => $result,
            ];
        } catch (\Throwable $e) {
            Log::error("PushRejectToOdooJob failed for Reject #{$rejectDetailId}: ".$e->getMessage());
            throw $e;
        }
    }
}
