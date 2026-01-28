<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'category' => $this->faker->randomElement(['Químico', 'Fertilizante', 'Semilla', 'Herramienta']),
            'base_unit' => $this->faker->randomElement(['kg', 'L', 'unidad']),
            'description' => $this->faker->sentence(),
        ];
    }
}
