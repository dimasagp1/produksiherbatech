<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mesin extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nama_mesin',
        'ct',
        'status_aktif',
        'work_center_id',
    ];

    protected $casts = [
        'ct' => 'float',
        'status_aktif' => 'boolean',
    ];

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    public function laporanHarians(): HasMany
    {
        return $this->hasMany(LaporanHarian::class);
    }

    public function scopeAktif($query)
    {
        return $query->where('status_aktif', true);
    }

    public function getEffectiveCtAttribute(): float
    {
        return $this->workCenter?->standard_ct_seconds > 0
            ? $this->workCenter->standard_ct_seconds
            : $this->ct;
    }
};