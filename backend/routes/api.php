<?php

use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\PackagingUnitController;
use App\Http\Controllers\Api\BaseUnitController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductOutputController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\ReceptionController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\TechnicalOrderController;
use App\Http\Controllers\Api\TechnicalRecipeController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\FarmLotController;
use App\Http\Controllers\Api\OutputTypeController;
use App\Http\Controllers\Api\ReportExportController;
use App\Http\Controllers\Api\WorkerController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\DailyAssignmentController;
use App\Http\Controllers\Api\LiquidationReportController;
use App\Http\Controllers\Api\LiquidationAnalyticsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Aquí se registran todas las rutas API para la aplicación AgriFlor.
| Todas las rutas están protegidas con autenticación JWT excepto login.
|
*/

// ============================================
// PUBLIC ROUTES (No Authentication Required)
// ============================================

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
});

// ============================================
// PROTECTED ROUTES (JWT Authentication Required)
// ============================================

Route::middleware('auth:api')->group(function () {

    // ----------------------------------------
    // AUTH ROUTES
    // ----------------------------------------
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
    });

    // ----------------------------------------
    // DASHBOARD ROUTES (All authenticated users)
    // ----------------------------------------
    Route::prefix('dashboard')->group(function () {
        Route::get('statistics', [DashboardController::class, 'getStatistics']);
        Route::get('inventory-by-category', [DashboardController::class, 'getInventoryByCategory']);
        Route::get('recent-activity', [DashboardController::class, 'getRecentActivity']);
    });

    // ----------------------------------------
    // USER LIST (All authenticated - for dropdowns)
    // ----------------------------------------
    Route::get('users/simple', [UserController::class, 'listSimple']);

    // ----------------------------------------
    // USER MANAGEMENT (Admin only)
    // ----------------------------------------
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::patch('users/{id}/status', [UserController::class, 'updateStatus']);
    });

    // ----------------------------------------
    // MASTER DATA MODULES
    // ----------------------------------------

    // PRODUCTS - Read (All authenticated - needed for outputs/receptions)
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);
    Route::post('products/search-with-inventory', [ProductController::class, 'searchWithInventory']);
    Route::get('products-for-outputs', [ProductController::class, 'getForOutputs']);

    // PRODUCTS - Write (Admin, Purchasing, Warehouse, Agronomist)
    Route::middleware('role:admin,purchasing,warehouse,agronomist')->group(function () {
        Route::post('products', [ProductController::class, 'store']);
        Route::put('products/{product}', [ProductController::class, 'update']);
        Route::delete('products/{product}', [ProductController::class, 'destroy']);
    });

    // BRANDS - Read (All authenticated)
    Route::get('brands', [BrandController::class, 'index']);
    Route::get('brands/{brand}', [BrandController::class, 'show']);

    // BRANDS - Write (Admin, Purchasing)
    Route::middleware('role:admin,purchasing')->group(function () {
        Route::post('brands', [BrandController::class, 'store']);
        Route::put('brands/{brand}', [BrandController::class, 'update']);
        Route::delete('brands/{brand}', [BrandController::class, 'destroy']);
    });

    // CATEGORIES - Read (All authenticated)
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{category}', [CategoryController::class, 'show']);

    // CATEGORIES - Write (Admin, Purchasing)
    Route::middleware('role:admin,purchasing')->group(function () {
        Route::post('categories', [CategoryController::class, 'store']);
        Route::put('categories/{category}', [CategoryController::class, 'update']);
        Route::delete('categories/{category}', [CategoryController::class, 'destroy']);
    });

    // SUPPLIERS - Read (All authenticated)
    Route::get('suppliers', [SupplierController::class, 'index']);
    Route::get('suppliers/{supplier}', [SupplierController::class, 'show']);

    // SUPPLIERS - Write (Admin, Purchasing)
    Route::middleware('role:admin,purchasing')->group(function () {
        Route::post('suppliers', [SupplierController::class, 'store']);
        Route::put('suppliers/{supplier}', [SupplierController::class, 'update']);
        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy']);
        Route::post('suppliers/{id}/contacts', [SupplierController::class, 'addContact']);
        Route::delete('suppliers/{id}/contacts/{contactId}', [SupplierController::class, 'removeContact']);
    });

    // LOCATIONS - Read (All authenticated - needed for outputs/receptions)
    Route::get('locations', [LocationController::class, 'index']);
    Route::get('locations/{location}', [LocationController::class, 'show']);
    Route::get('locations/type/warehouses', [LocationController::class, 'warehouses']);
    Route::get('locations/type/farms', [LocationController::class, 'farms']);

    // LOCATIONS - Write (Admin, Warehouse, Supervisor, Purchasing)
    Route::middleware('role:admin,warehouse,supervisor,purchasing')->group(function () {
        Route::post('locations', [LocationController::class, 'store']);
        Route::put('locations/{location}', [LocationController::class, 'update']);
        Route::delete('locations/{location}', [LocationController::class, 'destroy']);
    });

    // FARM LOTS - Read (All authenticated - needed for outputs/receptions)
    Route::get('farm-lots', [FarmLotController::class, 'index']);
    Route::get('farm-lots/{id}', [FarmLotController::class, 'show']);
    Route::get('locations/{locationId}/farm-lots', [FarmLotController::class, 'getByLocation']);

    // FARM LOTS - Write (Admin, Warehouse)
    Route::middleware('role:admin,warehouse')->group(function () {
        Route::post('farm-lots', [FarmLotController::class, 'store']);
        Route::put('farm-lots/{id}', [FarmLotController::class, 'update']);
        Route::delete('farm-lots/{id}', [FarmLotController::class, 'destroy']);
    });

    // OUTPUT TYPES (All authenticated users can view)
    Route::get('output-types', [OutputTypeController::class, 'index']);
    Route::get('output-types/{id}', [OutputTypeController::class, 'show']);

    // OUTPUT TYPES - Write operations (Admin only)
    Route::middleware('role:admin')->group(function () {
        Route::post('output-types', [OutputTypeController::class, 'store']);
        Route::put('output-types/{id}', [OutputTypeController::class, 'update']);
        Route::delete('output-types/{id}', [OutputTypeController::class, 'destroy']);
    });

    // PACKAGING UNITS - Read (All authenticated)
    Route::get('packaging-units', [PackagingUnitController::class, 'index']);
    Route::get('packaging-units/{packaging_unit}', [PackagingUnitController::class, 'show']);

    // PACKAGING UNITS - Write (Admin, Purchasing)
    Route::middleware('role:admin,purchasing')->group(function () {
        Route::post('packaging-units', [PackagingUnitController::class, 'store']);
        Route::put('packaging-units/{packaging_unit}', [PackagingUnitController::class, 'update']);
        Route::delete('packaging-units/{packaging_unit}', [PackagingUnitController::class, 'destroy']);
    });

    // BASE UNITS - Read (All authenticated)
    Route::get('base-units', [BaseUnitController::class, 'index']);
    Route::get('base-units/{base_unit}', [BaseUnitController::class, 'show']);

    // BASE UNITS - Write (Admin, Purchasing)
    Route::middleware('role:admin,purchasing')->group(function () {
        Route::post('base-units', [BaseUnitController::class, 'store']);
        Route::put('base-units/{base_unit}', [BaseUnitController::class, 'update']);
        Route::delete('base-units/{base_unit}', [BaseUnitController::class, 'destroy']);
    });

    // ----------------------------------------
    // TECHNICAL PROCESSES
    // ----------------------------------------

    // TECHNICAL RECIPES (Admin, Agronomist)
    Route::middleware('role:admin,agronomist')->group(function () {
        Route::apiResource('technical-recipes', TechnicalRecipeController::class);
        Route::post('technical-recipes/{id}/duplicate', [TechnicalRecipeController::class, 'duplicate']);
    });

    // TECHNICAL ORDERS (Admin, Agronomist, Supervisor - View only)
    Route::middleware('role:admin,agronomist,supervisor')->group(function () {
        Route::get('technical-orders', [TechnicalOrderController::class, 'index']);
        Route::get('technical-orders/{id}', [TechnicalOrderController::class, 'show']);
    });

    // TECHNICAL ORDERS - Write operations (Admin, Agronomist only)
    Route::middleware('role:admin,agronomist')->group(function () {
        Route::post('technical-orders', [TechnicalOrderController::class, 'store']);
        Route::put('technical-orders/{id}', [TechnicalOrderController::class, 'update']);
        Route::delete('technical-orders/{id}', [TechnicalOrderController::class, 'destroy']);
        Route::post('technical-orders/{id}/approve', [TechnicalOrderController::class, 'approve']);
        Route::post('technical-orders/{id}/complete', [TechnicalOrderController::class, 'complete']);
        Route::post('technical-orders/{id}/cancel', [TechnicalOrderController::class, 'cancel']);
    });

    // ----------------------------------------
    // WAREHOUSE MANAGEMENT
    // ----------------------------------------

    // PURCHASES - Read (All authenticated - needed for receptions from purchases)
    Route::get('purchases', [PurchaseController::class, 'index']);
    Route::get('purchases/{id}', [PurchaseController::class, 'show']);
    Route::get('purchases/{id}/export-pdf', [PurchaseController::class, 'exportPdf']);

    // PURCHASES - Write operations (Admin, Purchasing, Warehouse)
    Route::middleware('role:admin,purchasing,warehouse')->group(function () {
        Route::post('purchases', [PurchaseController::class, 'store']);
        Route::put('purchases/{id}', [PurchaseController::class, 'update']);
        Route::delete('purchases/{id}', [PurchaseController::class, 'destroy']);
        Route::post('purchases/{id}/attachments', [PurchaseController::class, 'addAttachment']);
        Route::delete('purchases/{id}/attachments/{attachmentId}', [PurchaseController::class, 'removeAttachment']);
        Route::put('purchases/{id}/cancel', [PurchaseController::class, 'cancel']);
    });

    // PRODUCT OUTPUTS - Read (All roles - todos deben tener acceso a salidas)
    Route::middleware('role:admin,warehouse,supervisor,farm,purchasing,agronomist,financiero')->group(function () {
        Route::post('product-outputs/validate-inventory', [ProductOutputController::class, 'validateInventory']);
        Route::get('product-outputs', [ProductOutputController::class, 'index']);
        Route::get('product-outputs/{id}', [ProductOutputController::class, 'show']);
    });

    // PRODUCT OUTPUTS - Write (All roles - todos deben tener acceso a salidas)
    Route::middleware('role:admin,warehouse,supervisor,farm,purchasing,agronomist,financiero')->group(function () {
        Route::post('product-outputs', [ProductOutputController::class, 'store']);
        Route::put('product-outputs/{id}', [ProductOutputController::class, 'update']);
        Route::delete('product-outputs/{id}', [ProductOutputController::class, 'destroy']);
        Route::post('product-outputs/{id}/mark-in-transit', [ProductOutputController::class, 'markInTransit']);
        Route::post('product-outputs/{id}/complete', [ProductOutputController::class, 'complete']);
    });

    // PRODUCT OUTPUTS - Approval (Admin, Supervisor only)
    Route::middleware('role:admin,supervisor')->group(function () {
        Route::post('product-outputs/{id}/approve', [ProductOutputController::class, 'approve']);
    });

    // PRODUCT OUTPUTS - Applications from consumption outputs (Admin, Warehouse, Agronomist)
    Route::middleware('role:admin,warehouse,agronomist')->group(function () {
        Route::post('product-outputs/{id}/register-application', [ProductOutputController::class, 'registerApplication']);
        Route::get('product-outputs/{id}/applications', [ProductOutputController::class, 'getApplications']);
    });

    // RECEPTIONS (All authenticated users can view)
    Route::get('receptions/available-sources', [ReceptionController::class, 'availableSources']);
    Route::get('receptions/available-sources-for-responsible', [ReceptionController::class, 'availableSourcesForResponsible']);
    Route::get('receptions', [ReceptionController::class, 'index']);
    Route::get('receptions/{id}', [ReceptionController::class, 'show']);
    Route::get('receptions/{id}/batches', [ReceptionController::class, 'getBatches']);
    Route::get('receptions/{id}/pending-products', [ReceptionController::class, 'getPendingProducts']);

    // RECEPTIONS - Write operations (All roles - todos deben tener acceso a recepciones)
    Route::middleware('role:admin,warehouse,farm,supervisor,agronomist,purchasing,financiero')->group(function () {
        Route::post('receptions', [ReceptionController::class, 'store']);
        Route::post('receptions/direct-reception', [ReceptionController::class, 'createReceptionWithBatch']);
        Route::post('receptions/{id}/batches', [ReceptionController::class, 'addBatch']);
        Route::put('receptions/{id}/complete', [ReceptionController::class, 'complete']);
        Route::put('receptions/{id}/cancel', [ReceptionController::class, 'cancel']);
    });

    // ----------------------------------------
    // INVENTORY MANAGEMENT
    // ----------------------------------------

    // INVENTORY - Read operations (All authenticated users)
    Route::get('inventory', [InventoryController::class, 'index']);
    Route::get('inventory/kardex', [InventoryController::class, 'kardex']);
    Route::get('inventory/kardex/product/{productId}', [InventoryController::class, 'productKardex']);
    Route::get('inventory/movements', [InventoryController::class, 'movements']);
    Route::get('inventory/movements/report', [InventoryController::class, 'movementsReport']);
    Route::get('inventory/movements/product/{productId}', [InventoryController::class, 'movementsByProduct']);
    Route::get('inventory/consumption/report', [InventoryController::class, 'consumptionReport']);
    Route::get('inventory/monthly-report', [InventoryController::class, 'monthlyReport']);
    Route::get('inventory/product-listing', [InventoryController::class, 'productListingReport']);
    Route::get('inventory/location/{locationId}', [InventoryController::class, 'byLocation']);
    Route::get('inventory/product/{productId}/details', [InventoryController::class, 'byProduct']);
    Route::get('inventory/{productId}', [InventoryController::class, 'show']);

    // INVENTORY - Adjustments (Admin, Warehouse only)
    Route::middleware('role:admin,warehouse')->group(function () {
        Route::post('inventory/adjustments', [InventoryController::class, 'adjustment']);
    });

    // ----------------------------------------
    // APPLICATIONS (Product Applications to Farm Lots)
    // ----------------------------------------

    // APPLICATIONS - Read operations (All authenticated users)
    Route::get('applications', [ApplicationController::class, 'index']);
    Route::get('applications/{id}', [ApplicationController::class, 'show']);

    // APPLICATIONS - Write operations (Admin, Warehouse, Agronomist)
    Route::middleware('role:admin,warehouse,agronomist')->group(function () {
        Route::post('applications', [ApplicationController::class, 'store']);
        Route::post('applications/{id}/cancel', [ApplicationController::class, 'cancel']);
    });

    // APPLICATIONS - Approval (Admin, Warehouse only)
    Route::middleware('role:admin,warehouse')->group(function () {
        Route::post('applications/{id}/approve', [ApplicationController::class, 'approve']);
    });

    // ----------------------------------------
    // ALERTS & REPORTS
    // ----------------------------------------

    // ALERTS - Read operations (All authenticated users)
    Route::get('alerts', [AlertController::class, 'index']);
    Route::get('alerts/{id}', [AlertController::class, 'show']);

    // ALERTS - Write operations (Admin, Supervisor, Warehouse, Financiero)
    Route::middleware('role:admin,supervisor,warehouse,financiero')->group(function () {
        Route::post('alerts', [AlertController::class, 'store']);
        Route::put('alerts/{id}/resolve', [AlertController::class, 'resolve']);
        Route::put('alerts/{id}/dismiss', [AlertController::class, 'dismiss']);
    });

    // ----------------------------------------
    // REPORT EXPORTS (Permission-based)
    // ----------------------------------------

    // REPORT EXPORTS - Excel and PDF (requires export_reports permission)
    Route::middleware('permission:export_reports')->group(function () {
        // Stock Report Exports
        Route::get('reports/stock/export-excel', [ReportExportController::class, 'exportStockExcel']);
        Route::get('reports/stock/export-pdf', [ReportExportController::class, 'exportStockPdf']);

        // Consumption Report Exports
        Route::get('reports/consumption/export-excel', [ReportExportController::class, 'exportConsumptionExcel']);
        Route::get('reports/consumption/export-pdf', [ReportExportController::class, 'exportConsumptionPdf']);

        // Inventory Movements Report Exports
        Route::get('reports/movements/export-excel', [ReportExportController::class, 'exportMovementsExcel']);
        Route::get('reports/movements/export-pdf', [ReportExportController::class, 'exportMovementsPdf']);

        // Kardex Product Report Exports
        Route::get('reports/kardex/export-excel', [ReportExportController::class, 'exportKardexExcel']);
        Route::get('reports/kardex/export-pdf', [ReportExportController::class, 'exportKardexPdf']);

        // Kardex List (Inventory General) Exports
        Route::get('reports/kardex-list/export-excel', [ReportExportController::class, 'exportKardexListExcel']);
        Route::get('reports/kardex-list/export-pdf', [ReportExportController::class, 'exportKardexListPdf']);

        // Monthly Inventory Report Export
        Route::get('reports/monthly-inventory/export-excel', [ReportExportController::class, 'exportMonthlyExcel']);

        // Product Listing Report Export
        Route::get('reports/product-listing/export-excel', [ReportExportController::class, 'exportProductListingExcel']);
    });

    // ----------------------------------------
    // ADMIN IMPORT / MIGRATION ENDPOINTS (Admin only)
    // ----------------------------------------
    Route::middleware('role:admin')->group(function () {
        Route::post('admin/import-inventory', [ImportController::class, 'importInventory']);
        Route::post('admin/run-migrations', [ImportController::class, 'runMigrations']);
        Route::post('admin/setup-brand', [ImportController::class, 'setupBrand']);
        Route::post('admin/clean-data', [ImportController::class, 'cleanData']);
    });

    // ----------------------------------------
    // LIQUIDATION MODULE
    // ----------------------------------------

    // WORKERS - Read (All authenticated - for dropdowns)
    Route::get('workers/simple', [WorkerController::class, 'listSimple']);

    // WORKERS - CRUD (Admin, Liquidador)
    Route::middleware('role:admin,liquidador')->group(function () {
        Route::get('workers', [WorkerController::class, 'index']);
        Route::post('workers', [WorkerController::class, 'store']);
        Route::get('workers/template', [WorkerController::class, 'downloadTemplate']);
        Route::post('workers/preview', [WorkerController::class, 'preview']);
        Route::post('workers/import', [WorkerController::class, 'processImport']);
        Route::get('workers/{id}', [WorkerController::class, 'show']);
        Route::put('workers/{id}', [WorkerController::class, 'update']);
        Route::delete('workers/{id}', [WorkerController::class, 'destroy']);
    });

    // TASKS - Read (All authenticated - for dropdowns)
    Route::get('tasks/simple', [TaskController::class, 'listSimple']);

    // TASKS - CRUD (Admin, Liquidador)
    Route::middleware('role:admin,liquidador')->group(function () {
        Route::get('tasks', [TaskController::class, 'index']);
        Route::post('tasks', [TaskController::class, 'store']);
        Route::get('tasks/{id}', [TaskController::class, 'show']);
        Route::put('tasks/{id}', [TaskController::class, 'update']);
        Route::delete('tasks/{id}', [TaskController::class, 'destroy']);
        Route::get('tasks/{id}/net-amount', [TaskController::class, 'getNetAmount']);

        // Task Deductions
        Route::post('tasks/{id}/deductions', [TaskController::class, 'storeDeduction']);
        Route::put('tasks/{id}/deductions/{deductionId}', [TaskController::class, 'updateDeduction']);
        Route::delete('tasks/{id}/deductions/{deductionId}', [TaskController::class, 'destroyDeduction']);
    });

    // DAILY ASSIGNMENTS (Admin, Liquidador)
    Route::middleware('role:admin,liquidador')->group(function () {
        Route::get('daily-assignments', [DailyAssignmentController::class, 'index']);
        Route::post('daily-assignments', [DailyAssignmentController::class, 'store']);
        Route::get('daily-assignments/template', [DailyAssignmentController::class, 'downloadTemplate']);
        Route::post('daily-assignments/preview', [DailyAssignmentController::class, 'preview']);
        Route::post('daily-assignments/process', [DailyAssignmentController::class, 'process']);
    });

    // LIQUIDATION REPORTS (Admin, Liquidador, Supervisor, Financiero)
    Route::middleware('role:admin,liquidador,supervisor,financiero')->group(function () {
        Route::post('reports/liquidation', [LiquidationReportController::class, 'generate']);
        Route::get('reports/liquidation/export-excel', [LiquidationReportController::class, 'exportExcel']);
        Route::get('reports/liquidation/export-pdf', [LiquidationReportController::class, 'exportPdf']);

        // LIQUIDATION ANALYTICS REPORTS (FUN-001 to FUN-005)
        Route::post('reports/analytics/labor-costs', [LiquidationAnalyticsController::class, 'laborCosts']);
        Route::post('reports/analytics/worker-productivity', [LiquidationAnalyticsController::class, 'workerProductivity']);
        Route::post('reports/analytics/task-analysis', [LiquidationAnalyticsController::class, 'taskAnalysis']);
        Route::post('reports/analytics/deductions-breakdown', [LiquidationAnalyticsController::class, 'deductionsBreakdown']);
        Route::post('reports/analytics/period-comparison', [LiquidationAnalyticsController::class, 'periodComparison']);
        Route::get('reports/analytics/{type}/export-excel', [LiquidationAnalyticsController::class, 'exportExcel']);
        Route::get('reports/analytics/{type}/export-pdf', [LiquidationAnalyticsController::class, 'exportPdf']);
    });
});

/*
|--------------------------------------------------------------------------
| ROLE PERMISSIONS SUMMARY
|--------------------------------------------------------------------------
|
| ADMIN (admin):
|   - Full access to everything
|   - User management
|   - All CRUD operations
|   - Can approve outputs
|
| PURCHASING (purchasing) - Encargado de Compras:
|   - Master data: products, brands, suppliers, locations, packaging/base units (CRUD)
|   - Purchases (CRUD)
|   - Product outputs (CRUD)
|   - Receptions (CRUD)
|
| AGRONOMIST (agronomist):
|   - Technical recipes (CRUD)
|   - Technical orders (CRUD)
|   - Product outputs (CRUD)
|   - Receptions (CRUD)
|
| WAREHOUSE (warehouse) - Bodeguero:
|   - Product outputs (CRUD)
|   - Receptions (CRUD)
|
| FARM (farm) - Operario de Finca:
|   - Product outputs (CRUD)
|   - Receptions (CRUD)
|
| SUPERVISOR (supervisor):
|   - Product outputs (CRUD + approve)
|   - Receptions (CRUD)
|   - Inventory (view)
|   - Reports (view)
|   - Alerts management
|   - Technical orders (view only)
|
| FINANCIERO (financiero):
|   - Reports (view + export)
|   - Inventory (view)
|   - Product outputs (CRUD)
|   - Receptions (CRUD)
|   - Alerts management
|
| ALL AUTHENTICATED:
|   - Dashboard
|   - Products, locations, brands, suppliers (read)
|   - Output types (read)
|   - Inventory (read)
|   - Applications (read)
|   - Alerts (read)
|   - Receptions (read)
|   - Purchases (read)
|   - Farm lots (read)
|
*/
