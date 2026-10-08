<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Foto legible del documento en cada registro de auditoría.
 *
 * Hasta hoy el resumen ("Compra PUR-x — 2 Galón SPORTAK…") se armaba con el
 * documento ACTUAL: un registro de hace un mes mostraba los productos de hoy,
 * y si el documento se había eliminado quedaba solo "Compra". La foto guarda,
 * ya en palabras, lo que había EN EL MOMENTO de la acción: encabezado, líneas
 * (producto, marca, cantidad, unidad) y los nombres de lo que se referencia
 * por ID, para que el registro se siga leyendo aunque después se borre el
 * proveedor o el producto.
 *
 * Nullable a propósito: los registros que ya existen no tienen foto y la
 * pantalla los sigue mostrando como siempre.
 *
 * La auditoría guarda en caché si la columna existe (para no preguntarle al
 * esquema en cada petición): esta migración borra esa caché al aplicarse y
 * al revertirse.
 *
 * @see \App\Support\Auditoria\FotoDeAuditoria
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = config('audit.drivers.database.connection', config('database.default'));
        $table = config('audit.drivers.database.table', 'audits');

        if (!Schema::connection($connection)->hasColumn($table, 'snapshot')) {
            Schema::connection($connection)->table($table, function (Blueprint $table) {
                $table->json('snapshot')->nullable()->after('new_values');
            });
        }

        $this->olvidarCache();
    }

    public function down(): void
    {
        $connection = config('audit.drivers.database.connection', config('database.default'));
        $table = config('audit.drivers.database.table', 'audits');

        if (Schema::connection($connection)->hasColumn($table, 'snapshot')) {
            Schema::connection($connection)->table($table, function (Blueprint $table) {
                $table->dropColumn('snapshot');
            });
        }

        $this->olvidarCache();
    }

    /** Ver App\Support\Auditoria\AuditoriaDeLaPeticion::hayColumnaDeFoto(). */
    private function olvidarCache(): void
    {
        try {
            Cache::forget('auditoria.audits_tiene_snapshot');
        } catch (\Throwable $e) {
            // Sin caché disponible no hay nada que olvidar.
        }
    }
};
