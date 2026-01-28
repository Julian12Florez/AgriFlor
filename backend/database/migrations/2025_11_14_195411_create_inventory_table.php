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
        Schema::create('inventory', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->uuid('brand_id');
            $table->uuid('location_id');
            $table->string('batch_number', 100)->nullable();
            $table->decimal('quantity', 10, 2);
            $table->string('unit', 50);
            $table->date('expiration_date')->nullable();
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->decimal('total_value', 12, 2)->nullable();
            $table->enum('status', ['good', 'low', 'expired', 'near_expiry'])->default('good');
            $table->timestamps();

            // Foreign keys
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('restrict');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('restrict');

            // Unique constraint
            $table->unique(['product_id', 'brand_id', 'location_id', 'batch_number']);

            // Indexes
            $table->index('location_id');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory');
    }
};
