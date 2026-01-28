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
        Schema::create('technical_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number', 50)->unique();
            $table->date('scheduled_date');
            $table->enum('status', ['draft', 'approved', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->uuid('recipe_id')->nullable();
            $table->uuid('responsible_agronomist');
            $table->decimal('estimated_cost', 12, 2)->default(0);
            $table->text('observations')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->uuid('applied_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Foreign keys
            $table->foreign('recipe_id')->references('id')->on('technical_recipes')->onDelete('set null');
            $table->foreign('responsible_agronomist')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('applied_by')->references('id')->on('users')->onDelete('restrict');

            // Indexes
            $table->index('scheduled_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('technical_orders');
    }
};
