<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Programaciones de tareas agricolas sobre fincas y lotes.
     * Cada programacion tiene cantidad total, fechas, personas planeadas
     * y jornales presupuestados. El avance acumulado y los jornales reales
     * se actualizan a medida que se registran logs diarios.
     * Las tareas ad-hoc (is_ad_hoc=true) se crean en el momento con
     * estimacion automatica del sistema.
     */
    public function up(): void
    {
        Schema::create('task_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 20)->unique();
            $table->uuid('task_catalog_id');
            $table->uuid('location_id');
            $table->uuid('lot_id')->nullable();
            $table->decimal('total_quantity', 12, 2);
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('working_days');
            $table->integer('planned_persons');
            $table->integer('budgeted_jornales');
            $table->decimal('accumulated_pct', 5, 2)->default(0);
            $table->integer('real_jornales')->default(0);

            $table->enum('status', ['planificada', 'en_progreso', 'completada', 'cancelada'])
                ->default('planificada');
            $table->decimal('final_performance_pct', 6, 2)->nullable();
            $table->enum('final_level', ['sobrepaso', 'alto', 'medio', 'bajo'])->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->boolean('is_ad_hoc')->default(false);
            $table->text('ad_hoc_motive')->nullable();

            $table->text('observations')->nullable();
            $table->uuid('created_by');
            $table->timestamps();

            $table->foreign('task_catalog_id')->references('id')->on('task_catalog');
            $table->foreign('location_id')->references('id')->on('locations');
            $table->foreign('lot_id')->references('id')->on('farm_lots')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users');

            $table->index(['location_id', 'status']);
            $table->index(['task_catalog_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_schedules');
    }
};
