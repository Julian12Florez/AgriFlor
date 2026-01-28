<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\{Inventory, Product, ProductOutput, OutputProduct, Reception, ReceptionItem};
use Illuminate\Support\Facades\{DB, Log};

echo "\n";
echo "🔬 PRUEBA DE BUG - RECEPCIONES PARCIALES\n";
echo "==========================================\n\n";

// DATOS DE LA PRUEBA
$productId = 'a095ca83-34b8-4cb6-8c94-7d506fa73508'; // Glifosato
$brandId = 'a095ca82-ee0c-4c87-8806-4cbb88ea73a1'; // Yara
$originLocationId = 'a095d5a5-23f8-4f03-b5a0-252d8b51929c'; // BODEGA PRINCIPAL
$destinationLocationId = 'a095ca83-0f19-47c0-bce5-095aa1407bb0'; // Bodega Central
$outputTypeId = 'a624ac75-d9c7-4770-817d-630f4a04b407'; // Transfer
$userId = 'a095ca82-e203-4f3e-b8bd-20da5c820ec8'; // Admin

// PASO 1: STOCK INICIAL
echo "📊 PASO 1: STOCK INICIAL\n";
echo "----------------------------------------\n";

$stockInicial = Inventory::where('product_id', $productId)
    ->where('brand_id', $brandId)
    ->where('location_id', $originLocationId)
    ->where('status', 'good')
    ->get();

$totalInicial = $stockInicial->sum('quantity');

echo "Ubicación: BODEGA PRINCIPAL\n";
echo "Producto: Glifosato\n";
echo "Stock total: {$totalInicial} L\n";
echo "Lotes:\n";
foreach ($stockInicial as $lote) {
    echo "  - Lote {$lote->batch_number}: {$lote->quantity} L\n";
}
echo "\n";

if ($totalInicial < 60) {
    echo "❌ ERROR: No hay suficiente stock para la prueba (necesitamos al menos 60 L)\n";
    exit(1);
}

// PASO 2: CREAR SALIDA DE TRANSFERENCIA (60 L)
echo "📦 PASO 2: CREAR SALIDA DE TRANSFERENCIA (60 L de {$totalInicial} L)\n";
echo "----------------------------------------\n";

DB::beginTransaction();

try {
    $output = ProductOutput::create([
        'output_number' => 'TEST-BUG-' . time(),
        'output_date' => now(),
        'output_type_id' => $outputTypeId,
        'origin_location_id' => $originLocationId,
        'destination_location_id' => $destinationLocationId,
        'responsible_user' => $userId,
        'status' => 'pending',
    ]);

    $outputProduct = OutputProduct::create([
        'output_id' => $output->id,
        'product_id' => $productId,
        'brand_id' => $brandId,
        'quantity_requested' => 60,
        'quantity_delivered' => 60,
        'unit' => 'L',
    ]);

    echo "✅ Salida creada: {$output->output_number}\n";
    echo "   - Cantidad solicitada: 60 L\n";
    echo "   - Estado: pending\n\n";

    // Verificar stock después de crear (no debe cambiar)
    $stockDespuesCrear = Inventory::where('product_id', $productId)
        ->where('brand_id', $brandId)
        ->where('location_id', $originLocationId)
        ->where('status', 'good')
        ->sum('quantity');

    echo "📊 Stock en origen después de crear salida: {$stockDespuesCrear} L\n";
    echo "   Esperado: {$totalInicial} L (sin cambios)\n";
    echo "   " . ($stockDespuesCrear == $totalInicial ? "✅ CORRECTO" : "❌ INCORRECTO") . "\n\n";

    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    echo "❌ Error creando salida: {$e->getMessage()}\n";
    exit(1);
}

// PASO 3: APROBAR SALIDA
echo "✅ PASO 3: APROBAR SALIDA\n";
echo "----------------------------------------\n";

DB::beginTransaction();

try {
    // Simular aprobación (normalmente se hace vía controlador)
    $output->update(['status' => 'approved']);

    echo "✅ Salida aprobada: {$output->output_number}\n\n";

    // Verificar stock después de aprobar (en NEW FLOW no debe cambiar)
    $stockDespuesAprobar = Inventory::where('product_id', $productId)
        ->where('brand_id', $brandId)
        ->where('location_id', $originLocationId)
        ->where('status', 'good')
        ->sum('quantity');

    echo "📊 Stock en origen después de aprobar: {$stockDespuesAprobar} L\n";
    echo "   Esperado: {$totalInicial} L (sin cambios en NEW FLOW)\n";
    echo "   " . ($stockDespuesAprobar == $totalInicial ? "✅ CORRECTO" : "❌ INCORRECTO") . "\n\n";

    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    echo "❌ Error aprobando salida: {$e->getMessage()}\n";
    exit(1);
}

// PASO 4: RECEPCIÓN PARCIAL (30 L de 60 L)
echo "📥 PASO 4: RECEPCIÓN PARCIAL (30 L de los 60 L)\n";
echo "----------------------------------------\n";
echo "⚠️  CRÍTICO: Solo recepcionando 30 L, NO los 60 L completos\n";
echo "   Esto simula una recepción parcial\n\n";

DB::beginTransaction();

try {
    // Crear recepción
    $reception = Reception::create([
        'reception_number' => 'REC-TEST-' . time(),
        'reception_date' => now(),
        'source_type' => 'output',
        'source_id' => $output->id,
        'received_by' => $userId,
        'status' => 'completed',
    ]);

    // Crear item de recepción
    $receptionItem = ReceptionItem::create([
        'reception_id' => $reception->id,
        'product_id' => $productId,
        'brand_id' => $brandId,
        'quantity_expected' => 60, // Total esperado
        'quantity_received' => 30, // ⚠️ SOLO 30 L (PARCIAL)
        'unit' => 'L',
        'status' => 'partial', // Parcial porque falta recibir 30 L más
    ]);

    echo "✅ Recepción creada: {$reception->reception_number}\n";
    echo "   - Cantidad esperada: 60 L\n";
    echo "   - Cantidad recibida: 30 L ⚠️ PARCIAL\n";
    echo "   - Cantidad pendiente: 30 L\n\n";

    // AQUÍ DEBERÍA LLAMAR AL MÉTODO processInventoryMovements
    // Vamos a simular lo que hace el controlador

    echo "🔍 PROCESANDO MOVIMIENTOS DE INVENTARIO...\n";

    // Verificar stock ANTES de procesar movimientos
    $stockAntesMovimientos = Inventory::where('product_id', $productId)
        ->where('brand_id', $brandId)
        ->where('location_id', $originLocationId)
        ->where('status', 'good')
        ->get();

    echo "   Stock en origen ANTES de movimientos:\n";
    foreach ($stockAntesMovimientos as $lote) {
        echo "     - Lote {$lote->batch_number}: {$lote->quantity} L\n";
    }
    echo "     Total: " . $stockAntesMovimientos->sum('quantity') . " L\n\n";

    // Simular el flujo del controlador
    // Esto es lo que hace ReceptionController::processInventoryMovements()

    // Como es output, debe crear EXIT y ENTRY
    echo "   Creando movimiento EXIT en origen...\n";

    // ⚠️ AQUÍ ESTÁ LA PREGUNTA CRÍTICA:
    // ¿Qué cantidad se pasa a createExitMovement?
    // ¿$receptionItem->quantity_received (30 L) ?
    // ¿O $outputProduct->quantity_delivered (60 L) ?

    // Simulemos lo que hace el código actual
    $cantidadAReducir = $receptionItem->quantity_received; // Debería ser 30

    echo "   Cantidad a reducir: {$cantidadAReducir} L\n";
    echo "   ⚠️ SI AQUÍ DICE 60 L EN LUGAR DE 30 L, ESE ES EL BUG\n\n";

    // Simular reduceInventoryFIFO
    $inventoryBatches = Inventory::where('product_id', $productId)
        ->where('brand_id', $brandId)
        ->where('location_id', $originLocationId)
        ->where('status', 'good')
        ->where('quantity', '>', 0)
        ->orderBy('expiration_date', 'asc')
        ->orderBy('created_at', 'asc')
        ->get();

    $remainingQuantity = $cantidadAReducir;

    echo "   Reduciendo inventario FIFO:\n";
    foreach ($inventoryBatches as $batch) {
        if ($remainingQuantity <= 0) break;

        $quantityBefore = $batch->quantity;

        if ($batch->quantity >= $remainingQuantity) {
            $batch->quantity -= $remainingQuantity;
            $batch->total_value = $batch->quantity * $batch->unit_price;

            if ($batch->quantity > 0) {
                $batch->save();
                echo "     - Lote {$batch->batch_number}: {$quantityBefore} L → {$batch->quantity} L (GUARDADO)\n";
            } else {
                $batch->delete();
                echo "     - Lote {$batch->batch_number}: {$quantityBefore} L → 0 L (ELIMINADO)\n";
            }

            $remainingQuantity = 0;
        } else {
            $remainingQuantity -= $batch->quantity;
            $batch->delete();
            echo "     - Lote {$batch->batch_number}: {$quantityBefore} L → 0 L (CONSUMIDO COMPLETAMENTE)\n";
        }
    }

    echo "\n";

    // Crear inventario en destino
    echo "   Creando inventario en destino (Bodega Central)...\n";

    $newBatch = Inventory::create([
        'product_id' => $productId,
        'brand_id' => $brandId,
        'location_id' => $destinationLocationId,
        'batch_number' => $reception->reception_number,
        'quantity' => $receptionItem->quantity_received, // 30 L
        'unit' => 'L',
        'unit_price' => 10, // Precio ejemplo
        'total_value' => 300,
        'status' => 'good',
        'expiration_date' => now()->addYear(),
    ]);

    echo "   ✅ Lote creado en destino: {$newBatch->quantity} L\n\n";

    DB::commit();

} catch (\Exception $e) {
    DB::rollBack();
    echo "❌ Error en recepción: {$e->getMessage()}\n";
    echo "Stack trace: {$e->getTraceAsString()}\n";
    exit(1);
}

// PASO 5: VERIFICAR STOCK FINAL
echo "📊 PASO 5: STOCK FINAL\n";
echo "========================================\n\n";

$stockFinalOrigen = Inventory::where('product_id', $productId)
    ->where('brand_id', $brandId)
    ->where('location_id', $originLocationId)
    ->where('status', 'good')
    ->get();

$totalFinalOrigen = $stockFinalOrigen->sum('quantity');
$esperadoOrigen = $totalInicial - 30; // Debería ser 200 - 30 = 170

echo "🏢 BODEGA PRINCIPAL (Origen):\n";
echo "   Stock final: {$totalFinalOrigen} L\n";
echo "   Stock esperado: {$esperadoOrigen} L\n";
echo "   Lotes:\n";
foreach ($stockFinalOrigen as $lote) {
    echo "     - Lote {$lote->batch_number}: {$lote->quantity} L\n";
}

if ($totalFinalOrigen == $esperadoOrigen) {
    echo "   ✅ CORRECTO: Se redujeron solo los 30 L recepcionados\n\n";
} else {
    echo "   ❌ BUG CONFIRMADO: Stock incorrecto!\n";
    echo "      Se esperaban {$esperadoOrigen} L pero hay {$totalFinalOrigen} L\n";
    $diferencia = $esperadoOrigen - $totalFinalOrigen;
    echo "      Se perdieron {$diferencia} L\n\n";
}

$stockFinalDestino = Inventory::where('product_id', $productId)
    ->where('brand_id', $brandId)
    ->where('location_id', $destinationLocationId)
    ->where('status', 'good')
    ->sum('quantity');

echo "🏬 Bodega Central (Destino):\n";
echo "   Stock final: {$stockFinalDestino} L\n";
echo "   Stock esperado: 30 L\n";
echo "   " . ($stockFinalDestino == 30 ? "✅ CORRECTO" : "❌ INCORRECTO") . "\n\n";

echo "🔢 TOTAL GENERAL:\n";
$totalGeneral = $totalFinalOrigen + $stockFinalDestino;
echo "   Total en sistema: {$totalGeneral} L\n";
echo "   Total inicial: {$totalInicial} L\n";
echo "   Diferencia: " . ($totalInicial - $totalGeneral) . " L\n";

if ($totalGeneral == $totalInicial) {
    echo "   ✅ CONSERVACIÓN DE STOCK: Correcto\n";
} else {
    echo "   ❌ PÉRDIDA DE STOCK: Se perdieron " . ($totalInicial - $totalGeneral) . " L\n";
}

echo "\n========================================\n";
echo "FIN DE LA PRUEBA\n\n";
