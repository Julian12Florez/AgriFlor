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
        Schema::table('product_outputs', function (Blueprint $table) {
            $table->uuid('output_type_id')->nullable()->after('output_number');

            // Foreign key
            $table->foreign('output_type_id')->references('id')->on('output_types')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_outputs', function (Blueprint $table) {
            $table->dropForeign(['output_type_id']);
            $table->dropColumn('output_type_id');
        });
    }
};
