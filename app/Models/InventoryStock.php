<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
    ];

    protected $casts = [
        'quantity' => 'float',
        'expired_date' => 'date',
    ];

    public function produk()
    {
        return $this->belongsTo(Produk::class);
    }

    public function scopeForLocation($query)
    {
        return $query->where('location', $location);
    }
}
