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
        // Primero, agregar 'pending' al enum manteniendo los valores actuales
        DB::statement("ALTER TABLE purchases MODIFY COLUMN status ENUM('ordered', 'in_transit', 'pending', 'received', 'cancelled') NOT NULL DEFAULT 'ordered'");

        // Cambiar registros con 'in_transit' y 'ordered' a 'pending'
        DB::table('purchases')
            ->whereIn('status', ['in_transit', 'ordered'])
            ->update(['status' => 'pending']);

        // Finalmente, remover 'ordered' e 'in_transit' del enum
        DB::statement("ALTER TABLE purchases MODIFY COLUMN status ENUM('pending', 'received', 'cancelled') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cambiar registros con 'pending' de vuelta a 'in_transit'
        DB::table('purchases')
            ->where('status', 'pending')
            ->update(['status' => 'in_transit']);

        // Revertir el enum
        DB::statement("ALTER TABLE purchases MODIFY COLUMN status ENUM('ordered', 'in_transit', 'received', 'cancelled') NOT NULL DEFAULT 'ordered'");
    }
};
