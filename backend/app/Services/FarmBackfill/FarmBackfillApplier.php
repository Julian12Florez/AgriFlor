<?php

namespace App\Services\FarmBackfill;

use App\Services\InventoryService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Escritura REAL de la reparación de entradas en finca.
 *
 * Todo ocurre dentro de UNA transacción, y dentro de ella se recalcula el plan
 * y se verifica el resultado contra referencias que este código NO escribe (los
 * movimientos `exit` de bodega y las entradas de las compras ficticias). Si una
 * sola comprobación falla, la excepción revierte todo.
 *
 * Secuencia (el orden no es intercambiable):
 *
 *   1. Foto de bodega ANTES (kardex + físico). La reparación no debe mover ni
 *      una unidad en bodega, y esa es la garantía que más tranquiliza al
 *      cliente: se comprueba al final.
 *   2. `lockForUpdate()` sobre las filas de `inventory` de las fincas del plan.
 *   3. Recálculo del plan con la base ya bloqueada. Nunca se reutilizan los
 *      números del pre-flight.
 *   4. Respaldo de TODAS las filas de `inventory` de las fincas afectadas
 *      (no sólo las que se tocan: así el rollback es un reemplazo completo).
 *   5. Escritura de las ENTRADAS (kardex + lotes) y sólo después de los NETEOS,
 *      que consumen de esos mismos lotes por FIFO.
 *   6. Verificación.
 *
 * La tabla de respaldo la crea una MIGRACIÓN, no este código: en MySQL
 * cualquier DDL fuerza un COMMIT implícito y partiría en dos la transacción de
 * quien llame a esto desde dentro de una. Lo único que queda aquí es el
 * archivado con `--force` (un RENAME), que se hace fuera de la transacción y
 * sólo cuando hay un respaldo previo que no se debe pisar.
 */
final class FarmBackfillApplier
{
    /** Tabla de respaldo → tabla de la que se respalda. */
    private const BACKUP_TABLES = [
        'inventory_farm_backfill_backup' => 'inventory',
    ];

    /** Tolerancia de la verificación: `decimal(10,2)` no admite más precisión. */
    private const TOLERANCE = 0.01;

    private const EPSILON = 0.005;

    private const CHUNK = 200;

    /**
     * Cerrojo con nombre de MySQL. `lockForUpdate()` no basta: una finca sin
     * ninguna fila de `inventory` (Avomagig) no tiene nada que bloquear, así
     * que dos corridas simultáneas podrían escribirle las dos. Con esto la
     * segunda falla al instante en vez de duplicar 72 toneladas.
     */
    private const RUN_LOCK = 'agriflor:reponer-entradas-finca';

    public function __construct(private readonly InventoryService $inventory)
    {
    }

    /**
     * Ejecuta $work con el cerrojo tomado, y lo suelta pase lo que pase. No es
     * transaccional a propósito: si lo fuera, el rollback lo liberaría antes de
     * tiempo.
     *
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    private function withRunLock(callable $work)
    {
        $acquired = (int) DB::selectOne('SELECT GET_LOCK(?, 0) AS ok', [self::RUN_LOCK])->ok;

        if ($acquired !== 1) {
            throw new RuntimeException(
                'Ya hay otra corrida de la reparación en marcha sobre esta base de datos. '
                . 'Espere a que termine: dos a la vez podrían duplicar lo repuesto.'
            );
        }

        try {
            return $work();
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS ok', [self::RUN_LOCK]);
        }
    }

    /**
     * Crea (o repara) las tablas de respaldo. DDL, así que va FUERA de la
     * transacción. Si ya traen filas de una corrida previa, no se pisan: se
     * archivan con sufijo de fecha. Un respaldo pisado es un respaldo que no
     * sirve.
     *
     * @return array<string, string> tabla → nombre del histórico creado
     */
    public function prepareBackupTables(bool $force): array
    {
        $archived = [];

        foreach (self::BACKUP_TABLES as $backup => $source) {
            // Aquí SÍ se crea si falta: este método es DDL por definición y corre
            // fuera de la transacción. El caso normal es que la tabla no exista
            // porque una corrida anterior con --force la archivó, y eso es
            // recuperable, no un error. El guard estricto vive en
            // ensureBackupTable(), que se usa dentro de la transacción.
            $this->recreateBackupTable($backup, $source);

            if (DB::table($backup)->count() === 0) {
                continue;
            }

            if (! $force) {
                throw new RuntimeException(sprintf(
                    'La tabla de respaldo `%s` ya tiene filas de una corrida previa. Revísela antes de '
                    . 'volver a ejecutar; use --force para archivarla y empezar un respaldo nuevo.',
                    $backup,
                ));
            }

            $archive = $backup . '_' . CarbonImmutable::now()->format('Ymd_His');
            DB::statement("RENAME TABLE `{$backup}` TO `{$archive}`");
            $archived[$backup] = $archive;
            // Archivar deja el nombre libre, así que aquí SÍ toca recrear la
            // tabla. Es DDL, pero corre ANTES de abrir la transacción de la
            // reparación, así que no la parte por la mitad.
            $this->recreateBackupTable($backup, $source);
        }

        return $archived;
    }

    /**
     * @param  FarmBackfillPlan  $preflight  plan del pre-flight: define qué filas bloquear
     * @param  callable(): FarmBackfillPlan  $replan  recalcula el plan con la base ya bloqueada
     * @param  array<string, string>  $archivedBackups
     */
    public function apply(
        FarmBackfillPlan $preflight,
        callable $replan,
        CarbonImmutable $cutoff,
        array $archivedBackups = [],
    ): FarmBackfillResult {
        return $this->withRunLock(fn () => DB::transaction(function () use ($preflight, $replan, $cutoff, $archivedBackups) {
            $warehouseBefore = $this->warehouseSnapshot();

            $locked = [];
            $this->lockFarmInventory($preflight->all(), $locked);

            $plan = $replan();

            if ($plan->isBlocked()) {
                throw new RuntimeException(
                    'Al recalcular dentro de la transacción aparecieron ' . count($plan->blockers)
                    . ' bloqueo(s): ' . PHP_EOL . '  · ' . implode(PHP_EOL . '  · ', array_slice($plan->blockers, 0, 10))
                );
            }

            $this->lockFarmInventory($plan->all(), $locked);
            $this->assertCutoffRespected($plan, $cutoff);

            $backedUp = $this->backupFarmInventory($plan);

            $physical = $this->writeEntries($plan->entries);
            $this->writeNettings($plan->nettings);

            $checks = $this->verify($plan, $warehouseBefore);

            return new FarmBackfillResult(
                applied: $plan,
                entryMovements: count($plan->entries),
                nettingMovements: count($plan->nettings),
                inventoryRowsCreated: $physical['created'],
                inventoryRowsUpdated: $physical['updated'],
                inventoryRowsBackedUp: $backedUp,
                checksRun: $checks,
                archivedBackups: $archivedBackups,
            );
        }));
    }

    /**
     * Deshace una corrida: borra los movimientos marcados y devuelve el
     * `inventory` de las fincas al respaldo previo, todo en una transacción.
     *
     * @return array{movimientos: int, filas_restauradas: int, fincas: int}
     */
    public function revert(): array
    {
        $backup = array_key_first(self::BACKUP_TABLES);

        if (! Schema::hasTable($backup)) {
            throw new RuntimeException("No existe la tabla de respaldo `{$backup}`: no hay nada que revertir.");
        }

        // La señal de que hubo corrida son los movimientos MARCADOS, no el
        // respaldo: si las fincas afectadas no tenían ni una fila de
        // `inventory` antes (Avomagig, creada el 24-ago), el respaldo queda
        // legítimamente vacío y la corrida sí hay que poder deshacerla.
        $marked = DB::table('inventory_movements')
            ->where('observations', 'like', FarmBackfillPlanner::MARK_PREFIX . '%')
            ->count();

        if ($marked === 0 && DB::table($backup)->count() === 0) {
            throw new RuntimeException(
                'No hay ninguna corrida que revertir: ni movimientos marcados ni filas respaldadas.'
            );
        }

        return $this->withRunLock(fn () => DB::transaction(function () use ($backup) {
            // Las ubicaciones a reponer salen de la UNIÓN del respaldo y de los
            // movimientos marcados. Sólo con el respaldo se escaparían las
            // fincas que no tenían ni una fila de `inventory` antes de la
            // corrida (Avomagig, creada el 24-ago, es el caso real): sus lotes
            // nuevos sobrevivirían al borrado de sus movimientos y dejarían
            // físico sin kardex, que es justo el descuadre que esto evita.
            $farms = array_values(array_unique(array_merge(
                DB::table($backup)->distinct()->pluck('location_id')->all(),
                DB::table('inventory_movements')
                    ->where('observations', 'like', FarmBackfillPlanner::MARK_PREFIX . '%')
                    ->distinct()
                    ->pluck('location_id')
                    ->all(),
            )));

            DB::table('inventory')->whereIn('location_id', $farms)->delete();

            $columns = $this->columnList('inventory');
            $restored = DB::affectingStatement(
                "INSERT INTO `inventory` ({$columns}) SELECT {$columns} FROM `{$backup}`"
            );

            $movements = DB::table('inventory_movements')
                ->where('observations', 'like', FarmBackfillPlanner::MARK_PREFIX . '%')
                ->delete();

            $divergent = $this->divergentTriples();

            if ($divergent !== []) {
                throw new RuntimeException(
                    'Tras revertir quedaron ' . count($divergent) . ' triple(s) con el kardex descuadrado '
                    . 'contra el físico; se deshace la reversión: ' . PHP_EOL . '  · '
                    . implode(PHP_EOL . '  · ', array_slice($divergent, 0, 10))
                );
            }

            return [
                'movimientos' => $movements,
                'filas_restauradas' => $restored,
                'fincas' => count($farms),
            ];
        }));
    }

    // -------------------------------------------------------------- respaldo

    /**
     * La tabla la crea la migración
     * `2026_09_07_120000_create_inventory_farm_backfill_backup_table`. Aquí sólo
     * se comprueba, y si ya está bien NO se ejecuta ni una sentencia DDL: en
     * MySQL un DDL fuerza COMMIT implícito y partiría en dos la transacción de
     * quien llame a esto desde dentro de una (las pruebas, entre otros).
     *
     * El CREATE de emergencia se conserva por si el comando se ejecuta en una
     * base a la que aún no le corrieron las migraciones; nunca debería hacer
     * falta, y si hace falta se nota porque va acompañado del respaldo vacío.
     */
    /**
     * El respaldo es parte del ESQUEMA, no algo que este comando improvise.
     *
     * Crearlo aquí en caliente era silencioso y peor de dos formas: en MySQL todo
     * DDL fuerza un COMMIT implícito, que rompe la transacción de RefreshDatabase
     * a media prueba; y la tabla nacía con el índice ÚNICO de `inventory`, que en
     * un respaldo estorba (archivar dos corridas del mismo triple es legítimo).
     * Si falta, es que falta correr las migraciones: hay que decirlo, no taparlo.
     */
    private function ensureBackupTable(string $backup, string $source): void
    {
        if (Schema::hasTable($backup) && Schema::hasColumn($backup, 'backed_up_at')) {
            return;
        }

        throw new RuntimeException(
            "La tabla de respaldo `{$backup}` no existe o está incompleta. "
            . 'Corra `php artisan migrate` antes de la reparación: el respaldo se crea en '
            . 'la migración 2026_09_07_120000_create_inventory_farm_backfill_backup_table, '
            . 'no en tiempo de ejecución.'
        );
    }

    /**
     * Recrea la tabla de respaldo con el MISMO esquema que fija la migración
     * 2026_09_07_120000_create_inventory_farm_backfill_backup_table.
     *
     * Solo se usa después de archivar la anterior con --force, que deja el nombre
     * libre. Fuera de ese caso la tabla es parte del esquema y se exige que ya
     * exista ({@see self::ensureBackupTable}).
     */
    private function recreateBackupTable(string $backup, string $source): void
    {
        if (Schema::hasTable($backup)) {
            return;
        }

        DB::statement("CREATE TABLE `{$backup}` LIKE `{$source}`");
        DB::statement(
            "ALTER TABLE `{$backup}` ADD COLUMN `backed_up_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP"
        );

        // El índice ÚNICO de `inventory` estorba en un respaldo: archivar dos
        // corridas del mismo triple es legítimo.
        $unique = 'inventory_product_id_brand_id_location_id_batch_number_unique';

        foreach (DB::select("SHOW INDEX FROM `{$backup}`") as $index) {
            if ($index->Key_name === $unique) {
                DB::statement("ALTER TABLE `{$backup}` DROP INDEX `{$unique}`");
                break;
            }
        }
    }

    /**
     * Respalda TODAS las filas de `inventory` de las fincas del plan, no sólo
     * las que se van a tocar: el rollback es entonces "borrar la finca y
     * reponer el respaldo", que no puede dejar filas huérfanas.
     */
    private function backupFarmInventory(FarmBackfillPlan $plan): int
    {
        $farms = $this->farmIds($plan);

        if ($farms === []) {
            return 0;
        }

        $columns = $this->columnList('inventory');
        $backup = array_key_first(self::BACKUP_TABLES);
        $total = 0;

        foreach (array_chunk($farms, self::CHUNK) as $chunk) {
            $placeholders = implode(', ', array_fill(0, count($chunk), '?'));
            $total += DB::affectingStatement(
                "INSERT INTO `{$backup}` ({$columns}, `backed_up_at`)
                 SELECT {$columns}, NOW() FROM `inventory` WHERE `location_id` IN ({$placeholders})",
                $chunk,
            );
        }

        return $total;
    }

    // ------------------------------------------------------------ escrituras

    /**
     * Kardex y lotes de las entradas, EN LA MISMA TRANSACCIÓN.
     *
     * Hoy no hay un solo par divergente entre `inventory` e `inventory_movements`
     * en toda la base; escribir una tabla sin la otra rompería esa propiedad y
     * nadie se enteraría hasta el cierre.
     *
     * Varios movimientos pueden caer en el mismo lote (misma recepción, mismo
     * producto, mismo nº de lote): se funden en UNA fila con precio promedio
     * ponderado, igual que hace `ReceptionController::updateInventoryStock`
     * cuando el lote ya existe. Es obligatorio: `inventory` tiene índice único
     * sobre (product_id, brand_id, location_id, batch_number).
     *
     * @param  array<int, PlannedMovement>  $entries
     * @return array{created: int, updated: int}
     */
    private function writeEntries(array $entries): array
    {
        if ($entries === []) {
            return ['created' => 0, 'updated' => 0];
        }

        $now = CarbonImmutable::now();
        $movements = [];
        $batches = [];

        foreach ($entries as $entry) {
            $movements[] = [
                'id' => (string) Str::orderedUuid(),
                'type' => 'entry',
                'product_id' => $entry->productId,
                'brand_id' => $entry->brandId,
                'location_id' => $entry->locationId,
                'quantity' => $entry->quantity,
                'unit' => $entry->unit,
                'movement_date' => $entry->movementDate,
                'expiration_date' => $entry->expirationDate,
                'unit_price' => $entry->unitPrice,
                'total_price' => $entry->totalPrice(),
                'responsible_user' => $entry->responsibleUser,
                'related_document_id' => $entry->relatedDocumentId,
                'related_document_type' => $entry->relatedDocumentType,
                'observations' => $this->mark($entry)
                    . 'Reposición de la entrada que no se escribió en la finca al recepcionar '
                    . $entry->documentLabel . '.',
                'created_at' => $now,
            ];

            $key = $entry->batchKey();
            $batches[$key] ??= [
                'entry' => $entry,
                'quantity' => 0.0,
                'value' => 0.0,
                'expiration' => null,
            ];
            $batches[$key]['quantity'] += $entry->quantity;
            $batches[$key]['value'] += $entry->quantity * $entry->unitPrice;
            $batches[$key]['expiration'] = $this->earlierDate($batches[$key]['expiration'], $entry->expirationDate);
        }

        foreach (array_chunk($movements, self::CHUNK) as $chunk) {
            DB::table('inventory_movements')->insert($chunk);
        }

        return $this->writeBatches($batches, $now);
    }

    /**
     * @param  array<string, array{entry: PlannedMovement, quantity: float, value: float, expiration: ?string}>  $batches
     * @return array{created: int, updated: int}
     */
    private function writeBatches(array $batches, CarbonImmutable $now): array
    {
        $inserts = [];
        $updated = 0;

        foreach ($batches as $batch) {
            /** @var PlannedMovement $entry */
            $entry = $batch['entry'];
            $quantity = round($batch['quantity'], 2);

            if ($quantity <= self::EPSILON) {
                continue;
            }

            $price = $quantity > 0 ? round($batch['value'] / $quantity, 2) : $entry->unitPrice;

            $existing = DB::table('inventory')
                ->where('product_id', $entry->productId)
                ->where('brand_id', $entry->brandId)
                ->where('location_id', $entry->locationId)
                ->where('batch_number', $entry->batchNumber)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                // Mismo camino que updateInventoryStock cuando el lote ya
                // existe: cantidad sumada y costo promedio ponderado.
                $newQuantity = round((float) $existing->quantity + $quantity, 2);
                $newValue = ((float) $existing->quantity * (float) $existing->unit_price) + $batch['value'];
                $newPrice = $newQuantity > 0 ? round($newValue / $newQuantity, 2) : $price;
                $expiration = $this->earlierDate(
                    $existing->expiration_date === null ? null : substr((string) $existing->expiration_date, 0, 10),
                    $batch['expiration'],
                );

                DB::table('inventory')->where('id', $existing->id)->update([
                    'quantity' => $newQuantity,
                    'unit_price' => $newPrice,
                    'total_value' => round($newQuantity * $newPrice, 2),
                    'expiration_date' => $expiration,
                    'status' => $this->statusFor($expiration),
                    'updated_at' => $now,
                ]);

                $updated++;

                continue;
            }

            $inserts[] = [
                'id' => (string) Str::orderedUuid(),
                'product_id' => $entry->productId,
                'brand_id' => $entry->brandId,
                'location_id' => $entry->locationId,
                'batch_number' => $entry->batchNumber,
                'quantity' => $quantity,
                'unit' => $entry->unit,
                'expiration_date' => $batch['expiration'],
                'unit_price' => $price,
                'total_value' => round($quantity * $price, 2),
                'status' => $this->statusFor($batch['expiration']),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($inserts, self::CHUNK) as $chunk) {
            DB::table('inventory')->insert($chunk);
        }

        return ['created' => count($inserts), 'updated' => $updated];
    }

    /**
     * Contrapartidas del neteo. Van DESPUÉS de las entradas porque consumen de
     * los lotes que aquéllas acaban de crear.
     *
     * El descargo físico usa `InventoryService::reduceInventoryFIFO`, el mismo
     * que usa la aplicación: así el lote consumido, la conversión de unidad y el
     * costo salen exactamente igual que si el usuario lo hubiera hecho a mano.
     * El precio del movimiento es el costo REAL de lo consumido que devuelve el
     * FIFO; si el lote no tuviera costo, se cae al de la entrada de bodega.
     *
     * @param  array<int, PlannedMovement>  $nettings
     */
    private function writeNettings(array $nettings): void
    {
        $now = CarbonImmutable::now();

        foreach ($nettings as $netting) {
            $consumed = $this->inventory->reduceInventoryFIFO(
                $netting->productId,
                $netting->brandId,
                $netting->locationId,
                $netting->quantity,
                $netting->unit,
            );

            $price = ((float) ($consumed['unit_price_base'] ?? 0.0)) > 0
                ? round((float) $consumed['unit_price_base'], 2)
                : $netting->unitPrice;

            DB::table('inventory_movements')->insert([
                'id' => (string) Str::orderedUuid(),
                'type' => 'exit',
                'product_id' => $netting->productId,
                'brand_id' => $netting->brandId,
                'location_id' => $netting->locationId,
                'quantity' => $netting->quantity,
                'unit' => $netting->unit,
                'movement_date' => $netting->movementDate,
                'expiration_date' => null,
                'unit_price' => $price,
                'total_price' => round($netting->quantity * $price, 2),
                'responsible_user' => $netting->responsibleUser,
                'related_document_id' => $netting->relatedDocumentId,
                'related_document_type' => $netting->relatedDocumentType,
                'observations' => $this->mark($netting)
                    . 'Contrapartida en finca de la devolución que se registró como compra '
                    . $netting->documentLabel . ' al proveedor de remanentes. Sin ella, lo devuelto '
                    . 'quedaría contado en bodega y en la finca a la vez.',
                'created_at' => $now,
            ]);
        }
    }

    /** Marca de idempotencia con el UUID del movimiento del que se deriva. */
    private function mark(PlannedMovement $movement): string
    {
        return FarmBackfillPlanner::MARK_PREFIX . $movement->sourceMovementId . '] ';
    }

    // ---------------------------------------------------------- verificación

    /**
     * Comprobaciones contra referencias que este código NO escribe.
     *
     * @param  array<string, array{kardex: float, fisico: float}>  $warehouseBefore
     * @return int número de comprobaciones ejecutadas
     */
    private function verify(FarmBackfillPlan $plan, array $warehouseBefore): int
    {
        $failures = [];
        $checks = 0;

        // 1. Cada `exit` huérfano tiene ya su entrada, con la misma cantidad y
        //    la misma fecha. La referencia es el `exit`, que existía antes.
        foreach ($plan->entries as $entry) {
            $checks++;

            $written = DB::table('inventory_movements')
                ->where('type', 'entry')
                ->where('location_id', $entry->locationId)
                ->where('product_id', $entry->productId)
                ->where('brand_id', $entry->brandId)
                ->where('related_document_id', $entry->relatedDocumentId)
                ->where('movement_date', $entry->movementDate)
                ->where('observations', 'like', '%' . $entry->sourceMovementId . '%')
                ->sum('quantity');

            if (abs((float) $written - $entry->quantity) > self::TOLERANCE) {
                $failures[] = sprintf(
                    '%s · %s · %s: se escribió %s y se esperaba %s.',
                    $entry->locationName,
                    $entry->productName,
                    $entry->documentLabel,
                    number_format((float) $written, 2),
                    number_format($entry->quantity, 2),
                );
            }
        }

        // 2. El invariante que hoy vale en toda la base: 0 pares divergentes
        //    entre el kardex y el físico.
        $checks++;
        $divergent = $this->divergentTriples();

        foreach ($divergent as $detail) {
            $failures[] = 'Kardex y físico dejaron de cuadrar: ' . $detail;
        }

        // 3. Ni un saldo negativo, ni en el libro ni en los lotes.
        $checks++;
        foreach ($this->negativeBalances($plan) as $detail) {
            $failures[] = $detail;
        }

        // 4. LA BODEGA NO SE MUEVE. Es lo único que el cliente ya concilió.
        $checks++;
        foreach ($this->warehouseDrift($warehouseBefore) as $detail) {
            $failures[] = 'La bodega se movió y no debía: ' . $detail;
        }

        if ($failures !== []) {
            throw new RuntimeException(
                'La verificación falló en ' . count($failures) . ' comprobación(es); se revierte TODO. Detalle:'
                . PHP_EOL . '  · ' . implode(PHP_EOL . '  · ', array_slice($failures, 0, 20))
                . (count($failures) > 20 ? PHP_EOL . '  · ... y ' . (count($failures) - 20) . ' más.' : '')
            );
        }

        return $checks;
    }

    /**
     * Triples en los que el saldo del kardex no coincide con la suma de lotes.
     *
     * @return array<int, string>
     */
    private function divergentTriples(): array
    {
        // UNION ALL de las dos tablas para emular un FULL OUTER JOIN: MySQL no
        // lo tiene, y con un LEFT JOIN se escaparían los triples que existen
        // sólo en `inventory` (lote sin un solo movimiento), que son
        // exactamente la mitad del descuadre que esta comprobación busca.
        $rows = DB::select(
            "SELECT product_id, brand_id, location_id,
                    ROUND(SUM(kardex), 2) AS kardex, ROUND(SUM(fisico), 2) AS fisico
               FROM (
                    SELECT product_id, brand_id, location_id,
                           SUM(CASE WHEN type = 'entry' THEN quantity ELSE -quantity END) AS kardex,
                           0 AS fisico
                      FROM inventory_movements GROUP BY product_id, brand_id, location_id
                    UNION ALL
                    SELECT product_id, brand_id, location_id, 0 AS kardex, SUM(quantity) AS fisico
                      FROM inventory GROUP BY product_id, brand_id, location_id
               ) u
              GROUP BY product_id, brand_id, location_id
             HAVING ABS(ROUND(SUM(kardex), 2) - ROUND(SUM(fisico), 2)) > ?",
            [self::TOLERANCE],
        );

        return array_map(
            fn (object $row) => sprintf(
                'producto %s en %s: kardex %s, físico %s',
                $row->product_id,
                $row->location_id,
                number_format((float) $row->kardex, 2),
                number_format((float) $row->fisico, 2),
            ),
            $rows,
        );
    }


    /**
     * Cierres de mes que el plan toca, más el del mes anterior al primero: son
     * los cortes contra los que el cliente concilia.
     *
     * @return array<int, string>
     */
    private function monthEndCutoffs(FarmBackfillPlan $plan): array
    {
        $meses = [];

        foreach (array_merge($plan->entries, $plan->nettings) as $movement) {
            $meses[substr($movement->movementDate, 0, 7)] = true;
        }

        if ($meses === []) {
            return [];
        }

        $ordenados = array_keys($meses);
        sort($ordenados);

        // El mes anterior al primero: si el plan lo dejó en negativo, el arrastre
        // envenena el inicial del siguiente.
        $previo = CarbonImmutable::createFromFormat('Y-m-d', $ordenados[0] . '-01')
            ->subMonth()
            ->format('Y-m');
        array_unshift($ordenados, $previo);

        return array_map(
            fn (string $mes) => CarbonImmutable::createFromFormat('Y-m-d', $mes . '-01')
                ->endOfMonth()
                ->toDateString(),
            array_values(array_unique($ordenados)),
        );
    }

    /** @return array<int, string> */
    private function negativeBalances(FarmBackfillPlan $plan): array
    {
        $failures = [];

        $negativeStock = DB::table('inventory')->where('quantity', '<', -self::TOLERANCE)->count();

        if ($negativeStock > 0) {
            $failures[] = "{$negativeStock} fila(s) de `inventory` con cantidad negativa.";
        }

        $farms = $this->farmIds($plan);

        if ($farms === []) {
            return $failures;
        }

        $negativeLedger = DB::table('inventory_movements')
            ->select('product_id', 'brand_id', 'location_id')
            ->selectRaw("SUM(CASE WHEN type = 'entry' THEN quantity ELSE -quantity END) as saldo")
            ->whereIn('location_id', $farms)
            ->groupBy('product_id', 'brand_id', 'location_id')
            ->havingRaw('saldo < ?', [-self::TOLERANCE])
            ->get();

        // El saldo TOTAL no basta: un neteo fechado antes de la entrada que lo
        // financia deja el mes en negativo y se recupera después, así que la suma
        // sin fecha da el visto bueno mientras el informe mensual muestra el
        // negativo. Pasó de verdad: Villa / BOROZINCO FOLIAR cerró agosto en
        // −10,00 L con esta comprobación en verde. Por eso se mira también CADA
        // CORTE MENSUAL que el plan toca, que es lo que ve el cliente al conciliar.
        foreach ($this->monthEndCutoffs($plan) as $corte) {
            $negativosAlCorte = DB::table('inventory_movements')
                ->select('product_id', 'brand_id', 'location_id')
                ->selectRaw("SUM(CASE WHEN type = 'entry' THEN quantity ELSE -quantity END) as saldo")
                ->whereIn('location_id', $farms)
                ->where('movement_date', '<=', $corte)
                ->groupBy('product_id', 'brand_id', 'location_id')
                ->havingRaw('saldo < ?', [-self::TOLERANCE])
                ->get();

            foreach ($negativosAlCorte as $row) {
                $failures[] = sprintf(
                    'El mes cierra en NEGATIVO al %s en el triple %s|%s|%s: %s. '
                    . 'Es el saldo que vería el informe mensual de esa finca.',
                    $corte,
                    $row->product_id,
                    $row->brand_id,
                    $row->location_id,
                    number_format((float) $row->saldo, 2),
                );
            }
        }

        foreach ($negativeLedger as $row) {
            $failures[] = sprintf(
                'Saldo de kardex NEGATIVO en el triple %s|%s|%s: %s.',
                $row->product_id,
                $row->brand_id,
                $row->location_id,
                number_format((float) $row->saldo, 2),
            );
        }

        return $failures;
    }

    /**
     * Kardex y físico de la bodega por producto: la foto que no puede cambiar.
     *
     * @return array<string, array{kardex: float, fisico: float}>
     */
    private function warehouseSnapshot(): array
    {
        $snapshot = [];

        $ledger = DB::table('inventory_movements')
            ->select('product_id')
            ->selectRaw("SUM(CASE WHEN type = 'entry' THEN quantity ELSE -quantity END) as saldo")
            ->whereIn('location_id', function ($query) {
                $query->select('id')->from('locations')->where('type', 'warehouse');
            })
            ->groupBy('product_id')
            ->get();

        foreach ($ledger as $row) {
            $snapshot[$row->product_id] = ['kardex' => round((float) $row->saldo, 2), 'fisico' => 0.0];
        }

        $physical = DB::table('inventory')
            ->select('product_id')
            ->selectRaw('SUM(quantity) as stock')
            ->whereIn('location_id', function ($query) {
                $query->select('id')->from('locations')->where('type', 'warehouse');
            })
            ->groupBy('product_id')
            ->get();

        foreach ($physical as $row) {
            $snapshot[$row->product_id] ??= ['kardex' => 0.0, 'fisico' => 0.0];
            $snapshot[$row->product_id]['fisico'] = round((float) $row->stock, 2);
        }

        return $snapshot;
    }

    /**
     * @param  array<string, array{kardex: float, fisico: float}>  $before
     * @return array<int, string>
     */
    private function warehouseDrift(array $before): array
    {
        $after = $this->warehouseSnapshot();
        $failures = [];

        foreach (array_keys($before + $after) as $productId) {
            foreach (['kardex', 'fisico'] as $concept) {
                $was = $before[$productId][$concept] ?? 0.0;
                $is = $after[$productId][$concept] ?? 0.0;

                if (abs($was - $is) > self::TOLERANCE) {
                    $failures[] = sprintf(
                        'producto %s, %s: %s → %s',
                        $productId,
                        $concept,
                        number_format($was, 2),
                        number_format($is, 2),
                    );
                }
            }
        }

        return $failures;
    }

    private function assertCutoffRespected(FarmBackfillPlan $plan, CarbonImmutable $cutoff): void
    {
        foreach ($plan->all() as $movement) {
            if ($movement->movementDate <= $cutoff->toDateString()) {
                throw new RuntimeException(sprintf(
                    'CORTE DURO: se intentó escribir %s · %s con fecha %s, dentro del periodo cerrado (<= %s).',
                    $movement->locationName,
                    $movement->productName,
                    $movement->movementDate,
                    $cutoff->toDateString(),
                ));
            }
        }
    }

    // ------------------------------------------------------------ auxiliares

    /**
     * @param  array<int, PlannedMovement>  $movements
     * @param  array<string, bool>  $locked
     */
    private function lockFarmInventory(array $movements, array &$locked): void
    {
        $pending = [];

        foreach ($movements as $movement) {
            if (isset($locked[$movement->locationId])) {
                continue;
            }

            $locked[$movement->locationId] = true;
            $pending[] = $movement->locationId;
        }

        foreach (array_chunk($pending, self::CHUNK) as $chunk) {
            $placeholders = implode(', ', array_fill(0, count($chunk), '?'));
            DB::select("SELECT `id` FROM `inventory` WHERE `location_id` IN ({$placeholders}) FOR UPDATE", $chunk);
        }
    }

    /** @return array<int, string> */
    private function farmIds(FarmBackfillPlan $plan): array
    {
        $farms = [];

        foreach ($plan->all() as $movement) {
            $farms[$movement->locationId] = true;
        }

        return array_keys($farms);
    }

    private function earlierDate(?string $current, ?string $candidate): ?string
    {
        if ($candidate === null) {
            return $current;
        }

        return ($current === null || $candidate < $current) ? $candidate : $current;
    }

    /** Misma clasificación que `ReceptionController::calculateInventoryStatus()`. */
    private function statusFor(?string $expiration): string
    {
        if ($expiration === null) {
            return 'good';
        }

        $days = (int) CarbonImmutable::now()->startOfDay()
            ->diffInDays(CarbonImmutable::parse($expiration)->startOfDay(), false);

        if ($days < 0) {
            return 'expired';
        }

        return $days <= 30 ? 'near_expiry' : 'good';
    }

    private function columnList(string $table): string
    {
        return implode(', ', array_map(
            fn (string $column) => "`{$column}`",
            Schema::getColumnListing($table),
        ));
    }
}
