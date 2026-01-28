<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{Product, Brand, Location, ProductOutput, Inventory, User, OutputType};
use Illuminate\Foundation\Testing\RefreshDatabase;

class TransferenciaBugPreciseTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function bug_transferencia_parcial_pierde_stock()
    {
        // ARRANGE: Setup inicial
        // Create a Role first
        $adminRole = \App\Models\Role::create([
            'name' => 'admin',
            'display_name' => 'Administrator',
            'has_full_access' => true,
            'excluded_modules' => [],
        ]);

        $user = User::factory()->create([
            'password' => bcrypt('password'),
            'role' => 'admin',
            'role_id' => $adminRole->id,
        ]);

        $this->actingAs($user);

        $ubicacionA = Location::factory()->create(['name' => 'Bodega A']);
        $ubicacionB = Location::factory()->create(['name' => 'Finca B']);
        $producto = Product::factory()->create(['name' => 'Producto Test', 'base_unit' => 'kg']);
        $marca = Brand::factory()->create();

        // Ensure transfer output type exists
        $outputType = OutputType::firstOrCreate(
            ['code' => 'transfer'],
            [
                'name' => 'Transferencia',
                'description' => 'Transferencia entre ubicaciones',
                'requires_destination' => true,
                'affects_inventory' => true,
            ]
        );

        // Crear inventario inicial: 100 kg en Ubicación A
        $lote = Inventory::create([
            'product_id' => $producto->id,
            'brand_id' => $marca->id,
            'location_id' => $ubicacionA->id,
            'batch_number' => 'LOTE-TEST-001',
            'quantity' => 100,
            'unit' => 'kg',
            'unit_price' => 10,
            'total_value' => 1000,
            'status' => 'good',
            'expiration_date' => now()->addMonths(6),
        ]);

        // Verificar estado inicial
        $stockInicial = Inventory::where('location_id', $ubicacionA->id)->sum('quantity');
        $this->assertEquals(100, $stockInicial);
        dump('✅ Estado inicial: 100 kg en Ubicación A');

        // ACT 1: Crear salida de transferencia de 60 kg
        $response = $this->postJson('/api/product-outputs', [
            'output_number' => 'OUT-TEST-001',
            'output_date' => now()->toDateString(),
            'output_type_id' => $outputType->id,
            'origin_location_id' => $ubicacionA->id,
            'destination_location_id' => $ubicacionB->id,
            'responsible_user' => $user->id,
            'products' => [
                [
                    'product_id' => $producto->id,
                    'brand_id' => $marca->id,
                    'quantity_requested' => 60,
                    'quantity_delivered' => 60,
                    'unit' => 'kg',
                ]
            ]
        ]);

        if ($response->status() !== 201) {
            dump('❌ Error al crear salida: ' . $response->json('message'));
            dump('Response:', $response->json());
        }

        $response->assertStatus(201);
        $outputId = $response->json('data.id');
        dump('✅ Salida creada: ' . $outputId);

        // Verificar stock después de crear salida (no debe cambiar en NEW FLOW)
        $stockDespuesCrear = Inventory::where('location_id', $ubicacionA->id)->sum('quantity');
        dump("📊 Stock después de crear salida: {$stockDespuesCrear} kg (esperado: 100 kg)");
        $this->assertEquals(100, $stockDespuesCrear, 'Stock no debe cambiar al crear salida');

        // ACT 2: Aprobar la salida
        $approveResponse = $this->postJson("/api/product-outputs/{$outputId}/approve");

        if ($approveResponse->status() !== 200) {
            dump('❌ Error al aprobar salida: ' . $approveResponse->json('message'));
            dump('Response:', $approveResponse->json());
        }

        $approveResponse->assertStatus(200);
        dump('✅ Salida aprobada');

        // Verificar stock después de aprobar (no debe cambiar en NEW FLOW)
        $stockDespuesAprobar = Inventory::where('location_id', $ubicacionA->id)->sum('quantity');
        dump("📊 Stock después de aprobar: {$stockDespuesAprobar} kg (esperado: 100 kg)");
        $this->assertEquals(100, $stockDespuesAprobar, 'Stock no debe cambiar al aprobar salida (NEW FLOW)');

        // ACT 3: Recepcionar 60 kg en Ubicación B
        $receptionResponse = $this->postJson('/api/receptions/create-with-batch', [
            'source_id' => $outputId,
            'source_type' => 'output',
            'reception_date' => now()->toDateString(),
            'received_by' => $user->id,
            'items' => [
                [
                    'product_id' => $producto->id,
                    'brand_id' => $marca->id,  // BUG FIX: Added brand_id to prevent wrong reception item lookup
                    'quantity_received' => 60,  // ✅ Solo 60, no 100
                    'condition' => 'good',
                    'expiration_date' => now()->addMonths(6)->toDateString(),
                ]
            ]
        ]);

        if ($receptionResponse->status() !== 201) {
            dump('❌ Error al crear recepción: ' . $receptionResponse->json('message'));
            dump('Response:', $receptionResponse->json());
        }

        $receptionResponse->assertStatus(201);
        dump('✅ Recepción creada: 60 kg recibidos');

        // ASSERT: Verificar resultado final
        $stockFinalA = Inventory::where('location_id', $ubicacionA->id)->sum('quantity');
        $stockFinalB = Inventory::where('location_id', $ubicacionB->id)->sum('quantity');

        dump("📊 RESULTADO FINAL:");
        dump("   Ubicación A: {$stockFinalA} kg (esperado: 40 kg)");
        dump("   Ubicación B: {$stockFinalB} kg (esperado: 60 kg)");

        // Verificar lotes individuales
        $lotesA = Inventory::where('location_id', $ubicacionA->id)->get();
        $lotesB = Inventory::where('location_id', $ubicacionB->id)->get();

        dump("📦 Lotes en Ubicación A:");
        foreach ($lotesA as $lote) {
            dump("   - Lote {$lote->batch_number}: {$lote->quantity} kg");
        }

        dump("📦 Lotes en Ubicación B:");
        foreach ($lotesB as $lote) {
            dump("   - Lote {$lote->batch_number}: {$lote->quantity} kg");
        }

        // Verificar movimientos
        $movimientos = \App\Models\InventoryMovement::where('product_id', $producto->id)
            ->orderBy('created_at')
            ->get();

        dump("📝 Movimientos de inventario ({$movimientos->count()} total):");
        foreach ($movimientos as $mov) {
            dump("   - {$mov->type}: {$mov->quantity} kg en ubicación {$mov->location_id} (doc: {$mov->related_document_type})");
        }

        // ASSERTIONS CRÍTICAS
        $this->assertEquals(40, $stockFinalA, '❌ BUG CONFIRMADO: Stock en A debería ser 40 kg (100 - 60 recibidos)');
        $this->assertEquals(60, $stockFinalB, 'Stock en B debería ser 60 kg');
        $this->assertEquals(100, $stockFinalA + $stockFinalB, 'Total de stock debe conservarse (100 kg)');
    }
}
