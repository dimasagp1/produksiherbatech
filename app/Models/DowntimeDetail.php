<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DowntimeDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'laporan_harian_id',
        'alasan_downtime_id',
        'durasi_menit',
        'keterangan',
    ];

    protected $casts = [
        'durasi_menit' => 'float',
    ];

    public function laporanHarian()
    {
        return $this->belongsTo(LaporanHarian::class);
    }

    public function alasanDowntime()
    {
        return $this->belongsTo(AlasanDowntime::class);
    }
}
