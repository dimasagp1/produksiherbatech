<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RejectDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'odoo_scrap_id',
        'laporan_harian_id',
        'jenis_reject',
        'jumlah',
        'keterangan',
        'created_by',
        'odoo_synced_at',
    ];

    protected $casts = [
        'jumlah' => 'integer',
        'odoo_synced_at' => 'datetime',
    ];

    public function laporanHarian()
    {
        return $this->belongsTo(LaporanHarian::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
