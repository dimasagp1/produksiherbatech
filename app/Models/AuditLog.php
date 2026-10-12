<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['auditable_type', 'auditable_id', 'event', 'old_values', 'new_values', 'user_id', 'reason', 'ip', 'context'];

    protected $casts = ['old_values' => 'array', 'new_values' => 'array', 'context' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \RuntimeException('AuditLog is append-only'));
        static::deleting(fn () => throw new \RuntimeException('AuditLog is append-only'));
    }
}
