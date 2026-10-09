<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class MpsItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'mps_plan_id',
        'tanggal',
        'line_id',
        'work_center_id',
        'shift',
        'mp_count',
        'target_qty',
        'cleaning',
        'adjusted_qty',
        'gap_reason',
        'notes',
    ];

    protected $casts = [
        'mp_count' => 'integer',
        'target_qty' => 'integer',
        'cleaning' => 'boolean',
        'adjusted_qty' => 'integer',
    ];

    protected function tanggal(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->format('Y-m-d') : null,
            set: fn ($value) => $value ? Carbon::parse($value)->format('Y-m-d') : null,
        );
    }

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MpsPlan::class);
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('tanggal', $date);
    }

    public function scopeForLine($query, $lineId)
    {
        return $query->where('line_id', $lineId);
    }

    public function scopeForShift($query, $shift)
    {
        return $query->where('shift', $shift);
    }

    public function scopeNotCleaning($query)
    {
        return $query->where('cleaning', false);
    }

    public function isSaturday(): bool
    {
        return Carbon::parse($this->tanggal)->isSaturday();
    }

    /**
     * Compute target quantity based on Work Center CT, shift hours, and MP count
     */
    public function computeTargetQty(bool $force = false): int
    {
        if ($this->target_qty > 0 && ! $force) {
            return $this->target_qty;
        }

        $wc = $this->workCenter ?? $this->plan->workCenter;
        if (! $wc || $wc->standard_ct_seconds <= 0 || $this->mp_count <= 0) {
            return 0;
        }

        $hours = $this->isSaturday() ? $wc->saturday_shift_hours : $wc->shift_hours;
        $outputPerMinute = 60 / $wc->standard_ct_seconds;

        return (int) round($outputPerMinute * $hours * 60 * $this->mp_count);
    }

    /**
     * Auto-calculate and save target_qty
     */
    public function recalculateTarget(): self
    {
        $this->target_qty = $this->computeTargetQty(true);
        $this->save();

        return $this;
    }

    /**
     * Get gap qty (adjusted - target)
     */
    public function getGapQtyAttribute(): ?int
    {
        if ($this->adjusted_qty === null) {
            return null;
        }

        return $this->adjusted_qty - $this->target_qty;
    }
}
