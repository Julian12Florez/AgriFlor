<?php

namespace App\Console\Commands;

use App\Models\BaseUnit;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportExcelInventory extends Command
{
    protected $signature = 'import:excel-inventory
                            {--step=all : Paso a ejecutar: categories, locations, base-units, products, stock-bodega, stock-fincas, all}
                            {--file=storage/app/inventario_feb.xlsx : Ruta del archivo Excel}
                            {--dry-run : Solo mostrar lo que se haria sin ejecutar}';

    protected $description = 'Importa inventario desde Excel: categorias, ubicaciones, productos y stock';

    private $unitMap = [
        'LITRO' => 'L',
        'KILOGRAMOS' => 'kg',
        'GRAMOS' => 'g',
        'MILILITROS' => 'mL',
        'CENTIMETROS' => 'cm',
    ];

    private $fincaColumns = [
        5 => 'ALQUERÍA',
        6 => 'BREVA',
        7 => 'CIRUELO',
        8 => 'ESPÁRRAGOS',
        9 => 'LA PALMA',
        10 => 'VENTA',
        11 => 'MANSIÓN',
        12 => 'MELON',
        13 => 'NARANJOS',
        14 => 'ROBLE 1',
        15 => 'ROBLE 2',
        16 => 'SALADEROS',
        17 => 'TORONJAS 2',
        18 => 'TORONJAS',
        19 => 'UVA',
        20 => 'VILLA',
        21 => 'ARANDANOS',
        22 => 'LABORATORIO',
    ];

    public function handle()
    {
        $step = $this->option('step');
        $filePath = base_path($this->option('file'));
        $dryRun = $this->option('dry-run');

        if (!file_exists($filePath)) {
            $this->error("Archivo no encontrado: {$filePath}");
            return 1;
        }

        $this->info("Cargando Excel: {$filePath}");
        $spreadsheet = IOFactory::load($filePath);

        if ($dryRun) {
            $this->warn('*** MODO DRY-RUN: No se ejecutaran cambios ***');
        }

        $steps = $step === 'all'
            ? ['categories', 'locations', 'base-units', 'products', 'stock-bodega', 'stock-fincas']
            : [$step];

        foreach ($steps as $s) {
            $this->info("\n========================================");
            $this->info("PASO: {$s}");
            $this->info("========================================\n");

            match ($s) {
                'categories' => $this->importCategories($spreadsheet, $dryRun),
                'locations' => $this->importLocations($dryRun),
                'base-units' => $this->importBaseUnits($dryRun),
                'products' => $this->importProducts($spreadsheet, $dryRun),
                'stock-bodega' => $this->importStockBodega($spreadsheet, $dryRun),
                'stock-fincas' => $this->importStockFincas($spreadsheet, $dryRun),
                default => $this->error("Paso desconocido: {$s}"),
            };
        }

        $this->info("\n*** Importacion completada ***");
        return 0;
    }

    private function importCategories($spreadsheet, bool $dryRun): void
    {
        $sheet = $spreadsheet->getSheetByName('INVENTARIOS');
        $categories = [];

        foreach ($sheet->getRowIterator(2) as $row) {
            $cellIterator = $row->getCellIterator('B', 'B');
            foreach ($cellIterator as $cell) {
                $val = trim($cell->getValue() ?? '');
                if ($val !== '') {
                    $categories[$val] = true;
                }
            }
        }

        $categories = array_keys($categories);
        sort($categories);

        $this->info("Categorias encontradas en Excel: " . count($categories));
        $created = 0;
        $existing = 0;

        foreach ($categories as $catName) {
            $slug = Str::slug($catName);
            $exists = Category::where('slug', $slug)->first();

            if ($exists) {
                $this->line("  [EXISTE] {$catName} ({$slug})");
                $existing++;
            } else {
                if (!$dryRun) {
                    Category::create([
                        'name' => mb_convert_case(mb_strtolower($catName), MB_CASE_TITLE, 'UTF-8'),
                        'slug' => $slug,
                        'description' => "Grupo de insumo: {$catName}",
                        'status' => 'active',
                    ]);
                }
                $this->line("  [CREADA] {$catName} ({$slug})");
                $created++;
            }
        }

        $this->info("\nResumen: {$created} creadas, {$existing} ya existian");
    }

    private function importLocations(bool $dryRun): void
    {
        // Verificar bodega principal
        $bodega = Location::where('type', 'warehouse')->first();
        if ($bodega) {
            $this->info("Bodega principal encontrada: {$bodega->name} (ID: {$bodega->id})");
        } else {
            $this->warn("No se encontro bodega principal, creando una...");
            if (!$dryRun) {
                $bodega = Location::create([
                    'name' => 'Bodega Principal',
                    'type' => 'warehouse',
                    'status' => 'active',
                ]);
            }
            $this->info("  [CREADA] Bodega Principal (warehouse)");
        }

        $fincas = array_values($this->fincaColumns);
        $created = 0;
        $existing = 0;

        foreach ($fincas as $fincaName) {
            $cleanName = trim($fincaName);
            // Buscar por nombre similar (sin acentos, case insensitive)
            $exists = Location::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($cleanName)])->first();

            if ($exists) {
                $this->line("  [EXISTE] {$cleanName} ({$exists->type})");
                $existing++;
            } else {
                if (!$dryRun) {
                    Location::create([
                        'name' => mb_convert_case(mb_strtolower($cleanName), MB_CASE_TITLE, 'UTF-8'),
                        'type' => 'farm',
                        'status' => 'active',
                    ]);
                }
                $this->line("  [CREADA] {$cleanName} (farm)");
                $created++;
            }
        }

        $this->info("\nResumen: {$created} creadas, {$existing} ya existian");
    }

    private function importBaseUnits(bool $dryRun): void
    {
        $needed = [
            ['name' => 'Centimetros', 'symbol' => 'cm', 'description' => 'Unidad de longitud', 'status' => 'active'],
        ];

        foreach ($needed as $unit) {
            $exists = BaseUnit::where('symbol', $unit['symbol'])->first();
            if ($exists) {
                $this->line("  [EXISTE] {$unit['name']} ({$unit['symbol']})");
            } else {
                if (!$dryRun) {
                    BaseUnit::create($unit);
                }
                $this->line("  [CREADA] {$unit['name']} ({$unit['symbol']})");
            }
        }
    }

    private function importProducts($spreadsheet, bool $dryRun): void
    {
        $sheet = $spreadsheet->getSheetByName('INVENTARIOS');
        $adminUser = User::where('email', 'admin@agriflor.com')->first();

        if (!$adminUser) {
            $this->error("No se encontro usuario admin@agriflor.com");
            return;
        }

        // Buscar o crear marca por defecto
        $defaultBrand = \App\Models\Brand::where('name', 'Sin Marca')->first();
        if (!$defaultBrand) {
            $this->error("No se encontro marca 'Sin Marca'. Crear primero.");
            return;
        }
        $this->info("Marca por defecto: {$defaultBrand->name} (ID: {$defaultBrand->id})");

        // Precargar categorias por slug
        $categories = Category::all()->keyBy(fn($c) => Str::slug($c->name));
        // También indexar por slug directo
        $catBySlug = Category::all()->keyBy('slug');

        $created = 0;
        $existing = 0;
        $errors = 0;

        foreach ($sheet->getRowIterator(2) as $row) {
            $cells = [];
            $cellIterator = $row->getCellIterator('A', 'D');
            foreach ($cellIterator as $cell) {
                $cells[$cell->getColumn()] = trim($cell->getValue() ?? '');
            }

            $code = $cells['A'];
            $grupoInsumo = $cells['B'];
            $nombre = $cells['C'];
            $unidad = $cells['D'];

            if (empty($nombre)) continue;

            // Buscar categoria
            $catSlug = Str::slug($grupoInsumo);
            $category = $catBySlug[$catSlug] ?? $categories[$catSlug] ?? null;

            if (!$category) {
                $this->warn("  [WARN] Categoria no encontrada para '{$grupoInsumo}' (slug: {$catSlug}), producto: {$nombre}");
                $errors++;
                continue;
            }

            // Mapear unidad
            $baseUnit = $this->unitMap[strtoupper($unidad)] ?? null;
            if (!$baseUnit) {
                $this->warn("  [WARN] Unidad no mapeada: '{$unidad}', producto: {$nombre}");
                $errors++;
                continue;
            }

            // Verificar si existe por product_code
            $exists = Product::where('product_code', (string)$code)->first();

            if ($exists) {
                $this->line("  [EXISTE] {$code} - {$nombre}");
                $existing++;
            } else {
                if (!$dryRun) {
                    Product::create([
                        'name' => $nombre,
                        'product_code' => (string)$code,
                        'brand_id' => $defaultBrand->id,
                        'category_id' => $category->id,
                        'base_unit' => $baseUnit,
                        'iva' => 0,
                        'active_ingredient' => '',
                        'min_stock' => 0,
                        'status' => 'active',
                        'created_by' => $adminUser->id,
                    ]);
                }
                $this->line("  [CREADO] {$code} - {$nombre} ({$category->name}, {$baseUnit})");
                $created++;
            }
        }

        $this->info("\nResumen: {$created} creados, {$existing} ya existian, {$errors} errores");
    }

    private function importStockBodega($spreadsheet, bool $dryRun): void
    {
        $sheet = $spreadsheet->getSheetByName('INVENTARIOS');
        $bodega = Location::where('type', 'warehouse')->first();
        $adminUser = User::where('email', 'admin@agriflor.com')->first();
        $defaultBrand = \App\Models\Brand::where('name', 'Sin Marca')->first();

        if (!$bodega) {
            $this->error("No se encontro bodega principal");
            return;
        }

        $this->info("Bodega: {$bodega->name} (ID: {$bodega->id})");

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($sheet, $bodega, $adminUser, $defaultBrand, $dryRun, &$created, &$skipped) {
            foreach ($sheet->getRowIterator(2) as $row) {
                $cells = [];
                $cellIterator = $row->getCellIterator('A', 'Z');
                $cellIterator->setIterateOnlyExistingCells(false);
                foreach ($cellIterator as $cell) {
                    $colIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($cell->getColumn()) - 1;
                    $cells[$colIndex] = $cell->getCalculatedValue();
                }

                $code = trim($cells[0] ?? '');
                $nombre = trim($cells[2] ?? '');
                $invFinal = floatval($cells[25] ?? 0); // Columna Z = INVENTARIO FINAL

                if (empty($nombre) || $invFinal <= 0) {
                    if (!empty($nombre)) $skipped++;
                    continue;
                }

                $product = Product::where('product_code', (string)$code)->first();
                if (!$product) {
                    $this->warn("  [WARN] Producto no encontrado: {$code} - {$nombre}");
                    continue;
                }

                if ($dryRun) {
                    $this->line("  [DRY] {$code} - {$nombre}: {$invFinal} {$product->base_unit}");
                    $created++;
                    continue;
                }

                // Verificar si ya tiene inventario en esta bodega
                $existingInv = Inventory::where('product_id', $product->id)
                    ->where('location_id', $bodega->id)
                    ->first();

                if ($existingInv) {
                    $this->line("  [EXISTE] {$code} - {$nombre}: ya tiene stock en bodega ({$existingInv->quantity})");
                    $skipped++;
                    continue;
                }

                // Crear inventario
                Inventory::create([
                    'product_id' => $product->id,
                    'brand_id' => $product->brand_id ?? $defaultBrand->id,
                    'location_id' => $bodega->id,
                    'quantity' => round($invFinal, 2),
                    'unit' => $product->base_unit,
                    'status' => 'good',
                ]);

                // Crear movimiento de entrada (ajuste inicial)
                InventoryMovement::create([
                    'type' => 'entry',
                    'product_id' => $product->id,
                    'brand_id' => $product->brand_id ?? $defaultBrand->id,
                    'location_id' => $bodega->id,
                    'quantity' => round($invFinal, 2),
                    'unit' => $product->base_unit,
                    'responsible_user' => $adminUser->id,
                    'observations' => 'Ajuste inicial - Inventario final febrero 2026 (importado desde Excel)',
                ]);

                $this->line("  [CREADO] {$code} - {$nombre}: {$invFinal} {$product->base_unit}");
                $created++;
            }
        });

        $this->info("\nResumen: {$created} registros de stock creados, {$skipped} sin stock o ya existentes");
    }

    private function importStockFincas($spreadsheet, bool $dryRun): void
    {
        $sheet = $spreadsheet->getSheetByName('REMANENTES');
        $adminUser = User::where('email', 'admin@agriflor.com')->first();
        $defaultBrand = \App\Models\Brand::where('name', 'Sin Marca')->first();

        // Mapear nombres de fincas a IDs de locations
        // En REMANENTES las fincas van de columna E(4) a V(21)
        $remanenteFincaCols = [
            4 => 'ALQUERÍA',
            5 => 'BREVA',
            6 => 'CIRUELO',
            7 => 'ESPÁRRAGOS',
            8 => 'LA PALMA',
            9 => 'VENTA',
            10 => 'MANSIÓN',
            11 => 'MELON',
            12 => 'NARANJOS',
            13 => 'ROBLE 1',
            14 => 'ROBLE 2',
            15 => 'SALADEROS',
            16 => 'TORONJAS 2',
            17 => 'TORONJAS',
            18 => 'UVA',
            19 => 'VILLA',
            20 => 'ARANDANOS',
            21 => 'LABORATORIO',
        ];

        // Precargar locations por nombre (case insensitive)
        $locations = Location::all();
        $locationMap = [];
        foreach ($remanenteFincaCols as $col => $fincaName) {
            $cleanName = mb_strtolower(trim($fincaName));
            $found = $locations->first(function ($loc) use ($cleanName) {
                return mb_strtolower(trim($loc->name)) === $cleanName;
            });
            if ($found) {
                $locationMap[$col] = $found;
            } else {
                $this->warn("  [WARN] Location no encontrada para: {$fincaName}");
            }
        }

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($sheet, $locationMap, $adminUser, $defaultBrand, $remanenteFincaCols, $dryRun, &$created, &$skipped) {
            foreach ($sheet->getRowIterator(2) as $row) {
                $cells = [];
                $cellIterator = $row->getCellIterator('A', 'W');
                $cellIterator->setIterateOnlyExistingCells(false);
                foreach ($cellIterator as $cell) {
                    $colIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($cell->getColumn()) - 1;
                    $cells[$colIndex] = $cell->getCalculatedValue();
                }

                $code = trim($cells[0] ?? '');
                $nombre = trim($cells[2] ?? '');

                if (empty($nombre)) continue;

                $product = Product::where('product_code', (string)$code)->first();
                if (!$product) continue;

                foreach ($remanenteFincaCols as $colIdx => $fincaName) {
                    $qty = floatval($cells[$colIdx] ?? 0);
                    if ($qty <= 0) continue;

                    $location = $locationMap[$colIdx] ?? null;
                    if (!$location) continue;

                    if ($dryRun) {
                        $this->line("  [DRY] {$nombre} -> {$location->name}: {$qty} {$product->base_unit}");
                        $created++;
                        continue;
                    }

                    // Verificar si ya existe
                    $existingInv = Inventory::where('product_id', $product->id)
                        ->where('location_id', $location->id)
                        ->first();

                    if ($existingInv) {
                        $this->line("  [EXISTE] {$nombre} en {$location->name}: ya tiene stock ({$existingInv->quantity})");
                        $skipped++;
                        continue;
                    }

                    Inventory::create([
                        'product_id' => $product->id,
                        'brand_id' => $product->brand_id ?? $defaultBrand->id,
                        'location_id' => $location->id,
                        'quantity' => round($qty, 2),
                        'unit' => $product->base_unit,
                        'status' => 'good',
                    ]);

                    InventoryMovement::create([
                        'type' => 'entry',
                        'product_id' => $product->id,
                        'brand_id' => $product->brand_id ?? $defaultBrand->id,
                        'location_id' => $location->id,
                        'quantity' => round($qty, 2),
                        'unit' => $product->base_unit,
                        'responsible_user' => $adminUser->id,
                        'observations' => "Remanente febrero 2026 en {$location->name} (importado desde Excel)",
                    ]);

                    $this->line("  [CREADO] {$nombre} -> {$location->name}: {$qty} {$product->base_unit}");
                    $created++;
                }
            }
        });

        $this->info("\nResumen: {$created} registros de remanente creados, {$skipped} ya existentes");
    }
}
