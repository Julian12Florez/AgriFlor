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
        Schema::create('technical_order_farms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('technical_order_id');
            $table->uuid('farm_id');
            $table->timestamp('created_at')->useCurrent();

            // Foreign keys
            $table->foreign('technical_order_id')->references('id')->on('technical_orders')->onDelete('cascade');
            $table->foreign('farm_id')->references('id')->on('locations')->onDelete('restrict');

            // Unique constraint
            $table->unique(['technical_order_id', 'farm_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('technical_order_farms');
    }
};
