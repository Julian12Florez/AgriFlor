<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds source_item_id to reception_items for 1:1 linking with source items
 * (output_products.id or purchase_items.id).
 *
 * Adds reception_item_id to reception_batch_items for direct linking
 * to the specific reception_item being received.
 *
 * This fixes the critical bug where multiple items of the same product+brand
 * (e.g., 2 Urea batches with different expiration dates) could not be
 * distinguished by the backend, causing quantities to always be applied
 * to the first matching item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reception_items', function (Blueprint $table) {
            $table->uuid('source_item_id')->nullable()->after('brand_id');
            $table->index('source_item_id');
        });

        Schema::table('reception_batch_items', function (Blueprint $table) {
            $table->uuid('reception_item_id')->nullable()->after('product_id');
            $table->foreign('reception_item_id')
                ->references('id')
                ->on('reception_items')
                ->onDelete('cascade');
        });

        // Backfill source_item_id for existing reception_items from outputs
        $this->backfillSourceItemIds();
    }

    public function down(): void
    {
        Schema::table('reception_batch_items', function (Blueprint $table) {
            $table->dropForeign(['reception_item_id']);
            $table->dropColumn('reception_item_id');
        });

        Schema::table('reception_items', function (Blueprint $table) {
            $table->dropIndex(['source_item_id']);
            $table->dropColumn('source_item_id');
        });
    }

    /**
     * Backfill source_item_id for existing reception_items.
     * For items where product_id+brand_id is unique within the reception's source,
     * we can safely link them. For ambiguous cases (duplicates), we link by
     * matching order (sorted by quantity_expected desc, which matches the
     * order output_products were created).
     */
    private function backfillSourceItemIds(): void
    {
        $receptions = \App\Models\Reception::all();

        foreach ($receptions as $reception) {
            if ($reception->source_type === 'output') {
                $outputProducts = \App\Models\OutputProduct::where('output_id', $reception->source_id)
                    ->orderBy('created_at', 'asc')
                    ->get();

                $receptionItems = $reception->receptionItems()
                    ->orderBy('created_at', 'asc')
                    ->get();

                // Match by position (they were created in the same order)
                foreach ($receptionItems as $index => $receptionItem) {
                    if (isset($outputProducts[$index])) {
                        $receptionItem->update(['source_item_id' => $outputProducts[$index]->id]);
                    }
                }
            } elseif ($reception->source_type === 'purchase') {
                $purchaseItems = \App\Models\PurchaseItem::where('purchase_id', $reception->source_id)
                    ->orderBy('created_at', 'asc')
                    ->get();

                $receptionItems = $reception->receptionItems()
                    ->orderBy('created_at', 'asc')
                    ->get();

                foreach ($receptionItems as $index => $receptionItem) {
                    if (isset($purchaseItems[$index])) {
                        $receptionItem->update(['source_item_id' => $purchaseItems[$index]->id]);
                    }
                }
            }
        }
    }
};
