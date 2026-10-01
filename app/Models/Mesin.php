<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mesin extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nama_mesin',
        'ct',
        'status_aktif',
    ];

    protected $casts = [
        'ct' => 'float',
        'status_aktif' => 'boolean',
    ];

    public function laporanHarians()
    {
        return $this->hasMany(LaporanHarian::class);
    }

    public function scopeAktif($query)
    {
        return $query->where('status_aktif', true);
    }
}
