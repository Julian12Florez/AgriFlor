<?php

use App\Http\Controllers\Api\AdjustmentController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CompanyController;
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
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TaskCatalogController;
use App\Http\Controllers\Api\PerformanceSettingsController;
use App\Http\Controllers\Api\TaskScheduleController;
use App\Http\Controllers\Api\PerformanceReportController;
use App\Http\Controllers\Api\TaskCategoryController;
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

    // NOTA: 'refresh' vive fuera del middleware auth:api a propósito. El guard JWT
    // (JWTGuard::user() -> JWT::check()) traga la TokenExpiredException y devuelve
    // false en cuanto el token expiró, así que auth:api respondería 401 ANTES de que
    // el controlador llegue a ejecutar JWTAuth::refresh() (que sí sabe aceptar un
    // token ya expirado, pero todavía dentro de la ventana de JWT_REFRESH_TTL).
    // El propio AuthController::refresh() valida el token (firma + ventana de refresh)
    // con JWTAuth::refresh(), así que sigue siendo seguro sin el middleware.
    Route::post('refresh', [AuthController::class, 'refresh']);
});

// ============================================
// PROTECTED ROUTES (JWT Authentication Required)
// ============================================

Route::middleware('auth:api')->group(function () {
    /*
    |----------------------------------------------------------------------
    | CÓMO SE DECIDE EL ACCESO (desde el 7-oct-2026)
    |----------------------------------------------------------------------
    | Cada ruta de ESCRITURA pide un permiso: ->middleware('permission:<nombre>').
    | Los permisos viven en App\Support\PermissionCatalog y quién los tiene se
    | guarda en la base (role_permission): es lo que el administrador configura
    | en la pantalla de Perfiles. Antes eran listas `role:admin,supervisor,...`
    | escritas aquí.
    |
    | Los comentarios "(Admin, Purchasing...)" de cada bloque son los perfiles
    | que tenían ese acceso el día del cambio, no una regla vigente.
    |
    | Siguen por NOMBRE de perfil, a propósito:
    |   - role:auditor  (auditoría: ni el administrador la ve)
    |   - role:admin    (mantenimiento: admin/clean-data, run-migrations...)
    |
    | Las LECTURAS siguen abiertas a cualquier usuario con sesión: las pantallas
    | se cruzan entre módulos (Salidas lee ubicaciones, productos, empresas...).
    | Lo que el perfil controla es qué pantallas abre y qué puede guardar.
    |
    | Una ruta de escritura nueva DEBE llevar su permission:, agregarse al
    | catálogo y a tests/Fixtures/route_access_antes_de_perfiles.json
    | (PermissionParityTest falla si aparece una ruta sin declarar).
    */

    // ----------------------------------------
    // AUTH ROUTES
    // ----------------------------------------
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
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
    Route::apiResource('users', UserController::class)->middleware('permission:manage_users');
    Route::patch('users/{id}/status', [UserController::class, 'updateStatus'])->middleware('permission:manage_users');

    // ----------------------------------------
    // PERFILES Y PERMISOS (Administración → Perfiles)
    // Aquí se configura lo que piden todas las demás rutas de este archivo.
    // El perfil de acceso total no se edita y el auditor no existe para esta
    // pantalla (ver RoleController).
    // ----------------------------------------
    // Lista corta para el selector de perfil del formulario de usuario.
    Route::get('roles/options', [RoleController::class, 'options']);

    Route::middleware('permission:manage_roles')->group(function () {
        Route::get('roles', [RoleController::class, 'index']);
        Route::get('roles/catalog', [RoleController::class, 'catalog']);
        Route::post('roles', [RoleController::class, 'store']);
        Route::put('roles/{id}', [RoleController::class, 'update']);
        Route::delete('roles/{id}', [RoleController::class, 'destroy']);
    });

    // ----------------------------------------
    // AUDITORÍA (quién hizo qué en el core) — SOLO LECTURA, SOLO rol 'auditor'
    // Requisito de seguridad: ningún otro rol (ni siquiera admin) puede verla.
    // role:auditor compara el nombre del rol de forma exacta => admin queda fuera.
    // ----------------------------------------
    Route::middleware('role:auditor')->group(function () {
        Route::get('audits', [\App\Http\Controllers\Api\AuditController::class, 'index']);
        Route::get('audits/filters', [\App\Http\Controllers\Api\AuditController::class, 'filters']);
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
    Route::post('products', [ProductController::class, 'store'])->middleware('permission:create_product');
    Route::put('products/{product}', [ProductController::class, 'update'])->middleware('permission:edit_product');
    Route::delete('products/{product}', [ProductController::class, 'destroy'])->middleware('permission:delete_product');

    // BRANDS - Read (All authenticated)
    Route::get('brands', [BrandController::class, 'index']);
    Route::get('brands/{brand}', [BrandController::class, 'show']);

    // BRANDS - Write (Admin, Purchasing)
    Route::post('brands', [BrandController::class, 'store'])->middleware('permission:create_master_data');
    Route::put('brands/{brand}', [BrandController::class, 'update'])->middleware('permission:edit_master_data');
    Route::delete('brands/{brand}', [BrandController::class, 'destroy'])->middleware('permission:delete_master_data');

    // COMPANIES - Read (All authenticated)
    // La lectura NO puede restringirse a admin: `company_id` es obligatorio al
    // crear compras y salidas, así que compras/bodega/finca necesitan poder
    // poblar el selector de empresa emisora. La administración (crear/editar/
    // logo) sí queda solo para admin, más abajo.
    Route::get('companies', [CompanyController::class, 'index']);
    Route::get('companies/{company}/logo', [CompanyController::class, 'showLogo']);
    Route::get('companies/{company}', [CompanyController::class, 'show']);

    // COMPANIES - Write (Admin)
    Route::post('companies', [CompanyController::class, 'store'])->middleware('permission:manage_companies');
    Route::put('companies/{company}', [CompanyController::class, 'update'])->middleware('permission:manage_companies');
    Route::post('companies/{company}/logo', [CompanyController::class, 'uploadLogo'])->middleware('permission:manage_companies');
    Route::delete('companies/{company}/logo', [CompanyController::class, 'deleteLogo'])->middleware('permission:manage_companies');

    // CATEGORIES - Read (All authenticated)
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{category}', [CategoryController::class, 'show']);

    // CATEGORIES - Write (Admin, Purchasing)
    Route::post('categories', [CategoryController::class, 'store'])->middleware('permission:create_master_data');
    Route::put('categories/{category}', [CategoryController::class, 'update'])->middleware('permission:edit_master_data');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:delete_master_data');

    // SUPPLIERS - Read (All authenticated)
    Route::get('suppliers', [SupplierController::class, 'index']);
    Route::get('suppliers/{supplier}', [SupplierController::class, 'show']);

    // SUPPLIERS - Write (Admin, Purchasing)
    Route::post('suppliers', [SupplierController::class, 'store'])->middleware('permission:create_master_data');
    Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->middleware('permission:edit_master_data');
    Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->middleware('permission:delete_master_data');
    Route::post('suppliers/{id}/contacts', [SupplierController::class, 'addContact'])->middleware('permission:edit_master_data');
    Route::delete('suppliers/{id}/contacts/{contactId}', [SupplierController::class, 'removeContact'])->middleware('permission:edit_master_data');

    // LOCATIONS - Read (All authenticated - needed for outputs/receptions)
    Route::get('locations', [LocationController::class, 'index']);
    Route::get('locations/{location}', [LocationController::class, 'show']);
    Route::get('locations/type/warehouses', [LocationController::class, 'warehouses']);
    Route::get('locations/type/farms', [LocationController::class, 'farms']);

    // LOCATIONS - Write (Admin, Warehouse, Supervisor, Purchasing)
    Route::post('locations', [LocationController::class, 'store'])->middleware('permission:create_location');
    Route::put('locations/{location}', [LocationController::class, 'update'])->middleware('permission:edit_location');
    Route::delete('locations/{location}', [LocationController::class, 'destroy'])->middleware('permission:delete_location');

    // FARM LOTS - Read (All authenticated - needed for outputs/receptions)
    Route::get('farm-lots', [FarmLotController::class, 'index']);
    Route::get('farm-lots/{id}', [FarmLotController::class, 'show']);
    Route::get('locations/{locationId}/farm-lots', [FarmLotController::class, 'getByLocation']);

    // FARM LOTS - Write (Admin, Warehouse)
    Route::post('farm-lots', [FarmLotController::class, 'store'])->middleware('permission:create_farm_lot');
    Route::put('farm-lots/{id}', [FarmLotController::class, 'update'])->middleware('permission:edit_farm_lot');
    Route::delete('farm-lots/{id}', [FarmLotController::class, 'destroy'])->middleware('permission:delete_farm_lot');

    // OUTPUT TYPES (All authenticated users can view)
    Route::get('output-types', [OutputTypeController::class, 'index']);
    Route::get('output-types/{id}', [OutputTypeController::class, 'show']);

    // OUTPUT TYPES - Write operations (Admin only)
    Route::post('output-types', [OutputTypeController::class, 'store'])->middleware('permission:manage_output_types');
    Route::put('output-types/{id}', [OutputTypeController::class, 'update'])->middleware('permission:manage_output_types');
    Route::delete('output-types/{id}', [OutputTypeController::class, 'destroy'])->middleware('permission:manage_output_types');

    // PACKAGING UNITS - Read (All authenticated)
    Route::get('packaging-units', [PackagingUnitController::class, 'index']);
    Route::get('packaging-units/{packaging_unit}', [PackagingUnitController::class, 'show']);

    // PACKAGING UNITS - Write (Admin, Purchasing)
    Route::post('packaging-units', [PackagingUnitController::class, 'store'])->middleware('permission:create_master_data');
    Route::put('packaging-units/{packaging_unit}', [PackagingUnitController::class, 'update'])->middleware('permission:edit_master_data');
    Route::delete('packaging-units/{packaging_unit}', [PackagingUnitController::class, 'destroy'])->middleware('permission:delete_master_data');

    // BASE UNITS - Read (All authenticated)
    Route::get('base-units', [BaseUnitController::class, 'index']);
    Route::get('base-units/{base_unit}', [BaseUnitController::class, 'show']);

    // BASE UNITS - Write (Admin, Purchasing)
    Route::post('base-units', [BaseUnitController::class, 'store'])->middleware('permission:create_master_data');
    Route::put('base-units/{base_unit}', [BaseUnitController::class, 'update'])->middleware('permission:edit_master_data');
    Route::delete('base-units/{base_unit}', [BaseUnitController::class, 'destroy'])->middleware('permission:delete_master_data');

    // ----------------------------------------
    // TECHNICAL PROCESSES
    // ----------------------------------------

    // TECHNICAL RECIPES (Admin, Agronomist)
    Route::apiResource('technical-recipes', TechnicalRecipeController::class)->only(['index', 'show'])->middleware('permission:view_recipes');
    Route::apiResource('technical-recipes', TechnicalRecipeController::class)->only(['store'])->middleware('permission:create_recipe');
    Route::apiResource('technical-recipes', TechnicalRecipeController::class)->only(['update'])->middleware('permission:edit_recipe');
    Route::apiResource('technical-recipes', TechnicalRecipeController::class)->only(['destroy'])->middleware('permission:delete_recipe');
    Route::post('technical-recipes/{id}/duplicate', [TechnicalRecipeController::class, 'duplicate'])->middleware('permission:create_recipe');

    // TECHNICAL ORDERS (Admin, Agronomist, Supervisor - View only)
    Route::get('technical-orders', [TechnicalOrderController::class, 'index'])->middleware('permission:view_technical_orders');
    Route::get('technical-orders/{id}', [TechnicalOrderController::class, 'show'])->middleware('permission:view_technical_orders');

    // TECHNICAL ORDERS - Write operations (Admin, Agronomist only)
    Route::post('technical-orders', [TechnicalOrderController::class, 'store'])->middleware('permission:create_technical_order');
    Route::put('technical-orders/{id}', [TechnicalOrderController::class, 'update'])->middleware('permission:edit_technical_order');
    Route::delete('technical-orders/{id}', [TechnicalOrderController::class, 'destroy'])->middleware('permission:delete_technical_order');
    Route::post('technical-orders/{id}/approve', [TechnicalOrderController::class, 'approve'])->middleware('permission:process_technical_order');
    Route::post('technical-orders/{id}/complete', [TechnicalOrderController::class, 'complete'])->middleware('permission:process_technical_order');
    Route::post('technical-orders/{id}/cancel', [TechnicalOrderController::class, 'cancel'])->middleware('permission:process_technical_order');

    // ----------------------------------------
    // WAREHOUSE MANAGEMENT
    // ----------------------------------------

    // PURCHASES - Read (All authenticated - needed for receptions from purchases)
    Route::get('purchases', [PurchaseController::class, 'index']);
    Route::get('purchases/{id}', [PurchaseController::class, 'show']);
    Route::get('purchases/{id}/export-pdf', [PurchaseController::class, 'exportPdf']);

    // PURCHASES - Write operations (Admin, Purchasing, Warehouse)
    Route::post('purchases', [PurchaseController::class, 'store'])->middleware('permission:create_purchase');
    Route::put('purchases/{id}', [PurchaseController::class, 'update'])->middleware('permission:edit_purchase');
    Route::delete('purchases/{id}', [PurchaseController::class, 'destroy'])->middleware('permission:delete_purchase');
    Route::post('purchases/{id}/attachments', [PurchaseController::class, 'addAttachment'])->middleware('permission:edit_purchase');
    Route::delete('purchases/{id}/attachments/{attachmentId}', [PurchaseController::class, 'removeAttachment'])->middleware('permission:edit_purchase');
    Route::put('purchases/{id}/cancel', [PurchaseController::class, 'cancel'])->middleware('permission:edit_purchase');

    // PURCHASES - Eliminar revirtiendo inventario (también recibidas). Destructivo:
    // solo administrador, con motivo obligatorio que queda en la auditoría.
    Route::get('purchases/{id}/reversal-preview', [PurchaseController::class, 'reversalPreview'])->middleware('permission:reverse_purchase');
    Route::post('purchases/{id}/reverse', [PurchaseController::class, 'reverse'])->middleware('permission:reverse_purchase');

    // PRODUCT OUTPUTS - Read (All roles - todos deben tener acceso a salidas)
    Route::post('product-outputs/validate-inventory', [ProductOutputController::class, 'validateInventory'])->middleware('permission:view_outputs');
    Route::get('product-outputs', [ProductOutputController::class, 'index'])->middleware('permission:view_outputs');
    Route::get('product-outputs/{id}/remision-pdf', [ProductOutputController::class, 'exportRemisionPdf'])->middleware('permission:view_outputs');
    Route::get('product-outputs/{id}', [ProductOutputController::class, 'show'])->middleware('permission:view_outputs');

    // PRODUCT OUTPUTS - Write (All roles - todos deben tener acceso a salidas)
    Route::post('product-outputs', [ProductOutputController::class, 'store'])->middleware('permission:create_output');
    Route::put('product-outputs/{id}', [ProductOutputController::class, 'update'])->middleware('permission:edit_output');
    Route::delete('product-outputs/{id}', [ProductOutputController::class, 'destroy'])->middleware('permission:delete_output');
    Route::post('product-outputs/{id}/mark-in-transit', [ProductOutputController::class, 'markInTransit'])->middleware('permission:edit_output');
    Route::post('product-outputs/{id}/complete', [ProductOutputController::class, 'complete'])->middleware('permission:edit_output');

    // PRODUCT OUTPUTS - Approval (Admin, Supervisor only)
    Route::post('product-outputs/{id}/approve', [ProductOutputController::class, 'approve'])->middleware('permission:approve_output');

    // PRODUCT OUTPUTS - Applications from consumption outputs (Admin, Warehouse, Agronomist)
    Route::post('product-outputs/{id}/register-application', [ProductOutputController::class, 'registerApplication'])->middleware('permission:register_application');
    Route::get('product-outputs/{id}/applications', [ProductOutputController::class, 'getApplications'])->middleware('permission:register_application');

    // RECEPTIONS (All authenticated users can view)
    Route::get('receptions/available-sources', [ReceptionController::class, 'availableSources']);
    Route::get('receptions/available-sources-for-responsible', [ReceptionController::class, 'availableSourcesForResponsible']);
    Route::get('receptions', [ReceptionController::class, 'index']);
    Route::get('receptions/{id}', [ReceptionController::class, 'show']);
    Route::get('receptions/{id}/batches', [ReceptionController::class, 'getBatches']);
    Route::get('receptions/{id}/pending-products', [ReceptionController::class, 'getPendingProducts']);

    // RECEPTIONS - Write operations (All roles - todos deben tener acceso a recepciones)
    Route::post('receptions', [ReceptionController::class, 'store'])->middleware('permission:create_reception');
    Route::post('receptions/direct-reception', [ReceptionController::class, 'createReceptionWithBatch'])->middleware('permission:create_reception');
    Route::post('receptions/{id}/batches', [ReceptionController::class, 'addBatch'])->middleware('permission:create_reception');
    Route::put('receptions/{id}/complete', [ReceptionController::class, 'complete'])->middleware('permission:edit_reception');
    Route::put('receptions/{id}/cancel', [ReceptionController::class, 'cancel'])->middleware('permission:edit_reception');
    Route::post('receptions/{id}/close-with-available', [ReceptionController::class, 'closeOutputReception'])->middleware('permission:edit_reception');
    // Finaliza la recepción con lo ya recibido y libera el remanente (sin mover inventario)
    Route::post('receptions/{id}/finalize', [ReceptionController::class, 'finalizeReception'])->middleware('permission:edit_reception');

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
    Route::get('inventory/farm-monthly-report', [InventoryController::class, 'farmMonthlyReport']);
    Route::get('inventory/farm-entries-report', [InventoryController::class, 'farmEntriesReport']);
    Route::get('inventory/product-listing', [InventoryController::class, 'productListingReport']);
    Route::get('inventory/location/{locationId}', [InventoryController::class, 'byLocation']);
    Route::get('inventory/product/{productId}/details', [InventoryController::class, 'byProduct']);
    Route::get('inventory/{productId}', [InventoryController::class, 'show']);

    // NOTA: aquí vivía POST inventory/adjustments (InventoryController::adjustment),
    // eliminada al entrar el módulo de solicitudes de ajuste. Era un atajo sin
    // ningún llamador en el frontend y peligroso: escribía siempre sobre un lote
    // ficticio 'MANUAL' (sin FIFO ni conversión de unidades), aceptaba
    // `type=adjustment` —que NO existe en el enum de inventory_movements— y dejaba
    // `related_document_type` en NULL, con lo que sus entradas se contaban como
    // COMPRAS en el inventario mensual. Todo ajuste pasa ahora por
    // /api/adjustments, con solicitud + aprobación y trazabilidad.

    // ----------------------------------------
    // ADJUSTMENTS (módulo de solicitudes de ajuste de inventario)
    // Crear/leer: CUALQUIER rol autenticado (el cliente pidió que cualquier
    // rol pueda registrar solicitudes). El aislamiento por ubicación limita
    // qué ajustes ve cada usuario en index(), no quién puede crear.
    // Aprobar: SOLO admin, porque es la única acción que toca el inventario.
    // ----------------------------------------
    Route::get('adjustment-reasons', [AdjustmentController::class, 'reasons']);
    Route::get('adjustments', [AdjustmentController::class, 'index']);
    Route::get('adjustments/{id}', [AdjustmentController::class, 'show']);
    Route::post('adjustments', [AdjustmentController::class, 'store']);
    // Cancelar: cualquier autenticado puede intentarlo, la autorización real
    // (solo el solicitante, solo si está pending) vive en el controlador.
    Route::put('adjustments/{id}/cancel', [AdjustmentController::class, 'cancel']);

    Route::put('adjustments/{id}/approve', [AdjustmentController::class, 'approve'])->middleware('permission:approve_adjustment');
    Route::put('adjustments/{id}/reject', [AdjustmentController::class, 'reject'])->middleware('permission:approve_adjustment');

    // ----------------------------------------
    // APPLICATIONS (Product Applications to Farm Lots)
    // ----------------------------------------

    // APPLICATIONS - Read operations (All authenticated users)
    Route::get('applications', [ApplicationController::class, 'index']);
    Route::get('applications/{id}', [ApplicationController::class, 'show']);

    // APPLICATIONS - Write operations (Admin, Warehouse, Agronomist)
    Route::post('applications', [ApplicationController::class, 'store'])->middleware('permission:register_application');
    Route::post('applications/{id}/cancel', [ApplicationController::class, 'cancel'])->middleware('permission:register_application');

    // APPLICATIONS - Approval (Admin, Warehouse only)
    Route::post('applications/{id}/approve', [ApplicationController::class, 'approve'])->middleware('permission:approve_application');

    // ----------------------------------------
    // ALERTS & REPORTS
    // ----------------------------------------

    // ALERTS - Read operations (All authenticated users)
    Route::get('alerts', [AlertController::class, 'index']);
    Route::get('alerts/{id}', [AlertController::class, 'show']);

    // ALERTS - Write operations (Admin, Supervisor, Warehouse, Financiero)
    Route::post('alerts', [AlertController::class, 'store'])->middleware('permission:manage_alerts');
    Route::put('alerts/{id}/resolve', [AlertController::class, 'resolve'])->middleware('permission:manage_alerts');
    Route::put('alerts/{id}/dismiss', [AlertController::class, 'dismiss'])->middleware('permission:manage_alerts');

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
    Route::get('workers', [WorkerController::class, 'index'])->middleware('permission:view_liquidation');
    Route::post('workers', [WorkerController::class, 'store'])->middleware('permission:create_liquidation');
    Route::get('workers/template', [WorkerController::class, 'downloadTemplate'])->middleware('permission:view_liquidation');
    Route::post('workers/preview', [WorkerController::class, 'preview'])->middleware('permission:create_liquidation');
    Route::post('workers/import', [WorkerController::class, 'processImport'])->middleware('permission:create_liquidation');
    Route::get('workers/{id}', [WorkerController::class, 'show'])->middleware('permission:view_liquidation');
    Route::put('workers/{id}', [WorkerController::class, 'update'])->middleware('permission:edit_liquidation');
    Route::delete('workers/{id}', [WorkerController::class, 'destroy'])->middleware('permission:delete_liquidation');

    // TASKS - Read (All authenticated - for dropdowns)
    Route::get('tasks/simple', [TaskController::class, 'listSimple']);

    // TASKS - CRUD (Admin, Liquidador)
    Route::get('tasks', [TaskController::class, 'index'])->middleware('permission:view_liquidation');
    Route::post('tasks', [TaskController::class, 'store'])->middleware('permission:create_liquidation');
    Route::get('tasks/{id}', [TaskController::class, 'show'])->middleware('permission:view_liquidation');
    Route::put('tasks/{id}', [TaskController::class, 'update'])->middleware('permission:edit_liquidation');
    Route::delete('tasks/{id}', [TaskController::class, 'destroy'])->middleware('permission:delete_liquidation');
    Route::get('tasks/{id}/net-amount', [TaskController::class, 'getNetAmount'])->middleware('permission:view_liquidation');

    // Task Deductions
    Route::post('tasks/{id}/deductions', [TaskController::class, 'storeDeduction'])->middleware('permission:create_liquidation');
    Route::put('tasks/{id}/deductions/{deductionId}', [TaskController::class, 'updateDeduction'])->middleware('permission:edit_liquidation');
    Route::delete('tasks/{id}/deductions/{deductionId}', [TaskController::class, 'destroyDeduction'])->middleware('permission:delete_liquidation');

    // DAILY ASSIGNMENTS (Admin, Liquidador)
    Route::get('daily-assignments', [DailyAssignmentController::class, 'index'])->middleware('permission:view_liquidation');
    Route::post('daily-assignments', [DailyAssignmentController::class, 'store'])->middleware('permission:create_liquidation');
    Route::get('daily-assignments/template', [DailyAssignmentController::class, 'downloadTemplate'])->middleware('permission:view_liquidation');
    Route::post('daily-assignments/preview', [DailyAssignmentController::class, 'preview'])->middleware('permission:create_liquidation');
    Route::post('daily-assignments/process', [DailyAssignmentController::class, 'process'])->middleware('permission:create_liquidation');

    // LIQUIDATION REPORTS (Admin, Liquidador, Supervisor, Financiero)
    Route::post('reports/liquidation', [LiquidationReportController::class, 'generate'])->middleware('permission:view_liquidation_reports');
    Route::get('reports/liquidation/export-excel', [LiquidationReportController::class, 'exportExcel'])->middleware('permission:view_liquidation_reports');
    Route::get('reports/liquidation/export-pdf', [LiquidationReportController::class, 'exportPdf'])->middleware('permission:view_liquidation_reports');

    // LIQUIDATION ANALYTICS REPORTS (FUN-001 to FUN-005)
    Route::post('reports/analytics/labor-costs', [LiquidationAnalyticsController::class, 'laborCosts'])->middleware('permission:view_liquidation_reports');
    Route::post('reports/analytics/worker-productivity', [LiquidationAnalyticsController::class, 'workerProductivity'])->middleware('permission:view_liquidation_reports');
    Route::post('reports/analytics/task-analysis', [LiquidationAnalyticsController::class, 'taskAnalysis'])->middleware('permission:view_liquidation_reports');
    Route::post('reports/analytics/deductions-breakdown', [LiquidationAnalyticsController::class, 'deductionsBreakdown'])->middleware('permission:view_liquidation_reports');
    Route::post('reports/analytics/period-comparison', [LiquidationAnalyticsController::class, 'periodComparison'])->middleware('permission:view_liquidation_reports');
    Route::get('reports/analytics/{type}/export-excel', [LiquidationAnalyticsController::class, 'exportExcel'])->middleware('permission:view_liquidation_reports');
    Route::get('reports/analytics/{type}/export-pdf', [LiquidationAnalyticsController::class, 'exportPdf'])->middleware('permission:view_liquidation_reports');

    // ═══════════════════════════════════════════════════════════════
    // PERFORMANCE MODULE (Rendimiento de Tareas Agricolas)
    // ═══════════════════════════════════════════════════════════════

    // Task Categories
    Route::get('performance/task-categories', [TaskCategoryController::class, 'index']);
    Route::post('performance/task-categories', [TaskCategoryController::class, 'store'])->middleware('permission:create_task_catalog');
    Route::put('performance/task-categories/{id}', [TaskCategoryController::class, 'update'])->middleware('permission:edit_task_catalog');
    Route::delete('performance/task-categories/{id}', [TaskCategoryController::class, 'destroy'])->middleware('permission:delete_task_catalog');

    // Worker Availability
    Route::get('performance/worker-availability', [TaskScheduleController::class, 'workerAvailability']);

    // Task Catalog - Read
    Route::get('performance/task-catalog', [TaskCatalogController::class, 'index']);
    Route::get('performance/task-catalog/categories', [TaskCatalogController::class, 'categories']);
    Route::get('performance/task-catalog/{id}', [TaskCatalogController::class, 'show']);

    // Performance Settings - Read
    Route::get('performance/settings', [PerformanceSettingsController::class, 'show']);

    // Schedules - Read
    Route::get('performance/schedules', [TaskScheduleController::class, 'index']);
    Route::get('performance/schedules/{id}', [TaskScheduleController::class, 'show']);
    Route::get('performance/schedules/{id}/logs', [TaskScheduleController::class, 'logs']);

    // Panel del Dia
    Route::get('performance/panel', [TaskScheduleController::class, 'panel']);

    // Estimacion Ad-Hoc
    Route::post('performance/schedules/estimate-ad-hoc', [TaskScheduleController::class, 'estimateAdHoc']);

    // Task Catalog - Write (admin, supervisor)
    Route::post('performance/task-catalog', [TaskCatalogController::class, 'store'])->middleware('permission:create_task_catalog');
    Route::put('performance/task-catalog/{id}', [TaskCatalogController::class, 'update'])->middleware('permission:edit_task_catalog');
    Route::patch('performance/task-catalog/{id}/toggle', [TaskCatalogController::class, 'toggleActive'])->middleware('permission:edit_task_catalog');

    // Performance Settings - Write (admin only)
    Route::put('performance/settings', [PerformanceSettingsController::class, 'update'])->middleware('permission:manage_performance_settings');

    // Schedules - Write (admin, supervisor)
    Route::post('performance/schedules', [TaskScheduleController::class, 'store'])->middleware('permission:create_schedule');
    Route::put('performance/schedules/{id}', [TaskScheduleController::class, 'update'])->middleware('permission:edit_schedule');
    Route::post('performance/schedules/{id}/cancel', [TaskScheduleController::class, 'cancel'])->middleware('permission:edit_schedule');

    // Daily Performance Report (read all)
    Route::get('performance/schedules/{id}/daily-performance', [TaskScheduleController::class, 'dailyPerformance']);

    // Daily Logs - Write (admin, supervisor, farm_operator)
    Route::post('performance/schedules/{id}/logs', [TaskScheduleController::class, 'storeLog'])->middleware('permission:register_task_log');

    // Finalize manual (admin, supervisor)
    Route::post('performance/schedules/{id}/finalize', [TaskScheduleController::class, 'finalize'])->middleware('permission:finalize_schedule');

    // Daily Logs - Delete (admin only)
    Route::delete('performance/logs/{id}', [TaskScheduleController::class, 'deleteLog'])->middleware('permission:delete_task_log');

    // Performance Reports (all authenticated)
    Route::get('performance/reports/by-task', [PerformanceReportController::class, 'performanceByTask']);
    Route::get('performance/reports/compliance', [PerformanceReportController::class, 'compliance']);
    Route::get('performance/reports/gantt', [PerformanceReportController::class, 'gantt']);
    Route::post('performance/reports/farm-comparison', [PerformanceReportController::class, 'farmComparison']);
    Route::get('performance/reports/projection', [PerformanceReportController::class, 'projection']);
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
| PERFORMANCE MODULE (Rendimiento de Tareas Agricolas):
|   - Task catalog: read (all), write (admin, supervisor)
|   - Settings: read (all), write (admin)
|   - Schedules: read (all), write (admin, supervisor)
|   - Daily logs: read (all), write (admin, supervisor, farm_operator)
|   - Panel: read (all)
|
*/
