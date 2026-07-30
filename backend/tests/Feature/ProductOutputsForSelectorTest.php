<?php

namespace Tests\Feature;

use App\Models\BaseUnit;
use App\Models\Brand;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\OutputProduct;
use App\Models\OutputType;
use App\Models\Product;
use App\Models\ProductOutput;
use App\Models\Reception;
use App\Models\ReceptionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PR-C / C-3: el selector de "Nueva Salida" (GET /api/products-for-outputs)
 * debe ofrecer exactamente lo que ProductOutputController::store() luego
 * acepta. Antes ofrecía el stock FÍSICO completo, y store() validaba
 * físico − comprometido (salidas approved/in_transit/partial sin recibir),
 * así que el usuario veía una cantidad que el backend rechazaba con 422
 * "Stock insuficiente" (medido: ABAMECTINA 6 L, KENDO 6 L).
 *
 * También cubre el orden FIFO del selector: los lotes "Sin vencimiento"
 * deben ir al FINAL, no al frente (MySQL ordena NULL primero por defecto).
 */
class ProductOutputsForSelectorTest extends TestCase
{
    use RefreshDatabase;

    private function makeFixtures(): array
    {
        $admin = User::create([
            'name' => 'Admin Selector Test',
            'email' => 'admin_selector_' . uniqid() . '@agriflor.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $brand = Brand::create([
            'name' => 'Marca Selector Test ' . uniqid(),
            'status' => 'active',
        ]);

        BaseUnit::firstOrCreate(
            ['symbol' => 'kg'],
            ['name' => 'Kilogramos', 'description' => 'Unidad de masa', 'status' => 'active']
        );

        $product = Product::create([
            'name' => 'Producto Selector Test',
            'brand_id' => $brand->id,
            'active_ingredient' => 'Glifosato',
            'min_stock' => 0,
            'status' => 'active',
            'base_unit' => 'kg',
            'created_by' => $admin->id,
        ]);

        $origin = Location::create([
            'name' => 'Bodega Selector Test',
            'type' => 'warehouse',
            'status' => 'active',
        ]);

        $destination = Location::create([
            'name' => 'Finca Selector Test',
            'type' => 'farm',
            'status' => 'active',
        ]);

        $transferType = OutputType::firstOrCreate(
            ['code' => 'transfer'],
            ['name' => 'Traslado', 'requires_lots' => false, 'status' => 'active']
        );

        return compact('admin', 'brand', 'product', 'origin', 'destination', 'transferType');
    }

    /**
     * Una salida aprobada (compromete stock) con una recepción PENDIENTE
     * (nada recibido todavía): exactamente el escenario que
     * CommittedStockService descuenta del disponible.
     */
    private function makeCommittedOutput(array $f, float $committedQty): void
    {
        $output = ProductOutput::create([
            'output_number' => ProductOutput::generateOutputNumber(),
            'output_type_id' => $f['transferType']->id,
            'output_date' => now()->toDateString(),
            'origin_location_id' => $f['origin']->id,
            'destination_location_id' => $f['destination']->id,
            'status' => 'approved',
            'total_cost' => $committedQty * 10,
            'responsible_user' => $f['admin']->id,
        ]);

        $outputProduct = OutputProduct::create([
            'output_id' => $output->id,
            'product_id' => $f['product']->id,
            'brand_id' => $f['brand']->id,
            'quantity_requested' => $committedQty,
            'quantity_delivered' => $committedQty,
            'unit' => 'kg',
        ]);

        $reception = Reception::create([
            'reception_number' => 'REC-SEL-' . strtoupper(substr(uniqid(), -8)),
            'source_id' => $output->id,
            'source_type' => 'output',
            'origin_location_id' => $f['origin']->id,
            'destination_location_id' => $f['destination']->id,
            'status' => 'pending',
            'total_expected' => $committedQty,
            'total_received' => 0,
            'completion_percentage' => 0,
            'responsible_user' => $f['admin']->id,
        ]);

        ReceptionItem::create([
            'reception_id' => $reception->id,
            'product_id' => $f['product']->id,
            'brand_id' => $f['brand']->id,
            'source_item_id' => $outputProduct->id,
            'quantity_expected' => $committedQty,
            'quantity_received' => 0,
            'quantity_pending' => $committedQty,
            'unit' => 'kg',
        ]);
    }

    public function test_available_quantity_excludes_committed_stock(): void
    {
        $f = $this->makeFixtures();

        Inventory::create([
            'product_id' => $f['product']->id,
            'brand_id' => $f['brand']->id,
            'location_id' => $f['origin']->id,
            'batch_number' => 'LOTE-SELECTOR-1',
            'quantity' => 100,
            'unit' => 'kg',
            'unit_price' => 10,
            'total_value' => 1000,
            'status' => 'good',
        ]);

        // Sin ninguna salida comprometida todavía: el disponible es el físico completo.
        $before = $this->actingAs($f['admin'], 'api')
            ->getJson('/api/products-for-outputs?location_id=' . $f['origin']->id)
            ->json('data');

        $this->assertSame(100.0, (float) $before[0]['base_quantity']);
        $this->assertSame(0.0, (float) $before[0]['committed_quantity']);
        $this->assertSame(100.0, (float) $before[0]['available_quantity']);
        $this->assertStringContainsString('100.00 kg disponible', $before[0]['display_label']);

        // Se aprueba una salida de 40 kg (aún no recibida): compromete stock.
        $this->makeCommittedOutput($f, 40);

        $after = $this->actingAs($f['admin'], 'api')
            ->getJson('/api/products-for-outputs?location_id=' . $f['origin']->id)
            ->json('data');

        $this->assertCount(1, $after);
        $this->assertSame(100.0, (float) $after[0]['quantity'], 'El físico del lote no cambia');
        $this->assertSame(100.0, (float) $after[0]['base_quantity']);
        $this->assertSame(40.0, (float) $after[0]['committed_quantity']);
        $this->assertSame(60.0, (float) $after[0]['available_quantity'], 'El disponible debe bajar en lo comprometido');
        $this->assertStringContainsString('60.00 kg disponible', $after[0]['display_label']);
    }

    public function test_orders_batches_without_expiration_last(): void
    {
        $f = $this->makeFixtures();

        // Lote SIN vencimiento: antes del fix, MySQL lo ponía PRIMERO (NULL first).
        Inventory::create([
            'product_id' => $f['product']->id,
            'brand_id' => $f['brand']->id,
            'location_id' => $f['origin']->id,
            'batch_number' => 'LOTE-SIN-VENCIMIENTO',
            'quantity' => 50,
            'unit' => 'kg',
            'expiration_date' => null,
            'unit_price' => 10,
            'total_value' => 500,
            'status' => 'good',
        ]);

        // Lote que SÍ vence, más adelante en el tiempo: debe salir ANTES que el
        // que no tiene fecha, porque el rótulo del selector promete FIFO por
        // vencimiento.
        Inventory::create([
            'product_id' => $f['product']->id,
            'brand_id' => $f['brand']->id,
            'location_id' => $f['origin']->id,
            'batch_number' => 'LOTE-CON-VENCIMIENTO',
            'quantity' => 30,
            'unit' => 'kg',
            'expiration_date' => now()->addDays(30)->toDateString(),
            'unit_price' => 10,
            'total_value' => 300,
            'status' => 'good',
        ]);

        $data = $this->actingAs($f['admin'], 'api')
            ->getJson('/api/products-for-outputs?location_id=' . $f['origin']->id)
            ->json('data');

        $this->assertCount(2, $data);
        $this->assertSame('LOTE-CON-VENCIMIENTO', $data[0]['batch_number'], 'El lote que vence debe ir primero');
        $this->assertSame('LOTE-SIN-VENCIMIENTO', $data[1]['batch_number'], 'Sin vencimiento va al final');
    }

    public function test_store_rejects_when_requested_exceeds_committed_aware_available(): void
    {
        $f = $this->makeFixtures();

        Inventory::create([
            'product_id' => $f['product']->id,
            'brand_id' => $f['brand']->id,
            'location_id' => $f['origin']->id,
            'batch_number' => 'LOTE-STORE-1',
            'quantity' => 100,
            'unit' => 'kg',
            'unit_price' => 10,
            'total_value' => 1000,
            'status' => 'good',
        ]);

        // Compromete 40 kg: disponible real = 60 kg.
        $this->makeCommittedOutput($f, 40);

        $payload = [
            'output_type_id' => $f['transferType']->id,
            'output_date' => now()->toDateString(),
            'origin_location_id' => $f['origin']->id,
            'destination_location_id' => $f['destination']->id,
            'products' => [[
                'product_id' => $f['product']->id,
                'brand_id' => $f['brand']->id,
                'quantity_requested' => 70,
                'quantity_delivered' => 70,
                'unit' => 'kg',
            ]],
        ];

        // 70 kg > 60 kg disponibles (100 físico - 40 comprometido): debe rechazar.
        $rejected = $this->actingAs($f['admin'], 'api')->postJson('/api/product-outputs', $payload);
        $rejected->assertStatus(422);

        // 50 kg <= 60 kg disponibles: debe aceptar.
        $payload['products'][0]['quantity_requested'] = 50;
        $payload['products'][0]['quantity_delivered'] = 50;
        $accepted = $this->actingAs($f['admin'], 'api')->postJson('/api/product-outputs', $payload);
        $accepted->assertStatus(201);
    }
}
