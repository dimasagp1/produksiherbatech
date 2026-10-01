<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockOpname extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'opname_number',
        'status',
        'location',
        'initiated_by',
        'approved_by',
        'initiated_at',
        'approved_at',
        'ira_persen',
        'discrepancy_value_rate',
        'notes',
    ];

    protected $casts = [
        'ira_persen' => 'float',
        'discrepancy_value_rate' => 'float',
        'initiated_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(StockOpnameItem::class);
    }

    public function initiator()
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
