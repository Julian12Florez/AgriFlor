<?php

namespace Database\Seeders;

use App\Models\BaseUnit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BaseUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $baseUnits = [
            [
                'name' => 'Kilogramos',
                'symbol' => 'kg',
                'description' => 'Unidad de masa del Sistema Internacional',
                'status' => 'active',
            ],
            [
                'name' => 'Litros',
                'symbol' => 'L',
                'description' => 'Unidad de volumen',
                'status' => 'active',
            ],
            [
                'name' => 'Unidades',
                'symbol' => 'unidades',
                'description' => 'Unidades individuales o piezas',
                'status' => 'active',
            ],
            [
                'name' => 'Gramos',
                'symbol' => 'g',
                'description' => 'Unidad de masa (submúltiplo del kilogramo)',
                'status' => 'active',
            ],
            [
                'name' => 'Mililitros',
                'symbol' => 'mL',
                'description' => 'Unidad de volumen (submúltiplo del litro)',
                'status' => 'active',
            ],
        ];

        foreach ($baseUnits as $unit) {
            BaseUnit::firstOrCreate(
                ['symbol' => $unit['symbol']], // Buscar por symbol
                $unit // Crear con todos los datos si no existe
            );
        }
    }
}
