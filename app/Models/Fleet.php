<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fleet extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'kode_armada',
        'nama_armada',
        'type',
        'plat_number',
        'driver_name',
        'driver_phone',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
