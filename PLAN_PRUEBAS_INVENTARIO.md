# 🧪 PLAN DE PRUEBAS EXHAUSTIVO - SISTEMA DE INVENTARIO AGRIFLOR

**Fecha:** 2025-11-17
**Objetivo:** Verificar funcionamiento completo del sistema de inventario
**Módulos a Probar:** Compras, Recepciones (parciales/totales), Salidas, Inventario

---

## 📋 ESCENARIOS DE PRUEBA

### 🎯 ESCENARIO 1: FLUJO COMPLETO CON RECEPCIÓN PARCIAL

**Objetivo:** Compra → Recepción en 3 lotes → Verificar inventario

**Pasos:**

1. **Crear Compra de 300 kg de NPK 10-20-20**
   - Proveedor: Proveedores Agrícolas S.A.
   - Ubicación destino: Bodega Central
   - 3 productos diferentes, cantidades grandes
   - Adjuntar factura PDF simulada

2. **Primera Recepción Parcial (40%)**
   - Recibir 120 kg de NPK (40% de 300 kg)
   - Condición: good
   - Fecha vencimiento: 2026-12-31
   - Adjuntar remisión

3. **Segunda Recepción Parcial (30%)**
   - Recibir 90 kg de NPK (30% de 300 kg)
   - Condición: good
   - Fecha vencimiento: 2027-03-15
   - Total recibido hasta ahora: 70%

4. **Tercera Recepción Final (30%)**
   - Recibir 90 kg de NPK (30% restante)
   - Condición: 60 kg good, 30 kg damaged
   - Solo 60 kg van a inventario
   - Total recibido: 100%

5. **Verificaciones:**
   - ✅ Inventario total en Bodega Central = 270 kg (120 + 90 + 60)
   - ✅ 30 kg damaged NO están en inventario disponible
   - ✅ 2 lotes diferentes (diferentes fechas vencimiento)
   - ✅ Movimientos de inventario registrados (3 entries)
   - ✅ Recepción status = 'completed'
   - ✅ Compra status = 'received'
   - ✅ API pending-products retorna vacío

---

### 🎯 ESCENARIO 2: FLUJO COMPLETO CON RECEPCIÓN TOTAL

**Objetivo:** Compra → Recepción en 1 solo lote → Salida → Verificar stock

**Pasos:**

1. **Crear Compra de 200 L de Glifosato**
   - Proveedor: Agroquímicos del Valle
   - Ubicación: Bodega Norte
   - Marca: Bayer
   - Precio unitario: $50.000/L

2. **Recepción Total (100% en un lote)**
   - Recibir 200 L completos
   - Condición: good
   - Fecha vencimiento: 2026-06-30

3. **Crear Salida de 80 L a Finca El Paraíso**
   - Origen: Bodega Norte
   - Destino: Finca El Paraíso
   - Cantidad: 80 L + 5% = 84 L
   - Status inicial: pending_approval

4. **Aprobar Salida**
   - Supervisor aprueba
   - Inventario debe reducirse con FIFO

5. **Verificaciones:**
   - ✅ Inventario Bodega Norte = 116 L (200 - 84)
   - ✅ Inventario Finca El Paraíso = 0 L (salida, no recepción en finca)
   - ✅ Movimiento de salida registrado
   - ✅ Alert de stock bajo si 116 < min_stock
   - ✅ Salida status = 'approved'

---

### 🎯 ESCENARIO 3: MÚLTIPLES UBICACIONES Y TRASLADOS

**Objetivo:** Verificar que inventario se actualiza correctamente por ubicación

**Pasos:**

1. **Estado Inicial:**
   - Bodega Central: 270 kg NPK (del Escenario 1)
   - Bodega Norte: 116 L Glifosato (del Escenario 2)
   - Finca El Paraíso: 0 inventario

2. **Compra directa a Finca La Esperanza**
   - Producto: 100 kg Urea
   - Ubicación: Finca La Esperanza
   - Recepción: 100 kg good

3. **Salida de Bodega Central a Finca El Paraíso**
   - Producto: 50 kg NPK
   - Cantidad con 5%: 52.5 kg
   - Aprobar salida

4. **Crear Recepción en Finca El Paraíso**
   - Source: Salida anterior (output)
   - Recibir 52.5 kg
   - Condición: good

5. **Verificaciones:**
   - ✅ Bodega Central: 217.5 kg NPK (270 - 52.5)
   - ✅ Bodega Norte: 116 L Glifosato (sin cambios)
   - ✅ Finca La Esperanza: 100 kg Urea
   - ✅ Finca El Paraíso: 52.5 kg NPK
   - ✅ Total NPK sistema: 270 kg (217.5 + 52.5)
   - ✅ Movimientos correctos por ubicación

---

### 🎯 ESCENARIO 4: VALIDACIONES Y ERRORES

**Objetivo:** Probar que validaciones previenen errores

**Pasos:**

1. **Intentar Sobrerrecepción**
   - Compra de 100 kg
   - Ya recibidos: 80 kg
   - Intentar recibir: 50 kg (excede lo ordenado)
   - ✅ Debe fallar con error claro

2. **Intentar Salida sin Inventario**
   - Producto con 0 stock
   - Intentar crear salida de 100 kg
   - ✅ Validación debe detectar insuficiencia

3. **Intentar Aprobar Salida sin Stock Suficiente**
   - Crear salida cuando hay stock
   - Aprobar otra salida del mismo producto primero
   - Intentar aprobar la segunda
   - ✅ Debe fallar en approve() por falta de inventario

4. **Validar Regla del 5%**
   - Cantidad solicitada: 100 kg
   - Cantidad a entregar: 106 kg (6% excede)
   - ✅ Debe fallar validación

---

### 🎯 ESCENARIO 5: BÚSQUEDAS Y CONSULTAS

**Objetivo:** Verificar APIs de búsqueda y consulta

**Pasos:**

1. **Búsqueda de Productos con Inventario**
   - POST /api/products/search-with-inventory
   - Location: Bodega Central
   - Search: "NPK"
   - ✅ Debe retornar NPK con 217.5 kg disponibles
   - ✅ Debe mostrar lotes FIFO ordenados

2. **Productos Pendientes de Recepción**
   - Crear compra de 3 productos
   - Recibir 2 completos, 1 parcial
   - GET /api/receptions/{id}/pending-products
   - ✅ Debe retornar solo el producto parcial

3. **Validación de Inventario Pre-Salida**
   - POST /api/product-outputs/validate-inventory
   - Solicitar 300 kg NPK de Bodega Central (tiene 217.5)
   - ✅ Debe retornar valid=false, deficit=82.5 kg

4. **Movimientos de Inventario (Kardex)**
   - GET /api/inventory/movements
   - Filtrar por producto NPK
   - ✅ Debe mostrar todas las entradas y salidas
   - ✅ Balance debe coincidir con inventario actual

---

### 🎯 ESCENARIO 6: FIFO Y VENCIMIENTOS

**Objetivo:** Verificar que FIFO funciona correctamente

**Pasos:**

1. **Crear 3 Lotes con Diferentes Vencimientos**
   - Lote 1: 100 kg NPK, vence 2025-12-31
   - Lote 2: 100 kg NPK, vence 2026-06-30
   - Lote 3: 100 kg NPK, vence 2026-12-31

2. **Crear Salida de 150 kg**
   - Aprobar salida
   - ✅ Debe consumir Lote 1 completo (100 kg)
   - ✅ Debe consumir 50 kg del Lote 2
   - ✅ Lote 1 eliminado (cantidad = 0)
   - ✅ Lote 2 restante: 50 kg
   - ✅ Lote 3 intacto: 100 kg

3. **Verificar Alert de Vencimiento**
   - Crear lote con vencimiento en 20 días
   - ✅ Observer debe crear alert automática
   - ✅ Severity = 'high' (< 30 días)

---

### 🎯 ESCENARIO 7: OBSERVERS Y ALERTAS

**Objetivo:** Verificar sincronización automática

**Pasos:**

1. **Verificar ReceptionBatchObserver**
   - Crear lote de recepción
   - ✅ Inventario debe actualizarse sin llamadas adicionales
   - ✅ Alert de vencimiento creada si aplica
   - ✅ Logs en laravel.log

2. **Verificar ProductOutputObserver**
   - Aprobar salida que deja stock bajo
   - ✅ Alert de stock bajo creada automáticamente
   - ✅ Severity según nivel de criticidad

3. **Verificar InventoryMovementObserver**
   - Crear movimiento
   - ✅ Validación de consistencia ejecutada
   - ✅ Auto-corrección si hay discrepancia

---

## 📊 MATRIZ DE PRUEBAS

| # | Escenario | Módulos | Endpoints | Validaciones | Observers |
|---|-----------|---------|-----------|--------------|-----------|
| 1 | Recepción Parcial | Compras, Recepciones | 8 | Sobrerrecepción | Reception |
| 2 | Recepción Total + Salida | Compras, Recepciones, Salidas | 10 | Regla 5%, Stock | Output |
| 3 | Múltiples Ubicaciones | Todos | 15 | Stock por ubicación | Todos |
| 4 | Validaciones | Todos | 8 | Todas | - |
| 5 | Búsquedas | Inventario | 5 | Cantidades reales | - |
| 6 | FIFO | Salidas | 6 | Orden vencimiento | Movement |
| 7 | Observers | Todos | 12 | Alertas automáticas | Todos |

---

## 🎯 ENDPOINTS A PROBAR (50+)

### Autenticación (4)
- [x] POST /api/auth/login
- [x] POST /api/auth/logout
- [x] POST /api/auth/refresh
- [x] GET /api/auth/me

### Compras (8)
- [ ] GET /api/purchases
- [ ] POST /api/purchases
- [ ] GET /api/purchases/{id}
- [ ] PUT /api/purchases/{id}
- [ ] DELETE /api/purchases/{id}
- [ ] POST /api/purchases/{id}/attachments
- [ ] DELETE /api/purchases/{id}/attachments/{attachmentId}
- [ ] GET /api/purchases?status=ordered

### Recepciones (8)
- [ ] GET /api/receptions
- [ ] POST /api/receptions
- [ ] GET /api/receptions/{id}
- [ ] GET /api/receptions/{id}/batches
- [ ] **GET /api/receptions/{id}/pending-products** ⭐ NUEVO
- [ ] POST /api/receptions/{id}/batches
- [ ] PUT /api/receptions/{id}/complete
- [ ] PUT /api/receptions/{id}/cancel

### Salidas (8)
- [ ] GET /api/product-outputs
- [ ] POST /api/product-outputs
- [ ] GET /api/product-outputs/{id}
- [ ] **POST /api/product-outputs/validate-inventory** ⭐ NUEVO
- [ ] PUT /api/product-outputs/{id}
- [ ] DELETE /api/product-outputs/{id}
- [ ] POST /api/product-outputs/{id}/approve
- [ ] POST /api/product-outputs/{id}/mark-in-transit

### Inventario (8)
- [ ] GET /api/inventory
- [ ] GET /api/inventory/{productId}
- [ ] GET /api/inventory/location/{locationId}
- [ ] GET /api/inventory/product/{productId}/details
- [ ] GET /api/inventory/movements
- [ ] GET /api/inventory/movements/product/{productId}
- [ ] POST /api/inventory/adjustments

### Productos (6)
- [ ] GET /api/products
- [ ] **POST /api/products/search-with-inventory** ⭐ NUEVO
- [ ] POST /api/products
- [ ] GET /api/products/{id}
- [ ] PUT /api/products/{id}
- [ ] DELETE /api/products/{id}

### Ubicaciones (7)
- [ ] GET /api/locations
- [ ] POST /api/locations
- [ ] GET /api/locations/{id}
- [ ] PUT /api/locations/{id}
- [ ] DELETE /api/locations/{id}
- [ ] GET /api/locations/type/warehouses
- [ ] GET /api/locations/type/farms

### Alertas (5)
- [ ] GET /api/alerts
- [ ] GET /api/alerts/{id}
- [ ] POST /api/alerts
- [ ] PUT /api/alerts/{id}/resolve
- [ ] PUT /api/alerts/{id}/dismiss

---

## 📝 DATOS DE PRUEBA

### Usuarios
```json
{
  "admin": {
    "email": "admin@agriflor.com",
    "password": "Admin123!"
  },
  "bodeguero": {
    "email": "bodega@agriflor.com",
    "password": "Bodega123!"
  },
  "supervisor": {
    "email": "supervisor@agriflor.com",
    "password": "Super123!"
  }
}
```

### Ubicaciones Disponibles
- Bodega Central (warehouse)
- Bodega Norte (warehouse)
- Finca El Paraíso (farm)
- Finca La Esperanza (farm)
- Finca Los Naranjos (farm)

### Productos Disponibles
- NPK 10-20-20 (fertilizante) - Marca: Yara
- Urea 46% (fertilizante) - Marca: Yara
- Glifosato 480 (herbicida) - Marca: Bayer
- Mancozeb 80% (fungicida) - Marca: BASF

---

## ✅ CRITERIOS DE ÉXITO

### Funcionalidad
- [x] Todas las compras se crean correctamente
- [ ] Recepciones parciales actualizan cantidades pendientes
- [ ] Recepciones totales completan en un solo lote
- [ ] Salidas reducen inventario con FIFO
- [ ] Inventario por ubicación es independiente
- [ ] Movimientos de inventario se registran correctamente

### Validaciones
- [ ] No se puede recibir más de lo ordenado
- [ ] No se puede crear salida sin stock
- [ ] Regla del 5% se respeta
- [ ] Validación pre-salida detecta faltantes

### Observers
- [ ] Inventario se actualiza automáticamente en recepciones
- [ ] Alertas de stock bajo se crean al aprobar salidas
- [ ] Alertas de vencimiento se crean en recepciones
- [ ] Discrepancias se auto-corrigen

### Integridad
- [ ] Todas las operaciones usan transacciones DB
- [ ] Si falla algún paso, todo hace ROLLBACK
- [ ] Cantidades siempre son consistentes
- [ ] No hay registros huérfanos

---

## 🚀 ORDEN DE EJECUCIÓN

1. ✅ Verificar Docker containers
2. ✅ Login como Admin
3. ✅ Obtener IDs de ubicaciones
4. ✅ Obtener IDs de productos
5. ✅ Obtener IDs de proveedores
6. 📋 Ejecutar Escenario 1 (Recepción Parcial)
7. 📋 Ejecutar Escenario 2 (Recepción Total)
8. 📋 Ejecutar Escenario 3 (Múltiples Ubicaciones)
9. 📋 Ejecutar Escenario 4 (Validaciones)
10. 📋 Ejecutar Escenario 5 (Búsquedas)
11. 📋 Ejecutar Escenario 6 (FIFO)
12. 📋 Ejecutar Escenario 7 (Observers)
13. 📊 Generar reporte de resultados

---

## 📄 DOCUMENTOS GENERADOS

1. **PLAN_PRUEBAS_INVENTARIO.md** (este documento)
2. **AgriFlor_API_Collection_v2.json** (Postman actualizada)
3. **RESULTADOS_PRUEBAS.md** (resultados de ejecución)
4. **LOGS_PRUEBAS.txt** (logs de Laravel durante pruebas)

---

**Preparado por:** Claude Code
**Fecha:** 2025-11-17
**Estado:** Listo para ejecución
