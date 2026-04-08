<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BaseUnit;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\OutputType;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ImportController extends Controller
{
    private array $unitMap = [
        'LITRO' => 'L',
        'KILOGRAMOS' => 'kg',
        'GRAMOS' => 'g',
        'MILILITROS' => 'mL',
        'CENTIMETROS' => 'cm',
    ];

    /**
     * Import inventory from Excel file
     * POST /api/admin/import-inventory
     * Accepts: file (Excel), step (categories|locations|base-units|products|stock-bodega|stock-fincas|all)
     */
    public function importInventory(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
            'step' => 'required|string|in:categories,locations,base-units,products,stock-bodega,stock-fincas,all',
        ]);

        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getRealPath());

        $step = $request->input('step');
        $steps = $step === 'all'
            ? ['categories', 'locations', 'base-units', 'products', 'stock-bodega', 'stock-fincas']
            : [$step];

        $results = [];
        foreach ($steps as $s) {
            $results[$s] = match ($s) {
                'categories' => $this->importCategories($spreadsheet),
                'locations' => $this->importLocations(),
                'base-units' => $this->importBaseUnits(),
                'products' => $this->importProducts($spreadsheet),
                'stock-bodega' => $this->importStockBodega($spreadsheet),
                'stock-fincas' => $this->importStockFincas($spreadsheet),
                default => ['error' => 'Paso desconocido'],
            };
        }

        return response()->json([
            'success' => true,
            'message' => 'Importación completada',
            'results' => $results,
        ]);
    }

    /**
     * Clean transactional data: products, inventory, movements, locations (farms), receptions, outputs, purchases
     * Keeps: users, roles, permissions, base_units, brands, output_types, suppliers, categories
     * POST /api/admin/clean-data
     */
    public function cleanData(): JsonResponse
    {
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            // Order matters due to FK constraints
            $tables = [
                'inventory_movements',
                'inventory',
                'reception_batch_items',
                'reception_batch_attachments',
                'reception_batches',
                'receptions',
                'purchase_items',
                'purchase_attachments',
                'purchases',
                'output_products',
                'product_outputs',
                'applications',
                'technical_order_products',
                'technical_order_farms',
                'technical_orders',
                'recipe_products',
                'technical_recipes',
                'product_packaging_units',
                'alerts',
                'farm_lots',
                'products',
            ];

            $cleaned = [];
            foreach ($tables as $table) {
                try {
                    $count = DB::table($table)->count();
                    DB::table($table)->truncate();
                    $cleaned[$table] = $count;
                } catch (\Exception $e) {
                    $cleaned[$table] = 'error: ' . $e->getMessage();
                }
            }

            // Delete farm locations (keep warehouses)
            $farmsDeleted = Location::where('type', 'farm')->count();
            Location::where('type', 'farm')->delete();
            $cleaned['locations_farms'] = $farmsDeleted;

            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            return response()->json([
                'success' => true,
                'message' => 'Datos transaccionales limpiados',
                'cleaned' => $cleaned,
            ]);
        } catch (\Exception $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Run pending migrations
     * POST /api/admin/run-migrations
     */
    public function runMigrations(): JsonResponse
    {
        try {
            \Artisan::call('migrate', ['--force' => true]);
            $output = \Artisan::output();
            return response()->json([
                'success' => true,
                'message' => 'Migraciones ejecutadas',
                'output' => $output,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create default brand "Sin Marca" if not exists
     * POST /api/admin/setup-brand
     */
    public function setupBrand(): JsonResponse
    {
        $brand = Brand::firstOrCreate(
            ['name' => 'Sin Marca'],
            ['description' => 'Marca por defecto para productos importados', 'status' => 'active']
        );

        // Create Remanente output type
        OutputType::firstOrCreate(
            ['name' => 'Remanente'],
            ['code' => 'remanente', 'description' => 'Devolución de producto desde finca a bodega', 'requires_lots' => false, 'status' => 'active']
        );

        return response()->json([
            'success' => true,
            'message' => 'Marca y tipo de salida creados',
            'brand_id' => $brand->id,
        ]);
    }

    private function importCategories($spreadsheet): array
    {
        $sheet = $spreadsheet->getSheetByName('INVENTARIOS');
        $categories = [];

        foreach ($sheet->getRowIterator(2) as $row) {
            foreach ($row->getCellIterator('B', 'B') as $cell) {
                $val = trim($cell->getValue() ?? '');
                if ($val !== '') $categories[$val] = true;
            }
        }

        $created = 0;
        $existing = 0;
        foreach (array_keys($categories) as $catName) {
            $slug = Str::slug($catName);
            if (Category::where('slug', $slug)->exists()) {
                $existing++;
            } else {
                Category::create([
                    'name' => mb_convert_case(mb_strtolower($catName), MB_CASE_TITLE, 'UTF-8'),
                    'slug' => $slug,
                    'description' => "Grupo de insumo: {$catName}",
                    'status' => 'active',
                ]);
                $created++;
            }
        }
        return ['created' => $created, 'existing' => $existing];
    }

    private function importLocations(): array
    {
        // Updated list including BREVA LOTE 7 (added in March 2026 inventory)
        $fincas = ['ALQUERÍA','BREVA','BREVA LOTE 7','CIRUELO','ESPÁRRAGOS','LA PALMA','VENTA','MANSIÓN','MELON','NARANJOS','ROBLE 1','ROBLE 2','SALADEROS','TORONJAS 2','TORONJAS','UVA','VILLA','ARANDANOS','LABORATORIO'];
        $created = 0;
        $existing = 0;

        foreach ($fincas as $name) {
            $clean = trim($name);
            if (Location::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($clean)])->exists()) {
                $existing++;
            } else {
                Location::create([
                    'name' => mb_convert_case(mb_strtolower($clean), MB_CASE_TITLE, 'UTF-8'),
                    'type' => 'farm',
                    'status' => 'active',
                ]);
                $created++;
            }
        }
        return ['created' => $created, 'existing' => $existing];
    }

    private function importBaseUnits(): array
    {
        $unit = BaseUnit::firstOrCreate(
            ['symbol' => 'cm'],
            ['name' => 'Centimetros', 'description' => 'Unidad de longitud', 'status' => 'active']
        );
        return ['cm' => $unit->wasRecentlyCreated ? 'created' : 'existing'];
    }

    private function importProducts($spreadsheet): array
    {
        $sheet = $spreadsheet->getSheetByName('INVENTARIOS');
        $adminUser = User::where('email', 'admin@agriflor.com')->first();
        $defaultBrand = Brand::where('name', 'Sin Marca')->first();

        if (!$adminUser || !$defaultBrand) {
            return ['error' => 'Falta usuario admin o marca Sin Marca. Ejecutar setup-brand primero.'];
        }

        $catBySlug = Category::all()->keyBy('slug');
        $created = 0;
        $existing = 0;
        $errors = 0;

        foreach ($sheet->getRowIterator(2) as $row) {
            $cells = [];
            foreach ($row->getCellIterator('A', 'D') as $cell) {
                $cells[$cell->getColumn()] = trim($cell->getValue() ?? '');
            }

            $code = $cells['A'];
            $nombre = $cells['C'];
            $unidad = $cells['D'];
            if (empty($nombre)) continue;

            $catSlug = Str::slug($cells['B']);
            $category = $catBySlug[$catSlug] ?? null;
            if (!$category) { $errors++; continue; }

            $baseUnit = $this->unitMap[strtoupper($unidad)] ?? null;
            if (!$baseUnit) { $errors++; continue; }

            if (Product::where('product_code', (string)$code)->exists()) {
                $existing++;
            } else {
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
                $created++;
            }
        }
        return ['created' => $created, 'existing' => $existing, 'errors' => $errors];
    }

    /**
     * Read header row and return map: header_name => column_index (0-based)
     */
    private function readHeaders($sheet): array
    {
        $headers = [];
        $headerRow = $sheet->getRowIterator(1, 1)->current();
        $cellIterator = $headerRow->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);
        foreach ($cellIterator as $cell) {
            $val = trim($cell->getValue() ?? '');
            if ($val !== '') {
                $colIndex = Coordinate::columnIndexFromString($cell->getColumn()) - 1;
                $headers[$val] = $colIndex;
            }
        }
        return $headers;
    }

    /**
     * Find column index by trying multiple possible header names
     */
    private function findCol(array $headers, array $possibleNames): ?int
    {
        foreach ($possibleNames as $name) {
            foreach ($headers as $h => $idx) {
                if (mb_strtolower(trim($h)) === mb_strtolower(trim($name))) {
                    return $idx;
                }
            }
        }
        return null;
    }

    private function importStockBodega($spreadsheet): array
    {
        $sheet = $spreadsheet->getSheetByName('INVENTARIOS');
        $bodega = Location::where('type', 'warehouse')->first();
        $adminUser = User::where('email', 'admin@agriflor.com')->first();
        $defaultBrand = Brand::where('name', 'Sin Marca')->first();

        if (!$bodega) return ['error' => 'No se encontró bodega principal'];

        // Read headers dynamically to handle different Excel layouts
        $headers = $this->readHeaders($sheet);
        $codeCol = $this->findCol($headers, ['Codigo', 'Código']);
        $nombreCol = $this->findCol($headers, ['Producto']);
        $invFinalCol = $this->findCol($headers, ['INVENTARIO FINAL', 'INVENTARIO FINAL ', 'Inventario Final']);

        if ($codeCol === null || $nombreCol === null || $invFinalCol === null) {
            return ['error' => 'No se encontraron las columnas requeridas en INVENTARIOS', 'headers' => array_keys($headers)];
        }

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($sheet, $bodega, $adminUser, $defaultBrand, $codeCol, $nombreCol, $invFinalCol, &$created, &$skipped) {
            foreach ($sheet->getRowIterator(2) as $row) {
                $cells = [];
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                foreach ($cellIterator as $cell) {
                    $colIndex = Coordinate::columnIndexFromString($cell->getColumn()) - 1;
                    $cells[$colIndex] = $cell->getCalculatedValue();
                }

                $code = trim($cells[$codeCol] ?? '');
                $nombre = trim($cells[$nombreCol] ?? '');
                $invFinal = floatval($cells[$invFinalCol] ?? 0);

                if (empty($nombre) || $invFinal <= 0) { if (!empty($nombre)) $skipped++; continue; }

                $product = Product::where('product_code', (string)$code)->first();
                if (!$product) continue;

                if (Inventory::where('product_id', $product->id)->where('location_id', $bodega->id)->exists()) {
                    $skipped++;
                    continue;
                }

                Inventory::create([
                    'product_id' => $product->id,
                    'brand_id' => $product->brand_id ?? $defaultBrand->id,
                    'location_id' => $bodega->id,
                    'quantity' => round($invFinal, 2),
                    'unit' => $product->base_unit,
                    'status' => 'good',
                ]);

                InventoryMovement::create([
                    'type' => 'entry',
                    'product_id' => $product->id,
                    'brand_id' => $product->brand_id ?? $defaultBrand->id,
                    'location_id' => $bodega->id,
                    'quantity' => round($invFinal, 2),
                    'unit' => $product->base_unit,
                    'responsible_user' => $adminUser->id,
                    'observations' => 'Ajuste inicial - Inventario final (importado desde Excel)',
                ]);
                $created++;
            }
        });
        return ['created' => $created, 'skipped' => $skipped, 'bodega' => $bodega->name];
    }

    private function importStockFincas($spreadsheet): array
    {
        $sheet = $spreadsheet->getSheetByName('REMANENTES');
        $adminUser = User::where('email', 'admin@agriflor.com')->first();
        $defaultBrand = Brand::where('name', 'Sin Marca')->first();

        // Read headers dynamically
        $headers = $this->readHeaders($sheet);
        $codeCol = $this->findCol($headers, ['Codigo', 'Código']);
        $nombreCol = $this->findCol($headers, ['Producto']);

        if ($codeCol === null || $nombreCol === null) {
            return ['error' => 'No se encontraron columnas requeridas en REMANENTES', 'headers' => array_keys($headers)];
        }

        // Excluded columns (not fincas)
        $excludeHeaders = ['Codigo', 'Código', 'Grupo Insumo', 'Producto', 'Unidad Medida', 'INVENTARIO FINAL', 'INVENTARIO FINAL ', 'TOTAL'];
        $excludeLower = array_map(fn($e) => mb_strtolower(trim($e)), $excludeHeaders);

        // Build location map from headers (any header that matches a Location name)
        $locations = Location::all();
        $locationMap = [];
        foreach ($headers as $header => $colIdx) {
            if (in_array(mb_strtolower(trim($header)), $excludeLower)) continue;
            $found = $locations->first(fn($loc) => mb_strtolower(trim($loc->name)) === mb_strtolower(trim($header)));
            if ($found) {
                $locationMap[$colIdx] = $found;
            }
        }

        $created = 0;
        $skipped = 0;
        $unmatchedFincas = [];
        foreach ($headers as $h => $idx) {
            if (in_array(mb_strtolower(trim($h)), $excludeLower)) continue;
            if (!isset($locationMap[$idx])) $unmatchedFincas[] = $h;
        }

        DB::transaction(function () use ($sheet, $locationMap, $adminUser, $defaultBrand, $codeCol, $nombreCol, &$created, &$skipped) {
            foreach ($sheet->getRowIterator(2) as $row) {
                $cells = [];
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                foreach ($cellIterator as $cell) {
                    $colIndex = Coordinate::columnIndexFromString($cell->getColumn()) - 1;
                    $cells[$colIndex] = $cell->getCalculatedValue();
                }

                $code = trim($cells[$codeCol] ?? '');
                $nombre = trim($cells[$nombreCol] ?? '');
                if (empty($nombre)) continue;

                $product = Product::where('product_code', (string)$code)->first();
                if (!$product) continue;

                foreach ($locationMap as $colIdx => $location) {
                    $qty = floatval($cells[$colIdx] ?? 0);
                    if ($qty <= 0) continue;

                    if (Inventory::where('product_id', $product->id)->where('location_id', $location->id)->exists()) {
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
                        'observations' => "Remanente en {$location->name} (importado desde Excel)",
                    ]);
                    $created++;
                }
            }
        });
        return [
            'created' => $created,
            'skipped' => $skipped,
            'fincas_matched' => count($locationMap),
            'fincas_unmatched' => $unmatchedFincas,
        ];
    }
}
