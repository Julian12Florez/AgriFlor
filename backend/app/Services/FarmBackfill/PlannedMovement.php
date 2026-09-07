<?php

namespace App\Services\FarmBackfill;

/**
 * Un movimiento de kardex que la reparación va a escribir en una FINCA.
 *
 * Hay dos clases, y las dos se derivan de un movimiento que YA existe en la
 * base (nunca de un cálculo): por eso `sourceMovementId` es obligatorio y es la
 * clave de idempotencia.
 *
 *  · KIND_ENTRY   — la entrada que nunca se escribió. Copia cantidad, unidad,
 *                   fecha, precio y responsable del movimiento `exit` de bodega
 *                   con el que forma pareja.
 *  · KIND_NETTING — la salida de la finca que compensa una devolución que el
 *                   cliente registró como compra ficticia a "REMANENTES FINCA".
 *                   Copia cantidad, unidad, fecha y responsable de la ENTRADA de
 *                   bodega de esa compra (recortada al saldo disponible).
 */
final class PlannedMovement
{
    public const KIND_ENTRY = 'entrada';

    public const KIND_NETTING = 'neteo';

    public function __construct(
        /** KIND_ENTRY | KIND_NETTING */
        public readonly string $kind,
        /** UUID del movimiento ya existente del que se deriva este. Clave de idempotencia. */
        public readonly string $sourceMovementId,
        /** 'entry' | 'exit' — el enum de inventory_movements. */
        public readonly string $type,
        public readonly string $productId,
        public readonly string $productCode,
        public readonly string $productName,
        public readonly string $brandId,
        public readonly string $brandName,
        /** Siempre una ubicación de tipo 'farm': la bodega no se toca. */
        public readonly string $locationId,
        public readonly string $locationName,
        public readonly float $quantity,
        public readonly string $unit,
        /** Y-m-d. La del movimiento origen, nunca hoy. */
        public readonly string $movementDate,
        public readonly ?string $expirationDate,
        public readonly float $unitPrice,
        public readonly string $responsibleUser,
        public readonly string $relatedDocumentId,
        public readonly string $relatedDocumentType,
        /** Número de documento legible para el informe (SAL-… / PUR-…). */
        public readonly string $documentLabel,
        /** Lote físico destino; sólo las entradas crean lote. */
        public readonly ?string $batchNumber,
    ) {
    }

    public function tripleKey(): string
    {
        return $this->productId . '|' . $this->brandId . '|' . $this->locationId;
    }

    /** Clave del lote físico: la misma tupla del índice único de `inventory`. */
    public function batchKey(): string
    {
        return $this->tripleKey() . '|' . (string) $this->batchNumber;
    }

    public function totalPrice(): float
    {
        return round($this->quantity * $this->unitPrice, 2);
    }

    /** Mes contable al que cae el movimiento (Y-m), para el informe por mes. */
    public function month(): string
    {
        return substr($this->movementDate, 0, 7);
    }

    /**
     * Copia con otra cantidad y otro precio. La usa el recorte del neteo, que
     * sólo puede devolver lo que el saldo de la finca aguanta.
     */
    public function withQuantity(float $quantity, ?float $unitPrice = null): self
    {
        return new self(
            $this->kind,
            $this->sourceMovementId,
            $this->type,
            $this->productId,
            $this->productCode,
            $this->productName,
            $this->brandId,
            $this->brandName,
            $this->locationId,
            $this->locationName,
            round($quantity, 2),
            $this->unit,
            $this->movementDate,
            $this->expirationDate,
            $unitPrice ?? $this->unitPrice,
            $this->responsibleUser,
            $this->relatedDocumentId,
            $this->relatedDocumentType,
            $this->documentLabel,
            $this->batchNumber,
        );
    }
}
