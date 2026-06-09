<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrección de arranque (una sola vez):
 *
 * Al empezar a usar el sistema se cargaron operaciones de MAYO durante JUNIO.
 * Los movimientos de inventario se fechan con la fecha de registro (created_at = now),
 * por lo que esas operaciones quedaron en junio y el Inventario Mensual de mayo salía
 * corto. Verificado: ~461 movimientos relacionados a recepciones quedaron mal fechados.
 *
 * Esta migración re-fecha cada movimiento ligado a una Recepción a la fecha de su
 * DOCUMENTO de negocio (compra → purchase_date; salida/transferencia → output_date),
 * conservando la hora original. Es determinista e idempotente (re-ejecutar deja la
 * misma fecha). NO cambia cantidades ni el stock actual: solo corrige en qué mes cae
 * cada movimiento. Probado en clon de prod: el cierre de mayo pasó a coincidir con el
 * conteo físico del cliente en 324/357 productos (el resto son errores de captura).
 *
 * Los movimientos de carga inicial ("Ajuste inicial", related_document_type NULL) ya
 * están fechados en mayo y NO se tocan.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Respaldo del created_at original (reversibilidad/auditoría) antes de re-fechar.
        DB::statement("
            CREATE TABLE IF NOT EXISTS inventory_movements_date_backup (
                movement_id CHAR(36) PRIMARY KEY,
                original_created_at TIMESTAMP NULL,
                backed_up_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        DB::statement("
            INSERT IGNORE INTO inventory_movements_date_backup (movement_id, original_created_at)
            SELECT im.id, im.created_at
            FROM inventory_movements im
            WHERE im.related_document_type = 'App\\\\Models\\\\Reception'
        ");

        DB::statement("
            UPDATE inventory_movements im
            JOIN receptions r
                ON r.id = im.related_document_id
               AND im.related_document_type = 'App\\\\Models\\\\Reception'
            LEFT JOIN purchases pu
                ON pu.id = r.source_id AND r.source_type = 'purchase'
            LEFT JOIN product_outputs po
                ON po.id = r.source_id AND r.source_type = 'output'
            SET im.created_at = TIMESTAMP(COALESCE(pu.purchase_date, po.output_date), TIME(im.created_at))
            WHERE im.related_document_type = 'App\\\\Models\\\\Reception'
              AND COALESCE(pu.purchase_date, po.output_date) IS NOT NULL
        ");
    }

    public function down(): void
    {
        // Restaura el created_at original desde el respaldo (si existe).
        DB::statement("
            UPDATE inventory_movements im
            JOIN inventory_movements_date_backup b ON b.movement_id = im.id
            SET im.created_at = b.original_created_at
        ");
    }
};
