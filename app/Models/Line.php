<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Line extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'kode_line',
        'nama_line',
        'status_aktif',
        'default_work_center_id',
        'effective_ct_seconds',
        'can_run_work_centers',
    ];

    protected $casts = [
        'status_aktif' => 'boolean',
        'effective_ct_seconds' => 'integer',
        'can_run_work_centers' => 'array',
    ];

    public function defaultWorkCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'default_work_center_id');
    }

    public function laporanHarians(): HasMany
    {
        return $this->hasMany(LaporanHarian::class);
    }

    public function mpsItems(): HasMany
    {
        return $this->hasMany(MpsItem::class);
    }

    public function scopeAktif($query)
    {
        return $query->where('status_aktif', true);
    }

    /**
     * Check if this line can run a specific work center type
     */
    public function canRunWorkCenter(string $type): bool
    {
        if (empty($this->can_run_work_centers)) {
            return true; // No restriction = can run all
        }

        return in_array($type, $this->can_run_work_centers);
    }

    /**
     * Get effective CT for this line (override or default from WorkCenter)
     */
    public function getEffectiveCtAttribute(): ?int
    {
        if ($this->effective_ct_seconds) {
            return $this->effective_ct_seconds;
        }

        return $this->defaultWorkCenter?->standard_ct_seconds;
    }
}
