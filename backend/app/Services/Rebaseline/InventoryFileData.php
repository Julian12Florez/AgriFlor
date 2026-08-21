<?php

namespace App\Services\Rebaseline;

/**
 * Resultado de leer `INVENTARIO JULIO FINAL.xlsx`: los saldos por producto +
 * ubicación, el catálogo de códigos que trae el archivo y las anomalías
 * detectadas al parsear (códigos repetidos, filas sin unidad, etc.).
 */
final class InventoryFileData
{
    /**
     * @param  array<string, FileBalance>  $balances  clave producto|ubicación normalizada
     * @param  array<string, string>  $productNames  código → nombre en el archivo
     * @param  array<string, string>  $unitLabels  código → etiqueta de unidad en el archivo
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public readonly array $balances,
        public readonly array $productNames,
        public readonly array $unitLabels,
        public readonly array $warnings,
    ) {}

    public function hasProduct(string $productCode): bool
    {
        return isset($this->productNames[$productCode]);
    }

    public function balanceFor(string $productCode, string $locationName): ?FileBalance
    {
        return $this->balances[$productCode . '|' . RebaselineText::normalize($locationName)] ?? null;
    }

    /** @return array<int, FileBalance> saldos distintos de cero */
    public function nonZeroBalances(): array
    {
        return array_values(array_filter(
            $this->balances,
            fn (FileBalance $balance) => abs($balance->quantity) > 0.0001,
        ));
    }

    public function totalQuantity(): float
    {
        return round(array_sum(array_map(
            fn (FileBalance $balance) => $balance->quantity,
            $this->balances,
        )), 2);
    }
}
