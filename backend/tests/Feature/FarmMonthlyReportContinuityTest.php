<?php

namespace Tests\Feature;

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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El informe mensual por finca medía con DOS relojes dentro de la misma función:
 * el stock inicial y las entradas leían `inventory_movements.movement_date`, pero
 * las columnas de remanente y consumo leían `product_outputs.output_date`.
 *
 * Como la recepción es un documento distinto de la salida —en producción va de 1
 * a 7 días después—, un remanente emitido a fin de mes caía en un mes en una
 * columna y en otro mes en las demás. La cadena se rompía: el stock final de un
 * mes dejaba de ser el inicial del siguiente, que es justo la propiedad que el
 * informe dice defender.
 *
 * Caso medido en producción: Mansión / SULFATO DE MAGNESIO, SAL-20260728-0022,
 * `output_date` 24/06 y kardex 28/07. Junio cerraba en 28 contra 53 reales, y
 * julio mostraba 25 kg de stock fantasma contra 0,00 reales.
 */
class FarmMonthlyReportContinuityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // El corte contable es configuración de producción, no del código que se
        // prueba aquí. Tiene su propia prueba en ClosedPeriodReceptionLockTest.
        config(['inventory.closed_period_until' => '2000-01-01']);
    }

    /**
     * Un remanente emitido el último día del mes y recibido en bodega al mes
     * siguiente: el cruce exacto que rompía la continuidad.
     */
    public function test_el_final_de_un_mes_es_el_inicial_del_siguiente(): void
    {
        $f = $this->fixtures();

        // La finca abre abril con 100 kg.
        $this->seedFarmStock($f, 100, '2026-03-31');

        // Devuelve 30 kg: documento del 30/04, kardex del 04/05.
        $this->remanente($f, 30, documento: '2026-04-30', kardex: '2026-05-04');

        $abril = $this->row($f, 4, 2026);
        $mayo = $this->row($f, 5, 2026);

        $this->assertEqualsWithDelta(
            $abril['final_stock'],
            $mayo['initial_stock'],
            0.01,
            'El final de abril y el inicial de mayo tienen que ser el mismo número.'
        );

        // Y con un solo reloj, el remanente pertenece al mes de su KARDEX.
        $this->assertEqualsWithDelta(
            100,
            $abril['final_stock'],
            0.01,
            'En abril no salió nada del kardex de la finca: cierra con los 100 kg.'
        );
        $this->assertEqualsWithDelta(
            70,
            $mayo['final_stock'],
            0.01,
            'Mayo es el mes en que el kardex registra la devolución: 100 − 30.'
        );
    }

    // ------------------------------------------------------------------

    private function fixtures(): array
    {
        $admin = User::create([
            'name' => 'Admin Continuidad',
            'email' => 'admin_cont_' . uniqid() . '@agriflor.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $brand = Brand::create(['name' => 'Marca Cont ' . uniqid(), 'status' => 'active']);

        BaseUnit::firstOrCreate(
            ['symbol' => 'kg'],
            ['name' => 'Kilogramos', 'description' => 'Unidad de masa', 'status' => 'active']
        );

        $product = Product::create([
            'name' => 'Producto Continuidad',
            'brand_id' => $brand->id,
            'active_ingredient' => 'Glifosato',
            'min_stock' => 0,
            'status' => 'active',
            'base_unit' => 'kg',
            'created_by' => $admin->id,
        ]);

        $bodega = Location::create(['name' => 'Bodega Cont', 'type' => 'warehouse', 'status' => 'active']);
        $finca = Location::create(['name' => 'Finca Cont', 'type' => 'farm', 'status' => 'active']);

        $remanenteType = OutputType::firstOrCreate(
            ['code' => 'remanente'],
            ['name' => 'Remanente', 'requires_lots' => false, 'status' => 'active']
        );

        return compact('admin', 'brand', 'product', 'bodega', 'finca', 'remanenteType');
    }

    /** Existencia de apertura en la finca: lote real más su movimiento de kardex. */
    private function seedFarmStock(array $f, float $cantidad, string $fecha): void
    {
        Inventory::create([
            'product_id' => $f['product']->id,
            'brand_id' => $f['brand']->id,
            'location_id' => $f['finca']->id,
            'batch_number' => 'APERTURA-' . uniqid(),
            'quantity' => $cantidad,
            'unit' => 'kg',
            'unit_price' => 10,
            'total_value' => $cantidad * 10,
            'status' => 'good',
        ]);

        InventoryMovement::create([
            'product_id' => $f['product']->id,
            'brand_id' => $f['brand']->id,
            'location_id' => $f['finca']->id,
            'type' => 'entry',
            'quantity' => $cantidad,
            'unit' => 'kg',
            'unit_price' => 10,
            'total_price' => $cantidad * 10,
            'movement_date' => $fecha,
            'responsible_user' => $f['admin']->id,
            'observations' => 'Apertura',
        ]);
    }

    /**
     * Remanente finca → bodega con el documento en un mes y el kardex en otro.
     * Se escriben las dos patas a mano para poder separar las fechas, que es lo
     * que el escenario necesita probar.
     */
    private function remanente(array $f, float $cantidad, string $documento, string $kardex): void
    {
        $output = ProductOutput::create([
            'output_number' => ProductOutput::generateOutputNumber(),
            'output_type_id' => $f['remanenteType']->id,
            'output_date' => $documento,
            'origin_location_id' => $f['finca']->id,
            'destination_location_id' => $f['bodega']->id,
            'status' => 'completed',
            'total_cost' => $cantidad * 10,
            'responsible_user' => $f['admin']->id,
        ]);

        OutputProduct::create([
            'output_id' => $output->id,
            'product_id' => $f['product']->id,
            'brand_id' => $f['brand']->id,
            'quantity_requested' => $cantidad,
            'quantity_delivered' => $cantidad,
            'unit' => 'kg',
        ]);

        $reception = Reception::create([
            'reception_number' => 'REC-CONT-' . uniqid(),
            'source_id' => $output->id,
            'source_type' => 'output',
            'origin_location_id' => $f['finca']->id,
            'destination_location_id' => $f['bodega']->id,
            'shipment_date' => $documento,
            'status' => 'completed',
            'total_expected' => $cantidad,
            'total_received' => $cantidad,
            'completion_percentage' => 100,
            'responsible_user' => $f['admin']->id,
        ]);

        // Salida de la finca, fechada con el KARDEX.
        InventoryMovement::create([
            'product_id' => $f['product']->id,
            'brand_id' => $f['brand']->id,
            'location_id' => $f['finca']->id,
            'type' => 'exit',
            'quantity' => $cantidad,
            'unit' => 'kg',
            'unit_price' => 10,
            'total_price' => $cantidad * 10,
            'movement_date' => $kardex,
            'related_document_id' => $reception->id,
            'related_document_type' => 'App\Models\Reception',
            'responsible_user' => $f['admin']->id,
            'observations' => 'Remanente devuelto',
        ]);

        Inventory::where('product_id', $f['product']->id)
            ->where('location_id', $f['finca']->id)
            ->decrement('quantity', $cantidad);
    }

    /** Fila del informe por finca para el producto de los fixtures. */
    private function row(array $f, int $mes, int $anio): array
    {
        $response = $this->actingAs($f['admin'], 'api')->getJson(
            '/api/inventory/farm-monthly-report'
            . '?month=' . $mes
            . '&year=' . $anio
            . '&location_id=' . $f['finca']->id
        );

        $response->assertStatus(200);

        foreach ($response->json('data.products') ?? [] as $row) {
            if ($row['product_id'] === $f['product']->id) {
                return $row;
            }
        }

        $this->fail("El producto no aparece en el informe de {$mes}/{$anio}.");
    }
}
