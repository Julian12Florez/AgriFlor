<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Singleton de configuracion global del modulo de Rendimiento.
     * Contiene los umbrales por defecto de los 4 niveles y el factor K
     * de deteccion de avance sospechoso. Cada tarea del catalogo puede
     * sobrescribir estos valores via columnas override_*.
     */
    public function up(): void
    {
        Schema::create('performance_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('global_sobrepaso_pct', 5, 2)->default(130);
            $table->decimal('global_alto_pct', 5, 2)->default(100);
            $table->decimal('global_medio_pct', 5, 2)->default(80);
            $table->decimal('global_k_factor', 4, 2)->default(3);
            $table->timestamps();
        });

        // Seed singleton row
        \DB::table('performance_settings')->insert([
            'global_sobrepaso_pct' => 130,
            'global_alto_pct' => 100,
            'global_medio_pct' => 80,
            'global_k_factor' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_settings');
    }
};
