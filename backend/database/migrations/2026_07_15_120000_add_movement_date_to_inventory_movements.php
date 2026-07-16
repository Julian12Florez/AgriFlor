<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Campo de FECHA REAL DEL MOVIMIENTO (registrada por el usuario, obligatoria).
 *
 * Hasta ahora los movimientos de inventario solo tenían `created_at` (automático = now()),
 * y TODOS los informes de inventario filtraban/agrupaban por ese campo. Eso obligó a una
 * corrección previa (2026_06_09_120000_realign_movement_dates_to_document) que re-fechó
 * ~461 movimientos. Para resolverlo de raíz, se agrega `movement_date`: la fecha del
 * movimiento que el usuario registra en el formulario (compra / entrada / salida /
 * recepción). Los informes pasan a leer este campo, no `created_at`.
 *
 * Backfill: como la corrección previa ya dejó `created_at` alineado a la fecha del
 * documento de negocio, se copia `movement_date = DATE(created_at)` para todo el histórico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->date('movement_date')->nullable()->after('unit');
            $table->index('movement_date');
        });

        // Histórico: created_at ya fue realineado a la fecha del documento.
        DB::statement("
            UPDATE inventory_movements
            SET movement_date = DATE(created_at)
            WHERE movement_date IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex(['movement_date']);
            $table->dropColumn('movement_date');
        });
    }
};
