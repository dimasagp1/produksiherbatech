<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class MaterialSupply extends Model
{
    use HasFactory;

    protected $fillable = ['supply_number', 'weekly_plan_id', 'work_center_id', 'required_date', 'status', 'delivered_at', 'delivered_by', 'created_by', 'notes'];

    protected $casts = ['required_date' => 'date', 'delivered_at' => 'datetime'];

    public function weeklyPlan(): BelongsTo
    {
        return $this->belongsTo(WeeklyPlan::class);
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliverer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialSupplyItem::class);
    }

    public static function nextNumber(): string
    {
        $d = Carbon::now()->format('Ymd');
        $seq = static::where('supply_number', 'like', "SUP-{$d}-%")->count() + 1;

        return sprintf('SUP-%s-%04d', $d, $seq);
    }

    public function isLate(): bool
    {
        if (! $this->delivered_at) {
            return Carbon::parse($this->required_date)->isPast() && $this->status === 'pending';
        }

        return Carbon::parse($this->delivered_at)->gt(Carbon::parse($this->required_date)->endOfDay());
    }

    public function hMinus1(): Carbon
    {
        return Carbon::parse($this->required_date)->subDay();
    }
}
