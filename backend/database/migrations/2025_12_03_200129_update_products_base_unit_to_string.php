<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Cambiar de enum a string (nullable)
        DB::statement("ALTER TABLE products MODIFY COLUMN base_unit VARCHAR(20) NULL");

        // Actualizar valores existentes para usar símbolos correctos de base_units
        DB::table('products')
            ->where('base_unit', 'litros')
            ->update(['base_unit' => 'L']);

        DB::table('products')
            ->where('base_unit', 'unidades')
            ->update(['base_unit' => 'unidades']); // mantener como está

        // kg ya está correcto

        // Agregar foreign key a base_units si no existe
        $foreignKeys = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND CONSTRAINT_NAME = 'products_base_unit_foreign'");
        if (empty($foreignKeys)) {
            Schema::table('products', function (Blueprint $table) {
                $table->foreign('base_unit')->references('symbol')->on('base_units')->onDelete('restrict');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remover foreign key si existe
        $foreignKeys = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND CONSTRAINT_NAME = 'products_base_unit_foreign'");
        if (!empty($foreignKeys)) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropForeign(['base_unit']);
            });
        }

        // Actualizar valores a los originales
        DB::table('products')
            ->where('base_unit', 'L')
            ->update(['base_unit' => 'litros']);

        // Cambiar de string a enum
        DB::statement("ALTER TABLE products MODIFY COLUMN base_unit ENUM('kg', 'litros', 'unidades') NOT NULL");
    }
};
