<?php

namespace Tests\Feature;

use App\Models\Adjustment;
use App\Models\AdjustmentReason;
use App\Models\BaseUnit;
use App\Models\Brand;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\OutputProduct;
use App\Models\OutputType;
use App\Models\Product;
use App\Models\ProductOutput;
use App\Models\Reception;
use App\Models\ReceptionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * PR-2: el inventario mensual (el informe que el cliente concilia contra
 * contabilidad) contaba como COMPRAS los remanentes y los traslados recibidos
 * desde una finca, porque su entrada de kardex se registra con
 * `related_document_type = 'App\Models\Reception'` — el mismo valor que usa una
 * compra real — sin importar que `receptions.source_type` sea 'output' (una
 * salida recepcionada) en vez de 'purchase'.
 *
 * Medido en producción: 756 movimientos / 372.486,74 unidades / $947.776.147
 * mal etiquetados como Compras; en julio, en una finca, 203.937,83 unidades
 * eran 100% envíos recibidos y 0% compras.
 *
 * El invariante que protege este archivo es el mismo de PR-1: `variation == 0`.
 * Si se excluyen esas entradas de "Compras" sin darles una columna nueva
 * ("Envíos recibidos" / `shipments_in`), dejan de estar explicadas por ninguna
 * columna y "Variación" se descuadra de nuevo.
 */
class MonthlyReportRemanenteClassificationTest extends TestCase
{
    use RefreshDatabase;

    private const REPORT_MONTH = 4;
    private const REPORT_YEAR = 2026;

    /** Fecha real de recepción del lote: dentro del mes del informe. */
    private const RECEPTION_DATE = '2026-04-15';

    /** Fecha de la existencia de apertura: mes anterior al informe. */
    private const OPENING_DATE = '2026-02-10';

    /**
     * Este archivo prueba la clasificación del inventario mensual (PR-2), no
     * el cierre contable de PR-A/AJ-2: se aleja `closed_period_until` de las
     * fechas históricas (abril/febrero de 2026) usadas aquí para que ese
     * chequeo — que sí corre en approve() — no interfiera.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['adjustments.closed_period_until' => '2000-01-01']);
    }

    /**
     * Catálogo mínimo: un admin, un producto con unidad base 'kg', una BODEGA,
     * una FINCA, y los output_types relevantes (remanente y traslado).
     */
    private function createFixtures(): array
    {
        $admin = User::create([
            'name' => 'Admin Remanente',
            'email' => 'admin_remanente_' . uniqid() . '@agriflor.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $brand = Brand::create([
            'name' => 'Marca Remanente ' . uniqid(),
            'status' => 'active',
        ]);

        BaseUnit::firstOrCreate(
            ['symbol' => 'kg'],
            ['name' => 'Kilogramos', 'description' => 'Unidad de masa', 'status' => 'active']
        );

        $product = Product::create([
            'name' => 'Producto Remanente',
            'brand_id' => $brand->id,
            'active_ingredient' => 'Glifosato',
            'min_stock' => 0,
            'status' => 'active',
            'base_unit' => 'kg',
            'created_by' => $admin->id,
        ]);

        $reason = AdjustmentReason::create([
            'code' => 'motivo_remanente_' . uniqid(),
            'name' => 'Motivo de prueba',
            'direction' => 'any',
            'active' => true,
        ]);

        $bodega = Location::create([
            'name' => 'Bodega Principal Test',
            'type' => 'warehouse',
            'status' => 'active',
        ]);

        $finca = Location::create([
            'name' => 'Finca Test',
            'type' => 'farm',
            'status' => 'active',
        ]);

        $remanenteType = OutputType::firstOrCreate(
            ['code' => 'remanente'],
            ['name' => 'Remanente', 'requires_lots' => false, 'status' => 'active']
        );

        $transferType = OutputType::firstOrCreate(
            ['code' => 'transfer'],
            ['name' => 'Traslado', 'requires_lots' => false, 'status' => 'active']
        );

        return compact(
            'admin', 'brand', 'product', 'reason', 'bodega', 'finca',
            'remanenteType', 'transferType'
        );
    }

    /**
     * Existencia previa en una ubicación: el lote real en `inventory` (lo que
     * consume FIFO) más su movimiento de kardex, fechados ANTES del mes del
     * informe.
     */
    private function seedOpeningStock(array $fixtures, string $locationId, float $quantity): void
    {
        Inventory::create([
            'product_id' => $fixtures['product']->id,
            'brand_id' => $fixtures['brand']->id,
            'location_id' => $locationId,
            'batch_number' => 'LOTE-APERTURA-' . uniqid(),
            'quantity' => $quantity,
            'unit' => 'kg',
            'unit_price' => 10,
            'total_value' => $quantity * 10,
            'status' => 'good',
        ]);

        InventoryMovement::create([
            'type' => 'entry',
            'product_id' => $fixtures['product']->id,
            'brand_id' => $fixtures['brand']->id,
            'location_id' => $locationId,
            'quantity' => $quantity,
            'unit' => 'kg',
            'movement_date' => self::OPENING_DATE,
            'unit_price' => 10,
            'total_price' => $quantity * 10,
            'responsible_user' => $fixtures['admin']->id,
            'observations' => 'Ajuste inicial',
        ]);
    }

    /**
     * Una salida aprobada (remanente o traslado) con un producto, junto a la
     * recepción pendiente que la va a recibir, entre las ubicaciones dadas.
     */
    private function makeOutputReception(
        array $fixtures,
        OutputType $type,
        float $quantity,
        string $originId,
        string $destinationId
    ): array {
        $output = ProductOutput::create([
            'output_number' => ProductOutput::generateOutputNumber(),
            'output_type_id' => $type->id,
            'output_date' => self::RECEPTION_DATE,
            'origin_location_id' => $originId,
            'destination_location_id' => $destinationId,
            'status' => 'approved',
            'total_cost' => $quantity * 10,
            'responsible_user' => $fixtures['admin']->id,
        ]);

        $outputProduct = OutputProduct::create([
            'output_id' => $output->id,
            'product_id' => $fixtures['product']->id,
            'brand_id' => $fixtures['brand']->id,
            'quantity_requested' => $quantity,
            'quantity_delivered' => $quantity,
            'unit' => 'kg',
        ]);

        $reception = Reception::create([
            'reception_number' => 'REC-TEST-' . strtoupper(substr(uniqid(), -8)),
            'source_id' => $output->id,
            'source_type' => 'output',
            'origin_location_id' => $originId,
            'destination_location_id' => $destinationId,
            'status' => 'pending',
            'total_expected' => $quantity,
            'total_received' => 0,
            'completion_percentage' => 0,
            'responsible_user' => $fixtures['admin']->id,
        ]);

        $receptionItem = ReceptionItem::create([
            'reception_id' => $reception->id,
            'product_id' => $fixtures['product']->id,
            'brand_id' => $fixtures['brand']->id,
            'source_item_id' => $outputProduct->id,
            'quantity_expected' => $quantity,
            'quantity_received' => 0,
            'quantity_pending' => $quantity,
            'unit' => 'kg',
        ]);

        return compact('output', 'outputProduct', 'reception', 'receptionItem');
    }

    /** Recepción de una COMPRA real: solo genera entrada en el destino. */
    private function makePurchaseReception(array $fixtures, float $quantity): array
    {
        $reception = Reception::create([
            'reception_number' => 'REC-TEST-' . strtoupper(substr(uniqid(), -8)),
            'source_id' => $fixtures['admin']->id,
            'source_type' => 'purchase',
            'origin_location_id' => null,
            'destination_location_id' => $fixtures['bodega']->id,
            'status' => 'pending',
            'total_expected' => $quantity,
            'total_received' => 0,
            'completion_percentage' => 0,
            'responsible_user' => $fixtures['admin']->id,
        ]);

        $receptionItem = ReceptionItem::create([
            'reception_id' => $reception->id,
            'product_id' => $fixtures['product']->id,
            'brand_id' => $fixtures['brand']->id,
            'quantity_expected' => $quantity,
            'quantity_received' => 0,
            'quantity_pending' => $quantity,
            'unit' => 'kg',
        ]);

        return compact('reception', 'receptionItem');
    }

    /** POST del lote de recepción con una fecha explícita dentro del mes del informe. */
    private function receiveBatch(
        array $fixtures,
        Reception $reception,
        float $quantity
    ): TestResponse {
        return $this->actingAs($fixtures['admin'], 'api')->postJson(
            "/api/receptions/{$reception->id}/batches",
            [
                'reception_id' => $reception->id,
                'reception_date' => self::RECEPTION_DATE,
                'received_by' => $fixtures['admin']->id,
                'items' => [[
                    'product_id' => $fixtures['product']->id,
                    'brand_id' => $fixtures['brand']->id,
                    'quantity_received' => $quantity,
                    'condition' => 'good',
                ]],
            ]
        );
    }

    private function monthlyReport(array $fixtures, string $locationId): TestResponse
    {
        return $this->actingAs($fixtures['admin'], 'api')->getJson(
            '/api/inventory/monthly-report'
            . '?month=' . self::REPORT_MONTH
            . '&year=' . self::REPORT_YEAR
            . '&location_id=' . $locationId
        );
    }

    /** Fila del informe correspondiente al producto de los fixtures. */
    private function rowFor(TestResponse $response, string $productId): array
    {
        foreach ($response->json('data.products') ?? [] as $row) {
            if ($row['product_id'] === $productId) {
                return $row;
            }
        }

        $this->fail("El producto {$productId} no aparece en el informe mensual.");
    }

    private function approve(array $fixtures, Adjustment $adjustment): TestResponse
    {
        return $this->actingAs($fixtures['admin'], 'api')
            ->putJson("/api/adjustments/{$adjustment->id}/approve");
    }

    // ------------------------------------------------------------------
    // 1. REMANENTE (finca -> bodega): cae en "returns", no en "purchases"
    // ------------------------------------------------------------------

    public function test_remanente_reception_counts_as_returns_not_purchases(): void
    {
        $fixtures = $this->createFixtures();
        $this->seedOpeningStock($fixtures, $fixtures['finca']->id, 200);

        $context = $this->makeOutputReception(
            $fixtures,
            $fixtures['remanenteType'],
            140,
            $fixtures['finca']->id,
            $fixtures['bodega']->id
        );

        $this->receiveBatch($fixtures, $context['reception'], 140)->assertStatus(201);

        $row = $this->rowFor(
            $this->monthlyReport($fixtures, $fixtures['bodega']->id)->assertStatus(200),
            $fixtures['product']->id
        );

        $this->assertEqualsWithDelta(
            140,
            $row['returns'],
            0.01,
            'El remanente recibido debe caer en "Remanente" (returns).'
        );
        $this->assertEqualsWithDelta(
            0,
            $row['purchases'],
            0.01,
            'Un remanente NO es una compra: contarlo también ahí lo duplicaría.'
        );
        $this->assertEqualsWithDelta(0, $row['shipments_in'] ?? 0, 0.01);
        $this->assertEqualsWithDelta(140, $row['final_stock'], 0.01);
        $this->assertEqualsWithDelta(140, $row['current_stock'], 0.01);
        $this->assertEqualsWithDelta(
            0,
            $row['variation'],
            0.01,
            'Variación debe ser cero: el remanente debe quedar explicado por alguna columna.'
        );
    }

    // ------------------------------------------------------------------
    // 2. TRASLADO recibido (finca -> bodega): cae en "shipments_in"
    // ------------------------------------------------------------------

    public function test_transfer_reception_counts_as_shipments_in_not_purchases(): void
    {
        $fixtures = $this->createFixtures();
        $this->seedOpeningStock($fixtures, $fixtures['finca']->id, 300);

        $context = $this->makeOutputReception(
            $fixtures,
            $fixtures['transferType'],
            90,
            $fixtures['finca']->id,
            $fixtures['bodega']->id
        );

        $this->receiveBatch($fixtures, $context['reception'], 90)->assertStatus(201);

        $row = $this->rowFor(
            $this->monthlyReport($fixtures, $fixtures['bodega']->id)->assertStatus(200),
            $fixtures['product']->id
        );

        $this->assertEqualsWithDelta(
            90,
            $row['shipments_in'] ?? null,
            0.01,
            'Un traslado recepcionado (no remanente) debe caer en "Envíos recibidos".'
        );
        $this->assertEqualsWithDelta(
            0,
            $row['purchases'],
            0.01,
            'Un traslado recepcionado NO es una compra.'
        );
        $this->assertEqualsWithDelta(0, $row['returns'], 0.01);
        $this->assertEqualsWithDelta(90, $row['final_stock'], 0.01);
        $this->assertEqualsWithDelta(90, $row['current_stock'], 0.01);
        $this->assertEqualsWithDelta(
            0,
            $row['variation'],
            0.01,
            'Variación debe ser cero: el envío recibido debe quedar explicado por "shipments_in".'
        );
    }

    // ------------------------------------------------------------------
    // 3. COMPRA real: no regresión, sigue en "purchases"
    // ------------------------------------------------------------------

    public function test_real_purchase_still_counts_as_purchases(): void
    {
        $fixtures = $this->createFixtures();
        $context = $this->makePurchaseReception($fixtures, 80);

        $this->receiveBatch($fixtures, $context['reception'], 80)->assertStatus(201);

        $row = $this->rowFor(
            $this->monthlyReport($fixtures, $fixtures['bodega']->id)->assertStatus(200),
            $fixtures['product']->id
        );

        $this->assertEqualsWithDelta(80, $row['purchases'], 0.01);
        $this->assertEqualsWithDelta(0, $row['returns'], 0.01);
        $this->assertEqualsWithDelta(0, $row['shipments_in'] ?? 0, 0.01);
        $this->assertEqualsWithDelta(0, $row['variation'], 0.01);
    }

    // ------------------------------------------------------------------
    // 4. Histórico con related_document_type NULL: no regresión del import
    // ------------------------------------------------------------------

    public function test_legacy_movement_with_null_document_type_still_counts_as_purchases(): void
    {
        $fixtures = $this->createFixtures();

        InventoryMovement::create([
            'type' => 'entry',
            'product_id' => $fixtures['product']->id,
            'brand_id' => $fixtures['brand']->id,
            'location_id' => $fixtures['bodega']->id,
            'quantity' => 55,
            'unit' => 'kg',
            'movement_date' => self::RECEPTION_DATE,
            'unit_price' => 10,
            'total_price' => 550,
            'responsible_user' => $fixtures['admin']->id,
            'related_document_type' => null,
            'related_document_id' => null,
            'observations' => 'Carga histórica de inventario importado',
        ]);

        $row = $this->rowFor(
            $this->monthlyReport($fixtures, $fixtures['bodega']->id)->assertStatus(200),
            $fixtures['product']->id
        );

        $this->assertEqualsWithDelta(
            55,
            $row['purchases'],
            0.01,
            'El histórico importado (sin documento relacionado) debe seguir contando como compra.'
        );
        $this->assertEqualsWithDelta(0, $row['variation'], 0.01);
    }

    // ------------------------------------------------------------------
    // 5. Ajuste aprobado de entrada: sigue en "increases", nunca en
    //    "purchases" ni en "shipments_in"
    // ------------------------------------------------------------------

    public function test_approved_entry_adjustment_never_counts_as_purchase_or_shipment_in(): void
    {
        $fixtures = $this->createFixtures();

        $adjustment = Adjustment::create([
            'adjustment_number' => Adjustment::generateAdjustmentNumber(),
            'type' => 'entry',
            'reason_id' => $fixtures['reason']->id,
            'notes' => 'Ajuste de prueba',
            'product_id' => $fixtures['product']->id,
            'brand_id' => $fixtures['brand']->id,
            'unit' => 'kg',
            'quantity_mode' => 'delta',
            'quantity' => 12,
            'unit_price' => 5,
            'movement_date' => self::RECEPTION_DATE,
            'status' => 'pending',
            'responsible_user' => $fixtures['admin']->id,
            'destination_location_id' => $fixtures['bodega']->id,
        ]);

        $this->approve($fixtures, $adjustment)->assertStatus(200);

        $row = $this->rowFor(
            $this->monthlyReport($fixtures, $fixtures['bodega']->id)->assertStatus(200),
            $fixtures['product']->id
        );

        $this->assertEqualsWithDelta(12, $row['increases'], 0.01);
        $this->assertEqualsWithDelta(0, $row['purchases'], 0.01);
        $this->assertEqualsWithDelta(0, $row['shipments_in'] ?? 0, 0.01);
        $this->assertEqualsWithDelta(0, $row['variation'], 0.01);
    }
}
