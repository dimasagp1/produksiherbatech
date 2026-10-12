<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialSupplyItem extends Model
{
    use HasFactory;

    protected $fillable = ['material_supply_id', 'produk_id', 'required_qty', 'delivered_qty', 'uom_id'];

    protected $casts = ['required_qty' => 'float', 'delivered_qty' => 'float', 'variance_qty' => 'float'];

    public function supply(): BelongsTo
    {
        return $this->belongsTo(MaterialSupply::class, 'material_supply_id');
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(ScmUom::class, 'uom_id');
    }

    public function getIsShortAttribute(): bool
    {
        return $this->delivered_qty < $this->required_qty;
    }
}
