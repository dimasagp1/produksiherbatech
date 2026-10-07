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
        'odoo_actual_qty',
        'odoo_variance_persen',
        'local_vs_odoo_variance_persen',
    ];

    protected $casts = [
        'quantity_used' => 'float',
        'quantity_standard' => 'float',
        'variance' => 'float',
        'odoo_actual_qty' => 'float',
        'odoo_variance_persen' => 'float',
        'local_vs_odoo_variance_persen' => 'float',
    ];

    protected $appends = [
        'ratio_persen',
    ];

    public function getRatioPersenAttribute(): ?float
    {
        if ($this->quantity_standard > 0) {
            return round((($this->quantity_used - $this->quantity_standard) / $this->quantity_standard) * 100, 2);
        }

        return null;
    }

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
};