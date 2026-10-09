<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MrpCalculation extends Model
{
    use HasFactory;

    protected $fillable = [
        'mps_plan_id',
        'produk_id',
        'material_produk_id',
        'material_name',
        'needed_qty',
        'needed_uom',
        'beginning_stock',
        'incoming_qty',
        'available_qty',
        'shortage_qty',
        'rop',
        'status',
        'calculated_at',
    ];

    protected $casts = [
        'needed_qty' => 'float',
        'beginning_stock' => 'float',
        'incoming_qty' => 'float',
        'available_qty' => 'float',
        'shortage_qty' => 'float',
        'rop' => 'float',
        'calculated_at' => 'datetime',
    ];

    public function mpsPlan(): BelongsTo
    {
        return $this->belongsTo(MpsPlan::class, 'mps_plan_id');
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    public function materialProduk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'material_produk_id');
    }

    public function scopeCritical($q)
    {
        return $q->where('status', 'critical');
    }

    public function scopeWarning($q)
    {
        return $q->where('status', 'warning');
    }

    public function scopeOk($q)
    {
        return $q->where('status', 'ok');
    }
}
