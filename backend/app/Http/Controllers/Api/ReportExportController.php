<?php

namespace App\Http\Controllers\Api;

use App\Exports\ConsumptionReportExport;
use App\Exports\InventoryMovementsReportExport;
use App\Exports\StockReportExport;
use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportExportController extends Controller
{
    /**
     * Export Stock Report to Excel
     */
    public function exportStockExcel(Request $request)
    {
        $filters = [
            'product_id' => $request->input('product_id'),
            'location_id' => $request->input('location_id'),
            'group_by' => $request->input('group_by', 'product'),
        ];

        $fileName = 'stock_report_' . now()->format('Y_m_d_His') . '.xlsx';

        return Excel::download(new StockReportExport($filters), $fileName);
    }

    /**
     * Export Stock Report to PDF
     */
    public function exportStockPdf(Request $request)
    {
        $filters = [
            'product_id' => $request->input('product_id'),
            'location_id' => $request->input('location_id'),
            'group_by' => $request->input('group_by', 'product'),
        ];

        // Get stock data
        $query = Inventory::with(['product.brand', 'location'])
            ->where('quantity', '>', 0)
            ->whereNotIn('status', ['expired']);

        if ($filters['product_id']) {
            $query->where('product_id', $filters['product_id']);
        }

        if ($filters['location_id']) {
            $query->where('location_id', $filters['location_id']);
        }

        $stockItems = $query->get();

        // Group data
        $groupedData = $this->groupStockData($stockItems, $filters['group_by']);

        // Calculate statistics
        $stats = [
            'total_items' => $stockItems->count(),
            'total_quantity' => $stockItems->sum('quantity'),
            'total_value' => $stockItems->sum('total_value'),
            'low_stock' => $stockItems->where('status', 'low')->count(),
            'expired' => $stockItems->whereIn('status', ['expired', 'near_expiry'])->count(),
        ];

        $data = [
            'title' => 'Reporte de Stock Actual',
            'date' => now()->format('d/m/Y H:i'),
            'user' => auth()->user()->name,
            'filters' => $filters,
            'data' => $groupedData,
            'stats' => $stats,
            'group_by' => $filters['group_by'],
        ];

        $pdf = Pdf::loadView('exports.pdf.stock-report', $data);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->download('stock_report_' . now()->format('Y_m_d_His') . '.pdf');
    }

    /**
     * Export Consumption Report to Excel
     */
    public function exportConsumptionExcel(Request $request)
    {
        $filters = [
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'product_id' => $request->input('product_id'),
            'location_id' => $request->input('location_id'),
        ];

        $fileName = 'consumption_report_' . now()->format('Y_m_d_His') . '.xlsx';

        return Excel::download(new ConsumptionReportExport($filters), $fileName);
    }

    /**
     * Export Consumption Report to PDF
     */
    public function exportConsumptionPdf(Request $request)
    {
        $filters = [
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'product_id' => $request->input('product_id'),
            'location_id' => $request->input('location_id'),
        ];

        // Get consumption data using the same query as the consumption report endpoint
        $consumptionData = $this->getConsumptionData($filters);

        $data = [
            'title' => 'Reporte de Consumo de Productos',
            'date' => now()->format('d/m/Y H:i'),
            'user' => auth()->user()->name,
            'filters' => $filters,
            'consumptions' => $consumptionData['consumptions'],
            'summary' => $consumptionData['summary'],
        ];

        $pdf = Pdf::loadView('exports.pdf.consumption-report', $data);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->download('consumption_report_' . now()->format('Y_m_d_His') . '.pdf');
    }

    /**
     * Export Inventory Movements Report to Excel
     */
    public function exportMovementsExcel(Request $request)
    {
        $filters = [
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'product_id' => $request->input('product_id'),
            'location_id' => $request->input('location_id'),
            'type' => $request->input('type'),
        ];

        $fileName = 'movements_report_' . now()->format('Y_m_d_His') . '.xlsx';

        return Excel::download(new InventoryMovementsReportExport($filters), $fileName);
    }

    /**
     * Export Inventory Movements Report to PDF
     */
    public function exportMovementsPdf(Request $request)
    {
        $filters = [
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'product_id' => $request->input('product_id'),
            'location_id' => $request->input('location_id'),
            'type' => $request->input('type'),
        ];

        // Get movements data
        $query = InventoryMovement::with([
            'product.brand',
            'product.packagingUnits',
            'location',
            'responsibleUser',
        ]);

        if ($filters['start_date'] && $filters['end_date']) {
            $query->whereBetween('created_at', [$filters['start_date'], $filters['end_date']]);
        }

        if ($filters['product_id']) {
            $query->where('product_id', $filters['product_id']);
        }

        if ($filters['location_id']) {
            $query->where('location_id', $filters['location_id']);
        }

        if ($filters['type']) {
            $query->where('type', $filters['type']);
        }

        $movements = $query->orderBy('created_at', 'desc')->get();

        // Calculate statistics
        $stats = [
            'total_movements' => $movements->count(),
            'total_entries' => $movements->where('type', 'entry')->count(),
            'total_exits' => $movements->whereIn('type', ['exit', 'application'])->count(),
            'total_applications' => $movements->where('type', 'application')->count(),
            'total_value' => $movements->sum('total_price'),
        ];

        $data = [
            'title' => 'Reporte de Movimientos de Inventario',
            'date' => now()->format('d/m/Y H:i'),
            'user' => auth()->user()->name,
            'filters' => $filters,
            'movements' => $movements,
            'stats' => $stats,
        ];

        $pdf = Pdf::loadView('exports.pdf.movements-report', $data);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->download('movements_report_' . now()->format('Y_m_d_His') . '.pdf');
    }

    /**
     * Group stock data by product or location
     */
    private function groupStockData($stockItems, $groupBy)
    {
        if ($groupBy === 'product') {
            $grouped = [];
            foreach ($stockItems as $item) {
                $key = $item->product_id . '-' . $item->product->brand_id;
                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'product_name' => $item->product->name,
                        'product_code' => $item->product->product_code,
                        'category' => $item->product->category,
                        'brand_name' => $item->product->brand->name,
                        'total_quantity' => 0,
                        'total_value' => 0,
                        'unit' => $item->unit,
                        'locations' => [],
                        'status' => 'good',
                    ];
                }
                $grouped[$key]['total_quantity'] += $item->quantity;
                $grouped[$key]['total_value'] += $item->total_value;
                $grouped[$key]['locations'][] = [
                    'name' => $item->location->name,
                    'quantity' => $item->quantity,
                    'value' => $item->total_value,
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
            // Group by location
            $grouped = [];
            foreach ($stockItems as $item) {
                $key = $item->location_id;
                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'location_name' => $item->location->name,
                        'location_type' => $item->location->type,
                        'total_items' => 0,
                        'total_quantity' => 0,
                        'total_value' => 0,
                        'products' => [],
                    ];
                }
                $grouped[$key]['total_items'] += 1;
                $grouped[$key]['total_quantity'] += $item->quantity;
                $grouped[$key]['total_value'] += $item->total_value;
                $grouped[$key]['products'][] = [
                    'name' => $item->product->name,
                    'brand' => $item->product->brand->name,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'value' => $item->total_value,
                ];
            }
            return array_values($grouped);
        }
    }

    /**
     * Get consumption data using the same logic as InventoryController::consumptionReport()
     */
    private function getConsumptionData($filters)
    {
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];
        $locationId = $filters['location_id'];
        $productId = $filters['product_id'];

        // Get base outputs with receptions
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

        $consumptions = [];
        $totalQuantityConsumed = 0;
        $totalBaseQuantityConsumed = 0;

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
                $totalQuantityConsumed += $quantity;
                $totalBaseQuantityConsumed += $totalBaseQuantity;

                $consumptions[] = (object)[
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
                    'origin_location_id' => $output->origin_location_id,
                    'origin_location_name' => $output->origin_location_name,
                    'destination_location_id' => $output->destination_location_id,
                    'destination_location_name' => $output->destination_location_name,
                    'observations' => $output->observations,
                ];
            }
        }

        $summary = [
            'total_consumptions' => count($consumptions),
            'total_quantity_consumed' => $totalQuantityConsumed,
            'total_base_quantity_consumed' => $totalBaseQuantityConsumed,
            'outputs_count' => $outputs->count(),
        ];

        return [
            'consumptions' => $consumptions,
            'summary' => $summary,
        ];
    }
}
