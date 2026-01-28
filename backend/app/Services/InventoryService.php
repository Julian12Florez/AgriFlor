<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\PackagingUnit;
use Illuminate\Support\Facades\Log;

/**
 * Centralized service for inventory operations.
 * Handles FIFO reduction with automatic unit conversion.
 *
 * Created to fix ERR-001/ERR-002: FIFO consumed entire inventory records
 * when units differed (e.g., kg vs Bultos) because no conversion was applied.
 */
class InventoryService
{
    /**
     * Convert a quantity to its base unit equivalent (kg, litros, unidades).
     *
     * If the unit matches a PackagingUnit name for the given product,
     * multiply by base_quantity to get the base unit value.
     * Otherwise, assume the quantity is already in the base unit.
     *
     * @param float  $quantity  The quantity to convert
     * @param string $unit      The unit name (e.g., "Bultos", "kg")
     * @param string $productId The product UUID
     * @return float The quantity expressed in the product's base unit
     */
    public function toBaseUnit(float $quantity, string $unit, string $productId): float
    {
        $packagingUnit = $this->findPackagingUnit($unit, $productId);

        if ($packagingUnit) {
            return $quantity * floatval($packagingUnit->base_quantity);
        }

        // Not a packaging unit — already in base unit
        return $quantity;
    }

    /**
     * Convert a base-unit quantity to a target packaging unit.
     *
     * @param float  $quantityInBase The quantity in base units
     * @param string $targetUnit     The target unit name
     * @param string $productId      The product UUID
     * @return float The quantity expressed in the target unit
     */
    public function fromBaseUnit(float $quantityInBase, string $targetUnit, string $productId): float
    {
        $packagingUnit = $this->findPackagingUnit($targetUnit, $productId);

        if ($packagingUnit && floatval($packagingUnit->base_quantity) > 0) {
            return $quantityInBase / floatval($packagingUnit->base_quantity);
        }

        // Not a packaging unit — already in base unit
        return $quantityInBase;
    }

    /**
     * Reduce inventory using FIFO (First In, First Out) with unit conversion.
     *
     * All comparisons are done in the product's base unit to ensure
     * correct deductions regardless of how inventory batches are stored.
     *
     * Uses pessimistic locking to prevent race conditions.
     *
     * @param string $productId     Product UUID
     * @param string $brandId       Brand UUID
     * @param string $locationId    Location UUID
     * @param float  $quantity      Amount to reduce
     * @param string $requestedUnit Unit of the requested quantity
     * @throws \Exception When insufficient inventory
     */
    public function reduceInventoryFIFO(
        string $productId,
        string $brandId,
        string $locationId,
        float $quantity,
        string $requestedUnit
    ): void {
        if ($quantity <= 0) {
            throw new \Exception("La cantidad a reducir debe ser mayor a 0.");
        }

        // Lock and get all inventory batches ordered by FIFO
        $inventoryBatches = Inventory::lockForUpdate()
            ->where('product_id', $productId)
            ->where('brand_id', $brandId)
            ->where('location_id', $locationId)
            ->whereNotIn('status', ['expired'])
            ->where('quantity', '>', 0)
            ->orderBy('expiration_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        // Convert requested quantity to base unit for uniform comparison
        $requestedInBase = $this->toBaseUnit($quantity, $requestedUnit, $productId);
        $remainingInBase = $requestedInBase;

        Log::info('FIFO reduction started', [
            'product_id' => $productId,
            'brand_id' => $brandId,
            'location_id' => $locationId,
            'requested_quantity' => $quantity,
            'requested_unit' => $requestedUnit,
            'requested_in_base' => $requestedInBase,
            'batches_found' => $inventoryBatches->count(),
        ]);

        foreach ($inventoryBatches as $batch) {
            if ($remainingInBase <= 0.01) {
                break;
            }

            // Convert this batch's quantity to base unit
            $batchQuantityInBase = $this->toBaseUnit(
                floatval($batch->quantity),
                $batch->unit,
                $productId
            );

            if ($batchQuantityInBase >= $remainingInBase) {
                // This batch has enough — reduce partially
                $reduceInBatchUnit = $this->fromBaseUnit($remainingInBase, $batch->unit, $productId);
                $newQuantity = floatval($batch->quantity) - $reduceInBatchUnit;

                if ($newQuantity > 0.01) {
                    $batch->quantity = $newQuantity;
                    $batch->total_value = $batch->quantity * $batch->unit_price;
                    $batch->save();

                    Log::info('FIFO: batch partially reduced', [
                        'inventory_id' => $batch->id,
                        'batch_number' => $batch->batch_number,
                        'reduced_in_batch_unit' => round($reduceInBatchUnit, 4),
                        'reduced_in_base' => round($remainingInBase, 4),
                        'remaining_quantity' => $batch->quantity,
                        'batch_unit' => $batch->unit,
                    ]);
                } else {
                    $batch->delete();
                    Log::info('FIFO: batch depleted and deleted', [
                        'inventory_id' => $batch->id,
                        'batch_number' => $batch->batch_number,
                    ]);
                }

                $remainingInBase = 0;
            } else {
                // Consume entire batch and continue
                $remainingInBase -= $batchQuantityInBase;

                Log::info('FIFO: batch fully consumed', [
                    'inventory_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'quantity_consumed' => $batch->quantity,
                    'quantity_consumed_base' => round($batchQuantityInBase, 4),
                    'remaining_to_reduce_base' => round($remainingInBase, 4),
                ]);

                $batch->delete();
            }
        }

        if ($remainingInBase > 0.01) {
            Log::error('FIFO: insufficient inventory', [
                'product_id' => $productId,
                'brand_id' => $brandId,
                'location_id' => $locationId,
                'requested_quantity' => $quantity,
                'requested_unit' => $requestedUnit,
                'deficit_in_base' => round($remainingInBase, 4),
            ]);

            throw new \Exception(
                "Inventario insuficiente. Faltan " . round($remainingInBase, 2) . " unidades base."
            );
        }

        Log::info('FIFO reduction completed successfully', [
            'product_id' => $productId,
            'total_reduced_base' => round($requestedInBase, 4),
        ]);
    }

    /**
     * Find a PackagingUnit by name for a specific product.
     *
     * @param string $unitName  The unit name to look up
     * @param string $productId The product UUID
     * @return PackagingUnit|null
     */
    private function findPackagingUnit(string $unitName, string $productId): ?PackagingUnit
    {
        return PackagingUnit::whereRaw('LOWER(name) = ?', [strtolower($unitName)])
            ->whereHas('products', function ($q) use ($productId) {
                $q->where('product_id', $productId);
            })
            ->first();
    }
}
