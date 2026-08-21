<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Libro completo del Informe de Inventario Mensual.
 *
 * Hoja 1: MOVIMIENTOS <MES>  ({@see MonthlyInventoryExport}) — la hoja de
 *         siempre, sin cambios.
 * Hoja 2: REMANENTES         ({@see MonthlyRemainderSheet}) — lo que las fincas
 *         devolvieron a la bodega en el mes, en el formato que el cliente lleva
 *         a mano.
 *
 * Se agrega un envoltorio en vez de convertir MonthlyInventoryExport en
 * multi-hoja para que la hoja 1 quede intacta byte a byte: sigue siendo la misma
 * clase, con las mismas columnas, filas y estilos que antes.
 */
class MonthlyInventoryWorkbookExport implements WithMultipleSheets
{
    protected array $data;
    protected array $farmColumns;
    protected string $month;
    protected string $year;

    /** Bodega del informe; la comparten ambas hojas. */
    protected ?string $warehouseId;

    public function __construct(
        array $data,
        array $farmColumns,
        string $month,
        string $year,
        ?string $warehouseId = null
    ) {
        $this->data = $data;
        $this->farmColumns = $farmColumns;
        $this->month = $month;
        $this->year = $year;
        $this->warehouseId = $warehouseId;
    }

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [
            new MonthlyInventoryExport($this->data, $this->farmColumns, $this->month, $this->year),
            new MonthlyRemainderSheet((int) $this->month, (int) $this->year, $this->warehouseId),
        ];
    }
}
