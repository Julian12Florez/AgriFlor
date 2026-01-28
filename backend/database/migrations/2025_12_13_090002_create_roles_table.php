<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique(); // 'admin', 'supervisor', 'warehouse_operator', 'farm_operator', 'purchasing'
            $table->string('display_name'); // 'Administrador', 'Supervisor', etc.
            $table->text('description')->nullable();
            $table->boolean('has_full_access')->default(false); // Only for admin
            $table->json('excluded_modules')->nullable(); // For purchasing: ['admin']
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
