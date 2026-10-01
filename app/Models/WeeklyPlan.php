<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WeeklyPlan extends Model
{
    use HasFactory, SoftDeletes;

    public const MO_STATUS_PENDING = 'pending';

    public const MO_STATUS_CONFIRMED = 'confirmed';

    public const MO_STATUS_IN_PROGRESS = 'in_progress';

    public const MO_STATUS_DONE = 'done';

    public const MO_STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'produk_id',
        'line_id',
        'proses',
        'batch_number',
        'odoo_mo_id',
        'mo_status',
        'target_output',
        'mp_count',
        'multiplier',
        'packing_hold',
        'tanggal',
        'status',
        'created_by',
    ];

    protected $casts = [
        'status' => 'string',
        'mo_status' => 'string',
        'odoo_mo_id' => 'integer',
        'target_output' => 'integer',
        'mp_count' => 'integer',
        'multiplier' => 'integer',
        'packing_hold' => 'boolean',
    ];

    public function produk()
    {
        return $this->belongsTo(Produk::class);
    }

    public function line()
    {
        return $this->belongsTo(Line::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function laporanHarians()
    {
        return $this->hasMany(LaporanHarian::class);
    }

    public function scopeAktif($query)
    {
        return $query->where('status', 'aktif');
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('tanggal', $date);
    }

    public function scopeForProduk($query, $produkId)
    {
        return $query->where('produk_id', $produkId);
    }

    public function scopeBoardVisible($query)
    {
        return $query->where('status', '!=', 'selesai')
            ->where('mo_status', '!=', self::MO_STATUS_CANCELLED);
    }

    /**
     * Apply sync data from Odoo MO (system path — bypasses human-edit limits).
     */
    public function applyOdooSync(array $data): void
    {
        $allowed = array_intersect_key($data, array_flip([
            'odoo_mo_id',
            'mo_status',
            'target_output',
            'mp_count',
            'multiplier',
            'tanggal',
            'batch_number',
            'produk_id',
        ]));

        if ($allowed !== []) {
            $this->fill($allowed)->save();
        }
    }

    public function computeTargetOutput(?int $defaultMultiplier = null): int
    {
        if ($this->target_output > 0) {
            return (int) $this->target_output;
        }

        $multiplier = $this->multiplier ?: ($defaultMultiplier ?? (int) Setting::get('target_output_multiplier', 2000));

        return (int) $this->mp_count * (int) $multiplier;
    }

    /**
     * Process order: mixing → filling → packing
     */
    private static array $processOrder = ['mixing' => 0, 'filling' => 1, 'packing' => 2];

    /**
     * Find the next process weekly plan for same product + same date
     */
    public static function getNextProcess($produkId, $tanggal, $currentProses): ?self
    {
        $currentOrder = self::$processOrder[$currentProses] ?? -1;

        return self::where('produk_id', $produkId)
            ->where('tanggal', $tanggal)
            ->where('status', 'aktif')
            ->get()
            ->filter(fn ($wp) => (self::$processOrder[$wp->proses] ?? 99) > $currentOrder)
            ->sortBy(fn ($wp) => self::$processOrder[$wp->proses] ?? 99)
            ->first();
    }

    public static function processOrderMap(): array
    {
        return self::$processOrder;
    }

    /**
     * Check if a weekly plan already has a laporan
     */
    public function hasLaporan(): bool
    {
        return $this->laporanHarians()->exists();
    }
}
