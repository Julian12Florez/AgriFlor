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
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->uuid('brand_id');
            $table->enum('category', ['fertilizante', 'pesticida', 'herbicida', 'fungicida']);
            $table->enum('base_unit', ['kg', 'litros', 'unidades']);
            $table->string('active_ingredient', 255);
            $table->decimal('min_stock', 10, 2)->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('description')->nullable();
            $table->uuid('created_by');
            $table->timestamps();

            // Foreign keys
            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');

            // Indexes
            $table->index('brand_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
