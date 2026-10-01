<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialUsageItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_usage_id',
        'produk_id',
        'material_name',
        'quantity_used',
        'quantity_standard',
        'variance',
        'uom_id',
    ];

    protected $casts = [
        'quantity_used' => 'float',
        'quantity_standard' => 'float',
        'variance' => 'float',
    ];

    public function usage()
    {
        return $this->belongsTo(MaterialUsage::class, 'material_usage_id');
    }

    public function produk()
    {
        return $this->belongsTo(Produk::class);
    }

    public function uom()
    {
        return $this->belongsTo(ScmUom::class, 'uom_id');
    }
}
