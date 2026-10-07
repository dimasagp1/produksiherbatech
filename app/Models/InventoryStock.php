<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryStock extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'produk_id',
        'quantity',
        'batch_number',
        'expired_date',
        'location',
        'beginning_stock_monthly',
        'snapshot_date',
        'is_baseline',
    ];

    protected $casts = [
        'quantity' => 'float',
        'expired_date' => 'date',
        'beginning_stock_monthly' => 'float',
        'snapshot_date' => 'date',
        'is_baseline' => 'boolean',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }

    public function scopeForLocation($query, $location)
    {
        return $query->where('location', $location);
    }

    public function scopeBaseline($query)
    {
        return $query->where('is_baseline', true);
    }

    public function scopeForSnapshotDate($query, $date)
    {
        return $query->where('snapshot_date', $date);
    }
};