<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrección de datos del bug de fechas en la ENTRADA de los traslados.
 *
 * Al recibir una salida de tipo traslado, el controlador no propagaba la fecha
 * del lote a la entrada del destino: la salida en el origen quedaba con la
 * `reception_date` correcta y la entrada en el destino con la fecha de registro
 * (`now()`). Las dos patas del mismo traslado, en fechas distintas.
 *
 * Eso rompe el Inventario Mensual: el stock del origen se calcula con el
 * `movement_date` del `exit`, pero la columna "Enviado a finca X" se deriva del
 * `movement_date` de la `entry` en el destino. Con fechas distintas el envío
 * cae en un mes y el descuento en otro, y "Variación" deja de ser cero.
 *
 * SELECTOR: se usa la FIRMA del bug, evaluada en tiempo de ejecución. No una
 * lista fija de ids (el bug estuvo activo hasta el fix, así que las filas
 * afectadas crecen hasta el momento del despliegue):
 *
 *   - entrada ligada a una Recepción de una salida (`source_type='output'`),
 *   - fechada exactamente con su propia fecha de registro (`DATE(created_at)`),
 *     que es la huella del `now()` que dejaba el default olvidado,
 *   - y con una fecha DISTINTA a la de su `exit` hermano.
 *
 * La corrección es poner la fecha del `exit` hermano, que es la que el usuario
 * escribió al recibir el lote.
 *
 * EMPAREJAMIENTO CON EL HERMANO — por qué incluye el número de lote:
 * (recepción, producto, marca, cantidad, unidad) NO es una clave única. Una
 * recepción parcial del mismo producto en dos lotes con la MISMA cantidad y
 * fechas de salida distintas deja dos `exit` candidatos para cada entrada, y
 * entonces el `UPDATE ... JOIN` de MySQL elige uno arbitrario: la entrada puede
 * quedar con la fecha del OTRO lote (sigue asimétrica) y el respaldo puede
 * registrar un `corrected_movement_date` que no es el que se escribió, o sea un
 * rastro de auditoría falso. Por eso el par se cierra además por el `#N` del
 * lote que `ReceptionController` escribe en `observations` en las dos patas.
 * Se extrae con `SUBSTRING_INDEX` sobre el PRIMER '#'; deliberadamente NO se usa
 * un LIKE sobre el literal acentuado ("Recepción"), que dependería del cotejo y
 * de la codificación de la conexión.
 *
 * Y si un grupo sigue siendo ambiguo (dos `exit` del mismo lote con fechas
 * distintas: dato ya inconsistente de origen), la migración SE ABSTIENE: deja la
 * fila como está y la cuenta en el log. Preferimos una entrada sin corregir,
 * visible en el informe, a una fecha adivinada.
 *
 * El plan se materializa en una tabla temporal ANTES de escribir nada, y tanto
 * el respaldo como el UPDATE leen de ese mismo plan. Así es imposible que el
 * respaldo diga una cosa y la tabla termine con otra.
 *
 * ⚠️ POR QUÉ NO EL CRITERIO OBVIO: comparar la entrada contra
 * `reception_batches.reception_date` parece equivalente y NO lo es. Medido sobre
 * una copia de producción, ese criterio selecciona 261 filas, de las cuales 213
 * están en MAYO de 2026 — movimientos que ya se alinearon a mano al cierre
 * contable del cliente (Siigo) y que volver a mover destruiría ese cierre.
 * El criterio por firma selecciona 38 filas, TODAS de julio de 2026.
 *
 * Cinturón de seguridad adicional: el WHERE excluye explícitamente cualquier
 * movimiento con `movement_date <= '2026-05-31'`. Mayo está cerrado y ninguna
 * corrección automática debe tocarlo, ni siquiera si la firma coincidiera.
 * La exclusión se aplica a las DOS patas: si la fecha destino cayera en mayo o
 * antes, la fila se deja como está en vez de meter movimiento en un mes ya
 * conciliado. Preferimos una entrada sin corregir a un cierre contable alterado
 * por una migración.
 *
 * CRITERIOS DE ABORTO VÁLIDOS al desplegar. El selector es dinámico A PROPÓSITO,
 * así que **ningún conteo fijo sirve como criterio de aborto**: exigir "38 filas"
 * abortaría una corrida legítima (el número depende de cuántos traslados se
 * recibieron antes del despliegue). Los dos criterios verificables son:
 *
 *   1. CERO filas con `movement_date <= '2026-05-31'` modificadas.
 *   2. CERO filas aterrizando en `movement_date <= '2026-05-31'`.
 *
 * Ambos se comprueban comparando contra un respaldo previo de la tabla. Si
 * alguno falla, revertir con `down()` (que restaura desde
 * `inventory_movements_md_backup`) e investigar.
 *
 * Solo se escribe `movement_date`. Ni cantidades, ni unidades, ni precios, ni la
 * tabla `inventory`: el stock actual ya es correcto (las cantidades siempre se
 * movieron bien), lo único mal era en qué mes caía cada movimiento.
 *
 * Idempotente: el respaldo usa INSERT IGNORE (conserva el valor ORIGINAL de la
 * primera pasada) y, tras corregir, ninguna fila cumple la firma, así que
 * re-ejecutar deja el mismo estado.
 */
return new class extends Migration
{
    /** Último día del mes ya conciliado con contabilidad: intocable. */
    private const CLOSED_THROUGH = '2026-05-31';

    private const REASON = 'E3E4_entry_transfer';

    private const BACKUP_TABLE = 'inventory_movements_md_backup';

    /** Plan de corrección: fuente única del respaldo y del UPDATE. */
    private const PLAN_TABLE = 'tmp_md_realign_plan';

    public function up(): void
    {
        $this->ensureBackupTable();
        $this->buildPlan();

        $summary = [
            'planificadas' => DB::table(self::PLAN_TABLE)->count(),
            'ambiguas_omitidas' => $this->countAmbiguousEntries(),
            'sin_marcador_de_lote' => $this->countEntriesWithoutBatchMarker(),
            'respaldadas' => $this->backupPlannedRows(),
            'corregidas' => $this->applyPlan(),
        ];

        $this->dropPlan();
        $this->report($summary);
    }

    public function down(): void
    {
        // Restaura la fecha original desde el respaldo. La tabla de respaldo NO
        // se borra: es la única evidencia de qué se corrigió y con qué valor.
        $restored = DB::affectingStatement("
            UPDATE inventory_movements im
            JOIN " . self::BACKUP_TABLE . " b
                ON b.movement_id = im.id
               AND b.reason = '" . self::REASON . "'
            SET im.movement_date = b.original_movement_date
            WHERE b.original_movement_date IS NOT NULL
              AND im.movement_date <> b.original_movement_date
        ");

        $this->report(['revertidas' => $restored]);
    }

    /**
     * El `CREATE TABLE` va detrás de un `hasTable` a propósito: en MySQL
     * cualquier DDL fuerza un COMMIT implícito, y eso rompería la transacción de
     * las pruebas (y con ella su aislamiento). Si la tabla ya existe —el caso
     * normal, porque esta misma migración la creó— no se emite DDL alguno.
     */
    private function ensureBackupTable(): void
    {
        if (Schema::hasTable(self::BACKUP_TABLE)) {
            return;
        }

        DB::statement("
            CREATE TABLE " . self::BACKUP_TABLE . " (
                movement_id CHAR(36) NOT NULL PRIMARY KEY,
                original_movement_date DATE NULL,
                corrected_movement_date DATE NULL,
                reason VARCHAR(50) NULL,
                backed_up_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    /**
     * Resuelve qué entrada va a qué fecha, y descarta lo irresoluble.
     * `HAVING COUNT(DISTINCT x.movement_date) = 1` es la abstención: si el lote
     * ofrece más de una fecha candidata, la entrada no entra al plan.
     */
    private function buildPlan(): void
    {
        $this->dropPlan();

        DB::statement("
            CREATE TEMPORARY TABLE " . self::PLAN_TABLE . " (
                movement_id CHAR(36) NOT NULL PRIMARY KEY,
                original_movement_date DATE NULL,
                corrected_movement_date DATE NULL
            )
        ");

        DB::statement("
            INSERT INTO " . self::PLAN_TABLE . "
                (movement_id, original_movement_date, corrected_movement_date)
            SELECT e.id, e.movement_date, MIN(x.movement_date)
            FROM inventory_movements e
            " . $this->signatureJoins() . "
            WHERE " . $this->signatureConditions() . "
            GROUP BY e.id, e.movement_date
            HAVING COUNT(DISTINCT x.movement_date) = 1
        ");
    }

    private function dropPlan(): void
    {
        // TEMPORARY no dispara COMMIT implícito (a diferencia de DROP TABLE).
        DB::statement('DROP TEMPORARY TABLE IF EXISTS ' . self::PLAN_TABLE);
    }

    private function countAmbiguousEntries(): int
    {
        $row = DB::selectOne("
            SELECT COUNT(*) AS total FROM (
                SELECT e.id
                FROM inventory_movements e
                " . $this->signatureJoins() . "
                WHERE " . $this->signatureConditions() . "
                GROUP BY e.id
                HAVING COUNT(DISTINCT x.movement_date) > 1
            ) ambiguas
        ");

        return (int) ($row->total ?? 0);
    }

    /**
     * Diagnóstico: entradas con la huella del bug cuyo `observations` no trae un
     * `#N` legible. Quedan fuera del emparejamiento por lote, así que conviene
     * que el despliegue las vea en el log en vez de que desaparezcan calladas.
     */
    private function countEntriesWithoutBatchMarker(): int
    {
        $row = DB::selectOne("
            SELECT COUNT(*) AS total
            FROM inventory_movements e
            JOIN receptions r ON r.id = e.related_document_id
            WHERE e.type = 'entry'
              AND e.related_document_type = 'App\\\\Models\\\\Reception'
              AND r.source_type = 'output'
              AND e.movement_date = DATE(e.created_at)
              AND e.movement_date > '" . self::CLOSED_THROUGH . "'
              AND NOT (" . $this->hasBatchMarker('e') . ")
        ");

        return (int) ($row->total ?? 0);
    }

    private function backupPlannedRows(): int
    {
        return DB::affectingStatement("
            INSERT IGNORE INTO " . self::BACKUP_TABLE . "
                (movement_id, original_movement_date, corrected_movement_date, reason)
            SELECT p.movement_id, p.original_movement_date, p.corrected_movement_date,
                   '" . self::REASON . "'
            FROM " . self::PLAN_TABLE . " p
        ");
    }

    private function applyPlan(): int
    {
        return DB::affectingStatement("
            UPDATE inventory_movements im
            JOIN " . self::PLAN_TABLE . " p ON p.movement_id = im.id
            SET im.movement_date = p.corrected_movement_date
            WHERE im.movement_date <> p.corrected_movement_date
              AND im.movement_date > '" . self::CLOSED_THROUGH . "'
              AND p.corrected_movement_date > '" . self::CLOSED_THROUGH . "'
        ");
    }

    /**
     * Recepción de origen y `exit` hermano: el par que generó la MISMA línea del
     * MISMO lote. Producto, marca, unidad y cantidad porque el controlador crea
     * las dos patas con esos valores idénticos; el número de lote porque sin él
     * el par no es único (ver cabecera).
     */
    private function signatureJoins(): string
    {
        return "
            JOIN receptions r
                ON r.id = e.related_document_id
            JOIN inventory_movements x
                ON x.related_document_id = e.related_document_id
               AND x.related_document_type = e.related_document_type
               AND x.product_id = e.product_id
               AND x.brand_id = e.brand_id
               AND x.quantity = e.quantity
               AND x.unit = e.unit
               AND x.type = 'exit'
               AND " . $this->batchMarker('x') . " = " . $this->batchMarker('e') . "
        ";
    }

    /**
     * Firma del bug. `movement_date = DATE(created_at)` es la parte que
     * distingue "lo fechó now() porque nadie le pasó la fecha" de "el usuario
     * eligió esa fecha a propósito".
     */
    private function signatureConditions(): string
    {
        return "
            e.type = 'entry'
            AND e.related_document_type = 'App\\\\Models\\\\Reception'
            AND r.source_type = 'output'
            AND e.movement_date = DATE(e.created_at)
            AND e.movement_date <> x.movement_date
            AND e.movement_date > '" . self::CLOSED_THROUGH . "'
            AND x.movement_date > '" . self::CLOSED_THROUGH . "'
            AND " . $this->hasBatchMarker('e') . "
            AND " . $this->hasBatchMarker('x') . "
        ";
    }

    /**
     * Número de lote embebido en `observations`. Se toma el texto entre el PRIMER
     * '#' y el siguiente espacio, para que un '#' posterior (p. ej. en el nombre
     * de una ubicación) no desplace la lectura.
     */
    private function batchMarker(string $alias): string
    {
        return "SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX({$alias}.observations, '#', 2), '#', -1), ' ', 1)";
    }

    /** El marcador existe y es un número: si no, la fila no se empareja. */
    private function hasBatchMarker(string $alias): string
    {
        return "({$alias}.observations IS NOT NULL"
            . " AND LOCATE('#', {$alias}.observations) > 0"
            . " AND " . $this->batchMarker($alias) . " REGEXP '^[0-9]+$')";
    }

    /**
     * Una migración correctiva que no dice cuánto reparó es indistinguible de una
     * que no reparó nada. Los conteos van al log de la aplicación (que es el log
     * del despliegue) y a la consola, salvo en pruebas.
     */
    private function report(array $summary): void
    {
        \Log::info('realign_transfer_entry_movement_dates', $summary);

        if (app()->environment('testing')) {
            return;
        }

        $detail = collect($summary)->map(fn ($v, $k) => "{$k}={$v}")->implode(' ');
        fwrite(STDOUT, "  realineación de fechas de entrada de traslados: {$detail}" . PHP_EOL);
    }
};
