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
        // Eliminar duplicados de base_units (mantener solo los oficiales)
        $officialSymbols = ['kg', 'L', 'unidades', 'g', 'mL'];
        DB::table('base_units')
            ->whereNotIn('symbol', $officialSymbols)
            ->delete();

        // Eliminar duplicados de prueba que tienen el mismo símbolo
        $duplicates = DB::table('base_units')
            ->select('symbol', DB::raw('COUNT(*) as count'))
            ->groupBy('symbol')
            ->having('count', '>', 1)
            ->get();

        foreach ($duplicates as $duplicate) {
            // Mantener solo el primero de cada símbolo duplicado
            $keep = DB::table('base_units')
                ->where('symbol', $duplicate->symbol)
                ->orderBy('created_at', 'asc')
                ->first();

            DB::table('base_units')
                ->where('symbol', $duplicate->symbol)
                ->where('id', '!=', $keep->id)
                ->delete();
        }

        // Ahora sí, asegurar que la columna symbol en base_units sea única
        // Verificar si ya existe el índice único antes de agregarlo
        $indexes = DB::select("SHOW INDEX FROM base_units WHERE Key_name = 'base_units_symbol_unique'");
        if (empty($indexes)) {
            Schema::table('base_units', function (Blueprint $table) {
                $table->unique('symbol');
            });
        }

        // Cambiar de enum a string (sin restricciones)
        DB::statement("ALTER TABLE packaging_units MODIFY COLUMN base_unit VARCHAR(20) NOT NULL");

        // Ahora actualizar valores existentes para usar símbolos correctos de base_units
        DB::table('packaging_units')
            ->where('base_unit', 'litros')
            ->update(['base_unit' => 'L']);

        DB::table('packaging_units')
            ->where('base_unit', 'unidades')
            ->update(['base_unit' => 'unidades']); // mantener como está

        // kg ya está correcto

        // Asegurar que 'unidades' existe en base_units antes de agregar foreign key
        $unidadesExists = DB::table('base_units')->where('symbol', 'unidades')->exists();
        if (!$unidadesExists) {
            DB::table('base_units')->insert([
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'name' => 'Unidades',
                'symbol' => 'unidades',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Agregar foreign key a base_units si no existe
        $foreignKeys = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'packaging_units' AND CONSTRAINT_NAME = 'packaging_units_base_unit_foreign'");
        if (empty($foreignKeys)) {
            Schema::table('packaging_units', function (Blueprint $table) {
                $table->foreign('base_unit')->references('symbol')->on('base_units')->onDelete('restrict');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remover foreign key
        Schema::table('packaging_units', function (Blueprint $table) {
            $table->dropForeign(['base_unit']);
        });

        // Actualizar valores a los originales
        DB::table('packaging_units')
            ->where('base_unit', 'L')
            ->update(['base_unit' => 'litros']);

        // Cambiar de string a enum
        DB::statement("ALTER TABLE packaging_units MODIFY COLUMN base_unit ENUM('kg', 'litros', 'unidades') NOT NULL");

        // Remover unique constraint de symbol en base_units
        Schema::table('base_units', function (Blueprint $table) {
            $table->dropUnique(['symbol']);
        });
    }
};
