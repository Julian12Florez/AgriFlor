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
        Schema::create('adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('adjustment_number')->unique();
            $table->enum('type', ['entry', 'exit', 'transfer']);
            $table->uuid('reason_id');
            $table->text('notes')->nullable();
            $table->uuid('product_id');
            $table->uuid('brand_id');
            $table->string('unit');
            $table->enum('quantity_mode', ['delta', 'absolute']);
            $table->decimal('quantity', 12, 2);
            $table->decimal('quantity_base', 12, 2)->nullable();
            $table->uuid('origin_location_id')->nullable();
            $table->uuid('destination_location_id')->nullable();
            $table->string('batch_number')->nullable();
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->date('movement_date');
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->uuid('responsible_user');
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('reason_id')->references('id')->on('adjustment_reasons')->onDelete('restrict');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('restrict');
            $table->foreign('origin_location_id')->references('id')->on('locations')->onDelete('restrict');
            $table->foreign('destination_location_id')->references('id')->on('locations')->onDelete('restrict');
            $table->foreign('responsible_user')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index('status');
            $table->index('product_id');
            $table->index('movement_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adjustments');
    }
};
