<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\PackagingUnit;
use App\Models\Product;
use Carbon\Carbon;
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
     * Returns what was ACTUALLY consumed, so the caller can value the movement
     * (and, for a transfer, the incoming batch) at the real FIFO cost instead of
     * an average of the whole location: valuing an exit at anything other than
     * the cost of the batches it consumed creates or destroys inventory value.
     * Callers that do not need it can ignore the return value.
     *
     * @param string $productId     Product UUID
     * @param string $brandId       Brand UUID
     * @param string $locationId    Location UUID
     * @param float  $quantity      Amount to reduce
     * @param string $requestedUnit Unit of the requested quantity
     * @param string|null $batchNumber Optional batch number to filter specific batches
     * @return array{consumed_base_qty: float, consumed_value: float, unit_price_base: float, earliest_expiration_date: string|null}
     * @throws \Exception When insufficient inventory
     */
    public function reduceInventoryFIFO(
        string $productId,
        string $brandId,
        string $locationId,
        float $quantity,
        string $requestedUnit,
        ?string $batchNumber = null
    ): array {
        if ($quantity <= 0) {
            throw new \Exception("La cantidad a reducir debe ser mayor a 0.");
        }

        // Lock and get all inventory batches ordered by FIFO
        // NOTE: Expired products ARE included — users need to be able to dispose of them
        // FIFO ordering prioritizes by expiration date (expired first), then creation date
        $query = Inventory::lockForUpdate()
            ->where('product_id', $productId)
            ->where('brand_id', $brandId)
            ->where('location_id', $locationId)
            ->where('quantity', '>', 0);

        // INT-002: Filter by batch_number when specified
        if ($batchNumber !== null) {
            $query->where('batch_number', $batchNumber);
        }

        $inventoryBatches = $query->orderBy('expiration_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        // Convert requested quantity to base unit for uniform comparison
        $requestedInBase = $this->toBaseUnit($quantity, $requestedUnit, $productId);
        $remainingInBase = $requestedInBase;

        // Costo REAL de lo que se va consumiendo (inventory.unit_price está por
        // unidad base) y vencimiento más próximo entre los lotes consumidos.
        $consumedInBase = 0.0;
        $consumedValue = 0.0;
        $earliestExpiration = null;

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

            $earliestExpiration = $this->earlierDate($earliestExpiration, $batch->expiration_date?->toDateString());

            if ($batchQuantityInBase >= $remainingInBase) {
                // This batch has enough — reduce partially
                $reduceInBatchUnit = $this->fromBaseUnit($remainingInBase, $batch->unit, $productId);
                $newQuantity = floatval($batch->quantity) - $reduceInBatchUnit;

                // Si el residuo es despreciable el lote se elimina, así que lo
                // consumido es el lote COMPLETO, no solo lo pedido.
                $takenInBase = $newQuantity > 0.01 ? $remainingInBase : $batchQuantityInBase;
                $consumedInBase += $takenInBase;
                $consumedValue += $takenInBase * floatval($batch->unit_price);

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
                $consumedInBase += $batchQuantityInBase;
                $consumedValue += $batchQuantityInBase * floatval($batch->unit_price);

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

            // Mensaje claro: nombra el producto y muestra requerido/disponible/faltante,
            // para que en recepciones de varios productos se sepa CUÁL bloquea.
            $productName = Product::where('id', $productId)->value('name') ?? $productId;
            $availableInBase = round($requestedInBase - $remainingInBase, 2);
            throw new \Exception(
                "Inventario insuficiente de '{$productName}': se requieren " . round($requestedInBase, 2) .
                " y solo hay " . $availableInBase . " unidades base disponibles (faltan " . round($remainingInBase, 2) . ")."
            );
        }

        Log::info('FIFO reduction completed successfully', [
            'product_id' => $productId,
            'total_reduced_base' => round($requestedInBase, 4),
            'consumed_value' => round($consumedValue, 4),
        ]);

        return [
            'consumed_base_qty' => $consumedInBase,
            'consumed_value' => $consumedValue,
            'unit_price_base' => $consumedInBase > 0 ? $consumedValue / $consumedInBase : 0.0,
            'earliest_expiration_date' => $earliestExpiration,
        ];
    }

    /**
     * La más próxima de dos fechas (Y-m-d), ignorando las nulas.
     */
    private function earlierDate(?string $current, ?string $candidate): ?string
    {
        if ($candidate === null) {
            return $current;
        }

        return ($current === null || $candidate < $current) ? $candidate : $current;
    }

    /**
     * Increase stock for a specific batch, applying weighted average cost.
     *
     * The batch is identified by [product_id, brand_id, location_id, batch_number]
     * (UNIQUE in the database). If it does not exist it is created; if it already
     * exists the quantity is added and the unit price becomes the weighted average
     * of the current stock and the incoming stock.
     *
     * The quantity MUST already be expressed in the product's base unit — the
     * caller is responsible for the conversion (see toBaseUnit()).
     *
     * Must be called inside a DB::transaction(): the batch row is locked with
     * lockForUpdate() to prevent concurrent updates.
     *
     * @param string      $productId      Product UUID
     * @param string      $brandId        Brand UUID
     * @param string      $locationId     Location UUID
     * @param float       $quantityInBase Quantity to add, already in base unit
     * @param float       $unitPrice      Unit price of the incoming quantity
     * @param string      $batchNumber    Batch number to create or increase
     * @param string|null $expirationDate Expiration date (Y-m-d), optional
     * @throws \Exception When the quantity/price are invalid or the product does not exist
     */
    public function addStock(
        string $productId,
        string $brandId,
        string $locationId,
        float $quantityInBase,
        float $unitPrice,
        string $batchNumber,
        ?string $expirationDate = null
    ): void {
        if ($quantityInBase <= 0) {
            throw new \Exception("La cantidad a ingresar debe ser mayor a 0.");
        }

        if ($unitPrice < 0) {
            throw new \Exception("El precio unitario no puede ser negativo.");
        }

        $batch = Inventory::lockForUpdate()
            ->where('product_id', $productId)
            ->where('brand_id', $brandId)
            ->where('location_id', $locationId)
            ->where('batch_number', $batchNumber)
            ->first();

        if ($batch) {
            $this->increaseBatch($batch, $quantityInBase, $unitPrice, $expirationDate);

            return;
        }

        $inventory = Inventory::create([
            'product_id' => $productId,
            'brand_id' => $brandId,
            'location_id' => $locationId,
            'batch_number' => $batchNumber,
            'quantity' => $quantityInBase,
            'unit' => $this->baseUnitOf($productId),
            'expiration_date' => $expirationDate,
            'unit_price' => $unitPrice,
            'total_value' => $quantityInBase * $unitPrice,
            'status' => $this->calculateStatus($expirationDate),
        ]);

        Log::info('addStock: new inventory batch created', [
            'inventory_id' => $inventory->id,
            'batch_number' => $batchNumber,
            'quantity' => $quantityInBase,
            'unit' => $inventory->unit,
            'unit_price' => $unitPrice,
            'expiration_date' => $expirationDate,
        ]);
    }

    /**
     * Add quantity to an existing batch using weighted average cost.
     *
     * newUnitPrice = ((qty * unitPrice) + (delta * incomingPrice)) / (qty + delta)
     *
     * @throws \Exception When the resulting quantity is not positive (corrupt batch)
     */
    private function increaseBatch(
        Inventory $batch,
        float $quantityInBase,
        float $unitPrice,
        ?string $expirationDate
    ): void {
        $currentQuantity = floatval($batch->quantity);
        $currentUnitPrice = floatval($batch->unit_price);

        $newQuantity = $currentQuantity + $quantityInBase;

        // Guarda obligatoria: addStock ya valida que $quantityInBase > 0, asi que un
        // resultado <= 0 solo puede venir de un lote con cantidad negativa (dato corrupto).
        // Sin esta guarda, $newQuantity == 0 provocaria un DivisionByZeroError fatal.
        if ($newQuantity <= 0) {
            throw new \Exception(
                "El lote '{$batch->batch_number}' quedaria con una cantidad no positiva (" .
                round($newQuantity, 2) . "): tiene " . round($currentQuantity, 2) .
                " en existencia. Revise el inventario antes de registrar el ingreso."
            );
        }

        $newUnitPrice = (($currentQuantity * $currentUnitPrice) + ($quantityInBase * $unitPrice)) / $newQuantity;
        $newExpirationDate = $expirationDate ?? optional($batch->expiration_date)->toDateString();

        $batch->update([
            'quantity' => $newQuantity,
            'unit_price' => $newUnitPrice,
            'total_value' => $newQuantity * $newUnitPrice,
            'expiration_date' => $newExpirationDate,
            'status' => $this->calculateStatus($newExpirationDate),
        ]);

        Log::info('addStock: inventory batch increased (weighted average)', [
            'inventory_id' => $batch->id,
            'batch_number' => $batch->batch_number,
            'previous_quantity' => $currentQuantity,
            'quantity_added' => $quantityInBase,
            'new_quantity' => $newQuantity,
            'previous_unit_price' => $currentUnitPrice,
            'incoming_unit_price' => $unitPrice,
            'new_unit_price' => round($newUnitPrice, 4),
        ]);
    }

    /**
     * Base unit (kg, L, unidades) in which the product's inventory is stored.
     *
     * Public because callers that already converted a quantity to the base unit
     * need it to call reduceInventoryFIFO() without triggering a SECOND
     * conversion: that method converts $quantity from $requestedUnit internally,
     * so passing the base unit makes the conversion an identity.
     *
     * @throws \Exception When the product does not exist
     */
    public function baseUnitOf(string $productId): string
    {
        $product = Product::find($productId);

        if (!$product) {
            throw new \Exception("El producto indicado no existe.");
        }

        return $product->base_unit ?: 'unidades';
    }

    /**
     * Inventory status for a batch with stock, based on its expiration date.
     *
     * Mirrors ReceptionController::calculateInventoryStatus so both entry paths
     * store the same status.
     */
    private function calculateStatus(?string $expirationDate): string
    {
        if ($expirationDate === null) {
            return 'good';
        }

        $daysToExpiry = (int) Carbon::now()->startOfDay()
            ->diffInDays(Carbon::parse($expirationDate)->startOfDay(), false);

        if ($daysToExpiry < 0) {
            return 'expired';
        }

        if ($daysToExpiry <= 30) {
            return 'near_expiry';
        }

        return 'good';
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
