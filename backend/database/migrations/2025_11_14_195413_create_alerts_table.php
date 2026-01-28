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
        Schema::create('alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('type', ['error', 'warning', 'info', 'success']);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->uuid('location_id')->nullable();
            $table->uuid('product_id')->nullable();
            $table->enum('severity', ['high', 'medium', 'low'])->default('medium');
            $table->enum('status', ['active', 'resolved', 'dismissed'])->default('active');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->uuid('resolved_by')->nullable();

            // Foreign keys
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('resolved_by')->references('id')->on('users')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
