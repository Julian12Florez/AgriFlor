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
        Schema::create('output_farm_lots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_output_id');
            $table->uuid('farm_lot_id');
            $table->timestamp('created_at')->useCurrent();

            // Foreign keys
            $table->foreign('product_output_id')->references('id')->on('product_outputs')->onDelete('cascade');
            $table->foreign('farm_lot_id')->references('id')->on('farm_lots')->onDelete('cascade');

            // Ensure unique combination
            $table->unique(['product_output_id', 'farm_lot_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('output_farm_lots');
    }
};
