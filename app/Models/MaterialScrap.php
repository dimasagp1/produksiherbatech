<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialScrap extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'batch_number',
        'weekly_plan_id',
        'produk_id',
        'material_name',
        'quantity',
        'uom_id',
        'source',
        'defect_reason',
        'odoo_scrap_id',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'float',
        'odoo_scrap_id' => 'integer',
    ];

    public function produk()
    {
        return $this->belongsTo(Produk::class);
    }

    public function weeklyPlan()
    {
        return $this->belongsTo(WeeklyPlan::class);
    }

    public function uom()
    {
        return $this->belongsTo(ScmUom::class, 'uom_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
