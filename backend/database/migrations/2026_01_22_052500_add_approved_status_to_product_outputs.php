<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify ENUM to add 'approved' and 'in_transit' statuses
        DB::statement("ALTER TABLE product_outputs MODIFY COLUMN status ENUM('pending', 'approved', 'in_transit', 'partial', 'completed', 'cancelled') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE product_outputs MODIFY COLUMN status ENUM('pending', 'partial', 'completed') DEFAULT 'pending'");
    }
};
