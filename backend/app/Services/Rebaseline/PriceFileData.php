<?php

namespace App\Services\Rebaseline;

/**
 * Resultado de leer `Valoración de inventarios.xlsx` (fuente contable de PRECIO).
 */
final class PriceFileData
{
    /**
     * @param  array<string, float>  $prices  código de producto → `Vlr Und`
     * @param  array<string, float>  $quantities  código de producto → `Saldo cantidades` (solo para cruce)
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public readonly array $prices,
        public readonly array $quantities,
        public readonly array $warnings,
    ) {}

    public function priceFor(string $productCode): ?float
    {
        $price = $this->prices[$productCode] ?? null;

        return $price !== null && $price > 0 ? $price : null;
    }

    public function totalQuantity(): float
    {
        return round(array_sum($this->quantities), 2);
    }
}
