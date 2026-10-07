<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `final_performance_pct` era DECIMAL(6,2): máximo 9.999,99 %. Finalizar una
 * programación con muchos jornales presupuestados y pocos reales (PROG-0046:
 * 314 contra 6 = 10.466,67 %) terminaba en error 500 y la tarea no se podía
 * cerrar. Con DECIMAL(10,2) cabe cualquier valor posible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_schedules', function (Blueprint $table) {
            $table->decimal('final_performance_pct', 10, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('task_schedules', function (Blueprint $table) {
            $table->decimal('final_performance_pct', 6, 2)->nullable()->change();
        });
    }
};
