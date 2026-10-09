<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Produk extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'odoo_id',
        'kode_produk',
        'nama_produk',
        'proses_default',
        'category_id',
        'uom_id',
        'safety_stock',
        'min_stock',
        'max_stock',
        'item_type',
        'odoo_uom',
        'status_aktif',
        'odoo_synced_at',
        'ct_seconds',
        'work_center_id',
        'rop',
        'lead_time_days',
        'safety_stock_days',
    ];

    protected $casts = [
        'status_aktif' => 'boolean',
        'odoo_synced_at' => 'datetime',
        'safety_stock' => 'float',
        'min_stock' => 'float',
        'max_stock' => 'float',
        'ct_seconds' => 'integer',
        'rop' => 'float',
        'lead_time_days' => 'integer',
        'safety_stock_days' => 'integer',
    ];

    public function weeklyPlans(): HasMany
    {
        return $this->hasMany(WeeklyPlan::class);
    }

    public function laporanHarians(): HasMany
    {
        return $this->hasMany(LaporanHarian::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ScmCategory::class, 'category_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(ScmUom::class, 'uom_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    public function inventoryStocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }

    public function scopeAktif($query)
    {
        return $query->where('status_aktif', true);
    }

    public function getEffectiveCtAttribute(): int
    {
        return $this->ct_seconds ?? $this->workCenter?->standard_ct_seconds ?? 0;
    }
}
