<?php

namespace Tests\Feature;

use App\Models\Adjustment;
use App\Models\AdjustmentReason;
use App\Models\BaseUnit;
use App\Models\Brand;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Task 9: los informes deben clasificar correctamente los movimientos que genera
 * la aprobación de un ajuste.
 *
 * El invariante que protege este archivo es `variation == 0`: el inventario
 * mensual es el informe que el cliente concilia contra contabilidad, y su
 * columna "Variación" (final − inicial − movimientos) debe quedar en cero
 * cuando TODO el movimiento del mes está explicado por alguna columna. Si un
 * ajuste no cae en la columna que le corresponde —o cae en DOS— el informe
 * descuadra.
 *
 * Todas las fechas son fijas (marzo de 2026) para que el mes del informe no
 * dependa del día en que corran las pruebas.
 */
class AdjustmentReportsConsistencyTest extends TestCase
{
    use RefreshDatabase;

    /** Mes/año del informe que se consulta en todas las pruebas. */
    private const REPORT_MONTH = 3;
    private const REPORT_YEAR = 2026;

    /** Fecha real del ajuste (dentro del mes del informe). */
    private const ADJUSTMENT_DATE = '2026-03-15';

    /** Fecha de la carga de existencias previa (mes anterior al informe). */
    private const OPENING_DATE = '2026-01-20';

    /**
     * Catálogo mínimo para armar ajustes y leer informes: un admin (único rol
     * que puede aprobar), un producto con unidad base 'kg', una BODEGA y una
     * FINCA. El tipo de cada ubicación importa: el inventario mensual arma la
     * matriz de envíos con las ubicaciones de tipo 'farm'.
     */
    private function createFixtures(): array
    {
        $admin = User::create([
            'name' => 'Admin Informes',
            'email' => 'admin_' . uniqid() . '@agriflor.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $brand = Brand::create([
            'name' => 'Marca Informes ' . uniqid(),
            'status' => 'active',
        ]);

        BaseUnit::firstOrCreate(
            ['symbol' => 'kg'],
            ['name' => 'Kilogramos', 'description' => 'Unidad de masa', 'status' => 'active']
        );

        $product = Product::create([
            'name' => 'Producto Informes',
            'brand_id' => $brand->id,
            'active_ingredient' => 'Glifosato',
            'min_stock' => 0,
            'status' => 'active',
            'base_unit' => 'kg',
            'created_by' => $admin->id,
        ]);

        $reason = AdjustmentReason::create([
            'code' => 'motivo_informes_' . uniqid(),
            'name' => 'Motivo de prueba',
            'direction' => 'any',
            'active' => true,
        ]);

        $bodega = Location::create([
            'name' => 'Bodega Central Test',
            'type' => 'warehouse',
            'status' => 'active',
        ]);

        $finca = Location::create([
            'name' => 'Finca Test',
            'type' => 'farm',
            'status' => 'active',
        ]);

        return compact('admin', 'brand', 'product', 'reason', 'bodega', 'finca');
    }

    private function makePendingAdjustment(array $fixtures, array $overrides = []): Adjustment
    {
        return Adjustment::create(array_merge([
            'adjustment_number' => Adjustment::generateAdjustmentNumber(),
            'type' => 'entry',
            'reason_id' => $fixtures['reason']->id,
            'notes' => 'Ajuste de prueba',
            'product_id' => $fixtures['product']->id,
            'brand_id' => $fixtures['brand']->id,
            'unit' => 'kg',
            'quantity_mode' => 'delta',
            'quantity' => 10,
            'unit_price' => 5,
            'movement_date' => self::ADJUSTMENT_DATE,
            'status' => 'pending',
            'responsible_user' => $fixtures['admin']->id,
        ], $overrides));
    }

    /**
     * Existencia inicial en una ubicación ANTES del mes del informe: el lote
     * real en `inventory` (lo que consume FIFO al aprobar una salida) más el
     * movimiento de kardex del que el informe deriva el inventario inicial.
     */
    private function seedOpeningStock(array $fixtures, string $locationId, float $quantity): void
    {
        Inventory::create([
            'product_id' => $fixtures['product']->id,
            'brand_id' => $fixtures['brand']->id,
            'location_id' => $locationId,
            'batch_number' => 'LOTE-APERTURA',
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
            'related_document_type' => 'App\Models\Reception',
            'related_document_id' => $fixtures['admin']->id,
            'observations' => 'Recepción de compra inicial',
        ]);
    }

    private function approve(User $admin, Adjustment $adjustment): TestResponse
    {
        return $this->actingAs($admin, 'api')
            ->putJson("/api/adjustments/{$adjustment->id}/approve");
    }

    private function monthlyReport(User $admin, string $locationId): TestResponse
    {
        return $this->actingAs($admin, 'api')->getJson(
            '/api/inventory/monthly-report'
            . '?month=' . self::REPORT_MONTH
            . '&year=' . self::REPORT_YEAR
            . '&location_id=' . $locationId
        );
    }

    /**
     * Fila del informe correspondiente al producto de los fixtures.
     */
    private function rowFor(TestResponse $response, string $productId): array
    {
        $rows = $response->json('data.products') ?? [];

        foreach ($rows as $row) {
            if ($row['product_id'] === $productId) {
                return $row;
            }
        }

        $this->fail("El producto {$productId} no aparece en el informe mensual.");
    }

    // ------------------------------------------------------------------
    // 1. Ajuste de ENTRADA aprobado
    // ------------------------------------------------------------------

    public function test_approved_entry_adjustment_counts_as_increase_and_never_as_purchase(): void
    {
        $fixtures = $this->createFixtures();

        $adjustment = $this->makePendingAdjustment($fixtures, [
            'type' => 'entry',
            'quantity' => 10,
            'destination_location_id' => $fixtures['bodega']->id,
        ]);

        $this->approve($fixtures['admin'], $adjustment)->assertStatus(200);

        $row = $this->rowFor(
            $this->monthlyReport($fixtures['admin'], $fixtures['bodega']->id)->assertStatus(200),
            $fixtures['product']->id
        );

        $this->assertEqualsWithDelta(10, $row['increases'], 0.01);
        // Un ajuste NO es una compra: contarlo también ahí lo duplicaría.
        $this->assertEqualsWithDelta(0, $row['purchases'], 0.01);
        $this->assertEqualsWithDelta(0, $row['decreases'], 0.01);
        $this->assertEqualsWithDelta(0, $row['variation'], 0.01);
    }

    // ------------------------------------------------------------------
    // 2. Ajuste de SALIDA aprobado
    // ------------------------------------------------------------------

    public function test_approved_exit_adjustment_counts_as_decrease(): void
    {
        $fixtures = $this->createFixtures();
        $this->seedOpeningStock($fixtures, $fixtures['bodega']->id, 50);

        $adjustment = $this->makePendingAdjustment($fixtures, [
            'type' => 'exit',
            'quantity' => 10,
            'origin_location_id' => $fixtures['bodega']->id,
            'destination_location_id' => null,
        ]);

        $this->approve($fixtures['admin'], $adjustment)->assertStatus(200);

        $row = $this->rowFor(
            $this->monthlyReport($fixtures['admin'], $fixtures['bodega']->id)->assertStatus(200),
            $fixtures['product']->id
        );

        $this->assertEqualsWithDelta(50, $row['initial_stock'], 0.01);
        $this->assertEqualsWithDelta(10, $row['decreases'], 0.01);
        $this->assertEqualsWithDelta(0, $row['increases'], 0.01);
        $this->assertEqualsWithDelta(0, $row['total_shipped'], 0.01);
        $this->assertEqualsWithDelta(40, $row['final_stock'], 0.01);
        $this->assertEqualsWithDelta(0, $row['variation'], 0.01);
    }

    // ------------------------------------------------------------------
    // 3. TRASLADO bodega → finca aprobado
    // ------------------------------------------------------------------

    public function test_approved_transfer_warehouse_to_farm_balances_origin_and_destination(): void
    {
        $fixtures = $this->createFixtures();
        $this->seedOpeningStock($fixtures, $fixtures['bodega']->id, 50);

        $adjustment = $this->makePendingAdjustment($fixtures, [
            'type' => 'transfer',
            'quantity' => 10,
            'origin_location_id' => $fixtures['bodega']->id,
            'destination_location_id' => $fixtures['finca']->id,
        ]);

        $this->approve($fixtures['admin'], $adjustment)->assertStatus(200);

        // ORIGEN (bodega): la salida se refleja como ENVÍO a la finca, no como
        // disminución. Contarla en las dos columnas la restaría dos veces.
        $origin = $this->rowFor(
            $this->monthlyReport($fixtures['admin'], $fixtures['bodega']->id)->assertStatus(200),
            $fixtures['product']->id
        );

        $this->assertEqualsWithDelta(0, $origin['decreases'], 0.01);
        $this->assertEqualsWithDelta(10, $origin['total_shipped'], 0.01);
        $this->assertEqualsWithDelta(10, $origin['farm_shipments'][$fixtures['finca']->id] ?? 0, 0.01);
        $this->assertEqualsWithDelta(40, $origin['final_stock'], 0.01);
        $this->assertEqualsWithDelta(0, $origin['variation'], 0.01);

        // DESTINO (finca): la entrada sí es un ingreso de existencias y ninguna
        // otra columna la explica, así que cuenta como aumento (y NO como compra).
        $destination = $this->rowFor(
            $this->monthlyReport($fixtures['admin'], $fixtures['finca']->id)->assertStatus(200),
            $fixtures['product']->id
        );

        $this->assertEqualsWithDelta(10, $destination['increases'], 0.01);
        $this->assertEqualsWithDelta(0, $destination['purchases'], 0.01);
        $this->assertEqualsWithDelta(0, $destination['decreases'], 0.01);
        $this->assertEqualsWithDelta(10, $destination['final_stock'], 0.01);
        $this->assertEqualsWithDelta(0, $destination['variation'], 0.01);
    }

    // ------------------------------------------------------------------
    // 4. Robustez ante el texto que escribe el usuario
    // ------------------------------------------------------------------

    /**
     * Las notas las escribe el solicitante y terminan copiadas en las
     * observaciones del movimiento. Si el informe clasificara SOLO por texto,
     * una nota con la palabra "disminución" convertiría la salida de un
     * traslado en una disminución: se restaría en la columna "Disminuciones"
     * ADEMÁS de en la matriz de envíos (ambas patas comparten
     * related_document_id) y la "Variación" del origen dejaría de ser 0.
     */
    public function test_transfer_notes_mentioning_disminucion_or_aumento_do_not_change_classification(): void
    {
        $fixtures = $this->createFixtures();
        $this->seedOpeningStock($fixtures, $fixtures['bodega']->id, 50);

        $adjustment = $this->makePendingAdjustment($fixtures, [
            'type' => 'transfer',
            'quantity' => 10,
            'origin_location_id' => $fixtures['bodega']->id,
            'destination_location_id' => $fixtures['finca']->id,
            'notes' => 'Traslado por disminución de consumo y aumento de siembra en la finca',
        ]);

        $this->approve($fixtures['admin'], $adjustment)->assertStatus(200);

        $origin = $this->rowFor(
            $this->monthlyReport($fixtures['admin'], $fixtures['bodega']->id)->assertStatus(200),
            $fixtures['product']->id
        );

        // La nota dice "disminución" pero el documento es un traslado: la salida
        // ya está contada como envío.
        $this->assertEqualsWithDelta(0, $origin['decreases'], 0.01);
        // Y la nota dice "aumento" sin que eso infle los aumentos del origen.
        $this->assertEqualsWithDelta(0, $origin['increases'], 0.01);
        $this->assertEqualsWithDelta(10, $origin['total_shipped'], 0.01);
        $this->assertEqualsWithDelta(0, $origin['variation'], 0.01);

        $destination = $this->rowFor(
            $this->monthlyReport($fixtures['admin'], $fixtures['finca']->id)->assertStatus(200),
            $fixtures['product']->id
        );

        // En el destino la entrada del traslado sigue siendo un aumento aunque
        // la nota mencione "disminución".
        $this->assertEqualsWithDelta(10, $destination['increases'], 0.01);
        $this->assertEqualsWithDelta(0, $destination['decreases'], 0.01);
        $this->assertEqualsWithDelta(0, $destination['variation'], 0.01);
    }

    /**
     * Contraparte del caso anterior en un ajuste NETO: un ajuste de entrada
     * cuya nota menciona "disminución" sigue siendo un aumento, y uno de salida
     * cuya nota menciona "aumento" sigue siendo una disminución.
     */
    public function test_net_adjustment_classification_ignores_misleading_notes(): void
    {
        $fixtures = $this->createFixtures();
        $this->seedOpeningStock($fixtures, $fixtures['bodega']->id, 50);

        $entry = $this->makePendingAdjustment($fixtures, [
            'type' => 'entry',
            'quantity' => 6,
            'destination_location_id' => $fixtures['bodega']->id,
            'notes' => 'Sobrante hallado tras la disminución del conteo anterior',
        ]);
        $this->approve($fixtures['admin'], $entry)->assertStatus(200);

        $exit = $this->makePendingAdjustment($fixtures, [
            'type' => 'exit',
            'quantity' => 4,
            'origin_location_id' => $fixtures['bodega']->id,
            'destination_location_id' => null,
            'notes' => 'Merma detectada tras el aumento del conteo anterior',
        ]);
        $this->approve($fixtures['admin'], $exit)->assertStatus(200);

        $row = $this->rowFor(
            $this->monthlyReport($fixtures['admin'], $fixtures['bodega']->id)->assertStatus(200),
            $fixtures['product']->id
        );

        $this->assertEqualsWithDelta(6, $row['increases'], 0.01);
        $this->assertEqualsWithDelta(4, $row['decreases'], 0.01);
        $this->assertEqualsWithDelta(0, $row['purchases'], 0.01);
        $this->assertEqualsWithDelta(52, $row['final_stock'], 0.01);
        $this->assertEqualsWithDelta(0, $row['variation'], 0.01);
    }

    // ------------------------------------------------------------------
    // 5. Retrocompatibilidad con el histórico
    // ------------------------------------------------------------------

    /**
     * Los ajustes anteriores a este módulo se registraron como movimientos
     * sueltos, sin documento relacionado, y su única marca es el texto de la
     * observación. Siguen clasificándose por texto.
     */
    public function test_legacy_movement_without_related_document_still_counts_as_increase(): void
    {
        $fixtures = $this->createFixtures();

        InventoryMovement::create([
            'type' => 'entry',
            'product_id' => $fixtures['product']->id,
            'brand_id' => $fixtures['brand']->id,
            'location_id' => $fixtures['bodega']->id,
            'quantity' => 7,
            'unit' => 'kg',
            'movement_date' => self::ADJUSTMENT_DATE,
            'unit_price' => 10,
            'total_price' => 70,
            'responsible_user' => $fixtures['admin']->id,
            'related_document_type' => null,
            'related_document_id' => null,
            'observations' => 'Ajuste positivo de inventario (carga histórica)',
        ]);

        $row = $this->rowFor(
            $this->monthlyReport($fixtures['admin'], $fixtures['bodega']->id)->assertStatus(200),
            $fixtures['product']->id
        );

        $this->assertEqualsWithDelta(7, $row['increases'], 0.01);
    }

    /**
     * Un movimiento legacy de salida con la marca de texto negativa sigue
     * cayendo en "Disminuciones".
     */
    public function test_legacy_movement_without_related_document_still_counts_as_decrease(): void
    {
        $fixtures = $this->createFixtures();
        $this->seedOpeningStock($fixtures, $fixtures['bodega']->id, 50);

        InventoryMovement::create([
            'type' => 'exit',
            'product_id' => $fixtures['product']->id,
            'brand_id' => $fixtures['brand']->id,
            'location_id' => $fixtures['bodega']->id,
            'quantity' => 3,
            'unit' => 'kg',
            'movement_date' => self::ADJUSTMENT_DATE,
            'unit_price' => 10,
            'total_price' => 30,
            'responsible_user' => $fixtures['admin']->id,
            'related_document_type' => null,
            'related_document_id' => null,
            'observations' => 'Ajuste negativo por disminución de inventario (carga histórica)',
        ]);

        $row = $this->rowFor(
            $this->monthlyReport($fixtures['admin'], $fixtures['bodega']->id)->assertStatus(200),
            $fixtures['product']->id
        );

        $this->assertEqualsWithDelta(3, $row['decreases'], 0.01);
        $this->assertEqualsWithDelta(0, $row['variation'], 0.01);
    }

    // ------------------------------------------------------------------
    // 6. Kardex
    // ------------------------------------------------------------------

    public function test_kardex_shows_adjustment_movement_with_document_and_real_date(): void
    {
        $fixtures = $this->createFixtures();

        $adjustment = $this->makePendingAdjustment($fixtures, [
            'type' => 'entry',
            'quantity' => 10,
            'destination_location_id' => $fixtures['bodega']->id,
        ]);

        $this->approve($fixtures['admin'], $adjustment)->assertStatus(200);

        $response = $this->actingAs($fixtures['admin'], 'api')
            ->getJson('/api/inventory/movements?product_id=' . $fixtures['product']->id)
            ->assertStatus(200);

        $movements = collect($response->json('data'))
            ->where('related_document_id', $adjustment->id)
            ->values();

        $this->assertCount(1, $movements);
        $this->assertSame('App\Models\Adjustment', $movements[0]['related_document_type']);
        $this->assertSame(self::ADJUSTMENT_DATE, $movements[0]['movement_date']);
        $this->assertSame('entry', $movements[0]['type']);
    }
}
