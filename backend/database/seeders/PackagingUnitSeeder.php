<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PackagingUnit;

class PackagingUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packagingUnits = [
            // Weight units
            [
                'name' => 'Bulto',
                'base_quantity' => 50,
                'base_unit' => 'kg',
            ],
            [
                'name' => 'Medio Bulto',
                'base_quantity' => 25,
                'base_unit' => 'kg',
            ],
            [
                'name' => 'Saco',
                'base_quantity' => 20,
                'base_unit' => 'kg',
            ],
            [
                'name' => 'Media Unidad',
                'base_quantity' => 12.5,
                'base_unit' => 'kg',
            ],
            [
                'name' => 'Kilogramo',
                'base_quantity' => 1,
                'base_unit' => 'kg',
            ],
            // Liquid units
            [
                'name' => 'Galón',
                'base_quantity' => 4,
                'base_unit' => 'L',
            ],
            [
                'name' => 'Galon',
                'base_quantity' => 4,
                'base_unit' => 'L',
            ],
            [
                'name' => 'Litro',
                'base_quantity' => 1,
                'base_unit' => 'L',
            ],
            [
                'name' => 'Medio Litro',
                'base_quantity' => 0.5,
                'base_unit' => 'L',
            ],
            // Counting units
            [
                'name' => 'Caja',
                'base_quantity' => 12,
                'base_unit' => 'unidades',
            ],
            [
                'name' => 'Unidad',
                'base_quantity' => 1,
                'base_unit' => 'unidades',
            ],
        ];

        foreach ($packagingUnits as $unit) {
            PackagingUnit::create($unit);
        }
    }
}
