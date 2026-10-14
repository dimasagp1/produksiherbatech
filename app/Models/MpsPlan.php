<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Services\MpsWeeklyPlanGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class MpsPlan extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'produk_id',
        'work_center_id',
        'batch_number',
        'month_year',
        'revision',
        'is_active_revision',
        'parent_plan_id',
        'status',
        'notes',
        'beginning_stock_snapshot',
        'beginning_stock_date',
        'closed_at',
        'created_by',
    ];

    protected $casts = [
        'status' => 'string',
        'revision' => 'integer',
        'is_active_revision' => 'boolean',
        'parent_plan_id' => 'integer',
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

    public function parentPlan(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_plan_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_plan_id')->orderBy('revision', 'asc');
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

    public function scopeActiveRevision($query)
    {
        return $query->where('is_active_revision', true);
    }

    /**
     * Create a new draft revision from this approved plan
     */
    public function createNextRevision(?int $userId = null): self
    {
        return DB::transaction(function () use ($userId) {
            $rootPlanId = $this->parent_plan_id ?: $this->id;

            $maxRev = (int) self::where(function ($q) use ($rootPlanId) {
                $q->where('id', $rootPlanId)
                    ->orWhere('parent_plan_id', $rootPlanId);
            })->max('revision');

            $newRevisionNumber = $maxRev + 1;

            $newPlan = self::create([
                'produk_id' => $this->produk_id,
                'work_center_id' => $this->work_center_id,
                'batch_number' => $this->batch_number,
                'month_year' => $this->month_year,
                'revision' => $newRevisionNumber,
                'is_active_revision' => false,
                'parent_plan_id' => $rootPlanId,
                'status' => 'draft',
                'notes' => 'Revisi '.$newRevisionNumber.' dari Plan #'.$this->id,
                'created_by' => $userId ?? auth()->id() ?? $this->created_by,
                'beginning_stock_snapshot' => $this->beginning_stock_snapshot,
                'beginning_stock_date' => $this->beginning_stock_date,
            ]);

            foreach ($this->items as $item) {
                $newItemData = $item->only([
                    'tanggal',
                    'line_id',
                    'work_center_id',
                    'shift',
                    'mp_count',
                    'target_qty',
                    'cleaning',
                    'adjusted_qty',
                    'gap_reason',
                    'notes',
                ]);
                $newItemData['mps_plan_id'] = $newPlan->id;
                MpsItem::create($newItemData);
            }

            return $newPlan;
        });
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
