<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('packaging_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->decimal('base_quantity', 10, 2);
            $table->enum('base_unit', ['kg', 'litros', 'unidades']);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packaging_units');
    }
};
