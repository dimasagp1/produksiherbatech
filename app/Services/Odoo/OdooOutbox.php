<?php

namespace App\Services\Odoo;

use App\Models\OdooSyncJob;
use Illuminate\Support\Facades\DB;

class OdooOutbox
{
    public static function enqueue(string $entity, int|string $id, string $operation, array $payload = [], ?string $odooModel = null, int $version = 1): OdooSyncJob
    {
        $key = "{$entity}:{$id}:{$operation}:{$version}";

        return DB::transaction(function () use ($key, $entity, $id, $operation, $payload, $odooModel) {
            $existing = OdooSyncJob::where('idempotency_key', $key)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            return OdooSyncJob::create([
                'idempotency_key' => $key,
                'entity_type' => $entity,
                'entity_id' => $id,
                'operation' => $operation,
                'payload' => $payload,
                'status' => 'pending',
                'odoo_model' => $odooModel,
                'created_by' => auth()->id(),
            ]);
        });
    }
}
