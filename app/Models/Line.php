<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Line extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'kode_line',
        'nama_line',
        'status_aktif',
    ];

    protected $casts = [
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
