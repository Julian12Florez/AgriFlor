<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Perfiles y permisos, parte 2: los perfiles se administran desde una pantalla.
 *
 * 1. `users.role` deja de ser una lista fija (ENUM) de nombres: el
 *    administrador puede crear perfiles nuevos y asignárselos a un usuario.
 *
 * 2. "Solo ve su finca" pasa a ser una casilla del perfil. Antes eran nombres
 *    escritos en el código:
 *      - User::LOCATION_SCOPED_ROLES = ['supervisor', 'farm']  → `location_scoped`
 *        (inventario, salidas, recepciones y ajustes).
 *      - `farm` en TaskScheduleController / TaskSchedule        → `schedule_scoped`
 *        (programaciones de tareas).
 *    Se marcan exactamente para los perfiles que ya estaban restringidos:
 *    nadie ve más ni menos que antes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 50)->default('warehouse')->change();
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('location_scoped')->default(false)->after('has_full_access');
            $table->boolean('schedule_scoped')->default(false)->after('location_scoped');
        });

        DB::table('roles')->whereIn('name', ['supervisor', 'farm'])->update(['location_scoped' => true]);
        DB::table('roles')->where('name', 'farm')->update(['schedule_scoped' => true]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['location_scoped', 'schedule_scoped']);
        });

        // `users.role` NO vuelve a ENUM: puede haber usuarios con perfiles
        // creados desde la pantalla, y un ENUM los dejaría sin valor válido.
    }
};
