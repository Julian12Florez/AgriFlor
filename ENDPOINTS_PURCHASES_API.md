# ESPECIFICACIÓN TÉCNICA: ENDPOINTS API PARA MÓDULO DE COMPRAS

**Documento:** Especificación de endpoints REST para módulo Purchases  
**Fecha:** 2025-11-17  
**Versión API:** v1  
**Base URL:** `/api/v1`

---

## TABLA DE CONTENIDOS
1. [Endpoints de Compras](#compras)
2. [Endpoints de Datos Relacionados](#datos-relacionados)
3. [Estructura de Respuestas](#respuestas)
4. [Códigos de Estado HTTP](#codigos)
5. [Validaciones](#validaciones)
6. [Ejemplos Completos](#ejemplos)

---

## <a id="compras"></a>1. ENDPOINTS DE COMPRAS

### 1.1. Listar Compras

**Endpoint:** `GET /api/v1/purchases`

**Descripción:** Obtiene lista de órdenes de compra con filtros opcionales

**Query Parameters:**
```
status          string (opcional)   - 'ordered' | 'in_transit' | 'received' | 'cancelled'
search          string (opcional)   - Búsqueda en número de orden o nombre proveedor
supplierId      string (opcional)   - Filtrar por proveedor específico
dateFrom        date (opcional)     - Formato: YYYY-MM-DD
dateTo          date (opcional)     - Formato: YYYY-MM-DD
sortBy          string (opcional)   - Defecto: 'createdAt'
sortOrder       string (opcional)   - 'asc' | 'desc' (Defecto: 'desc')
page            number (opcional)   - Defecto: 1
limit           number (opcional)   - Defecto: 10 (Máximo: 100)
includeSupplier boolean (opcional)  - Defecto: false
includeItems    boolean (opcional)  - Defecto: true
```

**Ejemplo de Llamada:**
```bash
GET /api/v1/purchases?status=ordered&page=1&limit=20&sortBy=createdAt&sortOrder=desc
```

**Response - 200 OK:**
```json
{
  "success": true,
  "message": "Órdenes de compra obtenidas exitosamente",
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "orderNumber": "PUR-2025-001",
      "supplierId": "supplier-123",
      "supplierName": "Distribuidora El Campo S.A.S.",
      "supplier": {
        "id": "supplier-123",
        "name": "Distribuidora El Campo S.A.S.",
        "nit": "900.123.456-1",
        "address": "Calle 45 #67-89",
        "city": "Bogotá",
        "department": "Cundinamarca",
        "phone": "+57 1 3456789",
        "email": "ventas@elcampo.com",
        "contactPerson": "Juan Pérez",
        "contactPhone": "+57 300 1234567",
        "paymentTerms": "30 días calendario",
        "status": "active"
      },
      "destinationLocationId": "location-2",
      "destinationLocationName": "Bodega Central",
      "purchaseDate": "2025-11-17T00:00:00Z",
      "expectedDelivery": "2025-11-24T00:00:00Z",
      "status": "ordered",
      "items": [
        {
          "id": "item-1",
          "productId": "prod-1",
          "productName": "Fertilizante NPK 15-15-15",
          "brandId": "brand-1",
          "brandName": "Yara",
          "quantity": 50,
          "quantityInBaseUnits": 2500,
          "unit": "kg",
          "packagingUnitId": "pu-1",
          "packagingUnitName": "Bulto",
          "baseQuantityPerUnit": 50,
          "unitPrice": 2500,
          "subtotal": 125000,
          "expirationDate": null
        }
      ],
      "subtotal": 1000000,
      "tax": 190000,
      "total": 1190000,
      "observations": "Entrega en Bodega Central",
      "attachments": [],
      "createdBy": "user-admin",
      "receivedBy": null,
      "receivedAt": null,
      "createdAt": "2025-11-17T10:30:00Z",
      "updatedAt": "2025-11-17T10:30:00Z"
    }
  ],
  "pagination": {
    "total": 45,
    "page": 1,
    "limit": 20,
    "totalPages": 3
  }
}
```

**Response - 400 Bad Request:**
```json
{
  "success": false,
  "message": "Parámetros inválidos",
  "errors": {
    "page": "Debe ser un número positivo",
    "limit": "Máximo 100 registros permitidos"
  }
}
```

---

### 1.2. Obtener Detalle de Compra

**Endpoint:** `GET /api/v1/purchases/{id}`

**Descripción:** Obtiene información completa de una orden específica

**Path Parameters:**
```
id (UUID) - ID de la orden de compra
```

**Ejemplo:**
```bash
GET /api/v1/purchases/550e8400-e29b-41d4-a716-446655440000
```

**Response - 200 OK:** (Mismo objeto Purchase que en listar)

**Response - 404 Not Found:**
```json
{
  "success": false,
  "message": "Orden de compra no encontrada",
  "error": "La orden con ID 550e8400-e29b-41d4-a716-446655440000 no existe"
}
```

---

### 1.3. Crear Nueva Compra

**Endpoint:** `POST /api/v1/purchases`

**Descripción:** Crea una nueva orden de compra

**Request Body:**
```json
{
  "supplierId": "supplier-123",
  "destinationLocationId": "location-2",
  "purchaseDate": "2025-11-17",
  "expectedDelivery": "2025-11-24",
  "observations": "Entrega en Bodega Central. Verificar fechas vencimiento.",
  "items": [
    {
      "productId": "prod-1",
      "quantity": 50,
      "packagingUnitId": "pu-1",
      "unitPrice": 2500
    },
    {
      "productId": "prod-5",
      "quantity": 100,
      "packagingUnitId": "pu-5",
      "unitPrice": 1800
    }
  ]
}
```

**Validaciones:**
- `supplierId`: Requerido, debe existir en BD
- `destinationLocationId`: Requerido, debe ser ubicación activa
- `purchaseDate`: Requerido, formato ISO 8601
- `expectedDelivery`: Opcional, debe ser >= purchaseDate
- `items`: Requerido, mínimo 1 item
- `items[].productId`: Requerido, debe existir
- `items[].quantity`: Requerido, > 0
- `items[].packagingUnitId`: Requerido, debe existir para producto
- `items[].unitPrice`: Requerido, >= 0

**Response - 201 Created:**
```json
{
  "success": true,
  "message": "Orden de compra creada exitosamente",
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440001",
    "orderNumber": "PUR-2025-002",
    "supplierId": "supplier-123",
    "supplierName": "Distribuidora El Campo S.A.S.",
    "destinationLocationId": "location-2",
    "destinationLocationName": "Bodega Central",
    "purchaseDate": "2025-11-17",
    "expectedDelivery": "2025-11-24",
    "status": "ordered",
    "items": [
      {
        "id": "item-new-1",
        "productId": "prod-1",
        "productName": "Fertilizante NPK 15-15-15",
        "brandId": "brand-1",
        "brandName": "Yara",
        "quantity": 50,
        "quantityInBaseUnits": 2500,
        "unit": "kg",
        "packagingUnitId": "pu-1",
        "packagingUnitName": "Bulto",
        "baseQuantityPerUnit": 50,
        "unitPrice": 2500,
        "subtotal": 125000
      }
    ],
    "subtotal": 225000,
    "tax": 42750,
    "total": 267750,
    "observations": "Entrega en Bodega Central",
    "attachments": [],
    "createdBy": "user-id",
    "createdAt": "2025-11-17T14:30:00Z",
    "updatedAt": "2025-11-17T14:30:00Z"
  }
}
```

**Response - 400 Bad Request:**
```json
{
  "success": false,
  "message": "Datos inválidos",
  "errors": {
    "supplierId": "El proveedor es requerido",
    "items.0.quantity": "La cantidad debe ser mayor a 0",
    "expectedDelivery": "La fecha de entrega no puede ser anterior a la fecha de compra"
  }
}
```

**Response - 422 Unprocessable Entity:**
```json
{
  "success": false,
  "message": "Validación fallida",
  "errors": {
    "supplierId": "El proveedor especificado no existe",
    "destinationLocationId": "La ubicación no está activa o no existe"
  }
}
```

---

### 1.4. Actualizar Estado de Compra

**Endpoint:** `PUT /api/v1/purchases/{id}/status`

**Descripción:** Cambia el estado de una orden de compra

**Path Parameters:**
```
id (UUID) - ID de la orden
```

**Request Body:**
```json
{
  "status": "in_transit",
  "notes": "Nota opcional sobre el cambio" 
}
```

**Estados Válidos y Transiciones:**
```
ordered     → in_transit, cancelled
in_transit  → received, cancelled
received    → (sin transiciones adicionales)
cancelled   → (estado terminal)
```

**Response - 200 OK:**
```json
{
  "success": true,
  "message": "Estado de la compra actualizado a: En Tránsito",
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "orderNumber": "PUR-2025-001",
    "status": "in_transit",
    "updatedAt": "2025-11-17T15:00:00Z"
  }
}
```

**Response - 400 Bad Request:**
```json
{
  "success": false,
  "message": "Transición de estado inválida",
  "error": "No se puede pasar de 'cancelled' a 'in_transit'"
}
```

**Response - 404 Not Found:**
```json
{
  "success": false,
  "message": "Orden de compra no encontrada"
}
```

---

### 1.5. Actualizar Compra Completa

**Endpoint:** `PUT /api/v1/purchases/{id}`

**Descripción:** Actualiza información de una orden (solo si está en estado 'ordered')

**Path Parameters:**
```
id (UUID) - ID de la orden
```

**Request Body:**
```json
{
  "purchaseDate": "2025-11-18",
  "expectedDelivery": "2025-11-25",
  "observations": "Nueva observación",
  "items": [
    {
      "productId": "prod-1",
      "quantity": 60,
      "packagingUnitId": "pu-1",
      "unitPrice": 2500
    }
  ]
}
```

**Restricciones:**
- Solo permitido cuando status = 'ordered'
- `destinationLocationId` NO puede cambiar después de creada
- Recalcula automáticamente subtotal, tax, total

**Response - 200 OK:** (Objeto Purchase actualizado)

**Response - 403 Forbidden:**
```json
{
  "success": false,
  "message": "No se puede modificar esta compra",
  "error": "Solo se pueden editar órdenes en estado 'ordered'"
}
```

---

### 1.6. Eliminar Compra

**Endpoint:** `DELETE /api/v1/purchases/{id}`

**Descripción:** Elimina una orden de compra (solo si está en estado 'ordered')

**Path Parameters:**
```
id (UUID) - ID de la orden
```

**Response - 200 OK:**
```json
{
  "success": true,
  "message": "Orden de compra eliminada exitosamente"
}
```

**Response - 403 Forbidden:**
```json
{
  "success": false,
  "message": "No se puede eliminar esta compra",
  "error": "Solo se pueden eliminar órdenes en estado 'ordered'"
}
```

---

<a id="datos-relacionados"></a>## 2. ENDPOINTS DE DATOS RELACIONADOS

### 2.1. Obtener Proveedores

**Endpoint:** `GET /api/v1/suppliers`

**Query Parameters:**
```
status  string (opcional)  - 'active' (defecto) | 'all'
search  string (opcional)  - Búsqueda en nombre o NIT
limit   number (opcional)  - Defecto: 100
```

**Response - 200 OK:**
```json
{
  "success": true,
  "data": [
    {
      "id": "supplier-123",
      "name": "Distribuidora El Campo S.A.S.",
      "nit": "900.123.456-1",
      "address": "Calle 45 #67-89, Zona Industrial",
      "city": "Bogotá",
      "department": "Cundinamarca",
      "phone": "+57 1 3456789",
      "email": "ventas@elcampo.com",
      "contactPerson": "Ing. Roberto Martínez",
      "contactPhone": "+57 300 1234567",
      "paymentTerms": "30 días calendario",
      "status": "active"
    }
  ]
}
```

---

### 2.2. Obtener Productos

**Endpoint:** `GET /api/v1/products`

**Query Parameters:**
```
status   string (opcional)  - 'active' (defecto) | 'all'
category string (opcional)  - 'fertilizer' | 'insecticide' | 'herbicide' | 'fungicide'
search   string (opcional)  - Búsqueda en nombre
limit    number (opcional)  - Defecto: 100
```

**Response - 200 OK:**
```json
{
  "success": true,
  "data": [
    {
      "id": "prod-1",
      "name": "Fertilizante NPK 15-15-15",
      "brand": "Yara",
      "brandId": "brand-1",
      "category": "fertilizer",
      "type": "fertilizer",
      "unit": "kg",
      "packagingUnits": [
        {
          "id": "pu-1",
          "name": "Bulto",
          "baseQuantity": 50,
          "baseUnit": "kg"
        },
        {
          "id": "pu-2",
          "name": "Media Unidad",
          "baseQuantity": 25,
          "baseUnit": "kg"
        }
      ],
      "stock": 200,
      "minStock": 50,
      "activeIngredient": "Nitrógeno 15%, Fósforo 15%, Potasio 15%",
      "price": 2500,
      "status": "active"
    }
  ]
}
```

---

### 2.3. Obtener Ubicaciones

**Endpoint:** `GET /api/v1/locations`

**Query Parameters:**
```
status string (opcional)  - 'active' (defecto) | 'all'
type   string (opcional)  - 'warehouse' | 'farm'
limit  number (opcional)  - Defecto: 100
```

**Response - 200 OK:**
```json
{
  "success": true,
  "data": [
    {
      "id": "location-2",
      "name": "Bodega Central",
      "type": "warehouse",
      "municipality": "Bogotá",
      "address": "Zona Industrial Puente Aranda",
      "coordinates": {
        "lat": 4.6261,
        "lng": -74.1151
      },
      "responsible": "María González",
      "status": "active"
    }
  ]
}
```

---

### 2.4. Obtener Unidades de Empaque de Producto

**Endpoint:** `GET /api/v1/products/{productId}/packaging-units`

**Path Parameters:**
```
productId (UUID) - ID del producto
```

**Response - 200 OK:**
```json
{
  "success": true,
  "data": [
    {
      "id": "pu-1",
      "name": "Bulto",
      "baseQuantity": 50,
      "baseUnit": "kg"
    },
    {
      "id": "pu-2",
      "name": "Media Unidad",
      "baseQuantity": 25,
      "baseUnit": "kg"
    }
  ]
}
```

---

<a id="respuestas"></a>## 3. ESTRUCTURA ESTÁNDAR DE RESPUESTAS

### Respuesta Exitosa
```json
{
  "success": true,
  "message": "Descripción de la acción completada",
  "data": {},
  "pagination": {}  // Opcional, solo en listados
}
```

### Respuesta con Error
```json
{
  "success": false,
  "message": "Descripción general del error",
  "error": "Detalles específicos",
  "errors": {}  // Opcional, para múltiples errores
}
```

---

<a id="codigos"></a>## 4. CÓDIGOS DE ESTADO HTTP

| Código | Significado | Caso de Uso |
|--------|-------------|-----------|
| 200 | OK | GET exitoso, PUT exitoso |
| 201 | Created | POST exitoso (crear recursos) |
| 400 | Bad Request | Parámetros inválidos, validación fallida |
| 401 | Unauthorized | No autenticado |
| 403 | Forbidden | No autorizado para la acción |
| 404 | Not Found | Recurso no existe |
| 409 | Conflict | Conflicto (ej: estado inválido) |
| 422 | Unprocessable Entity | Validación de lógica de negocio |
| 500 | Server Error | Error interno del servidor |

---

<a id="validaciones"></a>## 5. VALIDACIONES AUTOMÁTICAS

### En Creación de Compra
```
✓ Proveedor existe y está activo
✓ Ubicación existe y está activa
✓ Al menos 1 item
✓ Cada producto existe
✓ Cada packagingUnit existe para el producto
✓ Cantidad > 0
✓ Precio >= 0
✓ Fecha entrega >= fecha compra
```

### En Cambio de Estado
```
✓ Transición válida según diagrama
✓ Compra existe
✓ Usuario tiene permisos
```

### Cálculos Automáticos
```
✓ quantityInBaseUnits = quantity × baseQuantityPerUnit
✓ itemSubtotal = quantity × unitPrice
✓ purchaseSubtotal = suma(itemSubtotal)
✓ tax = purchaseSubtotal × 0.19
✓ total = purchaseSubtotal + tax
```

---

<a id="ejemplos"></a>## 6. EJEMPLOS COMPLETOS

### Ejemplo 1: Crear Compra Completa

```bash
curl -X POST http://localhost:8000/api/v1/purchases \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{
    "supplierId": "supplier-001",
    "destinationLocationId": "location-2",
    "purchaseDate": "2025-11-17",
    "expectedDelivery": "2025-11-24",
    "observations": "Entrega urgente",
    "items": [
      {
        "productId": "prod-1",
        "quantity": 50,
        "packagingUnitId": "pu-1",
        "unitPrice": 2500
      },
      {
        "productId": "prod-5",
        "quantity": 100,
        "packagingUnitId": "pu-5",
        "unitPrice": 1800
      }
    ]
  }'
```

### Ejemplo 2: Cambiar Estado a En Tránsito

```bash
curl -X PUT http://localhost:8000/api/v1/purchases/550e8400-e29b-41d4-a716-446655440000/status \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{
    "status": "in_transit",
    "notes": "Transportista: Juan García"
  }'
```

### Ejemplo 3: Listar Compras Filtradas

```bash
curl -X GET "http://localhost:8000/api/v1/purchases?status=in_transit&page=1&limit=10&sortBy=createdAt&sortOrder=desc" \
  -H "Authorization: Bearer TOKEN"
```

---

**Fin de Especificación**  
Revisión: v1.0  
Actualizado: 2025-11-17
