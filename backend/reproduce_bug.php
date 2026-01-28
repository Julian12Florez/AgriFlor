<?php

/**
 * SCRIPT DE REPRODUCCIÓN DEL BUG DE PÉRDIDA DE STOCK
 *
 * Este script simula EXACTAMENTE el flujo que describe el usuario:
 * 1. Producto con 100 unidades en ubicación A
 * 2. Transferencia de 60 unidades A → B
 * 3. Recepción PARCIAL de 30 unidades
 * 4. Las 40 unidades restantes en A DESAPARECEN
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\{Product, Brand, Location, Inventory, ProductOutput, OutputProduct, OutputType, Reception, ReceptionItem, User};
use Illuminate\Support\Facades\{DB, Log};

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  🔬 REPRODUCCIÓN DEL BUG - TRANSFERENCIA PARCIAL              ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// ============================================================================
// CONFIGURACIÓN
// ============================================================================

$productId = 'a095ca83-34b8-4cb6-8c94-7d506fa73508'; // Glifosato
$brandId = 'a095ca82-ee0c-4c87-8806-4cbb88ea73a1'; // Yara
$originLocationId = 'a095d5a5-23f8-4f03-b5a0-252d8b51929c'; // BODEGA PRINCIPAL
$destinationLocationId = 'a095ca83-0f19-47c0-bce5-095aa1407bb0'; // Bodega Central
$outputTypeId = 'a624ac75-d9c7-4770-817d-630f4a04b407'; // Transfer
$userId = 'a095ca82-e203-4f3e-b8bd-20da5c820ec8'; // Admin

$CANTIDAD_TOTAL_SALIDA = 60; // Transferir 60 L
$CANTIDAD_RECEPCION_PARCIAL = 30; // Recepcionar solo 30 L (PARCIAL)

// ============================================================================
// PASO 0: LIMPIAR DATOS DE PRUEBA ANTERIORES
// ============================================================================

echo "🧹 PASO 0: LIMPIANDO DATOS DE PRUEBAS ANTERIORES\n";
echo "════════════════════════════════════════════════════════════════\n";

DB::beginTransaction();

// Eliminar salidas y recepciones de prueba
ProductOutput::where('output_number', 'LIKE', 'TEST-BUG-%')->delete();
Reception::where('reception_number', 'LIKE', 'REC-TEST-%')->delete();

DB::commit();
echo "✅ Datos de prueba anteriores eliminados\n\n";

// ============================================================================
// PASO 1: VERIFICAR STOCK INICIAL
// ============================================================================

echo "📊 PASO 1: STOCK INICIAL\n";
echo "════════════════════════════════════════════════════════════════\n";

$stockInicial = Inventory::where('product_id', $productId)
    ->where('brand_id', $brandId)
    ->where('location_id', $originLocationId)
    ->where('status', 'good')
    ->get();

$totalInicial = $stockInicial->sum('quantity');

echo "Ubicación: BODEGA PRINCIPAL\n";
echo "Producto: Glifosato\n";
echo "Stock total: {$totalInicial} L\n";
echo "Lotes disponibles:\n";
foreach ($stockInicial as $lote) {
    echo "  ├─ Lote {$lote->batch_number}: {$lote->quantity} L (ID: {$lote->id})\n";
}
echo "\n";

if ($totalInicial < $CANTIDAD_TOTAL_SALIDA) {
    echo "❌ ERROR: No hay suficiente stock para la prueba\n";
    echo "   Necesitamos al menos {$CANTIDAD_TOTAL_SALIDA} L pero solo hay {$totalInicial} L\n\n";
    exit(1);
}

// ============================================================================
// PASO 2: CREAR SALIDA DE TRANSFERENCIA
// ============================================================================

echo "📦 PASO 2: CREAR SALIDA DE TRANSFERENCIA ({$CANTIDAD_TOTAL_SALIDA} L)\n";
echo "════════════════════════════════════════════════════════════════\n";

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
        'quantity_requested' => $CANTIDAD_TOTAL_SALIDA,
        'quantity_delivered' => $CANTIDAD_TOTAL_SALIDA,
        'unit' => 'L',
    ]);

    echo "✅ Salida creada exitosamente\n";
    echo "   ├─ ID: {$output->id}\n";
    echo "   ├─ Número: {$output->output_number}\n";
    echo "   ├─ Cantidad: {$CANTIDAD_TOTAL_SALIDA} L\n";
    echo "   └─ Status: {$output->status}\n\n";

    // Verificar stock (no debe cambiar en NEW FLOW)
    $stockDespuesCrear = Inventory::where('product_id', $productId)
        ->where('brand_id', $brandId)
        ->where('location_id', $originLocationId)
        ->where('status', 'good')
        ->sum('quantity');

    echo "📊 Stock después de crear salida: {$stockDespuesCrear} L\n";
    echo "   Esperado: {$totalInicial} L (sin cambios)\n";
    if ($stockDespuesCrear == $totalInicial) {
        echo "   ✅ CORRECTO: Stock no cambió\n\n";
    } else {
        echo "   ❌ ERROR: Stock cambió inesperadamente\n\n";
    }

    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    echo "❌ Error creando salida: {$e->getMessage()}\n\n";
    exit(1);
}

// ============================================================================
// PASO 3: RECEPCIÓN PARCIAL (AQUÍ ESTÁ EL BUG PROBABLE)
// ============================================================================

echo "📥 PASO 3: RECEPCIÓN PARCIAL ({$CANTIDAD_RECEPCION_PARCIAL} L de {$CANTIDAD_TOTAL_SALIDA} L)\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "⚠️  CRÍTICO: Solo recibiendo {$CANTIDAD_RECEPCION_PARCIAL} L, NO los {$CANTIDAD_TOTAL_SALIDA} L completos\n";
echo "   Esto simula una recepción parcial real\n\n";

// Activar logs detallados
Log::info('═══════════════════════════════════════════════════════════');
Log::info('🔬 INICIANDO REPRODUCCIÓN DEL BUG - RECEPCIÓN PARCIAL');
Log::info('═══════════════════════════════════════════════════════════');

DB::beginTransaction();

try {
    // Crear recepción
    $reception = Reception::create([
        'reception_number' => 'REC-TEST-' . time(),
        'reception_date' => now(),
        'source_type' => 'output',
        'source_id' => $output->id,
        'origin_location_id' => $originLocationId,
        'destination_location_id' => $destinationLocationId,
        'received_by' => $userId,
        'responsible_user' => $userId,
        'status' => 'completed',
    ]);

    Log::info('✅ Recepción creada', [
        'reception_id' => $reception->id,
        'reception_number' => $reception->reception_number,
    ]);

    // Crear item de recepción
    $receptionItem = ReceptionItem::create([
        'reception_id' => $reception->id,
        'product_id' => $productId,
        'brand_id' => $brandId,
        'quantity_expected' => $CANTIDAD_TOTAL_SALIDA, // Esperado: 60 L (total)
        'quantity_received' => $CANTIDAD_RECEPCION_PARCIAL, // Recibido: 30 L (PARCIAL)
        'unit' => 'L',
        'status' => 'partial', // Parcial porque faltan 30 L más
    ]);

    Log::info('✅ Reception Item creado', [
        'reception_item_id' => $receptionItem->id,
        'quantity_expected' => $CANTIDAD_TOTAL_SALIDA,
        'quantity_received' => $CANTIDAD_RECEPCION_PARCIAL,
        'status' => 'partial',
    ]);

    echo "✅ Recepción creada: {$reception->reception_number}\n";
    echo "   ├─ Cantidad esperada: {$CANTIDAD_TOTAL_SALIDA} L\n";
    echo "   ├─ Cantidad recibida: {$CANTIDAD_RECEPCION_PARCIAL} L ⚠️ PARCIAL\n";
    echo "   └─ Cantidad pendiente: " . ($CANTIDAD_TOTAL_SALIDA - $CANTIDAD_RECEPCION_PARCIAL) . " L\n\n";

    echo "🔍 PROCESANDO MOVIMIENTOS DE INVENTARIO...\n";
    echo "════════════════════════════════════════════════════════════════\n";

    // Obtener stock ANTES de procesar movimientos
    $lotesAntesMovimientos = Inventory::where('product_id', $productId)
        ->where('brand_id', $brandId)
        ->where('location_id', $originLocationId)
        ->where('status', 'good')
        ->get();

    echo "Stock en origen ANTES de movimientos:\n";
    foreach ($lotesAntesMovimientos as $lote) {
        echo "  ├─ Lote {$lote->batch_number}: {$lote->quantity} L\n";
    }
    $totalAntesMovimientos = $lotesAntesMovimientos->sum('quantity');
    echo "  └─ TOTAL: {$totalAntesMovimientos} L\n\n";

    Log::info('📊 Stock ANTES de movimientos', [
        'total' => $totalAntesMovimientos,
        'lotes' => $lotesAntesMovimientos->map(fn($l) => [
            'batch' => $l->batch_number,
            'quantity' => $l->quantity,
        ])->toArray(),
    ]);

    // ========================================================================
    // AQUÍ SE LLAMA AL MÉTODO processInventoryMovements
    // ========================================================================

    $controller = new \App\Http\Controllers\Api\ReceptionController();

    $itemData = [
        'product_id' => $productId,
        'quantity_received' => $CANTIDAD_RECEPCION_PARCIAL, // ← 30 L (PARCIAL)
        'condition' => 'good',
        'expiration_date' => now()->addYear()->toDateString(),
    ];

    Log::info('🚀 LLAMANDO A processInventoryMovements', [
        'itemData' => $itemData,
        'reception_id' => $reception->id,
        'reception_item_id' => $receptionItem->id,
    ]);

    // Usar reflexión para llamar al método privado
    $reflection = new \ReflectionClass($controller);
    $method = $reflection->getMethod('processInventoryMovements');
    $method->setAccessible(true);

    echo "⚙️  Ejecutando processInventoryMovements...\n\n";

    $method->invoke(
        $controller,
        $reception,
        $itemData,
        $receptionItem,
        1, // batchNumber
        $userId
    );

    Log::info('✅ processInventoryMovements completado');

    // Obtener stock DESPUÉS de procesar movimientos
    $lotesDespuesMovimientos = Inventory::where('product_id', $productId)
        ->where('brand_id', $brandId)
        ->where('location_id', $originLocationId)
        ->where('status', 'good')
        ->get();

    echo "Stock en origen DESPUÉS de movimientos:\n";
    foreach ($lotesDespuesMovimientos as $lote) {
        echo "  ├─ Lote {$lote->batch_number}: {$lote->quantity} L\n";
    }
    $totalDespuesMovimientos = $lotesDespuesMovimientos->sum('quantity');
    echo "  └─ TOTAL: {$totalDespuesMovimientos} L\n\n";

    Log::info('📊 Stock DESPUÉS de movimientos', [
        'total' => $totalDespuesMovimientos,
        'lotes' => $lotesDespuesMovimientos->map(fn($l) => [
            'batch' => $l->batch_number,
            'quantity' => $l->quantity,
        ])->toArray(),
    ]);

    // Obtener stock en destino
    $lotesDestino = Inventory::where('product_id', $productId)
        ->where('brand_id', $brandId)
        ->where('location_id', $destinationLocationId)
        ->where('status', 'good')
        ->get();

    echo "Stock en destino DESPUÉS de movimientos:\n";
    foreach ($lotesDestino as $lote) {
        echo "  ├─ Lote {$lote->batch_number}: {$lote->quantity} L\n";
    }
    $totalDestino = $lotesDestino->sum('quantity');
    echo "  └─ TOTAL: {$totalDestino} L\n\n";

    DB::commit();

    echo "✅ Movimientos procesados exitosamente\n\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "❌ ERROR en recepción: {$e->getMessage()}\n";
    echo "Stack trace:\n{$e->getTraceAsString()}\n\n";

    Log::error('❌ Error en recepción', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);

    exit(1);
}

// ============================================================================
// PASO 4: VERIFICAR RESULTADO FINAL
// ============================================================================

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  📊 PASO 4: VERIFICACIÓN FINAL DE STOCK                       ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

$stockFinalOrigen = Inventory::where('product_id', $productId)
    ->where('brand_id', $brandId)
    ->where('location_id', $originLocationId)
    ->where('status', 'good')
    ->get();

$totalFinalOrigen = $stockFinalOrigen->sum('quantity');
$esperadoOrigen = $totalInicial - $CANTIDAD_RECEPCION_PARCIAL; // 200 - 30 = 170

echo "🏢 BODEGA PRINCIPAL (Origen):\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "Stock final: {$totalFinalOrigen} L\n";
echo "Stock esperado: {$esperadoOrigen} L\n";
echo "Lotes:\n";
foreach ($stockFinalOrigen as $lote) {
    echo "  ├─ Lote {$lote->batch_number}: {$lote->quantity} L\n";
}
echo "\n";

if ($totalFinalOrigen == $esperadoOrigen) {
    echo "✅ CORRECTO: Stock en origen es correcto\n";
    echo "   Se redujeron exactamente los {$CANTIDAD_RECEPCION_PARCIAL} L recepcionados\n\n";
} else {
    echo "❌ BUG CONFIRMADO: Stock en origen es INCORRECTO!\n";
    echo "   Se esperaban {$esperadoOrigen} L pero hay {$totalFinalOrigen} L\n";
    $diferencia = $esperadoOrigen - $totalFinalOrigen;
    if ($diferencia > 0) {
        echo "   ⚠️  SE PERDIERON {$diferencia} L\n\n";
    } else {
        echo "   ⚠️  HAY {abs($diferencia)} L DE MÁS\n\n";
    }
}

$stockFinalDestino = Inventory::where('product_id', $productId)
    ->where('brand_id', $brandId)
    ->where('location_id', $destinationLocationId)
    ->where('status', 'good')
    ->sum('quantity');

echo "🏬 Bodega Central (Destino):\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "Stock final: {$stockFinalDestino} L\n";
echo "Stock esperado: {$CANTIDAD_RECEPCION_PARCIAL} L\n";
if ($stockFinalDestino == $CANTIDAD_RECEPCION_PARCIAL) {
    echo "✅ CORRECTO\n\n";
} else {
    echo "❌ INCORRECTO: Se esperaban {$CANTIDAD_RECEPCION_PARCIAL} L\n\n";
}

echo "🔢 TOTAL GENERAL:\n";
echo "════════════════════════════════════════════════════════════════\n";
$totalGeneral = $totalFinalOrigen + $stockFinalDestino;
echo "Total en sistema: {$totalGeneral} L\n";
echo "Total inicial: {$totalInicial} L\n";
$perdida = $totalInicial - $totalGeneral;
echo "Diferencia: {$perdida} L\n\n";

if ($totalGeneral == $totalInicial) {
    echo "✅ CONSERVACIÓN DE STOCK: Correcto\n";
    echo "   No se perdió stock en el sistema\n\n";
} else {
    echo "❌ PÉRDIDA DE STOCK DETECTADA!\n";
    if ($perdida > 0) {
        echo "   Se perdieron {$perdida} L del sistema\n\n";
    } else {
        echo "   Aparecieron {abs($perdida)} L extra en el sistema\n\n";
    }
}

// ============================================================================
// RESUMEN FINAL
// ============================================================================

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  🎯 RESUMEN FINAL                                              ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

echo "FLUJO EJECUTADO:\n";
echo "  1️⃣  Stock inicial en origen: {$totalInicial} L\n";
echo "  2️⃣  Salida creada: {$CANTIDAD_TOTAL_SALIDA} L\n";
echo "  3️⃣  Recepción parcial: {$CANTIDAD_RECEPCION_PARCIAL} L\n";
echo "  4️⃣  Stock final en origen: {$totalFinalOrigen} L\n";
echo "  5️⃣  Stock final en destino: {$stockFinalDestino} L\n\n";

$bugDetectado = ($totalFinalOrigen != $esperadoOrigen) || ($totalGeneral != $totalInicial);

if ($bugDetectado) {
    echo "🐛 RESULTADO: BUG CONFIRMADO\n";
    echo "════════════════════════════════════════════════════════════════\n";
    echo "El sistema NO mantiene correctamente el stock en recepciones parciales.\n";
    echo "Revisa los logs en storage/logs/laravel.log para detalles.\n\n";
    echo "Comando para ver logs:\n";
    echo "  docker exec agriflor-app tail -100 storage/logs/laravel.log | grep '🔍\\|🚨\\|✅\\|❌'\n\n";
    exit(1);
} else {
    echo "✅ RESULTADO: EL SISTEMA FUNCIONA CORRECTAMENTE\n";
    echo "════════════════════════════════════════════════════════════════\n";
    echo "El stock se mantuvo correctamente en la recepción parcial.\n";
    echo "Si reportas un bug, debe ser en otro flujo o condición.\n\n";
    exit(0);
}
