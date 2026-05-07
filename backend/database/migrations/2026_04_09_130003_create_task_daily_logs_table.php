<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registros diarios de avance de una programacion de tarea.
     * Cada log aporta un porcentaje de avance + el numero de personas
     * que trabajaron ese dia. El sistema no clasifica el dia en tiempo real;
     * solo acumula para calcular el rendimiento real al cierre de la tarea.
     * La bandera suspicious se activa cuando el avance diario supera
     * K x ritmo_esperado y requiere confirmacion del supervisor.
     */
    public function up(): void
    {
        Schema::create('task_daily_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('task_schedule_id');
            $table->date('log_date');
            $table->timestamp('registered_at');
            $table->enum('mode', ['programada', 'ad_hoc', 'retroactiva'])
                ->default('programada');
            $table->decimal('advance_pct_today', 5, 2);
            $table->decimal('accumulated_snapshot_pct', 5, 2);
            $table->integer('persons_today');
            $table->boolean('suspicious')->default(false);
            $table->boolean('suspicious_confirmed')->default(false);
            $table->text('observations')->nullable();
            $table->uuid('created_by');
            $table->timestamps();

            $table->foreign('task_schedule_id')->references('id')->on('task_schedules')
                ->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users');

            $table->index(['task_schedule_id', 'log_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_daily_logs');
    }
};
