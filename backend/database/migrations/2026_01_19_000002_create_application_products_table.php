<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabla para registrar los productos individuales aplicados en cada aplicación.
     * Cada aplicación puede tener múltiples productos.
     */
    public function up(): void
    {
        Schema::create('application_products', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Aplicación a la que pertenece este producto
            $table->uuid('application_id');

            // Producto aplicado
            $table->uuid('product_id');

            // Marca del producto
            $table->uuid('brand_id');

            // Cantidad aplicada
            $table->decimal('quantity', 10, 2);

            // Unidad de medida
            $table->string('unit', 50);

            // Área aplicada (en hectáreas u otra unidad)
            $table->decimal('applied_area', 10, 2)->nullable();

            // Unidad de área
            $table->string('area_unit', 20)->nullable()->default('ha'); // hectáreas

            // Dosis aplicada (cantidad por unidad de área)
            $table->decimal('dosage', 10, 4)->nullable();

            // Unidad de dosis (ej: L/ha, kg/ha)
            $table->string('dosage_unit', 50)->nullable();

            // Recepción de origen (para trazabilidad)
            // Permite rastrear de qué recepción provino el producto aplicado
            $table->uuid('reception_id')->nullable();

            // Observaciones específicas del producto
            $table->text('observations')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('application_id')->references('id')->on('applications')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('restrict');
            $table->foreign('reception_id')->references('id')->on('receptions')->onDelete('set null');

            // Indexes
            $table->index('application_id');
            $table->index('product_id');
            $table->index('brand_id');
            $table->index('reception_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_products');
    }
};
