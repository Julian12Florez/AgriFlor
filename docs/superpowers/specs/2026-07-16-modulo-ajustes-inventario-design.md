# Módulo de Ajustes de Inventario — Diseño (Spec)

**Fecha:** 2026-07-16
**Autor:** JulianFlorez12 + Claude
**Estado:** Aprobado (diseño)

## Problema

Hoy no existe forma segura de corregir errores de stock (ej.: una compra recibida dos veces por error, o una salida recepcionada de más). El stock solo se muta en 3 lugares (recepción, aprobación de aplicación, y un endpoint de ajuste roto/huérfano `POST /inventory/adjustments` que solo toca un lote ficticio `'MANUAL'`, sin FIFO, sin conversión de unidades, sin costo promedio, sin enlace a documento y sin UI). Ningún documento (compra/recepción/salida/aplicación) revierte inventario al cancelarse/eliminarse. Se necesita un **módulo de ajustes** con **solicitud → aprobación**.

## Objetivo

Permitir que **cualquier rol** cree una **solicitud de ajuste** de stock de un producto (con **tipo** y **motivo**), que queda **pendiente** y **solo se aplica al inventario cuando un admin la aprueba**. El admin también puede rechazarla. Al aprobar, se materializa el movimiento compensatorio y se actualiza el stock; los informes quedan correctos.

## Decisiones de diseño (confirmadas con el usuario)

1. **Paradigma:** ajuste manual con tipo + motivo (NO reversa automática de documentos; NO toca los documentos originales).
2. **Tipos** (cada uno aplica su lógica de stock):
   - **Entrada** (`entry`): SUBE stock en una ubicación destino (lote existente con costo promedio ponderado, o nuevo lote `AJU-…`).
   - **Salida** (`exit`): BAJA stock de una ubicación origen (por FIFO o de un lote específico elegido), al costo del lote.
   - **Transferencia** (`transfer`): mueve stock de origen a destino (salida en origen + entrada en destino, mismo costo).
3. **Motivo:** catálogo predefinido (`adjustment_reasons`) + nota libre opcional.
4. **Cantidad:** modo **delta** (±) o **valor absoluto** (fija un lote a X; el sistema calcula la diferencia). Absoluto solo para Entrada/Salida sobre un lote específico; Transferencia solo delta.
5. **Flujo de aprobación OBLIGATORIO:** `pending → approved (aplica stock) | rejected`. Crear = cualquier rol autenticado. **Aprobar/Rechazar = solo admin (rol exacto).** El stock se materializa únicamente al aprobar.
6. **Permisos:** crear = cualquier autenticado (respeta aislamiento por ubicación para lo que ve/solicita). Aprobar/rechazar = `role:admin`.
7. **Auditoría:** el modelo cabecera `Adjustment` implementa `Auditable` (owen-it) → queda en la tabla `audits`. Además, guarda `responsible_user` (solicitante), `approved_by`, `approved_at`.
8. **`movement_date` obligatorio** en la solicitud (fecha real del movimiento; alimenta los informes, coherente con el campo ya existente).

## Restricciones técnicas (del análisis de código)

- **NO usar `type='adjustment'`** en `inventory_movements`: el enum de la BD es `['entry','exit','transfer','application']` (sin `adjustment`) y varios reportes no lo cuentan. Los ajustes se registran como `entry`/`exit`.
- **Clasificación en `monthlyReport`** (`InventoryController.php` ~1454-1529): 
  - `purchases`: `type='entry'` con `related_document_type LIKE '%Reception'/'%Purchase'` **o NULL**. → El ajuste DEBE setear `related_document_type='App\Models\Adjustment'` para NO contarse como compra.
  - `increases`: `type='entry'` + `observations LIKE '%aumento%'` o `'%ajuste%positiv%'`.
  - `decreases`: `type IN ('exit','application')` + `observations LIKE '%disminuc%'` o `'%ajuste%negativ%'`.
  - → Las `observations` del movimiento de ajuste deben incluir las palabras clave. **Además** se endurece el reporte para clasificar por `related_document_type='App\Models\Adjustment'` (robusto, no dependiente del texto).
- **Costo:** aumentar stock usa **costo promedio ponderado** (patrón de `ReceptionController::updateInventoryStock`). Se extrae a `InventoryService::addStock()` para reutilizar. Reducir usa el costo del lote (FIFO).
- **Conversión de unidades:** `InventoryService::toBaseUnit/fromBaseUnit` (soporta unidades de empaque). El ajuste acepta unidades de empaque, no solo base.
- **FIFO borra lotes agotados** (`InventoryService::reduceInventoryFIFO` hace `delete()` a lotes en 0) — comportamiento existente, aceptable para ajustes.
- **Aislamiento por ubicación:** reusar `User::canViewAllLocations()` / `managedLocationIds()` (supervisor/farm solo sus ubicaciones).
- **Ruta:** usar `permission:adjust_inventory` para crear (o abierto a autenticados) y `role:admin` para aprobar/rechazar. (El endpoint viejo queda deprecado.)

## Modelo de datos

### Tabla `adjustment_reasons` (catálogo)
| Campo | Tipo | Notas |
|---|---|---|
| id | uuid pk | |
| code | string unique | ej. `compra_doble` |
| name | string | ej. "Compra/recepción doble" |
| direction | enum(`any`,`entry`,`exit`,`transfer`) | filtra motivos por tipo |
| active | boolean default true | |
| timestamps | | |

Seeder: `error_captura`(any), `conteo_fisico`(any), `merma_dano`(exit), `vencimiento`(exit), `robo_perdida`(exit), `devolucion`(any), `compra_doble`(exit), `salida_erronea`(entry), `traslado_interno`(transfer), `ajuste_inicial`(entry).

### Tabla `adjustments` (documento cabecera, auditable)
| Campo | Tipo | Notas |
|---|---|---|
| id | uuid pk | |
| adjustment_number | string unique | `AJU-YYYYMMDD-XXXX` |
| type | enum(`entry`,`exit`,`transfer`) | |
| reason_id | uuid fk adjustment_reasons | |
| notes | text nullable | nota libre |
| product_id | uuid fk | |
| brand_id | uuid fk | |
| unit | string | unidad ingresada (empaque o base) |
| quantity_mode | enum(`delta`,`absolute`) | |
| quantity | decimal(12,2) | valor ingresado |
| quantity_base | decimal(12,2) nullable | delta aplicado en unidad base (se fija al aprobar) |
| origin_location_id | uuid nullable fk | requerido para exit/transfer |
| destination_location_id | uuid nullable fk | requerido para entry/transfer |
| batch_number | string nullable | lote específico; null=FIFO(exit)/nuevo(entry) |
| unit_price | decimal(12,2) nullable | costo (solo entry) |
| movement_date | date | fecha real del movimiento |
| status | enum(`pending`,`approved`,`rejected`,`cancelled`) default `pending` | |
| responsible_user | uuid fk users | solicitante |
| approved_by | uuid nullable fk users | admin que aprueba |
| approved_at | timestamp nullable | |
| rejection_reason | text nullable | |
| timestamps | | |

## Comportamiento al APROBAR (materialización del stock)

Dentro de `DB::transaction` + `lockForUpdate` sobre los lotes involucrados, según `type` y `quantity_mode`:

- **Resolver delta base:**
  - `delta`: `quantity_base = toBaseUnit(quantity, unit, product)`.
  - `absolute` (solo entry/exit, requiere `batch_number`): `current = ` cantidad base del lote; `target = toBaseUnit(quantity, unit, product)`; `delta = target - current`. Entrada exige `delta >= 0`; Salida exige `delta <= 0` (se aplica `abs(delta)` como salida). Si el signo contradice el tipo → error 422 al aprobar.
- **Entrada:** `InventoryService::addStock(product, brand, destination, deltaBase, unitPrice, batchNumber ?? 'AJU-{id8}')` con promedio ponderado. Crea `InventoryMovement type='entry'`, `related_document_type='App\Models\Adjustment'`, `related_document_id=adjustment.id`, `movement_date`, `observations = "[AUMENTO / ajuste positivo] {reason.name}: {notes}"`.
- **Salida:** `InventoryService::reduceInventoryFIFO(product, brand, origin, deltaBase, unit, batchNumber)`. Crea `InventoryMovement type='exit'`, ligado al ajuste, `observations = "[DISMINUCIÓN / ajuste negativo] {reason.name}: {notes}"`. Valida stock suficiente (excepción → 422 con mensaje claro).
- **Transferencia:** salida en origen (FIFO/lote) + entrada en destino al mismo costo. Dos movimientos ligados al ajuste (observaciones "traslado por ajuste").
- Setea `status='approved'`, `approved_by=auth()->id()`, `approved_at=now()`, `quantity_base=deltaBase`.

**Crear** solo valida forma + muestra stock disponible como referencia; **NO** toca inventario. La validación dura de stock ocurre **al aprobar** (con lock), porque el stock pudo cambiar.

## Integración con informes (verificación obligatoria)

Tras aprobar, se verifica que:
1. **Inventario Mensual** (`monthlyReport`): un ajuste positivo cae en `increases` (no en `purchases`); uno negativo en `decreases`; `initial/final` derivados de movimientos cuadran con `inventory`; `variation = 0`.
2. **Kardex** (`movements`, `movementsReport`, `productKardex`): el movimiento aparece con su tipo, fecha (`movement_date`), observaciones y ligado al ajuste (`related_document_type='App\Models\Adjustment'`).
3. **Stock actual** (`inventory`) = suma de lotes tras el ajuste.
4. **Reportes de stock / product-listing / farm**: reflejan el nuevo stock.
Endurecimiento: `monthlyReport` clasifica `increases/decreases` también por `related_document_type='App\Models\Adjustment'` (además del texto), para robustez.

## Backend (archivos)
- Migraciones: `create_adjustment_reasons_table`, `create_adjustments_table`.
- Seeder: `AdjustmentReasonSeeder` (+ registrar en `DatabaseSeeder`).
- Modelos: `Adjustment` (Auditable, `generateAdjustmentNumber()`, relaciones), `AdjustmentReason`.
- Servicio: `InventoryService::addStock(...)` (extraído del patrón de recepción, promedio ponderado).
- Request: `StoreAdjustmentRequest`, `RejectAdjustmentRequest`.
- Controller: `AdjustmentController` (index, store, show, approve, reject, cancel).
- Resource: `AdjustmentResource` (dual naming).
- Rutas: grupo en `routes/api.php`.
- Endurecimiento: `InventoryController::monthlyReport` (clasificación por related_document_type).

## Frontend (archivos)
- `services/api.ts`: `adjustmentsApi` (+ `adjustmentReasonsApi`).
- `pages/inventory/Adjustments.tsx`: lista + modal de solicitud + (para admin) cola de pendientes con Aprobar/Rechazar.
- `components/layout/MainLayout.tsx`: entrada de menú bajo "Inventario".
- Router (`App.tsx`): ruta `/inventory/adjustments`.
- Tipos en `types/index.ts`.

## Permisos
- Crear/ver: cualquier autenticado con acceso a inventario (aislamiento por ubicación aplica).
- Aprobar/Rechazar: `role:admin` estricto.

## Fuera de alcance (v1)
- Reversa automática de documentos.
- Multi-producto por solicitud (una solicitud = un producto).
- Notificaciones push al admin (se usa cola/badge de pendientes; notificación = mejora futura).
- Flujo de doble aprobación.

## Riesgos
- Clasificación de reportes por texto: mitigado con palabras clave + clasificación por `related_document_type`.
- Costo promedio ponderado en entradas: reusar patrón probado de recepción (extraído a `InventoryService`).
- FIFO borra lotes: al reducir, si el lote específico no existe (fue borrado), error claro; el usuario elige otro lote/FIFO.
- Concurrencia: `lockForUpdate` al aprobar + re-validación evita sobre-descuento.
