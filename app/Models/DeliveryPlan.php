<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryPlan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'delivery_number',
        'odoo_so_id',
        'customer_name',
        'planned_date',
        'status',
        'fleet_id',
        'driver_name',
        'revenue_coa',
        'on_time',
        'in_full',
        'damage_free',
        'doc_accuracy',
        'complaint',
        'actual_delivery_date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'planned_date' => 'date',
        'actual_delivery_date' => 'date',
        'on_time' => 'boolean',
        'in_full' => 'boolean',
        'damage_free' => 'boolean',
        'doc_accuracy' => 'boolean',
        'complaint' => 'boolean',
    ];

    public function items()
    {
        return $this->hasMany(DeliveryItem::class);
    }

    public function fleet()
    {
        return $this->belongsTo(Fleet::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
