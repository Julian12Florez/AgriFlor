<?php

namespace App\Observers;

use App\Models\InventoryMovement;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryMovementObserver
{
    /**
     * Handle the InventoryMovement "created" event.
     *
     * This observer validates inventory consistency after each movement
     * by comparing stored quantity with calculated totals from movements.
     * Uses pessimistic locking to prevent race conditions.
     */
    public function created(InventoryMovement $movement): void
    {
        try {
            // Skip validation if inventory_id is not set (legacy movements)
            if (!$movement->inventory_id) {
                return;
            }

            // Use transaction with lock to prevent race conditions (ERR-001 fix)
            DB::transaction(function () use ($movement) {
                // Lock the inventory row for update to prevent concurrent modifications
                $inventory = Inventory::lockForUpdate()->find($movement->inventory_id);

                if (!$inventory) {
                    Log::warning("InventoryMovement created without valid inventory", [
                        'movement_id' => $movement->id,
                        'inventory_id' => $movement->inventory_id,
                    ]);
                    return;
                }

                // Calculate total entries for this inventory
                $totalEntries = InventoryMovement::where('inventory_id', $inventory->id)
                    ->where('type', 'entry')
                    ->sum('quantity');

                // Calculate total exits for this inventory
                $totalExits = InventoryMovement::where('inventory_id', $inventory->id)
                    ->where('type', 'exit')
                    ->sum('quantity');

                // Calculate expected quantity
                $calculatedQuantity = $totalEntries - $totalExits;

                // Get current stored quantity
                $storedQuantity = $inventory->quantity;

                Log::info("Inventory consistency check", [
                    'inventory_id' => $inventory->id,
                    'product_id' => $inventory->product_id,
                    'location_id' => $inventory->location_id,
                    'stored_quantity' => $storedQuantity,
                    'calculated_quantity' => $calculatedQuantity,
                    'total_entries' => $totalEntries,
                    'total_exits' => $totalExits,
                    'movement_id' => $movement->id,
                ]);

                // Check for discrepancy using both absolute and relative thresholds (LOG-002 fix)
                $discrepancy = abs($storedQuantity - $calculatedQuantity);
                $percentageDiscrepancy = $storedQuantity > 0
                    ? ($discrepancy / $storedQuantity) * 100
                    : ($discrepancy > 0 ? 100 : 0);

                // Only correct if absolute discrepancy > 0.01 AND relative > 1%
                if ($discrepancy > 0.01 && $percentageDiscrepancy > 1) {
                    Log::error("Inventory quantity mismatch detected", [
                        'inventory_id' => $inventory->id,
                        'product_id' => $inventory->product_id,
                        'location_id' => $inventory->location_id,
                        'stored_quantity' => $storedQuantity,
                        'calculated_quantity' => $calculatedQuantity,
                        'discrepancy' => $discrepancy,
                        'percentage_discrepancy' => $percentageDiscrepancy,
                        'movement_id' => $movement->id,
                    ]);

                    // Auto-correct the inventory quantity
                    $inventory->quantity = $calculatedQuantity;
                    $inventory->save();

                    Log::info("Inventory quantity auto-corrected", [
                        'inventory_id' => $inventory->id,
                        'old_quantity' => $storedQuantity,
                        'new_quantity' => $calculatedQuantity,
                    ]);

                    // Create an alert for significant discrepancies
                    if ($discrepancy > 10) {
                        \App\Models\Alert::create([
                            'type' => 'inventory_discrepancy',
                            'product_id' => $inventory->product_id,
                            'location_id' => $inventory->location_id,
                            'title' => 'Discrepancia en inventario detectada',
                            'message' => sprintf(
                                'Se detectó una diferencia de %s unidades en el inventario del producto. Cantidad corregida automáticamente de %s a %s.',
                                number_format($discrepancy, 2),
                                number_format($storedQuantity, 2),
                                number_format($calculatedQuantity, 2)
                            ),
                            'severity' => 'high',
                            'status' => 'pending',
                        ]);
                    }
                } else {
                    Log::debug("Inventory quantity is consistent", [
                        'inventory_id' => $inventory->id,
                        'quantity' => $storedQuantity,
                    ]);
                }
            });
        } catch (\Exception $e) {
            Log::error("Error in InventoryMovementObserver", [
                'movement_id' => $movement->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't throw exception to prevent blocking the main operation
        }
    }

    /**
     * Handle the InventoryMovement "updated" event.
     */
    public function updated(InventoryMovement $movement): void
    {
        // If quantity or type changed, revalidate inventory
        if ($movement->isDirty('quantity') || $movement->isDirty('type')) {
            Log::warning("InventoryMovement updated (quantity or type changed)", [
                'movement_id' => $movement->id,
                'changes' => $movement->getChanges(),
                'original' => $movement->getOriginal(),
            ]);

            // Re-trigger created logic to validate consistency
            $this->created($movement);
        }
    }

    /**
     * Handle the InventoryMovement "deleted" event.
     * Uses pessimistic locking to prevent race conditions.
     */
    public function deleted(InventoryMovement $movement): void
    {
        Log::warning("InventoryMovement deleted", [
            'movement_id' => $movement->id,
            'inventory_id' => $movement->inventory_id,
            'type' => $movement->type,
            'quantity' => $movement->quantity,
            'message' => 'This may cause inventory inconsistencies. Manual verification recommended.',
        ]);

        // Revalidate inventory with lock to prevent race conditions
        if ($movement->inventory_id) {
            try {
                DB::transaction(function () use ($movement) {
                    $inventory = Inventory::lockForUpdate()->find($movement->inventory_id);
                    if ($inventory) {
                        // Recalculate based on remaining movements
                        $totalEntries = InventoryMovement::where('inventory_id', $inventory->id)
                            ->where('type', 'entry')
                            ->sum('quantity');

                        $totalExits = InventoryMovement::where('inventory_id', $inventory->id)
                            ->where('type', 'exit')
                            ->sum('quantity');

                        $calculatedQuantity = $totalEntries - $totalExits;

                        $inventory->quantity = $calculatedQuantity;
                        $inventory->save();

                        Log::info("Inventory recalculated after movement deletion", [
                            'inventory_id' => $inventory->id,
                            'new_quantity' => $calculatedQuantity,
                        ]);
                    }
                });
            } catch (\Exception $e) {
                Log::error("Error recalculating inventory after movement deletion", [
                    'movement_id' => $movement->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
