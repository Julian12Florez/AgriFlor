<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar columna category_id (nullable temporalmente)
        Schema::table('products', function (Blueprint $table) {
            $table->uuid('category_id')->nullable()->after('brand_id');
        });

        // 2. Migrar datos existentes usando el slug
        DB::statement("
            UPDATE products p
            SET category_id = (
                SELECT c.id FROM categories c WHERE c.slug = p.category
            )
            WHERE p.category IS NOT NULL
        ");

        // 3. Hacer category_id NOT NULL y agregar FK
        Schema::table('products', function (Blueprint $table) {
            $table->uuid('category_id')->nullable(false)->change();
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('restrict');
            $table->index('category_id');
        });

        // 4. Eliminar columna category (ENUM)
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('category');
        });
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
