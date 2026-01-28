<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Location;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $locations = [
            // WAREHOUSES
            [
                'name' => 'Bodega Central',
                'type' => 'warehouse',
                'municipality' => 'Medellín, Antioquia',
                'address' => null,
                'coordinates_lat' => null,
                'coordinates_lng' => null,
                'responsible' => 'María González',
                'status' => 'active',
            ],
            [
                'name' => 'Bodega Norte',
                'type' => 'warehouse',
                'municipality' => 'Rionegro, Antioquia',
                'address' => null,
                'coordinates_lat' => null,
                'coordinates_lng' => null,
                'responsible' => 'Luis Martínez',
                'status' => 'active',
            ],
            // FARMS
            [
                'name' => 'Finca El Paraíso',
                'type' => 'farm',
                'municipality' => 'Carmen de Viboral',
                'address' => null,
                'coordinates_lat' => 6.0833,
                'coordinates_lng' => -75.3333,
                'responsible' => 'Pedro López',
                'status' => 'active',
            ],
            [
                'name' => 'Finca La Esperanza',
                'type' => 'farm',
                'municipality' => 'La Ceja',
                'address' => null,
                'coordinates_lat' => 6.0333,
                'coordinates_lng' => -75.4333,
                'responsible' => 'Ana Torres',
                'status' => 'active',
            ],
            [
                'name' => 'Finca Vista Hermosa',
                'type' => 'farm',
                'municipality' => 'El Retiro',
                'address' => null,
                'coordinates_lat' => 6.0633,
                'coordinates_lng' => -75.5000,
                'responsible' => 'José Ramírez',
                'status' => 'active',
            ],
        ];

        foreach ($locations as $location) {
            Location::create($location);
        }
    }
}
