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
        Schema::create('reception_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reception_id');
            $table->uuid('product_id');
            $table->uuid('brand_id');
            $table->decimal('quantity_expected', 10, 2);
            $table->decimal('quantity_received', 10, 2)->default(0);
            $table->decimal('quantity_pending', 10, 2)->default(0);
            $table->string('unit', 50);
            $table->date('expiration_date')->nullable();
            $table->enum('condition', ['good', 'damaged', 'expired'])->nullable();
            $table->text('observations')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Foreign keys
            $table->foreign('reception_id')->references('id')->on('receptions')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reception_items');
    }
};
