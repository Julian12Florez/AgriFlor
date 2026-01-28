<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\Location;
use App\Models\Product;
use App\Models\Brand;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Purchase;
use App\Models\Reception;
use App\Models\ProductOutput;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Alert;

class RunInventoryTests extends Command
{
    protected $signature = 'test:inventory {--scenario=all}';
    protected $description = 'Ejecuta pruebas completas del sistema de inventario con datos reales en BD';

    private $baseUrl;
    private $token;
    private $results = [];

    public function handle()
    {
        $this->baseUrl = config('app.url');
        $this->info("🧪 INICIANDO PRUEBAS DE INVENTARIO");
        $this->info("Base URL: {$this->baseUrl}");
        $this->newLine();

        // Login
        if (!$this->login()) {
            $this->error("❌ Login falló. Abortando pruebas.");
            return 1;
        }

        // Obtener datos maestros
        $this->info("📊 Obteniendo datos maestros de la BD...");
        $masterData = $this->getMasterData();
        $this->displayMasterData($masterData);

        $scenario = $this->option('scenario');

        if ($scenario === 'all' || $scenario === '1') {
            $this->runScenario1($masterData);
        }

        if ($scenario === 'all' || $scenario === '2') {
            $this->runScenario2($masterData);
        }

        if ($scenario === 'all' || $scenario === '3') {
            $this->runScenario3($masterData);
        }

        if ($scenario === 'all' || $scenario === 'search') {
            $this->runSearchTests($masterData);
        }

        $this->displayResults();

        return 0;
    }

    private function login(): bool
    {
        $this->info("🔐 Autenticando como admin...");

        $response = Http::post("{$this->baseUrl}/api/auth/login", [
            'email' => 'admin@agriflor.com',
            'password' => 'Admin123!'
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $this->token = $data['data']['token'] ?? null;

            if ($this->token) {
                $this->info("   ✓ Login exitoso");
                $user = $data['data']['user'] ?? [];
                $this->info("   Usuario: {$user['name']} ({$user['role']})");
                return true;
            }
        }

        $this->error("   ✗ Login falló: " . $response->body());
        return false;
    }

    private function getMasterData(): array
    {
        $data = [
            'locations' => Location::all(),
            'products' => Product::with('brand')->get(),
            'brands' => Brand::all(),
            'suppliers' => Supplier::all(),
            'warehouses' => Location::where('type', 'warehouse')->get(),
            'farms' => Location::where('type', 'farm')->get(),
        ];

        return $data;
    }

    private function displayMasterData($data): void
    {
        $this->newLine();
        $this->info("📍 Ubicaciones: " . $data['locations']->count());
        foreach ($data['warehouses'] as $location) {
            $this->line("   - {$location->name} (Bodega)");
        }
        foreach ($data['farms'] as $location) {
            $this->line("   - {$location->name} (Finca)");
        }

        $this->newLine();
        $this->info("📦 Productos: " . $data['products']->count());
        foreach ($data['products']->take(5) as $product) {
            $this->line("   - {$product->name} (Marca: {$product->brand->name})");
        }

        $this->newLine();
        $this->info("🏭 Proveedores: " . $data['suppliers']->count());
        foreach ($data['suppliers'] as $supplier) {
            $this->line("   - {$supplier->name}");
        }
        $this->newLine();
    }

    private function runScenario1($masterData): void
    {
        $this->newLine();
        $this->info("═══════════════════════════════════════════════════════");
        $this->info("🎯 ESCENARIO 1: COMPRA CON RECEPCIÓN PARCIAL (3 LOTES)");
        $this->info("═══════════════════════════════════════════════════════");
        $this->newLine();

        // Seleccionar datos
        $warehouse = $masterData['warehouses']->first();
        $supplier = $masterData['suppliers']->first();
        $product = $masterData['products']->first();

        if (!$warehouse || !$supplier || !$product) {
            $this->error("❌ Faltan datos maestros necesarios");
            return;
        }

        $this->info("📋 Datos del escenario:");
        $this->line("   Proveedor: {$supplier->name}");
        $this->line("   Bodega: {$warehouse->name}");
        $this->line("   Producto: {$product->name} (Marca: {$product->brand->name})");
        $this->newLine();

        // PASO 1: Crear compra de 300 unidades
        $this->info("PASO 1: Crear compra de 300 kg");
        $purchaseData = [
            'supplier_id' => $supplier->id,
            'location_id' => $warehouse->id,
            'expected_date' => now()->addDays(7)->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => $product->id,
                    'brand_id' => $product->brand_id,
                    'quantity' => 300,
                    'unit' => 'kg',
                    'unit_price' => 50000,
                ]
            ],
            'notes' => 'Prueba automatizada - Recepción parcial'
        ];

        $response = Http::withToken($this->token)
            ->post("{$this->baseUrl}/api/purchases", $purchaseData);

        if ($response->successful()) {
            $purchase = $response->json()['data'];
            $this->info("   ✓ Compra creada: {$purchase['purchase_number']}");
            $this->line("   Total: \$" . number_format($purchase['total'], 0));
            $this->results['scenario1']['purchase_id'] = $purchase['id'];
        } else {
            $this->error("   ✗ Error creando compra: " . $response->body());
            return;
        }

        // PASO 2: Crear recepción
        $this->newLine();
        $this->info("PASO 2: Crear recepción desde compra");

        $receptionData = [
            'reception_number' => 'REC-TEST-' . time(),
            'source_type' => 'purchase',
            'source_id' => $purchase['id'],
            'origin_location_id' => $supplier->id, // En realidad debería ser location del proveedor
            'destination_location_id' => $warehouse->id,
            'shipment_date' => now()->format('Y-m-d'),
            'observations' => 'Recepción de prueba automática'
        ];

        $response = Http::withToken($this->token)
            ->post("{$this->baseUrl}/api/receptions", $receptionData);

        if ($response->successful()) {
            $reception = $response->json()['data'];
            $this->info("   ✓ Recepción creada: {$reception['reception_number']}");
            $this->line("   Total esperado: {$reception['total_expected']} kg");
            $this->results['scenario1']['reception_id'] = $reception['id'];
        } else {
            $this->error("   ✗ Error creando recepción: " . $response->body());
            return;
        }

        // PASO 3: Agregar primer lote (40% = 120 kg)
        $this->newLine();
        $this->info("PASO 3: Agregar primer lote parcial (120 kg - 40%)");

        $batch1Data = [
            'reception_date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity_received' => 120,
                    'condition' => 'good',
                    'expiration_date' => now()->addMonths(12)->format('Y-m-d'),
                    'observations' => 'Primer lote - 40%'
                ]
            ],
            'observations' => 'Lote 1 de 3'
        ];

        $response = Http::withToken($this->token)
            ->post("{$this->baseUrl}/api/receptions/{$reception['id']}/batches", $batch1Data);

        if ($response->successful()) {
            $this->info("   ✓ Lote 1 agregado: 120 kg recibidos");

            // Verificar inventario
            $inventory = Inventory::where('product_id', $product->id)
                ->where('location_id', $warehouse->id)
                ->where('status', 'good')
                ->sum('quantity');

            $this->line("   📊 Inventario actual en {$warehouse->name}: {$inventory} kg");
        } else {
            $this->error("   ✗ Error agregando lote 1: " . $response->body());
        }

        // PASO 4: Agregar segundo lote (30% = 90 kg)
        $this->newLine();
        $this->info("PASO 4: Agregar segundo lote parcial (90 kg - 30%)");

        $batch2Data = [
            'reception_date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity_received' => 90,
                    'condition' => 'good',
                    'expiration_date' => now()->addMonths(18)->format('Y-m-d'),
                    'observations' => 'Segundo lote - 30%'
                ]
            ],
            'observations' => 'Lote 2 de 3'
        ];

        $response = Http::withToken($this->token)
            ->post("{$this->baseUrl}/api/receptions/{$reception['id']}/batches", $batch2Data);

        if ($response->successful()) {
            $this->info("   ✓ Lote 2 agregado: 90 kg recibidos");

            $inventory = Inventory::where('product_id', $product->id)
                ->where('location_id', $warehouse->id)
                ->where('status', 'good')
                ->sum('quantity');

            $this->line("   📊 Inventario actual en {$warehouse->name}: {$inventory} kg");
            $this->line("   Total recibido hasta ahora: 210 kg (70%)");
        } else {
            $this->error("   ✗ Error agregando lote 2: " . $response->body());
        }

        // PASO 5: Consultar productos pendientes (API NUEVA)
        $this->newLine();
        $this->info("PASO 5: Consultar productos pendientes (API v1.1)");

        $response = Http::withToken($this->token)
            ->get("{$this->baseUrl}/api/receptions/{$reception['id']}/pending-products");

        if ($response->successful()) {
            $pendingProducts = $response->json()['data'];
            $this->info("   ✓ API pending-products funcionando");

            if (count($pendingProducts) > 0) {
                foreach ($pendingProducts as $item) {
                    $this->line("   - {$item['product_name']}: {$item['quantity_pending']} kg pendientes");
                }
            } else {
                $this->warn("   ⚠ No hay productos pendientes");
            }
        } else {
            $this->error("   ✗ Error consultando pendientes");
        }

        // PASO 6: Agregar tercer lote final (30% = 90 kg, 60 good + 30 damaged)
        $this->newLine();
        $this->info("PASO 6: Agregar lote final (90 kg - 60 good + 30 damaged)");

        $batch3Data = [
            'reception_date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity_received' => 60,
                    'condition' => 'good',
                    'expiration_date' => now()->addMonths(15)->format('Y-m-d'),
                    'observations' => 'Tercer lote - parte buena'
                ],
                [
                    'product_id' => $product->id,
                    'quantity_received' => 30,
                    'condition' => 'damaged',
                    'observations' => 'Tercer lote - dañado'
                ]
            ],
            'observations' => 'Lote 3 de 3 - FINAL'
        ];

        $response = Http::withToken($this->token)
            ->post("{$this->baseUrl}/api/receptions/{$reception['id']}/batches", $batch3Data);

        if ($response->successful()) {
            $this->info("   ✓ Lote 3 agregado: 60 kg good + 30 kg damaged");

            $inventory = Inventory::where('product_id', $product->id)
                ->where('location_id', $warehouse->id)
                ->where('status', 'good')
                ->sum('quantity');

            $this->line("   📊 Inventario final en {$warehouse->name}: {$inventory} kg");
            $this->line("   ⚠ 30 kg damaged NO están en inventario disponible");
        } else {
            $this->error("   ✗ Error agregando lote 3: " . $response->body());
        }

        // PASO 7: Verificaciones finales
        $this->newLine();
        $this->info("PASO 7: Verificaciones finales");

        // Verificar recepción completada
        $reception = Reception::find($reception['id']);
        $this->line("   Estado de recepción: {$reception->status}");
        $this->line("   Porcentaje completado: {$reception->completion_percentage}%");

        // Verificar movimientos
        $movements = InventoryMovement::where('product_id', $product->id)
            ->where('location_id', $warehouse->id)
            ->where('type', 'entry')
            ->count();
        $this->line("   Movimientos de entrada registrados: {$movements}");

        // Verificar lotes en inventario
        $batches = Inventory::where('product_id', $product->id)
            ->where('location_id', $warehouse->id)
            ->where('status', 'good')
            ->count();
        $this->line("   Lotes diferentes en inventario: {$batches}");

        $this->newLine();
        $this->info("✅ ESCENARIO 1 COMPLETADO");
        $this->results['scenario1']['status'] = 'completed';
    }

    private function runScenario2($masterData): void
    {
        $this->newLine();
        $this->info("═══════════════════════════════════════════════════════");
        $this->info("🎯 ESCENARIO 2: COMPRA + RECEPCIÓN TOTAL + SALIDA");
        $this->info("═══════════════════════════════════════════════════════");
        $this->newLine();

        $warehouse = $masterData['warehouses']->skip(1)->first() ?? $masterData['warehouses']->first();
        $farm = $masterData['farms']->first();
        $supplier = $masterData['suppliers']->skip(1)->first() ?? $masterData['suppliers']->first();
        $product = $masterData['products']->skip(1)->first() ?? $masterData['products']->first();

        $this->info("📋 Datos del escenario:");
        $this->line("   Bodega origen: {$warehouse->name}");
        $this->line("   Finca destino: {$farm->name}");
        $this->line("   Producto: {$product->name}");
        $this->newLine();

        // PASO 1: Crear compra
        $this->info("PASO 1: Crear compra de 200 kg");

        $purchaseData = [
            'supplier_id' => $supplier->id,
            'location_id' => $warehouse->id,
            'expected_date' => now()->addDays(5)->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => $product->id,
                    'brand_id' => $product->brand_id,
                    'quantity' => 200,
                    'unit' => 'kg',
                    'unit_price' => 45000,
                ]
            ],
        ];

        $response = Http::withToken($this->token)
            ->post("{$this->baseUrl}/api/purchases", $purchaseData);

        if (!$response->successful()) {
            $this->error("   ✗ Error creando compra");
            return;
        }

        $purchase = $response->json()['data'];
        $this->info("   ✓ Compra creada: {$purchase['purchase_number']}");

        // PASO 2: Crear recepción
        $this->newLine();
        $this->info("PASO 2: Crear recepción");

        $receptionData = [
            'reception_number' => 'REC-TOTAL-' . time(),
            'source_type' => 'purchase',
            'source_id' => $purchase['id'],
            'origin_location_id' => $supplier->id,
            'destination_location_id' => $warehouse->id,
            'shipment_date' => now()->format('Y-m-d'),
        ];

        $response = Http::withToken($this->token)
            ->post("{$this->baseUrl}/api/receptions", $receptionData);

        if (!$response->successful()) {
            $this->error("   ✗ Error creando recepción");
            return;
        }

        $reception = $response->json()['data'];
        $this->info("   ✓ Recepción creada: {$reception['reception_number']}");

        // PASO 3: Recibir TODO en un solo lote
        $this->newLine();
        $this->info("PASO 3: Recibir 200 kg completos en un solo lote");

        $batchData = [
            'reception_date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity_received' => 200,
                    'condition' => 'good',
                    'expiration_date' => now()->addMonths(10)->format('Y-m-d'),
                ]
            ],
        ];

        $response = Http::withToken($this->token)
            ->post("{$this->baseUrl}/api/receptions/{$reception['id']}/batches", $batchData);

        if ($response->successful()) {
            $this->info("   ✓ 200 kg recibidos en un solo lote");

            $inventory = Inventory::where('product_id', $product->id)
                ->where('location_id', $warehouse->id)
                ->where('status', 'good')
                ->sum('quantity');

            $this->line("   📊 Inventario en {$warehouse->name}: {$inventory} kg");
        } else {
            $this->error("   ✗ Error en recepción");
        }

        // PASO 4: Crear salida a finca
        $this->newLine();
        $this->info("PASO 4: Crear salida de 80 kg a finca (+ 5% = 84 kg)");

        $outputData = [
            'output_number' => 'SAL-TEST-' . time(),
            'origin_location_id' => $warehouse->id,
            'destination_location_id' => $farm->id,
            'shipment_date' => now()->format('Y-m-d'),
            'products' => [
                [
                    'product_id' => $product->id,
                    'brand_id' => $product->brand_id,
                    'quantity' => 80,
                    'quantity_to_deliver' => 84, // 80 + 5%
                    'unit' => 'kg',
                ]
            ],
        ];

        $response = Http::withToken($this->token)
            ->post("{$this->baseUrl}/api/product-outputs", $outputData);

        if ($response->successful()) {
            $output = $response->json()['data'];
            $this->info("   ✓ Salida creada: {$output['output_number']}");
            $this->line("   Estado: {$output['status']}");
            $this->results['scenario2']['output_id'] = $output['id'];
        } else {
            $this->error("   ✗ Error creando salida: " . $response->body());
            return;
        }

        // PASO 5: Aprobar salida (reduce inventario FIFO)
        $this->newLine();
        $this->info("PASO 5: Aprobar salida (reduce inventario con FIFO)");

        $response = Http::withToken($this->token)
            ->post("{$this->baseUrl}/api/product-outputs/{$output['id']}/approve");

        if ($response->successful()) {
            $this->info("   ✓ Salida aprobada");

            $inventory = Inventory::where('product_id', $product->id)
                ->where('location_id', $warehouse->id)
                ->where('status', 'good')
                ->sum('quantity');

            $this->line("   📊 Inventario restante en {$warehouse->name}: {$inventory} kg");
            $this->line("   Esperado: 116 kg (200 - 84)");

            // Verificar alertas
            $alerts = Alert::where('product_id', $product->id)
                ->where('location_id', $warehouse->id)
                ->where('status', 'pending')
                ->count();

            if ($alerts > 0) {
                $this->warn("   ⚠ {$alerts} alertas creadas (posiblemente stock bajo)");
            }
        } else {
            $this->error("   ✗ Error aprobando salida: " . $response->body());
        }

        $this->newLine();
        $this->info("✅ ESCENARIO 2 COMPLETADO");
    }

    private function runScenario3($masterData): void
    {
        $this->newLine();
        $this->info("═══════════════════════════════════════════════════════");
        $this->info("🎯 ESCENARIO 3: MÚLTIPLES UBICACIONES Y TRASLADOS");
        $this->info("═══════════════════════════════════════════════════════");
        $this->newLine();

        // Mostrar estado actual del inventario
        $this->info("📊 Estado actual del inventario por ubicación:");

        foreach ($masterData['locations'] as $location) {
            $totalInventory = Inventory::where('location_id', $location->id)
                ->where('status', 'good')
                ->sum('quantity');

            $this->line("   {$location->name}: {$totalInventory} kg total");
        }

        $this->newLine();
        $this->info("✅ ESCENARIO 3 COMPLETADO (Consulta)");
    }

    private function runSearchTests($masterData): void
    {
        $this->newLine();
        $this->info("═══════════════════════════════════════════════════════");
        $this->info("🔍 PRUEBAS DE BÚSQUEDA Y CONSULTAS (APIs v1.1)");
        $this->info("═══════════════════════════════════════════════════════");
        $this->newLine();

        $warehouse = $masterData['warehouses']->first();
        $product = $masterData['products']->first();

        // TEST 1: Búsqueda de productos con inventario
        $this->info("TEST 1: POST /api/products/search-with-inventory");

        $response = Http::withToken($this->token)
            ->post("{$this->baseUrl}/api/products/search-with-inventory", [
                'location_id' => $warehouse->id,
                'search' => substr($product->name, 0, 5)
            ]);

        if ($response->successful()) {
            $data = $response->json();
            $this->info("   ✓ API funcionando");
            $this->line("   Productos con stock encontrados: " . $data['count']);

            if ($data['count'] > 0) {
                foreach ($data['data'] as $item) {
                    $this->line("   - {$item['name']}: {$item['total_available']} disponibles");
                }
            }
        } else {
            $this->error("   ✗ Error: " . $response->body());
        }

        // TEST 2: Validación de inventario pre-salida
        $this->newLine();
        $this->info("TEST 2: POST /api/product-outputs/validate-inventory");

        $response = Http::withToken($this->token)
            ->post("{$this->baseUrl}/api/product-outputs/validate-inventory", [
                'location_id' => $warehouse->id,
                'products' => [
                    [
                        'product_id' => $product->id,
                        'brand_id' => $product->brand_id,
                        'quantity' => 1000 // Cantidad alta para probar validación
                    ]
                ]
            ]);

        if ($response->successful()) {
            $data = $response->json();
            $this->info("   ✓ API funcionando");
            $this->line("   Validación general: " . ($data['valid'] ? 'VÁLIDO' : 'INVÁLIDO'));

            foreach ($data['data'] as $item) {
                $status = $item['sufficient'] ? '✓' : '✗';
                $this->line("   {$status} {$item['product_name']}: {$item['available']} disponibles (solicitado: {$item['requested']})");

                if (!$item['sufficient']) {
                    $this->warn("      Faltan: {$item['deficit']} unidades");
                }
            }
        } else {
            $this->error("   ✗ Error: " . $response->body());
        }

        $this->newLine();
        $this->info("✅ PRUEBAS DE BÚSQUEDA COMPLETADAS");
    }

    private function displayResults(): void
    {
        $this->newLine();
        $this->info("═══════════════════════════════════════════════════════");
        $this->info("📊 RESUMEN DE RESULTADOS");
        $this->info("═══════════════════════════════════════════════════════");
        $this->newLine();

        // Estadísticas generales
        $totalPurchases = Purchase::count();
        $totalReceptions = Reception::count();
        $totalOutputs = ProductOutput::count();
        $totalInventory = Inventory::where('status', 'good')->sum('quantity');
        $totalMovements = InventoryMovement::count();
        $totalAlerts = Alert::where('status', 'pending')->count();

        $this->line("Compras totales: {$totalPurchases}");
        $this->line("Recepciones totales: {$totalReceptions}");
        $this->line("Salidas totales: {$totalOutputs}");
        $this->line("Inventario total (good): {$totalInventory} kg");
        $this->line("Movimientos registrados: {$totalMovements}");
        $this->line("Alertas pendientes: {$totalAlerts}");

        $this->newLine();
        $this->info("✅ TODAS LAS PRUEBAS COMPLETADAS");
    }
}
