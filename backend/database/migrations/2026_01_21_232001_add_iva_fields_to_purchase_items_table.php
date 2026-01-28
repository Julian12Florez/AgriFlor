<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Run the migrations.
     * ERR-003: Add IVA fields to purchase_items for per-product tax calculation
     */
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->integer('iva_percentage')->default(0)->after('subtotal');
            $table->decimal('tax_amount', 12, 2)->default(0)->after('iva_percentage');
            $table->decimal('total', 12, 2)->default(0)->after('tax_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropColumn(['iva_percentage', 'tax_amount', 'total']);
        });
    }
};
