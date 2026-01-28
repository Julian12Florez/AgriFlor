<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Brand;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $brands = [
            [
                'name' => 'Yara',
                'status' => 'active',
            ],
            [
                'name' => 'Bayer',
                'status' => 'active',
            ],
            [
                'name' => 'BASF',
                'status' => 'active',
            ],
            [
                'name' => 'Syngenta',
                'status' => 'active',
            ],
            [
                'name' => 'Corteva',
                'status' => 'active',
            ],
            [
                'name' => 'FMC',
                'status' => 'active',
            ],
        ];

        foreach ($brands as $brand) {
            Brand::create($brand);
        }
    }
}
