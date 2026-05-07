<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega campo de total de trabajadores disponibles por finca.
     * Se usa para validar disponibilidad al programar tareas.
     */
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->integer('total_workers')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('total_workers');
        });
    }
};
