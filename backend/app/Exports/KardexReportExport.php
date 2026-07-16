<?php

namespace App\Exports;

use App\Services\InventoryService;
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
use Illuminate\Support\Facades\DB;

class KardexReportExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle, WithMapping
{
    protected $filters;
    protected $product;
    protected $movements;
    protected $inventoryService;

    public function __construct(array $filters, InventoryService $inventoryService)
    {
        $this->filters = $filters;
        $this->inventoryService = $inventoryService;
    }

    public function collection()
    {
        $productId = $this->filters['product_id'];

        // Get product info
        $this->product = DB::table('products')
            ->where('id', $productId)
            ->first();

        // Get movements query
        $movementsQuery = DB::table('inventory_movements')
            ->select([
                'inventory_movements.*',
                'brands.name as brand_name',
                'locations.name as location_name',
                'users.name as responsible_user_name'
            ])
            ->join('brands', 'inventory_movements.brand_id', '=', 'brands.id')
            ->join('locations', 'inventory_movements.location_id', '=', 'locations.id')
            ->join('users', 'inventory_movements.responsible_user', '=', 'users.id')
            ->where('inventory_movements.product_id', $productId);

        if ($this->filters['location_id'] ?? null) {
            $movementsQuery->where('inventory_movements.location_id', $this->filters['location_id']);
        }

        if ($this->filters['start_date'] ?? null) {
            $movementsQuery->whereDate('inventory_movements.movement_date', '>=', $this->filters['start_date']);
        }

        if ($this->filters['end_date'] ?? null) {
            $movementsQuery->whereDate('inventory_movements.movement_date', '<=', $this->filters['end_date']);
        }

        $rawMovements = $movementsQuery
            ->orderBy('inventory_movements.movement_date', 'asc')
            ->orderBy('inventory_movements.created_at', 'asc')
            ->get();

        // Calculate running balance converting to base unit
        $balance = 0;
        $this->movements = [];

        foreach ($rawMovements as $movement) {
            $quantityValue = floatval($movement->quantity);
            $quantityInBase = $this->inventoryService->toBaseUnit($quantityValue, $movement->unit, $productId);

            if ($movement->type === 'entry' || $movement->type === 'adjustment') {
                $balance += $quantityInBase;
                $quantityIn = $quantityInBase;
                $quantityOut = 0;
            } else {
                $balance -= $quantityInBase;
                $quantityIn = 0;
                $quantityOut = $quantityInBase;
            }

            $this->movements[] = (object)[
                'date' => $movement->movement_date,
                'type' => $movement->type,
                'brand_name' => $movement->brand_name,
                'location_name' => $movement->location_name,
                'quantity_in' => $quantityIn,
                'quantity_out' => $quantityOut,
                'balance' => round($balance, 4),
                'original_quantity' => $quantityValue,
                'original_unit' => $movement->unit,
                'unit_price' => floatval($movement->unit_price ?? 0),
                'responsible_user' => $movement->responsible_user_name,
                'observations' => $movement->observations,
            ];
        }

        return collect($this->movements);
    }

    public function headings(): array
    {
        $baseUnit = $this->product->base_unit ?? 'unidades';

        return [
            'FECHA',
            'TIPO',
            'MARCA',
            'UBICACIÓN',
            'ENTRADA (' . $baseUnit . ')',
            'SALIDA (' . $baseUnit . ')',
            'SALDO (' . $baseUnit . ')',
            'DETALLE EMPAQUE',
            'PRECIO UNIT.',
            'RESPONSABLE',
            'OBSERVACIONES',
        ];
    }

    public function map($row): array
    {
        $baseUnit = $this->product->base_unit ?? 'unidades';

        // Packaging detail
        $packagingDetail = '';
        if ($row->original_unit !== $baseUnit) {
            $packagingDetail = $row->original_quantity . ' ' . $row->original_unit;
        }

        return [
            Carbon::parse($row->date)->format('d/m/Y H:i'),
            $this->getTypeLabel($row->type),
            $row->brand_name,
            $row->location_name,
            $row->quantity_in > 0 ? '+' . number_format($row->quantity_in, 0, ',', '.') : '',
            $row->quantity_out > 0 ? '-' . number_format($row->quantity_out, 0, ',', '.') : '',
            number_format($row->balance, 0, ',', '.'),
            $packagingDetail,
            '$' . number_format($row->unit_price, 0, ',', '.'),
            $row->responsible_user,
            $row->observations ?? '',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:K1')->applyFromArray([
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

        $sheet->setAutoFilter('A1:K1');
        $sheet->freezePane('A2');

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16, // Fecha
            'B' => 14, // Tipo
            'C' => 18, // Marca
            'D' => 20, // Ubicación
            'E' => 15, // Entrada
            'F' => 15, // Salida
            'G' => 15, // Saldo
            'H' => 18, // Detalle Empaque
            'I' => 15, // Precio Unit.
            'J' => 20, // Responsable
            'K' => 30, // Observaciones
        ];
    }

    public function title(): string
    {
        $productName = $this->product->name ?? 'Producto';
        return 'Kardex - ' . substr($productName, 0, 25);
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
