<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialUsage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'usage_number',
        'weekly_plan_id',
        'user_id',
        'usage_date',
        'shift',
        'notes',
    ];

    protected $casts = [
        'usage_date' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(MaterialUsageItem::class);
    }

    public function weeklyPlan()
    {
        return $this->belongsTo(WeeklyPlan::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
