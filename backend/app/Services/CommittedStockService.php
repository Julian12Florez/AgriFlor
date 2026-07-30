<?php

namespace App\Services;

use App\Models\OutputProduct;
use App\Models\ProductOutput;
use App\Models\Reception;
use Illuminate\Support\Collection;

/**
 * Cuánto stock FÍSICO está COMPROMETIDO (reservado) por salidas que ya fueron
 * entregadas pero aún no se reciben del todo: 'approved', 'in_transit' y
 * 'partial'. Ese stock sigue en el lote, pero ya no está libre para una nueva
 * salida.
 *
 * Extraído de ProductOutputController::store() — que ya calculaba esto para
 * validar el stock antes de crear la salida — para que
 * ProductController::getForOutputs() use EXACTAMENTE la misma regla al armar
 * el desplegable de "Nueva Salida". Antes el desplegable ofrecía el físico
 * completo y el backend rechazaba con 422 "Stock insuficiente" porque
 * validaba físico − comprometido: el usuario veía una cantidad que nunca
 * podía despachar (PR-C / E5 / C-3).
 */
class CommittedStockService
{
    /**
     * Estados de salida que retienen stock: ya se entregó (o se aprobó para
     * entregar) pero la recepción del destino todavía no lo liberó del todo.
     */
    public const DEFAULT_STATUSES = ['approved', 'in_transit', 'partial'];

    public function __construct(private InventoryService $inventoryService)
    {
    }

    /**
     * Salidas de un origen cuyo estado retiene stock, precargadas UNA vez para
     * que el llamador calcule el comprometido de varios productos sin repetir
     * esta consulta por cada uno (evita N+1 cuando se recorren muchos
     * productos de una misma ubicación, como en getForOutputs()).
     */
    public function otherOutputsForLocation(
        string $locationId,
        array $statuses = self::DEFAULT_STATUSES,
        ?string $excludeOutputId = null
    ): Collection {
        $query = ProductOutput::where('origin_location_id', $locationId)
            ->whereIn('status', $statuses);

        if ($excludeOutputId !== null) {
            $query->where('id', '!=', $excludeOutputId);
        }

        return $query->get();
    }

    /**
     * Cuánto de [producto, marca] está comprometido por las salidas ya
     * precargadas con otherOutputsForLocation(), y qué salidas lo retienen
     * (para armar mensajes de error legibles, como hacía el código original
     * de ProductOutputController::store()).
     *
     * @param  Collection<int, ProductOutput>  $otherOutputs  Resultado de otherOutputsForLocation()
     * @return array{total: float, blocking: array<int, array{output_number: string, pending: float}>}
     */
    public function committedBreakdown(Collection $otherOutputs, string $productId, string $brandId): array
    {
        $committedBase = 0.0;
        $blocking = [];

        foreach ($otherOutputs as $otherOutput) {
            $otherProducts = OutputProduct::where('output_id', $otherOutput->id)
                ->where('product_id', $productId)
                ->where('brand_id', $brandId)
                ->get();

            foreach ($otherProducts as $otherProduct) {
                $deliveredBase = $this->inventoryService->toBaseUnit(
                    floatval($otherProduct->quantity_delivered),
                    $otherProduct->unit,
                    $productId
                );

                // Restar lo ya recibido en el destino: eso ya dejó de estar comprometido.
                $receivedBase = 0.0;
                $reception = Reception::where('source_id', $otherOutput->id)
                    ->where('source_type', 'output')
                    ->first();

                if ($reception) {
                    $items = $reception->receptionItems()
                        ->where('product_id', $productId)
                        ->where('brand_id', $brandId)
                        ->get();

                    foreach ($items as $item) {
                        $receivedBase += $this->inventoryService->toBaseUnit(
                            floatval($item->quantity_received),
                            $item->unit,
                            $productId
                        );
                    }
                }

                $pendingBase = max(0, $deliveredBase - $receivedBase);
                if ($pendingBase > 0.01) {
                    $committedBase += $pendingBase;
                    $blocking[] = [
                        'output_number' => $otherOutput->output_number ?? $otherOutput->id,
                        'pending' => round($pendingBase, 2),
                    ];
                }
            }
        }

        return ['total' => $committedBase, 'blocking' => $blocking];
    }

    /**
     * Atajo para cuando solo hace falta el comprometido de UNA combinación
     * producto+marca (no vale la pena reutilizar $otherOutputs entre varias).
     */
    public function committedQuantity(
        string $locationId,
        string $productId,
        string $brandId,
        array $statuses = self::DEFAULT_STATUSES,
        ?string $excludeOutputId = null
    ): float {
        $otherOutputs = $this->otherOutputsForLocation($locationId, $statuses, $excludeOutputId);

        return $this->committedBreakdown($otherOutputs, $productId, $brandId)['total'];
    }
}
