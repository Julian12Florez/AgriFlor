<?php

use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BrandController;
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
    // USER MANAGEMENT (Admin only)
    // ----------------------------------------
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::patch('users/{id}/status', [UserController::class, 'updateStatus']);
    });

    // ----------------------------------------
    // MASTER DATA MODULES
    // ----------------------------------------

    // PRODUCTS (Admin, Agronomist, Warehouse)
    Route::middleware('role:admin,agronomist,warehouse')->group(function () {
        Route::post('products/search-with-inventory', [ProductController::class, 'searchWithInventory']);
        Route::get('products-for-outputs', [ProductController::class, 'getForOutputs']);
        Route::apiResource('products', ProductController::class);
    });

    // BRANDS (Admin, Warehouse)
    Route::middleware('role:admin,warehouse')->group(function () {
        Route::apiResource('brands', BrandController::class);
    });

    // SUPPLIERS (Admin, Warehouse)
    Route::middleware('role:admin,warehouse')->group(function () {
        Route::apiResource('suppliers', SupplierController::class);
        Route::post('suppliers/{id}/contacts', [SupplierController::class, 'addContact']);
        Route::delete('suppliers/{id}/contacts/{contactId}', [SupplierController::class, 'removeContact']);
    });

    // LOCATIONS (Admin, Warehouse, Supervisor)
    Route::middleware('role:admin,warehouse,supervisor')->group(function () {
        Route::apiResource('locations', LocationController::class);
        Route::get('locations/type/warehouses', [LocationController::class, 'warehouses']);
        Route::get('locations/type/farms', [LocationController::class, 'farms']);
    });

    // FARM LOTS (Admin, Warehouse, Supervisor, Agronomist)
    Route::middleware('role:admin,warehouse,supervisor,agronomist')->group(function () {
        Route::get('farm-lots', [FarmLotController::class, 'index']);
        Route::get('farm-lots/{id}', [FarmLotController::class, 'show']);
        Route::get('locations/{locationId}/farm-lots', [FarmLotController::class, 'getByLocation']);
    });

    // FARM LOTS - Write operations (Admin, Warehouse)
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

    // PACKAGING UNITS (Admin, Warehouse)
    Route::middleware('role:admin,warehouse')->group(function () {
        Route::apiResource('packaging-units', PackagingUnitController::class);
    });

    // BASE UNITS (Admin)
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('base-units', BaseUnitController::class);
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

    // PURCHASES (Admin, Warehouse, Supervisor - View only)
    Route::middleware('role:admin,warehouse,supervisor')->group(function () {
        Route::get('purchases', [PurchaseController::class, 'index']);
        Route::get('purchases/{id}', [PurchaseController::class, 'show']);
    });

    // PURCHASES - Write operations (Admin, Warehouse only)
    Route::middleware('role:admin,warehouse')->group(function () {
        Route::post('purchases', [PurchaseController::class, 'store']);
        Route::put('purchases/{id}', [PurchaseController::class, 'update']);
        Route::delete('purchases/{id}', [PurchaseController::class, 'destroy']);
        Route::post('purchases/{id}/attachments', [PurchaseController::class, 'addAttachment']);
        Route::delete('purchases/{id}/attachments/{attachmentId}', [PurchaseController::class, 'removeAttachment']);
    });

    // PRODUCT OUTPUTS (Admin, Warehouse, Supervisor)
    Route::middleware('role:admin,warehouse,supervisor')->group(function () {
        Route::post('product-outputs/validate-inventory', [ProductOutputController::class, 'validateInventory']);
        Route::get('product-outputs', [ProductOutputController::class, 'index']);
        Route::get('product-outputs/{id}', [ProductOutputController::class, 'show']);
    });

    // PRODUCT OUTPUTS - Write operations (Admin, Warehouse)
    Route::middleware('role:admin,warehouse')->group(function () {
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

    // RECEPTIONS - Write operations (Admin, Warehouse, Farm)
    Route::middleware('role:admin,warehouse,farm')->group(function () {
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

    // ALERTS - Write operations (Admin, Supervisor, Warehouse)
    Route::middleware('role:admin,supervisor,warehouse')->group(function () {
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
| AGRONOMIST (agronomist):
|   - Technical recipes (CRUD)
|   - Technical orders (CRUD)
|   - View products, inventory, reports
|
| WAREHOUSE (warehouse):
|   - Products, brands, suppliers (CRUD)
|   - Purchases (CRUD)
|   - Product outputs (CRUD, except approval)
|   - Receptions (CRUD)
|   - Inventory management
|   - Alerts management
|
| SUPERVISOR (supervisor):
|   - View everything
|   - Approve outputs
|   - Manage alerts
|   - Technical orders (view only)
|
| FARM (farm):
|   - Receptions in farms (add batches)
|   - View technical orders assigned
|   - View inventory
|
*/
