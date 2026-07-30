<?php

namespace Tests\Feature;

use App\Models\Application;
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
 * PR-1: la fecha del movimiento de inventario de una recepción es la
 * `reception_date` del LOTE que se está recibiendo, y es LA MISMA en las dos
 * patas del traslado (salida en el origen y entrada en el destino).
 *
 * Por qué importa: el informe de Inventario Mensual (el que el cliente concilia
 * contra contabilidad) calcula el stock del origen con el `movement_date` del
 * movimiento `exit`, pero la columna "Enviado a finca X" la calcula leyendo el
 * `movement_date` de la `entry` en el destino. Si las dos patas llevan fechas
 * distintas, el envío aparece en un mes y el descuento en otro: el informe
 * descuadra y la columna "Variación" deja de ser cero.
 *
 * Todas las fechas son fijas (junio de 2026) para que el resultado no dependa
 * del día en que corran las pruebas: la `reception_date` del lote está en un mes
 * ANTERIOR a hoy, que es exactamente el caso que el bug rompía (fechaba la
 * entrada con `now()`).
 */
class OutputReceptionMovementDateTest extends TestCase
{
    use RefreshDatabase;

    /** Fecha real de recepción del lote: mes anterior a "hoy" en cualquier corrida. */
    private const RECEPTION_DATE = '2026-06-17';

    /** Fecha del segundo lote, en otro mes: cada lote debe fechar SU movimiento. */
    private const SECOND_RECEPTION_DATE = '2026-05-20';

    /**
     * Fecha del documento de salida. A propósito DISTINTA de la reception_date:
     * la decisión de negocio es fechar por la recepción del lote, no por el
     * `output_date`. Si alguien vuelve a usar `output_date`, estas pruebas fallan.
     */
    private const OUTPUT_DATE = '2026-06-01';

    /**
     * Catálogo mínimo: un admin, un producto con unidad base 'kg', una BODEGA
     * de origen, una FINCA de destino con un lote de cultivo, y los dos tipos de
     * salida que se comportan distinto (traslado genera entrada en destino,
     * consumo no).
     */
    private function createFixtures(): array
    {
        $admin = User::create([
            'name' => 'Admin Fechas',
            'email' => 'admin_fechas_' . uniqid() . '@agriflor.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $brand = Brand::create([
            'name' => 'Marca Fechas ' . uniqid(),
            'status' => 'active',
        ]);

        BaseUnit::firstOrCreate(
            ['symbol' => 'kg'],
            ['name' => 'Kilogramos', 'description' => 'Unidad de masa', 'status' => 'active']
        );

        $product = Product::create([
            'name' => 'Producto Fechas',
            'brand_id' => $brand->id,
            'active_ingredient' => 'Glifosato',
            'min_stock' => 0,
            'status' => 'active',
            'base_unit' => 'kg',
            'created_by' => $admin->id,
        ]);

        $bodega = Location::create([
            'name' => 'Bodega Origen Fechas',
            'type' => 'warehouse',
            'status' => 'active',
        ]);

        $finca = Location::create([
            'name' => 'Finca Destino Fechas',
            'type' => 'farm',
            'status' => 'active',
        ]);

        $farmLot = FarmLot::create([
            'location_id' => $finca->id,
            'name' => 'Lote Fechas',
            'status' => 'active',
        ]);

        $transferType = OutputType::firstOrCreate(
            ['code' => 'transfer'],
            ['name' => 'Traslado', 'requires_lots' => false, 'status' => 'active']
        );

        $consumptionType = OutputType::firstOrCreate(
            ['code' => 'consumption'],
            ['name' => 'Consumo', 'requires_lots' => true, 'status' => 'active']
        );

        return compact(
            'admin', 'brand', 'product', 'bodega', 'finca',
            'farmLot', 'transferType', 'consumptionType'
        );
    }

    /**
     * Existencia previa en el origen: el lote real en `inventory` (lo que consume
     * FIFO) más su movimiento de kardex, fechados ANTES del mes que se prueba.
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
            'movement_date' => '2026-04-10',
            'unit_price' => 10,
            'total_price' => $quantity * 10,
            'responsible_user' => $fixtures['admin']->id,
            'observations' => 'Ajuste inicial',
        ]);
    }

    /**
     * Una salida aprobada (traslado o consumo) con un producto, junto a la
     * recepción pendiente que la va a recibir.
     */
    private function makeOutputReception(array $fixtures, OutputType $type, float $quantity): array
    {
        $output = ProductOutput::create([
            'output_number' => ProductOutput::generateOutputNumber(),
            'output_type_id' => $type->id,
            'output_date' => self::OUTPUT_DATE,
            'origin_location_id' => $fixtures['bodega']->id,
            'destination_location_id' => $fixtures['finca']->id,
            'status' => 'approved',
            'total_cost' => $quantity * 10,
            'responsible_user' => $fixtures['admin']->id,
        ]);

        if ($type->code === 'consumption') {
            $output->farmLots()->attach($fixtures['farmLot']->id);
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
            'origin_location_id' => $fixtures['bodega']->id,
            'destination_location_id' => $fixtures['finca']->id,
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

    /** Recepción de una COMPRA: solo genera entrada en el destino. */
    private function makePurchaseReception(array $fixtures, float $quantity): array
    {
        $reception = Reception::create([
            'reception_number' => 'REC-TEST-' . strtoupper(substr(uniqid(), -8)),
            'source_id' => $fixtures['admin']->id, // sin Purchase: el precio cae al del producto
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

    /** POST del lote de recepción con una fecha explícita. */
    private function receiveBatch(
        array $fixtures,
        Reception $reception,
        string $receptionDate,
        float $quantity
    ): TestResponse {
        return $this->actingAs($fixtures['admin'], 'api')->postJson(
            "/api/receptions/{$reception->id}/batches",
            [
                'reception_id' => $reception->id,
                'reception_date' => $receptionDate,
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

    private function assertSingleMovementDate(
        Reception $reception,
        string $type,
        string $expectedDate,
        string $message
    ): InventoryMovement {
        $movements = $this->movementsOf($reception, $type);

        $this->assertCount(1, $movements, "Se esperaba un único movimiento '{$type}'.");
        $this->assertSame(
            $expectedDate,
            $movements->first()->movement_date->toDateString(),
            $message
        );

        return $movements->first();
    }

    // ------------------------------------------------------------------
    // 1. TRASLADO: las dos patas llevan la MISMA fecha (el bug de PR-1)
    // ------------------------------------------------------------------

    public function test_transfer_reception_dates_both_legs_with_the_batch_reception_date(): void
    {
        $fixtures = $this->createFixtures();
        $this->seedOpeningStock($fixtures, $fixtures['bodega']->id, 500);
        $context = $this->makeOutputReception($fixtures, $fixtures['transferType'], 140);

        $this->receiveBatch($fixtures, $context['reception'], self::RECEPTION_DATE, 140)
            ->assertStatus(201);

        $exit = $this->assertSingleMovementDate(
            $context['reception'],
            'exit',
            self::RECEPTION_DATE,
            'La salida en el origen debe llevar la fecha de recepción del lote.'
        );

        $entry = $this->assertSingleMovementDate(
            $context['reception'],
            'entry',
            self::RECEPTION_DATE,
            'La entrada en el destino debe llevar la MISMA fecha que la salida, ' .
            'no la fecha de registro (este es el bug que corrige PR-1).'
        );

        // El invariante de negocio, dicho de forma explícita.
        $this->assertSame(
            $exit->movement_date->toDateString(),
            $entry->movement_date->toDateString(),
            'Origen y destino nunca deben llevar fechas distintas.'
        );

        // La fecha NO sale del documento de salida.
        $this->assertNotSame(self::OUTPUT_DATE, $entry->movement_date->toDateString());

        $this->assertSame($fixtures['bodega']->id, $exit->location_id);
        $this->assertSame($fixtures['finca']->id, $entry->location_id);
    }

    // ------------------------------------------------------------------
    // 2. COMPRA: no regresión, la entrada conserva la fecha del lote
    // ------------------------------------------------------------------

    public function test_purchase_reception_dates_entry_with_the_batch_reception_date(): void
    {
        $fixtures = $this->createFixtures();
        $context = $this->makePurchaseReception($fixtures, 80);

        $this->receiveBatch($fixtures, $context['reception'], self::RECEPTION_DATE, 80)
            ->assertStatus(201);

        $this->assertSingleMovementDate(
            $context['reception'],
            'entry',
            self::RECEPTION_DATE,
            'La entrada de una compra debe conservar la fecha de recepción del lote.'
        );

        $this->assertCount(0, $this->movementsOf($context['reception'], 'exit'));
    }

    // ------------------------------------------------------------------
    // 3. CONSUMO: solo salida, y la Application hereda la misma fecha
    // ------------------------------------------------------------------

    public function test_consumption_reception_dates_exit_and_application_with_the_batch_date(): void
    {
        $fixtures = $this->createFixtures();
        $this->seedOpeningStock($fixtures, $fixtures['bodega']->id, 300);
        $context = $this->makeOutputReception($fixtures, $fixtures['consumptionType'], 25);

        $this->receiveBatch($fixtures, $context['reception'], self::RECEPTION_DATE, 25)
            ->assertStatus(201);

        $this->assertSingleMovementDate(
            $context['reception'],
            'exit',
            self::RECEPTION_DATE,
            'La salida por consumo debe llevar la fecha de recepción del lote.'
        );

        // Un consumo no entra a ninguna parte: se gasta.
        $this->assertCount(0, $this->movementsOf($context['reception'], 'entry'));

        $application = Application::where('product_output_id', $context['output']->id)->first();

        $this->assertNotNull($application, 'El consumo debe generar una Application.');
        $this->assertSame(
            self::RECEPTION_DATE,
            $application->application_date->toDateString(),
            'La aplicación debe fecharse el día en que se consumió, no el día en que se registró.'
        );
    }

    // ------------------------------------------------------------------
    // 4. VARIOS LOTES: cada movimiento lleva la fecha de SU lote
    // ------------------------------------------------------------------

    public function test_each_batch_dates_its_own_movements(): void
    {
        $fixtures = $this->createFixtures();
        $this->seedOpeningStock($fixtures, $fixtures['bodega']->id, 500);
        $context = $this->makeOutputReception($fixtures, $fixtures['transferType'], 100);

        // Lote 1 en mayo, lote 2 en junio: la recepción se completa en dos entregas.
        $this->receiveBatch($fixtures, $context['reception'], self::SECOND_RECEPTION_DATE, 40)
            ->assertStatus(201);
        $this->receiveBatch($fixtures, $context['reception'], self::RECEPTION_DATE, 60)
            ->assertStatus(201);

        foreach (['exit', 'entry'] as $type) {
            $byQuantity = $this->movementsOf($context['reception'], $type)
                ->keyBy(fn ($movement) => (string) intval($movement->quantity));

            $this->assertCount(2, $byQuantity, "Se esperaban dos movimientos '{$type}'.");
            $this->assertSame(
                self::SECOND_RECEPTION_DATE,
                $byQuantity['40']->movement_date->toDateString(),
                "El movimiento '{$type}' del primer lote debe llevar la fecha del primer lote."
            );
            $this->assertSame(
                self::RECEPTION_DATE,
                $byQuantity['60']->movement_date->toDateString(),
                "El movimiento '{$type}' del segundo lote debe llevar la fecha del segundo lote."
            );
        }
    }
}
