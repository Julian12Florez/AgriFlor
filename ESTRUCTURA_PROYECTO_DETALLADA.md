# ESTRUCTURA DEL PROYECTO AGRIFLOR - MAPEO DETALLADO DE MÓDULOS

Fecha: 2025-12-03
Resumen: Documentación completa de archivos del backend y frontend para los módulos de Producto, Compra y Salida.

---

## 1. MÓDULO DE PRODUCTO (PRODUCTS)

### Backend - Producto

#### Modelo
- **Archivo:** `/home/julian/Documentos/AgriFlor/backend/app/Models/Product.php`
- **Descripción:** Modelo Eloquent para productos químicos agrícolas
- **Características principales:**
  - UUID como identificador primario
  - Campos: name, brand_id, category, base_unit, active_ingredient, min_stock, status, description, created_by
  - Relaciones con: Brand, User, PackagingUnit, RecipeProduct, TechnicalRecipe, PurchaseItem, OutputProduct, Inventory
  - Scopes: scopeActive(), scopeByCategory(), scopeLowStock()

#### Controlador
- **Archivo:** `/home/julian/Documentos/AgriFlor/backend/app/Http/Controllers/Api/ProductController.php`
- **Métodos HTTP:**
  - GET `/api/products` - Listar productos con filtros
  - GET `/api/products/{id}` - Obtener producto específico
  - POST `/api/products` - Crear nuevo producto
  - PUT `/api/products/{id}` - Actualizar producto
  - DELETE `/api/products/{id}` - Eliminar producto
  - POST `/api/products/search-with-inventory` - Búsqueda avanzada

#### Validaciones (Form Requests)
- **Archivo:** `/home/julian/Documentos/AgriFlor/backend/app/Http/Requests/StoreProductRequest.php`
- **Validaciones:**
  - name: required, string, max:255
  - brand_id: required, exists:brands,id
  - category: required, in:fertilizante,pesticida,herbicida,fungicida
  - base_unit: required, string
  - active_ingredient: required, string
  - min_stock: numeric, min:0
  - status: in:active,inactive

- **Archivo:** `/home/julian/Documentos/AgriFlor/backend/app/Http/Requests/UpdateProductRequest.php`
- **Validaciones:** Similares a StoreProductRequest

#### Resource (Transformador)
- **Archivo:** `/home/julian/Documentos/AgriFlor/backend/app/Http/Resources/ProductResource.php`
- **Propósito:** Transformar datos de modelo a JSON para API

#### Migraciones
- **Archivo:** `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195347_create_products_table.php`
- **Tabla:** products
- **Columnas:** id (uuid), name, brand_id (fk), category, base_unit, active_ingredient, min_stock, status, description, created_by (fk), created_at, updated_at

#### Seeders
- **Archivo:** `/home/julian/Documentos/AgriFlor/backend/database/seeders/ProductSeeder.php`
- **Propósito:** Datos de prueba iniciales para productos

---

### Frontend - Producto

#### Página Principal
- **Archivo:** `/home/julian/Documentos/AgriFlor/frontend/src/pages/master/Products.tsx`
- **Componentes:**
  - Tabla responsiva (móvil/desktop)
  - Modal para crear/editar productos
  - Filtros: búsqueda, categoría, estado
  - Acciones: crear, editar, eliminar
  - Manejo de errores de validación del backend

#### Servicio API
- **Archivo:** `/home/julian/Documentos/AgriFlor/frontend/src/services/api.ts`
- **Función:** `productsApi`
  ```typescript
  - list(params?: Record<string, any>): GET /products
  - get(id: string): GET /products/{id}
  - create(data: any): POST /products
  - update(id: string, data: any): PUT /products/{id}
  - delete(id: string): DELETE /products/{id}
  - searchWithInventory(data: any): POST /products/search-with-inventory
  ```

#### Tipos TypeScript
- **Archivo:** `/home/julian/Documentos/AgriFlor/frontend/src/data/types.ts`
- **Interface Product:**
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

#### Componentes Reutilizables
- **Archivo:** `/home/julian/Documentos/AgriFlor/frontend/src/components/ResponsiveTable.tsx`
- **Propósito:** Tabla adaptable para móvil/desktop

---

## 2. MÓDULO DE COMPRA (PURCHASES)

### Backend - Compra

#### Modelos
- **Archivo Principal:** `/home/julian/Documentos/AgriFlor/backend/app/Models/Purchase.php`
  - UUID como identificador
  - Campos: order_number, supplier_id, destination_location_id, purchase_date, expected_delivery, status, subtotal, tax, total, observations, created_by, received_by, received_at
  - Relaciones: Supplier, Location (destination), User (creator), User (receiver), PurchaseItem, PurchaseAttachment, Reception, InventoryMovement
  - Scopes: scopeByStatus(), scopePending(), scopeReceived()

- **Archivo Adicional:** `/home/julian/Documentos/AgriFlor/backend/app/Models/PurchaseItem.php`
  - Items individuales de la compra
  - Campos: purchase_id, product_id, quantity, unit_price, subtotal

- **Archivo Adicional:** `/home/julian/Documentos/AgriFlor/backend/app/Models/PurchaseAttachment.php`
  - Adjuntos de la compra (documentos, facturas, etc.)

#### Controlador
- **Archivo:** `/home/julian/Documentos/AgriFlor/backend/app/Http/Controllers/Api/PurchaseController.php`
- **Métodos HTTP:**
  - GET `/api/purchases` - Listar compras con filtros
  - GET `/api/purchases/{id}` - Obtener compra específica
  - POST `/api/purchases` - Crear nueva compra
  - PUT `/api/purchases/{id}` - Actualizar compra
  - DELETE `/api/purchases/{id}` - Eliminar compra
  - POST `/api/purchases/{id}/attachments` - Agregar adjunto
  - DELETE `/api/purchases/{id}/attachments/{attachmentId}` - Eliminar adjunto

#### Validaciones (Form Requests)
- **Archivo:** `/home/julian/Documentos/AgriFlor/backend/app/Http/Requests/StorePurchaseRequest.php`
- **Validaciones:**
  - order_number: required, unique:purchases
  - supplier_id: required, exists:suppliers,id
  - destination_location_id: required, exists:locations,id
  - purchase_date: required, date
  - expected_delivery: nullable, date, after:purchase_date
  - status: in:ordered,in_transit,received,cancelled
  - items: required, array
  - items.*.product_id: required, exists:products,id
  - items.*.quantity: required, numeric, min:0.01
  - items.*.unit_price: required, numeric, min:0

- **Archivo:** `/home/julian/Documentos/AgriFlor/backend/app/Http/Requests/UpdatePurchaseRequest.php`
- **Validaciones:** Similares con ajustes para actualización

#### Resources (Transformadores)
- **Archivo:** `/home/julian/Documentos/AgriFlor/backend/app/Http/Resources/PurchaseResource.php`
- **Archivo:** `/home/julian/Documentos/AgriFlor/backend/app/Http/Resources/PurchaseItemResource.php`

#### Migraciones
- **Archivo:** `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195400_create_purchases_table.php`
  - Tabla: purchases
  - Columnas: id (uuid), order_number, supplier_id (fk), destination_location_id (fk), purchase_date, expected_delivery, status, subtotal, tax, total, observations, created_by (fk), received_by (fk), received_at, created_at

- **Archivo:** `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195401_create_purchase_items_table.php`
  - Tabla: purchase_items
  - Columnas: id (uuid), purchase_id (fk), product_id (fk), quantity, unit, unit_price, subtotal

- **Archivo:** `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195402_create_purchase_attachments_table.php`
  - Tabla: purchase_attachments
  - Columnas: id (uuid), purchase_id (fk), file_path, file_name, file_size, created_at

---

### Frontend - Compra

#### Página Principal
- **Archivo:** `/home/julian/Documentos/AgriFlor/frontend/src/pages/purchases/Purchases.tsx`
- **Componentes:**
  - Tabla de compras responsiva
  - Vista detallada de compra (Drawer/Modal)
  - Formulario de creación/edición
  - Tabla de items dentro del formulario
  - Gestión de adjuntos (upload, descarga, eliminación)
  - Filtros: búsqueda, estado
  - Acciones: crear, editar, eliminar, descargar PDF, generar reporte
  - Cambio de estado (ordered → in_transit → received)

#### Servicio API
- **Archivo:** `/home/julian/Documentos/AgriFlor/frontend/src/services/api.ts`
- **Función:** `purchasesApi`
  ```typescript
  - list(params?: Record<string, any>): GET /purchases
  - get(id: string): GET /purchases/{id}
  - create(data: any): POST /purchases
  - update(id: string, data: any): PUT /purchases/{id}
  - delete(id: string): DELETE /purchases/{id}
  - addAttachment(id: string, file: FormData): POST /purchases/{id}/attachments
  - deleteAttachment(id: string, attachmentId: string): DELETE /purchases/{id}/attachments/{attachmentId}
  ```

#### Tipos TypeScript
- **Interfaz en:** `/home/julian/Documentos/AgriFlor/frontend/src/pages/purchases/Purchases.tsx`
  ```typescript
  interface Purchase {
    id: string;
    orderNumber: string;
    supplierId: string;
    supplierName: string;
    destinationLocationId: string;
    destinationLocationName: string;
    purchaseDate: Date;
    expectedDelivery?: Date;
    status: 'ordered' | 'in_transit' | 'received' | 'cancelled';
    items: PurchaseItem[];
    subtotal: number;
    tax: number;
    total: number;
    observations?: string;
    attachments: string[];
    createdBy: string;
    receivedBy?: string;
    receivedAt?: Date;
    createdAt: Date;
  }

  interface PurchaseItem {
    id: string;
    productId: string;
    productName: string;
    brandId: string;
    brandName: string;
    quantity: number;
    quantityInBaseUnits: number;
    unit: string;
    packagingUnitId: string;
    packagingUnitName: string;
    baseQuantityPerUnit: number;
    unitPrice: number;
    subtotal: number;
    expirationDate?: Date;
  }
  ```

#### Generador PDF
- **Archivo:** `/home/julian/Documentos/AgriFlor/frontend/src/utils/pdfGenerator.ts`
- **Funciones:**
  - `downloadPurchaseOrderPDF(purchase: Purchase): void`
  - `openPurchaseOrderPDF(purchase: Purchase): void`

---

## 3. MÓDULO DE SALIDA (PRODUCT OUTPUTS)

### Backend - Salida

#### Modelo Principal
- **Archivo:** `/home/julian/Documentos/AgriFlor/backend/app/Models/ProductOutput.php`
  - UUID como identificador
  - Campos: output_number, technical_order_id, output_date, origin_location_id, destination_location_id, status, total_cost, observations, responsible_user
  - Relaciones: TechnicalOrder, Location (origin), Location (destination), User (responsible), OutputProduct, Reception, InventoryMovement
  - Scopes: scopeByStatus(), scopePending(), scopeCompleted()
  - Estados: pending, partial, completed, cancelled

#### Modelo Auxiliar
- **Archivo:** `/home/julian/Documentos/AgriFlor/backend/app/Models/OutputProduct.php`
  - Productos individuales en la salida
  - Campos: output_id, product_id, quantity, unit, cost_per_unit, subtotal

#### Controlador
- **Archivo:** `/home/julian/Documentos/AgriFlor/backend/app/Http/Controllers/Api/ProductOutputController.php`
- **Métodos HTTP:**
  - GET `/api/product-outputs` - Listar salidas con filtros
  - GET `/api/product-outputs/{id}` - Obtener salida específica
  - POST `/api/product-outputs` - Crear nueva salida
  - PUT `/api/product-outputs/{id}` - Actualizar salida
  - DELETE `/api/product-outputs/{id}` - Eliminar salida
  - POST `/api/product-outputs/{id}/approve` - Aprobar salida (aplica FIFO a inventario)
  - POST `/api/product-outputs/{id}/mark-in-transit` - Marcar en tránsito
  - POST `/api/product-outputs/{id}/complete` - Completar salida
  - POST `/api/product-outputs/validate-inventory` - Validar disponibilidad de inventario

#### Validaciones (Form Requests)
- **Archivo:** `/home/julian/Documentos/AgriFlor/backend/app/Http/Requests/StoreProductOutputRequest.php`
- **Validaciones:**
  - output_number: required, unique:product_outputs
  - technical_order_id: required, exists:technical_orders,id
  - output_date: required, date
  - origin_location_id: required, exists:locations,id
  - destination_location_id: required, exists:locations,id
  - status: in:pending,partial,completed,cancelled
  - items: required, array
  - items.*.product_id: required, exists:products,id
  - items.*.quantity: required, numeric, min:0.01

- **Archivo:** `/home/julian/Documentos/AgriFlor/backend/app/Http/Requests/UpdateProductOutputRequest.php`
- **Validaciones:** Similares a StoreProdOutputRequest

#### Resource (Transformador)
- **Archivo:** `/home/julian/Documentos/AgriFlor/backend/app/Http/Resources/ProductOutputResource.php`

#### Migraciones
- **Archivo:** `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195403_create_product_outputs_table.php`
  - Tabla: product_outputs
  - Columnas: id (uuid), output_number, technical_order_id (fk), output_date, origin_location_id (fk), destination_location_id (fk), status, total_cost, observations, responsible_user (fk), created_at, updated_at

- **Archivo:** `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195404_create_output_products_table.php`
  - Tabla: output_products
  - Columnas: id (uuid), output_id (fk), product_id (fk), quantity, unit, cost_per_unit, subtotal

#### Observers (Disparadores Automáticos)
- **Archivo:** `/home/julian/Documentos/AgriFlor/backend/app/Observers/ProductOutputObserver.php`
- **Eventos:**
  - creating: Genera número de salida automático
  - updated: Actualiza inventario cuando estado cambia a "completed"

---

### Frontend - Salida

#### Página Principal
- **Archivo:** `/home/julian/Documentos/AgriFlor/frontend/src/pages/outputs/Outputs.tsx`
- **Componentes:**
  - Tabla responsiva de salidas
  - Vista detallada (Drawer)
  - Formulario de creación/edición
  - Tabla de productos dentro del formulario
  - Validación de inventario antes de aprobar
  - Filtros: búsqueda, estado, ubicación origen
  - Acciones: crear, editar, eliminar, aprobar, marcar en tránsito, completar
  - Indicadores visuales de estado

#### Servicio API
- **Archivo:** `/home/julian/Documentos/AgriFlor/frontend/src/services/api.ts`
- **Función:** `outputsApi`
  ```typescript
  - list(params?: Record<string, any>): GET /product-outputs
  - get(id: string): GET /product-outputs/{id}
  - create(data: any): POST /product-outputs
  - update(id: string, data: any): PUT /product-outputs/{id}
  - delete(id: string): DELETE /product-outputs/{id}
  - validateInventory(data: any): POST /product-outputs/validate-inventory
  - approve(id: string): POST /product-outputs/{id}/approve
  - markInTransit(id: string): POST /product-outputs/{id}/mark-in-transit
  - complete(id: string): POST /product-outputs/{id}/complete
  ```

#### Tipos TypeScript
- **Interfaz en:** `/home/julian/Documentos/AgriFlor/frontend/src/pages/outputs/Outputs.tsx` y `/home/julian/Documentos/AgriFlor/frontend/src/data/types.ts`
  ```typescript
  interface ProductOutput {
    id: string;
    outputNumber: string;
    technicalOrderId: string;
    outputDate: Date;
    originLocationId: string;
    originLocationName: string;
    destinationLocationId: string;
    destinationLocationName: string;
    status: 'pending' | 'partial' | 'completed' | 'cancelled';
    totalCost: number;
    observations?: string;
    responsibleUser: string;
    items: OutputProduct[];
    createdAt: Date;
  }

  interface OutputProduct {
    id: string;
    outputId: string;
    productId: string;
    productName: string;
    quantity: number;
    unit: string;
    costPerUnit: number;
    subtotal: number;
  }
  ```

---

## 4. RESUMEN DE ARCHIVOS POR MÓDULO

### Producto - Archivos Totales: 10

**Backend (5):**
1. `/home/julian/Documentos/AgriFlor/backend/app/Models/Product.php`
2. `/home/julian/Documentos/AgriFlor/backend/app/Http/Controllers/Api/ProductController.php`
3. `/home/julian/Documentos/AgriFlor/backend/app/Http/Requests/StoreProductRequest.php`
4. `/home/julian/Documentos/AgriFlor/backend/app/Http/Requests/UpdateProductRequest.php`
5. `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195347_create_products_table.php`

**Frontend (5):**
1. `/home/julian/Documentos/AgriFlor/frontend/src/pages/master/Products.tsx`
2. `/home/julian/Documentos/AgriFlor/frontend/src/services/api.ts` (sección productsApi)
3. `/home/julian/Documentos/AgriFlor/frontend/src/data/types.ts` (interfaz Product)
4. `/home/julian/Documentos/AgriFlor/frontend/src/components/ResponsiveTable.tsx`
5. `/home/julian/Documentos/AgriFlor/frontend/src/config/theme.ts` (colores y estilos)

---

### Compra - Archivos Totales: 18

**Backend (10):**
1. `/home/julian/Documentos/AgriFlor/backend/app/Models/Purchase.php`
2. `/home/julian/Documentos/AgriFlor/backend/app/Models/PurchaseItem.php`
3. `/home/julian/Documentos/AgriFlor/backend/app/Models/PurchaseAttachment.php`
4. `/home/julian/Documentos/AgriFlor/backend/app/Http/Controllers/Api/PurchaseController.php`
5. `/home/julian/Documentos/AgriFlor/backend/app/Http/Requests/StorePurchaseRequest.php`
6. `/home/julian/Documentos/AgriFlor/backend/app/Http/Requests/UpdatePurchaseRequest.php`
7. `/home/julian/Documentos/AgriFlor/backend/app/Http/Resources/PurchaseResource.php`
8. `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195400_create_purchases_table.php`
9. `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195401_create_purchase_items_table.php`
10. `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195402_create_purchase_attachments_table.php`

**Frontend (8):**
1. `/home/julian/Documentos/AgriFlor/frontend/src/pages/purchases/Purchases.tsx`
2. `/home/julian/Documentos/AgriFlor/frontend/src/services/api.ts` (sección purchasesApi)
3. `/home/julian/Documentos/AgriFlor/frontend/src/utils/pdfGenerator.ts` (funciones de PDF para compras)
4. `/home/julian/Documentos/AgriFlor/frontend/src/mock/purchases.ts` (datos simulados)
5. `/home/julian/Documentos/AgriFlor/frontend/src/components/ResponsiveTable.tsx`
6. `/home/julian/Documentos/AgriFlor/frontend/src/data/types.ts` (interfaces Purchase, PurchaseItem)
7. `/home/julian/Documentos/AgriFlor/frontend/src/config/theme.ts`
8. `/home/julian/Documentos/AgriFlor/frontend/src/main.tsx` (routing)

---

### Salida - Archivos Totales: 17

**Backend (9):**
1. `/home/julian/Documentos/AgriFlor/backend/app/Models/ProductOutput.php`
2. `/home/julian/Documentos/AgriFlor/backend/app/Models/OutputProduct.php`
3. `/home/julian/Documentos/AgriFlor/backend/app/Http/Controllers/Api/ProductOutputController.php`
4. `/home/julian/Documentos/AgriFlor/backend/app/Http/Requests/StoreProductOutputRequest.php`
5. `/home/julian/Documentos/AgriFlor/backend/app/Http/Requests/UpdateProductOutputRequest.php`
6. `/home/julian/Documentos/AgriFlor/backend/app/Http/Resources/ProductOutputResource.php`
7. `/home/julian/Documentos/AgriFlor/backend/app/Observers/ProductOutputObserver.php`
8. `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195403_create_product_outputs_table.php`
9. `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195404_create_output_products_table.php`

**Frontend (8):**
1. `/home/julian/Documentos/AgriFlor/frontend/src/pages/outputs/Outputs.tsx`
2. `/home/julian/Documentos/AgriFlor/frontend/src/services/api.ts` (sección outputsApi)
3. `/home/julian/Documentos/AgriFlor/frontend/src/components/ResponsiveTable.tsx`
4. `/home/julian/Documentos/AgriFlor/frontend/src/data/types.ts` (interfaces ProductOutput, OutputProduct)
5. `/home/julian/Documentos/AgriFlor/frontend/src/config/theme.ts`
6. `/home/julian/Documentos/AgriFlor/frontend/src/mock/movements.ts` (movimientos de inventario)
7. `/home/julian/Documentos/AgriFlor/frontend/src/pages/inventory/Inventory.tsx` (relación con inventario)
8. `/home/julian/Documentos/AgriFlor/frontend/src/main.tsx` (routing)

---

## 5. ARCHIVOS COMPARTIDOS/REUTILIZABLES

### Backend - Compartidos entre módulos:

1. **Modelos relacionados:**
   - `/home/julian/Documentos/AgriFlor/backend/app/Models/Location.php` - Ubicación (usada en Compra y Salida)
   - `/home/julian/Documentos/AgriFlor/backend/app/Models/Supplier.php` - Proveedor (usado en Compra)
   - `/home/julian/Documentos/AgriFlor/backend/app/Models/TechnicalOrder.php` - Orden técnica (usada en Salida)
   - `/home/julian/Documentos/AgriFlor/backend/app/Models/Brand.php` - Marca (usada en Producto)
   - `/home/julian/Documentos/AgriFlor/backend/app/Models/Inventory.php` - Inventario (relacionado con todos)
   - `/home/julian/Documentos/AgriFlor/backend/app/Models/InventoryMovement.php` - Movimientos (genera Compra y Salida)
   - `/home/julian/Documentos/AgriFlor/backend/app/Models/Reception.php` - Recepción (relaciona Compra y Salida con Inventario)

2. **Controladores de apoyo:**
   - `/home/julian/Documentos/AgriFlor/backend/app/Http/Controllers/Api/InventoryController.php`
   - `/home/julian/Documentos/AgriFlor/backend/app/Http/Controllers/Api/LocationController.php`
   - `/home/julian/Documentos/AgriFlor/backend/app/Http/Controllers/Api/SupplierController.php`

3. **Migraciones de soporte:**
   - `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195348_create_packaging_units_table.php`
   - `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195351_create_suppliers_table.php`
   - `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195353_create_locations_table.php`
   - `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195411_create_inventory_table.php`
   - `/home/julian/Documentos/AgriFlor/backend/database/migrations/2025_11_14_195412_create_inventory_movements_table.php`

### Frontend - Compartidos entre módulos:

1. **Servicios API:**
   - `/home/julian/Documentos/AgriFlor/frontend/src/services/api.ts` - Módulo central de APIs

2. **Componentes comunes:**
   - `/home/julian/Documentos/AgriFlor/frontend/src/components/ResponsiveTable.tsx` - Tabla adaptable
   - `/home/julian/Documentos/AgriFlor/frontend/src/components/layout/MainLayout.tsx` - Layout principal
   - `/home/julian/Documentos/AgriFlor/frontend/src/components/PrivateRoute.tsx` - Protección de rutas

3. **Datos y tipos:**
   - `/home/julian/Documentos/AgriFlor/frontend/src/data/types.ts` - Definiciones de interfaces
   - `/home/julian/Documentos/AgriFlor/frontend/src/data/mockData.ts` - Datos iniciales

4. **Configuración:**
   - `/home/julian/Documentos/AgriFlor/frontend/src/config/theme.ts` - Tema y colores
   - `/home/julian/Documentos/AgriFlor/frontend/src/main.tsx` - Punto de entrada

5. **Utilidades:**
   - `/home/julian/Documentos/AgriFlor/frontend/src/utils/pdfGenerator.ts` - Generación de PDFs

---

## 6. FLUJO DE DATOS INTEGRADO

### Flujo de Compra (Purchase)
```
1. Usuario crea Compra en Frontend (Purchases.tsx)
   ↓
2. Frontend valida datos y envía POST a PurchaseController
   ↓
3. PurchaseController valida con StorePurchaseRequest
   ↓
4. Compra se guarda en BD con sus items (PurchaseItem)
   ↓
5. Puede adjuntar documentos (PurchaseAttachment)
   ↓
6. Estado: ordered → in_transit → received
   ↓
7. Cuando se recibe, se crea Reception que actualiza Inventory
```

### Flujo de Salida (ProductOutput)
```
1. Usuario crea Salida en Frontend (Outputs.tsx)
   ↓
2. Frontend valida inventario disponible
   ↓
3. Usuario aprueba la salida
   ↓
4. ProductOutputObserver dispara evento "updating"
   ↓
5. Se recalcula inventario usando FIFO (First In First Out)
   ↓
6. Se generan InventoryMovements para cada producto
   ↓
7. Salida se marca como "completed"
```

### Flujo de Producto (Product)
```
1. Usuario gestiona Productos en Frontend (Products.tsx)
   ↓
2. ProductController maneja CRUD
   ↓
3. Productos se usan en:
   - PurchaseItem (qué se compra)
   - OutputProduct (qué se saca)
   - Inventory (stock actual)
   - InventoryMovement (historial)
```

---

## 7. ESTRUCTURA DE DIRECTORIOS CONSOLIDADA

```
AgriFlor/
├── backend/
│   ├── app/
│   │   ├── Models/
│   │   │   ├── Product.php
│   │   │   ├── Purchase.php
│   │   │   ├── PurchaseItem.php
│   │   │   ├── PurchaseAttachment.php
│   │   │   ├── ProductOutput.php
│   │   │   ├── OutputProduct.php
│   │   │   ├── Location.php
│   │   │   ├── Supplier.php
│   │   │   ├── Brand.php
│   │   │   ├── Inventory.php
│   │   │   ├── InventoryMovement.php
│   │   │   ├── Reception.php
│   │   │   ├── TechnicalOrder.php
│   │   │   └── User.php
│   │   ├── Http/
│   │   │   ├── Controllers/Api/
│   │   │   │   ├── ProductController.php
│   │   │   │   ├── PurchaseController.php
│   │   │   │   ├── ProductOutputController.php
│   │   │   │   ├── LocationController.php
│   │   │   │   ├── SupplierController.php
│   │   │   │   ├── InventoryController.php
│   │   │   │   └── ...
│   │   │   ├── Requests/
│   │   │   │   ├── StoreProductRequest.php
│   │   │   │   ├── UpdateProductRequest.php
│   │   │   │   ├── StorePurchaseRequest.php
│   │   │   │   ├── UpdatePurchaseRequest.php
│   │   │   │   ├── StoreProductOutputRequest.php
│   │   │   │   └── UpdateProductOutputRequest.php
│   │   │   ├── Resources/
│   │   │   │   ├── ProductResource.php
│   │   │   │   ├── PurchaseResource.php
│   │   │   │   ├── ProductOutputResource.php
│   │   │   │   └── ...
│   │   │   └── Middleware/
│   │   │       └── CheckRole.php
│   │   └── Observers/
│   │       ├── ProductOutputObserver.php
│   │       ├── ReceptionBatchObserver.php
│   │       └── ...
│   ├── database/
│   │   ├── migrations/
│   │   │   ├── 2025_11_14_195347_create_products_table.php
│   │   │   ├── 2025_11_14_195400_create_purchases_table.php
│   │   │   ├── 2025_11_14_195401_create_purchase_items_table.php
│   │   │   ├── 2025_11_14_195402_create_purchase_attachments_table.php
│   │   │   ├── 2025_11_14_195403_create_product_outputs_table.php
│   │   │   ├── 2025_11_14_195404_create_output_products_table.php
│   │   │   ├── 2025_11_14_195411_create_inventory_table.php
│   │   │   ├── 2025_11_14_195412_create_inventory_movements_table.php
│   │   │   └── ...
│   │   └── seeders/
│   │       ├── ProductSeeder.php
│   │       ├── SupplierSeeder.php
│   │       └── ...
│   └── routes/
│       └── api.php
│
└── frontend/
    ├── src/
    │   ├── pages/
    │   │   ├── master/
    │   │   │   └── Products.tsx
    │   │   ├── purchases/
    │   │   │   └── Purchases.tsx
    │   │   ├── outputs/
    │   │   │   └── Outputs.tsx
    │   │   ├── inventory/
    │   │   │   └── Inventory.tsx
    │   │   ├── reception/
    │   │   │   └── Reception.tsx
    │   │   ├── Dashboard.tsx
    │   │   └── auth/
    │   │       └── Login.tsx
    │   ├── services/
    │   │   ├── api.ts
    │   │   └── mockApi.ts
    │   ├── components/
    │   │   ├── ResponsiveTable.tsx
    │   │   ├── layout/
    │   │   │   └── MainLayout.tsx
    │   │   └── PrivateRoute.tsx
    │   ├── data/
    │   │   ├── types.ts
    │   │   └── mockData.ts
    │   ├── mock/
    │   │   ├── products.ts
    │   │   ├── purchases.ts
    │   │   ├── movements.ts
    │   │   └── ...
    │   ├── utils/
    │   │   └── pdfGenerator.ts
    │   ├── config/
    │   │   └── theme.ts
    │   └── main.tsx
    └── package.json
```

---

## 8. TABLA DE REFERENCIA RÁPIDA

| Aspecto | Producto | Compra | Salida |
|--------|----------|--------|---------|
| **Modelo Backend** | Product | Purchase, PurchaseItem, PurchaseAttachment | ProductOutput, OutputProduct |
| **Controlador Backend** | ProductController | PurchaseController | ProductOutputController |
| **Página Frontend** | Products.tsx | Purchases.tsx | Outputs.tsx |
| **API Service** | productsApi | purchasesApi | outputsApi |
| **Estados** | active, inactive | ordered, in_transit, received, cancelled | pending, partial, completed, cancelled |
| **Tabla BD Principal** | products | purchases | product_outputs |
| **Tablas Relacionales** | product_packaging_units | purchase_items, purchase_attachments | output_products |
| **Validación** | StoreProductRequest | StorePurchaseRequest | StoreProductOutputRequest |
| **Resource/Transformador** | ProductResource | PurchaseResource | ProductOutputResource |
| **Observer** | - | - | ProductOutputObserver |

---

**Fin del Documento**
