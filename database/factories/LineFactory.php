<?php

namespace Database\Factories;

use App\Models\Line;
use App\Models\WorkCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

class LineFactory extends Factory
{
    protected $model = Line::class;

    public function definition(): array
    {
        return [
            'kode_line' => 'L'.$this->faker->unique()->numberBetween(1, 99),
            'nama_line' => 'Line '.$this->faker->unique()->lexify('??'),
            'status_aktif' => true,
            'default_work_center_id' => null,
            'effective_ct_seconds' => null,
            'can_run_work_centers' => null,
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (Line $line) {
            if (! $line->default_work_center_id) {
                $wc = WorkCenter::inRandomOrder()->first();
                if ($wc) {
                    $line->update(['default_work_center_id' => $wc->id]);
                }
            }
        });
    }
}
