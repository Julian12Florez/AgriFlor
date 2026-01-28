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
        Schema::create('receptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reception_number', 50)->unique();
            $table->uuid('source_id');
            $table->enum('source_type', ['purchase', 'output']);
            $table->uuid('origin_location_id')->nullable();
            $table->uuid('destination_location_id');
            $table->date('shipment_date')->nullable();
            $table->enum('status', ['pending', 'partial', 'completed', 'cancelled'])->default('pending');
            $table->decimal('total_expected', 10, 2)->default(0);
            $table->decimal('total_received', 10, 2)->default(0);
            $table->decimal('completion_percentage', 5, 2)->default(0);
            $table->uuid('responsible_user');
            $table->text('observations')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('origin_location_id')->references('id')->on('locations')->onDelete('restrict');
            $table->foreign('destination_location_id')->references('id')->on('locations')->onDelete('restrict');
            $table->foreign('responsible_user')->references('id')->on('users')->onDelete('restrict');

            // Indexes
            $table->index(['source_id', 'source_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receptions');
    }
};
