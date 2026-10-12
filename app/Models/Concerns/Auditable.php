<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(fn ($m) => $m->recordAudit('created', [], $m->getAttributes()));
        static::updated(function ($m) {
            $changes = $m->getChanges();
            unset($changes['updated_at']);
            if ($changes === []) {
                return;
            }
            $old = array_intersect_key($m->getOriginal(), $changes);
            $m->recordAudit('updated', $old, $changes);
        });
        static::deleted(fn ($m) => $m->recordAudit('deleted', $m->getOriginal(), []));
    }

    protected function recordAudit(string $event, array $old, array $new): void
    {
        AuditLog::create([
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'event' => $event,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'user_id' => auth()->id(),
            'reason' => Request::input('audit_reason'),
            'ip' => Request::ip(),
            'context' => ['url' => Request::fullUrl()],
            'created_at' => now(),
        ]);
    }
}
