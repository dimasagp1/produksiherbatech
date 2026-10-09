<?php

namespace Database\Factories;

use App\Models\MpsPlan;
use App\Models\Produk;
use App\Models\User;
use App\Models\WorkCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

class MpsPlanFactory extends Factory
{
    protected $model = MpsPlan::class;

    public function definition(): array
    {
        return [
            'produk_id' => Produk::inRandomOrder()->first()?->id ?? Produk::factory(),
            'work_center_id' => WorkCenter::inRandomOrder()->first()?->id ?? WorkCenter::factory(),
            'month_year' => now()->format('Y-m'),
            'status' => $this->faker->randomElement(['draft', 'approved', 'active', 'closed']),
            'notes' => $this->faker->optional()->sentence(),
            'created_by' => User::inRandomOrder()->first()?->id ?? User::factory(),
        ];
    }
}
