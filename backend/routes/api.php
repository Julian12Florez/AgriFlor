<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\ReceptionController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\TechnicalOrderController;
use App\Http\Controllers\Api\TechnicalRecipeController;
use App\Http\Controllers\Api\ProductOutputController;
use App\Http\Controllers\Api\ReportExportController;
use App\Http\Controllers\Api\PackagingUnitController;
use App\Http\Controllers\Api\LiquidationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/register', [AuthController::class, 'register']);

// Protected routes
Route::middleware('auth:api')->group(function () {
    // Auth
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::put('auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('auth/password', [AuthController::class, 'changePassword']);

    // Dashboard
    Route::get('dashboard/statistics', [DashboardController::class, 'getStatistics']);
    Route::get('dashboard/inventory-by-category', [DashboardController::class, 'getInventoryByCategory']);
    Route::get('dashboard/recent-activity', [DashboardController::class, 'getRecentActivity']);

    // USERS - Read (All authenticated)
    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{user}', [UserController::class, 'show']);

    // USERS - Write (Admin only)
    Route::middleware('role:admin')->group(function () {
        Route::post('users', [UserController::class, 'store']);
        Route::put('users/{user}', [UserController::class, 'update']);
        Route::delete('users/{user}', [UserController::class, 'destroy']);
        Route::post('users/{user}/toggle-status', [UserController::class, 'toggleStatus']);
    });

    // ROLES - Read (All authenticated)
    Route::get('roles', [RoleController::class, 'index']);
    Route::get('roles/{role}', [RoleController::class, 'show']);
    Route::get('permissions', [RoleController::class, 'permissions']);

    // ROLES - Write (Admin only)
    Route::middleware('role:admin')->group(function () {
        Route::post('roles', [RoleController::class, 'store']);
        Route::put('roles/{role}', [RoleController::class, 'update']);
        Route::delete('roles/{role}', [RoleController::class, 'destroy']);
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
    });

    // LOCATIONS - Read (All authenticated)
    Route::get('locations', [LocationController::class, 'index']);
    Route::get('locations/{location}', [LocationController::class, 'show']);

    // LOCATIONS - Write (Admin)
    Route::middleware('role:admin')->group(function () {
        Route::post('locations', [LocationController::class, 'store']);
        Route::put('locations/{location}', [LocationController::class, 'update']);
        Route::delete('locations/{location}', [LocationController::class, 'destroy']);
    });

    // PACKAGING UNITS - Read (All authenticated)
    Route::get('packaging-units', [PackagingUnitController::class, 'index']);
    Route::get('packaging-units/{packagingUnit}', [PackagingUnitController::class, 'show']);

    // PACKAGING UNITS - Write (Admin, Purchasing)
    Route::middleware('role:admin,purchasing')->group(function () {
        Route::post('packaging-units', [PackagingUnitController::class, 'store']);
        Route::put('packaging-units/{packagingUnit}', [PackagingUnitController::class, 'update']);
        Route::delete('packaging-units/{packagingUnit}', [PackagingUnitController::class, 'destroy']);
    });

    // PRODUCTS - Read (All authenticated)
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);
    Route::get('products-with-inventory', [ProductController::class, 'searchWithInventory']);
    Route::get('products-for-outputs', [ProductController::class, 'getForOutputs']);

    // PRODUCTS - Write (Admin, Purchasing)
    Route::middleware('role:admin,purchasing')->group(function () {
        Route::post('products', [ProductController::class, 'store']);
        Route::put('products/{product}', [ProductController::class, 'update']);
        Route::delete('products/{product}', [ProductController::class, 'destroy']);
    });

    // PURCHASES - Read (Admin, Purchasing, Warehouse)
    Route::middleware('role:admin,purchasing,warehouse')->group(function () {
        Route::get('purchases', [PurchaseController::class, 'index']);
        Route::get('purchases/{purchase}', [PurchaseController::class, 'show']);
    });

    // PURCHASES - Write (Admin, Purchasing)
    Route::middleware('role:admin,purchasing')->group(function () {
        Route::post('purchases', [PurchaseController::class, 'store']);
        Route::put('purchases/{purchase}', [PurchaseController::class, 'update']);
        Route::delete('purchases/{purchase}', [PurchaseController::class, 'destroy']);
        Route::post('purchases/{purchase}/approve', [PurchaseController::class, 'approve']);
        Route::post('purchases/{purchase}/cancel', [PurchaseController::class, 'cancel']);
    });

    // INVENTORY - Read (All authenticated)
    Route::get('inventory', [InventoryController::class, 'index']);
    Route::get('inventory/by-location', [InventoryController::class, 'byLocation']);
    Route::get('inventory/kardex', [InventoryController::class, 'getKardex']);
    Route::get('inventory/{inventory}', [InventoryController::class, 'show']);

    // INVENTORY - Adjustments (Admin, Warehouse)
    Route::middleware('role:admin,warehouse')->group(function () {
        Route::post('inventory/adjust', [InventoryController::class, 'adjust']);
    });

    // TRANSFERS - Read (Admin, Warehouse)
    Route::middleware('role:admin,warehouse')->group(function () {
        Route::get('transfers', [TransferController::class, 'index']);
        Route::get('transfers/{transfer}', [TransferController::class, 'show']);
        Route::post('transfers', [TransferController::class, 'store']);
        Route::put('transfers/{transfer}', [TransferController::class, 'update']);
        Route::delete('transfers/{transfer}', [TransferController::class, 'destroy']);
        Route::post('transfers/{transfer}/complete', [TransferController::class, 'complete']);
        Route::post('transfers/{transfer}/cancel', [TransferController::class, 'cancel']);
    });

    // RECEPTIONS - Read (Admin, Warehouse, Purchasing)
    Route::middleware('role:admin,warehouse,purchasing')->group(function () {
        Route::get('receptions', [ReceptionController::class, 'index']);
        Route::get('receptions/{reception}', [ReceptionController::class, 'show']);
        Route::get('receptions/{reception}/pending-items', [ReceptionController::class, 'getPendingItems']);
    });

    // RECEPTIONS - Write (Admin, Warehouse)
    Route::middleware('role:admin,warehouse')->group(function () {
        Route::post('receptions', [ReceptionController::class, 'store']);
        Route::post('receptions/{reception}/receive-batch', [ReceptionController::class, 'receiveBatch']);
        Route::post('receptions/{reception}/complete', [ReceptionController::class, 'complete']);
        Route::post('receptions/{reception}/cancel', [ReceptionController::class, 'cancel']);
    });

    // TECHNICAL RECIPES - Read (Admin, Technical, Warehouse)
    Route::middleware('role:admin,technical,warehouse')->group(function () {
        Route::get('technical-recipes', [TechnicalRecipeController::class, 'index']);
        Route::get('technical-recipes/{recipe}', [TechnicalRecipeController::class, 'show']);
    });

    // TECHNICAL RECIPES - Write (Admin, Technical)
    Route::middleware('role:admin,technical')->group(function () {
        Route::post('technical-recipes', [TechnicalRecipeController::class, 'store']);
        Route::put('technical-recipes/{recipe}', [TechnicalRecipeController::class, 'update']);
        Route::delete('technical-recipes/{recipe}', [TechnicalRecipeController::class, 'destroy']);
    });

    // TECHNICAL ORDERS - Read (Admin, Technical, Warehouse)
    Route::middleware('role:admin,technical,warehouse')->group(function () {
        Route::get('technical-orders', [TechnicalOrderController::class, 'index']);
        Route::get('technical-orders/{order}', [TechnicalOrderController::class, 'show']);
    });

    // TECHNICAL ORDERS - Write (Admin, Technical)
    Route::middleware('role:admin,technical')->group(function () {
        Route::post('technical-orders', [TechnicalOrderController::class, 'store']);
        Route::put('technical-orders/{order}', [TechnicalOrderController::class, 'update']);
        Route::delete('technical-orders/{order}', [TechnicalOrderController::class, 'destroy']);
        Route::post('technical-orders/{order}/approve', [TechnicalOrderController::class, 'approve']);
        Route::post('technical-orders/{order}/cancel', [TechnicalOrderController::class, 'cancel']);
        Route::post('technical-orders/{order}/complete', [TechnicalOrderController::class, 'complete']);
    });

    // PRODUCT OUTPUTS - Read (Admin, Technical, Warehouse)
    Route::middleware('role:admin,technical,warehouse')->group(function () {
        Route::get('product-outputs', [ProductOutputController::class, 'index']);
        Route::get('product-outputs/{output}', [ProductOutputController::class, 'show']);
    });

    // PRODUCT OUTPUTS - Write (Admin, Warehouse)
    Route::middleware('role:admin,warehouse')->group(function () {
        Route::post('product-outputs', [ProductOutputController::class, 'store']);
        Route::put('product-outputs/{output}', [ProductOutputController::class, 'update']);
        Route::delete('product-outputs/{output}', [ProductOutputController::class, 'destroy']);
        Route::post('product-outputs/{output}/complete', [ProductOutputController::class, 'complete']);
        Route::post('product-outputs/{output}/cancel', [ProductOutputController::class, 'cancel']);
    });

    // REPORTS
    Route::middleware('role:admin,technical,warehouse,purchasing')->group(function () {
        Route::get('reports/stock', [ReportExportController::class, 'stockReport']);
        Route::get('reports/stock/export/pdf', [ReportExportController::class, 'exportStockPdf']);
        Route::get('reports/stock/export/excel', [ReportExportController::class, 'exportStockExcel']);
        Route::get('reports/consumption', [ReportExportController::class, 'consumptionReport']);
        Route::get('reports/consumption/export/pdf', [ReportExportController::class, 'exportConsumptionPdf']);
        Route::get('reports/consumption/export/excel', [ReportExportController::class, 'exportConsumptionExcel']);
        Route::get('reports/kardex/export/pdf', [ReportExportController::class, 'exportKardexPdf']);
        Route::get('reports/kardex/export/excel', [ReportExportController::class, 'exportKardexExcel']);
    });

    // LIQUIDATIONS
    Route::middleware('role:admin,technical,warehouse')->group(function () {
        // Workers
        Route::get('liquidation/workers', [LiquidationController::class, 'indexWorkers']);
        Route::post('liquidation/workers', [LiquidationController::class, 'storeWorker']);
        Route::put('liquidation/workers/{worker}', [LiquidationController::class, 'updateWorker']);
        Route::delete('liquidation/workers/{worker}', [LiquidationController::class, 'destroyWorker']);

        // Tasks
        Route::get('liquidation/tasks', [LiquidationController::class, 'indexTasks']);
        Route::post('liquidation/tasks', [LiquidationController::class, 'storeTask']);
        Route::put('liquidation/tasks/{task}', [LiquidationController::class, 'updateTask']);
        Route::delete('liquidation/tasks/{task}', [LiquidationController::class, 'destroyTask']);

        // Assignments
        Route::get('liquidation/assignments', [LiquidationController::class, 'indexAssignments']);
        Route::post('liquidation/assignments', [LiquidationController::class, 'storeAssignment']);
        Route::put('liquidation/assignments/{assignment}', [LiquidationController::class, 'updateAssignment']);
        Route::delete('liquidation/assignments/{assignment}', [LiquidationController::class, 'destroyAssignment']);

        // Reports
        Route::get('liquidation/report', [LiquidationController::class, 'generateReport']);
        Route::get('liquidation/report/export/pdf', [LiquidationController::class, 'exportReportPdf']);
        Route::get('liquidation/report/export/excel', [LiquidationController::class, 'exportReportExcel']);
    });
});
