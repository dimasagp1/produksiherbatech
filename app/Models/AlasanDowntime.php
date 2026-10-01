<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AlasanDowntime extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nama_alasan',
        'tipe_input',
        'durasi_default_menit',
        'status_aktif',
    ];

    protected $casts = [
        'durasi_default_menit' => 'integer',
        'status_aktif' => 'boolean',
    ];

    public function downtimeDetails()
    {
        return $this->hasMany(DowntimeDetail::class);
    }

    public function scopeAktif($query)
    {
        return $query->where('status_aktif', true);
    }

    public function scopeManual($query)
    {
        return $query->where('tipe_input', 'manual');
    }

    public function scopeDefaultHardcode($query)
    {
        return $query->where('tipe_input', 'default_hardcode');
    }
}
