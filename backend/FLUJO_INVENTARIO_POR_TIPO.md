# FLUJO DE INVENTARIO POR TIPO DE MOVIMIENTO

**Fecha:** 2025-12-13
**Estado:** ✅ IMPLEMENTADO (ACTUALIZADO - Reducción Gradual)

---

## ⚡ CAMBIO IMPORTANTE: REDUCCIÓN GRADUAL DE INVENTARIO

### 🔄 Nuevo Flujo (Opción B - Implementado)

**Para ProductOutputs:**
1. **Al APROBAR:**
   - ✅ Se VALIDA que hay inventario suficiente
   - ✅ Se cambia estado a `approved`
   - ❌ NO se reduce inventario
   - ❌ NO se crean movimientos de inventario
   - ⚠️ Se crea alerta de stock bajo PROYECTADO (si aplica)

2. **Al RECEPCIONAR (parcial o total):**
   - ✅ Se reduce inventario GRADUALMENTE (cantidad recepcionada)
   - ✅ Se crea EXIT movement en origen
   - ✅ Se crea ENTRY movement en destino (si no es consumption)
   - ✅ Cada recepción parcial actualiza el stock

**Ventajas:**
- ✅ Cada recepción parcial genera sus propios movimientos
- ✅ Stock se reduce gradualmente a medida que se recepciona
- ✅ Trazabilidad perfecta: cada EXIT tiene su ENTRY correspondiente
- ✅ Movimientos reflejan la realidad física del momento

**Ejemplo con Recepciones Parciales:**
```
Inventario inicial Bodega A: 25 unidades
ProductOutput aprobado: 19 unidades (origen: Bodega A, destino: Bodega B)

1️⃣ APROBAR Output:
   Bodega A: 25 unidades (sin cambios)
   Bodega B: 0 unidades
   Movements: ninguno

2️⃣ Primera Recepción (12 unidades):
   Bodega A: 13 unidades (25 - 12) ✅
   Bodega B: 12 unidades ✅
   Movements:
     - EXIT 12u en Bodega A
     - ENTRY 12u en Bodega B

3️⃣ Segunda Recepción (7 unidades):
   Bodega A: 6 unidades (13 - 7) ✅
   Bodega B: 19 unidades (12 + 7) ✅
   Movements:
     - EXIT 7u en Bodega A
     - ENTRY 7u en Bodega B

📊 Total Movements: 2 EXIT + 2 ENTRY = 4 movimientos (trazabilidad completa)
```

### 🔄 Compatibilidad con Flujo Antiguo

**Problema:** Outputs aprobados ANTES del cambio ya redujeron inventario al aprobar.

**Solución Implementada:** Detección automática de flujo

```php
// En ReceptionController::processInventoryMovements()
$inventoryAlreadyReduced = InventoryMovement::where('related_document_type', 'App\\Models\\ProductOutput')
    ->where('related_document_id', $reception->source_id)
    ->where('product_id', $productId)
    ->where('brand_id', $brandId)
    ->exists();

if ($inventoryAlreadyReduced) {
    // FLUJO ANTIGUO: Ya se redujo al aprobar
    // Solo crear ENTRY en destino (si no es consumption)
    // NO crear EXIT (evita reducción duplicada)
} else {
    // FLUJO NUEVO: Reducir gradualmente
    // Crear EXIT + ENTRY normalmente
}
```

**Ventaja:** El sistema funciona correctamente con AMBOS flujos sin necesidad de migración.

---

## 📋 TIPOS DE MOVIMIENTOS Y SU COMPORTAMIENTO

### 1. ENTRADAS (Recepciones de Compras)

**Tipo:** `purchase`
**Comportamiento:**
- ✅ Crea ENTRY movement en **destino**
- ✅ Suma stock en ubicación de destino
- ✅ NO afecta ningún origen (viene del proveedor)

**Flujo:**
```
PROVEEDOR → [ENTRY] → UBICACIÓN DESTINO
                      Stock: +cantidad
```

**Ejemplo:**
```
Compra de 100 kg de fertilizante
Proveedor: Agro Sur
Destino: Bodega Central

Resultado:
- Bodega Central: +100 kg
- Inventory Movement: ENTRY en Bodega Central
```

---

### 2. SALIDAS TIPO CONSUMO

**Tipo:** `output` con `output_type.code = 'consumption'`
**Comportamiento:**
- ✅ Crea EXIT movement en **origen**
- ✅ Descuenta stock de ubicación origen
- ❌ NO crea ENTRY movement en destino
- ❌ NO suma stock en ubicación destino
- ✅ Mantiene trazabilidad de farm_lots donde se consumió

**Flujo:**
```
UBICACIÓN ORIGEN → [EXIT] → CONSUMO EN LOTES
Stock: -cantidad              (NO se suma en destino)
```

**Ejemplo:**
```
Salida de consumo: 20 L de fungicida Nativo
Origen: Villa antonella (bodega)
Destino: Finca Julian (lotes 1 y 2)
Tipo: Consumo

Resultado:
- Villa antonella: -20 L ✅
- Finca Julian: +0 L (se consume, no se almacena) ✅
- Farm Lots asociados: lote 1, lote 2 (trazabilidad) ✅
- Inventory Movement: EXIT en Villa antonella ✅
```

**Por qué:**
- El producto se CONSUME/APLICA en los lotes de la finca
- No se almacena en la ubicación destino
- Los farm_lots registran DÓNDE se consumió
- El inventario solo disminuye en origen

---

### 3. SALIDAS TIPO TRANSFERENCIA

**Tipo:** `output` con `output_type.code != 'consumption'`
**Incluye:** `transfer`, `technical_order`, `free_request`
**Comportamiento:**
- ✅ Crea EXIT movement en **origen**
- ✅ Descuenta stock de ubicación origen
- ✅ Crea ENTRY movement en **destino**
- ✅ Suma stock en ubicación destino

**Flujo:**
```
UBICACIÓN ORIGEN → [EXIT] → [ENTRY] → UBICACIÓN DESTINO
Stock: -cantidad                      Stock: +cantidad
```

**Ejemplo:**
```
Transferencia: 50 kg de abono
Origen: Bodega Central
Destino: Bodega Norte
Tipo: Transferencia

Resultado:
- Bodega Central: -50 kg ✅
- Bodega Norte: +50 kg ✅
- Inventory Movement: EXIT en Bodega Central ✅
- Inventory Movement: ENTRY en Bodega Norte ✅
```

**Por qué:**
- El producto se MUEVE de un lugar a otro
- El inventario total no cambia (solo se redistribuye)
- Stock total sistema: 0 (sale de un lado, entra del otro)

---

## 🔄 IMPLEMENTACIÓN EN ReceptionController

### Método: `processInventoryMovements()`

**Archivo:** `app/Http/Controllers/Api/ReceptionController.php`
**Líneas:** 1006-1056

```php
elseif ($sourceType === 'output') {
    // OUTPUT: Behavior depends on output type

    // Get the ProductOutput to check its type
    $output = $reception->productOutput()->with('outputType')->first();
    $outputTypeCode = $output?->outputType?->code;

    // 1. ALWAYS create EXIT movement in origin location
    $this->createExitMovement(
        $reception,
        $productId,
        $brandId,
        $quantityReceived,
        $unit,
        $unitPrice,
        $userId,
        $batchNumber
    );

    // 2. Create ENTRY movement in destination ONLY if NOT consumption
    if ($outputTypeCode !== 'consumption') {
        // Transfer, technical_order, free_request: create entry
        $this->createEntryMovement(
            $reception,
            $productId,
            $brandId,
            $quantityReceived,
            $unit,
            $expirationDate,
            $unitPrice,
            $userId,
            $batchNumber,
            $itemData['condition']
        );

        \Log::info('Output transfer: inventory moved from origin to destination');
    } else {
        // Consumption: only exit, no entry in destination
        \Log::info('Output consumption: inventory consumed (no entry in destination)');
    }
}
```

---

## 📊 TABLA COMPARATIVA

| Tipo de Movimiento | Origen Stock | Destino Stock | Movements Creados | Trazabilidad |
|--------------------|--------------|---------------|-------------------|--------------|
| **Compra (Purchase)** | N/A | +cantidad | ENTRY en destino | ReceptionBatch |
| **Consumo (Consumption)** | -cantidad | +0 ❌ | EXIT en origen | Farm Lots + ReceptionBatch |
| **Transferencia** | -cantidad | +cantidad | EXIT en origen + ENTRY en destino | ReceptionBatch |
| **Orden Técnica** | -cantidad | +cantidad | EXIT en origen + ENTRY en destino | ReceptionBatch |
| **Solicitud Libre** | -cantidad | +cantidad | EXIT en origen + ENTRY en destino | ReceptionBatch |

---

## 🎯 CASOS DE USO REALES

### Caso 1: Compra de Insumos
```
Usuario compra 200 kg de fertilizante
└─ Crea purchase
└─ Crea reception
└─ Registra batch recibido: 200 kg
└─ Sistema crea:
   └─ InventoryMovement: ENTRY en Bodega Central
   └─ Inventory: +200 kg en Bodega Central
```

### Caso 2: Aplicación en Lote (Consumo)
```
Usuario necesita aplicar fungicida en lote 1 y lote 2
└─ Crea output tipo "Consumo"
   └─ Origen: Bodega Finca
   └─ Destino: Finca (para trazabilidad)
   └─ Farm Lots: [lote 1, lote 2]
└─ Crea reception
└─ Registra batch aplicado: 20 L
└─ Sistema crea:
   └─ InventoryMovement: EXIT en Bodega Finca ✅
   └─ Inventory: -20 L en Bodega Finca ✅
   └─ NO crea ENTRY en Finca ✅
   └─ Farm Lots asociados: registran dónde se aplicó ✅
```

### Caso 3: Transferencia entre Bodegas
```
Usuario transfiere productos de una bodega a otra
└─ Crea output tipo "Transferencia"
   └─ Origen: Bodega Central
   └─ Destino: Bodega Norte
└─ Crea reception
└─ Registra batch transferido: 50 kg
└─ Sistema crea:
   └─ InventoryMovement: EXIT en Bodega Central ✅
   └─ Inventory: -50 kg en Bodega Central ✅
   └─ InventoryMovement: ENTRY en Bodega Norte ✅
   └─ Inventory: +50 kg en Bodega Norte ✅
```

---

## ✅ VERIFICACIONES DE INTEGRIDAD

### Para Consumo:
- [ ] Stock disminuye SOLO en origen
- [ ] Stock NO aumenta en destino
- [ ] Farm lots están asociados a la salida
- [ ] Se registra solo EXIT movement
- [ ] Logs indican "inventory consumed"

### Para Transferencias:
- [ ] Stock disminuye en origen
- [ ] Stock aumenta en destino
- [ ] Se registran EXIT y ENTRY movements
- [ ] Stock total del sistema no cambia
- [ ] Logs indican "inventory moved"

### Para Compras:
- [ ] Stock aumenta en destino
- [ ] Se registra solo ENTRY movement
- [ ] Stock total del sistema aumenta

---

## 🔍 TRAZABILIDAD POR TIPO

### Compras (Purchase)
- **Origen:** Proveedor (no en sistema)
- **Destino:** Ubicación
- **Productos:** purchase_items
- **Batches:** reception_batches
- **Inventory:** solo en destino

### Consumo (Consumption)
- **Origen:** Ubicación (bodega/almacén)
- **Destino:** Finca (solo referencia)
- **Farm Lots:** output_farm_lots (dónde se consumió)
- **Productos:** output_products
- **Batches:** reception_batches
- **Inventory:** solo descuento de origen

### Transferencias (Transfer, etc.)
- **Origen:** Ubicación
- **Destino:** Ubicación
- **Productos:** output_products
- **Batches:** reception_batches
- **Inventory:** descuento origen + suma destino

---

## 📝 LOGS PARA MONITOREO

### Consumo
```
[INFO] Output consumption: inventory consumed (no entry in destination)
{
  "output_type": "consumption",
  "origin": "uuid-origen",
  "destination": "uuid-destino",
  "farm_lots": ["uuid-lote-1", "uuid-lote-2"]
}
```

### Transferencia
```
[INFO] Output transfer: inventory moved from origin to destination
{
  "output_type": "transfer",
  "origin": "uuid-origen",
  "destination": "uuid-destino"
}
```

---

## 🚨 IMPORTANTE

1. **Nunca** crear ENTRY movement para salidas de consumo
2. **Siempre** crear EXIT movement para todas las salidas
3. **Verificar** output_type.code antes de decidir si crear ENTRY
4. **Mantener** farm_lots para trazabilidad de consumo
5. **Usar FIFO** para reducir inventario en origen

---

## ✅ ESTADO ACTUAL

- [x] Compras: funcionan correctamente (solo ENTRY en destino)
- [x] Consumo: corregido (solo EXIT en origen, no ENTRY en destino)
- [x] Transferencias: funcionan correctamente (EXIT origen + ENTRY destino)
- [x] Trazabilidad: farm_lots registran ubicación de consumo
- [x] Logs: diferencian entre consumo y transferencia

**Fecha de implementación:** 2025-12-12
**Archivo modificado:** `app/Http/Controllers/Api/ReceptionController.php`
**Estado:** ✅ LISTO PARA PRODUCCIÓN
