<?php

namespace Database\Seeders;

use App\Models\OutputType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OutputTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $outputTypes = [
            [
                'id' => Str::uuid(),
                'name' => 'Orden Técnica',
                'code' => 'technical_order',
                'description' => 'Salida de productos asociada a una orden técnica',
                'requires_lots' => false,
                'status' => 'active',
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Solicitud Libre',
                'code' => 'free_request',
                'description' => 'Salida de productos por solicitud libre sin orden técnica',
                'requires_lots' => false,
                'status' => 'active',
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Transferencia',
                'code' => 'transfer',
                'description' => 'Transferencia de productos entre ubicaciones',
                'requires_lots' => false,
                'status' => 'active',
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Consumo',
                'code' => 'consumption',
                'description' => 'Salida de productos para consumo en lotes específicos de finca',
                'requires_lots' => true,
                'status' => 'active',
            ],
        ];

        foreach ($outputTypes as $type) {
            OutputType::create($type);
        }
    }
}
