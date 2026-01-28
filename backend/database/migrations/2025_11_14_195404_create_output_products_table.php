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
        Schema::create('output_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('output_id');
            $table->uuid('product_id');
            $table->uuid('brand_id');
            $table->decimal('quantity_requested', 10, 2);
            $table->decimal('quantity_delivered', 10, 2);
            $table->string('unit', 50);
            $table->string('batch_number', 100)->nullable();
            $table->date('expiration_date')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Foreign keys
            $table->foreign('output_id')->references('id')->on('product_outputs')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('output_products');
    }
};
