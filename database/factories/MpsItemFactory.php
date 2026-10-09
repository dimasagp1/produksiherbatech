<?php

namespace Database\Factories;

use App\Models\Line;
use App\Models\MpsItem;
use App\Models\MpsPlan;
use App\Models\WorkCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

class MpsItemFactory extends Factory
{
    protected $model = MpsItem::class;

    public function definition(): array
    {
        $plan = MpsPlan::inRandomOrder()->first() ?? MpsPlan::factory()->create();
        $line = Line::inRandomOrder()->first() ?? Line::factory()->create();
        $wc = WorkCenter::inRandomOrder()->first() ?? WorkCenter::factory()->create();

        return [
            'mps_plan_id' => $plan->id,
            'tanggal' => $this->faker->dateTimeBetween($plan->month_year.'-01', $plan->month_year.'-'.now()->parse($plan->month_year.'-01')->daysInMonth)->format('Y-m-d'),
            'line_id' => $line->id,
            'work_center_id' => $wc->id,
            'shift' => $this->faker->randomElement(['shift1', 'shift2']),
            'mp_count' => $this->faker->numberBetween(1, 8),
            'target_qty' => 0, // Will be calculated
            'cleaning' => false,
            'adjusted_qty' => null,
            'gap_reason' => null,
            'notes' => null,
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (MpsItem $item) {
            $item->recalculateTarget();
        });
    }
}
