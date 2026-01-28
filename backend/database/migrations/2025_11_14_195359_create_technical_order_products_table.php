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
        Schema::create('technical_order_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('technical_order_id');
            $table->uuid('product_id');
            $table->uuid('brand_id');
            $table->decimal('quantity', 10, 2);
            $table->string('unit', 50);
            $table->text('observations')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Foreign keys
            $table->foreign('technical_order_id')->references('id')->on('technical_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('technical_order_products');
    }
};
