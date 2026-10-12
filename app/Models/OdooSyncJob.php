<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OdooSyncJob extends Model
{
    protected $fillable = [
        'idempotency_key', 'entity_type', 'entity_id', 'operation', 'payload',
        'status', 'attempts', 'last_error', 'odoo_model', 'odoo_res_id',
        'request_body', 'response_body', 'next_attempt_at', 'processed_at', 'created_by',
    ];

    protected $casts = [
        'payload' => 'array',
        'request_body' => 'array',
        'response_body' => 'array',
        'next_attempt_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
