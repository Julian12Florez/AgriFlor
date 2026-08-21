<?php

namespace App\Services\Rebaseline;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Aplicación REAL del re-baseline de inventario (reglas v3 aprobadas).
 *
 * Todo lo que escribe ocurre dentro de UNA transacción, y dentro de esa misma
 * transacción se vuelve a calcular el plan y se verifica el resultado CONTRA EL
 * ARCHIVO. Si una sola comprobación falla, se lanza excepción y el rollback deja
 * la base exactamente como estaba.
 *
 * Secuencia (el orden importa y no es intercambiable):
 *
 *   1. `lockForUpdate()` sobre las filas de `inventory` de los triples del
 *      pre-flight. Se hace ANTES de leer nada más para que los saldos que se
 *      recalculan a continuación no puedan moverse bajo los pies.
 *   2. Respaldo + borrado de TODOS los ajustes (`adjustments` y sus movimientos
 *      en `inventory_movements`). Va antes del recálculo a propósito: así `A`
 *      (delta de agosto) sale ya SIN los movimientos de ajuste, que es
 *      exactamente lo que exigen las reglas, sin necesidad de filtrarlos a mano
 *      en la consulta.
 *   3. Recálculo del plan (K31, A, P) con la base ya en su estado definitivo.
 *      NUNCA se reutilizan los números del pre-flight: son la foto de hace unos
 *      segundos, y esta es la única idempotencia real que tiene la corrida.
 *   4. Respaldo de las filas de `inventory` que se van a tocar.
 *   5. Escritura: kardex (una fila en `adjustments` + UN movimiento fechado al
 *      corte por J − K31) y físico (lote único BASE-JUL-2026 con J + A).
 *   6. Verificación por triple contra el archivo. No compara tabla contra tabla
 *      (eso sería tautológico: las dos las acaba de escribir el mismo código),
 *      sino contra `J` y `J + A` derivados del Excel del cliente.
 *
 * Las tablas de respaldo se crean FUERA de la transacción porque en MySQL
 * cualquier DDL fuerza un COMMIT implícito y partiría la transacción en dos.
 */
final class RebaselineApplier
{
    /** Marcas de idempotencia de la corrida real (reglas v3, punto 6). */
    public const ADJUSTMENT_PREFIX = 'REBASE-JUL26-';

    public const BASELINE_BATCH = 'BASE-JUL-2026';

    /**
     * Mismo literal que `AdjustmentController::MOVEMENT_DOCUMENT_TYPE`. Los
     * informes mensuales clasifican un movimiento como aumento o disminución
     * mirando `adjustments.type` cuando `related_document_type` es este valor,
     * así que la fila de `adjustments` debe existir de verdad.
     */
    private const MOVEMENT_DOCUMENT_TYPE = 'App\Models\Adjustment';

    /**
     * Etiquetas idénticas a las del módulo de ajustes. Contienen las palabras
     * clave que buscan los informes ('aumento'/'ajuste positivo' y
     * 'disminuc'/'ajuste negativo') para el histórico que no viene de un ajuste.
     */
    private const POSITIVE_TAG = '[AUMENTO / ajuste positivo]';

    private const NEGATIVE_TAG = '[DISMINUCIÓN / ajuste negativo]';

    /** Motivo con el que quedan catalogados los ajustes de la línea base. */
    private const REASON_CODE = 'ajuste_inicial';

    /** Tolerancia de la verificación: `decimal(10,2)` no admite más precisión. */
    private const TOLERANCE = 0.01;

    /** Umbral para decidir si un número es "cero" antes de redondear a 2. */
    private const EPSILON = 0.0001;

    /** Tamaño de lote de los INSERT/DELETE masivos. */
    private const CHUNK = 200;

    /** Tabla de respaldo → tabla de la que se respalda. */
    private const BACKUP_TABLES = [
        'inventory_rebaseline_backup' => 'inventory',
        'inventory_movements_rebaseline_backup' => 'inventory_movements',
        'adjustments_rebaseline_backup' => 'adjustments',
    ];

    /**
     * Marcas de una corrida previa. Devuelve el detalle para poder explicarle al
     * operador QUÉ encontró en vez de un "ya se aplicó" a secas.
     *
     * @return array{ajustes: int, lotes: int}
     */
    public function previousRunMarks(): array
    {
        return [
            'ajustes' => DB::table('adjustments')
                ->where('adjustment_number', 'like', self::ADJUSTMENT_PREFIX . '%')
                ->count(),
            'lotes' => DB::table('inventory')
                ->where('batch_number', self::BASELINE_BATCH)
                ->count(),
        ];
    }

    /**
     * Crea (o repara) las tres tablas de respaldo. DDL, así que va FUERA de la
     * transacción.
     *
     * Si alguna trae filas de una corrida anterior la corrida se detiene, salvo
     * `--force`; con `--force` el respaldo viejo NO se borra: se archiva con
     * sufijo de fecha y se empieza uno limpio. Un respaldo pisado es un respaldo
     * que no sirve.
     *
     * @return array<string, string> tabla → nombre del archivo histórico creado
     */
    public function prepareBackupTables(bool $force): array
    {
        $archived = [];

        foreach (self::BACKUP_TABLES as $backup => $source) {
            $this->ensureBackupTable($backup, $source);

            $rows = DB::table($backup)->count();

            if ($rows === 0) {
                continue;
            }

            if (! $force) {
                throw new RuntimeException(sprintf(
                    'La tabla de respaldo `%s` ya tiene %s fila(s) de una corrida previa. '
                    . 'Revise ese respaldo antes de volver a ejecutar; use --force para archivarlo '
                    . 'automáticamente y empezar uno nuevo.',
                    $backup,
                    number_format($rows),
                ));
            }

            $archived[$backup] = $this->archiveBackupTable($backup);
            $this->ensureBackupTable($backup, $source);
        }

        return $archived;
    }

    /**
     * @param  array<int, PlanRow>  $preflightRows  plan del pre-flight: define qué filas se bloquean
     * @param  callable(): array<int, PlanRow>  $replan  recalcula el plan con la base ya bloqueada
     * @param  array<string, string>  $archivedBackups
     */
    public function apply(
        CarbonImmutable $cutoff,
        array $preflightRows,
        callable $replan,
        string $userId,
        array $archivedBackups = [],
    ): RebaselineResult {
        $reasonId = $this->reasonId();

        return DB::transaction(function () use ($cutoff, $preflightRows, $replan, $userId, $reasonId, $archivedBackups) {
            $locked = [];
            $this->lockAffectedInventory($preflightRows, $locked);

            $valueBefore = $this->totalInventoryValue();

            $deleted = $this->purgeAdjustments();

            /** @var array<int, PlanRow> $rows */
            $rows = $replan();

            $this->assertNoBlockingRows($rows);

            $writable = array_values(array_filter($rows, fn (PlanRow $row) => $row->isWritable()));
            $skipped = array_values(array_filter(
                $rows,
                fn (PlanRow $row) => ! $row->isWritable() && $row->hasChanges(),
            ));

            // Segundo bloqueo: el primero se hizo con los triples del pre-flight
            // y el recálculo pudo añadir alguno. Bloquear ahora el conjunto
            // definitivo garantiza que NINGUNA fila se escriba sin lock, sin
            // tener que abortar por una diferencia que puede ser legítima
            // (p. ej. un triple que solo existía por un movimiento de ajuste).
            $appeared = $this->lockAffectedInventory($writable, $locked);

            $backedUp = $this->backupInventoryRows($writable);
            $ledger = $this->writeLedger($writable, $cutoff, $userId, $reasonId);
            $physical = $this->writePhysicalStock($writable);

            $checks = $this->verifyAgainstFile($writable, $cutoff);

            return new RebaselineResult(
                triplesProcessed: count($writable),
                movementsCreated: $ledger['movements'],
                adjustmentsCreated: $ledger['adjustments'],
                oldAdjustmentsDeleted: $deleted['adjustments'],
                oldAdjustmentMovementsDeleted: $deleted['movements'],
                inventoryRowsBackedUp: $backedUp,
                inventoryRowsDeleted: $physical['deleted'],
                inventoryRowsCreated: $physical['created'],
                triplesEmptied: $physical['emptied'],
                rowsWithoutPrice: $physical['without_price'],
                valueBefore: $valueBefore,
                valueAfter: $this->totalInventoryValue(),
                checksRun: $checks,
                appliedRows: $rows,
                skippedRows: $skipped,
                archivedBackups: $archivedBackups,
                triplesAppearedAfterReplan: count($appeared),
            );
        });
    }

    // -------------------------------------------------------------- respaldos

    private function ensureBackupTable(string $backup, string $source): void
    {
        // CREATE TABLE ... LIKE copia columnas, tipos, COTEJO e índices, y NO
        // copia las claves foráneas: es justo lo que se quiere en un respaldo
        // (las filas respaldadas no deben depender de que el original siga ahí).
        DB::statement("CREATE TABLE IF NOT EXISTS `{$backup}` LIKE `{$source}`");

        if (! Schema::hasColumn($backup, 'backed_up_at')) {
            DB::statement("ALTER TABLE `{$backup}` ADD COLUMN `backed_up_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP");
        }
    }

    private function archiveBackupTable(string $backup): string
    {
        $archive = $backup . '_' . CarbonImmutable::now()->format('Ymd_His');

        DB::statement("RENAME TABLE `{$backup}` TO `{$archive}`");

        return $archive;
    }

    /**
     * Copia a la tabla de respaldo las filas de `inventory` de los triples que
     * se van a reescribir. Se hace ANTES de borrar una sola fila.
     *
     * @param  array<int, PlanRow>  $rows
     */
    private function backupInventoryRows(array $rows): int
    {
        $columns = $this->columnList('inventory');
        $total = 0;

        foreach (array_chunk($this->triples($rows), self::CHUNK) as $chunk) {
            [$sql, $bindings] = $this->tupleFilter($chunk);

            $total += DB::affectingStatement(
                "INSERT INTO `inventory_rebaseline_backup` ({$columns}, `backed_up_at`)
                 SELECT {$columns}, NOW() FROM `inventory` WHERE {$sql}",
                $bindings,
            );
        }

        return $total;
    }

    // ------------------------------------------------------ borrado de ajustes

    /**
     * Elimina TODOS los ajustes y sus movimientos (reglas v3, "corte de agosto").
     *
     * Se respalda antes de borrar. Se borran primero los movimientos y después
     * las cabeceras para no dejar, ni por un instante dentro de la transacción,
     * un movimiento apuntando a un documento inexistente.
     *
     * Al ejecutarse ANTES del recálculo, deja `A` (delta de agosto) limpio de
     * ajustes sin que el cálculo tenga que saber nada de ellos. Y en una
     * re-corrida con `--force` borra también los ajustes REBASE-JUL26- de la
     * corrida anterior, que es lo que hace que aplicar dos veces dé el mismo
     * resultado que aplicar una.
     *
     * @return array{adjustments: int, movements: int}
     */
    private function purgeAdjustments(): array
    {
        $movementColumns = $this->columnList('inventory_movements');
        $adjustmentColumns = $this->columnList('adjustments');

        DB::affectingStatement(
            "INSERT INTO `inventory_movements_rebaseline_backup` ({$movementColumns}, `backed_up_at`)
             SELECT {$movementColumns}, NOW() FROM `inventory_movements`
             WHERE `related_document_type` LIKE '%Adjustment%'"
        );

        DB::affectingStatement(
            "INSERT INTO `adjustments_rebaseline_backup` ({$adjustmentColumns}, `backed_up_at`)
             SELECT {$adjustmentColumns}, NOW() FROM `adjustments`"
        );

        $movements = DB::affectingStatement(
            "DELETE FROM `inventory_movements` WHERE `related_document_type` LIKE '%Adjustment%'"
        );

        $adjustments = DB::affectingStatement('DELETE FROM `adjustments`');

        return ['adjustments' => $adjustments, 'movements' => $movements];
    }

    // ------------------------------------------------------------- escrituras

    /**
     * UN movimiento por triple, fechado al corte, por (J − K31), más la fila de
     * `adjustments` a la que apunta.
     *
     * `type` es 'entry' o 'exit' porque el enum de `inventory_movements` es
     * ('entry','exit','transfer','application'): NO existe 'adjustment'.
     *
     * @param  array<int, PlanRow>  $rows
     * @return array{adjustments: int, movements: int}
     */
    private function writeLedger(array $rows, CarbonImmutable $cutoff, string $userId, string $reasonId): array
    {
        $date = $cutoff->toDateString();
        $now = CarbonImmutable::now();
        $note = 'Ajuste inicial · re-baseline de inventario al ' . $date
            . ' (apertura tomada del archivo del cliente).';

        $adjustments = [];
        $movements = [];
        $sequence = 0;

        foreach ($rows as $row) {
            $delta = $row->ledgerMovement();

            if (abs($delta) <= self::EPSILON) {
                continue;
            }

            $sequence++;

            if ($sequence > 9999) {
                throw new RuntimeException(
                    'El plan necesita más de 9.999 ajustes y el formato ' . self::ADJUSTMENT_PREFIX
                    . '#### solo tiene cuatro dígitos.'
                );
            }

            $isEntry = $delta > 0;
            $type = $isEntry ? 'entry' : 'exit';
            $quantity = round(abs($delta), 2);
            $price = $row->effectivePrice();
            $adjustmentId = (string) Str::orderedUuid();

            $adjustments[] = [
                'id' => $adjustmentId,
                'adjustment_number' => self::ADJUSTMENT_PREFIX . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
                'type' => $type,
                'reason_id' => $reasonId,
                'notes' => $note,
                'product_id' => $row->productId,
                'brand_id' => $row->brandId,
                'unit' => $row->unit,
                // 'delta' y no 'absolute': lo que documenta esta fila es el
                // movimiento del LIBRO (J − K31), no el saldo final.
                'quantity_mode' => 'delta',
                'quantity' => $quantity,
                'quantity_base' => $quantity,
                'origin_location_id' => $isEntry ? null : $row->locationId,
                'destination_location_id' => $isEntry ? $row->locationId : null,
                'batch_number' => self::BASELINE_BATCH,
                'unit_price' => $price,
                'movement_date' => $date,
                'status' => 'approved',
                'responsible_user' => $userId,
                'approved_by' => $userId,
                'approved_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $movements[] = [
                'id' => (string) Str::orderedUuid(),
                'type' => $type,
                'product_id' => $row->productId,
                'brand_id' => $row->brandId,
                'location_id' => $row->locationId,
                'quantity' => $quantity,
                'unit' => $row->unit,
                'movement_date' => $date,
                'expiration_date' => null,
                'unit_price' => $price,
                'total_price' => $price === null ? null : round($quantity * $price, 2),
                'responsible_user' => $userId,
                'related_document_id' => $adjustmentId,
                'related_document_type' => self::MOVEMENT_DOCUMENT_TYPE,
                'observations' => ($isEntry ? self::POSITIVE_TAG : self::NEGATIVE_TAG) . ' ' . $note,
                'created_at' => $now,
            ];
        }

        // Las cabeceras primero: `related_document_id` debe apuntar a una fila
        // que ya exista, aunque no haya clave foránea que lo obligue.
        foreach (array_chunk($adjustments, self::CHUNK) as $chunk) {
            DB::table('adjustments')->insert($chunk);
        }

        foreach (array_chunk($movements, self::CHUNK) as $chunk) {
            DB::table('inventory_movements')->insert($chunk);
        }

        return ['adjustments' => count($adjustments), 'movements' => count($movements)];
    }

    /**
     * Fija el stock físico al valor ABSOLUTO (J + A) en un lote único
     * BASE-JUL-2026, borrando los lotes anteriores del triple.
     *
     * VENCIMIENTO: al fundir varios lotes en uno se conserva el vencimiento MÁS
     * PRÓXIMO de entre los lotes que todavía tenían existencias. Es la misma
     * regla que aplica `InventoryService::increaseBatch()` al mezclar
     * existencias, y por el mismo motivo: quedarse con la fecha más lejana
     * "rejuvenecería" mercancía próxima a vencer, la sacaría de las alertas y la
     * mandaría al fondo del FIFO. Se ignoran los lotes en cero: su fecha ya no
     * corresponde a ninguna existencia y arrastraría vencimientos históricos a
     * la línea base.
     *
     * PRECIO: el del archivo. Si el archivo no lo trae, se conserva el costo
     * actual del triple (`PlanRow::effectivePrice()`), nunca 0: valorar a 0 lo
     * que sí tiene costo destruiría valor de inventario.
     *
     * @param  array<int, PlanRow>  $rows
     * @return array{deleted: int, created: int, emptied: int, without_price: int}
     */
    private function writePhysicalStock(array $rows): array
    {
        $expirations = $this->expirationByTriple($rows);

        $deleted = 0;

        foreach (array_chunk($this->triples($rows), self::CHUNK) as $chunk) {
            [$sql, $bindings] = $this->tupleFilter($chunk);
            $deleted += DB::affectingStatement("DELETE FROM `inventory` WHERE {$sql}", $bindings);
        }

        $now = CarbonImmutable::now();
        $inserts = [];
        $emptied = 0;
        $withoutPrice = 0;

        foreach ($rows as $row) {
            $target = $row->physicalTarget();

            if ($target <= self::EPSILON) {
                $emptied++;

                continue;
            }

            $price = $row->effectivePrice();
            $expiration = $expirations[$row->tripleKey()] ?? null;

            if ($price === null) {
                $withoutPrice++;
            }

            $inserts[] = [
                'id' => (string) Str::orderedUuid(),
                'product_id' => $row->productId,
                'brand_id' => $row->brandId,
                'location_id' => $row->locationId,
                'batch_number' => self::BASELINE_BATCH,
                'quantity' => $target,
                'unit' => $row->unit,
                'expiration_date' => $expiration,
                'unit_price' => $price,
                'total_value' => $price === null ? null : round($target * $price, 2),
                'status' => $this->statusFor($expiration),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($inserts, self::CHUNK) as $chunk) {
            DB::table('inventory')->insert($chunk);
        }

        return [
            'deleted' => $deleted,
            'created' => count($inserts),
            'emptied' => $emptied,
            'without_price' => $withoutPrice,
        ];
    }

    /**
     * Vencimiento heredado por el lote de la línea base: el más próximo entre
     * los lotes del triple que TIENEN existencias.
     *
     * @param  array<int, PlanRow>  $rows
     * @return array<string, string|null>
     */
    private function expirationByTriple(array $rows): array
    {
        $wanted = array_flip(array_map(fn (PlanRow $row) => $row->tripleKey(), $rows));
        $expirations = [];

        $found = DB::table('inventory')
            ->select('product_id', 'brand_id', 'location_id')
            ->selectRaw('MIN(CASE WHEN quantity > 0 THEN expiration_date END) as expiration_date')
            ->groupBy('product_id', 'brand_id', 'location_id')
            ->get();

        foreach ($found as $group) {
            $key = $group->product_id . '|' . $group->brand_id . '|' . $group->location_id;

            if ($group->expiration_date !== null && isset($wanted[$key])) {
                $expirations[$key] = substr((string) $group->expiration_date, 0, 10);
            }
        }

        return $expirations;
    }

    /** Misma clasificación que `InventoryService::calculateStatus()`. */
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

    // ----------------------------------------------------------- verificación

    /**
     * Verificación CONTRA EL ARCHIVO, triple por triple.
     *
     * Deliberadamente NO se compara `inventory` contra `inventory_movements`:
     * las dos las acaba de escribir este mismo código a partir del mismo plan,
     * así que coincidirían aunque el plan estuviera mal. La referencia es `J`
     * (apertura del archivo) y `J + A` (apertura más el agosto que se conserva).
     *
     * @param  array<int, PlanRow>  $rows
     * @return int número de comprobaciones ejecutadas
     */
    private function verifyAgainstFile(array $rows, CarbonImmutable $cutoff): int
    {
        $ledger = $this->ledgerByTriple($cutoff);
        $physical = $this->physicalByTriple();
        $failures = [];
        $checks = 0;

        foreach ($rows as $row) {
            $key = $row->tripleKey();
            $opening = round($row->fileBalance, 2);
            $target = $row->physicalTarget();

            $atCutoff = round((float) ($ledger[$key]->at_cutoff ?? 0.0), 2);
            $today = round((float) ($ledger[$key]->today ?? 0.0), 2);
            $stock = round((float) ($physical[$key] ?? 0.0), 2);

            $checks += 3;

            foreach ([
                ['kardex al corte', $atCutoff, $opening],
                ['kardex hoy', $today, $target],
                ['stock físico', $stock, $target],
            ] as [$concept, $actual, $expected]) {
                if (abs($actual - $expected) > self::TOLERANCE) {
                    $failures[] = sprintf(
                        '%s · %s · %s → %s: %s, esperado %s (dif %s)',
                        $row->productCode,
                        $row->productName,
                        $row->locationName,
                        $concept,
                        number_format($actual, 2),
                        number_format($expected, 2),
                        number_format($actual - $expected, 2),
                    );
                }
            }
        }

        $failures = array_merge($failures, $this->negativeBalances($ledger));

        if ($failures !== []) {
            throw new RuntimeException(
                'La verificación contra el archivo falló en ' . count($failures)
                . ' comprobación(es); se revierte TODO. Detalle:' . PHP_EOL . '  · '
                . implode(PHP_EOL . '  · ', array_slice($failures, 0, 20))
                . (count($failures) > 20 ? PHP_EOL . '  · ... y ' . (count($failures) - 20) . ' más.' : '')
            );
        }

        return $checks;
    }

    /**
     * Salvaguarda global: después del re-baseline no puede quedar UN SOLO saldo
     * negativo, ni en el libro ni en el stock, ni siquiera en triples que el
     * plan no tocó.
     *
     * @param  array<string, object>  $ledger  saldos por triple ya calculados
     * @return array<int, string>
     */
    private function negativeBalances(array $ledger): array
    {
        $failures = [];

        foreach ($ledger as $key => $balance) {
            if ((float) $balance->today < -self::TOLERANCE) {
                $failures[] = "kardex hoy NEGATIVO en el triple {$key}: " . number_format((float) $balance->today, 2);
            }
        }

        $negativeStock = DB::table('inventory')->where('quantity', '<', 0)->count();

        if ($negativeStock > 0) {
            $failures[] = "{$negativeStock} fila(s) de `inventory` con cantidad negativa.";
        }

        return $failures;
    }

    /** @return array<string, object> */
    private function ledgerByTriple(CarbonImmutable $cutoff): array
    {
        $signed = "CASE WHEN type = 'entry' THEN quantity ELSE -quantity END";
        $date = $cutoff->toDateString();

        $rows = DB::table('inventory_movements')
            ->select('product_id', 'brand_id', 'location_id')
            ->selectRaw("SUM(CASE WHEN movement_date <= ? OR movement_date IS NULL THEN ({$signed}) ELSE 0 END) as at_cutoff", [$date])
            ->selectRaw("SUM({$signed}) as today")
            ->groupBy('product_id', 'brand_id', 'location_id')
            ->get();

        $indexed = [];

        foreach ($rows as $row) {
            $indexed[$row->product_id . '|' . $row->brand_id . '|' . $row->location_id] = $row;
        }

        return $indexed;
    }

    /** @return array<string, float> */
    private function physicalByTriple(): array
    {
        $rows = DB::table('inventory')
            ->select('product_id', 'brand_id', 'location_id')
            ->selectRaw('SUM(quantity) as quantity')
            ->groupBy('product_id', 'brand_id', 'location_id')
            ->get();

        $indexed = [];

        foreach ($rows as $row) {
            $indexed[$row->product_id . '|' . $row->brand_id . '|' . $row->location_id] = (float) $row->quantity;
        }

        return $indexed;
    }

    // -------------------------------------------------------------- auxiliares

    /**
     * Bloquea con `FOR UPDATE` las filas de `inventory` de los triples dados,
     * saltándose los que ya se bloquearon en una pasada anterior.
     *
     * Se devuelven por referencia los triples nuevos para que el llamador pueda
     * reportarlos: no son un error (el recálculo puede añadir o quitar alguno),
     * pero sí un dato que conviene ver en la consola.
     *
     * @param  array<int, PlanRow>  $rows
     * @param  array<string, bool>  $locked  triples ya bloqueados; se actualiza
     * @return array<int, string> triples que no estaban bloqueados todavía
     */
    private function lockAffectedInventory(array $rows, array &$locked = []): array
    {
        $pending = [];
        $appeared = [];

        foreach ($this->triples($rows) as $triple) {
            $key = implode('|', $triple);

            if (isset($locked[$key])) {
                continue;
            }

            $pending[] = $triple;
            $appeared[] = $key;
            $locked[$key] = true;
        }

        foreach (array_chunk($pending, self::CHUNK) as $chunk) {
            [$sql, $bindings] = $this->tupleFilter($chunk);
            DB::select("SELECT `id` FROM `inventory` WHERE {$sql} FOR UPDATE", $bindings);
        }

        return $appeared;
    }

    /**
     * Pre-flight bloqueante DENTRO de la transacción: el de fuera se hizo con
     * una foto anterior y el estado pudo cambiar.
     *
     * @param  array<int, PlanRow>  $rows
     */
    private function assertNoBlockingRows(array $rows): void
    {
        $blocked = array_values(array_filter($rows, fn (PlanRow $row) => $row->isBlocked()));

        if ($blocked === []) {
            return;
        }

        $detail = array_map(
            fn (PlanRow $row) => $row->productCode . ' · ' . $row->productName . ' · '
                . $row->locationName . ' → ' . $row->alertLabel(),
            array_slice($blocked, 0, 10),
        );

        throw new RuntimeException(
            'Al recalcular dentro de la transacción aparecieron ' . count($blocked)
            . ' fila(s) con alertas bloqueantes: ' . PHP_EOL . '  · ' . implode(PHP_EOL . '  · ', $detail)
        );
    }

    private function reasonId(): string
    {
        $reason = DB::table('adjustment_reasons')->where('code', self::REASON_CODE)->first();

        if ($reason === null) {
            throw new RuntimeException(
                'No existe el motivo de ajuste "' . self::REASON_CODE . '" en `adjustment_reasons`; '
                . 'ejecute las migraciones antes de aplicar el re-baseline.'
            );
        }

        return (string) $reason->id;
    }

    private function totalInventoryValue(): float
    {
        return round((float) DB::table('inventory')
            ->selectRaw('COALESCE(SUM(total_value), 0) as valor')
            ->value('valor'), 2);
    }

    /**
     * @param  array<int, PlanRow>  $rows
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    private function triples(array $rows): array
    {
        $triples = [];

        foreach ($rows as $row) {
            if ($row->isWritable()) {
                $triples[$row->tripleKey()] = [(string) $row->productId, (string) $row->brandId, (string) $row->locationId];
            }
        }

        return array_values($triples);
    }

    /**
     * Predicado `(product_id, brand_id, location_id) IN ((?,?,?), ...)`.
     *
     * El constructor de filas de MySQL evita tener que emparejar los tres
     * campos con OR anidados, que con cientos de triples generaría una consulta
     * intratable.
     *
     * @param  array<int, array{0: string, 1: string, 2: string}>  $chunk
     * @return array{0: string, 1: array<int, string>}
     */
    private function tupleFilter(array $chunk): array
    {
        $placeholders = implode(', ', array_fill(0, count($chunk), '(?, ?, ?)'));
        $bindings = [];

        foreach ($chunk as $triple) {
            array_push($bindings, $triple[0], $triple[1], $triple[2]);
        }

        return ["(`product_id`, `brand_id`, `location_id`) IN ({$placeholders})", $bindings];
    }

    /** Lista de columnas entrecomillada, para los INSERT ... SELECT del respaldo. */
    private function columnList(string $table): string
    {
        $columns = Schema::getColumnListing($table);

        return implode(', ', array_map(fn (string $column) => "`{$column}`", $columns));
    }
}
