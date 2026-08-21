<?php

namespace App\Services\Rebaseline;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

/**
 * Hoja de cálculo con encabezado: resuelve las columnas POR NOMBRE.
 *
 * Existe por un incidente real: el script de carga anterior leía la columna 25
 * fija ("INVENTARIO FINAL"). El cliente insertó una columna, la 25 pasó a ser
 * "REMANENTE" y la carga tomó la columna equivocada sin emitir un solo aviso.
 * Aquí ninguna columna se referencia por índice literal: si el encabezado
 * esperado no aparece, se aborta con un mensaje explícito.
 */
final class HeaderedSheet
{
    /** @var array<string, int> nombre normalizado del encabezado → índice de columna (1-based) */
    private array $columns = [];

    public function __construct(
        private readonly Worksheet $sheet,
        private readonly int $headerRow,
    ) {
        $this->indexHeader();
    }

    public function name(): string
    {
        return $this->sheet->getTitle();
    }

    /**
     * Índice de la columna cuyo encabezado coincide (ya normalizado) con $header.
     *
     * @throws RuntimeException si la columna no existe en la hoja
     */
    public function requireColumn(string $header): int
    {
        $key = RebaselineText::normalize($header);

        if (! isset($this->columns[$key])) {
            throw new RuntimeException(sprintf(
                'La hoja "%s" no tiene la columna "%s" en la fila %d. Encabezados encontrados: %s',
                $this->sheet->getTitle(),
                $header,
                $this->headerRow,
                implode(', ', array_keys($this->columns)),
            ));
        }

        return $this->columns[$key];
    }

    /**
     * Índices de las columnas ubicadas ENTRE dos encabezados (ambos excluidos).
     *
     * Es como se localizan las columnas de finca de la hoja REMANENTES: las que
     * están entre "Unidad Medida" e "INVENTARIO FINAL". Si el cliente agrega o
     * quita una finca, se detecta sola.
     *
     * @return array<int, string> índice de columna → etiqueta original del encabezado
     */
    public function columnsBetween(string $leftHeader, string $rightHeader): array
    {
        $from = $this->requireColumn($leftHeader) + 1;
        $to = $this->requireColumn($rightHeader) - 1;

        $found = [];

        for ($column = $from; $column <= $to; $column++) {
            $label = trim((string) $this->value($column, $this->headerRow));

            if ($label !== '') {
                $found[$column] = $label;
            }
        }

        return $found;
    }

    /**
     * Valor de la celda. Si la celda es una fórmula devuelve el valor que Excel
     * dejó en caché (el que el cliente vio y validó) y solo recalcula cuando no
     * hay caché: recalcular las ~360 filas cuesta 20 s y da exactamente lo mismo.
     */
    public function value(int $column, int $row): mixed
    {
        $cell = $this->sheet->getCell([$column, $row]);
        $raw = $cell->getValue();

        if (is_string($raw) && str_starts_with($raw, '=')) {
            return $cell->getOldCalculatedValue() ?? $cell->getCalculatedValue();
        }

        return $raw;
    }

    public function text(int $column, int $row): string
    {
        $value = $this->value($column, $row);

        return trim(is_scalar($value) ? (string) $value : '');
    }

    public function number(int $column, int $row): float
    {
        $value = $this->value($column, $row);

        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * Números de las filas de datos (todo lo que está debajo del encabezado).
     *
     * @return iterable<int>
     */
    public function dataRows(): iterable
    {
        return range($this->headerRow + 1, $this->sheet->getHighestDataRow());
    }

    private function indexHeader(): void
    {
        $lastColumn = Coordinate::columnIndexFromString($this->sheet->getHighestDataColumn());

        for ($column = 1; $column <= $lastColumn; $column++) {
            $label = RebaselineText::normalize($this->text($column, $this->headerRow));

            if ($label !== '' && ! isset($this->columns[$label])) {
                $this->columns[$label] = $column;
            }
        }
    }
}
