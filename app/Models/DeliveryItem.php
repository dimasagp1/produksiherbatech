<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_plan_id',
        'produk_id',
        'product_name',
        'quantity',
        'uom_name',
        'so_line_id',
    ];

    protected $casts = [
        'quantity' => 'float',
    ];

    public function plan()
    {
        return $this->belongsTo(DeliveryPlan::class, 'delivery_plan_id');
    }

    public function produk()
    {
        return $this->belongsTo(Produk::class);
    }
}
