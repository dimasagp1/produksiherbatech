<?php

namespace App\Models;

use App\Services\MpsWeeklyPlanGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MpsPlan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'produk_id',
        'work_center_id',
        'month_year',
        'status',
        'notes',
        'beginning_stock_snapshot',
        'beginning_stock_date',
        'closed_at',
        'created_by',
    ];

    protected $casts = [
        'status' => 'string',
        'beginning_stock_snapshot' => 'decimal:3',
        'beginning_stock_date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MpsItem::class)->orderBy('tanggal')->orderBy('shift');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function weeklyPlans(): HasMany
    {
        return $this->hasMany(WeeklyPlan::class, 'mps_plan_id');
    }

    public function scopeForMonth($query, string $monthYear)
    {
        return $query->where('month_year', $monthYear);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function getBeginningStockOhAttribute(): float
    {
        if ($this->beginning_stock_snapshot !== null) {
            return (float) $this->beginning_stock_snapshot;
        }
        if (! $this->produk) {
            return 0;
        }

        return (float) $this->getLiveStockOh();
    }

    public function getLiveStockOh(): float
    {
        if (! $this->produk) {
            return 0;
        }

        return (float) $this->produk->inventoryStocks()->where('location', 'GUDANG-UTAMA')->sum('quantity');
    }

    public function isSnapshotStale(float $tolerance = 0.001): bool
    {
        if ($this->beginning_stock_snapshot === null) {
            return false;
        }

        return abs($this->getLiveStockOh() - (float) $this->beginning_stock_snapshot) > $tolerance;
    }

    public function snapshotBeginningStock(): float
    {
        $val = $this->getLiveStockOh();
        $this->update(['beginning_stock_snapshot' => $val, 'beginning_stock_date' => now()->toDateString()]);

        return $val;
    }

    /**
     * Generate WeeklyPlan drafts from MPS items
     */
    public function generateWeeklyPlans(): array
    {
        $generator = app(MpsWeeklyPlanGenerator::class);

        return $generator->generate($this);
    }
}
