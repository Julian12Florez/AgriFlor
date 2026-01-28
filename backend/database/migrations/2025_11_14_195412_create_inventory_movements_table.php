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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('type', ['entry', 'exit', 'transfer', 'application']);
            $table->uuid('product_id');
            $table->uuid('brand_id');
            $table->uuid('location_id');
            $table->decimal('quantity', 10, 2);
            $table->string('unit', 50);
            $table->date('expiration_date')->nullable();
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->decimal('total_price', 12, 2)->nullable();
            $table->uuid('responsible_user');
            $table->uuid('related_document_id')->nullable();
            $table->string('related_document_type', 50)->nullable();
            $table->text('observations')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Foreign keys
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('restrict');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('restrict');
            $table->foreign('responsible_user')->references('id')->on('users')->onDelete('restrict');

            // Indexes
            $table->index('location_id');
            $table->index('product_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
