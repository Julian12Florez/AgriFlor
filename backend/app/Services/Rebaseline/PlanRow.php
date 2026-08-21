<?php

namespace App\Services\Rebaseline;

/**
 * Una línea del plan de re-baseline: qué pasaría con un triple
 * producto + marca + ubicación si se aplicara el archivo de julio.
 *
 * Nomenclatura de las reglas aprobadas:
 *   J   = saldo de apertura al 31-jul según el archivo
 *   K31 = saldo del kardex al 31-jul (movement_date <= corte)
 *   A   = delta de agosto (movement_date > corte)
 *   P   = stock físico actual (tabla inventory)
 *
 * Resultado exigido tras aplicar: kardex@31jul = J, kardex_hoy = J + A, físico = J + A.
 */
final class PlanRow
{
    public const ALERT_NEGATIVE_BALANCE = 'SALDO_NEGATIVO';
    public const ALERT_FARM_CLOSES_AT_ZERO = 'FINCA_CIERRA_EN_CERO';
    public const ALERT_UNIT_MISMATCH = 'UNIDAD_NO_COINCIDE';
    public const ALERT_UNKNOWN_UNIT = 'UNIDAD_DESCONOCIDA';
    public const ALERT_PRODUCT_NOT_IN_CATALOG = 'PRODUCTO_NO_EN_CATALOGO';
    public const ALERT_MISSING_FROM_FILE = 'AUSENTE_EN_ARCHIVO';
    public const ALERT_NO_FILE_PRICE = 'SIN_PRECIO_ARCHIVO';
    public const ALERT_MULTIPLE_BRANDS = 'MULTIMARCA';
    public const ALERT_LOCATION_NOT_FOUND = 'UBICACION_NO_ENCONTRADA';

    /**
     * Alertas que abortan la corrida real (en --dry-run solo se reportan).
     */
    public const BLOCKING_ALERTS = [
        self::ALERT_NEGATIVE_BALANCE,
        self::ALERT_UNIT_MISMATCH,
        self::ALERT_UNKNOWN_UNIT,
        self::ALERT_LOCATION_NOT_FOUND,
    ];

    /**
     * `$brandId` y `$locationId` van al final y son opcionales a propósito: la
     * simulación solo necesita los NOMBRES (es lo que se revisa con bodega),
     * pero la aplicación real necesita las tres llaves del triple para escribir.
     * Una fila sin las tres no es escribible (producto fuera del catálogo o
     * ubicación sin match) y la aplicación la omite reportándola.
     *
     * @param  array<int, string>  $alerts
     */
    public function __construct(
        public readonly ?string $productId,
        public readonly string $productCode,
        public readonly string $productName,
        public readonly string $brandName,
        public readonly string $locationName,
        public readonly string $unit,
        public readonly float $fileBalance,
        public readonly float $originalFileBalance,
        public readonly float $ledgerAtCutoff,
        public readonly float $augustDelta,
        public readonly float $physicalStock,
        public readonly ?float $filePrice,
        public readonly ?float $currentPrice,
        public readonly float $currentValue,
        public readonly array $alerts,
        public readonly ?string $brandId = null,
        public readonly ?string $locationId = null,
    ) {}

    /**
     * ¿La aplicación real puede escribir esta fila? Exige las tres llaves del
     * triple: sin ellas no hay `inventory` ni `inventory_movements` que tocar.
     */
    public function isWritable(): bool
    {
        return $this->productId !== null
            && $this->brandId !== null && $this->brandId !== ''
            && $this->locationId !== null && $this->locationId !== '';
    }

    /** Clave del triple producto + marca + ubicación. */
    public function tripleKey(): string
    {
        return $this->productId . '|' . $this->brandId . '|' . $this->locationId;
    }

    /**
     * Unidades que la apertura reconoce POR ENCIMA de lo que dice el archivo.
     * Solo es distinto de 0 en la excepción de fincas que cierran en 0.
     */
    public function recognizedAboveFile(): float
    {
        return round($this->fileBalance - $this->originalFileBalance, 2);
    }

    /** Movimiento único que se escribiría al kardex, fechado al corte: J − K31. */
    public function ledgerMovement(): float
    {
        return round($this->fileBalance - $this->ledgerAtCutoff, 2);
    }

    /** Valor ABSOLUTO al que queda el stock físico: J + A. */
    public function physicalTarget(): float
    {
        return round($this->fileBalance + $this->augustDelta, 2);
    }

    /** Precio con el que quedaría valorizado: el del archivo, o el actual si el archivo no lo trae. */
    public function effectivePrice(): ?float
    {
        return $this->filePrice ?? $this->currentPrice;
    }

    public function targetValue(): float
    {
        return round($this->physicalTarget() * (float) $this->effectivePrice(), 2);
    }

    public function valueDelta(): float
    {
        return round($this->targetValue() - $this->currentValue, 2);
    }

    public function hasAlert(string $alert): bool
    {
        return in_array($alert, $this->alerts, true);
    }

    public function isBlocked(): bool
    {
        return array_intersect($this->alerts, self::BLOCKING_ALERTS) !== [];
    }

    /** ¿Esta línea cambia algo? Sirve para no llenar el resumen de ruido. */
    public function hasChanges(): bool
    {
        return abs($this->ledgerMovement()) > 0.0001
            || abs($this->physicalTarget() - $this->physicalStock) > 0.0001
            || abs($this->valueDelta()) > 0.01;
    }

    public function alertLabel(): string
    {
        return implode(' + ', $this->alerts);
    }
}
