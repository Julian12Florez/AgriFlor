<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agregar campo IVA a la tabla de productos.
     * Este campo almacena el porcentaje de IVA aplicable al producto.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Agregar campo IVA después de base_unit
            $table->integer('iva')->default(0)->after('base_unit')->comment('Porcentaje de IVA: 0, 5, 16, 19, etc.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('iva');
        });
    }
};
