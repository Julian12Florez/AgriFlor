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

class ProductListingExport implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths
{
    protected array $data;
    protected string $date;

    public function __construct(array $data, string $date)
    {
        $this->data = $data;
        $this->date = $date;
    }

    public function title(): string
    {
        return 'Sheet1';
    }

    public function headings(): array
    {
        return ['Finca', 'Codigo', 'Grupo Insumo', 'Producto', 'Unidad Medida', 'Stock Fecha', 'Diferencial', 'Stock Actual', 'Estado'];
    }

    public function array(): array
    {
        return array_map(fn($p) => [
            strtoupper($p['location_type'] === 'warehouse' ? 'BODEGA' : $p['location']),
            $p['product_code'],
            $p['category'],
            $p['product_name'],
            $p['unit'],
            $p['stock_at_date'],
            $p['differential'],
            $p['current_stock'],
            $p['status'],
        ], $this->data);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15, 'B' => 10, 'C' => 20, 'D' => 35,
            'E' => 15, 'F' => 14, 'G' => 12, 'H' => 14, 'I' => 10,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = count($this->data) + 1;
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2E7D32']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ],
            "A2:I{$lastRow}" => [
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ],
            "F2:H{$lastRow}" => [
                'numberFormat' => ['formatCode' => '#,##0.00'],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ],
        ];
    }
}
