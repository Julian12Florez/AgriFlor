<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalogo de tareas agricolas (Podas, Plateo, Guadaña, Fumigacion, etc.)
     * Cada tarea define su unidad base, rendimiento de referencia y,
     * opcionalmente, overrides de la configuracion global de rendimiento.
     */
    public function up(): void
    {
        Schema::create('task_catalog', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->enum('unit', ['arbol', 'hectarea', 'm2', 'metro']);
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->decimal('reference_yield', 10, 2)->nullable()
                ->comment('Rendimiento de referencia: unidades por jornal');
            $table->boolean('active')->default(true);

            // Overrides opcionales de configuracion global
            $table->decimal('override_sobrepaso_pct', 5, 2)->nullable();
            $table->decimal('override_alto_pct', 5, 2)->nullable();
            $table->decimal('override_medio_pct', 5, 2)->nullable();
            $table->decimal('override_k_factor', 4, 2)->nullable();

            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['active', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_catalog');
    }
};
