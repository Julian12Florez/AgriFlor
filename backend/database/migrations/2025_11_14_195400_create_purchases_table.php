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
        Schema::create('purchases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number', 50)->unique();
            $table->uuid('supplier_id');
            $table->uuid('destination_location_id');
            $table->date('purchase_date');
            $table->date('expected_delivery')->nullable();
            $table->enum('status', ['ordered', 'in_transit', 'received', 'cancelled'])->default('ordered');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('observations')->nullable();
            $table->uuid('created_by');
            $table->uuid('received_by')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Foreign keys
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('restrict');
            $table->foreign('destination_location_id')->references('id')->on('locations')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('received_by')->references('id')->on('users')->onDelete('restrict');

            // Indexes
            $table->index('supplier_id');
            $table->index('status');
            $table->index('purchase_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
