<?php

namespace Database\Factories;

use App\Models\Produk;
use App\Models\ScmCategory;
use App\Models\ScmUom;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProdukFactory extends Factory
{
    protected $model = Produk::class;

    public function definition(): array
    {
        return [
            'odoo_id' => null,
            'kode_produk' => 'PRD-'.$this->faker->unique()->numerify('####'),
            'nama_produk' => $this->faker->words(3, true),
            'proses_default' => $this->faker->randomElement(['mixing', 'filling', 'secondary']),
            'category_id' => ScmCategory::inRandomOrder()->first()?->id,
            'uom_id' => ScmUom::inRandomOrder()->first()?->id,
            'safety_stock' => $this->faker->randomFloat(2, 0, 100),
            'min_stock' => $this->faker->randomFloat(2, 0, 50),
            'max_stock' => $this->faker->randomFloat(2, 100, 1000),
            'item_type' => $this->faker->randomElement(['fg', 'rm', 'pm', 'wip']),
            'odoo_uom' => $this->faker->randomElement(['Pcs', 'Kg', 'L', 'G']),
            'status_aktif' => true,
            'odoo_synced_at' => null,
        ];
    }
}
