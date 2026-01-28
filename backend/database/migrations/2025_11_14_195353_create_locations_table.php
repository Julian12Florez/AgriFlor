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
        Schema::create('locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->enum('type', ['warehouse', 'farm']);
            $table->string('municipality', 100)->nullable();
            $table->string('address', 255)->nullable();
            $table->decimal('coordinates_lat', 10, 8)->nullable();
            $table->decimal('coordinates_lng', 11, 8)->nullable();
            $table->string('responsible', 255)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
