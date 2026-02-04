<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Fertilizante',
                'slug' => 'fertilizante',
                'description' => 'Productos para nutrición de plantas',
                'status' => 'active',
            ],
            [
                'name' => 'Pesticida',
                'slug' => 'pesticida',
                'description' => 'Productos para control de plagas',
                'status' => 'active',
            ],
            [
                'name' => 'Herbicida',
                'slug' => 'herbicida',
                'description' => 'Productos para control de malezas',
                'status' => 'active',
            ],
            [
                'name' => 'Fungicida',
                'slug' => 'fungicida',
                'description' => 'Productos para control de hongos',
                'status' => 'active',
            ],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}
