<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BomItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'bom_id',
        'material_produk_id',
        'material_name',
        'quantity',
        'uom_id',
    ];

    protected $casts = [
        'quantity' => 'float',
    ];

    public function bom()
    {
        return $this->belongsTo(Bom::class);
    }

    public function materialProduk()
    {
        return $this->belongsTo(Produk::class, 'material_produk_id');
    }

    public function uom()
    {
        return $this->belongsTo(ScmUom::class, 'uom_id');
    }
}
