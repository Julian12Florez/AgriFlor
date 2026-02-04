<?php

namespace App\Exports;

use App\Models\Inventory;
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

class StockReportExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle, WithMapping
{
    protected $filters;
    protected $groupedData;
    protected ?InventoryService $inventoryService;

    public function __construct(array $filters, ?InventoryService $inventoryService = null)
    {
        $this->filters = $filters;
        $this->inventoryService = $inventoryService ?? app(InventoryService::class);
    }

    public function collection()
    {
        // Get stock data
        $query = Inventory::with(['product.brand', 'product.packagingUnits', 'location'])
            ->where('quantity', '>', 0)
            ->whereNotIn('status', ['expired']);

        if ($this->filters['product_id']) {
            $query->where('product_id', $this->filters['product_id']);
        }

        if ($this->filters['location_id']) {
            $query->where('location_id', $this->filters['location_id']);
        }

        $stockItems = $query->get();

        // Group data
        $this->groupedData = $this->groupStockData($stockItems, $this->filters['group_by']);

        return collect($this->groupedData);
    }

    public function headings(): array
    {
        if ($this->filters['group_by'] === 'product') {
            return [
                'PRODUCTO',
                'CÓDIGO',
                'CATEGORÍA',
                'MARCA',
                'STOCK (BASE)',
                'DETALLE EMPAQUE',
                'VALOR TOTAL',
                'N° UBICACIONES',
                'ESTADO',
            ];
        } else {
            return [
                'UBICACIÓN',
                'TIPO',
                'TOTAL PRODUCTOS',
                'CANTIDAD TOTAL',
                'VALOR TOTAL',
            ];
        }
    }

    public function map($row): array
    {
        if ($this->filters['group_by'] === 'product') {
            $hasPackaging = isset($row['base_quantity']) && $row['base_quantity'] > 1;
            $baseUnit = $row['base_unit'] ?? $row['unit'] ?? 'unidades';

            // Always show stock in base unit
            $stockBaseText = number_format($row['total_base_quantity'] ?? $row['total_quantity'], 0, ',', '.') . ' ' . $baseUnit;

            // Packaging detail only when there's packaging
            $packagingDetail = '';
            if ($hasPackaging) {
                $packagingDetail = ($row['total_quantity'] ?? 0) . ' ' . ($row['unit'] ?? '');
            }

            return [
                $row['product_name'] ?? 'Sin nombre',
                $row['product_code'] ?? 'N/A',
                $row['category'] ?? 'Sin categoría',
                $row['brand_name'] ?? 'Sin marca',
                $stockBaseText,
                $packagingDetail,
                '$' . number_format($row['total_value'] ?? 0, 0, ',', '.'),
                count($row['locations'] ?? []),
                $this->getStatusLabel($row['status'] ?? 'good'),
            ];
        } else {
            return [
                $row['location_name'] ?? 'Sin ubicación',
                $row['location_type'] === 'warehouse' ? 'Bodega' : 'Finca',
                $row['total_items'] ?? 0,
                $row['total_quantity'] ?? 0,
                '$' . number_format($row['total_value'] ?? 0, 0, ',', '.'),
            ];
        }
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol = $this->filters['group_by'] === 'product' ? 'I' : 'E';

        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
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
        $sheet->setAutoFilter("A1:{$lastCol}1");

        // Freeze first row
        $sheet->freezePane('A2');

        return [];
    }

    public function columnWidths(): array
    {
        if ($this->filters['group_by'] === 'product') {
            return [
                'A' => 30, // Producto
                'B' => 15, // Código
                'C' => 20, // Categoría
                'D' => 20, // Marca
                'E' => 18, // Stock (Base)
                'F' => 20, // Detalle Empaque
                'G' => 18, // Valor Total
                'H' => 18, // N° Ubicaciones
                'I' => 15, // Estado
            ];
        } else {
            return [
                'A' => 30, // Ubicación
                'B' => 15, // Tipo
                'C' => 20, // Total Productos
                'D' => 20, // Cantidad Total
                'E' => 18, // Valor Total
            ];
        }
    }

    public function title(): string
    {
        return 'Stock Actual';
    }

    private function groupStockData($stockItems, $groupBy)
    {
        if ($groupBy === 'product') {
            $grouped = [];
            foreach ($stockItems as $item) {
                $key = $item->product_id . '-' . ($item->product->brand_id ?? 'no-brand');

                // Convert to base unit using InventoryService
                $qtyInBase = $this->inventoryService->toBaseUnit(
                    floatval($item->quantity),
                    $item->unit,
                    $item->product_id
                );

                // Get base unit from product
                $baseUnit = $item->product->base_unit ?? $item->unit ?? 'unidades';

                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'product_name' => $item->product->name ?? 'Sin nombre',
                        'product_code' => $item->product->product_code ?? 'N/A',
                        'category' => $item->product->category?->name ?? 'Sin categoría',
                        'brand_name' => $item->product->brand->name ?? 'Sin marca',
                        'total_quantity' => 0,
                        'total_value' => 0,
                        'total_base_quantity' => 0,
                        'unit' => $item->unit ?? 'unidades',
                        'base_unit' => $baseUnit,
                        'locations' => [],
                        'status' => 'good',
                    ];
                }

                $grouped[$key]['total_quantity'] += $item->quantity ?? 0;
                $grouped[$key]['total_value'] += $item->total_value ?? 0;
                $grouped[$key]['total_base_quantity'] += $qtyInBase;

                $grouped[$key]['locations'][] = [
                    'name' => $item->location->name ?? 'Sin ubicación',
                    'quantity' => $item->quantity ?? 0,
                ];

                // Update status
                if (in_array($item->status, ['expired'])) {
                    $grouped[$key]['status'] = $item->status;
                } elseif ($item->status === 'low' && $grouped[$key]['status'] !== 'expired') {
                    $grouped[$key]['status'] = 'low';
                }
            }
            return array_values($grouped);
        } else {
            // Group by location - convert each item to base before summing
            $grouped = [];
            foreach ($stockItems as $item) {
                $key = $item->location_id;

                $qtyInBase = $this->inventoryService->toBaseUnit(
                    floatval($item->quantity),
                    $item->unit,
                    $item->product_id
                );

                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'location_name' => $item->location->name ?? 'Sin ubicación',
                        'location_type' => $item->location->type ?? 'unknown',
                        'total_items' => 0,
                        'total_quantity' => $qtyInBase,
                        'total_value' => 0,
                        'products' => [],
                    ];
                } else {
                    $grouped[$key]['total_quantity'] += $qtyInBase;
                }
                $grouped[$key]['total_items'] += 1;
                $grouped[$key]['total_value'] += $item->total_value ?? 0;
                $grouped[$key]['products'][] = [
                    'name' => $item->product->name ?? 'Sin nombre',
                    'brand' => $item->product->brand->name ?? 'Sin marca',
                    'quantity' => $qtyInBase,
                    'unit' => $item->product->base_unit ?? $item->unit,
                    'value' => $item->total_value ?? 0,
                ];
            }
            return array_values($grouped);
        }
    }

    private function getStatusLabel($status)
    {
        $labels = [
            'good' => 'Disponible',
            'low' => 'Stock Bajo',
            'out_of_stock' => 'Agotado',
            'expired' => 'Vencido',
            'near_expiry' => 'Por Vencer',
        ];

        return $labels[$status] ?? 'Disponible';
    }
}
