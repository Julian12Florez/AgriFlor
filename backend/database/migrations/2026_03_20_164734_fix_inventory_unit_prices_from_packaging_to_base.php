<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fix inventory unit_price and total_value that were stored using packaging unit prices
 * instead of base unit prices.
 *
 * Problem: When receiving a purchase, getUnitPriceForMovement() returned the PurchaseItem
 * unit_price (price per packaging unit, e.g., $120,000/Bulto) without converting to base
 * unit price (e.g., $2,400/kg where 1 Bulto = 50 kg). This caused inventory records to
 * have inflated unit_price and total_value.
 *
 * Fix: For each inventory record that came from a purchase reception, recalculate
 * unit_price = purchase_item.unit_price / packaging_unit.base_quantity
 * total_value = inventory.quantity * corrected_unit_price
 */
return new class extends Migration
{
    public function up(): void
    {
        // Get all inventory records that came from purchase receptions
        // These have batch_number starting with 'REC-'
        $inventoryRecords = DB::table('inventory')
            ->where('batch_number', 'like', 'REC-%')
            ->where('unit_price', '>', 0)
            ->get();

        $fixed = 0;
        $skipped = 0;

        foreach ($inventoryRecords as $inventory) {
            // Find the reception that created this inventory via the batch_number
            // batch_number format: REC-{reception_id_first_8}-{batch_number}
            $parts = explode('-', $inventory->batch_number);
            if (count($parts) < 3) {
                $skipped++;
                continue;
            }

            $receptionIdPrefix = $parts[1];

            // Find the reception
            $reception = DB::table('receptions')
                ->where('id', 'like', $receptionIdPrefix . '%')
                ->first();

            if (!$reception || $reception->source_type !== 'purchase') {
                $skipped++;
                continue;
            }

            // Find the purchase item for this product
            $purchaseItem = DB::table('purchase_items')
                ->where('purchase_id', $reception->source_id)
                ->where('product_id', $inventory->product_id)
                ->where('brand_id', $inventory->brand_id)
                ->first();

            if (!$purchaseItem || !$purchaseItem->packaging_unit_id) {
                $skipped++;
                continue;
            }

            // Get the packaging unit
            $packagingUnit = DB::table('packaging_units')
                ->where('id', $purchaseItem->packaging_unit_id)
                ->first();

            if (!$packagingUnit || floatval($packagingUnit->base_quantity) <= 1) {
                $skipped++;
                continue;
            }

            // Calculate corrected price
            $oldUnitPrice = floatval($inventory->unit_price);
            $baseQuantity = floatval($packagingUnit->base_quantity);
            $newUnitPrice = round($oldUnitPrice / $baseQuantity, 2);
            $newTotalValue = round(floatval($inventory->quantity) * $newUnitPrice, 2);

            // Only fix if the price actually needs correction (avoid re-running)
            if ($oldUnitPrice == $newUnitPrice) {
                $skipped++;
                continue;
            }

            DB::table('inventory')
                ->where('id', $inventory->id)
                ->update([
                    'unit_price' => $newUnitPrice,
                    'total_value' => $newTotalValue,
                ]);

            Log::info('Fixed inventory unit_price', [
                'inventory_id' => $inventory->id,
                'product_id' => $inventory->product_id,
                'packaging_unit' => $packagingUnit->name,
                'base_quantity' => $baseQuantity,
                'old_unit_price' => $oldUnitPrice,
                'new_unit_price' => $newUnitPrice,
                'old_total_value' => floatval($inventory->quantity) * $oldUnitPrice,
                'new_total_value' => $newTotalValue,
            ]);

            $fixed++;
        }

        // Also fix inventory_movements with inflated prices
        $movements = DB::table('inventory_movements')
            ->where('related_document_type', 'App\\Models\\Reception')
            ->where('unit_price', '>', 0)
            ->get();

        $movementsFixed = 0;

        foreach ($movements as $movement) {
            $reception = DB::table('receptions')
                ->where('id', $movement->related_document_id)
                ->first();

            if (!$reception || $reception->source_type !== 'purchase') {
                continue;
            }

            $purchaseItem = DB::table('purchase_items')
                ->where('purchase_id', $reception->source_id)
                ->where('product_id', $movement->product_id)
                ->where('brand_id', $movement->brand_id)
                ->first();

            if (!$purchaseItem || !$purchaseItem->packaging_unit_id) {
                continue;
            }

            $packagingUnit = DB::table('packaging_units')
                ->where('id', $purchaseItem->packaging_unit_id)
                ->first();

            if (!$packagingUnit || floatval($packagingUnit->base_quantity) <= 1) {
                continue;
            }

            $oldPrice = floatval($movement->unit_price);
            $baseQty = floatval($packagingUnit->base_quantity);
            $newPrice = round($oldPrice / $baseQty, 2);

            if ($oldPrice == $newPrice) {
                continue;
            }

            $newTotal = round(floatval($movement->quantity) * $newPrice, 2);

            DB::table('inventory_movements')
                ->where('id', $movement->id)
                ->update([
                    'unit_price' => $newPrice,
                    'total_price' => $newTotal,
                ]);

            $movementsFixed++;
        }

        Log::info("Price fix migration completed", [
            'inventory_fixed' => $fixed,
            'inventory_skipped' => $skipped,
            'movements_fixed' => $movementsFixed,
        ]);
    }

    public function down(): void
    {
        // This migration fixes data, reverting would re-introduce the bug
        // No-op on rollback
    }
};
