<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de respaldo de `inventory` para la reparación de entradas en finca
 * (`inventario:reponer-entradas-finca`, FRENTE D).
 *
 * POR QUÉ ES UNA MIGRACIÓN Y NO DDL DENTRO DEL COMANDO
 * ----------------------------------------------------
 * El re-baseline crea sus tablas de respaldo al vuelo, y en producción funciona.
 * Aquí no sirve: en MySQL cualquier DDL fuerza un COMMIT implícito, y
 * `RefreshDatabase` envuelve cada prueba en una transacción. Un
 * `CREATE TABLE` dentro del comando la cierra a media prueba y contamina las
 * siguientes. Este comando escribe 189 filas sobre datos reales y tiene que
 * poder probarse, así que el respaldo pasa a ser parte del esquema: versionado,
 * revisable y sin DDL en tiempo de ejecución.
 *
 * `CREATE TABLE ... LIKE` copia columnas, tipos, cotejo e índices, y NO copia
 * las claves foráneas: es justo lo que se quiere en un respaldo (sus filas no
 * deben depender de que el original siga ahí).
 */
return new class extends Migration
{
    private const BACKUP = 'inventory_farm_backfill_backup';

    public function up(): void
    {
        // La tabla puede existir ya: en producción la creó el propio comando en
        // caliente antes de que esta migración se versionara, y de ese modo
        // conserva el índice ÚNICO que aquí estorba. Por eso el índice se revisa
        // SIEMPRE, exista la tabla o no.
        if (!Schema::hasTable(self::BACKUP)) {
            DB::statement('CREATE TABLE `' . self::BACKUP . '` LIKE `inventory`');
            DB::statement(
                'ALTER TABLE `' . self::BACKUP . '` ADD COLUMN `backed_up_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP'
            );
        }

        // `inventory` tiene un índice ÚNICO sobre
        // (product_id, brand_id, location_id, batch_number). En el respaldo
        // estorba: archivar dos corridas del mismo triple es legítimo. Se
        // conservan los índices normales, que es lo que acelera el rollback.
        $unique = 'inventory_product_id_brand_id_location_id_batch_number_unique';

        foreach (DB::select('SHOW INDEX FROM `' . self::BACKUP . '`') as $index) {
            if ($index->Key_name === $unique) {
                DB::statement('ALTER TABLE `' . self::BACKUP . '` DROP INDEX `' . $unique . '`');
                break;
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(self::BACKUP);
    }
};
