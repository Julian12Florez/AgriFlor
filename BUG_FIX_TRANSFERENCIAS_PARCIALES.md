# CORRECCIONES APLICADAS - Bug de Transferencias Parciales

## RESUMEN EJECUTIVO

**Bug Identificado:** Pérdida de unidades en transferencias parciales
**Causa Raíz:** Falta de filtro por `brand_id` al buscar ReceptionItem
**Severidad:** ⚠️ CRÍTICA
**Estado:** ✅ CORREGIDO

---

## CORRECCIONES IMPLEMENTADAS

### 1. ReceptionController.php - createReceptionWithBatch() - Línea ~515

**Archivo:** `/home/julian/Documentos/AgriFlor/backend/app/Http/Controllers/Api/ReceptionController.php`

**ANTES:**
```php
$receptionItem = $reception->receptionItems()
    ->where('product_id', $itemData['product_id'])
    ->first();
```

**DESPUÉS:**
```php
// BUG FIX: Added brand_id filter to prevent using wrong reception item
$receptionItem = $reception->receptionItems()
    ->where('product_id', $itemData['product_id'])
    ->where('brand_id', $itemData['brand_id'] ?? null)
    ->first();
```

---

### 2. ReceptionController.php - addBatch() - Línea ~613

**Archivo:** `/home/julian/Documentos/AgriFlor/backend/app/Http/Controllers/Api/ReceptionController.php`

**ANTES:**
```php
$receptionItem = $reception->receptionItems()
    ->where('product_id', $itemData['product_id'])
    ->first();
```

**DESPUÉS:**
```php
// BUG FIX: Added brand_id filter to prevent using wrong reception item
$receptionItem = $reception->receptionItems()
    ->where('product_id', $itemData['product_id'])
    ->where('brand_id', $itemData['brand_id'] ?? null)
    ->first();
```

---

### 3. ReceptionController.php - Validación inline - Línea 436

**Archivo:** `/home/julian/Documentos/AgriFlor/backend/app/Http/Controllers/Api/ReceptionController.php`

**ANTES:**
```php
$data = $request->validate([
    // ...
    'items.*.product_id' => 'required|uuid',
    'items.*.quantity_received' => 'required|numeric|gt:0',
    // ...
]);
```

**DESPUÉS:**
```php
$data = $request->validate([
    // ...
    'items.*.product_id' => 'required|uuid',
    'items.*.brand_id' => 'nullable|uuid|exists:brands,id',  // BUG FIX
    'items.*.quantity_received' => 'required|numeric|gt:0',
    // ...
]);
```

---

### 4. StoreReceptionBatchRequest.php - Línea 24

**Archivo:** `/home/julian/Documentos/AgriFlor/backend/app/Http/Requests/StoreReceptionBatchRequest.php`

**ANTES:**
```php
'items.*.product_id' => ['required', 'uuid', 'exists:products,id'],
'items.*.quantity_received' => ['required', 'numeric', 'gt:0'],
```

**DESPUÉS:**
```php
'items.*.product_id' => ['required', 'uuid', 'exists:products,id'],
'items.*.brand_id' => ['nullable', 'uuid', 'exists:brands,id'],  // BUG FIX
'items.*.quantity_received' => ['required', 'numeric', 'gt:0'],
```

---

### 5. User.php - Helper hasRole() - Línea 85

**Archivo:** `/home/julian/Documentos/AgriFlor/backend/app/Models/User.php`

**NUEVO MÉTODO:**
```php
public function hasRole(string $roleName): bool
{
    return $this->role === $roleName;
}
```

---

## ARCHIVOS CREADOS PARA TESTING

1. **`tests/Feature/TransferenciaBugPreciseTest.php`** - Test completo de reproducción
2. **`database/factories/ProductFactory.php`** - Factory para tests
3. **`database/factories/BrandFactory.php`** - Factory para tests
4. **`database/factories/LocationFactory.php`** - Factory para tests

---

## CÓMO FUNCIONA LA CORRECCIÓN

### Problema:
Cuando se recepcionaba parcialmente, el código buscaba `ReceptionItem` solo por `product_id`, sin filtrar por `brand_id`. Si había múltiples registros para el mismo producto, traía el incorrecto y usaba datos equivocados.

### Solución:
Ahora filtra por **ambos**: `product_id` + `brand_id`, asegurando match exacto.

---

## PASOS SIGUIENTES

### 🔴 URGENTE: Actualizar Frontend

El frontend **DEBE** enviar `brand_id` en requests de recepción:

```javascript
// ✅ Request CORRECTO
{
  "items": [
    {
      "product_id": "uuid",
      "brand_id": "uuid",  // ← DEBE INCLUIRSE
      "quantity_received": 60,
      "condition": "good"
    }
  ]
}
```

### Testing

```bash
# Ejecutar test (requiere SQLite configurado)
php artisan test --filter=TransferenciaBugPreciseTest

# Monitorear logs
tail -f storage/logs/laravel.log | grep "Reception Item Info"
```

---

## ARCHIVOS MODIFICADOS

- ✅ `ReceptionController.php` (3 cambios)
- ✅ `StoreReceptionBatchRequest.php` (1 cambio)
- ✅ `User.php` (1 método nuevo)
- ✅ `phpunit.xml` (habilitado SQLite)

## ARCHIVOS CREADOS

- ✅ `BUG_REPORT_TRANSFERENCIA_PARCIAL.md` (análisis completo)
- ✅ `BUG_FIX_TRANSFERENCIAS_PARCIALES.md` (este archivo)
- ✅ `TransferenciaBugPreciseTest.php`
- ✅ 3 factories para tests

---

**Fecha:** 2026-01-19
**Corregido por:** Claude (Anthropic)
**Total de líneas corregidas:** ~10 líneas críticas

---

## CHECKLIST DE DEPLOYMENT

- [x] Código corregido
- [x] Tests creados
- [x] Documentación completa
- [ ] **Frontend actualizado (PENDIENTE)**
- [ ] Tests ejecutados
- [ ] Deploy a staging
- [ ] Deploy a producción
- [ ] Monitoreo 24h post-deploy
