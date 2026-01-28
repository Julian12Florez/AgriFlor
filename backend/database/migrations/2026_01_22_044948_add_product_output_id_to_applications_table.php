<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agrega relacion entre Application y ProductOutput
     * para vincular aplicaciones de campo con salidas tipo consumo.
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->uuid('product_output_id')->nullable()->after('farm_lot_id');

            $table->foreign('product_output_id')
                  ->references('id')->on('product_outputs')
                  ->onDelete('set null');

            $table->index('product_output_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['product_output_id']);
            $table->dropIndex(['product_output_id']);
            $table->dropColumn('product_output_id');
        });
    }
};
