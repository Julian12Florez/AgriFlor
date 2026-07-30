<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class MonthlyInventoryExport implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths
{
    protected array $data;
    protected array $farmColumns;
    protected string $month;
    protected string $year;

    public function __construct(array $data, array $farmColumns, string $month, string $year)
    {
        $this->data = $data;
        $this->farmColumns = $farmColumns;
        $this->month = $month;
        $this->year = $year;
    }

    public function title(): string
    {
        $months = ['', 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO',
                    'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
        return 'MOVIMIENTOS ' . ($months[(int)$this->month] ?? $this->month);
    }

    public function headings(): array
    {
        $headers = ['Codigo', 'Grupo Insumo', 'Producto', 'Unidad Medida', 'INVEN INICIAL', 'INV FINAL (CIERRE MES)', 'STOCK ACTUAL (BODEGA)', 'DIFERENCIA'];

        foreach ($this->farmColumns as $farm) {
            $headers[] = strtoupper($farm['name']);
        }

        // Traslados salientes que NO van a una finca (p. ej. bodega -> bodega):
        // la matriz de columnas de finca no los explica, así que sin esta
        // columna el Excel del cliente no cuadraba contra el Total Mov.
        $headers[] = 'TRASLADOS SALIENTES';

        $headers[] = 'COMPRAS';
        $headers[] = 'REMANENTE';
        $headers[] = 'AUMENTOS';
        $headers[] = 'DISMINUCIONES';
        $headers[] = 'TOTAL MOV';
        $headers[] = 'VARIACIÓN';

        return $headers;
    }

    public function array(): array
    {
        $rows = [];
        foreach ($this->data as $product) {
            $row = [
                $product['product_code'],
                $product['category'],
                $product['product_name'],
                $product['unit'],
                $product['initial_stock'],
                $product['final_stock'],
                $product['current_stock'] ?? 0,
                $product['initial_stock'] - $product['final_stock'],
            ];

            // Farm shipments (negative = envío, positive = devolución)
            foreach ($this->farmColumns as $farm) {
                $val = $product['farm_shipments'][$farm['id']] ?? 0;
                $row[] = $val > 0 ? -$val : 0; // Negative = sent to farm
            }

            // Traslados salientes que no fueron a una finca. Mismo signo que las
            // columnas de finca de arriba: negativo porque salió de la ubicación.
            $transfersOut = (float) ($product['transfers_out'] ?? 0);
            $row[] = $transfersOut > 0 ? -$transfersOut : 0;

            $row[] = $product['purchases'];
            $row[] = $product['returns'] ?? 0;
            $row[] = $product['increases'];
            $row[] = $product['decreases'];
            $row[] = $product['total_movements'];
            $row[] = $product['variation'];

            $rows[] = $row;
        }
        return $rows;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10,  // Codigo
            'B' => 18,  // Grupo Insumo
            'C' => 35,  // Producto
            'D' => 14,  // Unidad
            'E' => 14,  // Inv Inicial
            'F' => 14,  // Inv Final
            'G' => 12,  // Diferencia
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = count($this->data) + 1;
        $lastCol = 8 + count($this->farmColumns) + 1 + 6; // 8 fijas + fincas + Traslados Salientes + 6 extra (incl. Remanente)
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastCol);

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2E7D32']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ],
            "A2:{$lastColLetter}{$lastRow}" => [
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ],
            "E2:{$lastColLetter}{$lastRow}" => [
                'numberFormat' => ['formatCode' => '#,##0.00'],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ],
        ];
    }
}
