<?php

namespace App\Observers;

use App\Models\ProductOutput;
use App\Models\Inventory;
use App\Models\Alert;
use Illuminate\Support\Facades\Log;

class ProductOutputObserver
{
    /**
     * Handle the ProductOutput "updated" event.
     *
     * This observer creates alerts when a product output is approved
     * and the remaining inventory (after reception) will fall below the minimum stock level.
     *
     * NOTE: With the new flow, inventory is NOT reduced when approving.
     * It will be reduced gradually as receptions are processed.
     * This observer calculates the PROJECTED remaining inventory after reception.
     */
    public function updated(ProductOutput $output): void
    {
        // Only act when status changes to 'approved'
        if (!$output->isDirty('status') || $output->status !== 'approved') {
            return;
        }

        try {
            // Load output products with relationships
            $output->load(['outputProducts.product', 'outputProducts.brand', 'originLocation']);

            foreach ($output->outputProducts as $outputProduct) {
                // LOG-002 fix: convert inventory and delivered quantity to base unit before comparing
                // INC-001 fix: use whereNotIn(['expired']) instead of where('status', 'good')
                $inventoryBatches = Inventory::where('product_id', $outputProduct->product_id)
                    ->where('location_id', $output->origin_location_id)
                    ->whereNotIn('status', ['expired'])
                    ->where('quantity', '>', 0)
                    ->get();

                $product = $outputProduct->product;

                // Convert all inventory to base unit
                $currentInventoryInBase = 0;
                foreach ($inventoryBatches as $batch) {
                    $pu = $product->packagingUnits?->first(function ($pu) use ($batch) {
                        return strtolower($pu->name) === strtolower($batch->unit);
                    });
                    $currentInventoryInBase += $batch->quantity * ($pu ? $pu->base_quantity : 1);
                }

                // Convert delivered quantity to base unit
                $puDelivered = $product->packagingUnits?->first(function ($pu) use ($outputProduct) {
                    return strtolower($pu->name) === strtolower($outputProduct->unit);
                });
                $deliveredInBase = $outputProduct->quantity_delivered * ($puDelivered ? $puDelivered->base_quantity : 1);

                // Calculate PROJECTED remaining in base unit after this output is fully received
                $remaining = $currentInventoryInBase - $deliveredInBase;

                // Check if inventory is below minimum stock
                if ($product->min_stock && $remaining <= $product->min_stock) {
                    $severity = 'medium';

                    // High severity if stock is zero or critical
                    if ($remaining == 0) {
                        $severity = 'high';
                    } elseif ($remaining < ($product->min_stock * 0.5)) {
                        $severity = 'high';
                    }

                    // Check if alert already exists
                    $existingAlert = Alert::where('type', 'low_stock')
                        ->where('product_id', $product->id)
                        ->where('location_id', $output->origin_location_id)
                        ->where('status', 'pending')
                        ->first();

                    if (!$existingAlert) {
                        Alert::create([
                            'type' => 'low_stock',
                            'product_id' => $product->id,
                            'location_id' => $output->origin_location_id,
                            'title' => $remaining <= 0 ? 'Producto se agotará' : 'Stock bajo proyectado',
                            'message' => sprintf(
                                'El producto %s tendrá %s %s después de recepcionar la salida aprobada. Mínimo requerido: %s %s. Se recomienda realizar una orden de compra.',
                                $product->name,
                                $remaining <= 0 ? 'cero' : number_format($remaining, 2),
                                $product->unit ?? 'unidades',
                                number_format($product->min_stock, 2),
                                $product->unit ?? 'unidades'
                            ),
                            'severity' => $severity,
                            'status' => 'pending',
                        ]);

                        Log::info("Low stock alert created via ProductOutputObserver (projected inventory)", [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'location_id' => $output->origin_location_id,
                            'current_inventory_base' => $currentInventoryInBase,
                            'quantity_to_reduce_base' => $deliveredInBase,
                            'projected_remaining_base' => $remaining,
                            'min_stock' => $product->min_stock,
                            'severity' => $severity,
                            'output_id' => $output->id,
                        ]);
                    } else {
                        // Update existing alert message with new quantity
                        $existingAlert->update([
                            'message' => sprintf(
                                'El producto %s tendrá %s %s después de recepcionar la salida aprobada. Mínimo requerido: %s %s. Se recomienda realizar una orden de compra.',
                                $product->name,
                                $remaining <= 0 ? 'cero' : number_format($remaining, 2),
                                $product->unit ?? 'unidades',
                                number_format($product->min_stock, 2),
                                $product->unit ?? 'unidades'
                            ),
                            'severity' => $severity,
                        ]);

                        Log::info("Low stock alert updated (projected inventory)", [
                            'alert_id' => $existingAlert->id,
                            'product_id' => $product->id,
                            'current_inventory_base' => $currentInventoryInBase,
                            'projected_remaining_base' => $remaining,
                        ]);
                    }
                } else {
                    // Inventory is sufficient, resolve any existing low stock alerts
                    Alert::where('type', 'low_stock')
                        ->where('product_id', $product->id)
                        ->where('location_id', $output->origin_location_id)
                        ->where('status', 'pending')
                        ->update([
                            'status' => 'resolved',
                            'resolved_at' => now(),
                        ]);

                    Log::info("Low stock alert auto-resolved", [
                        'product_id' => $product->id,
                        'remaining' => $remaining,
                        'min_stock' => $product->min_stock,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error("Error in ProductOutputObserver", [
                'output_id' => $output->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't throw exception to prevent blocking the main operation
        }
    }

    /**
     * Handle the ProductOutput "deleted" event.
     */
    public function deleted(ProductOutput $output): void
    {
        // If an approved output is deleted, we should reverse inventory changes
        // However, this is typically not allowed in production
        if ($output->status === 'approved') {
            Log::warning("Approved ProductOutput deleted", [
                'output_id' => $output->id,
                'message' => 'Inventory may need manual adjustment',
            ]);
        }
    }
}
