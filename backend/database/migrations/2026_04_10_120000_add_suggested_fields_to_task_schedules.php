<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega campos de estimación sugerida por el sistema al momento de crear
     * la programación. Se guardan para poder comparar en reportes:
     * - suggested_persons: personas que el sistema sugirió según rendimiento de referencia
     * - suggested_working_days: días hábiles necesarios con las personas que el usuario eligió
     * - suggested_end_date: fecha fin calculada con las personas del usuario
     * - reference_yield_used: rendimiento de referencia usado para el cálculo
     */
    public function up(): void
    {
        Schema::table('task_schedules', function (Blueprint $table) {
            $table->integer('suggested_persons')->nullable()->after('planned_persons');
            $table->integer('suggested_working_days')->nullable()->after('suggested_persons');
            $table->date('suggested_end_date')->nullable()->after('suggested_working_days');
            $table->decimal('reference_yield_used', 10, 2)->nullable()->after('suggested_end_date');
        });
    }

    public function down(): void
    {
        Schema::table('task_schedules', function (Blueprint $table) {
            $table->dropColumn(['suggested_persons', 'suggested_working_days', 'suggested_end_date', 'reference_yield_used']);
        });
    }
};
