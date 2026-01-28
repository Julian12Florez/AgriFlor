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
        Schema::create('technical_recipes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('category', ['fertilization', 'pest_control', 'disease_control', 'weed_control', 'other']);
            $table->text('application_instructions')->nullable();
            $table->text('safety_notes')->nullable();
            $table->decimal('estimated_cost', 12, 2)->default(0);
            $table->integer('usage_count')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->uuid('created_by');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('last_used')->nullable();

            // Foreign keys
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('technical_recipes');
    }
};
