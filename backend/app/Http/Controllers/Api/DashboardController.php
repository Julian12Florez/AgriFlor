<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Reception;
use App\Models\TechnicalOrder;
use App\Services\InventoryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }
    /**
     * Get dashboard statistics (KPIs)
     */
    public function getStatistics(Request $request)
    {
        try {
            // Total products
            $totalProducts = Product::where('status', 'active')->count();

            // Active technical orders (draft, approved)
            $activeOrders = TechnicalOrder::whereIn('status', ['draft', 'approved'])->count();

            // Purchases for current month (sum of total)
            $currentMonth = Carbon::now()->startOfMonth();
            $monthlyPurchases = Purchase::where('purchase_date', '>=', $currentMonth)
                ->sum('total');

            // Active alerts
            $activeAlerts = Alert::where('status', 'active')->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_products' => $totalProducts,
                    'active_orders' => $activeOrders,
                    'monthly_purchases' => (float) $monthlyPurchases,
                    'active_alerts' => $activeAlerts,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get inventory breakdown by product category
     */
    public function getInventoryByCategory(Request $request)
    {
        try {
            // Get all active categories
            $categories = DB::table('categories')
                ->where('status', 'active')
                ->get(['id', 'name', 'slug']);

            // Get inventory items with category info
            $inventoryItems = DB::table('inventory')
                ->join('products', 'inventory.product_id', '=', 'products.id')
                ->join('categories', 'products.category_id', '=', 'categories.id')
                ->select(
                    'categories.id as category_id',
                    'categories.slug as category_slug',
                    'categories.name as category_name',
                    'inventory.product_id',
                    'inventory.quantity',
                    'inventory.unit'
                )
                ->where('products.status', 'active')
                ->where('inventory.quantity', '>', 0)
                ->get();

            // Initialize totals for all categories
            $categoryTotals = [];
            foreach ($categories as $category) {
                $categoryTotals[$category->slug] = [
                    'name' => $category->name,
                    'total' => 0,
                ];
            }

            // Sum quantities by category
            foreach ($inventoryItems as $item) {
                if (isset($categoryTotals[$item->category_slug])) {
                    $qtyInBase = $this->inventoryService->toBaseUnit(
                        floatval($item->quantity),
                        $item->unit,
                        $item->product_id
                    );
                    $categoryTotals[$item->category_slug]['total'] += $qtyInBase;
                }
            }

            // Calculate total for percentage
            $totalInventory = array_sum(array_column($categoryTotals, 'total'));

            // Format data with percentages (keyed by slug for backwards compatibility)
            $result = [];
            foreach ($categoryTotals as $slug => $data) {
                $result[$slug] = [
                    'name' => $data['name'],
                    'percentage' => $totalInventory > 0
                        ? round(($data['total'] / $totalInventory) * 100, 2)
                        : 0,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener inventario por categoría: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recent activity timeline
     */
    public function getRecentActivity(Request $request)
    {
        try {
            $limit = $request->input('limit', 10);
            $activities = collect();

            // Recent completed technical orders
            $recentOrders = TechnicalOrder::where('status', 'completed')
                ->with(['agronomist', 'farms'])
                ->orderBy('applied_at', 'desc')
                ->limit(3)
                ->get()
                ->map(function ($order) {
                    return [
                        'type' => 'order_completed',
                        'title' => 'Orden Técnica Completada',
                        'description' => $order->farms->first()
                            ? $order->farms->first()->name . ' - ' . $order->order_number
                            : $order->order_number,
                        'timestamp' => $order->applied_at,
                        'color' => 'green',
                    ];
                });

            // Recent purchases
            $recentPurchases = Purchase::with('supplier')
                ->orderBy('purchase_date', 'desc')
                ->limit(3)
                ->get()
                ->map(function ($purchase) {
                    return [
                        'type' => 'purchase',
                        'title' => 'Nueva Compra Registrada',
                        'description' => ($purchase->supplier ? $purchase->supplier->name : 'Proveedor') . ' - ' . $purchase->order_number,
                        'timestamp' => $purchase->purchase_date,
                        'color' => 'blue',
                    ];
                });

            // Recent receptions
            $recentReceptions = Reception::with(['destinationLocation'])
                ->where('status', 'completed')
                ->orderBy('updated_at', 'desc')
                ->limit(3)
                ->get()
                ->map(function ($reception) {
                    return [
                        'type' => 'reception',
                        'title' => 'Entrada a Bodega',
                        'description' => ($reception->destinationLocation ? $reception->destinationLocation->name : 'Ubicación') . ' - ' . $reception->reception_number,
                        'timestamp' => $reception->updated_at,
                        'color' => 'orange',
                    ];
                });

            // Recent high-priority alerts
            $recentAlerts = Alert::with('product')
                ->where('status', 'active')
                ->where('severity', 'high')
                ->orderBy('created_at', 'desc')
                ->limit(2)
                ->get()
                ->map(function ($alert) {
                    return [
                        'type' => 'alert',
                        'title' => $alert->title,
                        'description' => $alert->product ? $alert->product->name : $alert->description,
                        'timestamp' => $alert->created_at,
                        'color' => 'red',
                    ];
                });

            // Merge all activities and sort by timestamp
            $activities = $activities
                ->concat($recentOrders)
                ->concat($recentPurchases)
                ->concat($recentReceptions)
                ->concat($recentAlerts)
                ->sortByDesc('timestamp')
                ->take($limit)
                ->values();

            return response()->json([
                'success' => true,
                'data' => $activities
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener actividad reciente: ' . $e->getMessage()
            ], 500);
        }
    }
}
