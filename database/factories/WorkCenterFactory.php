<?php

namespace Database\Factories;

use App\Models\WorkCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkCenterFactory extends Factory
{
    protected $model = WorkCenter::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['mixing', 'filling', 'secondary']);
        $ctMap = ['mixing' => 3, 'filling' => 2, 'secondary' => 4];
        $mpMap = ['mixing' => 4, 'filling' => 6, 'secondary' => 8];

        return [
            'code' => 'WC-'.strtoupper(substr($type, 0, 3)).'-'.$this->faker->unique()->numberBetween(1, 99),
            'name' => ucfirst($type),
            'type' => $type,
            'standard_ct_seconds' => $ctMap[$type],
            'fit_mp' => $mpMap[$type],
            'shift_hours' => 6.5,
            'saturday_shift_hours' => 4.0,
            'is_active' => true,
            'notes' => null,
        ];
    }
}
