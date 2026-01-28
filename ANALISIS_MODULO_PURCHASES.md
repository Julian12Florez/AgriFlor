# ANÁLISIS EXHAUSTIVO DEL MÓDULO DE COMPRAS/PURCHASES - AGRIFLOR FRONTEND

**Fecha de Análisis:** 2025-11-17  
**Nivel de Exhaustividad:** Very Thorough  
**Proyecto:** Sistema de Gestión de Inventario Agrícola AgriFlor  

---

## INDICE
1. [Arquitectura General del Módulo](#arquitectura)
2. [Archivos y Estructura](#archivos)
3. [Interfaces y Estructura de Datos](#interfaces)
4. [APIs Consumidas](#apis-consumidas)
5. [Validaciones Implementadas](#validaciones)
6. [Funcionalidades Específicas](#funcionalidades)
7. [Campos de Formulario](#campos-formulario)
8. [Gestión de Items de Compra](#gestion-items)
9. [Adjunción de Archivos](#adjuntos)
10. [Cálculos Automáticos](#calculos)
11. [APIs Faltantes en Backend](#apis-faltantes)

---

## <a id="arquitectura"></a>1. ARQUITECTURA GENERAL DEL MÓDULO

### 1.1. Ubicación en la Estructura del Proyecto
```
/home/julian/Documentos/AgriFlor/frontend/src/
├── pages/
│   └── purchases/
│       └── Purchases.tsx (Componente principal)
├── mock/
│   └── purchases.ts (Archivo mock VACÍO - requiere eliminación)
├── data/
│   ├── mockData.ts (Datos de ejemplo para compras)
│   └── types.ts (Definición de tipos TypeScript)
├── utils/
│   └── pdfGenerator.ts (Generador de PDFs para órdenes)
└── services/
    └── mockApi.ts (Servicio API mock)
```

### 1.2. Componentes Principales
El módulo está implementado como un único componente funcional de React (`Purchases.tsx`) que gestiona:
- Listado de órdenes de compra (vista responsiva)
- Formulario de creación de nuevas órdenes
- Modal de detalles
- Gestión de estados
- Generación y descarga de PDFs

### 1.3. Stack Utilizado
- **Framework:** React 18 + TypeScript
- **UI Components:** Ant Design
- **Gestión de Estado:** React Hooks (useState, useEffect)
- **Fechas:** dayjs
- **PDF:** jsPDF (funciones wrapper en pdfGenerator.ts)
- **Formularios:** Ant Design Form con Form.List para items dinámicos

---

## <a id="archivos"></a>2. ARCHIVOS Y ESTRUCTURA DETALLADA

### 2.1. Archivo Principal: Purchases.tsx
**Ruta:** `/home/julian/Documentos/AgriFlor/frontend/src/pages/purchases/Purchases.tsx`  
**Tamaño:** ~1006 líneas  
**Responsabilidades:**
- Gestión de estado de compras
- Renderizado de tabla responsiva (móvil/escritorio)
- Formulario de creación de compras
- Generación de PDFs
- Transiciones de estados

### 2.2. Archivo Mock: purchases.ts
**Ruta:** `/home/julian/Documentos/AgriFlor/frontend/src/mock/purchases.ts`  
**Status:** VACÍO (archivo sin contenido)  
**Acción Recomendada:** Eliminar - ya no es necesario

### 2.3. Datos Mock: mockData.ts
**Ruta:** `/home/julian/Documentos/AgriFlor/frontend/src/data/mockData.ts`  
**Líneas:** 1-1289  
**Contiene:**
- `mockPurchases` - Array de 3 órdenes de ejemplo (líneas 1078-1288)
- `mockSuppliers` - Proveedores (líneas 98-141)
- `mockProducts` - Productos disponibles (líneas 144-337)
- `mockLocations` - Ubicaciones de entrega (líneas 40-95)

### 2.4. Tipos TypeScript: types.ts
**Ruta:** `/home/julian/Documentos/AgriFlor/frontend/src/data/types.ts`  
**Contiene:** Interfaces compartidas del sistema

### 2.5. Generador de PDF: pdfGenerator.ts
**Ruta:** `/home/julian/Documentos/AgriFlor/frontend/src/utils/pdfGenerator.ts`  
**Líneas:** 587  
**Características:**
- Genera HTML+CSS para PDFs
- Soporta información completa de compra
- Información de proveedor
- Detalles de entrega
- Tabla de productos
- Cálculos de totales
- Términos y condiciones
- Espacios para firmas

---

## <a id="interfaces"></a>3. INTERFACES Y ESTRUCTURA DE DATOS

### 3.1. Interfaz Purchase (Orden de Compra)
```typescript
interface Purchase {
  id: string;                          // Identificador único
  orderNumber: string;                 // PUR-YYYY-XXX
  supplierId: string;                  // ID del proveedor
  supplierName: string;                // Nombre del proveedor
  supplier?: Supplier;                 // Objeto completo del proveedor
  destinationLocationId: string;       // ID ubicación destino
  destinationLocationName: string;     // Nombre ubicación
  purchaseDate: Date;                  // Fecha de compra
  expectedDelivery?: Date;             // Fecha entrega esperada (opcional)
  status: 'ordered' | 'in_transit' | 'received' | 'cancelled'; // Estados
  items: PurchaseItem[];               // Array de items
  subtotal: number;                    // Subtotal sin IVA
  tax: number;                         // IVA (19% calculado)
  total: number;                       // Total con IVA
  observations?: string;               // Notas adicionales (opcional)
  attachments: string[];               // Array de URLs de archivos
  createdBy: string;                   // Usuario creador
  receivedBy?: string;                 // Usuario que recibió (opcional)
  receivedAt?: Date;                   // Fecha de recepción (opcional)
  createdAt: Date;                     // Fecha creación registro
}
```

### 3.2. Interfaz PurchaseItem (Item de Compra)
```typescript
interface PurchaseItem {
  id: string;                          // ID único del item
  productId: string;                   // ID del producto
  productName: string;                 // Nombre del producto
  brandId: string;                     // ID de la marca
  brandName: string;                   // Nombre de la marca
  quantity: number;                    // Cantidad en unidades de empaque
  quantityInBaseUnits: number;         // Cantidad convertida a unidad base
  unit: string;                        // Unidad base (kg, L, etc)
  packagingUnitId: string;             // ID unidad de empaque seleccionada
  packagingUnitName: string;           // Nombre de la unidad de empaque
  baseQuantityPerUnit: number;         // Factor de conversión
  unitPrice: number;                   // Precio por unidad de empaque
  subtotal: number;                    // quantity × unitPrice
  expirationDate?: Date;               // Fecha vencimiento (se llena en recepción)
}
```

### 3.3. Interfaz Supplier (Proveedor)
```typescript
interface Supplier {
  id: string;
  name: string;
  nit: string;
  address: string;
  city: string;
  department: string;
  phone: string;
  email: string;
  contactPerson: string;
  contactPhone: string;
  paymentTerms: string;
  status: string;
}
```

---

## <a id="apis-consumidas"></a>4. APIs CONSUMIDAS POR EL MÓDULO

### 4.1. APIs Actualmente Implementadas (Mock)

El módulo **NO consume APIs reales del backend**. Utiliza datos mock del archivo `mockData.ts`.

**Funciones que DEBERÍA consumir del backend:**

#### 4.1.1. Obtener Lista de Compras
```
GET /api/purchases
Query Parameters:
  - status: 'ordered' | 'in_transit' | 'received' | 'cancelled' (opcional)
  - search: string (búsqueda por número de orden o nombre de proveedor)
  - page: number (paginación)
  - limit: number (registros por página)

Response:
{
  success: boolean,
  data: Purchase[],
  pagination: {
    total: number,
    page: number,
    limit: number
  }
}
```

#### 4.1.2. Obtener Detalle de una Compra
```
GET /api/purchases/{id}

Response:
{
  success: boolean,
  data: Purchase
}
```

#### 4.1.3. Crear Nueva Orden de Compra
```
POST /api/purchases
Content-Type: application/json

Body:
{
  supplierId: string,
  purchaseDate: Date,
  destinationLocationId: string,
  expectedDelivery: Date (opcional),
  observations: string (opcional),
  items: [
    {
      productId: string,
      quantity: number,
      packagingUnitId: string,
      unitPrice: number
    }
  ]
}

Response:
{
  success: boolean,
  data: Purchase,
  message: string
}
```

#### 4.1.4. Actualizar Estado de Compra
```
PUT /api/purchases/{id}/status
Content-Type: application/json

Body:
{
  status: 'ordered' | 'in_transit' | 'received' | 'cancelled'
}

Response:
{
  success: boolean,
  data: Purchase,
  message: string
}
```

#### 4.1.5. Obtener Proveedores (para dropdown)
```
GET /api/suppliers
Query Parameters:
  - status: 'active' (para filtrar solo activos)

Response:
{
  success: boolean,
  data: Supplier[]
}
```

#### 4.1.6. Obtener Productos (para dropdown)
```
GET /api/products
Query Parameters:
  - status: 'active'
  - category: string (opcional)

Response:
{
  success: boolean,
  data: Product[]
}
```

#### 4.1.7. Obtener Ubicaciones (para dropdown)
```
GET /api/locations
Query Parameters:
  - status: 'active'
  - type: 'warehouse' | 'farm' (opcional)

Response:
{
  success: boolean,
  data: Location[]
}
```

#### 4.1.8. Obtener Unidades de Empaque para un Producto
```
GET /api/products/{productId}/packaging-units

Response:
{
  success: boolean,
  data: PackagingUnit[]
}
```

---

## <a id="validaciones"></a>5. VALIDACIONES IMPLEMENTADAS

### 5.1. Validaciones a Nivel de Formulario

#### A. Validaciones Obligatorias:
1. **Proveedor** (supplierId)
   - Requerido: ✓
   - Regla: `{ required: true, message: 'Selecciona un proveedor' }`

2. **Fecha de Compra** (purchaseDate)
   - Requerido: ✓
   - Regla: `{ required: true, message: 'Selecciona la fecha' }`

3. **Ubicación de Destino** (destinationLocationId)
   - Requerido: ✓
   - Regla: `{ required: true, message: 'Selecciona la ubicación de entrega' }`

4. **Producto en Items** (items[].productId)
   - Requerido: ✓
   - Regla: `{ required: true, message: 'Selecciona un producto' }`

5. **Cantidad en Items** (items[].quantity)
   - Requerido: ✓
   - Regla: `{ required: true, message: 'Ingresa cantidad' }`
   - Mínimo: 1

6. **Unidad de Empaque** (items[].packagingUnitId)
   - Requerido: ✓
   - Regla: `{ required: true, message: 'Selecciona unidad' }`

7. **Precio Unitario** (items[].unitPrice)
   - Requerido: ✓
   - Regla: `{ required: true, message: 'Ingresa precio' }`
   - Mínimo: 0

#### B. Validaciones Opcionales:
1. **Fecha de Entrega Esperada** - Optional
2. **Observaciones** - Optional

### 5.2. Validaciones de Lógica de Negocio

```typescript
// 1. Conversión de unidades automática
const quantityInBaseUnits = quantity * baseQuantityPerUnit;

// 2. Cálculo automático de subtotales
const itemSubtotal = quantity * unitPrice;

// 3. Validación de estado permitido
const allowedActions = {
  'ordered': ['in_transit', 'cancelled'],
  'in_transit': ['received', 'cancelled', 'create_reception'],
  'received': ['create_reception'],
  'cancelled': []
};

// 4. Restricción de ubicaciones activas
filter(location => location.status === 'active')
```

### 5.3. Validaciones en PDF
- Conversión correcta de unidades
- Cálculo de IVA (19% fijo)
- Formato de fechas (DD/MM/YYYY)
- Información completa del proveedor

---

## <a id="funcionalidades"></a>6. FUNCIONALIDADES ESPECÍFICAS IDENTIFICADAS

### 6.1. Gestión de Órdenes de Compra

#### A. Crear Nueva Orden
```typescript
handleCreatePurchase(values: any) {
  // 1. Procesa items:
  //    - Busca información de producto
  //    - Obtiene unidad de empaque
  //    - Calcula cantidad en unidades base
  //    - Calcula subtotal de item
  
  // 2. Calcula totales:
  //    - Subtotal = suma de subtotales de items
  //    - Tax = subtotal × 0.19 (IVA 19%)
  //    - Total = subtotal + tax
  
  // 3. Genera número de orden:
  //    - Formato: PUR-YYYY-XXX
  //    - Ejemplo: PUR-2025-001
  
  // 4. Crea objeto Purchase
  // 5. Actualiza estado local
  // 6. Limpia formulario
}
```

#### B. Visualizar Detalles
- Modal/Drawer con información completa
- Tabla de items con detalles de empaque
- Cálculos de totales
- Observaciones (si existen)
- Botones de acciones permitidas según estado

#### C. Cambiar Estado
```typescript
handleStatusChange(purchaseId: string, newStatus: string) {
  // Transiciones permitidas:
  // ordered → in_transit, cancelled
  // in_transit → received, cancelled, create_reception
  // received → create_reception
  // cancelled → (sin transiciones)
}
```

#### D. Generar PDF
- Descarga orden en formato PDF/HTML
- Abre en nueva ventana para impresión
- Incluye:
  - Información de empresa
  - Número y fecha de orden
  - Datos del proveedor completos
  - Términos de pago
  - Dirección de entrega
  - Tabla de productos
  - Cálculos (subtotal, IVA, total)
  - Observaciones
  - Términos y condiciones
  - Espacios para firmas

### 6.2. Búsqueda y Filtrado

#### A. Búsqueda por Texto
```typescript
filteredPurchases = purchases.filter(purchase => {
  return purchase.orderNumber.toLowerCase().includes(searchText) ||
         purchase.supplierName.toLowerCase().includes(searchText)
})
```
- Busca en número de orden
- Busca en nombre de proveedor

#### B. Filtrado por Estado
```typescript
statusFilter: 'ordered' | 'in_transit' | 'received' | 'cancelled' | undefined
```

### 6.3. Gestión de Unidades de Empaque

#### A. Conversión Automática
Cuando el usuario selecciona una unidad de empaque:
1. Busca el `baseQuantityPerUnit` de esa unidad
2. Multiplica: `quantityInBaseUnits = quantity × baseQuantityPerUnit`
3. Actualiza campo de equivalencia total automáticamente

#### B. Equivalencias Mostradas en PDF
```
Formato en PDF: "X EMPAQUES × Y (unidad base) = Z (unidad base)"
Ejemplo: "50 Bultos × 50 kg = 2500 kg"
```

### 6.4. Gestión de Proveedores
- Selección de proveedor desde dropdown
- Información completa del proveedor mostrada en detalles
- Datos incluidos en PDF:
  - Razón social
  - NIT
  - Dirección
  - Contacto
  - Teléfono/Celular
  - Email
  - Términos de pago

### 6.5. Responsividad

#### A. Tabla de Escritorio (≥768px)
- Columnas: Orden, Proveedor, Productos, Total, Entrega, Estado, Acciones
- Expansible para ver totales

#### B. Tabla Móvil (<768px)
- Columna simple con compra + estado + total
- Acciones: Ver, PDF, Acción principal

### 6.6. Interfaz de Usuario

#### A. Botones de Acción Según Estado
```typescript
ordered: [
  { 'in_transit': 'Marcar En Tránsito' },
  { 'cancelled': 'Cancelar' }
]

in_transit: [
  { 'create_reception': 'Crear Recepción' },
  { 'received': 'Marcar Recibido' },
  { 'cancelled': 'Cancelar' }
]

received: [
  { 'create_reception': 'Crear Recepción' }
]
```

#### B. Iconografía
- `ShoppingCartOutlined` - Compra
- `TruckOutlined` - En tránsito
- `CheckCircleOutlined` - Recibido
- `ExclamationCircleOutlined` - Cancelado
- `InboxOutlined` - Crear recepción
- `PrinterOutlined` - Imprimir PDF
- `DownloadOutlined` - Descargar PDF

---

## <a id="campos-formulario"></a>7. CAMPOS DE FORMULARIO DETALLADOS

### 7.1. Campos de Encabezado

| Campo | Tipo | Validación | Placeholder | Notas |
|-------|------|-----------|-------------|-------|
| `supplierId` | Select | Requerido | "Selecciona un proveedor" | Datos de mockSuppliers |
| `purchaseDate` | DatePicker | Requerido | - | Formato DD/MM/YYYY |
| `destinationLocationId` | Select | Requerido | "Selecciona ubicación" | Solo activas |
| `expectedDelivery` | DatePicker | Opcional | - | Formato DD/MM/YYYY |
| `observations` | TextArea | Opcional | "Observaciones..." | 3 filas |

### 7.2. Campos Dinámicos de Items (Form.List)

Para cada item de compra:

| Campo | Tipo | Validación | Notas |
|-------|------|-----------|-------|
| `items[n].productId` | Select | Requerido | Busca en mockProducts |
| `items[n].quantity` | InputNumber | Requerido, min=1 | Cantidad en unidades empaque |
| `items[n].packagingUnitId` | Select | Requerido | Varía según producto |
| `items[n].baseUnit` | Input (disabled) | Auto-poblado | Muestra unidad base |
| `items[n].totalEquivalence` | Input (disabled) | Auto-calculado | Muestra total en unidad base |
| `items[n].unitPrice` | InputNumber | Requerido, min=0 | Precio por unidad |
| Botón Eliminar | Button | - | Elimina el item |

### 7.3. Botones Principales

```
Cancelar | Crear Orden de Compra
```

---

## <a id="gestion-items"></a>8. CÓMO MANEJA LOS ITEMS DE COMPRA

### 8.1. Estructura de Form.List

```jsx
<Form.Item label="Productos">
  <Form.List name="items">
    {(fields, { add, remove }) => (
      <>
        {fields.map(({ key, name, ...restField }) => (
          // Card con campos del item
        ))}
        <Button type="dashed" onClick={() => add()} block>
          + Agregar Producto
        </Button>
      </>
    )}
  </Form.List>
</Form.Item>
```

### 8.2. Agregar Item

Hace clic en "+ Agregar Producto":
1. Agrega nueva fila al Form.List
2. Inicializa campos vacíos
3. Permite seleccionar producto y empaque

### 8.3. Editar Item

Cambios con actualizaciones automáticas:

#### A. Al cambiar Producto:
```typescript
onChange={(productId) => {
  // 1. Busca producto en mockProducts
  // 2. Obtiene unidad base
  // 3. Limpia packagingUnitId
  // 4. Limpia equivalencia
}}
```

#### B. Al cambiar Cantidad:
```typescript
onChange={(quantity) => {
  // 1. Obtiene baseQuantityPerUnit del empaque
  // 2. Calcula: totalEquivalence = quantity × baseQuantityPerUnit
  // 3. Actualiza campo de equivalencia total
}}
```

#### C. Al cambiar Unidad de Empaque:
```typescript
onChange={(packagingUnitId) => {
  // 1. Busca unidad en producto
  // 2. Obtiene baseQuantityPerUnit
  // 3. Obtiene nombre de unidad
  // 4. Calcula equivalencia total
  // 5. Actualiza campos relacionados
}}
```

### 8.4. Eliminar Item

Botón "×" rojo: Llama a `remove(name)` del Form.List

### 8.5. Validaciones por Item

```
- Producto: obligatorio
- Cantidad: obligatorio, mínimo 1
- Unidad de empaque: obligatoria
- Precio unitario: obligatorio, mínimo 0
```

---

## <a id="adjuntos"></a>9. FUNCIONALIDAD DE ADJUNCIÓN DE ARCHIVOS

### 9.1. Estado Actual

**ESTADO: NO IMPLEMENTADO en la UI**

El campo `attachments: string[]` existe en la interfaz `Purchase` pero:
- No hay componente Upload visible en el formulario
- No hay visualización de archivos adjuntos
- Array siempre se inicializa vacío: `attachments: []`

### 9.2. Campos Disponibles

La interfaz soporta:
```typescript
attachments: string[];  // Array de URLs o paths de archivos
```

### 9.3. Funcionalidades Necesarias (Propuesta)

Para implementar adjuntos se necesitaría:

#### A. En el Formulario
```jsx
<Form.Item 
  name="attachments"
  label="Adjuntos (Factura, Especificaciones, etc.)"
>
  <Upload 
    multiple
    accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.png"
  />
</Form.Item>
```

#### B. En Detalles
```jsx
{selectedPurchase.attachments && selectedPurchase.attachments.length > 0 && (
  <div>
    <Divider>Archivos Adjuntos</Divider>
    {selectedPurchase.attachments.map((attachment, index) => (
      <a key={index} href={attachment}>{attachment}</a>
    ))}
  </div>
)}
```

#### C. En PDF
```
- Listar nombre de archivos adjuntos
- O incluir URLs/referencias
```

---

## <a id="calculos"></a>10. CÁLCULOS AUTOMÁTICOS IMPLEMENTADOS

### 10.1. Cálculos en Formulario

#### A. Cantidad en Unidades Base
```typescript
const quantityInBaseUnits = quantity × baseQuantityPerUnit

Ejemplo:
- Usuario ingresa: 50 Bultos
- baseQuantityPerUnit (de Bulto) = 50 kg
- Resultado: 50 × 50 = 2500 kg
```

#### B. Subtotal de Item
```typescript
const itemSubtotal = quantity × unitPrice

Ejemplo:
- Cantidad: 50
- Precio unitario: $2500
- Resultado: 50 × 2500 = $125,000
```

### 10.2. Cálculos en Creación de Orden

#### A. Subtotal de Orden
```typescript
const subtotal = items.reduce((sum, item) => sum + item.subtotal, 0)

Proceso:
1. Para cada item: cantidad × precioUnitario
2. Suma todos los subtotales
```

#### B. IVA (Impuesto al Valor Agregado)
```typescript
const tax = subtotal × 0.19

Porcentaje: 19% (fijo)
Cálculo: subtotal × 0.19
```

#### C. Total
```typescript
const total = subtotal + tax

Ejemplo:
- Subtotal: $1,000,000
- IVA: $190,000 (19%)
- Total: $1,190,000
```

### 10.3. Visualización de Cálculos

#### En Modal/Drawer de Detalles:
```
Subtotal:    $1,000,000
IVA (19%):   $190,000
─────────────────────────
Total:       $1,190,000    (en verde, mayor tamaño)
```

#### En Tabla Expandida:
Muestra subtotal, IVA y total en la fila expandible

#### En PDF:
Tabla de totales en la esquina derecha con:
- Fondo gris claro para subtotal e IVA
- Fondo verde oscuro (#2E7D32) para total

### 10.4. Validaciones Numéricas

```
Cantidad (InputNumber):
- min={1}
- No permite negativos
- Acepta decimales

Precio Unitario (InputNumber):
- min={0}
- No permite negativos
- Acepta decimales

Cantidad en unidades base:
- Calculated field (no editable)
- quantity × baseQuantityPerUnit
```

---

## <a id="apis-faltantes"></a>11. APIs QUE PODRÍAN ESTAR FALTANDO EN EL BACKEND

### 11.1. APIs Críticas Necesarias

#### 1. CRUD Básico de Compras
```
POST   /api/purchases                    ✓ Identificada
GET    /api/purchases                    ✓ Identificada
GET    /api/purchases/{id}               ✓ Identificada
PUT    /api/purchases/{id}               ✗ FALTANTE
DELETE /api/purchases/{id}               ✗ FALTANTE
```

#### 2. Gestión de Estado
```
PUT    /api/purchases/{id}/status        ✓ Identificada
PATCH  /api/purchases/{id}/mark-received ✗ VARIANTE
```

#### 3. Documentación
```
GET    /api/purchases/{id}/pdf           ✗ FALTANTE
POST   /api/purchases/{id}/send-email    ✗ FALTANTE
```

### 11.2. APIs de Datos Relacionados

#### Proveedores
```
GET    /api/suppliers                    ✓ Identificada
GET    /api/suppliers/{id}               ✓ Identificada
POST   /api/suppliers                    ✗ FALTANTE (pero no necesaria aquí)
```

#### Productos
```
GET    /api/products                     ✓ Identificada
GET    /api/products/{id}                ✓ Identificada
GET    /api/products/{id}/packaging-units ✓ Identificada
```

#### Ubicaciones
```
GET    /api/locations                    ✓ Identificada
GET    /api/locations/{id}               ✓ Identificada
```

### 11.3. APIs Avanzadas Sugeridas

#### A. Adjuntos
```
POST   /api/purchases/{id}/attachments   ✗ FALTANTE
DELETE /api/purchases/{id}/attachments/{attachmentId} ✗ FALTANTE
```

#### B. Historial/Auditoría
```
GET    /api/purchases/{id}/history       ✗ FALTANTE
GET    /api/purchases/{id}/status-changes ✗ FALTANTE
```

#### C. Reportes
```
GET    /api/purchases/reports/summary    ✗ FALTANTE
GET    /api/purchases/reports/by-supplier ✗ FALTANTE
GET    /api/purchases/reports/by-status ✗ FALTANTE
```

#### D. Validaciones
```
GET    /api/suppliers/{id}/validate      ✗ FALTANTE
GET    /api/products/{id}/validate       ✗ FALTANTE
```

### 11.4. Parámetros Query Recomendados

```
GET /api/purchases?
  - status=ordered,in_transit
  - supplierId=SUP-001
  - dateFrom=2025-01-01
  - dateTo=2025-11-30
  - search=PUR-2025
  - sortBy=createdAt
  - sortOrder=desc
  - page=1
  - limit=10
  - includeSupplier=true
  - includeItems=true
```

---

## 12. ESTRUCTURA DE DATOS ESPERADA DEL BACKEND

### 12.1. Tablas Base Necesarias en BD

```sql
-- Tabla de Compras
CREATE TABLE purchases (
  id UUID PRIMARY KEY,
  order_number VARCHAR(50) UNIQUE NOT NULL,
  supplier_id UUID NOT NULL FOREIGN KEY,
  destination_location_id UUID NOT NULL FOREIGN KEY,
  purchase_date TIMESTAMP NOT NULL,
  expected_delivery TIMESTAMP NULL,
  status ENUM('ordered','in_transit','received','cancelled') DEFAULT 'ordered',
  subtotal DECIMAL(10,2) NOT NULL,
  tax DECIMAL(10,2) NOT NULL,
  total DECIMAL(10,2) NOT NULL,
  observations TEXT NULL,
  created_by UUID NOT NULL FOREIGN KEY,
  received_by UUID NULL FOREIGN KEY,
  received_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de Items de Compra
CREATE TABLE purchase_items (
  id UUID PRIMARY KEY,
  purchase_id UUID NOT NULL FOREIGN KEY,
  product_id UUID NOT NULL FOREIGN KEY,
  brand_id UUID NOT NULL FOREIGN KEY,
  quantity DECIMAL(10,2) NOT NULL,
  quantity_in_base_units DECIMAL(10,2) NOT NULL,
  unit VARCHAR(50) NOT NULL,
  packaging_unit_id UUID FOREIGN KEY,
  unit_price DECIMAL(10,2) NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  expiration_date DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de Adjuntos
CREATE TABLE purchase_attachments (
  id UUID PRIMARY KEY,
  purchase_id UUID NOT NULL FOREIGN KEY,
  file_path VARCHAR(255) NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_type VARCHAR(50),
  file_size INT,
  uploaded_by UUID NOT NULL FOREIGN KEY,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 13. RESUMEN DE HALLAZGOS

### 13.1. Fortalezas

✓ Componente funcional bien estructurado  
✓ Sistema de empaque completo e implementado  
✓ Generación de PDF profesional  
✓ UI responsiva (móvil/escritorio)  
✓ Validaciones a nivel de formulario  
✓ Estados y transiciones claras  
✓ Cálculos automáticos correctos (IVA 19%)  
✓ Interfaz intuitiva con iconografía  

### 13.2. Debilidades/Pendientes

✗ No consume APIs reales del backend  
✗ Datos hardcodeados en mock  
✗ Funcionalidad de adjuntos no implementada  
✗ Sin notificaciones de cambios  
✗ Sin historial/auditoría  
✗ Sin paginación real  
✗ Sin búsqueda avanzada  
✗ Sin reportes  

### 13.3. Recomendaciones Inmediatas

1. **Conectar a APIs backend** - Implementar servicio HTTP real
2. **Implementar adjuntos** - Agregar componente Upload
3. **Agregar recepción de compras** - Integración con módulo Reception
4. **Implementar historial** - Auditoría de cambios
5. **Mejorar búsqueda** - Filtros avanzados
6. **Agregar paginación** - Para grandes volúmenes

---

## 14. ENDPOINTS RESUMIDOS POR PRIORIDAD

### Prioridad 1 (Esencial)
```
POST   /api/purchases
GET    /api/purchases
GET    /api/purchases/{id}
PUT    /api/purchases/{id}/status
GET    /api/suppliers
GET    /api/products
GET    /api/locations
GET    /api/products/{id}/packaging-units
```

### Prioridad 2 (Alta)
```
PUT    /api/purchases/{id}
DELETE /api/purchases/{id}
GET    /api/purchases/{id}/pdf
```

### Prioridad 3 (Media)
```
POST   /api/purchases/{id}/attachments
GET    /api/purchases/reports/summary
```

---

**Fin del Análisis**  
Generado: 2025-11-17  
Analista: Sistema de Análisis AgriFlor
