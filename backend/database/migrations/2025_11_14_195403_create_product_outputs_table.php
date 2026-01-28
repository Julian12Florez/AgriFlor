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
        Schema::create('product_outputs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('output_number', 50)->unique();
            $table->uuid('technical_order_id')->nullable();
            $table->date('output_date');
            $table->uuid('origin_location_id');
            $table->uuid('destination_location_id');
            $table->enum('status', ['pending', 'partial', 'completed'])->default('pending');
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->text('observations')->nullable();
            $table->uuid('responsible_user');
            $table->timestamp('created_at')->useCurrent();

            // Foreign keys
            $table->foreign('technical_order_id')->references('id')->on('technical_orders')->onDelete('set null');
            $table->foreign('origin_location_id')->references('id')->on('locations')->onDelete('restrict');
            $table->foreign('destination_location_id')->references('id')->on('locations')->onDelete('restrict');
            $table->foreign('responsible_user')->references('id')->on('users')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_outputs');
    }
};
