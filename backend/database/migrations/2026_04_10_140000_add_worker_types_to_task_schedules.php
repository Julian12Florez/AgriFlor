<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Soporta 3 tipos de trabajadores en programaciones:
     * - planned_persons (existente): trabajadores propios de la finca
     * - external_farm_workers (nuevo): trabajadores prestados de otra finca
     * - third_party_workers (nuevo): trabajadores terceros (contratistas)
     *
     * Ahora ningun campo individual es obligatorio, pero la suma debe ser >= 1.
     * Hacer planned_persons nullable para permitir tareas solo con externos/terceros.
     */
    public function up(): void
    {
        Schema::table('task_schedules', function (Blueprint $table) {
            $table->integer('external_farm_workers')->default(0)->after('planned_persons');
            $table->integer('third_party_workers')->default(0)->after('external_farm_workers');
        });

        // Hacer planned_persons nullable
        DB::statement('ALTER TABLE task_schedules MODIFY planned_persons INT NULL');
    }

    public function down(): void
    {
        Schema::table('task_schedules', function (Blueprint $table) {
            $table->dropColumn(['external_farm_workers', 'third_party_workers']);
        });
        DB::statement('ALTER TABLE task_schedules MODIFY planned_persons INT NOT NULL');
    }
};
