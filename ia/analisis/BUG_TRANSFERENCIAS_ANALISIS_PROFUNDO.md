# 🐛 ANÁLISIS PROFUNDO: BUG DE PÉRDIDA DE STOCK EN TRANSFERENCIAS PARCIALES

**Fecha:** 2026-01-19
**Estado:** BUG CONFIRMADO POR USUARIO
**Severidad:** 🔴 CRÍTICA

---

## 📋 DESCRIPCIÓN DEL BUG

### Escenario Real Reportado por Usuario:

1. **Stock Inicial:** Producto X tiene **100 unidades** en Ubicación A
2. **Acción:** Usuario crea transferencia de **60 unidades** de A → B
3. **Acción:** Usuario recepciona las 60 unidades en B
4. **Resultado INCORRECTO:**
   - Ubicación B: ✅ 60 unidades (CORRECTO)
   - Ubicación A: ❌ **0 unidades** (INCORRECTO - debería tener 40)
5. **CONSECUENCIA:** **40 unidades DESAPARECEN del sistema**

---

## 🔍 HIPÓTESIS DEL BUG (MUY PROBABLE)

**El sistema está pasando la cantidad TOTAL de la salida (`quantity_delivered`) en lugar de la cantidad RECIBIDA en la recepción parcial (`quantity_received`) al método `reduceInventoryFIFO()`.**

### Explicación Técnica:

Cuando se crea una salida de 60 unidades y luego se hace una recepción parcial de **30 unidades**:

- ❌ **INCORRECTO (Bug):** Se reduce el inventario en **60 unidades** (cantidad total de la salida)
- ✅ **CORRECTO:** Debería reducir el inventario en **30 unidades** (cantidad recibida en esta recepción)

---

## 📂 ARCHIVOS Y LÍNEAS CRÍTICAS A REVISAR

### 🔴 ARCHIVO 1: `ReceptionController.php`

**Ubicación:** `/home/julian/Documentos/AgriFlor/backend/app/Http/Controllers/Api/ReceptionController.php`

#### Método 1: `createReceptionWithBatch()` (líneas 424-563)

Este es el punto de entrada cuando se crea una recepción.

**QUÉ REVISAR:**
```php
// ¿Qué valor tiene $itemData['quantity_received']?
// ¿Se está pasando correctamente a processInventoryMovements?

foreach ($data['items'] as $itemData) {
    $this->processInventoryMovements(
        $reception,
        $itemData,  // ← ¿Contiene quantity_received = 30 o 60?
        $receptionItem,
        $batchNumber,
        $request->user()->id
    );
}
```

---

#### ⚠️ Método 2: `processInventoryMovements()` (líneas 1026-1204)

**ESTE ES EL MÉTODO MÁS CRÍTICO**

**QUÉ BUSCAR:**

```php
private function processInventoryMovements(
    Reception $reception,
    array $itemData,       // ← Contiene quantity_received
    $receptionItem,
    int $batchNumber,
    string $userId
): void {
    // ...

    if ($sourceType === 'output') {
        // 🔍 AQUÍ ESTÁ EL PROBLEMA PROBABLE

        // PREGUNTA CRÍTICA:
        // ¿Qué cantidad se pasa a createExitMovement?

        $this->createExitMovement(
            $reception,
            $itemData,          // ← ¿Usa $itemData['quantity_received']?
            $receptionItem,     // ← ¿O usa $receptionItem->quantity_expected?
            $batchNumber,
            $userId
        );
    }
}
```

**BUSCAR EN EL CÓDIGO:**

```bash
# Busca cómo se determina la cantidad a reducir
grep -n "quantity" backend/app/Http/Controllers/Api/ReceptionController.php | grep -A5 -B5 "createExitMovement"
```

---

#### 🔴 Método 3: `createExitMovement()` (líneas 1271-1319)

**ESTE MÉTODO LLAMA A reduceInventoryFIFO**

**QUÉ REVISAR:**

```php
private function createExitMovement(
    Reception $reception,
    array $itemData,
    $receptionItem,
    int $batchNumber,
    string $userId
): void {
    // 🔍 LÍNEA CRÍTICA: ¿Qué valor tiene $quantity?

    // POSIBLE BUG (Hipótesis A):
    $quantity = $receptionItem->quantity_expected;  // ❌ 60 (TOTAL)

    // CORRECTO debería ser (Hipótesis B):
    $quantity = $itemData['quantity_received'];     // ✅ 30 (PARCIAL)

    // O tal vez:
    $quantity = $receptionItem->quantity_delivered; // ❌ 60 (TOTAL)

    // Crear movimiento EXIT
    $movement = InventoryMovement::create([
        'type' => 'exit',
        'quantity' => $quantity,  // ← VALOR CRÍTICO
        // ...
    ]);

    // 🚨 AQUÍ SE REDUCE EL INVENTARIO
    $this->reduceInventoryFIFO(
        $productId,
        $brandId,
        $locationId,
        $quantity  // ← SI AQUÍ LLEGA 60 EN LUGAR DE 30, ESE ES EL BUG
    );
}
```

---

#### ✅ Método 4: `reduceInventoryFIFO()` (líneas 1405-1477)

**ESTE MÉTODO PARECE CORRECTO** (según análisis previo)

```php
private function reduceInventoryFIFO(
    string $productId,
    string $brandId,
    string $locationId,
    float $quantity  // ← El problema es QUÉ VALOR LLEGA AQUÍ
): void {
    // La lógica FIFO es correcta:
    foreach ($inventoryBatches as $batch) {
        if ($batch->quantity >= $remainingQuantity) {
            $batch->quantity -= $remainingQuantity;  // ✅ Correcto

            if ($batch->quantity > 0) {
                $batch->save();  // ✅ Guarda remanente
            } else {
                $batch->delete(); // ✅ Solo elimina si queda en 0
            }
        }
    }
}
```

**NOTA:** Este método funciona correctamente. El problema NO está aquí, está en QUÉ VALOR SE LE PASA.

---

## 🔧 CÓMO IDENTIFICAR EL BUG EXACTO

### Paso 1: Agregar Logs Temporales

Agrega estos logs en `ReceptionController.php`:

```php
// En createReceptionWithBatch() (alrededor línea 500)
foreach ($data['items'] as $itemData) {
    \Log::info('🔍 DEBUG: Item Data', [
        'quantity_received' => $itemData['quantity_received'] ?? 'NO EXISTE',
        'product_id' => $itemData['product_id'] ?? 'NO EXISTE',
    ]);

    $this->processInventoryMovements(...);
}

// En processInventoryMovements() (alrededor línea 1100-1150)
\Log::info('🔍 DEBUG: Process Inventory Movements', [
    'source_type' => $sourceType,
    'itemData_quantity_received' => $itemData['quantity_received'] ?? 'NO EXISTE',
    'receptionItem_quantity_expected' => $receptionItem->quantity_expected ?? 'NO EXISTE',
    'receptionItem_quantity_received' => $receptionItem->quantity_received ?? 'NO EXISTE',
]);

// En createExitMovement() (alrededor línea 1280-1290)
\Log::info('🔍 DEBUG: Create Exit Movement - CANTIDAD A REDUCIR', [
    'quantity' => $quantity,  // ← ESTE ES EL VALOR CRÍTICO
    'itemData' => $itemData,
    'receptionItem' => [
        'quantity_expected' => $receptionItem->quantity_expected,
        'quantity_received' => $receptionItem->quantity_received,
    ],
]);

// Antes de llamar a reduceInventoryFIFO()
\Log::info('🚨 LLAMANDO A reduceInventoryFIFO', [
    'quantity_to_reduce' => $quantity,
    'product_id' => $productId,
    'location_id' => $locationId,
]);
```

### Paso 2: Ejecutar Prueba Manual

1. Asegúrate que un producto tenga **100 unidades** en una ubicación
2. Crea una salida de transferencia de **60 unidades**
3. Haz una recepción **PARCIAL** de solo **30 unidades**
4. Revisa los logs:

```bash
docker exec agriflor-app tail -f storage/logs/laravel.log | grep "🔍\|🚨"
```

### Paso 3: Identificar el Valor Incorrecto

En los logs busca:

- `quantity_received`: Debería ser **30**
- `quantity_to_reduce`: Debería ser **30**

**Si `quantity_to_reduce` es 60 en lugar de 30, ESE ES EL BUG.**

---

## 💡 SOLUCIÓN PROPUESTA

Una vez identificada la línea exacta, la corrección probablemente será algo como:

### ANTES (Bug):
```php
private function createExitMovement(...): void
{
    // ❌ Usa la cantidad TOTAL de la salida
    $quantity = $output->outputProducts()
        ->where('product_id', $productId)
        ->first()
        ->quantity_delivered;  // 60 (TOTAL)

    $this->reduceInventoryFIFO(..., $quantity);
}
```

### DESPUÉS (Correcto):
```php
private function createExitMovement(...): void
{
    // ✅ Usa la cantidad RECIBIDA en esta recepción específica
    $quantity = $itemData['quantity_received'];  // 30 (PARCIAL)

    $this->reduceInventoryFIFO(..., $quantity);
}
```

---

## 📊 DATOS REALES CONSULTADOS

Durante la investigación se confirmó:

- ✅ Hay productos con stock real en BODEGA PRINCIPAL
- ✅ Glifosato: 200 L disponibles
- ✅ Ubicaciones: BODEGA PRINCIPAL, Bodega Central, Finca El Paraíso, etc.
- ✅ Sistema de autenticación funciona
- ⚠️ Hay error 500 al crear salidas via API (problema de sesiones, secundario)

---

## 🎯 PRÓXIMOS PASOS INMEDIATOS

1. ✅ **AGREGAR LOGS** en los 3 métodos críticos
2. ✅ **EJECUTAR PRUEBA MANUAL** con datos reales
3. ✅ **REVISAR LOGS** para identificar valor incorrecto
4. ✅ **CORREGIR LÍNEA** específica del código
5. ✅ **PROBAR NUEVAMENTE** y verificar que funciona
6. ✅ **CREAR TESTS AUTOMATIZADOS** para evitar regresión

---

## ⚠️ IMPACTO DEL BUG

- **Severidad:** CRÍTICA
- **Pérdida de datos:** Stock desaparece del sistema
- **Afecta a:** Todas las transferencias con recepciones parciales
- **Solución:** Simple (cambiar 1-2 líneas de código)
- **Tiempo de corrección:** 1-2 horas (incluyendo pruebas)

---

## 📝 NOTAS ADICIONALES

### Flujo Correcto de Recepciones Parciales:

```
Estado Inicial:
- Ubicación A: 100 unidades

Salida creada: 60 unidades (A → B)
- Ubicación A: 100 unidades (sin cambios en NEW FLOW)

Recepción 1: 30 unidades
- Ubicación A: 100 - 30 = 70 unidades ✅
- Ubicación B: 30 unidades ✅

Recepción 2: 30 unidades (resto)
- Ubicación A: 70 - 30 = 40 unidades ✅
- Ubicación B: 60 unidades ✅

Total conservado: 100 unidades ✅
```

### Campos Importantes en los Modelos:

**OutputProduct:**
- `quantity_requested`: Cantidad solicitada inicialmente
- `quantity_delivered`: Cantidad total aprobada para salir

**ReceptionItem:**
- `quantity_expected`: Cantidad total esperada (= quantity_delivered)
- `quantity_received`: Cantidad ACUMULADA recibida hasta ahora

**$itemData (en request):**
- `quantity_received`: Cantidad recibida EN ESTA RECEPCIÓN ESPECÍFICA

**¡El bug probablemente está usando `quantity_expected` o `quantity_delivered` en lugar de `$itemData['quantity_received']`!**

---

**FIN DEL ANÁLISIS**

---

**Contacto para dudas:**
- Revisar con desarrollador que conozca el flujo completo
- Ejecutar logs y compartir resultados
- Implementar corrección basada en hallazgos de logs
