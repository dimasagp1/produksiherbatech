<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockOpnameItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_opname_id',
        'produk_id',
        'batch_number',
        'system_qty',
        'counted_qty',
        'discrepancy',
        'uom_id',
        'stelling_card',
        'discrepancy_reason',
    ];

    protected $casts = [
        'system_qty' => 'float',
        'counted_qty' => 'float',
        'discrepancy' => 'float',
    ];

    public function opname()
    {
        return $this->belongsTo(StockOpname::class, 'stock_opname_id');
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
