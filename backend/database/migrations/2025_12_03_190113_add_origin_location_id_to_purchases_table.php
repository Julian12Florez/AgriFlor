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
        Schema::table('purchases', function (Blueprint $table) {
            $table->uuid('origin_location_id')->nullable()->after('supplier_id');
            $table->foreign('origin_location_id')->references('id')->on('locations')->onDelete('restrict');
            $table->index('origin_location_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropForeign(['origin_location_id']);
            $table->dropIndex(['origin_location_id']);
            $table->dropColumn('origin_location_id');
        });
    }
};
