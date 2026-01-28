# MAPA COMPLETO DEL BACKEND AGRIF LOR - LARAVEL

**Fecha de Generación**: 2025-11-22
**Stack**: Laravel + MySQL + JWT Authentication
**Estructura**: RESTful API con autenticación basada en roles

---

## TABLA DE CONTENIDOS
1. [Descripción General](#descripción-general)
2. [Arquitectura de Autenticación y Roles](#arquitectura-de-autenticación-y-roles)
3. [Módulos por Categoría](#módulos-por-categoría)
4. [Controladores y Métodos](#controladores-y-métodos)
5. [Modelos y Relaciones](#modelos-y-relaciones)
6. [Resources (Transformadores de Datos)](#resources-transformadores-de-datos)
7. [Rutas API Detalladas](#rutas-api-detalladas)

---

## DESCRIPCIÓN GENERAL

### Stack Tecnológico
- **Framework**: Laravel 10+
- **Autenticación**: JWT (JSON Web Tokens) - PHPOpenSourceSaver/JWTAuth
- **ORM**: Eloquent
- **Base de Datos**: MySQL
- **IDs**: UUID v4 (HasUuids trait)
- **Timestamps**: ISO 8601

### Principios Arquitectónicos
- API RESTful
- Resource-based routing
- Soft deletes (donde corresponde)
- Polymorphic relationships (Recepción, Inventario)
- Role-based access control

---

## ARQUITECTURA DE AUTENTICACIÓN Y ROLES

### Roles Disponibles
1. **ADMIN** - Acceso total a todas las funcionalidades
2. **AGRONOMIST** - Gestión de recetas técnicas y órdenes técnicas
3. **WAREHOUSE** - Gestión de inventario, compras y salidas
4. **SUPERVISOR** - Supervisión y aprobación de operaciones
5. **FARM** - Operaciones en fincas asignadas

### Estructura de User
```php
Campos: id, email, name, password, role, status
JWT Claims: role, status
Métodos de Autenticación:
- login (POST /auth/login) - SIN autenticación
- logout (POST /auth/logout) - CON JWT
- refresh (POST /auth/refresh) - CON JWT
- me (GET /auth/me) - CON JWT
```

---

## MÓDULOS POR CATEGORÍA

### MÓDULO 1: GESTIÓN MAESTRA (Master Data)

#### 1.1 PRODUCTS (Productos)
**Controlador**: `ProductController`
- **GET /products** - Listar productos con filtros
- **POST /products** - Crear producto
- **GET /products/{id}** - Ver detalles
- **PUT /products/{id}** - Actualizar
- **DELETE /products/{id}** - Eliminar
- **POST /products/search-with-inventory** - Buscar con inventario real por ubicación

**Modelo**: `Product`
- Relaciones:
  - brand (belongsTo Brand)
  - creator (belongsTo User)
  - packagingUnits (belongsToMany PackagingUnit)
  - recipeProducts (hasMany RecipeProduct)
  - technicalRecipes (belongsToMany TechnicalRecipe)
  - purchaseItems (hasMany PurchaseItem)
  - outputProducts (hasMany OutputProduct)
  - receptionItems (hasMany ReceptionItem)
  - inventory (hasMany Inventory)
  - inventoryMovements (hasMany InventoryMovement)
  - alerts (hasMany Alert)

**Resource**: `ProductResource`
```json
{
  "id": "uuid",
  "name": "string",
  "category": "string (fertilizante|pesticida|herbicida|fungicida)",
  "baseUnit": "string",
  "activeIngredient": "string",
  "minStock": "decimal",
  "status": "string (active|inactive)",
  "description": "string",
  "brand": {...},
  "createdAt": "iso8601",
  "updatedAt": "iso8601"
}
```

#### 1.2 BRANDS (Marcas)
**Controlador**: `BrandController`
- **GET /brands** - Listar marcas
- **POST /brands** - Crear marca
- **GET /brands/{id}** - Ver detalles
- **PUT /brands/{id}** - Actualizar
- **DELETE /brands/{id}** - Eliminar

**Modelo**: `Brand`
- Relaciones:
  - products (hasMany Product)
  - recipeProducts (hasMany RecipeProduct)
  - technicalOrderProducts (hasMany TechnicalOrderProduct)
  - purchaseItems (hasMany PurchaseItem)
  - outputProducts (hasMany OutputProduct)
  - receptionItems (hasMany ReceptionItem)
  - inventory (hasMany Inventory)
  - inventoryMovements (hasMany InventoryMovement)

**Resource**: `BrandResource`
```json
{
  "id": "uuid",
  "name": "string",
  "status": "string",
  "createdAt": "iso8601"
}
```

#### 1.3 SUPPLIERS (Proveedores)
**Controlador**: `SupplierController`
- **GET /suppliers** - Listar proveedores
- **POST /suppliers** - Crear proveedor (con contactos)
- **GET /suppliers/{id}** - Ver detalles
- **PUT /suppliers/{id}** - Actualizar
- **DELETE /suppliers/{id}** - Eliminar
- **POST /suppliers/{id}/contacts** - Agregar contacto
- **DELETE /suppliers/{id}/contacts/{contactId}** - Eliminar contacto

**Modelo**: `Supplier`
- Relaciones:
  - contacts (hasMany SupplierContact)
  - purchases (hasMany Purchase)

**Modelo Secundario**: `SupplierContact`
- Campos: name, position, phone, email

**Resource**: `SupplierResource`
```json
{
  "id": "uuid",
  "name": "string",
  "nit": "string",
  "address": "string",
  "city": "string",
  "phone": "string",
  "email": "string",
  "paymentTerms": "string",
  "status": "string",
  "contacts": [...],
  "createdAt": "iso8601"
}
```

#### 1.4 LOCATIONS (Ubicaciones)
**Controlador**: `LocationController`
- **GET /locations** - Listar ubicaciones
- **GET /locations?type=warehouse** - Filtrar por tipo
- **GET /locations/type/warehouses** - Listar solo bodegas
- **GET /locations/type/farms** - Listar solo fincas
- **POST /locations** - Crear ubicación
- **GET /locations/{id}** - Ver detalles
- **PUT /locations/{id}** - Actualizar
- **DELETE /locations/{id}** - Eliminar

**Modelo**: `Location`
- Tipos: warehouse, farm
- Relaciones:
  - purchasesAsDestination (hasMany Purchase)
  - outputsAsOrigin (hasMany ProductOutput)
  - outputsAsDestination (hasMany ProductOutput)
  - receptionsAsOrigin (hasMany Reception)
  - receptionsAsDestination (hasMany Reception)
  - technicalOrders (belongsToMany TechnicalOrder)
  - inventory (hasMany Inventory)
  - inventoryMovements (hasMany InventoryMovement)
  - alerts (hasMany Alert)

**Resource**: `LocationResource`
```json
{
  "id": "uuid",
  "name": "string",
  "type": "string (warehouse|farm)",
  "municipality": "string",
  "address": "string",
  "coordinatesLat": "decimal",
  "coordinatesLng": "decimal",
  "responsible": "string",
  "status": "string",
  "createdAt": "iso8601"
}
```

#### 1.5 PACKAGING UNITS (Unidades de Empaque)
**Controlador**: `PackagingUnitController`
- **GET /packaging-units** - Listar unidades
- **POST /packaging-units** - Crear unidad
- **GET /packaging-units/{id}** - Ver detalles
- **PUT /packaging-units/{id}** - Actualizar
- **DELETE /packaging-units/{id}** - Eliminar

**Modelo**: `PackagingUnit`
- Relaciones:
  - products (belongsToMany Product)

**Resource**: `PackagingUnitResource`
```json
{
  "id": "uuid",
  "name": "string",
  "baseQuantity": "decimal",
  "baseUnit": "string",
  "createdAt": "iso8601"
}
```

---

### MÓDULO 2: PROCESOS TÉCNICOS

#### 2.1 TECHNICAL RECIPES (Recetas Técnicas)
**Controlador**: `TechnicalRecipeController`
**Roles Permitidos**: admin, agronomist

- **GET /technical-recipes** - Listar recetas
- **POST /technical-recipes** - Crear receta con productos
- **GET /technical-recipes/{id}** - Ver detalles
- **PUT /technical-recipes/{id}** - Actualizar
- **DELETE /technical-recipes/{id}** - Eliminar
- **POST /technical-recipes/{id}/duplicate** - Duplicar receta existente

**Modelo**: `TechnicalRecipe`
- Relaciones:
  - creator (belongsTo User)
  - recipeProducts (hasMany RecipeProduct)
  - products (belongsToMany Product)
  - technicalOrders (hasMany TechnicalOrder)

**Modelo Secundario**: `RecipeProduct`
- Campos: quantity, unit, application_rate, observations

**Resource**: `TechnicalRecipeResource`
```json
{
  "id": "uuid",
  "name": "string",
  "description": "string",
  "category": "string",
  "applicationInstructions": "string",
  "safetyNotes": "string",
  "estimatedCost": "decimal",
  "usageCount": "integer",
  "status": "string (active|inactive)",
  "products": [
    {
      "id": "uuid",
      "productId": "uuid",
      "brandId": "uuid",
      "quantity": "decimal",
      "unit": "string",
      "applicationRate": "decimal",
      "observations": "string",
      "product": {...},
      "brand": {...}
    }
  ],
  "createdBy": "string",
  "lastUsed": "iso8601",
  "createdAt": "iso8601"
}
```

#### 2.2 TECHNICAL ORDERS (Órdenes Técnicas)
**Controlador**: `TechnicalOrderController`
**Roles**: admin, agronomist (crear/editar), supervisor (solo lectura)

- **GET /technical-orders** - Listar órdenes
- **GET /technical-orders/{id}** - Ver detalles
- **POST /technical-orders** - Crear orden con productos y fincas
- **PUT /technical-orders/{id}** - Actualizar
- **DELETE /technical-orders/{id}** - Eliminar (solo draft/cancelled)
- **POST /technical-orders/{id}/approve** - Aprobar orden
- **POST /technical-orders/{id}/complete** - Completar orden
- **POST /technical-orders/{id}/cancel** - Cancelar orden

**Estados**: draft → approved → completed (o cancelled)

**Modelo**: `TechnicalOrder`
- Relaciones:
  - recipe (belongsTo TechnicalRecipe)
  - agronomist (belongsTo User)
  - applier (belongsTo User)
  - farms (belongsToMany Location)
  - technicalOrderProducts (hasMany TechnicalOrderProduct)
  - products (belongsToMany Product)
  - productOutputs (hasMany ProductOutput)
  - inventoryMovements (morphMany InventoryMovement)

**Modelo Secundario**: `TechnicalOrderProduct`
- Campos: quantity, unit, observations

**Modelo Secundario**: `TechnicalOrderFarm`
- Tabla pivote para relación many-to-many

**Resource**: `TechnicalOrderResource`
```json
{
  "id": "uuid",
  "orderNumber": "string",
  "scheduledDate": "date",
  "status": "string (draft|approved|completed|cancelled)",
  "estimatedCost": "decimal",
  "observations": "string",
  "appliedAt": "iso8601",
  "appliedBy": "string",
  "createdAt": "iso8601",
  "farms": [
    {
      "id": "uuid",
      "name": "string",
      "type": "string",
      "municipality": "string",
      "status": "string"
    }
  ],
  "products": [
    {
      "id": "uuid",
      "productId": "uuid",
      "productName": "string",
      "brandId": "uuid",
      "brandName": "string",
      "quantity": "decimal",
      "unit": "string",
      "observations": "string"
    }
  ],
  "recipe": {...},
  "agronomist": {...}
}
```

---

### MÓDULO 3: GESTIÓN DE ALMACÉN

#### 3.1 PURCHASES (Compras)
**Controlador**: `PurchaseController`
**Roles**: admin, warehouse (CRUD), supervisor (lectura)

- **GET /purchases** - Listar compras con filtros
- **GET /purchases/{id}** - Ver detalles completos
- **POST /purchases** - Crear compra con ítems y adjuntos
- **PUT /purchases/{id}** - Actualizar (solo status 'ordered')
- **DELETE /purchases/{id}** - Eliminar (solo status 'ordered')
- **PUT /purchases/{id}/status** - Cambiar estado (ordered → in_transit → received)
- **POST /purchases/{id}/attachments** - Agregar archivo
- **DELETE /purchases/{id}/attachments/{attachmentId}** - Eliminar archivo

**Estados**: ordered → in_transit → received

**Modelo**: `Purchase`
- Relaciones:
  - supplier (belongsTo Supplier)
  - destinationLocation (belongsTo Location)
  - creator (belongsTo User)
  - receiver (belongsTo User)
  - purchaseItems (hasMany PurchaseItem)
  - attachments (hasMany PurchaseAttachment)
  - reception (hasOne Reception)
  - inventoryMovements (morphMany InventoryMovement)

**Modelos Secundarios**:
- `PurchaseItem` - Campos: quantity, quantity_in_base_units, unit_price, subtotal, expiration_date
- `PurchaseAttachment` - Campos: file_name, file_path, file_type, file_size

**Resource**: `PurchaseResource`
```json
{
  "id": "uuid",
  "orderNumber": "string",
  "purchaseDate": "date",
  "expectedDelivery": "date",
  "status": "string (ordered|in_transit|received)",
  "subtotal": "float",
  "tax": "float",
  "total": "float",
  "observations": "string",
  "receivedAt": "iso8601",
  "createdAt": "iso8601",
  "supplier": {
    "id": "uuid",
    "name": "string",
    "nit": "string",
    "phone": "string",
    "email": "string",
    "city": "string",
    "status": "string"
  },
  "destinationLocation": {...},
  "items": [
    {
      "id": "uuid",
      "productId": "uuid",
      "productName": "string",
      "brandId": "uuid",
      "brandName": "string",
      "packagingUnitId": "uuid",
      "packagingUnitName": "string",
      "quantity": "float",
      "quantityInBaseUnits": "float",
      "unitPrice": "float",
      "subtotal": "float",
      "expirationDate": "date"
    }
  ],
  "attachments": [...],
  "creator": {...},
  "receiver": {...}
}
```

#### 3.2 PRODUCT OUTPUTS (Salidas de Productos)
**Controlador**: `ProductOutputController`
**Roles**: admin, warehouse (CRUD), supervisor (aprobación)

- **GET /product-outputs** - Listar salidas
- **GET /product-outputs/{id}** - Ver detalles
- **POST /product-outputs** - Crear salida con productos
- **PUT /product-outputs/{id}** - Actualizar (no si está completada)
- **DELETE /product-outputs/{id}** - Eliminar (solo pending)
- **POST /product-outputs/validate-inventory** - Validar disponibilidad de inventario
- **POST /product-outputs/{id}/approve** - Aprobar y reducir inventario (supervisor/admin)
- **POST /product-outputs/{id}/mark-in-transit** - Marcar en tránsito
- **POST /product-outputs/{id}/complete** - Completar salida

**Estados**: pending → approved → in_transit → completed

**Modelo**: `ProductOutput`
- Relaciones:
  - technicalOrder (belongsTo TechnicalOrder)
  - originLocation (belongsTo Location)
  - destinationLocation (belongsTo Location)
  - responsibleUser (belongsTo User)
  - outputProducts (hasMany OutputProduct)
  - reception (hasOne Reception)
  - inventoryMovements (morphMany InventoryMovement)

**Modelo Secundario**: `OutputProduct`
- Campos: quantity_requested, quantity_delivered, unit, batch_number, expiration_date

**Resource**: `ProductOutputResource`
```json
{
  "id": "uuid",
  "outputNumber": "string",
  "technicalOrderId": "uuid",
  "technicalOrder": {...},
  "outputDate": "date",
  "originLocationId": "uuid",
  "originLocation": {...},
  "destinationLocationId": "uuid",
  "destinationLocation": {...},
  "status": "string (pending|approved|in_transit|completed)",
  "totalCost": "float",
  "observations": "string",
  "responsibleUser": "uuid",
  "responsibleUserDetails": {...},
  "products": [
    {
      "id": "uuid",
      "productId": "uuid",
      "product": {...},
      "brandId": "uuid",
      "brand": {...},
      "quantityRequested": "float",
      "quantityDelivered": "float",
      "unit": "string",
      "batchNumber": "string",
      "expirationDate": "date"
    }
  ],
  "createdAt": "iso8601"
}
```

#### 3.3 RECEPTIONS (Recepciones)
**Controlador**: `ReceptionController`
**Roles**: admin, warehouse, farm (CRUD)

- **GET /receptions** - Listar recepciones con filtros
- **GET /receptions/{id}** - Ver detalles completos
- **GET /receptions/{id}/batches** - Listar lotes de recepción
- **GET /receptions/{id}/pending-products** - Productos aún pendientes
- **POST /receptions** - Crear recepción de compra u output
- **POST /receptions/{id}/batches** - Agregar lote con productos recibidos
- **PUT /receptions/{id}/complete** - Completar recepción
- **PUT /receptions/{id}/cancel** - Cancelar recepción

**Estados**: pending → partial → completed (o cancelled)

**Modelo**: `Reception`
- Relaciones (polimórficas):
  - source (morphTo - Purchase o ProductOutput)
  - purchase (belongsTo Purchase)
  - productOutput (belongsTo ProductOutput)
  - originLocation (belongsTo Location)
  - destinationLocation (belongsTo Location)
  - responsibleUser (belongsTo User)
  - receptionItems (hasMany ReceptionItem)
  - receptionBatches (hasMany ReceptionBatch)
  - inventoryMovements (morphMany InventoryMovement)

**Modelos Secundarios**:
- `ReceptionItem` - Campos: quantity_expected, quantity_received, quantity_pending, unit
- `ReceptionBatch` - Campos: batch_number, reception_date, observations
- `ReceptionBatchItem` - Campos: quantity_received, condition, expiration_date, observations
- `ReceptionBatchAttachment` - Campos: file_name, file_path, file_type, file_size

**Resource**: `ReceptionResource`
```json
{
  "id": "uuid",
  "receptionNumber": "string",
  "sourceId": "uuid",
  "sourceType": "string (purchase|output)",
  "shipmentDate": "date",
  "status": "string (pending|partial|completed|cancelled)",
  "totalExpected": "decimal",
  "totalReceived": "decimal",
  "completionPercentage": "decimal",
  "observations": "string",
  "createdAt": "iso8601",
  "updatedAt": "iso8601",
  "originLocation": {...},
  "destinationLocation": {...},
  "responsibleUser": {...},
  "sourceDetails": {
    "type": "purchase|output",
    "orderNumber": "string",
    "purchaseDate": "date",
    "supplier": {...}
  },
  "items": [
    {
      "id": "uuid",
      "productId": "uuid",
      "productName": "string",
      "quantityExpected": "decimal",
      "quantityReceived": "decimal",
      "unit": "string"
    }
  ],
  "batches": [...]
}
```

**Resource**: `ReceptionBatchResource`
```json
{
  "id": "uuid",
  "batchNumber": "integer",
  "receptionDate": "date",
  "observations": "string",
  "receiver": {...},
  "items": [
    {
      "id": "uuid",
      "productId": "uuid",
      "quantityReceived": "decimal",
      "condition": "string (good|damaged|expired)",
      "expirationDate": "date",
      "observations": "string",
      "product": {...}
    }
  ],
  "attachments": [...]
}
```

---

### MÓDULO 4: GESTIÓN DE INVENTARIO

#### 4.1 INVENTORY (Inventario)
**Controlador**: `InventoryController`
**Roles**: todos (lectura), admin/warehouse (ajustes)

- **GET /inventory** - Listar inventario actual
- **GET /inventory/{productId}** - Detalles de inventario por producto
- **GET /inventory/location/{locationId}** - Inventario por ubicación
- **GET /inventory/product/{productId}/details** - Detalles completos por producto
- **GET /inventory/movements** - Kardex (movimientos de inventario)
- **GET /inventory/movements/product/{productId}** - Movimientos de un producto
- **POST /inventory/adjustments** - Crear ajuste manual de inventario

**Modelo**: `Inventory`
- Relaciones:
  - product (belongsTo Product)
  - brand (belongsTo Brand)
  - location (belongsTo Location)

**Modelo Secundario**: `InventoryMovement`
- Tipos: entry, exit, application, adjustment
- Relaciones (polimórficas):
  - related_document (morphTo - puede ser Purchase, Reception, ProductOutput, TechnicalOrder)

**Resource**: `InventoryResource`
```json
{
  "id": "uuid",
  "productId": "uuid",
  "brandId": "uuid",
  "locationId": "uuid",
  "batchNumber": "string",
  "quantity": "decimal",
  "unit": "string (kg|litros|unidades)",
  "expirationDate": "iso8601",
  "unitPrice": "decimal",
  "totalValue": "decimal",
  "status": "string (good|expired|near_expiry|low)",
  "product": {...},
  "brand": {...},
  "location": {...},
  "createdAt": "iso8601",
  "updatedAt": "iso8601"
}
```

**Resource**: `InventoryMovementResource`
```json
{
  "id": "uuid",
  "type": "string (entry|exit|application|adjustment)",
  "productId": "uuid",
  "brandId": "uuid",
  "locationId": "uuid",
  "quantity": "decimal",
  "unit": "string",
  "expirationDate": "date",
  "unitPrice": "decimal",
  "totalPrice": "decimal",
  "observations": "string",
  "responsibleUser": "uuid",
  "product": {...},
  "brand": {...},
  "location": {...},
  "responsibleUser": {...},
  "createdAt": "iso8601"
}
```

---

### MÓDULO 5: ALERTAS Y REPORTES

#### 5.1 ALERTS (Alertas)
**Controlador**: `AlertController`
**Roles**: todos (lectura), admin/supervisor/warehouse (creación/resolución)

- **GET /alerts** - Listar alertas ordenadas por severidad
- **GET /alerts/{id}** - Ver detalles de alerta
- **POST /alerts** - Crear alerta
- **PUT /alerts/{id}/resolve** - Resolver alerta
- **PUT /alerts/{id}/dismiss** - Descartar alerta

**Tipos**: low_stock, expiry_warning, missing_goods, quality_issue, other
**Severidades**: low, medium, high
**Estados**: active, resolved, dismissed

**Modelo**: `Alert`
- Relaciones:
  - location (belongsTo Location)
  - product (belongsTo Product)
  - resolver (belongsTo User)

**Resource**: `AlertResource`
```json
{
  "id": "uuid",
  "type": "string",
  "title": "string",
  "description": "string",
  "locationId": "uuid",
  "productId": "uuid",
  "severity": "string (low|medium|high)",
  "status": "string (active|resolved|dismissed)",
  "resolvedAt": "iso8601",
  "resolvedBy": "uuid",
  "location": {...},
  "product": {...},
  "resolver": {...},
  "createdAt": "iso8601"
}
```

#### 5.2 DASHBOARD (Reportes y Estadísticas)
**Controlador**: `DashboardController`
**Métodos**:

- **GET /dashboard/statistics** - KPIs principales
  - total_products (count activos)
  - active_orders (draft/approved)
  - monthly_purchases (suma de monto)
  - active_alerts (alertas activas)

- **GET /dashboard/inventory-by-category** - Inventario agrupado por categoría
  - Retorna porcentajes por categoría

- **GET /dashboard/recent-activity** - Actividad reciente
  - Órdenes completadas
  - Compras registradas
  - Recepciones completadas
  - Alertas de alta prioridad

---

### MÓDULO 6: GESTIÓN DE USUARIOS

#### 6.1 USERS (Usuarios)
**Controlador**: `UserController`
**Roles**: admin (CRUD)

- **GET /users** - Listar usuarios con filtros
- **POST /users** - Crear usuario
- **GET /users/{id}** - Ver detalles
- **PUT /users/{id}** - Actualizar (no contraseña)
- **DELETE /users/{id}** - Eliminar (no a sí mismo)
- **PATCH /users/{id}/status** - Cambiar estado (active/inactive)

**Modelo**: `User` (Authenticatable, JWTSubject)
- Relaciones:
  - products (hasMany Product)
  - technicalRecipes (hasMany TechnicalRecipe)
  - technicalOrdersAsAgronomist (hasMany TechnicalOrder)
  - technicalOrdersAsApplier (hasMany TechnicalOrder)
  - purchasesCreated (hasMany Purchase)
  - purchasesReceived (hasMany Purchase)
  - productOutputs (hasMany ProductOutput)
  - receptions (hasMany Reception)
  - receptionBatches (hasMany ReceptionBatch)
  - inventoryMovements (hasMany InventoryMovement)
  - alertsResolved (hasMany Alert)
  - purchaseAttachments (hasMany PurchaseAttachment)
  - receptionBatchAttachments (hasMany ReceptionBatchAttachment)

**Resource**: `UserResource`
```json
{
  "id": "uuid",
  "email": "string",
  "name": "string",
  "role": "string (admin|agronomist|warehouse|supervisor|farm)",
  "status": "string (active|inactive)",
  "createdAt": "iso8601",
  "updatedAt": "iso8601"
}
```

#### 6.2 AUTH (Autenticación)
**Controlador**: `AuthController`

- **POST /auth/login** - Login (SIN autenticación)
  - Input: email, password
  - Output: user, token, token_type, expires_in

- **POST /auth/logout** - Logout (CON JWT)
- **GET /auth/me** - Obtener usuario actual (CON JWT)
- **POST /auth/refresh** - Refrescar token (CON JWT)

---

## CONTROLADORES Y MÉTODOS

### Resumen Completo de Métodos por Controlador

| Controlador | Métodos | Roles Requeridos |
|---|---|---|
| **ProductController** | index, store, show, update, destroy, searchWithInventory | admin, agronomist, warehouse |
| **BrandController** | index, store, show, update, destroy | admin, warehouse |
| **SupplierController** | index, store, show, update, destroy, addContact, removeContact | admin, warehouse |
| **LocationController** | index, store, show, update, destroy, warehouses, farms | admin, warehouse, supervisor |
| **PackagingUnitController** | index, store, show, update, destroy | admin, warehouse |
| **TechnicalRecipeController** | index, store, show, update, destroy, duplicate | admin, agronomist |
| **TechnicalOrderController** | index, store, show, update, destroy, approve, complete, cancel | admin, agronomist |
| **PurchaseController** | index, store, show, update, destroy, updateStatus, addAttachment, removeAttachment | admin, warehouse |
| **ProductOutputController** | index, store, show, update, destroy, approve, markInTransit, complete, validateInventory | admin, warehouse, supervisor |
| **ReceptionController** | index, store, show, addBatch, getBatches, getPendingProducts, complete, cancel | admin, warehouse, farm |
| **InventoryController** | index, show, byLocation, byProduct, movements, movementsByProduct, adjustment | todos (lectura), admin/warehouse (escritura) |
| **AlertController** | index, store, show, resolve, dismiss | todos (lectura), admin/supervisor/warehouse (escritura) |
| **UserController** | index, store, show, update, destroy, updateStatus | admin |
| **AuthController** | login, logout, me, refresh | públicos/privados según método |
| **DashboardController** | getStatistics, getInventoryByCategory, getRecentActivity | autenticados |

---

## MODELOS Y RELACIONES

### Estructura Completa de Modelos

#### Core Models

**User**
```
Timestamps: created_at, updated_at
UUID: Sí
Relaciones: Ver módulo 6.1
```

**Product**
```
Timestamps: created_at, updated_at
UUID: Sí
Fillable: name, brand_id, category, base_unit, active_ingredient, min_stock, status, description, created_by
Relaciones: 19 relaciones (ver arriba)
Scopes: active(), byCategory(), lowStock()
```

**Brand**
```
Timestamps: created_at (sin updated_at)
UUID: Sí
Fillable: name, status
Relaciones: 8 relaciones
Scopes: active()
```

**Location**
```
Timestamps: created_at (sin updated_at)
UUID: Sí
Tipos: warehouse, farm
Relaciones: 8 relaciones
Scopes: active(), warehouses(), farms()
```

**Supplier**
```
Timestamps: created_at (sin updated_at)
UUID: Sí
Fillable: name, nit, address, city, phone, email, payment_terms, status
Relaciones: contacts, purchases
Scopes: active()
```

**SupplierContact**
```
Campos: name, position, phone, email
UUID: Sí
```

**PackagingUnit**
```
UUID: Sí
Fillable: name, base_quantity, base_unit
Relaciones: products (belongsToMany)
```

**Purchase**
```
Timestamps: created_at (sin updated_at, sin timestamps)
UUID: Sí
Estados: ordered, in_transit, received
Relaciones: supplier, destinationLocation, creator, receiver, purchaseItems, attachments, reception
Scopes: byStatus(), pending(), received()
```

**PurchaseItem**
```
UUID: Sí
Relaciones: purchase, product, brand, packagingUnit
```

**PurchaseAttachment**
```
UUID: Sí
Campos: file_name, file_path, file_type, file_size
Relaciones: purchase, uploader (belongsTo User)
```

**ProductOutput**
```
Timestamps: created_at (sin updated_at)
UUID: Sí
Estados: pending, approved, in_transit, completed
Relaciones: technicalOrder, originLocation, destinationLocation, responsibleUser, outputProducts, reception
Scopes: byStatus(), pending(), completed()
```

**OutputProduct**
```
UUID: Sí
Campos: quantity_requested, quantity_delivered, unit, batch_number, expiration_date
Relaciones: output, product, brand
```

**Reception**
```
Timestamps: created_at, updated_at
UUID: Sí
Estados: pending, partial, completed, cancelled
Source Types: purchase, output
Relaciones: source (morphTo), purchase, productOutput, originLocation, destinationLocation, responsibleUser, receptionItems, receptionBatches
Scopes: byStatus(), pending(), completed(), bySourceType()
```

**ReceptionItem**
```
UUID: Sí
Campos: quantity_expected, quantity_received, quantity_pending, unit, brand_id
```

**ReceptionBatch**
```
UUID: Sí
Campos: batch_number, reception_date, observations
Relaciones: receiver (belongsTo User), batchItems, attachments
```

**ReceptionBatchItem**
```
UUID: Sí
Campos: quantity_received, condition (good|damaged|expired), expiration_date, observations
Relaciones: batchItem, product
```

**ReceptionBatchAttachment**
```
UUID: Sí
Campos: file_name, file_path, file_type, file_size
```

**Inventory**
```
Timestamps: created_at, updated_at
UUID: Sí
Fillable: product_id, brand_id, location_id, batch_number, quantity, unit, expiration_date, unit_price, total_value, status
Estados: good, expired, near_expiry, low
Relaciones: product, brand, location
Scopes: byLocation(), byProduct(), lowStock(), expired(), nearExpiry(), good()
Accessor: getTotalValueAttribute (quantity * unit_price)
```

**InventoryMovement**
```
Timestamps: created_at (sin updated_at)
UUID: Sí
Tipos: entry, exit, application, adjustment
Relaciones: product, brand, location, responsibleUser, related_document (morphTo - polymorphic)
```

**TechnicalRecipe**
```
Timestamps: created_at, updated_at
UUID: Sí
Relaciones: creator, recipeProducts, products (belongsToMany), technicalOrders
Scopes: active(), byCategory(), mostUsed()
```

**RecipeProduct**
```
UUID: Sí
Campos: quantity, unit, application_rate, observations
Relaciones: recipe, product, brand
```

**TechnicalOrder**
```
Timestamps: created_at (sin updated_at)
UUID: Sí
Estados: draft, approved, completed, cancelled
Relaciones: recipe, agronomist, applier, farms (belongsToMany), technicalOrderProducts, products, productOutputs, inventoryMovements (morphMany)
Scopes: byStatus(), pending(), completed()
```

**TechnicalOrderProduct**
```
UUID: Sí
Campos: quantity, unit, observations
Relaciones: order, product, brand
```

**TechnicalOrderFarm**
```
Tabla pivote con timestamps
Relaciones: technicalOrder, farm (Location)
```

**Alert**
```
Timestamps: created_at (sin updated_at)
UUID: Sí
Tipos: low_stock, expiry_warning, missing_goods, quality_issue, other
Severidades: low, medium, high
Estados: active, resolved, dismissed
Relaciones: location, product, resolver (belongsTo User)
Scopes: active(), resolved(), dismissed(), byType(), bySeverity(), highPriority()
```

---

## RESOURCES (TRANSFORMADORES DE DATOS)

Todos los resources están ubicados en `/backend/app/Http/Resources/`

### Resources Disponibles

1. **UserResource** - Transforma datos de User
2. **ProductResource** - Transforma datos de Product
3. **BrandResource** - Transforma datos de Brand
4. **LocationResource** - Transforma datos de Location
5. **SupplierResource** - Transforma datos de Supplier con contactos
6. **PackagingUnitResource** - Transforma datos de PackagingUnit
7. **PurchaseResource** - Transforma Purchase con ítems, adjuntos y cálculos
8. **ProductOutputResource** - Transforma ProductOutput con productos
9. **ReceptionResource** - Transforma Reception con items y lotes
10. **ReceptionBatchResource** - Transforma ReceptionBatch
11. **InventoryResource** - Transforma Inventory con cálculo de estado
12. **InventoryMovementResource** - Transforma InventoryMovement (Kardex)
13. **TechnicalRecipeResource** - Transforma TechnicalRecipe con productos
14. **TechnicalOrderResource** - Transforma TechnicalOrder con relaciones
15. **AlertResource** - Transforma Alert

### Convenciones en Resources

- Nombres de campos en camelCase (ej: createdAt, productName)
- Relaciones cargadas con `whenLoaded()` para lazy loading
- Timestamps en formato ISO 8601
- Decimales como float cuando corresponde
- Arrays de objetos relacionados transformados recursivamente

---

## RUTAS API DETALLADAS

### BASE URL
```
https://api.agrif lor.local/api
```

### CONVENCIONES
- Autenticación: Header `Authorization: Bearer <TOKEN>`
- Content-Type: `application/json`
- Respuestas: JSON estructurado con `success`, `message`, `data`

---

## RUTAS PÚBLICAS (Sin Autenticación)

```
POST /auth/login
  Input: { email, password }
  Output: { success, message, data: { user, token, token_type, expires_in } }
```

---

## RUTAS PROTEGIDAS POR JWT

### AUTH (Autenticación Protegida)
```
POST   /auth/logout
GET    /auth/me
POST   /auth/refresh
```

### DASHBOARD
```
GET  /dashboard/statistics
GET  /dashboard/inventory-by-category
GET  /dashboard/recent-activity?limit=10
```

### USER MANAGEMENT (admin)
```
GET    /users?role=admin&status=active&search=name&per_page=15
POST   /users
GET    /users/{id}
PUT    /users/{id}
DELETE /users/{id}
PATCH  /users/{id}/status
```

### PRODUCTS (admin, agronomist, warehouse)
```
GET    /products?category=fertilizante&status=active&brand_id=UUID&search=name&per_page=15
POST   /products
GET    /products/{id}
PUT    /products/{id}
DELETE /products/{id}
POST   /products/search-with-inventory
  Input: { location_id, search?, category? }
```

### BRANDS (admin, warehouse)
```
GET    /brands?status=active&search=name&per_page=15
POST   /brands
GET    /brands/{id}
PUT    /brands/{id}
DELETE /brands/{id}
```

### SUPPLIERS (admin, warehouse)
```
GET    /suppliers?status=active&search=name&per_page=15
POST   /suppliers
GET    /suppliers/{id}
PUT    /suppliers/{id}
DELETE /suppliers/{id}
POST   /suppliers/{id}/contacts
DELETE /suppliers/{id}/contacts/{contactId}
```

### LOCATIONS (admin, warehouse, supervisor)
```
GET    /locations?type=warehouse&status=active&search=name&per_page=15
POST   /locations
GET    /locations/{id}
PUT    /locations/{id}
DELETE /locations/{id}
GET    /locations/type/warehouses?status=active&per_page=15
GET    /locations/type/farms?status=active&per_page=15
```

### PACKAGING UNITS (admin, warehouse)
```
GET    /packaging-units?base_unit=kg&search=name&per_page=15
POST   /packaging-units
GET    /packaging-units/{id}
PUT    /packaging-units/{id}
DELETE /packaging-units/{id}
```

### TECHNICAL RECIPES (admin, agronomist)
```
GET    /technical-recipes?category=cat&status=active&search=name&per_page=15
POST   /technical-recipes
  Input: { name, description, category, application_instructions, safety_notes, status, products: [...] }
GET    /technical-recipes/{id}
PUT    /technical-recipes/{id}
DELETE /technical-recipes/{id}
POST   /technical-recipes/{id}/duplicate
```

### TECHNICAL ORDERS (admin, agronomist, supervisor)
```
GET    /technical-orders?status=draft&farm_id=UUID&responsible_agronomist=UUID&date_from=2025-01-01&date_to=2025-01-31&search=name&per_page=15
POST   /technical-orders  (admin, agronomist)
  Input: { order_number, scheduled_date, recipe_id?, responsible_agronomist, observations?, farm_ids: [...], products: [...] }
GET    /technical-orders/{id}
PUT    /technical-orders/{id}  (admin, agronomist)
DELETE /technical-orders/{id}  (admin, agronomist)
POST   /technical-orders/{id}/approve  (admin, agronomist)
POST   /technical-orders/{id}/complete  (admin, agronomist)
POST   /technical-orders/{id}/cancel  (admin, agronomist)
```

### PURCHASES (admin, warehouse, supervisor)
```
GET    /purchases?status=ordered&supplier_id=UUID&destination_location_id=UUID&date_from=2025-01-01&date_to=2025-01-31&search=order_number&per_page=15
POST   /purchases  (admin, warehouse)
  Input: { order_number, supplier_id, destination_location_id, purchase_date, expected_delivery?, observations?, items: [...], attachments?: [...] }
GET    /purchases/{id}
PUT    /purchases/{id}  (admin, warehouse)
DELETE /purchases/{id}  (admin, warehouse)
PUT    /purchases/{id}/status  (admin, warehouse)
  Input: { status: 'ordered|in_transit|received' }
POST   /purchases/{id}/attachments  (admin, warehouse)
DELETE /purchases/{id}/attachments/{attachmentId}  (admin, warehouse)
```

### PRODUCT OUTPUTS (admin, warehouse, supervisor)
```
GET    /product-outputs?status=pending&origin_location_id=UUID&destination_location_id=UUID&technical_order_id=UUID&start_date=2025-01-01&end_date=2025-01-31&search=output_number&per_page=15
POST   /product-outputs/validate-inventory  (admin, warehouse, supervisor)
  Input: { location_id, products: [{ product_id, brand_id, quantity }, ...] }
  Output: { valid, message, data: [{ product_id, product_name, requested, available, sufficient, deficit, batches, message }, ...] }
POST   /product-outputs  (admin, warehouse)
  Input: { output_number, technical_order_id?, output_date, origin_location_id, destination_location_id, observations?, products: [...] }
GET    /product-outputs/{id}
PUT    /product-outputs/{id}  (admin, warehouse)
DELETE /product-outputs/{id}  (admin, warehouse)
POST   /product-outputs/{id}/approve  (admin, supervisor)
POST   /product-outputs/{id}/mark-in-transit  (admin, warehouse)
POST   /product-outputs/{id}/complete  (admin, warehouse)
```

### RECEPTIONS (admin, warehouse, farm)
```
GET    /receptions?status=pending&source_type=purchase&origin_location_id=UUID&destination_location_id=UUID&date_from=2025-01-01&date_to=2025-01-31&search=reception_number&per_page=15
POST   /receptions  (admin, warehouse, farm)
  Input: { reception_number, source_type: 'purchase|output', source_id, origin_location_id, destination_location_id, shipment_date?, observations? }
GET    /receptions/{id}
GET    /receptions/{id}/batches
GET    /receptions/{id}/pending-products
POST   /receptions/{id}/batches  (admin, warehouse, farm)
  Input: { reception_date, observations?, items: [{ product_id, quantity_received, condition, expiration_date?, observations? }, ...], attachments?: [...] }
PUT    /receptions/{id}/complete  (admin, warehouse, farm)
PUT    /receptions/{id}/cancel  (admin, warehouse, farm)
```

### INVENTORY (todos lectura, admin/warehouse escritura)
```
GET    /inventory?location_id=UUID&product_id=UUID&status=good&search=batch_number&per_page=15
GET    /inventory/{productId}
GET    /inventory/location/{locationId}
GET    /inventory/product/{productId}/details
GET    /inventory/movements?type=entry&product_id=UUID&location_id=UUID&start_date=2025-01-01&end_date=2025-01-31&search=observations&per_page=15
GET    /inventory/movements/product/{productId}?location_id=UUID&start_date=2025-01-01&end_date=2025-01-31&per_page=15
POST   /inventory/adjustments  (admin, warehouse)
  Input: { type: 'entry|exit|adjustment', product_id, brand_id, location_id, quantity, unit, expiration_date?, unit_price?, observations? }
```

### ALERTS (todos lectura, admin/supervisor/warehouse escritura)
```
GET    /alerts?type=low_stock&severity=high&status=active&location_id=UUID&product_id=UUID&search=title&per_page=15
POST   /alerts  (admin, supervisor, warehouse)
  Input: { type, title, description, location_id?, product_id?, severity }
GET    /alerts/{id}
PUT    /alerts/{id}/resolve  (admin, supervisor, warehouse)
PUT    /alerts/{id}/dismiss  (admin, supervisor, warehouse)
```

---

## PATRONES IMPORTANTES

### Validación de Transiciones de Estado

**Purchase**: ordered → in_transit → received (irreversible)

**ProductOutput**: pending → approved → in_transit → completed
- Aprobación crea InventoryMovements y reduce inventario (FIFO)

**Reception**: pending → partial → completed (o cancelled)
- Cada lote agregado recalcula estado y porcentaje

**TechnicalOrder**: draft → approved → completed (o cancelled)

### Operaciones Polymorphic

**Reception.source** → Puede ser Purchase o ProductOutput
**InventoryMovement.related_document** → Puede ser Purchase, Reception, ProductOutput, o TechnicalOrder

### Cálculos Automáticos

- **PurchaseResource**: tax = subtotal * 0.19 (IVA)
- **InventoryResource.status**: Calcula automáticamente (good|expired|near_expiry|low)
- **ProductOutput.totalCost**: Suma de costos de ítems
- **Reception.completionPercentage**: (totalReceived / totalExpected) * 100

### Autenticación JWT

- TTL configurable en `config('jwt.ttl')`
- Claims personalizados: role, status
- Renovación de tokens con `/auth/refresh`

---

## NOTAS ARQUITECTÓNICAS

1. **UUIDs**: Todos los IDs son UUID v4 (no autoincrementing)
2. **Soft Deletes**: No implementados de forma explícita, pero se pueden agregar
3. **Auditoría**: created_by y updated_by se manejan en algunos modelos
4. **Timestamps**: ISO 8601 en las respuestas
5. **Validación**: Se realiza en FormRequest classes (no mostradas en este documento)
6. **Transacciones**: Usadas en operaciones multi-entidad (DB::beginTransaction)
7. **Archivos**: Almacenados en storage/app/public con rutas relativas
8. **Paginación**: Por defecto 15 registros por página, configurable con per_page

---

**Fin del documento**

