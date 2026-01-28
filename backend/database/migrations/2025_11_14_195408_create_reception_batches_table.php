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
        Schema::create('reception_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reception_id');
            $table->integer('batch_number');
            $table->timestamp('reception_date');
            $table->uuid('received_by');
            $table->text('observations')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Foreign keys
            $table->foreign('reception_id')->references('id')->on('receptions')->onDelete('cascade');
            $table->foreign('received_by')->references('id')->on('users')->onDelete('restrict');

            // Unique constraint
            $table->unique(['reception_id', 'batch_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reception_batches');
    }
};
