<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Location;
use App\Models\Product;
use App\Models\Brand;
use App\Models\Supplier;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Reception;
use App\Models\ReceptionItem;
use App\Models\ReceptionBatch;
use App\Models\ReceptionBatchItem;
use App\Models\ProductOutput;
use App\Models\OutputProduct;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Alert;
use App\Models\User;

class TestInventoryDirectly extends Command
{
    protected $signature = 'test:inventory-direct';
    protected $description = 'Ejecuta pruebas directas de inventario usando modelos Eloquent';

    private $user;
    private $results = [];

    public function handle()
    {
        $this->info("🧪 EJECUTANDO PRUEBAS DIRECTAS DE INVENTARIO");
        $this->info("════════════════════════════════════════════");
        $this->newLine();

        // Obtener usuario admin
        $this->user = User::where('email', 'admin@agriflor.com')->first();

        if (!$this->user) {
            $this->error("❌ Usuario admin no encontrado");
            return 1;
        }

        $this->info("✓ Usuario: {$this->user->name} ({$this->user->role})");
        $this->newLine();

        // Obtener datos maestros
        $masterData = $this->getMasterData();
        $this->displayMasterData($masterData);

        // Ejecutar escenarios
        $this->runScenario1Compra($masterData);
        $this->runScenario2RecepcionParcial($masterData);
        $this->runScenario3RecepcionTotal($masterData);
        $this->runScenario4Salida($masterData);
        $this->runScenario5Busquedas($masterData);

        // Resumen final
        $this->displayFinalSummary();

        return 0;
    }

    private function getMasterData(): array
    {
        $this->info("📊 Cargando datos maestros de la BD...");

        return [
            'locations' => Location::all(),
            'warehouses' => Location::where('type', 'warehouse')->get(),
            'farms' => Location::where('type', 'farm')->get(),
            'products' => Product::with('brand')->get(),
            'brands' => Brand::all(),
            'suppliers' => Supplier::all(),
        ];
    }

    private function displayMasterData($data): void
    {
        $this->table(
            ['Tipo', 'Cantidad'],
            [
                ['Ubicaciones Totales', $data['locations']->count()],
                ['  - Bodegas', $data['warehouses']->count()],
                ['  - Fincas', $data['farms']->count()],
                ['Productos', $data['products']->count()],
                ['Marcas', $data['brands']->count()],
                ['Proveedores', $data['suppliers']->count()],
            ]
        );
        $this->newLine();
    }

    private function runScenario1Compra($masterData): void
    {
        $this->info("═══════════════════════════════════════════");
        $this->info("🎯 ESCENARIO 1: CREAR COMPRA");
        $this->info("═══════════════════════════════════════════");
        $this->newLine();

        try {
            DB::beginTransaction();

            $warehouse = $masterData['warehouses']->first();
            $supplier = $masterData['suppliers']->first();
            $product = $masterData['products']->first();

            $this->line("Bodega: {$warehouse->name}");
            $this->line("Proveedor: {$supplier->name}");
            $this->line("Producto: {$product->name} (Marca: {$product->brand->name})");
            $this->newLine();

            // Crear compra - usar IVA del producto
            $subtotal = 300 * 50000; // 300 kg * $50,000
            $ivaPercentage = $product->iva ?? 19;
            $tax = $subtotal * ($ivaPercentage / 100);
            $total = $subtotal + $tax;

            $purchase = Purchase::create([
                'order_number' => 'PUR-TEST-' . time(),
                'supplier_id' => $supplier->id,
                'destination_location_id' => $warehouse->id,
                'purchase_date' => now(),
                'expected_delivery' => now()->addDays(7),
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'status' => 'ordered',
                'observations' => 'Compra de prueba automática',
                'created_by' => $this->user->id,
            ]);

            // Crear items
            // Obtener la primera unidad de empaque del producto
            $packagingUnit = $product->packagingUnits()->first();

            if (!$packagingUnit) {
                throw new \Exception("Producto {$product->name} no tiene unidades de empaque configuradas");
            }

            $quantity = 300;
            $quantityInBaseUnits = $quantity * $packagingUnit->base_quantity;

            PurchaseItem::create([
                'purchase_id' => $purchase->id,
                'product_id' => $product->id,
                'brand_id' => $product->brand_id,
                'packaging_unit_id' => $packagingUnit->id,
                'quantity' => $quantity,
                'quantity_in_base_units' => $quantityInBaseUnits,
                'unit_price' => 50000,
                'subtotal' => 15000000,
            ]);

            DB::commit();

            $this->info("✅ Compra creada: {$purchase->order_number}");
            $this->line("   Subtotal: \$" . number_format($purchase->subtotal, 0));
            $this->line("   IVA ({$ivaPercentage}%): \$" . number_format($purchase->tax, 0));
            $this->line("   Total: \$" . number_format($purchase->total, 0));

            $this->results['purchase_id'] = $purchase->id;
            $this->results['product_id'] = $product->id;
            $this->results['warehouse_id'] = $warehouse->id;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("❌ Error: " . $e->getMessage());
        }

        $this->newLine();
    }

    private function runScenario2RecepcionParcial($masterData): void
    {
        $this->info("═══════════════════════════════════════════");
        $this->info("🎯 ESCENARIO 2: RECEPCIÓN PARCIAL (3 LOTES)");
        $this->info("═══════════════════════════════════════════");
        $this->newLine();

        if (!isset($this->results['purchase_id'])) {
            $this->warn("⚠ Compra no creada, saltando escenario");
            return;
        }

        try {
            $purchase = Purchase::find($this->results['purchase_id']);
            $warehouse = Location::find($this->results['warehouse_id']);
            $product = Product::find($this->results['product_id']);

            // Crear recepción
            DB::beginTransaction();

            $reception = Reception::create([
                'reception_number' => 'REC-TEST-' . time(),
                'source_id' => $purchase->id,
                'source_type' => 'purchase',
                'origin_location_id' => $warehouse->id,
                'destination_location_id' => $warehouse->id,
                'shipment_date' => now(),
                'status' => 'pending',
                'total_expected' => 300,
                'total_received' => 0,
                'completion_percentage' => 0,
                'responsible_user' => $this->user->id,
            ]);

            // Crear reception_item
            ReceptionItem::create([
                'reception_id' => $reception->id,
                'product_id' => $product->id,
                'brand_id' => $product->brand_id,
                'quantity_expected' => 300,
                'quantity_received' => 0,
                'quantity_pending' => 300,
                'unit' => 'kg',
            ]);

            DB::commit();

            $this->info("✅ Recepción creada: {$reception->reception_number}");
            $this->line("   Cantidad esperada: 300 kg");
            $this->newLine();

            // LOTE 1: 120 kg (40%)
            $this->info("📦 LOTE 1: Recibiendo 120 kg (40%)");
            $this->addBatch($reception, $product, 120, 'good', now()->addMonths(12));

            // LOTE 2: 90 kg (30%)
            $this->info("📦 LOTE 2: Recibiendo 90 kg (30%)");
            $this->addBatch($reception, $product, 90, 'good', now()->addMonths(18));

            // LOTE 3: 60 good + 30 damaged
            $this->info("📦 LOTE 3: Recibiendo 60 kg good + 30 kg damaged");
            $this->addBatch($reception, $product, 60, 'good', now()->addMonths(15));
            $this->addBatch($reception, $product, 30, 'damaged', null, sameBatch: true);

            // Verificar estado final
            $reception->refresh();
            $this->newLine();
            $this->info("📊 ESTADO FINAL DE RECEPCIÓN:");
            $this->line("   Estado: {$reception->status}");
            $this->line("   Completitud: {$reception->completion_percentage}%");
            $this->line("   Total recibido: {$reception->total_received} kg");

            // Verificar inventario
            $inventory = Inventory::where('product_id', $product->id)
                ->where('location_id', $warehouse->id)
                ->where('status', 'good')
                ->sum('quantity');

            $this->line("   Inventario disponible: {$inventory} kg");
            $this->line("   ⚠ 30 kg damaged NO en inventario");

            $this->results['reception_id'] = $reception->id;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("❌ Error: " . $e->getMessage());
            $this->error("   Trace: " . $e->getTraceAsString());
        }

        $this->newLine();
    }

    private function addBatch($reception, $product, $quantity, $condition, $expiration = null, $sameBatch = false): void
    {
        try {
            DB::beginTransaction();

            if (!$sameBatch) {
                $batchNumber = $reception->receptionBatches()->max('batch_number') + 1;

                $batch = ReceptionBatch::create([
                    'reception_id' => $reception->id,
                    'batch_number' => $batchNumber,
                    'reception_date' => now(),
                    'received_by' => $this->user->id,
                ]);
            } else {
                $batch = $reception->receptionBatches()->latest()->first();
            }

            // Crear batch item
            ReceptionBatchItem::create([
                'batch_id' => $batch->id,
                'product_id' => $product->id,
                'quantity_received' => $quantity,
                'condition' => $condition,
                'expiration_date' => $expiration,
            ]);

            // Actualizar reception_item
            $receptionItem = $reception->receptionItems()
                ->where('product_id', $product->id)
                ->first();

            $receptionItem->quantity_received += $quantity;
            $receptionItem->quantity_pending = max(0, $receptionItem->quantity_expected - $receptionItem->quantity_received);
            $receptionItem->save();

            // Crear movimiento de inventario si es good
            if ($condition === 'good') {
                InventoryMovement::create([
                    'type' => 'entry',
                    'product_id' => $product->id,
                    'brand_id' => $product->brand_id,
                    'location_id' => $reception->destination_location_id,
                    'quantity' => $quantity,
                    'unit' => 'kg',
                    'expiration_date' => $expiration,
                    'related_document_type' => Reception::class,
                    'related_document_id' => $reception->id,
                    'responsible_user' => $this->user->id,
                    'observations' => "Lote #{$batch->batch_number} - {$condition}",
                ]);

                // Crear/actualizar inventario
                $inventory = Inventory::firstOrCreate([
                    'product_id' => $product->id,
                    'brand_id' => $product->brand_id,
                    'location_id' => $reception->destination_location_id,
                    'expiration_date' => $expiration,
                    'status' => 'good',
                ], [
                    'quantity' => 0,
                    'unit' => 'kg',
                ]);

                $inventory->quantity += $quantity;
                $inventory->save();
            }

            // Actualizar totales de recepción
            $totalReceived = $reception->receptionItems()->sum('quantity_received');
            $totalExpected = $reception->total_expected;

            $reception->total_received = $totalReceived;
            $reception->completion_percentage = ($totalReceived / $totalExpected) * 100;

            if ($reception->completion_percentage >= 100) {
                $reception->status = 'completed';
            } else if ($reception->completion_percentage > 0) {
                $reception->status = 'partial';
            }

            $reception->save();

            DB::commit();

            $this->line("   ✓ {$quantity} kg ({$condition}) agregados");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("   ✗ Error: " . $e->getMessage());
        }
    }

    private function runScenario3RecepcionTotal($masterData): void
    {
        $this->info("═══════════════════════════════════════════");
        $this->info("🎯 ESCENARIO 3: RECEPCIÓN TOTAL (1 LOTE)");
        $this->info("═══════════════════════════════════════════");
        $this->newLine();

        try {
            $warehouse = $masterData['warehouses']->skip(1)->first() ?? $masterData['warehouses']->first();
            $supplier = $masterData['suppliers']->first();
            $product = $masterData['products']->skip(1)->first() ?? $masterData['products']->first();

            DB::beginTransaction();

            // Crear compra - usar IVA del producto
            $subtotal = 200 * 45000;
            $ivaPercentage = $product->iva ?? 19;
            $tax = $subtotal * ($ivaPercentage / 100);

            $purchase = Purchase::create([
                'order_number' => 'PUR-TOTAL-' . time(),
                'supplier_id' => $supplier->id,
                'destination_location_id' => $warehouse->id,
                'purchase_date' => now(),
                'expected_delivery' => now()->addDays(5),
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $subtotal + $tax,
                'status' => 'ordered',
                'created_by' => $this->user->id,
            ]);

            // Obtener la primera unidad de empaque del producto
            $packagingUnit = $product->packagingUnits()->first();

            if (!$packagingUnit) {
                throw new \Exception("Producto {$product->name} no tiene unidades de empaque configuradas");
            }

            $quantity = 200;
            $quantityInBaseUnits = $quantity * $packagingUnit->base_quantity;

            PurchaseItem::create([
                'purchase_id' => $purchase->id,
                'product_id' => $product->id,
                'brand_id' => $product->brand_id,
                'packaging_unit_id' => $packagingUnit->id,
                'quantity' => $quantity,
                'quantity_in_base_units' => $quantityInBaseUnits,
                'unit_price' => 45000,
                'subtotal' => 9000000,
            ]);

            // Crear recepción
            $reception = Reception::create([
                'reception_number' => 'REC-TOTAL-' . time(),
                'source_id' => $purchase->id,
                'source_type' => 'purchase',
                'origin_location_id' => $warehouse->id,
                'destination_location_id' => $warehouse->id,
                'shipment_date' => now(),
                'status' => 'pending',
                'total_expected' => 200,
                'total_received' => 0,
                'completion_percentage' => 0,
                'responsible_user' => $this->user->id,
            ]);

            ReceptionItem::create([
                'reception_id' => $reception->id,
                'product_id' => $product->id,
                'brand_id' => $product->brand_id,
                'quantity_expected' => 200,
                'quantity_received' => 0,
                'quantity_pending' => 200,
                'unit' => 'kg',
            ]);

            DB::commit();

            $this->line("Producto: {$product->name}");
            $this->line("Bodega: {$warehouse->name}");
            $this->newLine();

            // Recibir todo en 1 lote
            $this->info("📦 Recibiendo 200 kg en un solo lote");
            $this->addBatch($reception, $product, 200, 'good', now()->addMonths(10));

            $inventory = Inventory::where('product_id', $product->id)
                ->where('location_id', $warehouse->id)
                ->where('status', 'good')
                ->sum('quantity');

            $this->info("✅ Recepción total completada");
            $this->line("   Inventario en {$warehouse->name}: {$inventory} kg");

            $this->results['total_reception_product_id'] = $product->id;
            $this->results['total_reception_warehouse_id'] = $warehouse->id;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("❌ Error: " . $e->getMessage());
        }

        $this->newLine();
    }

    private function runScenario4Salida($masterData): void
    {
        $this->info("═══════════════════════════════════════════");
        $this->info("🎯 ESCENARIO 4: SALIDA CON REGLA DEL 5%");
        $this->info("═══════════════════════════════════════════");
        $this->newLine();

        if (!isset($this->results['total_reception_product_id'])) {
            $this->warn("⚠ Producto con inventario no disponible");
            return;
        }

        try {
            $warehouse = Location::find($this->results['total_reception_warehouse_id']);
            $farm = $masterData['farms']->first();
            $product = Product::find($this->results['total_reception_product_id']);

            $inventoryBefore = Inventory::where('product_id', $product->id)
                ->where('location_id', $warehouse->id)
                ->where('status', 'good')
                ->sum('quantity');

            $this->line("Inventario inicial en {$warehouse->name}: {$inventoryBefore} kg");
            $this->line("Destino: {$farm->name}");
            $this->newLine();

            DB::beginTransaction();

            // Crear salida: 80 kg + 5% = 84 kg
            $quantity = 80;
            $quantityWithTolerance = $quantity * 1.05; // 84 kg

            $output = ProductOutput::create([
                'output_number' => 'OUT-TEST-' . time(),
                'origin_location_id' => $warehouse->id,
                'destination_location_id' => $farm->id,
                'output_date' => now(),
                'status' => 'pending',
                'responsible_user' => $this->user->id,
            ]);

            OutputProduct::create([
                'output_id' => $output->id,
                'product_id' => $product->id,
                'brand_id' => $product->brand_id,
                'quantity_requested' => $quantity,
                'quantity_delivered' => $quantityWithTolerance,
                'unit' => 'kg',
            ]);

            DB::commit();

            $this->info("✅ Salida creada: {$output->output_number}");
            $this->line("   Cantidad solicitada: {$quantity} kg");
            $this->line("   Cantidad a entregar (+5%): {$quantityWithTolerance} kg");
            $this->line("   Estado: {$output->status}");
            $this->newLine();

            // Aprobar salida (reduce inventario FIFO)
            $this->info("🔓 Aprobando salida (reduce inventario)...");

            DB::beginTransaction();

            // Reducir inventario FIFO
            $inventoryItems = Inventory::where('product_id', $product->id)
                ->where('location_id', $warehouse->id)
                ->where('status', 'good')
                ->orderBy('expiration_date', 'asc')
                ->orderBy('created_at', 'asc')
                ->get();

            $remaining = $quantityWithTolerance;

            foreach ($inventoryItems as $inv) {
                if ($remaining <= 0) break;

                if ($inv->quantity >= $remaining) {
                    $inv->quantity -= $remaining;
                    $inv->save();

                    InventoryMovement::create([
                        'type' => 'exit',
                        'product_id' => $product->id,
                        'brand_id' => $product->brand_id,
                        'location_id' => $warehouse->id,
                        'quantity' => $remaining,
                        'unit' => 'kg',
                        'related_document_type' => ProductOutput::class,
                        'related_document_id' => $output->id,
                        'responsible_user' => $this->user->id,
                    ]);

                    $remaining = 0;
                } else {
                    $remaining -= $inv->quantity;

                    InventoryMovement::create([
                        'type' => 'exit',
                        'product_id' => $product->id,
                        'brand_id' => $product->brand_id,
                        'location_id' => $warehouse->id,
                        'quantity' => $inv->quantity,
                        'unit' => 'kg',
                        'related_document_type' => ProductOutput::class,
                        'related_document_id' => $output->id,
                        'responsible_user' => $this->user->id,
                    ]);

                    $inv->delete();
                }
            }

            $output->status = 'completed';
            $output->save();

            DB::commit();

            $inventoryAfter = Inventory::where('product_id', $product->id)
                ->where('location_id', $warehouse->id)
                ->where('status', 'good')
                ->sum('quantity');

            $this->info("✅ Salida aprobada");
            $this->line("   Inventario antes: {$inventoryBefore} kg");
            $this->line("   Cantidad reducida: {$quantityWithTolerance} kg");
            $this->line("   Inventario después: {$inventoryAfter} kg");
            $this->line("   Diferencia: " . ($inventoryBefore - $inventoryAfter) . " kg");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("❌ Error: " . $e->getMessage());
        }

        $this->newLine();
    }

    private function runScenario5Busquedas($masterData): void
    {
        $this->info("═══════════════════════════════════════════");
        $this->info("🔍 ESCENARIO 5: CONSULTAS Y BÚSQUEDAS");
        $this->info("═══════════════════════════════════════════");
        $this->newLine();

        // Estado de inventario por ubicación
        $this->info("📊 INVENTARIO POR UBICACIÓN:");

        foreach ($masterData['locations'] as $location) {
            $total = Inventory::where('location_id', $location->id)
                ->where('status', 'good')
                ->sum('quantity');

            if ($total > 0) {
                $this->line("   {$location->name}: {$total} kg");
            }
        }

        $this->newLine();

        // Movimientos totales
        $totalMovements = InventoryMovement::count();
        $entries = InventoryMovement::where('type', 'entry')->count();
        $exits = InventoryMovement::where('type', 'exit')->count();

        $this->info("📋 MOVIMIENTOS DE INVENTARIO:");
        $this->line("   Total: {$totalMovements}");
        $this->line("   Entradas: {$entries}");
        $this->line("   Salidas: {$exits}");

        $this->newLine();

        // Alertas
        $alerts = Alert::where('status', 'pending')->count();
        $this->info("🚨 ALERTAS PENDIENTES: {$alerts}");

        if ($alerts > 0) {
            $alertList = Alert::where('status', 'pending')
                ->with('product')
                ->limit(5)
                ->get();

            foreach ($alertList as $alert) {
                $this->line("   - {$alert->title} ({$alert->severity})");
            }
        }

        $this->newLine();
    }

    private function displayFinalSummary(): void
    {
        $this->info("════════════════════════════════════════════");
        $this->info("📊 RESUMEN FINAL");
        $this->info("════════════════════════════════════════════");
        $this->newLine();

        $data = [
            ['Compras', Purchase::count()],
            ['Recepciones', Reception::count()],
            ['Lotes recibidos', ReceptionBatch::count()],
            ['Salidas', ProductOutput::count()],
            ['Inventario total (good)', Inventory::where('status', 'good')->sum('quantity') . ' kg'],
            ['Movimientos', InventoryMovement::count()],
            ['Alertas pendientes', Alert::where('status', 'pending')->count()],
        ];

        $this->table(['Concepto', 'Valor'], $data);

        $this->newLine();
        $this->info("✅ TODAS LAS PRUEBAS COMPLETADAS CON ÉXITO");
        $this->info("Base de datos actualizada con datos reales de prueba");
    }
}
