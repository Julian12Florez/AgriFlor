<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agrega campos de capacidad multiple a farm_lots para soportar el modulo
     * de Rendimiento de Tareas Agricolas. Un mismo lote puede tener
     * simultaneamente capacidades en arboles, hectareas, metros cubicos (riego)
     * y metros lineales (cercas, vias), porque las tareas agricolas usan
     * distintas unidades aunque apliquen sobre el mismo lote.
     */
    public function up(): void
    {
        Schema::table('farm_lots', function (Blueprint $table) {
            $table->decimal('total_trees', 10, 0)->nullable()->after('area');
            $table->decimal('area_hectares', 10, 2)->nullable()->after('total_trees');
            $table->decimal('total_cubic_meters', 12, 2)->nullable()->after('area_hectares');
            $table->decimal('total_linear_meters', 10, 2)->nullable()->after('total_cubic_meters');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('farm_lots', function (Blueprint $table) {
            $table->dropColumn([
                'total_trees',
                'area_hectares',
                'total_cubic_meters',
                'total_linear_meters',
            ]);
        });
    }
};
