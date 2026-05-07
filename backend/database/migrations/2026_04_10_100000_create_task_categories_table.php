<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('color', 20)->nullable()->comment('Color hex para badges');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Migrar categorias existentes de task_catalog.category (string) a la nueva tabla
        $existing = DB::table('task_catalog')
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category');

        $colors = [
            'Establecimiento' => '#8B4513',
            'Mantenimiento' => '#2196F3',
            'Fitosanitario' => '#F44336',
            'Infraestructura' => '#607D8B',
            'Nutricion' => '#4CAF50',
            'Monitoreo' => '#FF9800',
            'Cosecha' => '#9C27B0',
            'Riego' => '#00BCD4',
        ];

        foreach ($existing as $cat) {
            DB::table('task_categories')->insert([
                'id' => \Illuminate\Support\Str::uuid(),
                'name' => $cat,
                'color' => $colors[$cat] ?? '#1890ff',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Agregar FK a task_catalog
        Schema::table('task_catalog', function (Blueprint $table) {
            $table->uuid('category_id')->nullable()->after('category');
        });

        // Actualizar FKs
        $categories = DB::table('task_categories')->pluck('id', 'name');
        foreach ($categories as $name => $id) {
            DB::table('task_catalog')
                ->where('category', $name)
                ->update(['category_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('task_catalog', function (Blueprint $table) {
            $table->dropColumn('category_id');
        });
        Schema::dropIfExists('task_categories');
    }
};
