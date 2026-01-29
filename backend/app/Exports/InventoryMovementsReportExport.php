<?php

namespace App\Exports;

use App\Models\InventoryMovement;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Carbon\Carbon;

class InventoryMovementsReportExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle, WithMapping
{
    protected $filters;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = InventoryMovement::with([
            'product.brand',
            'product.packagingUnits',
            'location',
            'responsibleUser',
        ]);

        if (($this->filters['start_date'] ?? null) && ($this->filters['end_date'] ?? null)) {
            $query->whereBetween('created_at', [$this->filters['start_date'], $this->filters['end_date']]);
        }

        if ($this->filters['product_id'] ?? null) {
            $query->where('product_id', $this->filters['product_id']);
        }

        if ($this->filters['location_id'] ?? null) {
            $query->where('location_id', $this->filters['location_id']);
        }

        if ($this->filters['type'] ?? null) {
            $query->where('type', $this->filters['type']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function headings(): array
    {
        return [
            'FECHA',
            'TIPO',
            'PRODUCTO',
            'CÓDIGO',
            'MARCA',
            'UBICACIÓN',
            'CANTIDAD (BASE)',
            'DETALLE EMPAQUE',
            'PRECIO UNIT.',
            'TOTAL',
            'USUARIO',
            'OBSERVACIONES',
        ];
    }

    public function map($row): array
    {
        // Get packaging unit info
        $baseQuantity = 1;
        $baseUnit = $row->unit ?? 'unidades';

        if ($row->product && $row->product->packagingUnits) {
            $packagingUnit = $row->product->packagingUnits->first(function ($pu) use ($row) {
                return strtolower($pu->name) === strtolower($row->unit);
            });

            if ($packagingUnit) {
                $baseQuantity = floatval($packagingUnit->base_quantity);
                $baseUnit = $packagingUnit->base_unit;
            }
        }

        $hasPackaging = $baseQuantity > 1;
        $totalBaseQuantity = ($row->quantity ?? 0) * $baseQuantity;

        // Build full unit name
        $fullUnitName = $row->unit ?? 'unidades';
        if ($hasPackaging) {
            $fullUnitName = $row->unit . ' de ' . $baseQuantity . ' ' . $baseUnit;
        }

        // Always show total quantity in base unit
        $sign = $row->type === 'exit' ? '-' : '+';
        $totalQuantityText = $sign . number_format($totalBaseQuantity, 0, ',', '.') . ' ' . $baseUnit;

        return [
            Carbon::parse($row->created_at)->format('d/m/Y H:i'),
            $this->getTypeLabel($row->type),
            $row->product->name ?? 'Sin nombre',
            $row->product->product_code ?? 'N/A',
            $row->product->brand->name ?? 'Sin marca',
            $row->location->name ?? 'N/A',
            $totalQuantityText,
            $hasPackaging ? ($row->quantity . ' ' . ($row->unit ?? '')) : '',
            '$' . number_format($row->unit_price ?? 0, 0, ',', '.'),
            '$' . number_format($row->total_price ?? 0, 0, ',', '.'),
            $row->responsibleUser->name ?? 'N/A',
            $row->observations ?? '',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:L1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2E7D32'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Auto-filter
        $sheet->setAutoFilter('A1:L1');

        // Freeze first row
        $sheet->freezePane('A2');

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16, // Fecha
            'B' => 14, // Tipo
            'C' => 30, // Producto
            'D' => 12, // Código
            'E' => 18, // Marca
            'F' => 20, // Ubicación
            'G' => 18, // Cantidad (Base)
            'H' => 20, // Detalle Empaque
            'I' => 15, // Precio Unit.
            'J' => 15, // Total
            'K' => 20, // Usuario
            'L' => 30, // Observaciones
        ];
    }

    public function title(): string
    {
        return 'Movimientos de Inventario';
    }

    private function getTypeLabel($type)
    {
        $labels = [
            'entry' => 'Entrada',
            'exit' => 'Salida',
            'transfer' => 'Transferencia',
            'application' => 'Aplicación',
            'adjustment' => 'Ajuste',
        ];

        return $labels[$type] ?? $type;
    }
}
