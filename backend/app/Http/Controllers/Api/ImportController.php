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
        $fincas = ['ALQUERÍA','BREVA','CIRUELO','ESPÁRRAGOS','LA PALMA','VENTA','MANSIÓN','MELON','NARANJOS','ROBLE 1','ROBLE 2','SALADEROS','TORONJAS 2','TORONJAS','UVA','VILLA','ARANDANOS','LABORATORIO'];
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

    private function importStockBodega($spreadsheet): array
    {
        $sheet = $spreadsheet->getSheetByName('INVENTARIOS');
        $bodega = Location::where('type', 'warehouse')->first();
        $adminUser = User::where('email', 'admin@agriflor.com')->first();
        $defaultBrand = Brand::where('name', 'Sin Marca')->first();

        if (!$bodega) return ['error' => 'No se encontró bodega principal'];

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($sheet, $bodega, $adminUser, $defaultBrand, &$created, &$skipped) {
            foreach ($sheet->getRowIterator(2) as $row) {
                $cells = [];
                $cellIterator = $row->getCellIterator('A', 'Z');
                $cellIterator->setIterateOnlyExistingCells(false);
                foreach ($cellIterator as $cell) {
                    $colIndex = Coordinate::columnIndexFromString($cell->getColumn()) - 1;
                    $cells[$colIndex] = $cell->getCalculatedValue();
                }

                $code = trim($cells[0] ?? '');
                $nombre = trim($cells[2] ?? '');
                $invFinal = floatval($cells[25] ?? 0);

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
                    'observations' => 'Ajuste inicial - Inventario final febrero 2026 (importado desde Excel)',
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

        $remanenteFincaCols = [
            4 => 'ALQUERÍA', 5 => 'BREVA', 6 => 'CIRUELO', 7 => 'ESPÁRRAGOS',
            8 => 'LA PALMA', 9 => 'VENTA', 10 => 'MANSIÓN', 11 => 'MELON',
            12 => 'NARANJOS', 13 => 'ROBLE 1', 14 => 'ROBLE 2', 15 => 'SALADEROS',
            16 => 'TORONJAS 2', 17 => 'TORONJAS', 18 => 'UVA', 19 => 'VILLA',
            20 => 'ARANDANOS', 21 => 'LABORATORIO',
        ];

        $locations = Location::all();
        $locationMap = [];
        foreach ($remanenteFincaCols as $col => $fincaName) {
            $found = $locations->first(fn($loc) => mb_strtolower(trim($loc->name)) === mb_strtolower(trim($fincaName)));
            if ($found) $locationMap[$col] = $found;
        }

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($sheet, $locationMap, $adminUser, $defaultBrand, $remanenteFincaCols, &$created, &$skipped) {
            foreach ($sheet->getRowIterator(2) as $row) {
                $cells = [];
                $cellIterator = $row->getCellIterator('A', 'W');
                $cellIterator->setIterateOnlyExistingCells(false);
                foreach ($cellIterator as $cell) {
                    $colIndex = Coordinate::columnIndexFromString($cell->getColumn()) - 1;
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
                        'observations' => "Remanente febrero 2026 en {$location->name} (importado desde Excel)",
                    ]);
                    $created++;
                }
            }
        });
        return ['created' => $created, 'skipped' => $skipped];
    }
}
