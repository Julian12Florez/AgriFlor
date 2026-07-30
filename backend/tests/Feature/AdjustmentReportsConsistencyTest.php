<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\InventoryController;
use App\Models\Adjustment;
use App\Models\AdjustmentReason;
use App\Models\BaseUnit;
use App\Models\Brand;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\PackagingUnit;
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
     * Este archivo prueba la CLASIFICACIÓN de movimientos en los informes, no
     * el cierre contable de PR-A/AJ-2 (que tiene sus propias pruebas en
     * AdjustmentTest.php): se mueve `closed_period_until` bien atrás de
     * OPENING_DATE/ADJUSTMENT_DATE (ambas de 2026) para que ese chequeo, que
     * SÍ corre en store()/approve(), nunca interfiera con fechas históricas
     * usadas aquí para ejercitar el mes del informe.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['adjustments.closed_period_until' => '2000-01-01']);
    }

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

    private function farmMonthlyReport(User $admin, string $locationId): TestResponse
    {
        return $this->actingAs($admin, 'api')->getJson(
            '/api/inventory/farm-monthly-report'
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
        // No regresión del fix de traslados a ubicaciones que no son fincas: este
        // envío YA lo cuenta la matriz de fincas, así que no puede contarse otra
        // vez como traslado saliente (total_shipped sería 20 y la variación −10).
        $this->assertEqualsWithDelta(0, $origin['transfers_out'], 0.01);
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

    /**
     * Un traslado cuyo destino NO es una finca (el caso que el propio módulo
     * publicita: "devolver producto de mi finca a la bodega central") también
     * tiene que cuadrar en el informe del ORIGEN.
     *
     * La matriz de envíos solo recorre ubicaciones type='farm', así que esta
     * salida no aparecía en ninguna columna y el informe de la finca quedaba con
     * variation = −30: un descuadre de 30 kg justo en la columna que el cliente
     * concilia contra contabilidad.
     */
    public function test_approved_transfer_farm_to_warehouse_balances_origin_and_destination(): void
    {
        $fixtures = $this->createFixtures();
        $this->seedOpeningStock($fixtures, $fixtures['finca']->id, 100);

        $adjustment = $this->makePendingAdjustment($fixtures, [
            'type' => 'transfer',
            'quantity' => 30,
            'origin_location_id' => $fixtures['finca']->id,
            'destination_location_id' => $fixtures['bodega']->id,
        ]);

        $this->approve($fixtures['admin'], $adjustment)->assertStatus(200);

        $origin = $this->rowFor(
            $this->monthlyReport($fixtures['admin'], $fixtures['finca']->id)->assertStatus(200),
            $fixtures['product']->id
        );

        $this->assertEqualsWithDelta(100, $origin['initial_stock'], 0.01);
        // Contabilizada como traslado saliente, no como disminución (un traslado
        // no es un ajuste neto) ni como envío a finca (el destino es una bodega).
        $this->assertEqualsWithDelta(30, $origin['transfers_out'], 0.01);
        $this->assertEqualsWithDelta(30, $origin['total_shipped'], 0.01);
        $this->assertEqualsWithDelta(0, $origin['decreases'], 0.01);
        $this->assertEqualsWithDelta(70, $origin['final_stock'], 0.01);
        $this->assertEqualsWithDelta(70, $origin['current_stock'], 0.01);
        $this->assertEqualsWithDelta(0, $origin['variation'], 0.01);

        $destination = $this->rowFor(
            $this->monthlyReport($fixtures['admin'], $fixtures['bodega']->id)->assertStatus(200),
            $fixtures['product']->id
        );

        $this->assertEqualsWithDelta(30, $destination['increases'], 0.01);
        $this->assertEqualsWithDelta(0, $destination['purchases'], 0.01);
        $this->assertEqualsWithDelta(30, $destination['final_stock'], 0.01);
        $this->assertEqualsWithDelta(0, $destination['variation'], 0.01);
    }

    /**
     * Mismo descuadre entre dos bodegas: ninguna es finca, así que la matriz de
     * envíos no ve nada y la salida quedaba sin explicar en el origen.
     */
    public function test_approved_transfer_warehouse_to_warehouse_balances_origin_and_destination(): void
    {
        $fixtures = $this->createFixtures();
        $this->seedOpeningStock($fixtures, $fixtures['bodega']->id, 100);

        $otherWarehouse = Location::create([
            'name' => 'Bodega Secundaria Test',
            'type' => 'warehouse',
            'status' => 'active',
        ]);

        $adjustment = $this->makePendingAdjustment($fixtures, [
            'type' => 'transfer',
            'quantity' => 30,
            'origin_location_id' => $fixtures['bodega']->id,
            'destination_location_id' => $otherWarehouse->id,
        ]);

        $this->approve($fixtures['admin'], $adjustment)->assertStatus(200);

        $origin = $this->rowFor(
            $this->monthlyReport($fixtures['admin'], $fixtures['bodega']->id)->assertStatus(200),
            $fixtures['product']->id
        );

        $this->assertEqualsWithDelta(30, $origin['transfers_out'], 0.01);
        $this->assertEqualsWithDelta(0, $origin['decreases'], 0.01);
        $this->assertEqualsWithDelta(70, $origin['final_stock'], 0.01);
        $this->assertEqualsWithDelta(0, $origin['variation'], 0.01);

        $destination = $this->rowFor(
            $this->monthlyReport($fixtures['admin'], $otherWarehouse->id)->assertStatus(200),
            $fixtures['product']->id
        );

        $this->assertEqualsWithDelta(30, $destination['increases'], 0.01);
        $this->assertEqualsWithDelta(0, $destination['transfers_out'], 0.01);
        $this->assertEqualsWithDelta(30, $destination['final_stock'], 0.01);
        $this->assertEqualsWithDelta(0, $destination['variation'], 0.01);
    }

    /**
     * El informe mensual suma `inventory_movements.quantity` EN CRUDO, sin
     * convertir: si el movimiento del ajuste se guardara en la unidad de captura
     * (2 "Bulto"), el informe sumaría 2 donde el inventario real subió 100 kg.
     *
     * Lo insidioso del defecto era que "Variación" seguía en 0 —el mismo error
     * entra en los dos lados de la resta— así que el descuadre pasaba
     * desapercibido y solo se veía comparando `final_stock` contra el stock real
     * (medido en producción: 15.694 informado contra 15.400 reales, −294 kg).
     */
    public function test_monthly_report_balances_when_adjustment_is_captured_in_a_packaging_unit(): void
    {
        $fixtures = $this->createFixtures();
        $this->seedOpeningStock($fixtures, $fixtures['bodega']->id, 1000);

        $bulto = PackagingUnit::create([
            'name' => 'Bulto',
            'base_quantity' => 50,
            'base_unit' => 'kg',
        ]);
        $fixtures['product']->packagingUnits()->attach($bulto->id);

        $adjustment = $this->makePendingAdjustment($fixtures, [
            'type' => 'entry',
            'unit' => 'Bulto',
            'quantity' => 2, // = 100 kg
            'unit_price' => 3, // por unidad base
            'destination_location_id' => $fixtures['bodega']->id,
        ]);

        $this->approve($fixtures['admin'], $adjustment)->assertStatus(200);

        $row = $this->rowFor(
            $this->monthlyReport($fixtures['admin'], $fixtures['bodega']->id)->assertStatus(200),
            $fixtures['product']->id
        );

        // El informe habla en unidad base, igual que el inventario real.
        $this->assertEqualsWithDelta(100, $row['increases'], 0.01);
        $this->assertEqualsWithDelta(1100, $row['final_stock'], 0.01);
        $this->assertEqualsWithDelta(1100, $row['current_stock'], 0.01);
        $this->assertEqualsWithDelta(0, $row['variation'], 0.01);

        // Y el stock real de la tabla inventory coincide con lo informado.
        $this->assertEqualsWithDelta(
            1100,
            (float) Inventory::where('location_id', $fixtures['bodega']->id)->sum('quantity'),
            0.01
        );
    }

    /**
     * El inventario por finca calculaba `final = inicial + entradas − consumo −
     * remanente`, con consumo/remanente derivados SOLO de los documentos de
     * salida: un ajuste de salida en la finca no restaba nada y el informe
     * reportaba stock que la finca ya no tiene (medido: final_stock 100 con 70
     * reales), además de romper la continuidad final(mes N) == inicial(mes N+1).
     */
    public function test_farm_monthly_report_subtracts_adjustment_exits(): void
    {
        $fixtures = $this->createFixtures();
        $this->seedOpeningStock($fixtures, $fixtures['finca']->id, 100);

        $adjustment = $this->makePendingAdjustment($fixtures, [
            'type' => 'exit',
            'quantity' => 30,
            'origin_location_id' => $fixtures['finca']->id,
            'destination_location_id' => null,
        ]);

        $this->approve($fixtures['admin'], $adjustment)->assertStatus(200);

        $row = $this->rowFor(
            $this->farmMonthlyReport($fixtures['admin'], $fixtures['finca']->id)->assertStatus(200),
            $fixtures['product']->id
        );

        $this->assertEqualsWithDelta(100, $row['initial_stock'], 0.01);
        $this->assertEqualsWithDelta(30, $row['other_exits'], 0.01);
        $this->assertEqualsWithDelta(70, $row['final_stock'], 0.01);

        // El stock real de la finca, que es lo que el informe debe reflejar.
        $this->assertEqualsWithDelta(
            70,
            (float) Inventory::where('location_id', $fixtures['finca']->id)->sum('quantity'),
            0.01
        );
    }

    /**
     * La salida de un TRASLADO desde la finca también sale del stock de la finca,
     * así que el informe por finca debe restarla igual que un ajuste de salida.
     */
    public function test_farm_monthly_report_subtracts_transfer_exits(): void
    {
        $fixtures = $this->createFixtures();
        $this->seedOpeningStock($fixtures, $fixtures['finca']->id, 100);

        $adjustment = $this->makePendingAdjustment($fixtures, [
            'type' => 'transfer',
            'quantity' => 30,
            'origin_location_id' => $fixtures['finca']->id,
            'destination_location_id' => $fixtures['bodega']->id,
        ]);

        $this->approve($fixtures['admin'], $adjustment)->assertStatus(200);

        $row = $this->rowFor(
            $this->farmMonthlyReport($fixtures['admin'], $fixtures['finca']->id)->assertStatus(200),
            $fixtures['product']->id
        );

        $this->assertEqualsWithDelta(30, $row['other_exits'], 0.01);
        $this->assertEqualsWithDelta(70, $row['final_stock'], 0.01);
    }

    /**
     * Guarda del clasificador de aumentos/disminuciones: sin patrones de texto, la
     * rama del histórico NO puede degenerar en una tautología ("no viene de un
     * ajuste"), que sumaría todos los movimientos del rango en la columna.
     */
    public function test_classification_without_legacy_patterns_matches_nothing(): void
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

        $controller = app(InventoryController::class);
        $method = new \ReflectionMethod($controller, 'whereClassifiedAsAdjustment');
        $method->setAccessible(true);

        $withPatterns = $method->invoke(
            $controller,
            InventoryMovement::where('product_id', $fixtures['product']->id),
            ['entry'],
            ['%aumento%', '%ajuste%positiv%']
        );
        $this->assertSame(1, (int) $withPatterns->count(), 'Con patrones, el movimiento histórico sí cuenta.');

        $withoutPatterns = $method->invoke(
            $controller,
            InventoryMovement::where('product_id', $fixtures['product']->id),
            ['entry'],
            []
        );
        $this->assertSame(0, (int) $withoutPatterns->count(), 'Sin patrones no puede contar NINGÚN movimiento.');
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
