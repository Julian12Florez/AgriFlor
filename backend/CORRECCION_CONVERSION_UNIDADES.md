# CORRECCIÓN: Error de Conversión de Unidades en Validación de Stock

**Fecha:** 2025-12-12
**Estado:** ✅ COMPLETADO

---

## 🔴 EL PROBLEMA

### Error Reportado
```json
{
  "message": "No hay suficiente inventario disponible. Disponible: 5.00",
  "errors": {
    "products.0.quantity_delivered": [
      "No hay suficiente inventario disponible. Disponible: 5.00"
    ]
  }
}
```

### Datos del Selector (Frontend)
```json
{
  "inventory_id": "a083a9a8-dcc7-4982-aa31-e348c305e061",
  "product_name": "Nativo",
  "brand_name": "Bayer",
  "quantity": "5.00",              ← 5 canecas
  "unit": "caneca 4",              ← Cada caneca = 4 litros
  "base_quantity": 20,             ← 5 * 4 = 20 litros
  "base_unit": "L",                ← Unidad base
  "display_label": "Nativo - Sin vencimiento - 20.00 L disponible"
}
```

### Datos Enviados en el Request
```json
{
  "product_id": "a081cfd4-fb70-4e69-a621-f237322bd861",
  "brand_id": "a0614048-4319-480d-a675-1c2b552fc81f",
  "quantity_requested": 20,
  "quantity_delivered": 20,        ← 20 litros
  "unit": "L",                     ← Litros (unidad base)
  "batch_number": "GENERAL"
}
```

---

## 🚨 CAUSA RAÍZ

La validación estaba comparando **manzanas con naranjas**:

### Validación ANTES (INCORRECTA):
```php
// StoreProductOutputRequest.php líneas 196-200
$availableQuantity = \App\Models\Inventory::where('location_id', $originLocationId)
    ->where('product_id', $productId)
    ->where('brand_id', $brandId)
    ->where('status', 'good')
    ->sum('quantity');  // ← Suma 5.00 (canecas) ❌

// Comparación:
if ($availableQuantity < $quantityDelivered) {
    // 5 canecas < 20 litros → ❌ ERROR (unidades diferentes)
}
```

**El problema:**
- **Inventario:** 5 canecas (quantity = 5, unit = "caneca 4")
- **Solicitado:** 20 litros (quantity_delivered = 20, unit = "L")
- **Comparación:** 5 < 20 → ERROR ❌

Pero en realidad:
- 5 canecas * 4 litros/caneca = **20 litros disponibles** ✅
- Usuario solicita **20 litros** ✅
- **¡HAY SUFICIENTE STOCK!** ✅

---

## ✅ LA SOLUCIÓN

### Estrategia
**Convertir TODAS las cantidades a la unidad base antes de comparar.**

1. Obtener todos los registros de inventario (pueden estar en diferentes unidades)
2. Para cada registro, buscar su packaging_unit para obtener el factor de conversión
3. Convertir la cantidad del inventario a unidad base: `quantity * base_quantity`
4. Sumar todas las cantidades convertidas
5. Comparar con la cantidad solicitada (que ya viene en unidad base)

### Validación DESPUÉS (CORRECTA):

**Archivo:** `app/Http/Requests/StoreProductOutputRequest.php` (líneas 176-244)

```php
protected function validateInventoryAvailability($validator)
{
    $originLocationId = $this->input('origin_location_id');

    foreach ($this->input('products', []) as $index => $productData) {
        $productId = $productData['product_id'] ?? null;
        $brandId = $productData['brand_id'] ?? null;
        $quantityDelivered = $productData['quantity_delivered'] ?? 0;
        $requestedUnit = $productData['unit'] ?? null;

        if (!$productId || !$brandId) {
            continue;
        }

        // 1. Get all inventory records for this product/brand/location
        $inventoryRecords = \App\Models\Inventory::where('location_id', $originLocationId)
            ->where('product_id', $productId)
            ->where('brand_id', $brandId)
            ->where('status', 'good')
            ->get();  // ✅ Obtiene registros completos, no solo suma

        if ($inventoryRecords->isEmpty()) {
            $validator->errors()->add(
                "products.{$index}.quantity_delivered",
                "No hay inventario disponible para este producto"
            );
            continue;
        }

        // 2. Get product with packaging units to convert quantities
        $product = \App\Models\Product::with('packagingUnits')->find($productId);

        // 3. Convert all inventory quantities to base unit and sum
        $availableInBaseUnit = 0;
        foreach ($inventoryRecords as $inventory) {
            $conversionFactor = 1;

            // Find the packaging unit that matches the inventory unit
            if ($product && $product->packagingUnits) {
                $packagingUnit = $product->packagingUnits->first(function ($pu) use ($inventory) {
                    return strtolower($pu->name) === strtolower($inventory->unit);
                });

                if ($packagingUnit) {
                    // Convert to base unit: quantity * base_quantity
                    $conversionFactor = $packagingUnit->base_quantity;
                }
            }

            // ✅ Convierte y suma en unidad base
            $availableInBaseUnit += $inventory->quantity * $conversionFactor;
        }

        // 4. Compare in base unit (the request already comes in base unit)
        if ($availableInBaseUnit < $quantityDelivered) {
            $baseUnit = $product && $product->packagingUnits->isNotEmpty()
                ? $product->packagingUnits->first()->base_unit
                : 'unidades';

            $validator->errors()->add(
                "products.{$index}.quantity_delivered",
                "No hay suficiente inventario disponible. Disponible: {$availableInBaseUnit} {$baseUnit}, solicitado: {$quantityDelivered} {$requestedUnit}"
            );
        }
    }
}
```

---

## 📊 EJEMPLO DE CONVERSIÓN

### Escenario Real

**Inventario en BD:**
| ID | Product | Brand | Quantity | Unit | Status |
|----|---------|-------|----------|------|--------|
| 1  | Nativo  | Bayer | 5.00     | caneca 4 | good |

**Packaging Unit del Producto:**
| Name | Base Quantity | Base Unit |
|------|---------------|-----------|
| caneca 4 | 4 | L |

**Usuario solicita:**
- 20 litros de Nativo (Bayer)

**Proceso de Validación:**

1. **Obtener inventarios:** 1 registro (5.00 caneca 4)

2. **Buscar packaging unit:** "caneca 4"
   - base_quantity = 4
   - base_unit = "L"

3. **Convertir a unidad base:**
   ```
   availableInBaseUnit = 5.00 * 4 = 20.00 L
   ```

4. **Comparar:**
   ```
   if (20.00 L < 20 L) → FALSE ✅
   // Hay suficiente stock, validación pasa
   ```

**Resultado:** ✅ **Validación exitosa**

---

## 🔄 FLUJO COMPLETO

### Caso 1: Inventario en Múltiples Unidades

**Inventario:**
- 3 caneca 4 (= 12 L)
- 8 botella 1L (= 8 L)
- Total disponible: 20 L

**Usuario solicita:** 15 L

**Validación:**
```php
$availableInBaseUnit = 0;

// Registro 1: 3 caneca 4
$conversionFactor = 4;  // base_quantity de "caneca 4"
$availableInBaseUnit += 3 * 4 = 12 L

// Registro 2: 8 botella 1L
$conversionFactor = 1;  // base_quantity de "botella 1L"
$availableInBaseUnit += 8 * 1 = 8 L

// Total: 12 + 8 = 20 L
// Comparación: 20 L >= 15 L → ✅ OK
```

### Caso 2: Sin Packaging Unit (Unidad Genérica)

**Inventario:**
- 100 kg

**Packaging Units:** Ninguno

**Usuario solicita:** 50 kg

**Validación:**
```php
// No encuentra packaging unit
$conversionFactor = 1;  // Default
$availableInBaseUnit = 100 * 1 = 100 kg

// Comparación: 100 kg >= 50 kg → ✅ OK
```

---

## 📝 ARCHIVOS MODIFICADOS

### 1. app/Http/Requests/StoreProductOutputRequest.php
**Líneas:** 176-244

**Cambios:**
- Método `validateInventoryAvailability()` completamente reescrito
- Ahora obtiene registros completos en lugar de solo sumar
- Carga las packaging_units del producto
- Convierte cada cantidad a unidad base
- Compara en unidad base
- Mensaje de error mejorado con unidades específicas

### 2. app/Http/Requests/UpdateProductOutputRequest.php
**Líneas:** 195-280

**Cambios:**
- Mismo cambio que StoreProductOutputRequest
- Mantiene la lógica adicional de "add back" para updates
- La cantidad existente se suma en unidad base

---

## ✅ BENEFICIOS DE LA SOLUCIÓN

### 1. Precisión
✅ Compara cantidades en la misma unidad (base)
✅ Maneja correctamente productos con múltiples unidades de empaque
✅ Evita falsos negativos por diferencias de unidades

### 2. Flexibilidad
✅ Funciona con cualquier packaging unit
✅ Soporta múltiples registros de inventario en diferentes unidades
✅ Funciona incluso sin packaging units (usa factor 1)

### 3. Mensajes Claros
```
Antes: "No hay suficiente inventario disponible. Disponible: 5.00"
Ahora: "No hay suficiente inventario disponible. Disponible: 20 L, solicitado: 20 L"
```
✅ Muestra cantidades y unidades
✅ Usuario entiende exactamente qué está pasando

### 4. Robustez
✅ Maneja casos edge: sin packaging units, unidades no encontradas
✅ Usa conversión por defecto (factor 1) cuando no encuentra la unidad
✅ Continúa validando otros productos si uno falla

---

## 🧪 CASOS DE PRUEBA

### Caso 1: Stock Suficiente con Conversión
```
Inventario: 5 caneca 4 = 20 L
Solicitud: 20 L
Resultado: ✅ APROBADO
```

### Caso 2: Stock Insuficiente
```
Inventario: 2 caneca 4 = 8 L
Solicitud: 20 L
Resultado: ❌ ERROR "Disponible: 8 L, solicitado: 20 L"
```

### Caso 3: Múltiples Registros
```
Inventario: 3 caneca 4 (12 L) + 8 botella 1L (8 L) = 20 L
Solicitud: 15 L
Resultado: ✅ APROBADO
```

### Caso 4: Sin Packaging Units
```
Inventario: 100 kg (sin packaging units)
Solicitud: 50 kg
Resultado: ✅ APROBADO (usa factor 1)
```

### Caso 5: Actualización (Update)
```
Inventario: 10 L
Salida existente: 5 L
Disponible para update: 10 + 5 = 15 L
Nueva solicitud: 12 L
Resultado: ✅ APROBADO
```

---

## 🎯 RESUMEN EJECUTIVO

### El Problema
La validación comparaba cantidades en **unidades diferentes**:
- Inventario en "canecas" (5.00)
- Solicitud en "litros" (20)
- Resultado: Falso negativo ❌

### La Solución
**Convertir TODO a unidad base antes de comparar:**
1. Obtener packaging_units del producto
2. Convertir cada registro de inventario a unidad base
3. Sumar todas las cantidades convertidas
4. Comparar con la solicitud (ya en unidad base)

### Archivos Modificados
- `app/Http/Requests/StoreProductOutputRequest.php`
- `app/Http/Requests/UpdateProductOutputRequest.php`

### Resultado
✅ Validación precisa con conversión de unidades
✅ Mensajes claros mostrando cantidades y unidades
✅ Soporta múltiples unidades de empaque
✅ Maneja casos edge correctamente

**Estado:** ✅ **LISTO PARA PRODUCCIÓN**
