<?php

/**
 * Script manual para reproducir bug de transferencias
 *
 * Uso:
 * php tests/ManualBugTest.php
 *
 * Este script debe ejecutarse DESPUÉS de correr TransferenciaBugSeeder
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\{ProductOutput, OutputProduct, Reception, ReceptionItem, Inventory, InventoryMovement, User, OutputType};
use Illuminate\Support\Facades\DB;

echo "\n========================================\n";
echo "PRUEBA DE BUG: TRANSFERENCIA PARCIAL\n";
echo "========================================\n\n";

try {
    // Obtener datos del seeder
    $bodegaA = App\Models\Location::where('name', 'Bodega A - Test')->first();
    $fincaB = App\Models\Location::where('name', 'Finca B - Test')->first();
    $producto = App\Models\Product::where('name', 'Fertilizante Test NPK')->first();
    $marca = App\Models\Brand::where('name', 'Marca Test')->first();
    $user = User::where('email', 'test@agriflor.com')->first();
    $transferType = OutputType::where('code', 'transfer')->first();

    if (!$bodegaA || !$fincaB || !$producto || !$marca || !$user || !$transferType) {
        echo "❌ ERROR: Datos del seeder no encontrados. Ejecuta primero TransferenciaBugSeeder\n";
        exit(1);
    }

    echo "📦 Datos obtenidos:\n";
    echo "   Bodega A: {$bodegaA->id}\n";
    echo "   Finca B: {$fincaB->id}\n";
    echo "   Producto: {$producto->id}\n";
    echo "   Marca: {$marca->id}\n\n";

    // PASO 1: Verificar inventario inicial
    echo "PASO 1: Verificar inventario inicial\n";
    echo str_repeat("-", 40) . "\n";

    $inventarioInicial = Inventory::where('product_id', $producto->id)
        ->where('location_id', $bodegaA->id)
        ->sum('quantity');

    echo "✅ Inventario en Bodega A: {$inventarioInicial} kg\n";
    echo "   Esperado: 100 kg\n\n";

    if ($inventarioInicial != 100) {
        echo "⚠️  ADVERTENCIA: Inventario inicial no es 100 kg\n\n";
    }

    // PASO 2: Crear salida de transferencia (60 kg de 100 kg)
    echo "PASO 2: Crear salida de transferencia (60 kg)\n";
    echo str_repeat("-", 40) . "\n";

    DB::beginTransaction();

    $salida = ProductOutput::create([
        'output_number' => 'OUT-TEST-' . time(),
        'output_date' => now(),
        'output_type_id' => $transferType->id,
        'origin_location_id' => $bodegaA->id,
        'destination_location_id' => $fincaB->id,
        'status' => 'pending',
        'total_cost' => 3000, // 60 kg * 50
        'responsible_user' => $user->id,
    ]);

    OutputProduct::create([
        'output_id' => $salida->id,
        'product_id' => $producto->id,
        'brand_id' => $marca->id,
        'quantity_requested' => 60,
        'quantity_delivered' => 60,
        'unit' => 'kg',
    ]);

    DB::commit();

    echo "✅ Salida creada: {$salida->output_number} (ID: {$salida->id})\n";
    echo "   Cantidad: 60 kg\n";
    echo "   Estado: {$salida->status}\n\n";

    // PASO 3: Aprobar la salida (cambiar a completed)
    echo "PASO 3: Aprobar la salida\n";
    echo str_repeat("-", 40) . "\n";

    $salida->update(['status' => 'completed']);

    echo "✅ Salida aprobada\n";

    // Verificar si se redujo inventario al aprobar (OLD FLOW)
    $inventarioDespuesAprobar = Inventory::where('product_id', $producto->id)
        ->where('location_id', $bodegaA->id)
        ->sum('quantity');

    echo "📊 Inventario después de aprobar: {$inventarioDespuesAprobar} kg\n";

    if ($inventarioDespuesAprobar == 100) {
        echo "   ✅ NEW FLOW: Inventario NO se redujo (correcto)\n";
    } elseif ($inventarioDespuesAprobar == 40) {
        echo "   ⚠️  OLD FLOW: Inventario se redujo al aprobar\n";
    } else {
        echo "   ❌ Estado inesperado: inventario = {$inventarioDespuesAprobar} kg\n";
    }
    echo "\n";

    // PASO 4: Crear recepción parcial (60 kg)
    echo "PASO 4: Crear recepción de 60 kg\n";
    echo str_repeat("-", 40) . "\n";

    DB::beginTransaction();

    $recepcion = Reception::create([
        'reception_number' => 'REC-TEST-' . time(),
        'source_type' => 'output',
        'source_id' => $salida->id,
        'origin_location_id' => $bodegaA->id,
        'destination_location_id' => $fincaB->id,
        'responsible_user' => $user->id,
        'status' => 'completed',
    ]);

    $receptionItem = ReceptionItem::create([
        'reception_id' => $recepcion->id,
        'product_id' => $producto->id,
        'brand_id' => $marca->id,
        'quantity_expected' => 60,
        'quantity_received' => 60,
        'unit' => 'kg',
    ]);

    // Aquí es donde se debería ejecutar processInventoryMovements
    // Por ahora lo simularemos manualmente

    echo "✅ Recepción creada: {$recepcion->reception_number} (ID: {$recepcion->id})\n";
    echo "   Cantidad recibida: 60 kg\n\n";

    DB::commit();

    // PASO 5: Verificar inventarios finales
    echo "PASO 5: Verificar inventarios finales\n";
    echo str_repeat("=", 40) . "\n";

    $inventarioFinalBodegaA = Inventory::where('product_id', $producto->id)
        ->where('location_id', $bodegaA->id)
        ->sum('quantity');

    $inventarioFinalFincaB = Inventory::where('product_id', $producto->id)
        ->where('location_id', $fincaB->id)
        ->sum('quantity');

    echo "📊 RESULTADOS:\n";
    echo "   Bodega A: {$inventarioFinalBodegaA} kg (esperado: 40 kg)\n";
    echo "   Finca B:  {$inventarioFinalFincaB} kg (esperado: 60 kg)\n\n";

    // Verificar movimientos
    $movimientos = InventoryMovement::where('product_id', $producto->id)
        ->orderBy('created_at', 'desc')
        ->get();

    echo "📋 Movimientos de inventario registrados:\n";
    foreach ($movimientos as $mov) {
        $subtype = $mov->subtype ?: 'N/A';
        echo "   - {$mov->type} | {$subtype} | {$mov->quantity} kg | {$mov->created_at}\n";
        echo "     Related: {$mov->related_document_type}\n";
    }
    echo "\n";

    // DIAGNÓSTICO
    echo str_repeat("=", 40) . "\n";
    echo "DIAGNÓSTICO:\n";
    echo str_repeat("=", 40) . "\n\n";

    if ($inventarioFinalBodegaA == 40 && $inventarioFinalFincaB == 60) {
        echo "✅ BUG NO CONFIRMADO: El sistema funciona correctamente\n";
        echo "   Stock se mantiene en origen y se transfiere correctamente\n";
    } elseif ($inventarioFinalBodegaA == 0 && $inventarioFinalFincaB == 60) {
        echo "❌ BUG CONFIRMADO: Se pierde el stock en origen\n";
        echo "   40 kg desaparecieron de Bodega A\n";
        echo "   Posible causa: Reducción doble o incorrecta en FIFO\n";
    } elseif ($inventarioFinalBodegaA == 100 && $inventarioFinalFincaB == 0) {
        echo "❌ BUG CONFIRMADO: No se procesó la recepción\n";
        echo "   El inventario no se actualizó\n";
    } else {
        echo "❌ COMPORTAMIENTO INESPERADO:\n";
        echo "   Bodega A: {$inventarioFinalBodegaA} kg\n";
        echo "   Finca B: {$inventarioFinalFincaB} kg\n";
    }

    echo "\n";

} catch (Exception $e) {
    DB::rollBack();
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "   Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    exit(1);
}

echo "========================================\n";
echo "PRUEBA COMPLETADA\n";
echo "========================================\n\n";
