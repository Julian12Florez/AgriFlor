<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'location_type' => $this->faker->randomElement(['bodega', 'finca', 'oficina']),
            'address' => $this->faker->address(),
        ];
    }
}
