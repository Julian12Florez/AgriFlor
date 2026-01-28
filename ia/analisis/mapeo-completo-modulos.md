# 🎯 MAPEO COMPLETO DE MÓDULOS - AgriFlor
## Análisis Detallado de Rutas, Páginas y Funcionalidades

---

## 🏗 ARQUITECTURA DE NAVEGACIÓN

### **Sidebar Navigation Structure**
```
📊 Dashboard
📁 Datos Maestros
  ├── 📦 Productos
  ├── 🏷️ Marcas
  ├── 🏪 Proveedores
  └── 📍 Fincas y Bodegas
🧪 Procesos Técnicos
  ├── 📋 Recetas Técnicas
  └── 📝 Órdenes Técnicas
🛒 Gestión de Compras
  ├── 💰 Compras
  └── 📥 Entradas a Bodega
🏛️ Gestión de Inventario
  ├── 📤 Salidas de Bodega
  ├── 📨 Recepción en Finca
  ├── 🔄 Transferencias
  └── 📊 Inventario y Kardex
📈 Reportes y Alertas
  ├── 🚨 Alertas
  └── 📊 Reportes
👥 Administración
  ├── 👤 Usuarios
  └── ⚙️ Configuración
```

---

## 📁 MÓDULO 1: DATOS MAESTROS

### **📦 1.1 PRODUCTOS**
**Ruta:** `/master/products`

#### **Páginas Específicas:**
- **Lista:** `/master/products` - Tabla con filtros y búsqueda
- **Crear:** `/master/products/create` - Formulario nuevo producto
- **Editar:** `/master/products/:id/edit` - Formulario edición
- **Detalle:** `/master/products/:id` - Vista detallada con kardex

#### **Componentes React:**
```typescript
// src/pages/master/products/
├── ProductsPage.tsx           // Lista principal
├── ProductCreatePage.tsx      // Formulario crear
├── ProductEditPage.tsx        // Formulario editar
├── ProductDetailPage.tsx      // Vista detalle
└── components/
    ├── ProductForm.tsx        // Formulario reutilizable
    ├── ProductTable.tsx       // Tabla con filtros
    ├── ProductFilters.tsx     // Filtros avanzados
    └── ProductCard.tsx        // Card para vista grid
```

#### **Funcionalidades CRUD:**
- ✅ **Create:** Formulario con validaciones (nombre, categoría, unidad, marcas)
- ✅ **Read:** Lista paginada con filtros por categoría, estado, marca
- ✅ **Update:** Edición in-line y modal
- ✅ **Delete:** Soft delete con confirmación
- ✅ **Bulk Actions:** Activar/desactivar múltiples
- ✅ **Export:** Excel/CSV de productos

#### **Mock Data Structure:**
```typescript
interface Product {
  id: string;
  name: string;
  category: 'fertilizante' | 'pesticida' | 'herbicida' | 'fungicida';
  baseUnit: 'kg' | 'litros' | 'unidades';
  status: 'active' | 'inactive';
  brands: Brand[];
  description?: string;
  createdBy: string;
  createdAt: Date;
  updatedAt: Date;
}
```

---

### **🏷️ 1.2 MARCAS**
**Ruta:** `/master/brands`

#### **Páginas Específicas:**
- **Lista:** `/master/brands` - Tabla simple
- **Crear:** `/master/brands/create` - Modal/formulario
- **Editar:** `/master/brands/:id/edit` - Modal/formulario

#### **Componentes React:**
```typescript
// src/pages/master/brands/
├── BrandsPage.tsx             // Lista principal
├── BrandCreateModal.tsx       // Modal crear
├── BrandEditModal.tsx         // Modal editar
└── components/
    ├── BrandForm.tsx          // Formulario
    ├── BrandTable.tsx         // Tabla
    └── BrandActions.tsx       // Acciones CRUD
```

#### **Funcionalidades CRUD:**
- ✅ **Create:** Modal rápido (nombre, estado)
- ✅ **Read:** Lista simple con búsqueda
- ✅ **Update:** Edición in-line
- ✅ **Delete:** Con validación de productos asociados
- ✅ **Association:** Ver productos por marca

---

### **🏪 1.3 PROVEEDORES**
**Ruta:** `/master/suppliers`

#### **Páginas Específicas:**
- **Lista:** `/master/suppliers` - Tabla con contactos
- **Crear:** `/master/suppliers/create` - Formulario completo
- **Editar:** `/master/suppliers/:id/edit` - Formulario edición
- **Detalle:** `/master/suppliers/:id` - Vista con historial compras

#### **Componentes React:**
```typescript
// src/pages/master/suppliers/
├── SuppliersPage.tsx          // Lista principal
├── SupplierCreatePage.tsx     // Formulario crear
├── SupplierEditPage.tsx       // Formulario editar
├── SupplierDetailPage.tsx     // Vista detalle
└── components/
    ├── SupplierForm.tsx       // Formulario complejo
    ├── SupplierTable.tsx      // Tabla con contactos
    ├── SupplierContacts.tsx   // Gestión contactos
    └── PurchaseHistory.tsx    // Historial compras
```

#### **Funcionalidades CRUD:**
- ✅ **Create:** Formulario con contactos múltiples
- ✅ **Read:** Lista con filtros por ciudad, estado
- ✅ **Update:** Edición completa
- ✅ **Delete:** Validación de compras existentes
- ✅ **Contacts:** CRUD de contactos anidado
- ✅ **History:** Historial de compras

#### **Mock Data Structure:**
```typescript
interface Supplier {
  id: string;
  name: string;
  nit: string;
  address: string;
  city: string;
  phone: string;
  email: string;
  contacts: SupplierContact[];
  status: 'active' | 'inactive';
  paymentTerms: string;
  createdAt: Date;
}
```

---

### **📍 1.4 FINCAS Y BODEGAS**
**Ruta:** `/master/locations`

#### **Páginas Específicas:**
- **Lista:** `/master/locations` - Tabla con tipos
- **Crear:** `/master/locations/create` - Formulario con mapa
- **Editar:** `/master/locations/:id/edit` - Formulario edición
- **Detalle:** `/master/locations/:id` - Vista con inventario actual

#### **Componentes React:**
```typescript
// src/pages/master/locations/
├── LocationsPage.tsx          // Lista principal
├── LocationCreatePage.tsx     // Formulario crear
├── LocationEditPage.tsx       // Formulario editar
├── LocationDetailPage.tsx     // Vista detalle
└── components/
    ├── LocationForm.tsx       // Formulario
    ├── LocationTable.tsx      // Tabla
    ├── LocationMap.tsx        // Mapa (mock)
    ├── LocationFilters.tsx    // Filtros tipo/municipio
    └── CurrentInventory.tsx   // Stock actual
```

#### **Funcionalidades CRUD:**
- ✅ **Create:** Formulario con coordenadas
- ✅ **Read:** Lista filtrada por tipo (finca/bodega)
- ✅ **Update:** Edición completa
- ✅ **Delete:** Validación de inventario existente
- ✅ **Map View:** Vista de mapa (mock)
- ✅ **Inventory:** Stock actual por ubicación

---

## 🧪 MÓDULO 2: PROCESOS TÉCNICOS

### **📋 2.1 RECETAS TÉCNICAS**
**Ruta:** `/technical/recipes`

#### **Páginas Específicas:**
- **Lista:** `/technical/recipes` - Cards/tabla de recetas
- **Crear:** `/technical/recipes/create` - Formulario con productos
- **Editar:** `/technical/recipes/:id/edit` - Formulario edición
- **Detalle:** `/technical/recipes/:id` - Vista con productos incluidos
- **Duplicar:** `/technical/recipes/:id/duplicate` - Clonar receta

#### **Componentes React:**
```typescript
// src/pages/technical/recipes/
├── RecipesPage.tsx            // Lista principal
├── RecipeCreatePage.tsx       // Formulario crear
├── RecipeEditPage.tsx         // Formulario editar
├── RecipeDetailPage.tsx       // Vista detalle
├── RecipeDuplicatePage.tsx    // Duplicar receta
└── components/
    ├── RecipeForm.tsx         // Formulario principal
    ├── RecipeTable.tsx        // Tabla recetas
    ├── RecipeCard.tsx         // Card vista
    ├── ProductSelector.tsx    // Selector productos
    ├── RecipeProducts.tsx     // Lista productos en receta
    └── RecipePreview.tsx      // Preview antes guardar
```

#### **Funcionalidades CRUD:**
- ✅ **Create:** Formulario con selector de productos múltiple
- ✅ **Read:** Lista con filtros por estado, categoría
- ✅ **Update:** Edición de productos incluidos
- ✅ **Delete:** Con validación de órdenes técnicas que la usan
- ✅ **Duplicate:** Clonar receta existente
- ✅ **Activate/Deactivate:** Cambio de estado
- ✅ **Product Management:** Agregar/quitar/modificar productos
- ✅ **Usage History:** Órdenes que han usado la receta

#### **Mock Data Structure:**
```typescript
interface TechnicalRecipe {
  id: string;
  name: string;
  description: string;
  category: string;
  status: 'active' | 'inactive';
  products: RecipeProduct[];
  usageCount: number; // Cuántas órdenes la han usado
  createdBy: string;
  createdAt: Date;
  lastUsed?: Date;
}

interface RecipeProduct {
  productId: string;
  productName: string;
  brandId: string;
  brandName: string;
  quantity: number;
  unit: string;
  observations?: string;
}
```

---

### **📝 2.2 ÓRDENES TÉCNICAS**
**Ruta:** `/technical/orders`

#### **Páginas Específicas:**
- **Lista:** `/technical/orders` - Tabla con estados
- **Crear:** `/technical/orders/create` - Formulario con recetas
- **Editar:** `/technical/orders/:id/edit` - Formulario edición (solo si no aplicada)
- **Detalle:** `/technical/orders/:id` - Vista completa
- **Aplicar:** `/technical/orders/:id/apply` - Proceso de aplicación
- **Historial:** `/technical/orders/history` - Órdenes aplicadas

#### **Componentes React:**
```typescript
// src/pages/technical/orders/
├── OrdersPage.tsx             // Lista principal
├── OrderCreatePage.tsx        // Formulario crear
├── OrderEditPage.tsx          // Formulario editar
├── OrderDetailPage.tsx        // Vista detalle
├── OrderApplyPage.tsx         // Aplicar orden
├── OrderHistoryPage.tsx       // Historial
└── components/
    ├── OrderForm.tsx          // Formulario principal
    ├── OrderTable.tsx         // Tabla órdenes
    ├── OrderCard.tsx          // Card vista
    ├── OrderStatus.tsx        // Badge estado
    ├── RecipeSelector.tsx     // Selector recetas
    ├── FarmSelector.tsx       // Selector fincas múltiple
    ├── OrderProducts.tsx      // Productos en orden
    ├── ApplicationForm.tsx    // Formulario aplicación
    └── OrderTimeline.tsx      // Timeline estados
```

#### **Funcionalidades CRUD:**
- ✅ **Create:** Formulario con opción de receta o manual
- ✅ **Read:** Lista filtrada por estado, fecha, finca
- ✅ **Update:** Solo órdenes no aplicadas
- ✅ **Delete:** Solo órdenes no aplicadas
- ✅ **Apply:** Proceso de aplicación en campo
- ✅ **Multi-Farm:** Una orden para múltiples fincas
- ✅ **Stock Validation:** Verificar disponibilidad antes crear
- ✅ **Recipe Integration:** Precargar productos desde receta
- ✅ **Status Tracking:** Estados: Creada → Aprobada → Aplicada

#### **Mock Data Structure:**
```typescript
interface TechnicalOrder {
  id: string;
  orderNumber: string; // AUTO-generated
  scheduledDate: Date;
  farmIds: string[];
  farmNames: string[];
  status: 'draft' | 'approved' | 'applied';
  recipeId?: string;
  recipeName?: string;
  products: OrderProduct[];
  appliedAt?: Date;
  appliedBy?: string;
  responsibleAgronomist: string;
  observations?: string;
  createdAt: Date;
  estimatedCost: number; // Calculado
}
```

---

## 🛒 MÓDULO 3: GESTIÓN DE COMPRAS

### **💰 3.1 COMPRAS**
**Ruta:** `/purchases`

#### **Páginas Específicas:**
- **Lista:** `/purchases` - Tabla con estados y totales
- **Crear:** `/purchases/create` - Formulario con items múltiples
- **Editar:** `/purchases/:id/edit` - Formulario edición
- **Detalle:** `/purchases/:id` - Vista completa con items
- **Recibir:** `/purchases/:id/receive` - Proceso recepción

#### **Componentes React:**
```typescript
// src/pages/purchases/
├── PurchasesPage.tsx          // Lista principal
├── PurchaseCreatePage.tsx     // Formulario crear
├── PurchaseEditPage.tsx       // Formulario editar
├── PurchaseDetailPage.tsx     // Vista detalle
├── PurchaseReceivePage.tsx    // Recibir compra
└── components/
    ├── PurchaseForm.tsx       // Formulario principal
    ├── PurchaseTable.tsx      // Tabla compras
    ├── PurchaseItems.tsx      // Items de compra
    ├── ItemSelector.tsx       // Selector productos/marcas
    ├── PurchaseStatus.tsx     // Badge estado
    ├── PurchaseTotals.tsx     // Totales calculados
    ├── SupplierInfo.tsx       // Info proveedor
    └── ReceiveForm.tsx        // Formulario recepción
```

#### **Funcionalidades CRUD:**
- ✅ **Create:** Formulario con items múltiples, cálculos automáticos
- ✅ **Read:** Lista con filtros por proveedor, estado, fecha
- ✅ **Update:** Edición completa (solo no recibidas)
- ✅ **Delete:** Solo compras no recibidas
- ✅ **Item Management:** Agregar/quitar/modificar items
- ✅ **Auto Calculations:** Subtotales, impuestos, total
- ✅ **Receive Process:** Marcar como recibida → trigger entrada bodega
- ✅ **Status Tracking:** Comprada → En tránsito → Recibida
- ✅ **Print:** Orden de compra (mock PDF)

#### **Mock Data Structure:**
```typescript
interface Purchase {
  id: string;
  orderNumber: string;
  supplierId: string;
  supplierName: string;
  purchaseDate: Date;
  expectedDelivery?: Date;
  status: 'ordered' | 'in_transit' | 'received' | 'cancelled';
  items: PurchaseItem[];
  subtotal: number;
  tax: number;
  total: number;
  observations?: string;
  attachments: string[]; // URLs archivos
  createdBy: string;
  receivedBy?: string;
  receivedAt?: Date;
}

interface PurchaseItem {
  id: string;
  productId: string;
  productName: string;
  brandId: string;
  brandName: string;
  quantity: number;
  unit: string;
  unitPrice: number;
  subtotal: number;
  batch?: string;
  expirationDate?: Date;
}
```

---

### **📥 3.2 ENTRADAS A BODEGA**
**Ruta:** `/warehouse/entries`

#### **Páginas Específicas:**
- **Lista:** `/warehouse/entries` - Tabla con lotes y vencimientos
- **Crear:** `/warehouse/entries/create` - Formulario entrada
- **Desde Compra:** `/warehouse/entries/from-purchase/:purchaseId` - Pre-llenado
- **Detalle:** `/warehouse/entries/:id` - Vista completa
- **Manual:** `/warehouse/entries/manual` - Entrada sin compra

#### **Componentes React:**
```typescript
// src/pages/warehouse/entries/
├── EntriesPage.tsx            // Lista principal
├── EntryCreatePage.tsx        // Formulario crear
├── EntryFromPurchasePage.tsx  // Desde compra
├── EntryDetailPage.tsx        // Vista detalle
├── EntryManualPage.tsx        // Entrada manual
└── components/
    ├── EntryForm.tsx          // Formulario principal
    ├── EntryTable.tsx         // Tabla entradas
    ├── EntryItems.tsx         // Items entrada
    ├── BatchForm.tsx          // Formulario lote/vencimiento
    ├── StockImpact.tsx        // Impacto en stock
    ├── PurchaseSelector.tsx   // Selector compras pendientes
    └── EntryValidation.tsx    // Validaciones
```

#### **Funcionalidades CRUD:**
- ✅ **Create:** Formulario con lotes y vencimientos obligatorios
- ✅ **Read:** Lista con filtros por fecha, producto, lote
- ✅ **Update:** Edición limitada (solo antes confirmar)
- ✅ **Delete:** Solo entradas no confirmadas
- ✅ **From Purchase:** Pre-llenar desde compra
- ✅ **Manual Entry:** Entradas sin compra previa (con justificación)
- ✅ **Stock Update:** **CRÍTICO - Actualiza inventario inmediatamente**
- ✅ **Batch Tracking:** Gestión de lotes y fechas vencimiento
- ✅ **Validation:** Cantidades vs. compra original
- ✅ **Documents:** Adjuntar remisiones, facturas

#### **Mock Data Structure:**
```typescript
interface WarehouseEntry {
  id: string;
  entryNumber: string;
  type: 'from_purchase' | 'manual' | 'transfer' | 'adjustment';
  purchaseId?: string;
  warehouseId: string;
  entryDate: Date;
  items: EntryItem[];
  responsibleUser: string;
  observations?: string;
  attachments: string[];
  status: 'draft' | 'confirmed';
  confirmedAt?: Date;
  confirmedBy?: string;
}

interface EntryItem {
  id: string;
  productId: string;
  productName: string;
  brandId: string;
  brandName: string;
  quantity: number;
  unit: string;
  unitPrice: number;
  batch: string; // OBLIGATORIO
  expirationDate: Date; // OBLIGATORIO
  location?: string; // Ubicación en bodega
}
```

---

## 🏛️ MÓDULO 4: GESTIÓN DE INVENTARIO

### **📤 4.1 SALIDAS DE BODEGA**
**Ruta:** `/warehouse/exits`

#### **Páginas Específicas:**
- **Lista:** `/warehouse/exits` - Tabla con estados y aprobaciones
- **Crear:** `/warehouse/exits/create` - Formulario salida
- **Desde Orden:** `/warehouse/exits/from-order/:orderId` - Pre-llenado
- **Detalle:** `/warehouse/exits/:id` - Vista completa
- **Aprobar:** `/warehouse/exits/:id/approve` - Proceso aprobación
- **Pendientes:** `/warehouse/exits/pending` - Salidas pendientes aprobación

#### **Componentes React:**
```typescript
// src/pages/warehouse/exits/
├── ExitsPage.tsx              // Lista principal
├── ExitCreatePage.tsx         // Formulario crear
├── ExitFromOrderPage.tsx      // Desde orden técnica
├── ExitDetailPage.tsx         // Vista detalle
├── ExitApprovePage.tsx        // Aprobar salida
├── ExitsPendingPage.tsx       // Pendientes aprobación
└── components/
    ├── ExitForm.tsx           // Formulario principal
    ├── ExitTable.tsx          // Tabla salidas
    ├── ExitItems.tsx          // Items salida
    ├── ExitValidation.tsx     // Validación 5% máximo
    ├── ApprovalFlow.tsx       // Flujo aprobación
    ├── ApprovalToken.tsx      // Token/enlace aprobación
    ├── OrderSelector.tsx      // Selector órdenes técnicas
    ├── StockAvailable.tsx     // Stock disponible
    └── ExitStatus.tsx         // Estados y timeline
```

#### **Funcionalidades CRUD:**
- ✅ **Create:** Formulario con validación stock disponible
- ✅ **Read:** Lista filtrada por estado, fecha, destino
- ✅ **Update:** Solo salidas no aprobadas
- ✅ **Delete:** Solo salidas pendientes
- ✅ **From Technical Order:** Pre-llenar desde orden técnica
- ✅ **5% Validation:** **CRÍTICO - Máximo 105% de lo solicitado**
- ✅ **Approval Process:** **CRÍTICO - Token/enlace para aprobador**
- ✅ **Stock Deduction:** **CRÍTICO - Descuenta stock inmediatamente**
- ✅ **Batch Selection:** FIFO automático o manual
- ✅ **Status Flow:** Pendiente → Aprobada → En tránsito → Recibida
- ✅ **Print:** Picking list, remisión

#### **Mock Data Structure:**
```typescript
interface WarehouseExit {
  id: string;
  exitNumber: string;
  type: 'technical_order' | 'free_request' | 'transfer';
  technicalOrderId?: string;
  destinationFarmId: string;
  destinationFarmName: string;
  requestedBy: string;
  exitDate: Date;
  items: ExitItem[];
  status: 'pending' | 'approved' | 'in_transit' | 'received' | 'rejected';
  approvalToken?: string; // Para enlace aprobación
  approvedBy?: string;
  approvedAt?: Date;
  rejectionReason?: string;
  observations?: string;
}

interface ExitItem {
  id: string;
  productId: string;
  productName: string;
  brandId: string;
  brandName: string;
  requestedQuantity: number;
  approvedQuantity: number; // Puede ser diferente
  unit: string;
  selectedBatches: SelectedBatch[]; // FIFO o manual
  observations?: string;
}

interface SelectedBatch {
  batch: string;
  expirationDate: Date;
  quantity: number;
  availableQuantity: number;
}
```

---

### **📨 4.2 RECEPCIÓN EN FINCA**
**Ruta:** `/farms/receptions`

#### **Páginas Específicas:**
- **Lista:** `/farms/receptions` - Tabla con validaciones
- **Crear:** `/farms/receptions/create` - Formulario recepción
- **Desde Salida:** `/farms/receptions/from-exit/:exitId` - Pre-llenado
- **Detalle:** `/farms/receptions/:id` - Vista completa
- **Pendientes:** `/farms/receptions/pending` - Salidas no recibidas

#### **Componentes React:**
```typescript
// src/pages/farms/receptions/
├── ReceptionsPage.tsx         // Lista principal
├── ReceptionCreatePage.tsx    // Formulario crear
├── ReceptionFromExitPage.tsx  // Desde salida
├── ReceptionDetailPage.tsx    // Vista detalle
├── ReceptionsPendingPage.tsx  // Pendientes recepción
└── components/
    ├── ReceptionForm.tsx      // Formulario principal
    ├── ReceptionTable.tsx     // Tabla recepciones
    ├── ReceptionItems.tsx     // Items recepción
    ├── QualityCheck.tsx       // Control calidad
    ├── DiscrepancyReport.tsx  // Reporte diferencias
    ├── ExitSelector.tsx       // Selector salidas pendientes
    └── ReceptionValidation.tsx // Validaciones cantidad
```

#### **Funcionalidades CRUD:**
- ✅ **Create:** Formulario validando contra salida original
- ✅ **Read:** Lista filtrada por finca, fecha, estado
- ✅ **Update:** Edición limitada después recepción
- ✅ **Delete:** Solo recepciones draft
- ✅ **From Exit:** Pre-llenar desde salida bodega
- ✅ **Quality Control:** Validación estado productos
- ✅ **Discrepancy Handling:** Reporte diferencias cantidad/calidad
- ✅ **Stock Update:** **CRÍTICO - Actualiza stock finca**
- ✅ **Approval/Rejection:** Aceptar o rechazar productos
- ✅ **Photo Evidence:** Adjuntar fotos productos recibidos

---

### **🔄 4.3 TRANSFERENCIAS ENTRE FINCAS**
**Ruta:** `/farms/transfers`

#### **Páginas Específicas:**
- **Lista:** `/farms/transfers` - Tabla origen/destino
- **Crear:** `/farms/transfers/create` - Formulario transferencia
- **Detalle:** `/farms/transfers/:id` - Vista completa
- **Recibir:** `/farms/transfers/:id/receive` - Confirmar recepción

#### **Componentes React:**
```typescript
// src/pages/farms/transfers/
├── TransfersPage.tsx          // Lista principal
├── TransferCreatePage.tsx     // Formulario crear
├── TransferDetailPage.tsx     // Vista detalle
├── TransferReceivePage.tsx    // Confirmar recepción
└── components/
    ├── TransferForm.tsx       // Formulario principal
    ├── TransferTable.tsx      // Tabla transferencias
    ├── TransferItems.tsx      // Items transferencia
    ├── FarmSelector.tsx       // Selector fincas origen/destino
    ├── TransferStatus.tsx     // Estados transferencia
    └── StockValidation.tsx    // Validar stock origen
```

#### **Funcionalidades CRUD:**
- ✅ **Create:** Formulario origen → destino
- ✅ **Read:** Lista filtrada por fincas, fecha, estado
- ✅ **Update:** Solo transferencias no enviadas
- ✅ **Delete:** Solo transferencias draft
- ✅ **Stock Validation:** Verificar disponibilidad en origen
- ✅ **Dual Stock Update:** **CRÍTICO - Resta origen, suma destino**
- ✅ **Status Tracking:** Creada → En tránsito → Recibida
- ✅ **Justification:** Motivo de transferencia obligatorio

---

### **📊 4.4 INVENTARIO Y KARDEX**
**Ruta:** `/inventory`

#### **Páginas Específicas:**
- **Dashboard:** `/inventory` - Resumen general stock
- **Kardex:** `/inventory/kardex` - Movimientos detallados
- **Por Ubicación:** `/inventory/by-location` - Stock por finca/bodega
- **Por Producto:** `/inventory/by-product` - Stock por producto
- **Vencimientos:** `/inventory/expirations` - Productos próximos vencer
- **Stock Bajo:** `/inventory/low-stock` - Productos con stock mínimo

#### **Componentes React:**
```typescript
// src/pages/inventory/
├── InventoryDashboard.tsx     // Dashboard principal
├── KardexPage.tsx             // Kardex detallado
├── InventoryByLocation.tsx    // Por ubicación
├── InventoryByProduct.tsx     // Por producto
├── ExpirationReport.tsx       // Vencimientos
├── LowStockReport.tsx         // Stock bajo
└── components/
    ├── InventoryCard.tsx      // Cards métricas
    ├── KardexTable.tsx        // Tabla kardex
    ├── InventoryFilters.tsx   // Filtros avanzados
    ├── StockChart.tsx         // Gráficos stock
    ├── MovementTimeline.tsx   // Timeline movimientos
    ├── ExpirationAlert.tsx    // Alertas vencimiento
    └── StockActions.tsx       // Acciones rápidas
```

#### **Funcionalidades:**
- ✅ **Real-time Stock:** Stock actual por ubicación
- ✅ **Kardex Complete:** Historial completo movimientos
- ✅ **Advanced Filters:** Por fecha, producto, ubicación, tipo movimiento
- ✅ **Expiration Tracking:** Productos próximos a vencer
- ✅ **Low Stock Alerts:** Productos bajo mínimo
- ✅ **Movement Analysis:** Análisis entrada/salida por período
- ✅ **Export Reports:** Excel/PDF del kardex
- ✅ **Batch Tracking:** Seguimiento lotes individual
- ✅ **Cost Analysis:** Análisis valor inventario

---

## 📈 MÓDULO 5: REPORTES Y ALERTAS

### **🚨 5.1 ALERTAS**
**Ruta:** `/alerts`

#### **Páginas Específicas:**
- **Dashboard:** `/alerts` - Panel alertas activas
- **Configuración:** `/alerts/config` - Configurar umbrales
- **Historial:** `/alerts/history` - Alertas pasadas

#### **Tipos de Alertas:**
- 🔴 **Vencimientos:** Productos vencen en 7/15/30 días
- 🟡 **Stock Bajo:** Productos bajo mínimo configurado
- 🟠 **Órdenes Atrasadas:** Órdenes técnicas no aplicadas
- 🔵 **Aprobaciones Pendientes:** Salidas pendientes aprobación
- 🟢 **Sin Movimiento:** Productos sin rotación 90+ días

### **📊 5.2 REPORTES**
**Ruta:** `/reports`

#### **Páginas Específicas:**
- **Dashboard:** `/reports` - Catálogo reportes
- **Consumo por Finca:** `/reports/consumption-by-farm`
- **Eficiencia Técnica:** `/reports/technical-efficiency`
- **Rotación Inventario:** `/reports/inventory-rotation`
- **Trazabilidad Producto:** `/reports/product-traceability`

---

## 👥 MÓDULO 6: ADMINISTRACIÓN

### **👤 6.1 USUARIOS**
**Ruta:** `/admin/users`

#### **Funcionalidades:**
- ✅ **CRUD Usuarios:** Crear, editar, desactivar usuarios
- ✅ **Roles y Permisos:** Asignación granular permisos
- ✅ **Auditoría:** Log actividades usuario
- ✅ **Reset Password:** Reseteo contraseñas

### **⚙️ 6.2 CONFIGURACIÓN**
**Ruta:** `/admin/config`

#### **Configuraciones:**
- ✅ **Umbrales Stock:** Mínimos por producto
- ✅ **Alertas:** Configuración días vencimiento
- ✅ **Aprobadores:** Usuarios para aprobar salidas
- ✅ **Notificaciones:** Configuración push notifications

---

## 🎯 RESUMEN TÉCNICO

### **Total de Páginas a Implementar: 45+**

### **Componentes Reutilizables Críticos:**
- FormBuilder (formularios dinámicos)
- DataTable (tablas con filtros)
- StatusBadge (estados visuales)
- StockIndicator (indicadores stock)
- DateRangePicker (filtros fecha)
- BatchSelector (selector lotes)
- ApprovalFlow (flujo aprobaciones)

### **Hooks Personalizados:**
- useInventoryMovements
- useStockValidation
- useApprovalFlow
- useBatchSelection
- useExpirationTracking

### **Estado Global (Zustand):**
- User & Auth
- Current Stock
- Pending Approvals
- Active Alerts
- Navigation State

---

**🎯 RESULTADO:** Mapeo completo de todas las funcionalidades CRUD, rutas específicas y componentes necesarios para implementar AgriFlor sin dejar escapar ningún detalle.