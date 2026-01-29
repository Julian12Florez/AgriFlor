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
use Illuminate\Support\Facades\DB;

class KardexListExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle, WithMapping
{
    protected $filters;
    protected $inventoryService;
    protected $groupedData;

    public function __construct(array $filters, InventoryService $inventoryService)
    {
        $this->filters = $filters;
        $this->inventoryService = $inventoryService;
    }

    public function collection()
    {
        // Get inventory items grouped by product (similar to kardex endpoint in InventoryController)
        $query = DB::table('inventory')
            ->select([
                'inventory.*',
                'products.name as product_name',
                'products.product_code',
                'products.category',
                'products.base_unit',
                'products.min_stock',
                'brands.name as brand_name',
                'locations.name as location_name',
            ])
            ->join('products', 'inventory.product_id', '=', 'products.id')
            ->join('brands', 'inventory.brand_id', '=', 'brands.id')
            ->join('locations', 'inventory.location_id', '=', 'locations.id')
            ->where('inventory.quantity', '>', 0);

        if ($this->filters['location_id'] ?? null) {
            $query->where('inventory.location_id', $this->filters['location_id']);
        }

        if ($this->filters['status'] ?? null) {
            $query->where('inventory.status', $this->filters['status']);
        }

        if ($this->filters['search'] ?? null) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('products.name', 'like', "%{$search}%")
                  ->orWhere('products.product_code', 'like', "%{$search}%");
            });
        }

        $items = $query->orderBy('products.name')->get();

        // Group by product
        $grouped = [];
        foreach ($items as $item) {
            $key = $item->product_id;

            $qtyInBase = $this->inventoryService->toBaseUnit(
                floatval($item->quantity),
                $item->unit,
                $item->product_id
            );

            if (!isset($grouped[$key])) {
                $grouped[$key] = (object)[
                    'product_name' => $item->product_name,
                    'product_code' => $item->product_code ?? 'N/A',
                    'category' => $item->category,
                    'base_unit' => $item->base_unit,
                    'min_stock' => floatval($item->min_stock ?? 0),
                    'total_quantity_base' => 0,
                    'total_value' => 0,
                    'locations_count' => 0,
                    'status' => 'good',
                    'locations_list' => [],
                ];
            }

            $grouped[$key]->total_quantity_base += $qtyInBase;
            $grouped[$key]->total_value += floatval($item->total_value ?? 0);

            $locName = $item->location_name;
            if (!in_array($locName, $grouped[$key]->locations_list)) {
                $grouped[$key]->locations_list[] = $locName;
                $grouped[$key]->locations_count++;
            }

            // Update status
            if ($item->status === 'expired') {
                $grouped[$key]->status = 'expired';
            } elseif ($item->status === 'low' && $grouped[$key]->status !== 'expired') {
                $grouped[$key]->status = 'low';
            } elseif ($item->status === 'near_expiry' && !in_array($grouped[$key]->status, ['expired', 'low'])) {
                $grouped[$key]->status = 'near_expiry';
            }
        }

        $this->groupedData = collect(array_values($grouped));
        return $this->groupedData;
    }

    public function headings(): array
    {
        return [
            'PRODUCTO',
            'CÓDIGO',
            'CATEGORÍA',
            'STOCK (BASE)',
            'VALOR TOTAL',
            'N° UBICACIONES',
            'UBICACIONES',
            'ESTADO',
        ];
    }

    public function map($row): array
    {
        return [
            $row->product_name,
            $row->product_code ?? 'N/A',
            ucfirst($row->category ?? 'N/A'),
            number_format($row->total_quantity_base, 0, ',', '.') . ' ' . ($row->base_unit ?? ''),
            '$' . number_format($row->total_value, 0, ',', '.'),
            $row->locations_count,
            implode(', ', $row->locations_list),
            $this->getStatusLabel($row->status),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:H1')->applyFromArray([
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

        $sheet->setAutoFilter('A1:H1');
        $sheet->freezePane('A2');

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30, // Producto
            'B' => 15, // Código
            'C' => 18, // Categoría
            'D' => 18, // Stock (Base)
            'E' => 18, // Valor Total
            'F' => 15, // N° Ubicaciones
            'G' => 30, // Ubicaciones
            'H' => 15, // Estado
        ];
    }

    public function title(): string
    {
        return 'Inventario Actual';
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
