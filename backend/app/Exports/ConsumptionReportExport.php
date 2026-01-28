<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
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

class ConsumptionReportExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle, WithMapping
{
    protected $filters;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $startDate = $this->filters['start_date'] ?? null;
        $endDate = $this->filters['end_date'] ?? null;
        $locationId = $this->filters['location_id'] ?? null;
        $productId = $this->filters['product_id'] ?? null;

        // Get base outputs with receptions (same logic as InventoryController)
        $outputsQuery = DB::table('product_outputs as po')
            ->join('receptions as r', function ($join) {
                $join->on('r.source_id', '=', 'po.id')
                    ->where('r.source_type', '=', 'output');
            })
            ->join('output_types', 'po.output_type_id', '=', 'output_types.id')
            ->leftJoin('locations as origin', 'po.origin_location_id', '=', 'origin.id')
            ->leftJoin('locations as dest', 'po.destination_location_id', '=', 'dest.id')
            ->select([
                'po.id as output_id',
                'po.output_number',
                'po.output_date',
                'po.destination_location_id',
                'dest.name as destination_location_name',
                'origin.id as origin_location_id',
                'origin.name as origin_location_name',
                'po.observations',
            ])
            ->where('output_types.code', 'consumption') // Only consumption type
            ->distinct();

        if ($startDate) {
            $outputsQuery->where('po.output_date', '>=', $startDate);
        }

        if ($endDate) {
            $outputsQuery->where('po.output_date', '<=', $endDate);
        }

        if ($locationId) {
            $outputsQuery->where('po.destination_location_id', $locationId);
        }

        $outputs = $outputsQuery->get();

        $consumptions = collect();

        foreach ($outputs as $output) {
            // Get products for this output
            $products = DB::table('output_products')
                ->select([
                    'output_products.*',
                    'products.name as product_name',
                    'products.product_code',
                    'products.category',
                    'brands.name as brand_name',
                ])
                ->join('products', 'output_products.product_id', '=', 'products.id')
                ->join('brands', 'output_products.brand_id', '=', 'brands.id')
                ->where('output_products.output_id', $output->output_id);

            if ($productId) {
                $products->where('output_products.product_id', $productId);
            }

            $products = $products->get();

            if ($products->isEmpty()) {
                continue;
            }

            foreach ($products as $product) {
                // Get actual EXIT quantity from inventory movements (the real consumption)
                $exitQuantity = DB::table('inventory_movements')
                    ->join('receptions', 'inventory_movements.related_document_id', '=', 'receptions.id')
                    ->where('inventory_movements.related_document_type', 'App\\Models\\Reception')
                    ->where('inventory_movements.type', 'exit')
                    ->where('receptions.source_type', 'output')
                    ->where('receptions.source_id', $output->output_id)
                    ->where('inventory_movements.product_id', $product->product_id)
                    ->where('inventory_movements.brand_id', $product->brand_id)
                    ->sum('inventory_movements.quantity');

                $quantity = floatval($exitQuantity ?? 0);

                // Skip if no quantity consumed
                if ($quantity <= 0) {
                    continue;
                }

                // Get packaging unit info
                $baseQuantity = 1;
                $baseUnit = $product->unit;
                $packagingUnit = DB::table('product_packaging_units')
                    ->join('packaging_units', 'product_packaging_units.packaging_unit_id', '=', 'packaging_units.id')
                    ->where('product_packaging_units.product_id', $product->product_id)
                    ->where(DB::raw('LOWER(packaging_units.name)'), strtolower($product->unit))
                    ->first(['packaging_units.base_quantity', 'packaging_units.base_unit']);

                if ($packagingUnit) {
                    $baseQuantity = floatval($packagingUnit->base_quantity);
                    $baseUnit = $packagingUnit->base_unit;
                }

                $totalBaseQuantity = $quantity * $baseQuantity;

                $consumptions->push((object)[
                    'output_id' => $output->output_id,
                    'output_number' => $output->output_number,
                    'output_date' => $output->output_date,
                    'product_id' => $product->product_id,
                    'product_name' => $product->product_name,
                    'product_code' => $product->product_code,
                    'category' => $product->category,
                    'brand_name' => $product->brand_name,
                    'quantity' => $quantity,
                    'unit' => $product->unit,
                    'base_quantity' => $baseQuantity,
                    'base_unit' => $baseUnit,
                    'total_base_quantity' => $totalBaseQuantity,
                    'origin_location_name' => $output->origin_location_name,
                    'destination_location_name' => $output->destination_location_name,
                    'observations' => $output->observations,
                ]);
            }
        }

        return $consumptions;
    }

    public function headings(): array
    {
        return [
            'FECHA',
            'N° SALIDA',
            'PRODUCTO',
            'CÓDIGO',
            'CATEGORÍA',
            'MARCA',
            'CANTIDAD',
            'UNIDAD',
            'CANTIDAD TOTAL',
            'ORIGEN',
            'FINCA DESTINO',
            'OBSERVACIONES',
        ];
    }

    public function map($row): array
    {
        $hasPackaging = isset($row->base_quantity) && $row->base_quantity > 1;

        // Build full unit name
        $fullUnitName = $row->unit ?? 'unidades';
        if ($hasPackaging) {
            $fullUnitName = $row->unit . ' de ' . $row->base_quantity . ' ' . $row->base_unit;
        }

        // Build total quantity text
        $totalQuantityText = '-';
        if ($hasPackaging && isset($row->total_base_quantity)) {
            $totalQuantityText = number_format($row->total_base_quantity, 0, ',', '.') . ' ' . $row->base_unit;
        }

        return [
            Carbon::parse($row->output_date)->format('d/m/Y'),
            $row->output_number ?? 'N/A',
            $row->product_name ?? 'Sin nombre',
            $row->product_code ?? 'N/A',
            $row->category ?? 'Sin categoría',
            $row->brand_name ?? 'Sin marca',
            $row->quantity ?? 0,
            $fullUnitName,
            $totalQuantityText,
            $row->origin_location_name ?? 'N/A',
            $row->destination_location_name ?? 'N/A',
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
            'A' => 12, // Fecha
            'B' => 15, // N° Salida
            'C' => 30, // Producto
            'D' => 12, // Código
            'E' => 18, // Categoría
            'F' => 18, // Marca
            'G' => 12, // Cantidad
            'H' => 20, // Unidad
            'I' => 18, // Cantidad Total
            'J' => 20, // Origen
            'K' => 20, // Finca Destino
            'L' => 30, // Observaciones
        ];
    }

    public function title(): string
    {
        return 'Consumo de Productos';
    }
}
