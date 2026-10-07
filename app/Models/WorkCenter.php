<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkCenter extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'type',
        'standard_ct_seconds',
        'fit_mp',
        'shift_hours',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'standard_ct_seconds' => 'integer',
        'fit_mp' => 'integer',
        'shift_hours' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function mesins(): HasMany
    {
        return $this->hasMany(Mesin::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(Line::class);
    }

    public function weeklyPlans(): HasMany
    {
        return $this->hasMany(WeeklyPlan::class);
    }

    public function laporanHarians(): HasMany
    {
        return $this->hasMany(LaporanHarian::class);
    }

    public function produks(): HasMany
    {
        return $this->hasMany(Produk::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getOutputPerMinuteAttribute(): float
    {
        if ($this->standard_ct_seconds <= 0) {
            return 0;
        }
        return round(60 / $this->standard_ct_seconds, 4);
    }

    public function getTargetOutputPerShiftAttribute(): int
    {
        if ($this->standard_ct_seconds <= 0 || $this->fit_mp <= 0) {
            return 0;
        }
        $outputPerMinute = 60 / $this->standard_ct_seconds;
        $minutesPerShift = $this->shift_hours * 60;
        return (int) round($outputPerMinute * $minutesPerShift);
    }

    public function getTargetOutputPerPersonAttribute(): float
    {
        $total = $this->target_output_per_shift;
        if ($total <= 0 || $this->fit_mp <= 0) {
            return 0;
        }
        return round($total / $this->fit_mp, 2);
    }
};