<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
 *   - y con una fecha DISTINTA a la de su `exit` hermano (misma recepción,
 *     mismo producto).
 *
 * La corrección es poner la fecha del `exit` hermano, que es la que el usuario
 * escribió al recibir el lote.
 *
 * ⚠️ POR QUÉ NO EL CRITERIO OBVIO: comparar la entrada contra
 * `reception_batches.reception_date` parece equivalente y NO lo es. Medido sobre
 * una copia de producción, ese criterio selecciona 261 filas, de las cuales 213
 * están en MAYO de 2026 — movimientos que ya se alinearon a mano al cierre
 * contable del cliente (Siigo) y que volver a mover destruiría ese cierre.
 * El criterio por firma selecciona 40 filas, TODAS de julio de 2026.
 *
 * Cinturón de seguridad adicional: el WHERE excluye explícitamente cualquier
 * movimiento con `movement_date <= '2026-05-31'`. Mayo está cerrado y ninguna
 * corrección automática debe tocarlo, ni siquiera si la firma coincidiera.
 * La exclusión se aplica a las DOS patas: si la fecha destino cayera en mayo o
 * antes, la fila se deja como está en vez de meter movimiento en un mes ya
 * conciliado. Preferimos una entrada sin corregir (visible en el informe) a un
 * cierre contable alterado por una migración.
 *
 * Solo se escribe `movement_date`. Ni cantidades, ni unidades, ni precios, ni la
 * tabla `inventory`: el stock actual ya es correcto (las cantidades siempre se
 * movieron bien), lo único mal era en qué mes caía cada movimiento.
 *
 * Idempotente: el respaldo usa INSERT IGNORE y, tras la primera pasada, ninguna
 * fila cumple la firma, así que re-ejecutar deja el mismo estado.
 */
return new class extends Migration
{
    /** Último día del mes ya conciliado con contabilidad: intocable. */
    private const CLOSED_THROUGH = '2026-05-31';

    private const REASON = 'E3E4_entry_transfer';

    public function up(): void
    {
        $this->createBackupTable();
        $this->backupAffectedRows();
        $this->realignEntryDates();
    }

    public function down(): void
    {
        // Restaura la fecha original desde el respaldo. La tabla de respaldo NO
        // se borra: es la única evidencia de qué se corrigió y con qué valor.
        DB::statement("
            UPDATE inventory_movements im
            JOIN inventory_movements_md_backup b
                ON b.movement_id = im.id
               AND b.reason = '" . self::REASON . "'
            SET im.movement_date = b.original_movement_date
            WHERE b.original_movement_date IS NOT NULL
        ");
    }

    private function createBackupTable(): void
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS inventory_movements_md_backup (
                movement_id CHAR(36) PRIMARY KEY,
                original_movement_date DATE NULL,
                corrected_movement_date DATE NULL,
                reason VARCHAR(50) NULL,
                backed_up_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    /**
     * Respalda las filas que se van a corregir ANTES de tocarlas, guardando
     * también el valor al que se van a mover (para poder auditar el diff sin
     * volver a calcular la firma).
     */
    private function backupAffectedRows(): void
    {
        DB::statement("
            INSERT IGNORE INTO inventory_movements_md_backup
                (movement_id, original_movement_date, corrected_movement_date, reason)
            SELECT e.id, e.movement_date, x.movement_date, '" . self::REASON . "'
            FROM inventory_movements e
            " . $this->signatureJoins() . "
            WHERE " . $this->signatureConditions()
        );
    }

    private function realignEntryDates(): void
    {
        DB::statement("
            UPDATE inventory_movements e
            " . $this->signatureJoins() . "
            SET e.movement_date = x.movement_date
            WHERE " . $this->signatureConditions()
        );
    }

    /**
     * Recepción de origen y `exit` hermano: el par que generó la MISMA línea de
     * lote. Se empareja además por marca, cantidad y unidad porque el
     * controlador crea las dos patas con esos valores idénticos; sin eso, una
     * recepción de varios lotes tendría varios `exit` candidatos con fechas
     * distintas y el emparejamiento sería ambiguo. Medido sobre una copia de
     * producción: la relación queda 1 a 1 (38 entradas → 38 filas).
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
        ";
    }
};
