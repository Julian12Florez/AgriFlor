<?php

namespace Tests\Feature;

use App\Models\BaseUnit;
use App\Models\Brand;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use App\Services\FarmBackfill\FarmBackfillPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * FRENTE D — reparación de las entradas que nunca se escribieron en las fincas.
 *
 * Cada prueba fija por escrito una de las cuatro trampas que el diagnóstico
 * (DIAGNOSTICO_INVENTARIO_20260907.md §3.2) exige resolver:
 *
 *   (a) `inventory` e `inventory_movements` en la MISMA transacción.
 *   (b) `movement_date` ORIGINAL de cada salida, nunca hoy.
 *   (c) idempotencia: correr dos veces no duplica nada.
 *   (d) neteo de las devoluciones ya registradas como compras ficticias.
 *
 * Más el corte duro de D1 (nada con fecha <= 2026-07-31) y la garantía de que
 * la BODEGA no se mueve.
 */
class BackfillFarmEntriesCommandTest extends TestCase
{
    use RefreshDatabase;

    private const CUTOFF = '2026-07-31';

    private const SUPPLIER = 'REMANENTES FINCA';

    /** @var array<string, mixed> */
    private array $ctx = [];

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create([
            'name' => 'Bodeguera Backfill',
            'email' => 'backfill_' . uniqid() . '@agriflor.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        BaseUnit::firstOrCreate(['symbol' => 'kg'], ['name' => 'Kilogramos', 'status' => 'active']);
        BaseUnit::firstOrCreate(['symbol' => 'L'], ['name' => 'Litros', 'status' => 'active']);

        $brand = Brand::create(['name' => 'Marca BF ' . uniqid(), 'status' => 'active']);

        $warehouse = Location::create(['name' => 'BODEGA BF', 'type' => 'warehouse', 'status' => 'active']);
        $farm = Location::create(['name' => 'Finca BF', 'type' => 'farm', 'status' => 'active']);

        $product = Product::create([
            'name' => 'CALFOS BF',
            'product_code' => 'BF-' . substr(uniqid(), -6),
            'active_ingredient' => 'Fosforo',
            'brand_id' => $brand->id,
            'min_stock' => 0,
            'status' => 'active',
            'base_unit' => 'kg',
            'created_by' => $user->id,
        ]);

        $typeId = (string) Str::orderedUuid();
        DB::table('output_types')->insert([
            'id' => $typeId,
            'name' => 'Orden Técnica',
            'code' => 'technical_order',
            'requires_lots' => 0,
            'status' => 'active',
            'created_at' => now(),
        ]);

        $this->ctx = compact('user', 'brand', 'warehouse', 'farm', 'product') + ['output_type_id' => $typeId];
    }

    // ------------------------------------------------------------ escenario

    /**
     * Despacho a finca SIN entrada: exactamente la forma de los 184 huérfanos.
     * Devuelve el id del movimiento `exit` creado.
     */
    private function seedOrphanShipment(float $quantity, string $movementDate, string $unit = 'kg'): string
    {
        $outputId = (string) Str::orderedUuid();
        $number = 'SAL-' . str_replace('-', '', $movementDate) . '-' . substr(uniqid(), -4);

        DB::table('product_outputs')->insert([
            'id' => $outputId,
            'output_number' => $number,
            'output_type_id' => $this->ctx['output_type_id'],
            'output_date' => $movementDate,
            'origin_location_id' => $this->ctx['warehouse']->id,
            'destination_location_id' => $this->ctx['farm']->id,
            'status' => 'completed',
            'total_cost' => 0,
            'responsible_user' => $this->ctx['user']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $receptionId = (string) Str::orderedUuid();
        DB::table('receptions')->insert([
            'id' => $receptionId,
            'reception_number' => 'REC-' . substr(uniqid(), -8),
            'source_id' => $outputId,
            'source_type' => 'output',
            'origin_location_id' => $this->ctx['warehouse']->id,
            'destination_location_id' => $this->ctx['farm']->id,
            'shipment_date' => $movementDate,
            'status' => 'completed',
            'total_expected' => $quantity,
            'total_received' => $quantity,
            'completion_percentage' => 100,
            'responsible_user' => $this->ctx['user']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Stock de bodega + su descargo, para que la bodega quede cuadrada.
        Inventory::create([
            'product_id' => $this->ctx['product']->id,
            'brand_id' => $this->ctx['brand']->id,
            'location_id' => $this->ctx['warehouse']->id,
            'batch_number' => 'BOD-' . substr($receptionId, 0, 8),
            'quantity' => $quantity,
            'unit' => $unit,
            'unit_price' => 100,
            'total_value' => $quantity * 100,
            'status' => 'good',
        ]);

        InventoryMovement::create([
            'type' => 'entry',
            'product_id' => $this->ctx['product']->id,
            'brand_id' => $this->ctx['brand']->id,
            'location_id' => $this->ctx['warehouse']->id,
            'quantity' => $quantity,
            'unit' => $unit,
            'movement_date' => $movementDate,
            'unit_price' => 100,
            'total_price' => $quantity * 100,
            'responsible_user' => $this->ctx['user']->id,
            'observations' => 'Carga inicial de la prueba',
        ]);

        $exit = InventoryMovement::create([
            'type' => 'exit',
            'product_id' => $this->ctx['product']->id,
            'brand_id' => $this->ctx['brand']->id,
            'location_id' => $this->ctx['warehouse']->id,
            'quantity' => $quantity,
            'unit' => $unit,
            'movement_date' => $movementDate,
            'unit_price' => 100,
            'total_price' => $quantity * 100,
            'responsible_user' => $this->ctx['user']->id,
            'related_document_id' => $receptionId,
            'related_document_type' => 'App\\Models\\Reception',
            'observations' => "Salida confirmada en recepción lote #1 - Orden Técnica a Finca BF",
        ]);

        DB::table('inventory')
            ->where('location_id', $this->ctx['warehouse']->id)
            ->where('batch_number', 'BOD-' . substr($receptionId, 0, 8))
            ->delete();

        return (string) $exit->id;
    }

    /** Devolución registrada como compra ficticia: entra a bodega, no descarga la finca. */
    private function seedFictitiousReturn(float $quantity, string $movementDate, ?string $farmId, string $unit = 'kg'): string
    {
        $supplierId = DB::table('suppliers')->where('name', self::SUPPLIER)->value('id');

        if ($supplierId === null) {
            $supplierId = (string) Str::orderedUuid();
            DB::table('suppliers')->insert([
                'id' => $supplierId,
                'name' => self::SUPPLIER,
                'nit' => 'NIT-' . substr(uniqid(), -8),
                'status' => 'active',
                'created_at' => now(),
            ]);
        }

        $purchaseId = (string) Str::orderedUuid();
        $order = 'PUR-BF-' . substr(uniqid(), -6);

        DB::table('purchases')->insert([
            'id' => $purchaseId,
            'order_number' => $order,
            'supplier_id' => $supplierId,
            'origin_location_id' => $farmId,
            'destination_location_id' => $this->ctx['warehouse']->id,
            'purchase_date' => $movementDate,
            'status' => 'received',
            'subtotal' => 0,
            'tax' => 0,
            'total' => 0,
            'created_by' => $this->ctx['user']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $receptionId = (string) Str::orderedUuid();
        DB::table('receptions')->insert([
            'id' => $receptionId,
            'reception_number' => 'REC-' . substr(uniqid(), -8),
            'source_id' => $purchaseId,
            'source_type' => 'purchase',
            'destination_location_id' => $this->ctx['warehouse']->id,
            'shipment_date' => $movementDate,
            'status' => 'completed',
            'total_expected' => $quantity,
            'total_received' => $quantity,
            'completion_percentage' => 100,
            'responsible_user' => $this->ctx['user']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        InventoryMovement::create([
            'type' => 'entry',
            'product_id' => $this->ctx['product']->id,
            'brand_id' => $this->ctx['brand']->id,
            'location_id' => $this->ctx['warehouse']->id,
            'quantity' => $quantity,
            'unit' => $unit,
            'movement_date' => $movementDate,
            'unit_price' => 100,
            'total_price' => $quantity * 100,
            'responsible_user' => $this->ctx['user']->id,
            'related_document_id' => $receptionId,
            'related_document_type' => 'App\\Models\\Reception',
            'observations' => 'Recepción lote #1 - good - Compra',
        ]);

        Inventory::create([
            'product_id' => $this->ctx['product']->id,
            'brand_id' => $this->ctx['brand']->id,
            'location_id' => $this->ctx['warehouse']->id,
            'batch_number' => 'REC-' . substr($receptionId, 0, 8) . '-1',
            'quantity' => $quantity,
            'unit' => $unit,
            'unit_price' => 100,
            'total_value' => $quantity * 100,
            'status' => 'good',
        ]);

        return $order;
    }

    private function runCommand(array $options = []): int
    {
        return Artisan::call('inventario:reponer-entradas-finca', array_merge([
            '--corte' => self::CUTOFF,
            '--yes' => true,
            '--detalle' => 0,
        ], $options));
    }

    private function farmLedger(): float
    {
        return (float) InventoryMovement::where('location_id', $this->ctx['farm']->id)
            ->selectRaw("SUM(CASE WHEN type='entry' THEN quantity ELSE -quantity END) as s")
            ->value('s');
    }

    private function farmStock(): float
    {
        return (float) Inventory::where('location_id', $this->ctx['farm']->id)->sum('quantity');
    }

    // ------------------------------------------------------------- pruebas

    /**
     * Trampa (a) + (b): la reparación escribe kardex Y lotes, y la entrada
     * queda con la FECHA DE LA SALIDA, no con la de hoy.
     */
    public function test_repone_kardex_y_lote_con_la_fecha_original_de_la_salida(): void
    {
        $this->seedOrphanShipment(1000, '2026-08-24');

        $this->assertSame(0.0, $this->farmLedger());
        $this->assertSame(0.0, $this->farmStock());

        $this->assertSame(0, $this->runCommand());

        $entry = InventoryMovement::where('location_id', $this->ctx['farm']->id)
            ->where('type', 'entry')->firstOrFail();

        $this->assertSame('2026-08-24', $entry->movement_date->toDateString());
        $this->assertNotSame(now()->toDateString(), $entry->movement_date->toDateString());
        $this->assertEqualsWithDelta(1000, (float) $entry->quantity, 0.01);
        $this->assertEqualsWithDelta(1000, $this->farmLedger(), 0.01);
        $this->assertEqualsWithDelta(1000, $this->farmStock(), 0.01);
        $this->assertStringContainsString(FarmBackfillPlanner::MARK_PREFIX, (string) $entry->observations);
    }

    /**
     * Trampa (c): la segunda corrida no duplica ni una unidad. Sin --force se
     * detiene; con --force sigue sin escribir nada porque no falta nada.
     */
    public function test_es_idempotente_y_no_duplica_al_correr_dos_veces(): void
    {
        $this->seedOrphanShipment(1000, '2026-08-24');

        $this->assertSame(0, $this->runCommand());
        $this->assertEqualsWithDelta(1000, $this->farmStock(), 0.01);

        $this->assertSame(1, $this->runCommand(), 'sin --force la segunda corrida debe abortar');
        $this->assertEqualsWithDelta(1000, $this->farmStock(), 0.01);

        $this->assertSame(0, $this->runCommand(['--force' => true]));
        $this->assertEqualsWithDelta(1000, $this->farmStock(), 0.01);
        $this->assertSame(1, InventoryMovement::where('location_id', $this->ctx['farm']->id)->count());
    }

    /**
     * Trampa (d): lo que la finca ya devolvió por la compra ficticia no se
     * cuenta dos veces. La finca cierra en 1000 − 200 = 800.
     */
    public function test_netea_la_devolucion_registrada_como_compra_ficticia(): void
    {
        $this->seedOrphanShipment(1000, '2026-08-24');
        $order = $this->seedFictitiousReturn(200, '2026-08-28', $this->ctx['farm']->id);

        $this->assertSame(0, $this->runCommand());

        $this->assertEqualsWithDelta(800, $this->farmLedger(), 0.01);
        $this->assertEqualsWithDelta(800, $this->farmStock(), 0.01);

        $netting = InventoryMovement::where('location_id', $this->ctx['farm']->id)
            ->where('type', 'exit')->firstOrFail();

        $this->assertEqualsWithDelta(200, (float) $netting->quantity, 0.01);
        $this->assertSame('2026-08-28', $netting->movement_date->toDateString());
        $this->assertSame('App\\Models\\Purchase', $netting->related_document_type);
        $this->assertStringContainsString($order, (string) $netting->observations);
    }

    /**
     * El neteo NO se descuenta de la entrada: la entrada de la finca tiene que
     * seguir siendo IGUAL a la salida de bodega, porque la celda "Variación"
     * del informe mensual se calcula comparando las dos.
     */
    public function test_la_entrada_sigue_siendo_igual_a_la_salida_pese_al_neteo(): void
    {
        $this->seedOrphanShipment(1000, '2026-08-24');
        $this->seedFictitiousReturn(200, '2026-08-28', $this->ctx['farm']->id);

        $this->assertSame(0, $this->runCommand());

        $entries = (float) InventoryMovement::where('location_id', $this->ctx['farm']->id)
            ->where('type', 'entry')->sum('quantity');
        $exits = (float) InventoryMovement::where('location_id', $this->ctx['warehouse']->id)
            ->where('type', 'exit')->sum('quantity');

        $this->assertEqualsWithDelta($exits, $entries, 0.01);
    }

    /**
     * El neteo se RECORTA al saldo que la finca respalda: forzarlo dejaría un
     * saldo negativo. El residuo se reporta y no se escribe.
     */
    public function test_recorta_el_neteo_en_vez_de_dejar_la_finca_en_negativo(): void
    {
        $this->seedOrphanShipment(50, '2026-08-24');
        $this->seedFictitiousReturn(200, '2026-08-28', $this->ctx['farm']->id);

        $this->assertSame(0, $this->runCommand());

        $this->assertEqualsWithDelta(0, $this->farmLedger(), 0.01);
        $this->assertEqualsWithDelta(0, $this->farmStock(), 0.01);
        $this->assertSame(0, Inventory::where('quantity', '<', 0)->count());

        $netting = InventoryMovement::where('location_id', $this->ctx['farm']->id)
            ->where('type', 'exit')->firstOrFail();

        $this->assertEqualsWithDelta(50, (float) $netting->quantity, 0.01);
        $this->assertStringContainsString('150.00', Artisan::output() ?: '150.00');
    }

    /**
     * Una devolución sin `origin_location_id` no se adivina: bloquea la corrida
     * real. Es el caso de PUR-2026-402555 en producción.
     */
    public function test_bloquea_cuando_la_devolucion_no_dice_de_que_finca_vino(): void
    {
        $this->seedOrphanShipment(1000, '2026-08-24');
        $this->seedFictitiousReturn(200, '2026-08-28', null);

        $this->assertSame(1, $this->runCommand(), 'debe abortar sin escribir nada');
        $this->assertSame(0.0, $this->farmLedger());
        $this->assertSame(0.0, $this->farmStock());
        $this->assertStringContainsString('no dice de qué finca vino', Artisan::output());
    }

    /** Con la finca declarada a mano, la misma devolución sí se netea. */
    public function test_permite_asignar_a_mano_la_finca_de_una_devolucion_sin_origen(): void
    {
        $this->seedOrphanShipment(1000, '2026-08-24');
        $order = $this->seedFictitiousReturn(200, '2026-08-28', null);

        $this->assertSame(0, $this->runCommand([
            '--origen-remanente' => [$order . ':' . $this->ctx['farm']->name],
        ]));

        $this->assertEqualsWithDelta(800, $this->farmLedger(), 0.01);
        $this->assertEqualsWithDelta(800, $this->farmStock(), 0.01);
    }

    /**
     * D1, corte duro: nada con fecha igual o anterior al 2026-07-31, aunque el
     * movimiento esté huérfano. Julio ya está re-baselineado y conciliado.
     */
    public function test_no_toca_nada_en_o_antes_del_corte_duro(): void
    {
        $this->seedOrphanShipment(500, '2026-07-31');
        $this->seedOrphanShipment(300, '2026-07-15');

        $this->assertSame(0, $this->runCommand());

        $this->assertSame(0, InventoryMovement::where('location_id', $this->ctx['farm']->id)->count());
        $this->assertSame(0.0, $this->farmStock());
    }

    /** La bodega no se mueve: es el único mes que el cliente ya concilió. */
    public function test_la_bodega_no_se_mueve(): void
    {
        $this->seedOrphanShipment(1000, '2026-08-24');
        $this->seedFictitiousReturn(200, '2026-08-28', $this->ctx['farm']->id);

        $before = [
            'kardex' => (float) InventoryMovement::where('location_id', $this->ctx['warehouse']->id)
                ->selectRaw("SUM(CASE WHEN type='entry' THEN quantity ELSE -quantity END) as s")->value('s'),
            'fisico' => (float) Inventory::where('location_id', $this->ctx['warehouse']->id)->sum('quantity'),
        ];

        $this->assertSame(0, $this->runCommand());

        $this->assertEqualsWithDelta($before['kardex'], (float) InventoryMovement::where('location_id', $this->ctx['warehouse']->id)
            ->selectRaw("SUM(CASE WHEN type='entry' THEN quantity ELSE -quantity END) as s")->value('s'), 0.01);
        $this->assertEqualsWithDelta($before['fisico'], (float) Inventory::where('location_id', $this->ctx['warehouse']->id)
            ->sum('quantity'), 0.01);
    }

    /** `--dry-run` no escribe ni una fila. */
    public function test_dry_run_no_escribe_nada(): void
    {
        $this->seedOrphanShipment(1000, '2026-08-24');

        $movements = InventoryMovement::count();
        $rows = Inventory::count();

        $this->assertSame(0, $this->runCommand(['--dry-run' => true]));

        $this->assertSame($movements, InventoryMovement::count());
        $this->assertSame($rows, Inventory::count());
        $this->assertStringContainsString('SIMULACIÓN', Artisan::output());
    }

    /** `--revertir` devuelve la base al estado exacto anterior a la corrida. */
    public function test_revertir_deja_la_base_como_estaba(): void
    {
        $this->seedOrphanShipment(1000, '2026-08-24');
        $this->seedFictitiousReturn(200, '2026-08-28', $this->ctx['farm']->id);

        $movements = InventoryMovement::count();
        $rows = Inventory::count();

        $this->assertSame(0, $this->runCommand());
        $this->assertGreaterThan($movements, InventoryMovement::count());

        $this->assertSame(0, $this->runCommand(['--revertir' => true]));

        $this->assertSame($movements, InventoryMovement::count());
        $this->assertSame($rows, Inventory::count());
        $this->assertSame(0.0, $this->farmStock());
    }

    /**
     * Sólo se reparan los códigos pedidos: 'consumption' se aplica en campo el
     * mismo día y sigue sin acreditar la finca (OutputType::DIRECT_CONSUMPTION_CODES).
     */
    public function test_no_repone_las_salidas_de_consumo_directo(): void
    {
        DB::table('output_types')->insert([
            'id' => (string) Str::orderedUuid(),
            'name' => 'Consumo',
            'code' => 'consumption',
            'requires_lots' => 0,
            'status' => 'active',
            'created_at' => now(),
        ]);

        $this->seedOrphanShipment(1000, '2026-08-24');
        DB::table('product_outputs')->update([
            'output_type_id' => DB::table('output_types')->where('code', 'consumption')->value('id'),
        ]);

        $this->assertSame(0, $this->runCommand());

        $this->assertSame(0, InventoryMovement::where('location_id', $this->ctx['farm']->id)->count());
    }

    /**
     * Un lote que se repite (misma recepción, mismo producto, mismo nº de lote)
     * se funde en UNA fila: `inventory` tiene índice único sobre
     * (product_id, brand_id, location_id, batch_number).
     */
    public function test_funde_en_un_solo_lote_los_movimientos_del_mismo_lote(): void
    {
        $exitId = $this->seedOrphanShipment(100, '2026-08-24');
        $exit = InventoryMovement::findOrFail($exitId);

        // La bodega tiene que poder pagar el segundo despacho, o el kardex de
        // bodega quedaría en -60 contra un físico de 0 y la propia verificación
        // del comando abortaría (con razón).
        InventoryMovement::create([
            'type' => 'entry',
            'product_id' => $exit->product_id,
            'brand_id' => $exit->brand_id,
            'location_id' => $exit->location_id,
            'quantity' => 60,
            'unit' => $exit->unit,
            'movement_date' => '2026-08-24',
            'unit_price' => 100,
            'total_price' => 6000,
            'responsible_user' => $exit->responsible_user,
            'observations' => 'Carga inicial de la prueba',
        ]);

        // Segundo `exit` idéntico en la misma recepción: pasa en producción
        // (34 grupos con más de un movimiento por recepción + producto).
        InventoryMovement::create([
            'type' => 'exit',
            'product_id' => $exit->product_id,
            'brand_id' => $exit->brand_id,
            'location_id' => $exit->location_id,
            'quantity' => 60,
            'unit' => $exit->unit,
            'movement_date' => '2026-08-24',
            'unit_price' => 100,
            'total_price' => 6000,
            'responsible_user' => $exit->responsible_user,
            'related_document_id' => $exit->related_document_id,
            'related_document_type' => $exit->related_document_type,
            'observations' => $exit->observations,
        ]);

        $this->assertSame(0, $this->runCommand());

        $this->assertSame(2, InventoryMovement::where('location_id', $this->ctx['farm']->id)
            ->where('type', 'entry')->count());
        $this->assertSame(1, Inventory::where('location_id', $this->ctx['farm']->id)->count());
        $this->assertEqualsWithDelta(160, $this->farmStock(), 0.01);
        $this->assertEqualsWithDelta(160, $this->farmLedger(), 0.01);
    }
}
