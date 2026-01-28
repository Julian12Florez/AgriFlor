# Sistema Completo de Movimientos de Inventario

## ✅ Implementación Completada

Se ha implementado un sistema COMPLETO de movimientos de inventario con todos los datos necesarios para informes y auditorías, respetando que **los movimientos reales se confirman en la RECEPCIÓN**.

---

## 🎯 Concepto Fundamental

### ANTES DE LA RECEPCIÓN:
- **Compra**: Orden registrada pero producto aún NO está en inventario
- **Salida**: Orden registrada pero producto aún NO ha salido físicamente del inventario

### EN LA RECEPCIÓN:
- **Es cuando se confirman los movimientos REALES**
- **Es cuando se actualizan las existencias físicas**
- **Es cuando se registra el cambio en la contabilidad**

---

## 📋 Flujo Completo Implementado

### 1. RECEPCIÓN DE COMPRA (Purchase)

```
┌─────────────────────────────────────────────────────────────────┐
│ COMPRA CREADA (estado: ordered)                                 │
│ - Orden registrada en sistema                                   │
│ - Producto AÚN NO está en inventario                           │
│ - NO hay movimientos de inventario                             │
└─────────────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│ RECEPCIÓN PARCIAL (estado: in_transit)                          │
│                                                                  │
│ ✅ Crea InventoryMovement tipo 'entry' en DESTINO              │
│    - type: entry                                                │
│    - location_id: Bodega Central (destino)                      │
│    - quantity: 500 kg (parcial)                                 │
│    - unit_price: $2,500.00 (de purchase_items)                 │
│    - total_price: $1,250,000.00                                │
│    - related_document_id: reception_id                          │
│    - responsible_user: user_id                                  │
│    - observations: "Recepción lote #1 - good - Compra"         │
│                                                                  │
│ ✅ Actualiza/Crea registro en tabla `inventory`                │
│    - product_id, brand_id, location_id, batch_number=GENERAL   │
│    - quantity: 0 → 500 kg                                       │
│    - unit_price: $2,500.00 (de compra)                         │
│    - total_value: $1,250,000.00                                │
│    - status: good/near_expiry/expired (calculado)              │
│                                                                  │
│ ✅ Stock REAL actualizado en Bodega Central                    │
└─────────────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│ RECEPCIÓN TOTAL (estado: received)                              │
│ - Mismo proceso para lote #2, #3, etc.                         │
│ - Suma acumulativa en inventario                               │
│ - Precio promedio ponderado si hay múltiples lotes             │
└─────────────────────────────────────────────────────────────────┘
```

**Movimientos generados:**
- ✅ **ENTRY** en ubicación destino (Bodega/Finca)
- ❌ **NO** genera salida (origen es proveedor externo)

---

### 2. RECEPCIÓN DE SALIDA (ProductOutput)

```
┌─────────────────────────────────────────────────────────────────┐
│ SALIDA CREADA (estado: pending)                                 │
│ - Orden registrada en sistema                                   │
│ - Producto AÚN NO ha salido físicamente                        │
│ - NO hay movimientos de inventario                             │
│ - Inventario en origen: 1,000 kg (sin cambios)                │
└─────────────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│ RECEPCIÓN PARCIAL (estado: partial)                             │
│                                                                  │
│ ✅ Crea InventoryMovement tipo 'exit' en ORIGEN                │
│    - type: exit                                                 │
│    - location_id: Bodega Central (origen)                       │
│    - quantity: 300 kg                                           │
│    - unit_price: $2,500.00 (del inventory en origen)           │
│    - total_price: $750,000.00                                  │
│    - related_document_id: reception_id                          │
│    - responsible_user: user_id                                  │
│    - observations: "Salida confirmada en recepción lote #1 -   │
│                     Transferencia a Finca La Esperanza"        │
│                                                                  │
│ ✅ Actualiza tabla `inventory` en ORIGEN                       │
│    - Bodega Central: 1,000 kg → 700 kg (-300 kg)              │
│    - unit_price: $2,500.00 (mantiene precio)                  │
│    - total_value: $1,750,000.00                                │
│                                                                  │
│ ✅ Crea InventoryMovement tipo 'entry' en DESTINO              │
│    - type: entry                                                │
│    - location_id: Finca La Esperanza (destino)                 │
│    - quantity: 300 kg                                           │
│    - unit_price: $2,500.00 (mismo del origen)                 │
│    - total_price: $750,000.00                                  │
│    - related_document_id: reception_id                          │
│    - responsible_user: user_id                                  │
│    - observations: "Recepción lote #1 - good - Transferencia" │
│                                                                  │
│ ✅ Actualiza/Crea tabla `inventory` en DESTINO                 │
│    - Finca La Esperanza: 0 kg → 300 kg (+300 kg)              │
│    - unit_price: $2,500.00 (del origen)                        │
│    - total_value: $750,000.00                                  │
│                                                                  │
│ ✅ Stock REAL actualizado en AMBAS ubicaciones                 │
└─────────────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│ RECEPCIÓN TOTAL (estado: completed)                             │
│ - Mismo proceso para lote #2, #3, etc.                         │
│ - Totales: Origen -1,000 kg, Destino +1,000 kg                │
└─────────────────────────────────────────────────────────────────┘
```

**Movimientos generados:**
- ✅ **EXIT** en ubicación origen (donde estaba el producto)
- ✅ **ENTRY** en ubicación destino (donde llega el producto)

---

## 🔍 Datos Completos para Auditoría

### Tabla `inventory_movements`

Cada movimiento registra:

| Campo | Descripción | Ejemplo |
|-------|-------------|---------|
| `id` | UUID único | `uuid` |
| `type` | Tipo de movimiento | `entry`, `exit`, `transfer`, `application` |
| `product_id` | Producto | `uuid` |
| `brand_id` | Marca | `uuid` |
| `location_id` | Ubicación donde ocurre | `uuid` |
| `quantity` | Cantidad (siempre positiva) | `300.00` |
| `unit` | Unidad de medida | `kg`, `litros`, `unidades` |
| `expiration_date` | Fecha de vencimiento | `2025-12-31` o `null` |
| `unit_price` | ✅ **Precio unitario** | `2500.00` |
| `total_price` | ✅ **Valor total** | `750000.00` |
| `responsible_user` | Usuario responsable | `uuid` |
| `related_document_id` | Documento origen | `reception_id` |
| `related_document_type` | Tipo de documento | `App\Models\Reception` |
| `observations` | ✅ **Observaciones detalladas** | Ver ejemplos abajo |
| `created_at` | Fecha/hora exacta | `2024-01-15 10:30:00` |

---

### Tabla `inventory`

Stock actual por producto/marca/ubicación:

| Campo | Descripción | Actualización |
|-------|-------------|---------------|
| `product_id` | Producto | - |
| `brand_id` | Marca | - |
| `location_id` | Ubicación | - |
| `batch_number` | Lote (usa 'GENERAL' para agregado) | - |
| `quantity` | ✅ **Stock actual** | ✅ Actualizado automáticamente |
| `unit` | Unidad | - |
| `expiration_date` | Vencimiento más cercano | Actualizado si aplica |
| `unit_price` | ✅ **Precio promedio ponderado** | ✅ Calculado automáticamente |
| `total_value` | ✅ **Valor total** | ✅ quantity × unit_price |
| `status` | Estado | ✅ Calculado: good/low/near_expiry/expired |

---

## 💰 Cálculo de Precios

### Para COMPRAS (Purchase):

```php
// Obtiene precio de purchase_items
$unitPrice = $purchaseItem->unit_price; // Ej: $2,500.00
```

**Origen:** Tabla `purchase_items` → campo `unit_price`

---

### Para SALIDAS (Output):

```php
// 1. Intenta obtener de inventory en ubicación origen
$inventory = Inventory::where('product_id', $productId)
    ->where('brand_id', $brandId)
    ->where('location_id', $originLocationId)
    ->first();

$unitPrice = $inventory->unit_price; // Ej: $2,500.00

// 2. Si no existe en origen, usa promedio de otras ubicaciones
if (!$inventory) {
    $unitPrice = Inventory::where('product_id', $productId)
        ->where('brand_id', $brandId)
        ->where('quantity', '>', 0)
        ->avg('unit_price');
}
```

**Origen:** Tabla `inventory` → campo `unit_price` (promedio ponderado histórico)

---

### Precio Promedio Ponderado

Cuando se recibe un nuevo lote con diferente precio:

```php
// Ejemplo:
// Stock actual: 1,000 kg @ $2,500/kg = $2,500,000
// Nueva entrada: 500 kg @ $2,600/kg = $1,300,000
//
// Cálculo:
$totalValue = (1000 * 2500) + (500 * 2600) = $3,800,000
$totalQuantity = 1000 + 500 = 1,500 kg
$newUnitPrice = 3,800,000 / 1,500 = $2,533.33/kg

// Resultado en inventory:
// quantity: 1,500 kg
// unit_price: $2,533.33
// total_value: $3,800,000.00
```

**Método PEPS implícito**: Las salidas usan el precio del inventario existente en origen.

---

## 📝 Observaciones Detalladas

### Recepción de Compra - ENTRY:
```
"Recepción lote #1 - good - Compra"
"Recepción lote #2 - damaged - Compra"
"Recepción lote #3 - good - Compra"
```

### Recepción de Salida - EXIT (origen):
```
"Salida confirmada en recepción lote #1 - Transferencia a Finca La Esperanza"
"Salida confirmada en recepción lote #2 - Transferencia a Bodega Norte"
```

### Recepción de Salida - ENTRY (destino):
```
"Recepción lote #1 - good - Transferencia"
"Recepción lote #2 - damaged - Transferencia"
```

---

## 🔒 Protección con Transacciones

### createReceptionWithBatch()
```php
try {
    DB::beginTransaction();

    // 1. Crear/obtener Reception
    // 2. Crear ReceptionBatch
    // 3. Crear ReceptionBatchItems
    // 4. Actualizar ReceptionItems (cantidades)
    // 5. ✅ processInventoryMovements()
    //    ├─ Crear InventoryMovement (exit si output)
    //    ├─ Crear InventoryMovement (entry)
    //    ├─ Actualizar/Crear Inventory (origen si output)
    //    └─ Actualizar/Crear Inventory (destino)
    // 6. Actualizar estado de Reception
    // 7. Actualizar estado de Purchase/Output

    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    // Nada se guarda si algo falla
}
```

**Garantía**: Si cualquier paso falla (ej: error en updateInventoryStock), TODO el proceso se revierte. Los datos permanecen consistentes.

---

### addBatch()
```php
try {
    DB::beginTransaction();

    // 1. Obtener Reception existente
    // 2. Crear nuevo ReceptionBatch
    // 3. Crear ReceptionBatchItems
    // 4. Actualizar ReceptionItems (cantidades)
    // 5. ✅ processInventoryMovements()
    //    ├─ Crear InventoryMovement (exit si output)
    //    ├─ Crear InventoryMovement (entry)
    //    ├─ Actualizar Inventory (origen si output)
    //    └─ Actualizar Inventory (destino)
    // 6. Adjuntar archivos
    // 7. Actualizar estado de Reception

    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    // Nada se guarda si algo falla
}
```

---

## 🎯 Métodos Implementados

### `processInventoryMovements()`
```php
private function processInventoryMovements(
    Reception $reception,
    array $itemData,
    $receptionItem,
    int $batchNumber,
    string $userId
): void
```

**Responsabilidad:** Orquestar creación de movimientos según tipo (purchase vs output)

**Decisión lógica:**
- Si `source_type === 'purchase'` → Solo ENTRY en destino
- Si `source_type === 'output'` → EXIT en origen + ENTRY en destino

---

### `createEntryMovement()`
```php
private function createEntryMovement(
    Reception $reception,
    string $productId,
    string $brandId,
    float $quantity,
    string $unit,
    ?string $expirationDate,
    float $unitPrice,
    string $userId,
    int $batchNumber,
    string $condition
): void
```

**Responsabilidad:**
1. Crear registro en `inventory_movements` tipo 'entry'
2. Llamar a `updateInventoryStock()` con cantidad positiva

---

### `createExitMovement()`
```php
private function createExitMovement(
    Reception $reception,
    string $productId,
    string $brandId,
    float $quantity,
    string $unit,
    float $unitPrice,
    string $userId,
    int $batchNumber
): void
```

**Responsabilidad:**
1. Crear registro en `inventory_movements` tipo 'exit'
2. Llamar a `updateInventoryStock()` con cantidad negativa

---

### `updateInventoryStock()`
```php
private function updateInventoryStock(
    string $productId,
    string $brandId,
    string $locationId,
    float $quantityChange, // +300 para entry, -300 para exit
    string $unit,
    ?string $expirationDate,
    float $unitPrice
): void
```

**Responsabilidad:**
1. Buscar registro existente en `inventory` (batch_number='GENERAL')
2. Si existe:
   - Calcular nueva cantidad (actual + change)
   - Calcular precio promedio ponderado (si es entrada)
   - Actualizar quantity, unit_price, total_value, status
3. Si NO existe (solo para entradas):
   - Crear nuevo registro con cantidad inicial
   - Asignar precio de compra/origen
   - Calcular total_value
   - Calcular status inicial

**Protección:** `max(0, $newQuantity)` - nunca permite stock negativo

---

### `getUnitPriceForMovement()`
```php
private function getUnitPriceForMovement(
    Reception $reception,
    string $productId,
    string $brandId
): float
```

**Responsabilidad:**
1. Si es compra → obtener de `purchase_items.unit_price`
2. Si es salida → obtener de `inventory.unit_price` en ubicación origen
3. Fallback → usar promedio de otras ubicaciones
4. Último fallback → retornar 0.0 (con log de advertencia)

---

### `calculateInventoryStatus()`
```php
private function calculateInventoryStatus(
    float $quantity,
    ?string $expirationDate
): string
```

**Responsabilidad:** Calcular estado según vencimiento

**Lógica:**
- Si días hasta vencimiento < 0 → 'expired'
- Si días hasta vencimiento ≤ 30 → 'near_expiry'
- Si cantidad = 0 → 'good' (se filtrará en consultas)
- Otro caso → 'good'

---

## 📊 Ejemplo Completo: Compra + Recepción

### 1. Se crea Compra
```sql
-- Table: purchases
INSERT INTO purchases VALUES (
  'uuid-comp-001',
  'COMP-2024-001',
  'supplier-uuid',
  'bodega-central-uuid',
  NULL, -- origin_location_id
  '2024-01-10',
  'ordered', -- status
  1250000.00, -- subtotal
  237500.00, -- tax
  1487500.00, -- total
  'admin-uuid'
);

-- Table: purchase_items
INSERT INTO purchase_items VALUES (
  'uuid-item-001',
  'uuid-comp-001',
  'product-fertilizante-uuid',
  'brand-yara-uuid',
  NULL, -- packaging_unit_id
  500.00, -- quantity
  500.00, -- quantity_in_base_units
  2500.00, -- unit_price ✅
  1250000.00, -- subtotal
  '2025-12-31' -- expiration_date
);
```

**Estado inventario:** NO cambia (compra solo es orden)

---

### 2. Se recepciona parcialmente (300 kg de 500 kg)

```sql
-- Table: receptions
INSERT INTO receptions VALUES (
  'uuid-rec-001',
  'REC-2024-000001',
  'uuid-comp-001', -- source_id
  'purchase', -- source_type
  NULL, -- origin_location_id (proveedor externo)
  'bodega-central-uuid', -- destination_location_id
  '2024-01-10',
  'pending',
  500.00, -- total_expected
  300.00, -- total_received
  60.00, -- completion_percentage
  'admin-uuid',
  NULL
);

-- Table: reception_batches
INSERT INTO reception_batches VALUES (
  'uuid-batch-001',
  'uuid-rec-001',
  1, -- batch_number
  '2024-01-15',
  'user-warehouse-uuid',
  'Recepción parcial'
);

-- Table: reception_batch_items
INSERT INTO reception_batch_items VALUES (
  'uuid-batch-item-001',
  'uuid-batch-001',
  'product-fertilizante-uuid',
  300.00, -- quantity_received
  'good', -- condition ✅ (solo good genera movimiento)
  '2025-12-31',
  NULL
);

-- ✅ MOVIMIENTO CREADO AUTOMÁTICAMENTE
-- Table: inventory_movements
INSERT INTO inventory_movements VALUES (
  'uuid-movement-001',
  'entry', -- type ✅
  'product-fertilizante-uuid',
  'brand-yara-uuid',
  'bodega-central-uuid', -- location_id (DESTINO)
  300.00, -- quantity
  'kg',
  '2025-12-31',
  2500.00, -- unit_price ✅ (de purchase_item)
  750000.00, -- total_price ✅ (300 × 2500)
  'user-warehouse-uuid',
  'uuid-rec-001', -- related_document_id
  'App\\Models\\Reception', -- related_document_type
  'Recepción lote #1 - good - Compra',
  '2024-01-15 10:30:00'
);

-- ✅ INVENTARIO ACTUALIZADO AUTOMÁTICAMENTE
-- Table: inventory
INSERT INTO inventory VALUES (
  'uuid-inv-001',
  'product-fertilizante-uuid',
  'brand-yara-uuid',
  'bodega-central-uuid',
  'GENERAL', -- batch_number
  300.00, -- quantity ✅
  'kg',
  '2025-12-31',
  2500.00, -- unit_price ✅
  750000.00, -- total_value ✅
  'good', -- status
  '2024-01-15 10:30:00',
  '2024-01-15 10:30:00'
);
```

**Kardex en Bodega Central:**
```
Fecha        | Tipo    | Entrada | Salida | Saldo  | Precio U. | Valor Total
-------------|---------|---------|--------|--------|-----------|-------------
2024-01-15   | Entrada | +300 kg |   -    | 300 kg | $2,500.00 | $750,000.00
```

---

### 3. Se recepciona el resto (200 kg restantes)

```sql
-- ✅ SEGUNDO MOVIMIENTO
INSERT INTO inventory_movements VALUES (
  'uuid-movement-002',
  'entry',
  'product-fertilizante-uuid',
  'brand-yara-uuid',
  'bodega-central-uuid',
  200.00, -- quantity
  'kg',
  '2025-12-31',
  2500.00, -- unit_price ✅
  500000.00, -- total_price ✅ (200 × 2500)
  'user-warehouse-uuid',
  'uuid-rec-001',
  'App\\Models\\Reception',
  'Recepción lote #2 - good - Compra',
  '2024-01-20 14:15:00'
);

-- ✅ INVENTARIO ACTUALIZADO (suma)
UPDATE inventory SET
  quantity = 500.00, -- 300 + 200
  unit_price = 2500.00, -- mismo precio
  total_value = 1250000.00, -- 500 × 2500
  updated_at = '2024-01-20 14:15:00'
WHERE id = 'uuid-inv-001';
```

**Kardex en Bodega Central:**
```
Fecha        | Tipo    | Entrada | Salida | Saldo  | Precio U. | Valor Total
-------------|---------|---------|--------|--------|-----------|-------------
2024-01-15   | Entrada | +300 kg |   -    | 300 kg | $2,500.00 | $750,000.00
2024-01-20   | Entrada | +200 kg |   -    | 500 kg | $2,500.00 | $1,250,000.00
```

---

## 📊 Ejemplo Completo: Salida + Recepción

### 1. Se crea Salida (transferencia)

```sql
-- Table: product_outputs
INSERT INTO product_outputs VALUES (
  'uuid-output-001',
  'OUT-2024-001',
  'bodega-central-uuid', -- origin_location_id
  'finca-esperanza-uuid', -- destination_location_id
  '2024-01-25',
  'pending', -- status
  'Transferencia a finca',
  'admin-uuid'
);

-- Table: output_products
INSERT INTO output_products VALUES (
  'uuid-output-prod-001',
  'uuid-output-001',
  'product-fertilizante-uuid',
  'brand-yara-uuid',
  200.00, -- quantity_requested
  0.00, -- quantity_delivered (aún no)
  'kg',
  'LOTE-2024-001',
  '2025-12-31'
);
```

**Estado inventario:**
- Bodega Central: 500 kg (SIN CAMBIOS - salida aún no confirmada)
- Finca Esperanza: 0 kg

---

### 2. Se recepciona la salida (200 kg)

```sql
-- Table: receptions
INSERT INTO receptions VALUES (
  'uuid-rec-002',
  'REC-2024-000002',
  'uuid-output-001', -- source_id
  'output', -- source_type ✅
  'bodega-central-uuid', -- origin_location_id ✅
  'finca-esperanza-uuid', -- destination_location_id ✅
  '2024-01-25',
  'completed',
  200.00,
  200.00,
  100.00,
  'admin-uuid',
  NULL
);

-- ✅ MOVIMIENTO EXIT EN ORIGEN (Bodega Central)
INSERT INTO inventory_movements VALUES (
  'uuid-movement-003',
  'exit', -- type ✅
  'product-fertilizante-uuid',
  'brand-yara-uuid',
  'bodega-central-uuid', -- location_id (ORIGEN) ✅
  200.00,
  'kg',
  NULL, -- no expiration en salidas
  2500.00, -- unit_price (del inventory origen)
  500000.00, -- total_price (200 × 2500)
  'user-warehouse-uuid',
  'uuid-rec-002',
  'App\\Models\\Reception',
  'Salida confirmada en recepción lote #1 - Transferencia a Finca La Esperanza',
  '2024-01-25 09:00:00'
);

-- ✅ INVENTARIO ORIGEN ACTUALIZADO (resta)
UPDATE inventory SET
  quantity = 300.00, -- 500 - 200 ✅
  unit_price = 2500.00, -- mantiene precio
  total_value = 750000.00, -- 300 × 2500
  updated_at = '2024-01-25 09:00:00'
WHERE id = 'uuid-inv-001'; -- Bodega Central

-- ✅ MOVIMIENTO ENTRY EN DESTINO (Finca Esperanza)
INSERT INTO inventory_movements VALUES (
  'uuid-movement-004',
  'entry', -- type ✅
  'product-fertilizante-uuid',
  'brand-yara-uuid',
  'finca-esperanza-uuid', -- location_id (DESTINO) ✅
  200.00,
  'kg',
  '2025-12-31',
  2500.00, -- unit_price (del origen)
  500000.00, -- total_price (200 × 2500)
  'user-warehouse-uuid',
  'uuid-rec-002',
  'App\\Models\\Reception',
  'Recepción lote #1 - good - Transferencia',
  '2024-01-25 09:00:00'
);

-- ✅ INVENTARIO DESTINO CREADO
INSERT INTO inventory VALUES (
  'uuid-inv-002',
  'product-fertilizante-uuid',
  'brand-yara-uuid',
  'finca-esperanza-uuid', -- ✅
  'GENERAL',
  200.00, -- quantity ✅
  'kg',
  '2025-12-31',
  2500.00, -- unit_price (del origen)
  500000.00, -- total_value
  'good',
  '2024-01-25 09:00:00',
  '2024-01-25 09:00:00'
);
```

**Kardex en Bodega Central:**
```
Fecha        | Tipo    | Entrada | Salida  | Saldo  | Precio U. | Valor Total
-------------|---------|---------|---------|--------|-----------|-------------
2024-01-15   | Entrada | +300 kg |    -    | 300 kg | $2,500.00 | $750,000.00
2024-01-20   | Entrada | +200 kg |    -    | 500 kg | $2,500.00 | $1,250,000.00
2024-01-25   | Salida  |    -    | -200 kg | 300 kg | $2,500.00 | $750,000.00
```

**Kardex en Finca Esperanza:**
```
Fecha        | Tipo    | Entrada | Salida | Saldo  | Precio U. | Valor Total
-------------|---------|---------|--------|--------|-----------|-------------
2024-01-25   | Entrada | +200 kg |   -    | 200 kg | $2,500.00 | $500,000.00
```

---

## ✅ Checklist de Implementación Completa

### Movimientos de Inventario
- [x] Crear InventoryMovement tipo 'entry' para compras
- [x] Crear InventoryMovement tipo 'exit' para salidas (origen)
- [x] Crear InventoryMovement tipo 'entry' para salidas (destino)
- [x] Incluir `unit_price` en todos los movimientos
- [x] Incluir `total_price` en todos los movimientos
- [x] Vincular a `related_document_id` (reception)
- [x] Registrar `responsible_user`
- [x] Agregar observaciones detalladas
- [x] Timestamp preciso con `created_at`

### Actualización de Inventario
- [x] Actualizar tabla `inventory` automáticamente
- [x] Crear registro si no existe (solo entradas)
- [x] Sumar cantidad en entradas
- [x] Restar cantidad en salidas
- [x] Calcular precio promedio ponderado
- [x] Actualizar `total_value` automáticamente
- [x] Calcular `status` basado en vencimiento
- [x] Proteger contra stock negativo (max 0)
- [x] Usar batch_number='GENERAL' para agregados

### Precios
- [x] Obtener precio de `purchase_items` para compras
- [x] Obtener precio de `inventory` origen para salidas
- [x] Fallback a precio promedio de otras ubicaciones
- [x] Log de advertencia si no se encuentra precio
- [x] Cálculo de precio promedio ponderado correcto

### Transacciones y Seguridad
- [x] Todo dentro de DB::beginTransaction()
- [x] DB::commit() al final si todo OK
- [x] DB::rollBack() en catch si hay error
- [x] Logs detallados en cada paso
- [x] Validación de datos antes de procesar

### Auditoría
- [x] Trazabilidad completa de movimientos
- [x] Usuario responsable registrado
- [x] Fecha/hora exacta de cada operación
- [x] Documento origen vinculado
- [x] Observaciones descriptivas
- [x] Precios y valores para contabilidad
- [x] Estado de productos registrado

---

## 🎉 Resultado Final

El sistema ahora tiene **TRAZABILIDAD COMPLETA** para informes y auditorías:

1. ✅ **Cada recepción** genera movimientos completos de inventario
2. ✅ **Compras**: Solo entrada en destino (origen es externo)
3. ✅ **Salidas**: Salida en origen + entrada en destino
4. ✅ **Tabla inventory** se actualiza automáticamente
5. ✅ **Precios** incluidos en todos los movimientos
6. ✅ **Precio promedio ponderado** calculado automáticamente
7. ✅ **Protección con transacciones** - todo o nada
8. ✅ **Logs completos** para debugging
9. ✅ **Kardex funcional** con saldos corridos
10. ✅ **Informes contables** con precios y valores
