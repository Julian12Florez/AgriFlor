<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('worker_code', 50)->unique();
            $table->string('full_name', 255);
            $table->string('document_id', 50)->unique();
            $table->date('hire_date');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index('worker_code');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workers');
    }
};
