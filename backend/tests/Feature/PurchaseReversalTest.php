<?php

namespace Tests\Feature;

use App\Models\BaseUnit;
use App\Models\Brand;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\OutputProduct;
use App\Models\OutputType;
use App\Models\PackagingUnit;
use App\Models\Product;
use App\Models\ProductOutput;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Reception;
use App\Models\ReceptionItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Eliminar una compra YA RECIBIDA desde la app, revirtiendo lo que metió al
 * inventario.
 *
 * El 30-sep-2026 el cliente pidió borrar PUR-2026-402555: un remanente de finca
 * registrado como compra ficticia a "REMANENTES FINCA". La app no podía: el
 * borrado de compras solo aceptaba el estado "ordered", que no existe desde la
 * migración de diciembre de 2025, así que no dejaba borrar NINGUNA compra. Hubo
 * que hacerlo con SQL a mano, y no era trivial: de los 12 productos, 3 ya se
 * habían despachado a fincas desde el lote de esa compra, así que no bastaba con
 * borrar filas: lo despachado había que descontarlo de otros lotes de la bodega.
 *
 * Esta prueba fija lo que el botón tiene que hacer — lo mismo que se hizo a mano
 * — y lo que NO puede permitir: dejar el kardex negativo en cualquier fecha,
 * tocar un mes cerrado, o dejar sin cubrir stock en tránsito.
 */
class PurchaseReversalTest extends TestCase
{
    use RefreshDatabase;

    private array $f;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inventory.closed_period_until' => '2026-07-31']);
        $this->f = $this->fixtures();
    }

    // ------------------------------------------------------------------
    // 1. Lo que tiene que hacer
    // ------------------------------------------------------------------

    /** El caso simple: el lote de la compra está intacto. Desaparece todo. */
    public function test_revierte_una_compra_recibida_con_el_lote_intacto(): void
    {
        [$compra, $recepcion] = $this->compraRecibida(40, '2026-09-07');

        $this->assertEqualsWithDelta(40, $this->fisico(), 0.01);

        $this->revertir($compra)->assertOk()->assertJsonPath('success', true);

        $this->assertNull(Purchase::find($compra->id));
        $this->assertNull(Reception::find($recepcion->id));
        $this->assertSame(0, $this->movimientosDe($recepcion));
        $this->assertEqualsWithDelta(0, $this->fisico(), 0.01);
        $this->assertEqualsWithDelta(0, $this->kardex(), 0.01);
    }

    /**
     * EL CASO DE PRODUCCIÓN: parte del lote de la compra ya salió a una finca. Lo
     * que salió se descuenta de otro lote de la bodega; kardex y físico quedan
     * iguales.
     */
    public function test_lo_que_ya_salio_se_descuenta_de_otro_lote(): void
    {
        $this->stockPrevio(100, '2026-09-01');
        [$compra, $recepcion] = $this->compraRecibida(40, '2026-09-07');
        $this->despacharDelLoteDeLaCompra($recepcion, 15, '2026-09-10');

        // Antes: 100 previos + 40 de la compra − 15 despachados = 125.
        $this->assertEqualsWithDelta(125, $this->fisico(), 0.01);
        $this->assertEqualsWithDelta(125, $this->kardex(), 0.01);

        $this->revertir($compra)->assertOk();

        // Después: sin la compra, lo despachado sale del lote previo: 100 − 15.
        $this->assertEqualsWithDelta(85, $this->fisico(), 0.01);
        $this->assertEqualsWithDelta(85, $this->kardex(), 0.01);
        $this->assertSame(0, Inventory::where('batch_number', 'like', 'REC-' . substr($recepcion->id, 0, 8) . '-%')->count());
        $this->assertEqualsWithDelta(85, (float) Inventory::where('batch_number', 'BASE-PREVIO')->value('quantity'), 0.01);
    }

    /** Una compra pendiente, sin recepción, también se puede eliminar. */
    public function test_una_compra_pendiente_sin_recepcion_se_elimina(): void
    {
        $compra = $this->compra('pending');

        $this->revertir($compra)->assertOk();

        $this->assertNull(Purchase::find($compra->id));
    }

    /** La vista previa explica cada línea y no cambia NADA. */
    public function test_la_vista_previa_explica_cada_linea_y_no_escribe(): void
    {
        $this->stockPrevio(100, '2026-09-01');
        [$compra, $recepcion] = $this->compraRecibida(40, '2026-09-07');
        $this->despacharDelLoteDeLaCompra($recepcion, 15, '2026-09-10');

        $plan = $this->actingAs($this->f['admin'], 'api')
            ->getJson("/api/purchases/{$compra->id}/reversal-preview")
            ->assertOk()
            ->json('data');

        $this->assertTrue($plan['can_reverse']);
        $this->assertSame([], $plan['blockers']);
        $this->assertCount(1, $plan['lines']);
        $this->assertEqualsWithDelta(40, $plan['lines'][0]['entered'], 0.01);
        $this->assertEqualsWithDelta(25, $plan['lines'][0]['still_in_lot'], 0.01);
        $this->assertEqualsWithDelta(15, $plan['lines'][0]['already_dispatched'], 0.01);

        // Nada cambió.
        $this->assertNotNull(Purchase::find($compra->id));
        $this->assertEqualsWithDelta(125, $this->fisico(), 0.01);
    }

    /** El motivo queda en la auditoría: el borrado no puede ser anónimo. */
    public function test_el_motivo_queda_en_la_auditoria(): void
    {
        // config/audit.php no audita desde consola, y las pruebas corren en
        // consola. En producción la petición es HTTP y sí se audita.
        config(['audit.console' => true]);

        [$compra] = $this->compraRecibida(40, '2026-09-07');

        $this->revertir($compra, 'Remanente registrado como compra; se rehace como ajuste')->assertOk();

        // El proyecto usa un morph map: el tipo se guarda como 'purchase', no
        // como el nombre de la clase.
        $auditoria = \DB::table('audits')
            ->where('auditable_type', (new Purchase())->getMorphClass())
            ->where('auditable_id', $compra->id)
            ->get();

        $this->assertTrue(
            $auditoria->contains('event', 'deleted'),
            'El borrado tiene que dejar su evento en la auditoría.'
        );
        $this->assertTrue(
            $auditoria->where('event', 'updated')
                ->contains(fn ($a) => str_contains((string) $a->new_values, 'se rehace como ajuste')),
            'El motivo tiene que quedar escrito en la traza de la compra.'
        );
    }

    // ------------------------------------------------------------------
    // 2. Lo que NO puede permitir
    // ------------------------------------------------------------------

    /**
     * Si lo que ya salió no se puede cubrir con otros lotes, revertir dejaría el
     * kardex en negativo desde la fecha de la compra. Se bloquea sin tocar nada.
     */
    public function test_bloquea_si_el_kardex_quedaria_negativo(): void
    {
        [$compra, $recepcion] = $this->compraRecibida(40, '2026-09-07');
        $this->despacharDelLoteDeLaCompra($recepcion, 15, '2026-09-10');

        $this->revertir($compra)
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertNotNull(Purchase::find($compra->id), 'No se puede haber borrado nada.');
        $this->assertEqualsWithDelta(25, $this->fisico(), 0.01);
        $this->assertEqualsWithDelta(25, $this->kardex(), 0.01);
    }

    /**
     * El stock de hoy alcanza, pero el de la fecha no: la compra entró el 7 y el
     * lote previo llegó el 20. Entre el 10 y el 20 el kardex quedaría en −15.
     */
    public function test_bloquea_si_el_kardex_quedaria_negativo_en_una_fecha_pasada(): void
    {
        [$compra, $recepcion] = $this->compraRecibida(40, '2026-09-07');
        $this->despacharDelLoteDeLaCompra($recepcion, 15, '2026-09-10');
        $this->stockPrevio(100, '2026-09-20');

        $this->revertir($compra)->assertStatus(422);

        $this->assertNotNull(Purchase::find($compra->id));
    }

    /** Un mes cerrado no se toca. */
    public function test_bloquea_si_la_compra_entro_en_un_mes_cerrado(): void
    {
        config(['inventory.closed_period_until' => '2026-06-30']);
        [$compra] = $this->compraRecibida(40, '2026-07-15');
        config(['inventory.closed_period_until' => '2026-07-31']);

        $this->revertir($compra)->assertStatus(422);

        $this->assertNotNull(Purchase::find($compra->id));
    }

    /** Si un lote de la compra va en una salida que aún no llega, no se toca. */
    public function test_bloquea_si_un_lote_de_la_compra_va_en_una_salida_en_transito(): void
    {
        [$compra, $recepcion] = $this->compraRecibida(40, '2026-09-07');

        $salida = ProductOutput::create([
            'output_number' => ProductOutput::generateOutputNumber(),
            'output_type_id' => $this->f['traslado']->id,
            'output_date' => '2026-09-10',
            'origin_location_id' => $this->f['bodega']->id,
            'destination_location_id' => $this->f['finca']->id,
            'status' => 'in_transit',
            'total_cost' => 0,
            'responsible_user' => $this->f['admin']->id,
        ]);
        OutputProduct::create([
            'output_id' => $salida->id,
            'product_id' => $this->f['product']->id,
            'brand_id' => $this->f['brand']->id,
            'quantity_requested' => 10,
            'quantity_delivered' => 10,
            'unit' => 'kg',
            'batch_number' => 'REC-' . substr($recepcion->id, 0, 8) . '-1',
        ]);

        $this->revertir($compra)->assertStatus(422);

        $this->assertNotNull(Purchase::find($compra->id));
    }

    /** Es destructivo: solo el administrador. */
    public function test_solo_el_administrador_puede(): void
    {
        [$compra] = $this->compraRecibida(40, '2026-09-07');

        $this->actingAs($this->f['bodeguero'], 'api')
            ->postJson("/api/purchases/{$compra->id}/reverse", ['motivo' => 'Prueba de permisos del rol'])
            ->assertStatus(403);

        $this->assertNotNull(Purchase::find($compra->id));
    }

    /** Sin motivo no se borra. */
    public function test_exige_un_motivo(): void
    {
        [$compra] = $this->compraRecibida(40, '2026-09-07');

        $this->actingAs($this->f['admin'], 'api')
            ->postJson("/api/purchases/{$compra->id}/reverse", [])
            ->assertStatus(422);

        $this->assertNotNull(Purchase::find($compra->id));
    }

    // ------------------------------------------------------------------

    private function revertir(Purchase $compra, string $motivo = 'Compra ficticia: era un remanente de finca')
    {
        return $this->actingAs($this->f['admin'], 'api')
            ->postJson("/api/purchases/{$compra->id}/reverse", ['motivo' => $motivo]);
    }

    private function compra(string $estado, float $cantidad = 40): Purchase
    {
        $compra = Purchase::create([
            'order_number' => 'PUR-TEST-' . strtoupper(substr(uniqid(), -6)),
            'supplier_id' => $this->f['supplier']->id,
            'destination_location_id' => $this->f['bodega']->id,
            'purchase_date' => '2026-09-04',
            'status' => $estado,
            'subtotal' => $cantidad * 10,
            'tax' => 0,
            'total' => $cantidad * 10,
            'created_by' => $this->f['admin']->id,
        ]);

        PurchaseItem::create([
            'purchase_id' => $compra->id,
            'product_id' => $this->f['product']->id,
            'brand_id' => $this->f['brand']->id,
            'packaging_unit_id' => $this->f['empaque']->id,
            'quantity' => $cantidad,
            'quantity_in_base_units' => $cantidad,
            'unit_price' => 10,
            'subtotal' => $cantidad * 10,
            'total' => $cantidad * 10,
        ]);

        return $compra;
    }

    /**
     * Una compra recibida por el flujo REAL de recepción: el mismo endpoint que
     * usa la bodega escribe la entrada del kardex y el lote REC-xxxxxxxx-1.
     *
     * @return array{0: Purchase, 1: Reception}
     */
    private function compraRecibida(float $cantidad, string $fecha): array
    {
        $compra = $this->compra('pending', $cantidad);

        $recepcion = Reception::create([
            'reception_number' => Reception::generateReceptionNumber(),
            'source_id' => $compra->id,
            'source_type' => 'purchase',
            'origin_location_id' => null,
            'destination_location_id' => $this->f['bodega']->id,
            'status' => 'pending',
            'total_expected' => $cantidad,
            'total_received' => 0,
            'completion_percentage' => 0,
            'responsible_user' => $this->f['admin']->id,
        ]);

        ReceptionItem::create([
            'reception_id' => $recepcion->id,
            'product_id' => $this->f['product']->id,
            'brand_id' => $this->f['brand']->id,
            'quantity_expected' => $cantidad,
            'quantity_received' => 0,
            'quantity_pending' => $cantidad,
            'unit' => 'kg',
        ]);

        $this->actingAs($this->f['admin'], 'api')->postJson("/api/receptions/{$recepcion->id}/batches", [
            'reception_id' => $recepcion->id,
            'reception_date' => $fecha,
            'received_by' => $this->f['admin']->id,
            'items' => [[
                'product_id' => $this->f['product']->id,
                'brand_id' => $this->f['brand']->id,
                'quantity_received' => $cantidad,
                'condition' => 'good',
            ]],
        ])->assertStatus(201);

        $compra->update(['status' => 'received']);

        return [$compra->fresh(), $recepcion->fresh()];
    }

    /** Stock anterior a la compra, en un lote propio. */
    private function stockPrevio(float $cantidad, string $fecha): void
    {
        InventoryMovement::create([
            'type' => 'entry',
            'product_id' => $this->f['product']->id,
            'brand_id' => $this->f['brand']->id,
            'location_id' => $this->f['bodega']->id,
            'quantity' => $cantidad,
            'unit' => 'kg',
            'movement_date' => $fecha,
            'unit_price' => 10,
            'total_price' => $cantidad * 10,
            'responsible_user' => $this->f['admin']->id,
            'observations' => 'Stock previo (prueba)',
        ]);

        Inventory::create([
            'product_id' => $this->f['product']->id,
            'brand_id' => $this->f['brand']->id,
            'location_id' => $this->f['bodega']->id,
            'batch_number' => 'BASE-PREVIO',
            'quantity' => $cantidad,
            'unit' => 'kg',
            'expiration_date' => '2027-12-31',
            'unit_price' => 10,
            'total_value' => $cantidad * 10,
            'status' => 'good',
        ]);
    }

    /** Lo mismo que hace una salida confirmada: kardex de salida + FIFO del lote. */
    private function despacharDelLoteDeLaCompra(Reception $recepcion, float $cantidad, string $fecha): void
    {
        InventoryMovement::create([
            'type' => 'exit',
            'product_id' => $this->f['product']->id,
            'brand_id' => $this->f['brand']->id,
            'location_id' => $this->f['bodega']->id,
            'quantity' => $cantidad,
            'unit' => 'kg',
            'movement_date' => $fecha,
            'unit_price' => 10,
            'total_price' => $cantidad * 10,
            'responsible_user' => $this->f['admin']->id,
            'observations' => 'Salida a finca (prueba)',
        ]);

        app(InventoryService::class)->reduceInventoryFIFO(
            $this->f['product']->id,
            $this->f['brand']->id,
            $this->f['bodega']->id,
            $cantidad,
            'kg',
            'REC-' . substr($recepcion->id, 0, 8) . '-1',
        );
    }

    private function fisico(): float
    {
        return (float) Inventory::where('product_id', $this->f['product']->id)
            ->where('location_id', $this->f['bodega']->id)
            ->sum('quantity');
    }

    private function kardex(): float
    {
        return (float) InventoryMovement::where('product_id', $this->f['product']->id)
            ->where('location_id', $this->f['bodega']->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'entry' THEN quantity ELSE -quantity END), 0) AS saldo")
            ->value('saldo');
    }

    private function movimientosDe(Reception $recepcion): int
    {
        return InventoryMovement::where('related_document_id', $recepcion->id)->count();
    }

    private function fixtures(): array
    {
        $admin = User::create([
            'name' => 'Admin Reversa',
            'email' => 'admin_rev_' . uniqid() . '@agriflor.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);
        $bodeguero = User::create([
            'name' => 'Bodeguero Reversa',
            'email' => 'bod_rev_' . uniqid() . '@agriflor.com',
            'password' => bcrypt('password'),
            'role' => 'warehouse',
            'status' => 'active',
        ]);

        BaseUnit::firstOrCreate(
            ['symbol' => 'kg'],
            ['name' => 'Kilogramos', 'description' => 'Unidad de masa', 'status' => 'active']
        );

        $brand = Brand::create(['name' => 'Marca Reversa ' . uniqid(), 'status' => 'active']);

        return [
            'admin' => $admin,
            'bodeguero' => $bodeguero,
            'brand' => $brand,
            'product' => Product::create([
                'name' => 'Producto Reversa',
                'brand_id' => $brand->id,
                'active_ingredient' => 'Magnesio',
                'min_stock' => 0,
                'status' => 'active',
                'base_unit' => 'kg',
                'created_by' => $admin->id,
            ]),
            'empaque' => PackagingUnit::create(['name' => 'Kilogramo', 'base_quantity' => 1, 'base_unit' => 'kg']),
            'supplier' => Supplier::create(['name' => 'Proveedor Reversa', 'nit' => '900' . random_int(100000, 999999), 'status' => 'active']),
            'bodega' => Location::create(['name' => 'Bodega Reversa', 'type' => 'warehouse', 'status' => 'active']),
            'finca' => Location::create(['name' => 'Finca Reversa', 'type' => 'farm', 'status' => 'active']),
            'traslado' => OutputType::firstOrCreate(
                ['code' => 'transfer'],
                ['name' => 'Traslado', 'requires_lots' => false, 'status' => 'active']
            ),
        ];
    }
}
