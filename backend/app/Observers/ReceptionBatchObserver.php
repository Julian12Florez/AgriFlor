<?php

namespace App\Observers;

use App\Models\ReceptionBatch;
use App\Models\Alert;
use Illuminate\Support\Facades\Log;

class ReceptionBatchObserver
{
    /**
     * Handle the ReceptionBatch "created" event.
     *
     * NOTE: Inventory management is handled entirely by ReceptionController
     * (createEntryMovement/createExitMovement/updateInventoryStock).
     * This observer only creates expiration alerts.
     * (ERR-003 fix: removed duplicate inventory updates that caused double counting)
     */
    public function created(ReceptionBatch $batch): void
    {
        try {
            // Load batch items with relationships for alert creation
            $batch->load(['batchItems.product', 'reception.receptionItems.brand']);

            foreach ($batch->batchItems as $item) {
                // Only process items in good condition
                if ($item->condition !== 'good') {
                    continue;
                }

                // Get brand_id from the reception_item
                $receptionItem = $batch->reception->receptionItems
                    ->where('product_id', $item->product_id)
                    ->first();

                if (!$receptionItem) {
                    continue;
                }

                // Create alert if product is expiring soon
                if ($item->expiration_date) {
                    $daysToExpire = now()->diffInDays($item->expiration_date, false);

                    // Alert if expiring in 30 days or less
                    if ($daysToExpire >= 0 && $daysToExpire <= 30) {
                        $severity = 'medium';

                        // High severity if expiring in 7 days or less
                        if ($daysToExpire <= 7) {
                            $severity = 'high';
                        }

                        // Check if alert already exists
                        $existingAlert = Alert::where('type', 'product_expiring')
                            ->where('product_id', $item->product_id)
                            ->where('location_id', $batch->reception->destination_location_id)
                            ->where('status', 'pending')
                            ->first();

                        if (!$existingAlert) {
                            Alert::create([
                                'type' => 'product_expiring',
                                'product_id' => $item->product_id,
                                'location_id' => $batch->reception->destination_location_id,
                                'title' => 'Producto próximo a vencer',
                                'message' => sprintf(
                                    'El producto %s (marca: %s) vence en %d días (fecha: %s)',
                                    $item->product->name,
                                    $receptionItem->brand ? $receptionItem->brand->name : 'Sin marca',
                                    $daysToExpire,
                                    $item->expiration_date
                                ),
                                'severity' => $severity,
                                'status' => 'pending',
                            ]);

                            Log::info("Expiration alert created", [
                                'product_id' => $item->product_id,
                                'days_to_expire' => $daysToExpire,
                                'severity' => $severity,
                            ]);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Error in ReceptionBatchObserver", [
                'batch_id' => $batch->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't throw exception to prevent blocking the main operation
        }
    }

    /**
     * Handle the ReceptionBatch "deleted" event.
     *
     * Note: Deleting a batch should reverse the inventory changes,
     * but this is typically not allowed in production systems.
     */
    public function deleted(ReceptionBatch $batch): void
    {
        Log::warning("ReceptionBatch deleted", [
            'batch_id' => $batch->id,
            'message' => 'Inventory adjustments may be required',
        ]);
    }
}
