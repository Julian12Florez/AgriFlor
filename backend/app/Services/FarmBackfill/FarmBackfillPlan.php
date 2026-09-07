<?php

namespace App\Services\FarmBackfill;

/**
 * Plan completo de la reparación: qué se va a escribir, qué NO se puede
 * escribir y por qué.
 *
 * Los totales se calculan SIEMPRE por unidad. Sumar kg con L y con cm produce
 * la cifra "95.927 unidades" que circula en varios informes y que es inválida
 * (ver DIAGNOSTICO_INVENTARIO_20260907.md, §2.A): esta clase no ofrece ningún
 * método que devuelva un total único.
 */
final class FarmBackfillPlan
{
    /**
     * @param  array<int, PlannedMovement>  $entries    entradas a la finca (una por `exit` huérfano)
     * @param  array<int, PlannedMovement>  $nettings   salidas de la finca que compensan las compras ficticias
     * @param  array<int, array{finca: string, producto: string, unidad: string, devuelto: float, neteado: float, residuo: float, motivo: string}>  $residuals
     * @param  array<int, string>  $blockers  impiden la corrida real; el --dry-run los reporta y sigue
     * @param  array<int, string>  $warnings  hay que verlos, no detienen nada
     */
    public function __construct(
        public readonly array $entries,
        public readonly array $nettings,
        public readonly array $residuals = [],
        public readonly array $blockers = [],
        public readonly array $warnings = [],
    ) {
    }

    /** @return array<int, PlannedMovement> */
    public function all(): array
    {
        return array_merge($this->entries, $this->nettings);
    }

    public function isBlocked(): bool
    {
        return $this->blockers !== [];
    }

    public function isEmpty(): bool
    {
        return $this->entries === [] && $this->nettings === [];
    }

    /**
     * Totales POR UNIDAD de una lista de movimientos.
     *
     * @param  array<int, PlannedMovement>  $movements
     * @return array<string, array{movimientos: int, cantidad: float}>  unidad → totales
     */
    public static function totalsByUnit(array $movements): array
    {
        $totals = [];

        foreach ($movements as $movement) {
            $totals[$movement->unit] ??= ['movimientos' => 0, 'cantidad' => 0.0];
            $totals[$movement->unit]['movimientos']++;
            $totals[$movement->unit]['cantidad'] += $movement->quantity;
        }

        ksort($totals);

        return array_map(
            fn (array $row) => ['movimientos' => $row['movimientos'], 'cantidad' => round($row['cantidad'], 2)],
            $totals,
        );
    }

    /**
     * Totales por mes contable y unidad. Agosto sigue abierto y septiembre
     * también, así que el operador tiene que ver cuánto cae en cada uno antes
     * de autorizar.
     *
     * @param  array<int, PlannedMovement>  $movements
     * @return array<string, array<string, array{movimientos: int, cantidad: float}>>
     */
    public static function totalsByMonthAndUnit(array $movements): array
    {
        $byMonth = [];

        foreach ($movements as $movement) {
            $byMonth[$movement->month()][] = $movement;
        }

        ksort($byMonth);

        return array_map(fn (array $group) => self::totalsByUnit($group), $byMonth);
    }

    /**
     * Detalle legible por finca + producto + unidad + fecha, que es lo que se
     * imprime ANTES de escribir nada.
     *
     * @param  array<int, PlannedMovement>  $movements
     * @return array<int, array{finca: string, producto: string, cantidad: float, unidad: string, fecha: string, documento: string}>
     */
    public static function detail(array $movements): array
    {
        $rows = array_map(fn (PlannedMovement $movement) => [
            'finca' => $movement->locationName,
            'producto' => $movement->productName,
            'cantidad' => $movement->quantity,
            'unidad' => $movement->unit,
            'fecha' => $movement->movementDate,
            'documento' => $movement->documentLabel,
        ], $movements);

        usort($rows, fn (array $a, array $b) => [$a['finca'], $a['fecha'], $a['producto']]
            <=> [$b['finca'], $b['fecha'], $b['producto']]);

        return $rows;
    }

    /**
     * Resumen por finca: cuántos movimientos y cuánto, por unidad.
     *
     * @param  array<int, PlannedMovement>  $movements
     * @return array<string, array<string, array{movimientos: int, cantidad: float}>>  finca → unidad → totales
     */
    public static function byFarm(array $movements): array
    {
        $byFarm = [];

        foreach ($movements as $movement) {
            $byFarm[$movement->locationName][] = $movement;
        }

        ksort($byFarm);

        return array_map(fn (array $group) => self::totalsByUnit($group), $byFarm);
    }
}
