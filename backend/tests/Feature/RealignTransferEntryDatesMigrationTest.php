<?php

namespace Tests\Feature;

use App\Models\BaseUnit;
use App\Models\Brand;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\Product;
use App\Models\Reception;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Migración correctiva 2026_07_30_120000_realign_transfer_entry_movement_dates.
 *
 * Lo que hay que demostrar no es solo que corrige, sino sobre todo que NO toca
 * lo que no debe: mayo de 2026 ya se conció contra contabilidad (Siigo) y un
 * criterio de selección demasiado amplio destruiría ese cierre. Por eso se
 * siembran movimientos de mayo que un criterio ingenuo agarraría y que deben
 * quedar intactos.
 *
 * NOTA SOBRE EL AISLAMIENTO: en MySQL cualquier DDL fuerza un COMMIT implícito
 * que rompe la transacción de `RefreshDatabase` —lo sembrado queda CONFIRMADO en
 * `agriflor_test` y Laravel se ve obligado a re-migrar la base entera antes de la
 * prueba siguiente (medido: ~17 s por DDL)—. Por eso la migración crea su tabla
 * de respaldo detrás de un `Schema::hasTable()`: cuando ya existe (el caso normal
 * en pruebas, porque `migrate:fresh` la creó) `up()` no emite DDL alguno y la
 * transacción sobrevive. `tearDown()` limpia igualmente el respaldo y lo sembrado,
 * como defensa en profundidad para la ruta en que la tabla sí haya que crearla.
 */
class RealignTransferEntryDatesMigrationTest extends TestCase
{
    use RefreshDatabase;

    /** Fecha correcta del traslado de julio: la que lleva el `exit`. */
    private const JULY_CORRECT_DATE = '2026-07-14';

    /** Fecha errónea con la que el bug dejó la `entry` (= su fecha de registro). */
    private const JULY_WRONG_DATE = '2026-07-28';

    /** Traslado de mayo: mes cerrado, intocable. */
    private const MAY_EXIT_DATE = '2026-05-10';
    private const MAY_ENTRY_DATE = '2026-05-20';

    private const BACKUP_TABLE = 'inventory_movements_md_backup';

    private const MIGRATION = 'migrations/2026_07_30_120000_realign_transfer_entry_movement_dates.php';

    /** Ids sembrados por la prueba, para poder limpiarlos tras el commit implícito. */
    private array $seeded = [
        'inventory_movements' => [],
        'receptions' => [],
        'products' => [],
        'brands' => [],
        'locations' => [],
        'users' => [],
    ];

    protected function tearDown(): void
    {
        // DELETE y no DROP TABLE: el DROP es DDL y volvería a romper la transacción
        // que este arreglo justamente preserva.
        if (Schema::hasTable(self::BACKUP_TABLE)) {
            DB::table(self::BACKUP_TABLE)->delete();
        }

        // El orden respeta las claves foráneas: primero lo que apunta, luego lo apuntado.
        foreach (['inventory_movements', 'receptions', 'products', 'brands', 'locations', 'users'] as $table) {
            if ($this->seeded[$table] !== []) {
                DB::table($table)->whereIn('id', $this->seeded[$table])->delete();
            }
        }

        parent::tearDown();
    }

    private function remember(string $table, string $id): string
    {
        $this->seeded[$table][] = $id;

        return $id;
    }

    private function migration(): object
    {
        return require database_path(self::MIGRATION);
    }

    private function runMigration(): void
    {
        $this->migration()->up();
    }

    private function createFixtures(): array
    {
        $admin = User::create([
            'name' => 'Admin Migracion',
            'email' => 'admin_mig_' . uniqid() . '@agriflor.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);
        $this->remember('users', $admin->id);

        $brand = Brand::create(['name' => 'Marca Mig ' . uniqid(), 'status' => 'active']);
        $this->remember('brands', $brand->id);

        BaseUnit::firstOrCreate(
            ['symbol' => 'kg'],
            ['name' => 'Kilogramos', 'description' => 'Unidad de masa', 'status' => 'active']
        );

        $product = Product::create([
            'name' => 'Producto Migracion',
            'brand_id' => $brand->id,
            'active_ingredient' => 'Glifosato',
            'min_stock' => 0,
            'status' => 'active',
            'base_unit' => 'kg',
            'created_by' => $admin->id,
        ]);
        $this->remember('products', $product->id);

        $bodega = Location::create(['name' => 'Bodega Mig', 'type' => 'warehouse', 'status' => 'active']);
        $finca = Location::create(['name' => 'Finca Mig', 'type' => 'farm', 'status' => 'active']);
        $this->remember('locations', $bodega->id);
        $this->remember('locations', $finca->id);

        return compact('admin', 'brand', 'product', 'bodega', 'finca');
    }

    private function makeReception(array $fixtures, float $quantity): Reception
    {
        $reception = Reception::create([
            'reception_number' => 'REC-MIG-' . strtoupper(substr(uniqid(), -8)),
            'source_id' => $fixtures['admin']->id,
            'source_type' => 'output',
            'origin_location_id' => $fixtures['bodega']->id,
            'destination_location_id' => $fixtures['finca']->id,
            'status' => 'completed',
            'total_expected' => $quantity,
            'total_received' => $quantity,
            'completion_percentage' => 100,
            'responsible_user' => $fixtures['admin']->id,
        ]);
        $this->remember('receptions', $reception->id);

        return $reception;
    }

    /**
     * Par exit/entry de UN lote de una recepción de salida.
     * `$entryCreatedAt` define si la entrada lleva la firma del bug: la huella es
     * `movement_date = DATE(created_at)`.
     */
    private function seedTransferBatch(
        array $fixtures,
        Reception $reception,
        int $batchNumber,
        string $exitDate,
        string $entryDate,
        string $entryCreatedAt,
        float $quantity
    ): array {
        $exit = $this->seedMovement($fixtures, $reception, 'exit', $batchNumber, $exitDate, $exitDate, $quantity);
        $entry = $this->seedMovement($fixtures, $reception, 'entry', $batchNumber, $entryDate, $entryCreatedAt, $quantity);

        return compact('exit', 'entry');
    }

    /** Recepción de un solo lote (el caso corriente). */
    private function seedTransferPair(
        array $fixtures,
        string $exitDate,
        string $entryDate,
        string $entryCreatedAt,
        float $quantity
    ): array {
        $reception = $this->makeReception($fixtures, $quantity);

        return $this->seedTransferBatch(
            $fixtures, $reception, 1, $exitDate, $entryDate, $entryCreatedAt, $quantity
        ) + ['reception' => $reception];
    }

    /**
     * Las observaciones se copian LITERALMENTE del formato que escribe
     * ReceptionController, incluidos los acentos: la migración desambigua el lote
     * leyendo el `#N` de este texto, así que el formato es parte del contrato.
     */
    private function observationsFor(string $type, int $batchNumber): string
    {
        return $type === 'entry'
            ? "Recepción lote #{$batchNumber} - good - Transferencia"
            : "Salida confirmada en recepción lote #{$batchNumber} - Traslado a Finca Mig";
    }

    private function seedMovement(
        array $fixtures,
        Reception $reception,
        string $type,
        int $batchNumber,
        string $movementDate,
        string $createdAt,
        float $quantity
    ): InventoryMovement {
        $movement = InventoryMovement::create([
            'type' => $type,
            'product_id' => $fixtures['product']->id,
            'brand_id' => $fixtures['brand']->id,
            'location_id' => $type === 'exit' ? $fixtures['bodega']->id : $fixtures['finca']->id,
            'quantity' => $quantity,
            'unit' => 'kg',
            'movement_date' => $movementDate,
            'unit_price' => 10,
            'total_price' => $quantity * 10,
            'responsible_user' => $fixtures['admin']->id,
            'related_document_id' => $reception->id,
            'related_document_type' => 'App\Models\Reception',
            'observations' => $this->observationsFor($type, $batchNumber),
        ]);
        $this->remember('inventory_movements', $movement->id);

        // `inventory_movements` no tiene updated_at; created_at se fija a mano
        // porque la firma del bug se detecta comparándolo con movement_date.
        DB::table('inventory_movements')
            ->where('id', $movement->id)
            ->update(['created_at' => $createdAt . ' 09:00:00']);

        return $movement->fresh();
    }

    private function movementDateOf(string $movementId): string
    {
        return DB::table('inventory_movements')->where('id', $movementId)->value('movement_date');
    }

    private function backupRowFor(string $movementId): ?object
    {
        return DB::table(self::BACKUP_TABLE)->where('movement_id', $movementId)->first();
    }

    // ------------------------------------------------------------------
    // 1. Corrige julio, protege mayo, respalda y es idempotente
    // ------------------------------------------------------------------

    public function test_migration_realigns_july_entry_protects_may_and_is_idempotent(): void
    {
        $fixtures = $this->createFixtures();

        // (1) Traslado de JULIO con la firma del bug: la entrada quedó fechada
        //     con su fecha de registro y difiere de su exit hermano.
        $july = $this->seedTransferPair(
            $fixtures, self::JULY_CORRECT_DATE, self::JULY_WRONG_DATE, self::JULY_WRONG_DATE, 140
        );

        // (2) Traslado de MAYO: exit y entry con fechas distintas, pero la
        //     entrada NO tiene la huella de now() (created_at es de junio,
        //     cuando se cargó el histórico). Un criterio ingenuo la agarraría.
        $may = $this->seedTransferPair(
            $fixtures, self::MAY_EXIT_DATE, self::MAY_ENTRY_DATE, '2026-06-05', 80
        );

        // (3) Traslado de MAYO con la firma completa del bug: aun así es
        //     intocable, porque mayo está cerrado contra contabilidad.
        $mayWithSignature = $this->seedTransferPair(
            $fixtures, '2026-05-05', '2026-05-25', '2026-05-25', 60
        );

        $this->runMigration();

        $this->assertSame(
            self::JULY_CORRECT_DATE,
            $this->movementDateOf($july['entry']->id),
            'La entrada con la firma del bug debe quedar con la fecha de su exit hermano.'
        );

        // El exit nunca se toca: era el que ya tenía la fecha correcta.
        $this->assertSame(
            self::JULY_CORRECT_DATE,
            $this->movementDateOf($july['exit']->id),
            'La migración solo reescribe entradas.'
        );

        // Mayo intacto en los dos escenarios.
        $this->assertSame(
            self::MAY_ENTRY_DATE,
            $this->movementDateOf($may['entry']->id),
            'Mayo está conciliado con contabilidad: no se toca.'
        );
        $this->assertSame(
            '2026-05-25',
            $this->movementDateOf($mayWithSignature['entry']->id),
            'Ni siquiera con la firma del bug se reescribe un movimiento de mayo.'
        );

        // El respaldo tiene exactamente la fila corregida, con los dos valores.
        $backup = DB::table(self::BACKUP_TABLE)->get();

        $this->assertCount(1, $backup, 'Solo la fila corregida debe respaldarse.');
        $this->assertSame($july['entry']->id, $backup->first()->movement_id);
        $this->assertSame(self::JULY_WRONG_DATE, $backup->first()->original_movement_date);
        $this->assertSame(self::JULY_CORRECT_DATE, $backup->first()->corrected_movement_date);
        $this->assertSame('E3E4_entry_transfer', $backup->first()->reason);

        // Idempotencia: re-ejecutar no cambia ni las fechas ni el respaldo.
        $this->runMigration();

        $this->assertSame(self::JULY_CORRECT_DATE, $this->movementDateOf($july['entry']->id));
        $this->assertSame(self::MAY_ENTRY_DATE, $this->movementDateOf($may['entry']->id));
        $this->assertCount(1, DB::table(self::BACKUP_TABLE)->get());
        $this->assertSame(
            self::JULY_WRONG_DATE,
            $this->backupRowFor($july['entry']->id)->original_movement_date,
            'El respaldo debe conservar el valor ORIGINAL, no el ya corregido.'
        );
    }

    // ------------------------------------------------------------------
    // 2. Dos lotes con la MISMA cantidad: el emparejamiento debe ser por lote
    // ------------------------------------------------------------------

    /**
     * El escenario que rompe un emparejamiento por (recepción, producto, marca,
     * cantidad, unidad): una recepción parcial del mismo producto en dos lotes
     * con la MISMA cantidad y fechas de salida distintas. Sin desambiguar por
     * número de lote, cada entrada tiene DOS exits candidatos y MySQL elige uno
     * arbitrario en el `UPDATE ... JOIN`: la entrada puede quedar con la fecha
     * del otro lote (sigue asimétrica) y el respaldo puede registrar un
     * `corrected_movement_date` distinto al que realmente se escribió, o sea un
     * rastro de auditoría falso.
     */
    public function test_pairs_by_batch_number_when_two_batches_share_the_quantity(): void
    {
        $fixtures = $this->createFixtures();
        $reception = $this->makeReception($fixtures, 80);

        // Mismo producto, marca, unidad y CANTIDAD (40) en los dos lotes.
        $batch1 = $this->seedTransferBatch(
            $fixtures, $reception, 1, '2026-06-05', self::JULY_WRONG_DATE, self::JULY_WRONG_DATE, 40
        );
        $batch2 = $this->seedTransferBatch(
            $fixtures, $reception, 2, '2026-07-05', self::JULY_WRONG_DATE, self::JULY_WRONG_DATE, 40
        );

        $this->runMigration();

        $this->assertSame(
            '2026-06-05',
            $this->movementDateOf($batch1['entry']->id),
            'La entrada del lote #1 debe tomar la fecha del exit del lote #1.'
        );
        $this->assertSame(
            '2026-07-05',
            $this->movementDateOf($batch2['entry']->id),
            'La entrada del lote #2 debe tomar la fecha del exit del lote #2, ' .
            'no la del otro lote que casualmente tiene la misma cantidad.'
        );

        // Y el respaldo debe decir la verdad sobre lo que se escribió.
        foreach ([$batch1, $batch2] as $batch) {
            $row = $this->backupRowFor($batch['entry']->id);

            $this->assertNotNull($row, 'Cada entrada corregida debe estar respaldada.');
            $this->assertSame(
                $this->movementDateOf($batch['entry']->id),
                $row->corrected_movement_date,
                'El respaldo debe registrar exactamente la fecha que se escribió.'
            );
            $this->assertSame(self::JULY_WRONG_DATE, $row->original_movement_date);
        }
    }

    // ------------------------------------------------------------------
    // 3. Un grupo irresoluble se salta, no se adivina
    // ------------------------------------------------------------------

    /**
     * Si dos exits del MISMO lote tienen fechas distintas (dato ya inconsistente
     * de origen), no hay forma de saber cuál es la buena. La migración debe
     * dejar la entrada como está en vez de elegir al azar.
     */
    public function test_skips_the_group_when_the_batch_still_has_two_candidate_dates(): void
    {
        $fixtures = $this->createFixtures();
        $reception = $this->makeReception($fixtures, 40);

        $entry = $this->seedMovement(
            $fixtures, $reception, 'entry', 1, self::JULY_WRONG_DATE, self::JULY_WRONG_DATE, 40
        );
        // Dos salidas del lote #1 con fechas distintas: ambigüedad irreducible.
        $this->seedMovement($fixtures, $reception, 'exit', 1, '2026-06-05', '2026-06-05', 40);
        $this->seedMovement($fixtures, $reception, 'exit', 1, '2026-07-05', '2026-07-05', 40);

        $this->runMigration();

        $this->assertSame(
            self::JULY_WRONG_DATE,
            $this->movementDateOf($entry->id),
            'Ante un lote con dos fechas candidatas, la migración debe abstenerse.'
        );
        $this->assertNull(
            $this->backupRowFor($entry->id),
            'Lo que no se corrige no se respalda: el respaldo solo describe cambios reales.'
        );
    }

    // ------------------------------------------------------------------
    // 4. Solo se escribe movement_date
    // ------------------------------------------------------------------

    public function test_migration_only_touches_movement_date(): void
    {
        $fixtures = $this->createFixtures();

        $july = $this->seedTransferPair(
            $fixtures, self::JULY_CORRECT_DATE, self::JULY_WRONG_DATE, self::JULY_WRONG_DATE, 140
        );

        $before = DB::table('inventory_movements')->where('id', $july['entry']->id)->first();

        $this->runMigration();

        $after = DB::table('inventory_movements')->where('id', $july['entry']->id)->first();

        foreach (['quantity', 'unit', 'unit_price', 'total_price', 'location_id', 'created_at', 'observations'] as $column) {
            $this->assertSame(
                $before->{$column},
                $after->{$column},
                "La migración no debe modificar '{$column}'."
            );
        }

        $this->assertNotSame($before->movement_date, $after->movement_date);
    }

    // ------------------------------------------------------------------
    // 5. down() es reversible y conserva la evidencia
    // ------------------------------------------------------------------

    public function test_down_restores_the_original_date_and_keeps_the_backup(): void
    {
        $fixtures = $this->createFixtures();

        $july = $this->seedTransferPair(
            $fixtures, self::JULY_CORRECT_DATE, self::JULY_WRONG_DATE, self::JULY_WRONG_DATE, 140
        );

        $this->runMigration();
        $this->assertSame(self::JULY_CORRECT_DATE, $this->movementDateOf($july['entry']->id));

        $this->migration()->down();

        $this->assertSame(
            self::JULY_WRONG_DATE,
            $this->movementDateOf($july['entry']->id),
            'down() debe devolver la fecha original.'
        );
        $this->assertCount(
            1,
            DB::table(self::BACKUP_TABLE)->get(),
            'down() no debe borrar el respaldo: es la evidencia de qué se corrigió.'
        );
    }
}
