<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationProduct;
use App\Models\BaseUnit;
use App\Models\Brand;
use App\Models\FarmLot;
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
 * "Orden Técnica" (`technical_order`) NO acredita stock a la finca.
 *
 * El producto de una orden técnica se aplica al cultivo el mismo día en que llega
 * a la finca: la finca no lo custodia. Mientras el sistema le creó una ENTRADA de
 * kardex, esa existencia nunca se descargaba y se acumuló: 420.823 unidades
 * fantasma en fincas a jul-2026 (327 salidas, 425.142 unidades). Desde ahora
 * `technical_order` se comporta como `consumption`:
 *
 *   - bodega: se descarga (movimiento `exit`)                    ✅ se mantiene
 *   - el movimiento queda trazable a la finca por el documento    ✅ se mantiene
 *   - finca: NO se le acredita stock (no hay `entry` en destino)  ⬅ el cambio
 *
 * La lista de códigos que se consumen en campo vive en UN solo sitio:
 * `OutputType::DIRECT_CONSUMPTION_CODES` / `OutputType::esConsumoDirecto()`.
 *
 * La otra mitad de la prueba es la NO regresión: `remanente` (la devolución de
 * producto sobrante de la finca a la bodega) SÍ tiene que sumar en el destino.
 * Si alguien mete `remanente` en la lista de consumo directo, la bodega dejaría
 * de recibir lo que le devuelven y el producto se evaporaría.
 *
 * Todas las fechas son fijas para que el resultado no dependa del día de la corrida.
 */
class DirectConsumptionOutputStockTest extends TestCase
{
    use RefreshDatabase;

    /** Fecha real de recepción del lote (la que fecha los movimientos). */
    private const RECEPTION_DATE = '2026-06-17';

    /** Fecha del documento de salida, a propósito distinta de la de recepción. */
    private const OUTPUT_DATE = '2026-06-01';

    /**
     * Catálogo mínimo: admin, producto en 'kg', bodega, finca con un lote de
     * cultivo, y los tipos de salida que se comparan (orden técnica vs remanente).
     */
    private function createFixtures(): array
    {
        $admin = User::create([
            'name' => 'Admin Consumo',
            'email' => 'admin_consumo_' . uniqid() . '@agriflor.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $brand = Brand::create([
            'name' => 'Marca Consumo ' . uniqid(),
            'status' => 'active',
        ]);

        BaseUnit::firstOrCreate(
            ['symbol' => 'kg'],
            ['name' => 'Kilogramos', 'description' => 'Unidad de masa', 'status' => 'active']
        );

        $product = Product::create([
            'name' => 'Producto Consumo',
            'brand_id' => $brand->id,
            'active_ingredient' => 'Mancozeb',
            'min_stock' => 0,
            'status' => 'active',
            'base_unit' => 'kg',
            'created_by' => $admin->id,
        ]);

        $bodega = Location::create([
            'name' => 'Bodega Principal Consumo',
            'type' => 'warehouse',
            'status' => 'active',
        ]);

        $finca = Location::create([
            'name' => 'Finca Consumo',
            'type' => 'farm',
            'status' => 'active',
        ]);

        $farmLot = FarmLot::create([
            'location_id' => $finca->id,
            'name' => 'Lote Consumo',
            'status' => 'active',
        ]);

        $technicalOrderType = OutputType::firstOrCreate(
            ['code' => 'technical_order'],
            ['name' => 'Orden Técnica', 'requires_lots' => false, 'status' => 'active']
        );

        $remanenteType = OutputType::firstOrCreate(
            ['code' => 'remanente'],
            ['name' => 'Remanente', 'requires_lots' => false, 'status' => 'active']
        );

        return compact(
            'admin', 'brand', 'product', 'bodega', 'finca',
            'farmLot', 'technicalOrderType', 'remanenteType'
        );
    }

    /** Existencia previa en una ubicación: lote real en `inventory` + su kardex. */
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
            'movement_date' => '2026-04-10',
            'unit_price' => 10,
            'total_price' => $quantity * 10,
            'responsible_user' => $fixtures['admin']->id,
            'observations' => 'Ajuste inicial',
        ]);
    }

    /**
     * Salida aprobada de un tipo dado, con su recepción pendiente.
     *
     * @param  array<int, string>  $farmLotIds  lotes de cultivo asociados (vacío = sin lotes,
     *                                          que es como llegan las órdenes técnicas reales)
     */
    private function makeOutputReception(
        array $fixtures,
        OutputType $type,
        float $quantity,
        string $originId,
        string $destinationId,
        array $farmLotIds = []
    ): array {
        $output = ProductOutput::create([
            'output_number' => ProductOutput::generateOutputNumber(),
            'output_type_id' => $type->id,
            'output_date' => self::OUTPUT_DATE,
            'origin_location_id' => $originId,
            'destination_location_id' => $destinationId,
            'status' => 'approved',
            'total_cost' => $quantity * 10,
            'responsible_user' => $fixtures['admin']->id,
        ]);

        if (!empty($farmLotIds)) {
            $output->farmLots()->attach($farmLotIds);
        }

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

    /** POST del lote de recepción (es lo que dispara los movimientos de inventario). */
    private function receiveBatch(array $fixtures, Reception $reception, float $quantity): TestResponse
    {
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

    /** Movimientos generados por una recepción, por tipo. */
    private function movementsOf(Reception $reception, string $type)
    {
        return InventoryMovement::where('related_document_type', 'App\Models\Reception')
            ->where('related_document_id', $reception->id)
            ->where('type', $type)
            ->get();
    }

    /** Saldo de kardex de una ubicación (entradas − salidas), a cualquier fecha. */
    private function kardexBalance(array $fixtures, string $locationId): float
    {
        return (float) InventoryMovement::where('product_id', $fixtures['product']->id)
            ->where('location_id', $locationId)
            ->selectRaw("
                SUM(CASE WHEN type = 'entry' THEN quantity ELSE 0 END) -
                SUM(CASE WHEN type IN ('exit', 'transfer', 'application') THEN quantity ELSE 0 END) as saldo
            ")
            ->value('saldo') ?? 0;
    }

    /** Existencia física (tabla `inventory`) de una ubicación. */
    private function physicalStock(array $fixtures, string $locationId): float
    {
        return (float) Inventory::where('product_id', $fixtures['product']->id)
            ->where('location_id', $locationId)
            ->sum('quantity');
    }

    // ------------------------------------------------------------------
    // 1. ORDEN TÉCNICA: descarga bodega y NO acredita stock a la finca
    // ------------------------------------------------------------------

    public function test_technical_order_discharges_warehouse_and_does_not_credit_the_farm(): void
    {
        $fixtures = $this->createFixtures();
        $this->seedOpeningStock($fixtures, $fixtures['bodega']->id, 500);

        // Así llegan las órdenes técnicas reales: SIN lotes de cultivo asociados
        // (output_types.requires_lots = 0; en producción, 327 de 327 sin lote).
        $context = $this->makeOutputReception(
            $fixtures,
            $fixtures['technicalOrderType'],
            140,
            $fixtures['bodega']->id,
            $fixtures['finca']->id
        );

        $this->receiveBatch($fixtures, $context['reception'], 140)->assertStatus(201);

        // --- BODEGA: se descarga, igual que antes ---
        $exits = $this->movementsOf($context['reception'], 'exit');
        $this->assertCount(1, $exits, 'La orden técnica debe seguir generando la salida de bodega.');

        $exit = $exits->first();
        $this->assertSame($fixtures['bodega']->id, $exit->location_id);
        $this->assertEqualsWithDelta(140, (float) $exit->quantity, 0.01);
        $this->assertSame(
            self::RECEPTION_DATE,
            $exit->movement_date->toDateString(),
            'La salida se fecha con la recepción del lote.'
        );

        $this->assertEqualsWithDelta(
            360,
            $this->kardexBalance($fixtures, $fixtures['bodega']->id),
            0.01,
            'El kardex de bodega debe bajar de 500 a 360.'
        );
        $this->assertEqualsWithDelta(
            360,
            $this->physicalStock($fixtures, $fixtures['bodega']->id),
            0.01,
            'La existencia física de bodega debe bajar de 500 a 360 (FIFO).'
        );

        // --- FINCA: no recibe NADA. Este es el cambio. ---
        $this->assertCount(
            0,
            $this->movementsOf($context['reception'], 'entry'),
            'Una orden técnica NO debe crear entrada en la finca: el producto se aplica al cultivo.'
        );
        $this->assertSame(
            0,
            InventoryMovement::where('location_id', $fixtures['finca']->id)->count(),
            'La finca no debe tener ningún movimiento de kardex.'
        );
        $this->assertEqualsWithDelta(
            0,
            $this->kardexBalance($fixtures, $fixtures['finca']->id),
            0.01,
            'El kardex de la finca debe quedar en 0: nada de stock fantasma.'
        );
        $this->assertEqualsWithDelta(
            0,
            $this->physicalStock($fixtures, $fixtures['finca']->id),
            0.01,
            'La finca no debe quedar con existencia física.'
        );

        // --- TRAZABILIDAD: el movimiento sigue apuntando a la finca ---
        $this->assertSame(
            'App\Models\Reception',
            $exit->related_document_type,
            'La salida queda ligada a la recepción...'
        );
        $this->assertSame($context['reception']->id, $exit->related_document_id);
        $this->assertSame(
            $fixtures['finca']->id,
            $context['reception']->fresh()->destination_location_id,
            '...y la recepción sigue diciendo a qué finca fue el producto.'
        );
        $this->assertStringContainsString(
            $fixtures['finca']->name,
            (string) $exit->observations,
            'La observación de la salida debe nombrar la finca de destino.'
        );

        // Sin lotes de cultivo no hay Application posible (applications.farm_lot_id
        // es NOT NULL): la recepción NO debe fallar por eso.
        $this->assertSame(
            0,
            Application::where('product_output_id', $context['output']->id)->count(),
            'Sin lote de cultivo no se crea Application, y la recepción igual se procesa.'
        );
    }

    // ------------------------------------------------------------------
    // 2. REMANENTE: SÍ acredita stock al destino (no se rompió)
    // ------------------------------------------------------------------

    /**
     * El remanente devuelve a la bodega el producto que sobró en la finca. Si
     * alguien lo tratara como consumo directo, la bodega no recibiría nada y el
     * producto desaparecería del sistema.
     */
    public function test_remanente_credits_stock_to_the_destination_warehouse(): void
    {
        $fixtures = $this->createFixtures();
        $this->seedOpeningStock($fixtures, $fixtures['finca']->id, 90);

        $context = $this->makeOutputReception(
            $fixtures,
            $fixtures['remanenteType'],
            30,
            $fixtures['finca']->id,      // origen: la finca devuelve
            $fixtures['bodega']->id      // destino: la bodega recibe
        );

        $this->receiveBatch($fixtures, $context['reception'], 30)->assertStatus(201);

        // --- FINCA (origen): se descarga ---
        $exits = $this->movementsOf($context['reception'], 'exit');
        $this->assertCount(1, $exits);
        $this->assertSame($fixtures['finca']->id, $exits->first()->location_id);
        $this->assertEqualsWithDelta(60, $this->kardexBalance($fixtures, $fixtures['finca']->id), 0.01);

        // --- BODEGA (destino): RECIBE. Esto es lo que no se puede romper. ---
        $entries = $this->movementsOf($context['reception'], 'entry');
        $this->assertCount(1, $entries, 'El remanente SÍ debe crear la entrada en el destino.');

        $entry = $entries->first();
        $this->assertSame($fixtures['bodega']->id, $entry->location_id);
        $this->assertEqualsWithDelta(30, (float) $entry->quantity, 0.01);
        $this->assertSame(
            self::RECEPTION_DATE,
            $entry->movement_date->toDateString(),
            'Entrada y salida comparten fecha.'
        );

        $this->assertEqualsWithDelta(
            30,
            $this->kardexBalance($fixtures, $fixtures['bodega']->id),
            0.01,
            'El kardex de la bodega debe sumar el remanente devuelto.'
        );
        $this->assertEqualsWithDelta(
            30,
            $this->physicalStock($fixtures, $fixtures['bodega']->id),
            0.01,
            'La existencia física de la bodega debe sumar el remanente devuelto.'
        );
    }

    // ------------------------------------------------------------------
    // 3. La fuente de verdad, dicha una sola vez
    // ------------------------------------------------------------------

    /**
     * Blinda la lista contra un "arreglo" descuidado: agregar 'transfer',
     * 'remanente' o 'free_request' a DIRECT_CONSUMPTION_CODES haría que el
     * destino dejara de recibir producto, y esta prueba lo caza sin necesidad
     * de montar toda una recepción.
     */
    public function test_direct_consumption_codes_are_exactly_consumption_and_technical_order(): void
    {
        $this->assertTrue(OutputType::esConsumoDirecto('technical_order'));
        $this->assertTrue(OutputType::esConsumoDirecto('consumption'));

        foreach (['transfer', 'remanente', 'free_request'] as $code) {
            $this->assertFalse(
                OutputType::esConsumoDirecto($code),
                "'{$code}' mueve producto entre ubicaciones que lo custodian: DEBE acreditar el destino."
            );
        }

        // Salida sin tipo (o con el tipo borrado): se comporta como traslado.
        $this->assertFalse(OutputType::esConsumoDirecto(null));

        $this->assertSame(
            ['consumption', 'technical_order'],
            OutputType::DIRECT_CONSUMPTION_CODES
        );
    }

    // ------------------------------------------------------------------
    // 4. Orden técnica CON lote de cultivo: además genera la Application
    // ------------------------------------------------------------------

    /**
     * Cuando la orden técnica sí trae lote de cultivo, hereda la trazabilidad
     * fina que ya tenía el consumo: Application + ApplicationProduct fechados el
     * día de la recepción. Y sigue sin acreditar stock a la finca.
     */
    public function test_technical_order_with_farm_lot_creates_the_application(): void
    {
        $fixtures = $this->createFixtures();
        $this->seedOpeningStock($fixtures, $fixtures['bodega']->id, 200);

        $context = $this->makeOutputReception(
            $fixtures,
            $fixtures['technicalOrderType'],
            45,
            $fixtures['bodega']->id,
            $fixtures['finca']->id,
            [$fixtures['farmLot']->id]
        );

        $this->receiveBatch($fixtures, $context['reception'], 45)->assertStatus(201);

        $this->assertCount(0, $this->movementsOf($context['reception'], 'entry'));

        $application = Application::where('product_output_id', $context['output']->id)->first();
        $this->assertNotNull($application, 'Con lote de cultivo, la orden técnica genera Application.');
        $this->assertSame($fixtures['farmLot']->id, $application->farm_lot_id);
        $this->assertSame(
            self::RECEPTION_DATE,
            $application->application_date->toDateString(),
            'La aplicación se fecha el día en que se aplicó, no el día del registro.'
        );

        $applicationProduct = ApplicationProduct::where('application_id', $application->id)->first();
        $this->assertNotNull($applicationProduct);
        $this->assertSame($fixtures['product']->id, $applicationProduct->product_id);
        $this->assertEqualsWithDelta(45, (float) $applicationProduct->quantity, 0.01);
    }

    // ------------------------------------------------------------------
    // 5. EL INVARIANTE DE NEGOCIO: el Inventario Mensual de la bodega cuadra
    // ------------------------------------------------------------------

    /**
     * Quitar la entrada en la finca deja huérfana la salida de bodega en el
     * informe mensual: la matriz "Enviado a finca X" se arma leyendo la ENTRADA
     * del destino, así que sin nada que leer los 140 kg no los explicaba ninguna
     * columna y caían enteros en "Variación" (medido: variation = −140, y en
     * producción serían ~150.000 unidades al mes), que es justo la celda que el
     * cliente concilia contra contabilidad.
     *
     * Por eso el informe atribuye estos envíos por el DOCUMENTO
     * (receptions.destination_location_id). Esta prueba es la que hay que romper
     * para volver a descuadrar el informe.
     */
    public function test_technical_order_still_balances_the_warehouse_monthly_report(): void
    {
        $fixtures = $this->createFixtures();
        $this->seedOpeningStock($fixtures, $fixtures['bodega']->id, 500);

        $context = $this->makeOutputReception(
            $fixtures,
            $fixtures['technicalOrderType'],
            140,
            $fixtures['bodega']->id,
            $fixtures['finca']->id
        );

        $this->receiveBatch($fixtures, $context['reception'], 140)->assertStatus(201);

        $row = $this->monthlyReportRowFor($fixtures, $fixtures['bodega']->id);

        $this->assertEqualsWithDelta(500, $row['initial_stock'], 0.01);
        $this->assertEqualsWithDelta(360, $row['final_stock'], 0.01);
        $this->assertEqualsWithDelta(
            140,
            $row['total_shipped'],
            0.01,
            'Lo enviado por orden técnica debe seguir contándose como envío desde la bodega.'
        );
        $this->assertEqualsWithDelta(
            140,
            $row['farm_shipments'][$fixtures['finca']->id] ?? 0,
            0.01,
            'Y debe quedar atribuido a la finca de destino, aunque la finca no reciba stock.'
        );
        $this->assertEqualsWithDelta(
            0,
            $row['variation'],
            0.01,
            'Variación de la bodega debe ser 0: es la celda que se concilia contra contabilidad.'
        );

        // La finca no aparece con existencias en su propio informe.
        $this->assertNull(
            $this->monthlyReportRowFor($fixtures, $fixtures['finca']->id, false),
            'La finca no debe figurar con stock ni movimientos en el informe mensual.'
        );
    }

    /**
     * Fila del Inventario Mensual de una ubicación para el mes del lote recibido.
     * Devuelve null cuando el producto no aparece (sin actividad ni existencias).
     */
    private function monthlyReportRowFor(array $fixtures, string $locationId, bool $required = true): ?array
    {
        $response = $this->actingAs($fixtures['admin'], 'api')->getJson(
            '/api/inventory/monthly-report'
            . '?month=' . intval(substr(self::RECEPTION_DATE, 5, 2))
            . '&year=' . intval(substr(self::RECEPTION_DATE, 0, 4))
            . '&location_id=' . $locationId
        )->assertStatus(200);

        foreach ($response->json('data.products') ?? [] as $row) {
            if ($row['product_id'] === $fixtures['product']->id) {
                return $row;
            }
        }

        if ($required) {
            $this->fail("El producto no aparece en el informe mensual de {$locationId}.");
        }

        return null;
    }
}
