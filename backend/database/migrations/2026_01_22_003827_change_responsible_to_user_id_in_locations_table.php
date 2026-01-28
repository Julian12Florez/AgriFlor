<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * ERR-001: Cambiar campo responsible (string) a responsible_user_id (FK a users)
     * Esto permite:
     * 1. Validar que el encargado sea un usuario real del sistema
     * 2. Filtrar recepciones pendientes por encargado de ubicacion
     * 3. Mantener integridad referencial
     */
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            // Eliminar columna string vieja
            $table->dropColumn('responsible');

            // Agregar nueva columna FK
            $table->uuid('responsible_user_id')->nullable()->after('coordinates_lng');
            $table->foreign('responsible_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            // Eliminar FK y columna nueva
            $table->dropForeign(['responsible_user_id']);
            $table->dropColumn('responsible_user_id');

            // Restaurar columna string vieja
            $table->string('responsible', 255)->nullable()->after('coordinates_lng');
        });
    }
};
