<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateProductCategories extends Command
{
    protected $signature = 'products:update-categories';
    protected $description = 'Update products with category_id based on old category ENUM field';

    public function handle()
    {
        $this->info('=== Actualizando categorías de productos ===');

        // 1. Verificar si la tabla categories existe
        if (!DB::getSchemaBuilder()->hasTable('categories')) {
            $this->error('La tabla categories no existe. Ejecuta las migraciones primero.');
            return 1;
        }

        // 2. Insertar categorías si no existen
        $categories = [
            ['slug' => 'fertilizante', 'name' => 'Fertilizante', 'description' => 'Productos para nutrición de plantas'],
            ['slug' => 'pesticida', 'name' => 'Pesticida', 'description' => 'Control de plagas'],
            ['slug' => 'herbicida', 'name' => 'Herbicida', 'description' => 'Control de malezas'],
            ['slug' => 'fungicida', 'name' => 'Fungicida', 'description' => 'Control de hongos'],
        ];

        foreach ($categories as $cat) {
            $exists = DB::table('categories')->where('slug', $cat['slug'])->exists();
            if (!$exists) {
                DB::table('categories')->insert([
                    'id' => (string) Str::uuid(),
                    'name' => $cat['name'],
                    'slug' => $cat['slug'],
                    'description' => $cat['description'],
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->info("Categoría '{$cat['name']}' creada.");
            } else {
                $this->line("Categoría '{$cat['name']}' ya existe.");
            }
        }

        // 3. Verificar si category_id existe en products
        if (!DB::getSchemaBuilder()->hasColumn('products', 'category_id')) {
            $this->error('La columna category_id no existe en products. Ejecuta las migraciones primero.');
            return 1;
        }

        // 4. Verificar si category (ENUM) existe en products
        $hasOldCategory = DB::getSchemaBuilder()->hasColumn('products', 'category');

        if ($hasOldCategory) {
            // Actualizar products usando el campo category ENUM
            $updated = DB::statement("
                UPDATE products p
                SET category_id = (
                    SELECT c.id FROM categories c WHERE c.slug = p.category
                )
                WHERE p.category IS NOT NULL AND p.category_id IS NULL
            ");
            $this->info('Productos actualizados con category_id desde campo ENUM.');
        } else {
            $this->warn('El campo category ENUM ya no existe. No hay nada que migrar.');
        }

        // 5. Mostrar resumen
        $total = DB::table('products')->count();
        $withCategory = DB::table('products')->whereNotNull('category_id')->count();
        $withoutCategory = DB::table('products')->whereNull('category_id')->count();

        $this->newLine();
        $this->info('=== Resumen ===');
        $this->line("Total productos: {$total}");
        $this->line("Con category_id: {$withCategory}");
        $this->line("Sin category_id: {$withoutCategory}");

        if ($withoutCategory > 0) {
            $this->warn("Hay {$withoutCategory} productos sin categoría asignada.");
        } else {
            $this->info('Todos los productos tienen categoría asignada.');
        }

        return 0;
    }
}
