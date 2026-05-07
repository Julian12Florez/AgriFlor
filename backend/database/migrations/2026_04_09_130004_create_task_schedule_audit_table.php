<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Auditoria de ajustes manuales en programaciones.
     * Se usa principalmente para registrar cuando un supervisor ajusta
     * manualmente el presupuesto (jornales o personas) de una tarea ad-hoc
     * con estimacion automatica, o cuando cambia campos sensibles de una
     * programacion en progreso.
     */
    public function up(): void
    {
        Schema::create('task_schedule_audit', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('task_schedule_id');
            $table->string('field_changed');
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->text('reason')->nullable();
            $table->uuid('changed_by');
            $table->timestamp('changed_at');

            $table->foreign('task_schedule_id')->references('id')->on('task_schedules')
                ->cascadeOnDelete();
            $table->foreign('changed_by')->references('id')->on('users');

            $table->index('task_schedule_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_schedule_audit');
    }
};
