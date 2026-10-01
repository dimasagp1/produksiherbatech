<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
    ];

    protected $casts = [
        'status_aktif' => 'boolean',
        'odoo_synced_at' => 'datetime',
        'safety_stock' => 'float',
        'min_stock' => 'float',
        'max_stock' => 'float',
    ];

    public function weeklyPlans()
    {
        return $this->hasMany(WeeklyPlan::class);
    }

    public function laporanHarians()
    {
        return $this->hasMany(LaporanHarian::class);
    }

    public function category()
    {
        return $this->belongsTo(ScmCategory::class, 'category_id');
    }

    public function uom()
    {
        return $this->belongsTo(ScmUom::class, 'uom_id');
    }

    public function inventoryStocks()
    {
        return $this->hasMany(InventoryStock::class);
    }

    public function scopeAktif($query)
    {
        return $query->where('status_aktif', true);
    }
}
