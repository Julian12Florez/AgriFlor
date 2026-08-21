<?php

namespace App\Services\Rebaseline;

/**
 * Un saldo del archivo del cliente: cuánto hay de un producto en una ubicación
 * al 31 de julio, según el Excel. Es el valor "J" de las reglas aprobadas.
 */
final class FileBalance
{
    public function __construct(
        public readonly string $productCode,
        public readonly string $productName,
        public readonly string $unitLabel,
        public readonly string $locationLabel,
        public readonly float $quantity,
        public readonly string $sourceSheet,
    ) {}

    /** Clave de casado con el sistema: producto + ubicación normalizada. */
    public function key(): string
    {
        return $this->productCode . '|' . RebaselineText::normalize($this->locationLabel);
    }

    public function withQuantity(float $quantity): self
    {
        return new self(
            $this->productCode,
            $this->productName,
            $this->unitLabel,
            $this->locationLabel,
            $quantity,
            $this->sourceSheet,
        );
    }
}
