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
use Tests\TestCase;

/**
 * Migración correctiva 2026_07_30_120000_realign_transfer_entry_movement_dates.
 *
 * Lo que hay que demostrar no es solo que corrige, sino sobre todo que NO toca
 * lo que no debe: mayo de 2026 ya se conció contra contabilidad (Siigo) y un
 * criterio de selección demasiado amplio destruiría ese cierre. Por eso hay dos
 * movimientos sembrados: uno con la firma del bug (julio) y uno de mayo que
 * cumpliría un criterio ingenuo pero debe quedar intacto.
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

    private function runMigration(): void
    {
        $migration = require database_path(
            'migrations/2026_07_30_120000_realign_transfer_entry_movement_dates.php'
        );

        $migration->up();
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

        $brand = Brand::create(['name' => 'Marca Mig ' . uniqid(), 'status' => 'active']);

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

        $bodega = Location::create(['name' => 'Bodega Mig', 'type' => 'warehouse', 'status' => 'active']);
        $finca = Location::create(['name' => 'Finca Mig', 'type' => 'farm', 'status' => 'active']);

        return compact('admin', 'brand', 'product', 'bodega', 'finca');
    }

    /**
     * Recepción de una salida (traslado) con su par de movimientos exit/entry.
     * `$createdAt` de la entrada define si la fila lleva la firma del bug: la
     * huella es `movement_date = DATE(created_at)`.
     */
    private function seedTransferPair(
        array $fixtures,
        string $exitDate,
        string $entryDate,
        string $entryCreatedAt,
        float $quantity
    ): array {
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

        $exit = $this->seedMovement($fixtures, $reception, 'exit', $exitDate, $exitDate, $quantity);
        $entry = $this->seedMovement($fixtures, $reception, 'entry', $entryDate, $entryCreatedAt, $quantity);

        return compact('reception', 'exit', 'entry');
    }

    private function seedMovement(
        array $fixtures,
        Reception $reception,
        string $type,
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
            'observations' => "Movimiento sembrado {$type}",
        ]);

        // `inventory_movements` no tiene updated_at; created_at se fija a mano
        // porque la firma del bug se detecta comparándolo con movement_date.
        DB::table('inventory_movements')
            ->where('id', $movement->id)
            ->update(['created_at' => $createdAt . ' 09:00:00']);

        return $movement->fresh();
    }

    private function movementDateOf(string $movementId): string
    {
        return DB::table('inventory_movements')
            ->where('id', $movementId)
            ->value('movement_date');
    }

    public function test_migration_realigns_july_entry_protects_may_and_is_idempotent(): void
    {
        $fixtures = $this->createFixtures();

        // (1) Traslado de JULIO con la firma del bug: la entrada quedó fechada
        //     con su fecha de registro y difiere de su exit hermano.
        $july = $this->seedTransferPair(
            $fixtures,
            self::JULY_CORRECT_DATE,
            self::JULY_WRONG_DATE,
            self::JULY_WRONG_DATE,
            140
        );

        // (2) Traslado de MAYO: exit y entry con fechas distintas, pero la
        //     entrada NO tiene la huella de now() (created_at es de junio,
        //     cuando se cargó el histórico). Un criterio ingenuo la agarraría.
        $may = $this->seedTransferPair(
            $fixtures,
            self::MAY_EXIT_DATE,
            self::MAY_ENTRY_DATE,
            '2026-06-05',
            80
        );

        // (3) Traslado de MAYO con la firma completa del bug: aun así es
        //     intocable, porque mayo está cerrado contra contabilidad.
        $mayWithSignature = $this->seedTransferPair(
            $fixtures,
            '2026-05-05',
            '2026-05-25',
            '2026-05-25',
            60
        );

        $this->runMigration();

        // La entrada de julio se realineó a la fecha de su exit.
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
            DB::table(self::BACKUP_TABLE)->where('movement_id', $july['entry']->id)
                ->value('original_movement_date'),
            'El respaldo debe conservar el valor ORIGINAL, no el ya corregido.'
        );
    }

    public function test_migration_only_touches_movement_date(): void
    {
        $fixtures = $this->createFixtures();

        $july = $this->seedTransferPair(
            $fixtures,
            self::JULY_CORRECT_DATE,
            self::JULY_WRONG_DATE,
            self::JULY_WRONG_DATE,
            140
        );

        $before = DB::table('inventory_movements')->where('id', $july['entry']->id)->first();

        $this->runMigration();

        $after = DB::table('inventory_movements')->where('id', $july['entry']->id)->first();

        foreach (['quantity', 'unit', 'unit_price', 'total_price', 'location_id', 'created_at'] as $column) {
            $this->assertSame(
                $before->{$column},
                $after->{$column},
                "La migración no debe modificar '{$column}'."
            );
        }

        $this->assertNotSame($before->movement_date, $after->movement_date);
    }

    public function test_down_restores_the_original_date_and_keeps_the_backup(): void
    {
        $fixtures = $this->createFixtures();

        $july = $this->seedTransferPair(
            $fixtures,
            self::JULY_CORRECT_DATE,
            self::JULY_WRONG_DATE,
            self::JULY_WRONG_DATE,
            140
        );

        $this->runMigration();
        $this->assertSame(self::JULY_CORRECT_DATE, $this->movementDateOf($july['entry']->id));

        $migration = require database_path(
            'migrations/2026_07_30_120000_realign_transfer_entry_movement_dates.php'
        );
        $migration->down();

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
