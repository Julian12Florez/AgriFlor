# LISTA COMPLETA DE ARCHIVOS - MÓDULOS PRODUCTO, COMPRA Y SALIDA

**Documento generado:** 2025-12-03  
**Proyecto:** AgriFlor - Sistema de Gestión de Inventario  
**Ruta base:** `/home/julian/Documentos/AgriFlor/`

---

## MÓDULO 1: PRODUCTO (PRODUCTS)

### Backend - Producto (5 archivos)

- [x] `/home/julian/Documentos/AgriFlor/backend/app/Models/Product.php`
  - Modelo Eloquent principal
  - UUID, campos: name, brand_id, category, base_unit, active_ingredient, min_stock, status
  - Relaciones: Brand, User, PackagingUnit, RecipeProduct, TechnicalRecipe, PurchaseItem, OutputProduct, Inventory

- [x] `/home/julian/Documentos/AgriFlor/backend/app/Http/Controllers/Api/ProductController.php`
  - Controlador REST
  - Métodos: index, show, store, update, destroy, searchWithInventory
  - Validación integrada con Form Requests

- [x] `/home/julian/Documentos/AgriFlor/backend/app/Http/Requests/StoreProductRequest.php`
  - Validación para creación
  - Reglas: name (required, string, max:255), category (in: fertilizante|pesticida|herbicida|fungicida), etc.

- [x] `/home/julian/Documentos/AgriFlor/backend/app/Http/Requests/UpdateProductRequest.php`
  - Validación para actualización
  - Reglas similares a StoreProductRequest

- [x] `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195347_create_products_table.php`
  - Creación de tabla `products`
  - Columnas: id (uuid), name, brand_id (fk), category, base_unit, active_ingredient, min_stock, status, description, created_by (fk), created_at, updated_at

### Frontend - Producto (5 archivos)

- [x] `/home/julian/Documentos/AgriFlor/frontend/src/pages/master/Products.tsx`
  - Página principal de gestión de productos
  - Tabla responsiva (móvil/desktop)
  - Modal para crear/editar
  - Filtros: búsqueda, categoría, estado
  - Mutations: createProductMutation, updateProductMutation, deleteProductMutation
  - Manejo de errores de validación del backend

- [x] `/home/julian/Documentos/AgriFlor/frontend/src/services/api.ts` (sección `productsApi`)
  - Funciones:
    - `list(params?)` - GET /products
    - `get(id)` - GET /products/{id}
    - `create(data)` - POST /products
    - `update(id, data)` - PUT /products/{id}
    - `delete(id)` - DELETE /products/{id}
    - `searchWithInventory(data)` - POST /products/search-with-inventory

- [x] `/home/julian/Documentos/AgriFlor/frontend/src/data/types.ts` (interfaz `Product`)
  - Definición TypeScript:
    ```typescript
    interface Product {
      id: string;
      name: string;
      category: string;
      baseUnit: string;
      activeIngredient: string;
      brandId: string;
      minStock: number;
      status: 'active' | 'inactive';
      description?: string;
      createdAt: Date;
      updatedAt: Date;
    }
    ```

- [x] `/home/julian/Documentos/AgriFlor/frontend/src/components/ResponsiveTable.tsx`
  - Componente de tabla adaptable
  - Props: mobileColumns, desktopColumns, dataSource, loading, pagination, etc.
  - Usado por Products.tsx

---

## MÓDULO 2: COMPRA (PURCHASES)

### Backend - Compra (10 archivos)

#### Modelos (3 archivos)

- [x] `/home/julian/Documentos/AgriFlor/backend/app/Models/Purchase.php`
  - Modelo principal de compra
  - UUID, timestamps: false
  - Campos: order_number, supplier_id, destination_location_id, purchase_date, expected_delivery, status, subtotal, tax, total, observations, created_by, received_by, received_at
  - Relaciones: Supplier, Location (destination), User (creator/receiver), PurchaseItem, PurchaseAttachment, Reception, InventoryMovement
  - Scopes: byStatus, pending, received

- [x] `/home/julian/Documentos/AgriFlor/backend/app/Models/PurchaseItem.php`
  - Modelo para items de compra
  - UUID
  - Campos: purchase_id (fk), product_id (fk), quantity, unit, unit_price, subtotal
  - Relación: belongsTo Purchase, Product

- [x] `/home/julian/Documentos/AgriFlor/backend/app/Models/PurchaseAttachment.php`
  - Modelo para adjuntos de compra
  - UUID
  - Campos: purchase_id (fk), file_path, file_name, file_size
  - Relación: belongsTo Purchase

#### Controlador (1 archivo)

- [x] `/home/julian/Documentos/AgriFlor/backend/app/Http/Controllers/Api/PurchaseController.php`
  - Métodos: index, show, store, update, destroy
  - Métodos adicionales para adjuntos: attachAttachment, deleteAttachment
  - Validación con StorePurchaseRequest y UpdatePurchaseRequest

#### Validaciones (2 archivos)

- [x] `/home/julian/Documentos/AgriFlor/backend/app/Http/Requests/StorePurchaseRequest.php`
  - Validaciones para creación
  - Reglas para: order_number, supplier_id, destination_location_id, purchase_date, expected_delivery, status
  - Validación de items: product_id, quantity, unit_price

- [x] `/home/julian/Documentos/AgriFlor/backend/app/Http/Requests/UpdatePurchaseRequest.php`
  - Validaciones para actualización
  - Similar a StorePurchaseRequest con ajustes

#### Transformador (1 archivo)

- [x] `/home/julian/Documentos/AgriFlor/backend/app/Http/Resources/PurchaseResource.php`
  - Transformación de datos de Purchase a JSON
  - Incluye relaciones cargadas

#### Migraciones (3 archivos)

- [x] `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195400_create_purchases_table.php`
  - Tabla `purchases`
  - Columnas principales + relaciones

- [x] `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195401_create_purchase_items_table.php`
  - Tabla `purchase_items`
  - Relaciones: purchase_id, product_id

- [x] `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195402_create_purchase_attachments_table.php`
  - Tabla `purchase_attachments`
  - Campos de archivo: file_path, file_name, file_size

### Frontend - Compra (8 archivos)

#### Página Principal (1 archivo)

- [x] `/home/julian/Documentos/AgriFlor/frontend/src/pages/purchases/Purchases.tsx`
  - Página de gestión de compras
  - Tabla responsiva con estado visual (iconos, colores)
  - Drawer para vista detallada con items y adjuntos
  - Modal para crear/editar compra
  - Tabla anidada de PurchaseItem dentro del formulario
  - Carga de archivos para adjuntos
  - Filtros: búsqueda, estado
  - Mutations: create, update, delete, addAttachment, deleteAttachment
  - Descarga de PDF de orden de compra
  - Cambio de estado: ordered → in_transit → received

#### Servicio API (1 archivo - sección)

- [x] `/home/julian/Documentos/AgriFlor/frontend/src/services/api.ts` (sección `purchasesApi`)
  - Funciones:
    - `list(params?)` - GET /purchases
    - `get(id)` - GET /purchases/{id}
    - `create(data)` - POST /purchases
    - `update(id, data)` - PUT /purchases/{id}
    - `delete(id)` - DELETE /purchases/{id}
    - `addAttachment(id, file)` - POST /purchases/{id}/attachments
    - `deleteAttachment(id, attachmentId)` - DELETE /purchases/{id}/attachments/{attachmentId}

#### Utilidades PDF (1 archivo)

- [x] `/home/julian/Documentos/AgriFlor/frontend/src/utils/pdfGenerator.ts`
  - Funciones:
    - `downloadPurchaseOrderPDF(purchase)` - Descarga PDF de orden de compra
    - `openPurchaseOrderPDF(purchase)` - Abre PDF en nueva pestaña

#### Datos Simulados (1 archivo)

- [x] `/home/julian/Documentos/AgriFlor/frontend/src/mock/purchases.ts`
  - Datos de ejemplo para pruebas

#### Componentes Reutilizables (1 archivo)

- [x] `/home/julian/Documentos/AgriFlor/frontend/src/components/ResponsiveTable.tsx`
  - Ya listado en Products

#### Tipos TypeScript (1 archivo - sección)

- [x] `/home/julian/Documentos/AgriFlor/frontend/src/data/types.ts` (interfaces `Purchase`, `PurchaseItem`)
  - Definiciones de interfaces principales para el módulo

#### Configuración (1 archivo)

- [x] `/home/julian/Documentos/AgriFlor/frontend/src/config/theme.ts`
  - Colores, estilos globales

#### Enrutamiento (1 archivo)

- [x] `/home/julian/Documentos/AgriFlor/frontend/src/main.tsx`
  - Configuración de rutas (incluye ruta de Purchases)

---

## MÓDULO 3: SALIDA (PRODUCT OUTPUTS)

### Backend - Salida (9 archivos)

#### Modelos (2 archivos)

- [x] `/home/julian/Documentos/AgriFlor/backend/app/Models/ProductOutput.php`
  - Modelo principal de salida
  - UUID, timestamps: false
  - Campos: output_number, technical_order_id, output_date, origin_location_id, destination_location_id, status, total_cost, observations, responsible_user
  - Relaciones: TechnicalOrder, Location (origin/destination), User (responsible), OutputProduct, Reception, InventoryMovement
  - Scopes: byStatus, pending, completed

- [x] `/home/julian/Documentos/AgriFlor/backend/app/Models/OutputProduct.php`
  - Modelo para items de salida
  - UUID
  - Campos: output_id (fk), product_id (fk), quantity, unit, cost_per_unit, subtotal
  - Relación: belongsTo ProductOutput, Product

#### Controlador (1 archivo)

- [x] `/home/julian/Documentos/AgriFlor/backend/app/Http/Controllers/Api/ProductOutputController.php`
  - Métodos: index, show, store, update, destroy
  - Métodos adicionales:
    - `validateInventory()` - Valida disponibilidad en inventario
    - `approve()` - Aprueba salida e aplica FIFO
    - `markInTransit()` - Marca como en tránsito
    - `complete()` - Completa la salida
  - Validación con StoreProductOutputRequest y UpdateProductOutputRequest

#### Validaciones (2 archivos)

- [x] `/home/julian/Documentos/AgriFlor/backend/app/Http/Requests/StoreProductOutputRequest.php`
  - Validaciones para creación
  - Reglas para: output_number, technical_order_id, output_date, locations, status
  - Validación de items: product_id, quantity

- [x] `/home/julian/Documentos/AgriFlor/backend/app/Http/Requests/UpdateProductOutputRequest.php`
  - Validaciones para actualización
  - Similar a StoreProductOutputRequest con ajustes

#### Transformador (1 archivo)

- [x] `/home/julian/Documentos/AgriFlor/backend/app/Http/Resources/ProductOutputResource.php`
  - Transformación de datos de ProductOutput a JSON

#### Observer (1 archivo)

- [x] `/home/julian/Documentos/AgriFlor/backend/app/Observers/ProductOutputObserver.php`
  - Eventos automáticos:
    - `creating()` - Genera número de salida automático
    - `updating()` - Actualiza inventario cuando estado cambia a "completed"
  - Implementa FIFO (First In First Out) para salida de productos

#### Migraciones (2 archivos)

- [x] `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195403_create_product_outputs_table.php`
  - Tabla `product_outputs`
  - Columnas principales + relaciones

- [x] `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195404_create_output_products_table.php`
  - Tabla `output_products`
  - Relaciones: output_id, product_id

### Frontend - Salida (8 archivos)

#### Página Principal (1 archivo)

- [x] `/home/julian/Documentos/AgriFlor/frontend/src/pages/outputs/Outputs.tsx`
  - Página de gestión de salidas de productos
  - Tabla responsiva con indicadores de estado
  - Drawer para vista detallada
  - Modal para crear/editar salida
  - Tabla anidada de OutputProduct
  - Validación de inventario antes de aprobar
  - Filtros: búsqueda, estado, ubicación origen
  - Mutations: create, update, delete, approve, markInTransit, complete
  - Cambio de estado automático
  - Integración con inventario (FIFO)

#### Servicio API (1 archivo - sección)

- [x] `/home/julian/Documentos/AgriFlor/frontend/src/services/api.ts` (sección `outputsApi`)
  - Funciones:
    - `list(params?)` - GET /product-outputs
    - `get(id)` - GET /product-outputs/{id}
    - `create(data)` - POST /product-outputs
    - `update(id, data)` - PUT /product-outputs/{id}
    - `delete(id)` - DELETE /product-outputs/{id}
    - `validateInventory(data)` - POST /product-outputs/validate-inventory
    - `approve(id)` - POST /product-outputs/{id}/approve
    - `markInTransit(id)` - POST /product-outputs/{id}/mark-in-transit
    - `complete(id)` - POST /product-outputs/{id}/complete

#### Componentes Reutilizables (1 archivo)

- [x] `/home/julian/Documentos/AgriFlor/frontend/src/components/ResponsiveTable.tsx`
  - Ya listado en Products

#### Tipos TypeScript (1 archivo - sección)

- [x] `/home/julian/Documentos/AgriFlor/frontend/src/data/types.ts` (interfaces `ProductOutput`, `OutputProduct`)
  - Definiciones de interfaces principales

#### Datos Simulados (1 archivo)

- [x] `/home/julian/Documentos/AgriFlor/frontend/src/mock/movements.ts`
  - Movimientos de inventario simulados (relacionado con salidas)

#### Inventario (1 archivo - compartido)

- [x] `/home/julian/Documentos/AgriFlor/frontend/src/pages/inventory/Inventory.tsx`
  - Página de inventario (muestra resultados de salidas)
  - Integración con OutputProduct

#### Configuración (1 archivo)

- [x] `/home/julian/Documentos/AgriFlor/frontend/src/config/theme.ts`
  - Estilos globales

#### Enrutamiento (1 archivo)

- [x] `/home/julian/Documentos/AgriFlor/frontend/src/main.tsx`
  - Configuración de rutas (incluye ruta de Outputs)

---

## ARCHIVOS COMPARTIDOS ENTRE MÓDULOS

### Backend - Modelos Comunes

- [x] `/home/julian/Documentos/AgriFlor/backend/app/Models/Location.php`
  - Utilizado en: Compra (destination_location_id), Salida (origin/destination_location_id)

- [x] `/home/julian/Documentos/AgriFlor/backend/app/Models/Supplier.php`
  - Utilizado en: Compra (supplier_id)

- [x] `/home/julian/Documentos/AgriFlor/backend/app/Models/Brand.php`
  - Utilizado en: Producto (brand_id)

- [x] `/home/julian/Documentos/AgriFlor/backend/app/Models/Inventory.php`
  - Actualizado por: Compra (al recibir), Salida (al completar)
  - Relacionado con: Producto

- [x] `/home/julian/Documentos/AgriFlor/backend/app/Models/InventoryMovement.php`
  - Generado por: Compra (al recibir), Salida (al completar)

- [x] `/home/julian/Documentos/AgriFlor/backend/app/Models/Reception.php`
  - Vincula: Compra/Salida con Inventory
  - Proceso intermedio de recepción

- [x] `/home/julian/Documentos/AgriFlor/backend/app/Models/TechnicalOrder.php`
  - Utilizado en: Salida (technical_order_id)

### Backend - Controladores de Apoyo

- [x] `/home/julian/Documentos/AgriFlor/backend/app/Http/Controllers/Api/InventoryController.php`
  - Gestión de inventario
  - Consultas de stock

- [x] `/home/julian/Documentos/AgriFlor/backend/app/Http/Controllers/Api/LocationController.php`
  - Gestión de ubicaciones

- [x] `/home/julian/Documentos/AgriFlor/backend/app/Http/Controllers/Api/SupplierController.php`
  - Gestión de proveedores

### Backend - Migraciones de Soporte

- [x] `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195346_create_brands_table.php`
- [x] `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195348_create_packaging_units_table.php`
- [x] `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195350_create_product_packaging_units_table.php`
- [x] `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195351_create_suppliers_table.php`
- [x] `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195352_create_supplier_contacts_table.php`
- [x] `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195353_create_locations_table.php`
- [x] `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195405_create_receptions_table.php`
- [x] `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195408_create_reception_batches_table.php`
- [x] `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195409_create_reception_batch_items_table.php`
- [x] `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195411_create_inventory_table.php`
- [x] `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195412_create_inventory_movements_table.php`

### Frontend - Servicios Compartidos

- [x] `/home/julian/Documentos/AgriFlor/frontend/src/services/api.ts`
  - Centro de control de todas las APIs
  - Secciones: authApi, dashboardApi, purchasesApi, outputsApi, productsApi, inventoryApi, locationsApi, suppliersApi, etc.

### Frontend - Componentes Comunes

- [x] `/home/julian/Documentos/AgriFlor/frontend/src/components/ResponsiveTable.tsx`
  - Tabla adaptable utilizada en: Products, Purchases, Outputs

- [x] `/home/julian/Documentos/AgriFlor/frontend/src/components/layout/MainLayout.tsx`
  - Layout principal con navegación

- [x] `/home/julian/Documentos/AgriFlor/frontend/src/components/PrivateRoute.tsx`
  - Protección de rutas autenticadas

### Frontend - Datos y Tipos

- [x] `/home/julian/Documentos/AgriFlor/frontend/src/data/types.ts`
  - Interfaces: Product, Purchase, PurchaseItem, ProductOutput, OutputProduct, etc.

- [x] `/home/julian/Documentos/AgriFlor/frontend/src/data/mockData.ts`
  - Datos iniciales

### Frontend - Archivos Estáticos y Utilidades

- [x] `/home/julian/Documentos/AgriFlor/frontend/src/config/theme.ts`
  - Configuración de colores, estilos globales

- [x] `/home/julian/Documentos/AgriFlor/frontend/src/main.tsx`
  - Punto de entrada, configuración de rutas

- [x] `/home/julian/Documentos/AgriFlor/frontend/src/utils/pdfGenerator.ts`
  - Generación de PDFs (usado en Compra, extensible a Salida)

---

## RESUMEN ESTADÍSTICO

| Categoría | Count |
|-----------|-------|
| Modelos Backend (Producto) | 1 |
| Modelos Backend (Compra) | 3 |
| Modelos Backend (Salida) | 2 |
| Controladores | 3 |
| Validaciones (Requests) | 6 |
| Transformadores (Resources) | 3 |
| Observers | 1 |
| Migraciones (Producto, Compra, Salida) | 6 |
| Migraciones (Compartidas) | 11 |
| Páginas Frontend | 3 |
| Servicios API (secciones) | 3+ |
| Componentes Frontend | 3 |
| Utilidades Frontend | 1+ |
| **Total de archivos específicos** | **45** |
| Archivos compartidos | **15+** |

---

## CÓMO USAR ESTE DOCUMENTO

1. **Para encontrar un archivo específico:** Busca por nombre de módulo (PRODUCTO, COMPRA, SALIDA)
2. **Para entender dependencias:** Ve a "ARCHIVOS COMPARTIDOS"
3. **Para desarrollar una nueva funcionalidad:** Sigue la estructura de uno de los módulos existentes
4. **Para depuración:** Revisa qué archivos están involucrados en cada módulo

---

**Última actualización:** 2025-12-03  
**Generado con:** Claude Code
