<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LaporanHarian extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'weekly_plan_id',
        'produk_id',
        'proses',
        'batch_number',
        'mesin_id',
        'ct',
        'line_id',
        'tanggal',
        'shift',
        'target_mp',
        'total_mp',
        'start_time',
        'start_time_at',
        'end_time',
        'gross_time_menit',
        'capacity_fisik',
        'output_fisik',
        'waktu_bersih_menit',
        'target_teoritis',
        'yield_persen',
        'availability_persen',
        'performance_persen',
        'oee_persen',
        'produktivitas_persen',
        'status',
        'locked_by',
        'locked_at',
        'timer_status',
        'pause_started_at',
        'total_pause_menit',
    ];

    protected $casts = [
        'ct' => 'float',
        'target_mp' => 'float',
        'total_mp' => 'integer',
        'gross_time_menit' => 'integer',
        'capacity_fisik' => 'integer',
        'output_fisik' => 'integer',
        'waktu_bersih_menit' => 'integer',
        'target_teoritis' => 'float',
        'yield_persen' => 'float',
        'availability_persen' => 'float',
        'performance_persen' => 'float',
        'oee_persen' => 'float',
        'produktivitas_persen' => 'float',
        'tanggal' => 'date',
        'shift' => 'string',
        'locked_at' => 'datetime',
        'timer_status' => 'string',
        'pause_started_at' => 'datetime',
        'start_time_at' => 'datetime',
        'total_pause_menit' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function weeklyPlan()
    {
        return $this->belongsTo(WeeklyPlan::class);
    }

    public function produk()
    {
        return $this->belongsTo(Produk::class);
    }

    public function mesin()
    {
        return $this->belongsTo(Mesin::class);
    }

    public function line()
    {
        return $this->belongsTo(Line::class);
    }

    public function locker()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function downtimeDetails()
    {
        return $this->hasMany(DowntimeDetail::class);
    }

    public function rejectDetails()
    {
        return $this->hasMany(RejectDetail::class);
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopeLocked($query)
    {
        return $query->where('status', 'locked');
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('tanggal', $date);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForLine($query, $lineId)
    {
        return $query->where('line_id', $lineId);
    }

    public function scopeForProduk($query, $produkId)
    {
        return $query->where('produk_id', $produkId);
    }

    public function scopeForProses($query, $proses)
    {
        return $query->where('proses', $proses);
    }

    public function scopeForBatch($query, $batchNumber)
    {
        return $query->where('batch_number', $batchNumber);
    }

    public function scopeForShift($query, ?string $shift)
    {
        return $query->when(in_array($shift, ['shift1', 'shift2'], true), fn ($q) => $q->where('shift', $shift));
    }

    public function scopeForMonth($query, $year, $month)
    {
        return $query->whereYear('tanggal', $year)
            ->whereMonth('tanggal', $month);
    }
}
