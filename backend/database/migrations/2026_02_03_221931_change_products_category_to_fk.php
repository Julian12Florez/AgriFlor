<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar columna category_id si no existe
        if (!Schema::hasColumn('products', 'category_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->uuid('category_id')->nullable()->after('brand_id');
            });
        }

        // 2. Migrar datos existentes usando el slug (solo si category ENUM existe)
        if (Schema::hasColumn('products', 'category')) {
            DB::statement("
                UPDATE products p
                SET category_id = (
                    SELECT c.id FROM categories c WHERE c.slug = p.category
                )
                WHERE p.category IS NOT NULL AND p.category_id IS NULL
            ");
        }

        // 3. Agregar FK si no existe (verificamos si ya tiene constraint)
        $hasFk = DB::select("
            SELECT COUNT(*) as cnt FROM information_schema.table_constraints
            WHERE table_name = 'products'
            AND constraint_type = 'FOREIGN KEY'
            AND constraint_name LIKE '%category_id%'
        ");

        if (empty($hasFk) || $hasFk[0]->cnt == 0) {
            Schema::table('products', function (Blueprint $table) {
                // Solo agregar FK si no existe
                $table->foreign('category_id')->references('id')->on('categories')->onDelete('restrict');
            });
        }

        // Agregar índice si no existe
        $hasIndex = DB::select("
            SELECT COUNT(*) as cnt FROM information_schema.statistics
            WHERE table_name = 'products'
            AND index_name = 'products_category_id_index'
        ");

        if (empty($hasIndex) || $hasIndex[0]->cnt == 0) {
            Schema::table('products', function (Blueprint $table) {
                $table->index('category_id');
            });
        }

        // 4. Eliminar columna category (ENUM) si todavía existe
        if (Schema::hasColumn('products', 'category')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }
    }

    public function down(): void
    {
        // Revertir: agregar ENUM, copiar datos, eliminar FK
        Schema::table('products', function (Blueprint $table) {
            $table->enum('category', ['fertilizante', 'pesticida', 'herbicida', 'fungicida'])->nullable()->after('brand_id');
        });

        DB::statement("
            UPDATE products p
            SET category = (
                SELECT c.slug FROM categories c WHERE c.id = p.category_id
            )
        ");

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropIndex(['category_id']);
            $table->dropColumn('category_id');
        });
    }
};
