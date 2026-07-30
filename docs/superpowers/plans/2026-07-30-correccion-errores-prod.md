# PLAN CONSOLIDADO DE CORRECCIÓN — AgriFlor (E1–E5 + módulo de Ajustes)

## 0. Estado real de la investigación (honestidad primero)

| Hallazgo | Analista | Estado |
|---|---|---|
| E2 remanentes contados como Compras | entregó | **REPRODUCIDO** con datos de prod (copia `agriflor_bug`). Causa raíz confirmada con SQL que cierra al céntimo. |
| E3/E4 fechas de junio caen en julio | entregó | **REPRODUCIDO**. Un solo bug, firma perfecta (38 entries mal fechados, 0 exits). Casos del cliente resueltos con números. |
| E5 disponible vs saldo del kardex | entregó | **REPRODUCIDO EXACTO** (10.525,00 vs 7.075,50). Atribución 1:1 a 11 movimientos huérfanos. |
| E1 columna Destino muestra fecha | **NO entregó nada (null)** | Sin análisis. **Yo hice una verificación de código directa** (abajo): causa encontrada y verificada en el código, **NO verificada en navegador con datos reales**. |
| Validación del módulo de Ajustes | **NO entregó nada (null)** | **HUECO ABIERTO.** No hay validación funcional del módulo recién desplegado. Mitigación parcial disponible (sección 4.6): dos analistas distintos lo auditaron de pasada y ambos lo encontraron correcto, y `adjustments` tiene **0 filas en producción**, así que hoy no puede haber daño en datos por ese módulo. |

Cosas que los analistas **desmintieron** y que no hay que perseguir:
- **La captura de E2 no se reproduce numéricamente** (Compras 92,9 no existe en ninguna ubicación × mes; los totales de las tarjetas tampoco cuadran). La captura es de un despliegue anterior. El *comportamiento*, el producto, la bodega y el mes sí se reproducen. El código del producto real es **1390**, no 1380.
- **E5 no son 3.999,50 kg sino 3.449,50 kg**: el cliente omitió al sumar un lote de 550,00 kg del desplegable. No hay tercer factor.
- El detalle del kardex transcrito en el triage de E5 (20 movimientos, 2.288, 28/03/2026…) es **ruido de OCR**, no un dato divergente.
- Hipótesis descartadas con evidencia en E5: unidades sin convertir, lotes borrados por FIFO, stock comprometido, y movimientos de importación sin contraparte. Ninguna explica la divergencia.
- Hipótesis del triage de E2 (`related_document_type LIKE '%Reception'` sin mirar `source_type`): **CONFIRMADA**.

Verificaciones que hice yo mismo sobre el código antes de firmar este plan (todas positivas):
- `backend/app/Http/Controllers/Api/ReceptionController.php:1784-1795` — la llamada termina en `$itemData['condition']`, **falta el 11º argumento**; la firma en `:1894-1905` declara `?string $movementDate = null` y `:1922` resuelve `$movementDate ?: now()->toDateString()`. Confirmado.
- `backend/app/Http/Controllers/Api/InventoryController.php` — el bloque `purchases` filtra `%Reception` / `%Purchase` / `NULL` sin mirar `receptions.source_type`; `$returns` viene de `$remanenteByProduct`; `total_movements` los suma ambos. Confirmado.
- `frontend/src/pages/inventory/Inventory.tsx:643-648` rotula **"Saldo Actual" = `current_balance`** y `frontend/src/pages/reports/InventoryKardex.tsx:507-508` rotula **"Stock Actual" = `current_stock`**; el endpoint devuelve ambos (`InventoryController.php:853-854`). Confirmado.
- `app/Exports/MonthlyInventoryExport.php:115` — `$lastCol = 8 + count($this->farmColumns) + 1 + 6;` es exactamente lo que reportó E2 (hay 6 columnas tras "TRASLADOS SALIENTES"). Confirmado: añadir columna obliga a `+ 7`.

### E1 — causa encontrada por mí (código verificado, no verificada en navegador)
`frontend/src/pages/outputs/Outputs.tsx:584-596`, columna "Destino":
```tsx
{record.farmName}                                  // línea 590  -> undefined
{dayjs(record.outputDate).format('DD/MM/YYYY')}    // línea 593  -> lo único que se ve
```
`farmName` **no existe en ninguna parte del payload**: `app/Http/Resources/ProductOutputResource.php` emite `destinationLocation.{id,name,type,municipality}` y `destinationLocationId`, nunca `farmName`. Tampoco lo añade ningún transform del frontend (grep completo: solo aparece en `Outputs.tsx:590` y `:679`, más un `farmNames` no relacionado de órdenes técnicas). Y el tipo `ProductOutput` (`frontend/src/data/types.ts:124-140`) declara `destinationLocationName`, **no** `farmName`: es además un error de tipos que no revienta el build porque el CI corre `vite build`, no `tsc` (nota de memoria del proyecto). Resultado: la columna "Destino" renderiza vacío arriba y la fecha abajo → **"la columna Destino muestra una fecha"**. El mismo bug está en el detalle: `Outputs.tsx:679` (`<Descriptions.Item label="Destino">{record.farmName}`).

---

## 1. Triage priorizado por impacto de negocio

| # | Hallazgo | Severidad | Naturaleza | Por qué está en esta posición |
|---|---|---|---|---|
| **P0-a** | **E3/E4** — la pata de ENTRADA de traslados/salidas se fecha con `now()` | **BLOQUEANTE** | **Regresión reciente** (nace con el despliegue de `movement_date`, commit `75e36bf`, 2026-07-16; invisible hasta la carga retroactiva del cliente del 28-30/07) | Es el único que **corrompió datos**. 38 movimientos mal fechados, 31 cruzan de mes. Rompe el cierre mensual que el cliente concilia: junio |Variación| 39.146,94 fantasma y julio "Enviado" 40.833,31 fantasma en BODEGA PRINCIPAL. Re-fechar elimina el **99,5% del descuadre de junio y el 93,8% del de julio**. |
| **P0-b** | **E2** — remanentes/envíos recibidos clasificados como "Compras" | **BLOQUEANTE** | **Defecto histórico** (el filtro `%Reception` existe desde que se corrigió la comparación de namespace; se hizo visible al empezar a recibir remanentes) | Es la **columna que el cliente concilia contra contabilidad**. Hoy hay 756 movimientos / 372.486,74 unidades / **$947.776.147** mal etiquetados como Compras; en la bodega de julio 2.708,25 (+3,16%); si el usuario selecciona una finca, 203.937,83 unidades en julio son 100% envíos recibidos, 0% compras. **No hay datos corruptos**: se calcula en lectura, se corrige retroactivamente solo con desplegar. |
| **P1-a** | **E5** — `inventory` (físico) vs saldo del kardex (libro) divergen 3.449,50 kg | **IMPORTANTE** (bloqueante para reparar datos, no para desplegar código) | **Defecto histórico acotado**: lo generaron las 3 migraciones de conciliación de mayo (`2026_06_09_130000`, `2026_06_10_120000`, `2026_06_16_120000`) con el guard `if (!$hasJune)` | 7 de 585 pares producto-ubicación, todos en BODEGA PRINCIPAL, atribuibles 1:1 a 11 movimientos sin contraparte física. El **stock despachable es correcto** (el del selector): no hay riesgo operativo inmediato. La reparación de datos **está bloqueada por una decisión de negocio** (¿manda Siigo o mandan los lotes?). La parte que resuelve la queja del cliente (dos pantallas del mismo kardex con rótulos distintos) es **frontend puro y sin riesgo**. |
| **P1-b** | **Reapertura silenciosa de meses cerrados** (colateral, detectado por E5) | **IMPORTANTE** | Regresión de comportamiento habilitada por `movement_date` (2026-07-16) | Una compra cargada el 29/07 con `movement_date` 2026-05-29 dejó el cierre de mayo de QROP KS en **45.050 kg contra los 34.550 alineados a Siigo**. Rompe en silencio el único mes que el cliente ya conció con Contabilidad. Sin candado, cualquier fix de fechas se puede volver a desalinear mañana. |
| **P2-a** | **E5/FIX-2** — el selector de Nueva Salida no descuenta stock comprometido | **IMPORTANTE** | Defecto histórico | `ProductController.php:243-248` ofrece más de lo que `ProductOutputController::store:142-219` acepta → 422 "Stock insuficiente" al guardar. Hoy afecta 2 productos (ABAMECTINA 6 L, KENDO 6 L). No es la causa de E5 pero es un bug real que el usuario vivirá como tal. |
| **P2-b** | **E5/FIX-6** — `InventoryService::toBaseUnit` nunca convierte | **IMPORTANTE-latente, NO tocar en esta tanda** | Defecto histórico | Busca la unidad por **nombre de presentación** en `packaging_units`; ninguna unidad real ('kg','g','L','cm','mL') coincide (JOIN: 0 coincidencias) → es la identidad para el 100% de las filas. 25 movimientos y 10 lotes de 6 productos se suman sin convertir (el peor: 1592 MOLIB-K, base g con un movimiento de 40.500 kg). **Debe ir en su propio despliegue**: mezclarlo haría indistinguibles sus descuadres de los de E2/E3/E4. |
| **P2-c** | **Saldo de libro negativo** — 1124 COMBATRAN XT: −4,00 L con físico 0 | **MENOR pero absurdo** | Defecto histórico (uno de los 11 huérfanos) | Un saldo negativo es imposible por definición; el kardex lo muestra. Reparación pequeña y segura en cualquier escenario de la Decisión 4. |
| **P3-a** | **E1** — columna "Destino" de Salidas muestra solo la fecha | **MENOR (cosmético)** | Defecto histórico (el payload nunca tuvo `farmName`) | No afecta ningún número. Fix de 2 líneas. El aprendizaje sistémico sí importa: el CI no corre `tsc`, así que este tipo de error nunca se detecta. |
| **P3-b** | `closeOutputReception` (`ReceptionController:1131-1250`) fecha todo con `now()` | **MENOR** | Defecto histórico | Mismo tipo que E3/E4 pero **0 filas de producción** (reemplazado por `finalize`). Sigue expuesto en `routes/api.php:303`. |
| **P3-c** | `ImportController.php:391,:480` crean movimientos sin `movement_date` | **MENOR-latente** | Defecto histórico | Una re-importación de la carga inicial fecharía todo en el mes en curso. Hoy inerte. |
| **P3-d** | "Compras" de abril incluye la carga inicial | **MENOR (rótulo)** | Por diseño del filtro (`related_document_type NULL`) | 1.071.154,42 de los 1.071.529,82 de abril son carga inicial Excel/Siigo; solo 375,40 son compras reales. **No es un bug, es un rótulo engañoso**: avisar al cliente antes de que lo lea como compras. |

**Descuadres que NO son ninguno de estos cinco errores y que no hay que prometer arreglar:** el descuadre de **mayo 2026** en BODEGA PRINCIPAL (24 productos, |Variación| 275.559,18) es preexistente y quedó **idéntico** antes y después de todas las simulaciones. Merece su propia investigación.

---

## 2. Plan de corrección de CÓDIGO (en orden de ejecución)

### PR-1 — E3/E4: una sola fecha por documento (BLOQUEANTE, va primero)
Debe desplegarse **antes** de cualquier migración de datos: si no, cada traslado nuevo sigue naciendo mal fechado.

1. `backend/app/Http/Controllers/Api/ReceptionController.php:1784-1795` → añadir `$movementDate` como 11º argumento de `createEntryMovement`. **Este es el fix, 1 argumento.**
2. **Blindaje anti-recaída** (recomendado, es la razón estructural del bug): quitar el default `= null` de `$movementDate` en `createEntryMovement` (`:1905`) y `createExitMovement` (`:1969`), hacerlo `string $movementDate` obligatorio, y resolver el fallback **una sola vez** al principio de `processInventoryMovements` (`:1709-1716`). Así un olvido futuro falla en tiempo de ejecución en vez de fechar en silencio. *Riesgo:* hay que actualizar **todos** los call sites (`:1749`, `:1777`, `:1794`, y `:1204` en `closeOutputReception`) — grep obligatorio antes de commitear, si queda uno fuera el endpoint revienta con `ArgumentCountError`.
3. `ReceptionController.php:1840` → `'application_date' => $movementDate ?: now()->toDateString()`. *Riesgo:* bajo (1 salida de consumo en prod).
4. `ReceptionController.php:1162` y `:1204` (`closeOutputReception`) → propagar la fecha. *Riesgo:* nulo en datos (0 filas), pero decide antes si esa ruta se retira (Decisión 10).
5. **NO tocar**: `AdjustmentController.php:1105-1117` (correcto, misma fecha en ambas patas), `ApplicationController.php:381-393` (correcto), `InventoryController.php:329`, `app/Models/InventoryMovement.php:48-50` (el fallback se queda como salvaguarda).

**Riesgo global de PR-1:** que el nuevo `movement_date` correcto caiga en un mes ya conciliado (mayo) — de ahí PR-6. Y que una recepción con varios lotes reciba la fecha del lote equivocado si el fallback se resuelve mal: resolverlo con el `batch_number` en curso, no con `MIN()`.

### PR-2 — E2: clasificación del informe mensual (BLOQUEANTE; cambia números que el cliente concilia)
Depende de nada en código, pero **la verificación numérica depende de que la Migración A ya esté aplicada** (los dos errores hoy se compensan parcialmente; ver 3.4).

1. `InventoryController.php` junto a `ADJUSTMENT_DOCUMENT_TYPE` (`:25`) → `private const RECEPTION_DOCUMENT_TYPE = 'App\\Models\\Reception';` (**literal completo, NO `getMorphClass()`**, por el `enforceMorphMap` del `AppServiceProvider`; misma razón ya documentada en `AdjustmentController:57-68`).
2. **Helper único** que identifica entradas nacidas de la recepción de un documento de SALIDA, filtrando por `receptions.source_type='output'` + `output_types.code`. **Clasificar por el documento, jamás por el texto de las observaciones**: la entrada de un remanente dice literalmente `"Recepción lote #1 - good - Transferencia"` (`ReceptionController:1929-1930`), no dice "Remanente".
3. `InventoryController.php:1462-1471` (`purchases`) → añadir `whereNotExists` sobre `receptions.source_type='output'`. **No tocar el resto del predicado**: `%Purchase` y `IS NULL` deben seguir contando o se pierden compras reales y el histórico importado.
4. `InventoryController.php:1442` y `:1521` (`returns`) → derivarlo del **kardex** con `movement_date`, no de `product_outputs.output_date`. Es lo que hace que `variation` cuadre, porque `variation` se calcula contra movimientos. `outputQtyByDestination` (`:1939-1952`) queda huérfano: borrarlo o marcarlo como no usado. **`farmOutputQtyByProduct` (`:1919-1932`) SIGUE en uso por `farmMonthlyReport`: no tocarlo aquí.**
5. **Nueva columna `shipments_in`** ("Envíos recibidos") para las entradas de recepciones de salida que **no** son remanente. Actualizar `total_movements` (`:1568`) y el filtro de actividad (`:1573`), y exponerla en la fila (`~:1588`).
   **TRAMPA CRÍTICA:** si se quitan de `purchases` **sin** añadir esta columna, esas entradas dejan de estar explicadas por ninguna columna y se convierten en **Variación**: un descuadre nuevo de 203.937,83 unidades en julio en los informes por finca. Los pasos 3 y 5 son atómicos, van en el mismo commit.
6. **Rendimiento (obligatorio, no opcional):** precargar **una vez** los ids de recepciones de salida por tipo (patrón ya usado en `$originExitDocIds`, `:1432`) en vez de repetir el `whereExists` con dos joins dentro del bucle de ~220 productos. El informe ya tarda segundos y el barrido de 20 ubicaciones × 3 meses superó los 300 s.
7. `frontend/src/pages/reports/MonthlyInventoryReport.tsx` → `shipments_in: number` en `ProductRow` (~`:38-42`) y columna nueva junto a "Remanente" (`:199-214`). "Remanente" ya lee `returns`, no cambia.
8. `app/Exports/MonthlyInventoryExport.php` → header `'ENVIOS RECIBIDOS'` (`:51-56`), su valor (`:87-92`) y **`$lastCol` de `+ 6` a `+ 7` (`:115`)** — verificado por mí: si no se toca, el Excel del cliente pierde el estilo de la última columna. `ReportExportController:764` reusa `monthlyReport`, hereda el fix.
9. **NO tocar** (verificado para no romper Ajustes): `whereClassifiedAsAdjustment` (`:1749-1789`), `increases` (`:1544-1551`), `decreases` (`:1558-1565`), `transferExitsNotShippedToFarms` (`:1691-1720`), `shippedDocumentIds` (`:1647-1673`). No hay solape: los ajustes llevan `related_document_type='App\Models\Adjustment'`.

**Riesgo global de PR-2:** cambia la columna Compras de **todos los meses ya conciliados** cuando la ubicación es una finca (may-2026 −57.991,73; jun-2026 −107.424,93; jul-2026 −203.937,83). Hay que avisar al cliente: **el número anterior estaba mal, no el nuevo**.

### PR-3 — E5: separar "stock físico" de "saldo contable" en la UI (IMPORTANTE, riesgo bajo, resuelve la queja)
1. `frontend/src/pages/inventory/Inventory.tsx:641-656` → mostrar `current_stock` como **"Stock Actual (físico)"** (que es lo que ya usan `InventoryKardex.tsx:507-508`, `MonthlyInventoryExport.php:72` y `ProductListingExport.php:46`) y añadir una quinta tarjeta **"Saldo contable (libro)"** con `current_balance` + Tooltip. Cuando `|current_balance − current_stock| > 0.01`, `Alert`/`Tag` con la diferencia. **Cambio 100% frontend**, el endpoint ya devuelve ambos.
2. Nuevo `app/Console/Commands/InventoryLedgerAuditCommand.php`: compara por producto+ubicación `SUM(inventory.quantity)` contra `SUM(entry) − SUM(exit,transfer,application)` y reporta `|dif| > 0.01`. Cubre las 585 combinaciones en una consulta. **Debe existir antes de reparar datos**, es la prueba de "0 divergencias".
3. Regla de proceso: **prohibido el patrón del guard `$hasJune`** en futuras conciliaciones. Si se ajusta el libro, se ajusta el lote; y si el objetivo es solo cuadrar un informe histórico, el movimiento debe llevar marcador explícito (`related_document_type='App\Models\AccountingAdjustment'`) y el kardex debe poder segregarlo.

### PR-4 — E1: columna Destino (MENOR, 5 minutos)
`frontend/src/pages/outputs/Outputs.tsx:590` y `:679` → `record.destinationLocation?.name ?? '-'` (el payload sí lo trae; `destinationLocation` ya se usa en el mismo archivo, `:261-267`). Revisar también el drawer/detalle móvil.
*Riesgo:* mínimo. *Acción sistémica recomendada:* añadir `tsc --noEmit` al CI — este bug es un error de tipos que el build con `vite` no ve.

### PR-5 — E5/FIX-2 y FIX-3: el selector debe ofrecer lo que el backend acepta (IMPORTANTE)
1. `ProductController.php:235-341` (`getForOutputs`) → calcular el comprometido reutilizando **la misma lógica** de `ProductOutputController::store:142-219` (extraerla a `InventoryService` o un `CommittedStockService`, **no duplicarla**), y devolver `quantity` / `committed_quantity` / `available_quantity`, usando el disponible en `display_label`/`short_label`.
2. `ProductController.php:246` → `orderByRaw('expiration_date IS NULL, expiration_date ASC')`: hoy MySQL pone los NULL primero y el lote "Sin vencimiento" de 252,50 kg encabeza la lista por delante del vencido.
*Riesgo:* **el selector ofrecerá menos**. Quien hoy ve 12 L de KENDO verá 6 L y lo reportará como bug nuevo → comunicar y mostrar el desglose físico/comprometido/disponible en el propio desplegable.

### PR-6 — Candado de periodo (IMPORTANTE, requiere Decisión 7)
Bloquear o exigir confirmación explícita cuando un movimiento nuevo lleve `movement_date` dentro de un periodo conciliado (`<= 2026-05-31`). Evidencia del daño: la recepción del 29/07 fechada 29/05 que dejó mayo de QROP KS en 45.050 kg contra 34.550 de Siigo.

### PR-7 — Rider de UX de recepción (obligatorio si se adopta `reception_date`; ver Decisión 1)
`frontend/src/pages/reception/Reception.tsx:335` y `:521` → el campo `receptionDate` arranca en `dayjs()` (hoy). Precargarlo con la fecha del documento origen (`output_date`/`purchase_date`) y validar `reception_date >= output_date` con aviso cuando el desfase cruza de mes.
**Sin este rider el bug se volverá a reportar como "lo puse en junio y quedó en julio" aunque el backend esté perfecto.** Evidencia de que hoy falla: 7 lotes con `reception_date` **anterior** al `output_date` (físicamente imposible) y 16 lotes a más de 31 días.

### Diferidos a su propio ciclo (no meter en esta tanda)
- **FIX-6 `toBaseUnit`** (`app/Services/InventoryService.php:32-42`): cambia saldos de 6 productos. Aislado, con su propia verificación.
- **Alinear `farmMonthlyReport`** (Consumo y Remanente por finca, `InventoryController.php:1819-1820` vía `farmOutputQtyByProduct`) al `movement_date`. Mientras use `output_date`, el informe por finca arrastra la misma desalineación que E2 corrigió en el mensual.
- `ImportController.php:391,:480` y `app/Console/Commands/ImportExcelInventory.php:366,:483`: `movement_date` explícito (fecha del corte).

### Grafo de dependencias
```
PR-1 (código E3/E4)  ──► Migración A (datos, 38 filas)  ──► medición
                                                            │
PR-3.2 (comando auditoría) ─────────────────────────────────┤
                                                            ▼
                                                     PR-2 (E2 informe) ──► medición ──► aviso al cliente
PR-4 (E1)   independiente
PR-3.1 (UI kardex) independiente         Decisión 4 ──► reparación de datos E5
PR-5 (selector) independiente, comunicar   Decisión 7 ──► PR-6
Decisión 1 ──► PR-7 (rider UX)
```

---

## 3. Plan de corrección de DATOS de producción

### 3.1 E2 — no requiere reparación de datos
La clasificación Compras/Remanente se calcula en **tiempo de lectura**; los 37 movimientos son correctos en el kardex (tipo, ubicación, cantidad, unidad, documento). Verificado además: `output_products.quantity_delivered == inventory_movements.quantity` en los 37 casos y ningún producto afectado mezcla unidades, así que el fix no cambia semántica de unidades. Solo con desplegar PR-2, julio pasa de Compras 88.411,85 → 85.703,60 y Remanente 2.527,75 → 2.708,25, y junio pasa de Remanente 180,50 → 0.

### 3.2 E3/E4 — Migración A (la única reparación de datos de esta tanda)
`backend/database/migrations/2026_07_30_120000_realign_transfer_entry_movement_dates.php`, patrón de `2026_06_09_120000_realign_movement_dates_to_document.php`: tabla de respaldo + `UPDATE JOIN` determinista + `down()` que restaura. **Solo toca `movement_date`.** No toca `quantity`, `unit`, `unit_price`, `total_price` ni la tabla `inventory`.

**Selector: usar la FIRMA del bug, nunca la comparación cruda.**
```
type='entry'
AND related_document_type='App\Models\Reception'
AND receptions.source_type='output'
AND movement_date = DATE(created_at)
AND movement_date <> movement_date del EXIT hermano del mismo documento/producto
```
→ selecciona **exactamente 38 filas, todas en 2026-07** (verificado por dos criterios independientes).

**PELIGRO MEDIDO:** el criterio ingenuo (`entry.movement_date <> DATE(reception_batches.reception_date)` sin la firma) selecciona **234 filas**, de las cuales **186 están en MAYO** y 80 cambiarían de mes → **destruiría el cierre de mayo alineado con Siigo**. Prohibido.

Variante recomendada por legibilidad (verificada, mismos 38): emparejar contra `reception_batches` restringiendo a `HAVING COUNT(*) = 1` (las 37 recepciones afectadas son de un solo lote). **Preferirla al emparejamiento por `observations`**: ese texto lleva tildes generadas en `ReceptionController:1935` y `:1995`, y si el literal del `LIKE` no coincide byte a byte el JOIN devuelve 0 filas y **la migración no repara nada sin dar error**.

Detalles operativos:
- Estructura de respaldo: `inventory_movements_md_backup (movement_id, original_movement_date, corrected_movement_date, reason='E3E4_entry_transfer', backed_up_at)`, con `INSERT IGNORE` → **idempotente**; re-ejecutar deja el mismo estado. Tras PR-1, la firma deja de producirse.
- `down()`: restaura `original_movement_date` desde el respaldo. **No borra la tabla de respaldo** (igual que `2026_06_09_120000` conserva `inventory_movements_date_backup`, hoy con 492 filas).
- `inventory_movements` **no tiene `updated_at`**: usar `DB::statement`/`DB::table`, nunca `Model::update()` masivo.
- Entra como **batch 10** (la última aplicada es `2026_07_29_120200_seed_adjustment_reasons`, batch 9).
- **No reparar** los 169 pares pre-despliegue que difieren de la fecha del lote: están simétricos (ambas patas igual) → no descuadran, y re-fecharlos movería mayo/Siigo.

**Verificación ANTES:** `mysqldump` de `inventory_movements`; contar la firma (debe dar 38, todas en 2026-07); guardar el baseline de los informes mensuales de mayo, junio y julio de BODEGA PRINCIPAL.
**Verificación DESPUÉS:** (a) la firma da 0 filas; (b) el respaldo tiene exactamente 38 filas con `original != corrected`; (c) `COUNT(*)` de `inventory_movements` idéntico; (d) `SUM(quantity)` por `type` idéntico; (e) **ningún movimiento con `movement_date <= '2026-05-31'` cambió** — si mayo se mueve, **ABORTAR**; (f) para los 25 productos afectados, Total Entradas / Total Salidas / Saldo Actual del kardex **idénticos** (solo cambia el orden).
**Reversión:** `down()` de la migración, o restaurar `inventory_movements` del dump. Reversión de código: `git revert` de PR-1.

Efecto secundario conocido y aceptado: las filas del kardex con saldo corrido negativo pasan de **18 a 22** (sobre 585 series). No es un dato malo introducido por el fix: son casos donde una entrada se registró después de la salida que la consumió. **Revisar esas 4 con el cliente.**

### 3.3 E5 — BLOQUEADO por la Decisión 4. Nada de SQL antes de esa decisión.
- Los 11 movimientos huérfanos (BODEGA PRINCIPAL, `movement_date` 2026-05-28 salvo uno 2026-04-30, `related_document_type NULL`, observaciones "Corrección de cierre mayo…" / "Ajuste a cierre contable mayo (Siigo)") explican el **100%** de la divergencia de los 7 productos.
- **Opción A (manda Siigo):** materializar el faltante como lote real. **Riesgo: crea stock que puede no existir**; si no existe, el próximo conteo genera una merma de 3.449,50 kg. No hacerlo sin conteo físico.
- **Opción B (mandan los lotes):** revertir los 11 del libro. **PELIGRO:** los `down()` de las tres migraciones borran **por texto de `observations`, sin filtrar por producto** → un `migrate:rollback` revertiría los **62** movimientos de conciliación, no los 11. Si se elige B, **SQL dirigido por producto, nunca `migrate:rollback`**.
- **Opción C (recomendada):** marcar los 11 como `App\Models\AccountingAdjustment`, mostrar "saldo contable" y "stock físico" como dos líneas explícitas, y materializar solo lo que un conteo físico nuevo confirme. **Cero riesgo de crear o destruir stock sin evidencia.**
- **Reparación segura en cualquier escenario:** 1124 COMBATRAN XT, libro −4,00 L con físico 0. Ojo con el método: el estado deseado es libro 0 / físico 0, así que hay que **compensar/anular el movimiento huérfano en el libro** (SQL dirigido con respaldo, patrón de migración correctiva). **NO usar el módulo de Ajustes aquí**: un ajuste de entrada de +4 dejaría libro 0 pero físico 4, es decir crearía stock inexistente.
- Antes de tocar nada: verificar que existen y tienen filas `inventory_corr_org_backup`, `inventory_contab_backup`, `inventory_movements_ajuste_backup` — son la única vía de vuelta.
- **Fijar el baseline de los informes de mayo y junio ANTES**: el cliente ya los revisó, y mayo de 1537 hoy da 45.050 (no 34.550) por la compra retro-fechada del 29/07. Si no se fija el baseline, ese descuadre se le atribuirá al fix.

### 3.4 Orden entre E2 y E3/E4, y por qué importa medir en tres estados
Hoy los dos errores **se compensan parcialmente** (BORAMON pasa de `variation` +50 a +300 al arreglar E2: eso es correcto, separa dos errores que se cancelaban). Además, los 4 remanentes desplazados (SULFATO 36,10 / CAL VIVA 70 / CERTUS 72 / ACUAFIN 2,40 = 180,50) son **el cruce exacto entre E2 y E3/E4**. Por eso:
1. Medir el **baseline** (junio 20 filas / 39.146,94; julio 36 / 40.833,31).
2. Aplicar PR-1 + Migración A → medir (esperado: junio 4 / 180,50; julio 23 / 2.527,75).
3. Aplicar PR-2 → medir de nuevo.
**No pronosticar el número final combinado**: los dos analistas midieron cada fix por separado, ninguno midió la combinación. Se mide en `agriflor_bug`, no se supone.

---

## 4. Plan de pruebas

### 4.1 E3/E4 — `tests/Feature/OutputReceptionMovementDateTest.php` (nuevo)
Patrón de `tests/Feature/AdjustmentReportsConsistencyTest.php`, que ya protege el invariante `variation == 0` con fechas fijas.
Con `Carbon::setTestNow('2026-05-10')`: salida `technical_order` con `output_date` de marzo, recibida con `reception_date='2026-03-20'` →
- **(a)** ambos movimientos (exit en bodega y entry en finca) con `movement_date = '2026-03-20'` ← **falla hoy**
- **(b)** `monthlyReport(mes=3, BODEGA)`: `total_shipped == cantidad`, `variation == 0` ← **falla hoy**
- **(c)** `monthlyReport(mes=5, BODEGA)`: `total_shipped == 0`, `variation == 0` ← **falla hoy**
- **(d)** `farmMonthlyReport(mes=3, FINCA)`: `entries == cantidad`
Cobertura de los 4 tipos: `technical_order`, `transfer`, `free_request` (rama no-consumption, `:1782`) y `consumption` (solo exit + `application_date` == fecha del lote, cubre `:1840`).
No-regresión: recepción de **compra** retro-fechada sigue con `movement_date == reception_date`. Recepción **parcial en dos meses** (`addBatch`, `:887`): cada par exit/entry en su mes y `variation == 0` en ambos.

### 4.2 E2 — `tests/Feature/MonthlyReportRemanenteClassificationTest.php` (nuevo)
- **T1** remanente finca→bodega recibido en el **mismo mes**: `purchases == 0`, `returns == cantidad`, `shipments_in == 0`, `variation == 0` ← hoy: `purchases == cantidad` y `variation == −cantidad`. **Este es el test que captura E2.**
- **T2** remanente con `output_date` en M y lote en M+1: en M → todo 0 y `variation 0` (hoy hay Remanente fantasma); en M+1 → `purchases 0`, `returns == cantidad` (hoy: Compras con Remanente vacío = el caso del cliente).
- **T3** no-regresión: recepción de **compra** → `purchases == cantidad`; y un movimiento histórico con `related_document_type NULL` **debe seguir** cayendo en `purchases`.
- **T4** informe con **FINCA** seleccionada: orden técnica bodega→finca recibida → informe de la finca `purchases == 0`, `shipments_in == cantidad`, `variation == 0`.

### 4.3 E5
- `tests/Feature/InventoryLedgerConsistencyTest.php` (nuevo): insertar un movimiento sin contraparte en `inventory` y assertar que el comando de auditoría lo detecta con la diferencia exacta. **Hoy no existe NINGÚN test que toque `productKardex` ni `getForOutputs`** (verificado con grep por el analista).
- Contrato de `getForOutputs`: `available_quantity == físico − comprometido`; lote con `quantity=0` no aparece; lote vencido **sí** aparece con `is_expired=true` y sufijo `[VENCIDO]` (comportamiento deliberado, comentario en `ProductController.php:242`); lotes sin vencimiento **al final**.
- Coherencia selector ↔ `store`: con la salida `partial` que retiene ABAMECTINA (2970) 6 L / KENDO (8541) 6 L, el selector ya no debe ofrecer esa cantidad y `store()` debe **aceptar** el total ofrecido (hoy responde 422).
- `productKardex` devuelve `current_balance` y `current_stock` y son distinguibles cuando hay huérfanos.
- Post-reparación: la vista de divergencia debe dar **0 pares con `|dif| > 0.01`** sobre los 585.

### 4.4 Verificación numérica sobre datos reales (`agriflor_bug`, nunca producción)
`cd backend && DB_DATABASE=agriflor_bug DB_HOST=127.0.0.1 DB_PORT=3308 php artisan tinker` invocando `monthlyReport`. BODEGA PRINCIPAL = `a1d153b2-70cc-458e-8896-067f582326da`.

| Medida | Hoy | Tras PR-1+Migración A | Tras +PR-2 |
|---|---|---|---|
| Junio: filas `variation != 0` / `sum abs` | 20 / 39.146,94 | **4 / 180,50** | medir |
| Julio: filas `variation != 0` / `sum abs` | 36 / 40.833,31 | **23 / 2.527,75** | medir (E2 solo: 19 / 38.966,44) |
| Julio `summary.total_purchases` | 88.411,85 | 88.411,85 | **85.703,60** |
| Julio suma `returns` | 2.527,75 | — | **2.708,25** |
| Junio suma `returns` | 180,50 | — | **0** |
| Julio `shipments_in` bodega | n/a | n/a | **0** |
| **Mayo: 24 filas / 275.559,18** | — | **SIN CAMBIO** | **SIN CAMBIO** |

**Si mayo cambia, abortar y revisar el selector.**
Casos fila a fila (E2): CAMPOFERT ZINC (2861) julio → `purchases 0`, `returns 100`, `variation 0` (hoy −100); ANTRACOL WP (2030) → `returns 2.000`, `variation 0`; CERRERO (3661) → `returns 30`, `variation 0`. SULFATO DE MAGNESIO (**1390**) julio → `purchases 0`, `returns 36,10`; **el residual `variation 25` es E3/E4, no E2**: dejar constancia para no perseguirlo.
Casos fila a fila (E3/E4): SAL-20260729-0023 / REC-2026-000397 → entry de SAFERMIX en Melón pasa de 2026-07-29 a **2026-06-16**; BODEGA junio "Enviado" 816 y `variation 0`; julio sin SAFERMIX; Melón junio con 816. REC-2026-000371 / SAL-20260728-0029 → entry de TERRASSIL SILICIO pasa de 2026-07-28 a **2026-06-17**.
Excel: exportar julio 2026 y comprobar la columna nueva, que COMPRAS ya no incluye 2.708,25, que REMANENTE trae 2.708,25 y que **el estilo llega hasta la última columna** (regresión de `$lastCol`).

### 4.5 E2E en navegador (`http://localhost:5173`, backend contra `agriflor_bug`)
1. **E2 (caso exacto del cliente):** `admin@agriflor.com` → Informes → Inventario Mensual → julio 2026 → BODEGA PRINCIPAL → filtrar "sulfato de m": la fila **1390** debe mostrar Compras "-" y Remanente 36,10. Repetir con una **finca** seleccionada: Compras "-" y el total en "Envíos recibidos".
2. **E3/E4:** `bodega@agriflor.com` → crear Salida tipo Orden Técnica con fecha de un mes pasado → aprobar como admin → recibirla con Fecha de Recepción en ese mismo mes → Inventario Mensual del **mes pasado**: la finca destino muestra la cantidad y Variación 0 → Inventario Mensual del **mes actual**: el producto NO aparece con "Enviado". Repetir dejando la fecha en "hoy" para validar el rider de UX (PR-7).
3. **E5:** Inventario → BODEGA PRINCIPAL → 1537 → Kardex: debe verse **"Stock Actual (físico) 10.125,50 kg"** y **"Saldo contable 13.575,00 kg"** con la advertencia de 3.449,50, en vez de un solo "Saldo Actual 13.575,00". Luego Salidas → Nueva Salida → origen BODEGA PRINCIPAL → "QROP": la suma de los lotes ofrecidos debe coincidir con el Stock Actual (físico).
4. **E1:** Salidas → la columna "Destino" debe mostrar el nombre de la ubicación destino (y la fecha debajo), y el detalle debe mostrarlo también. Probar con un remanente cuyo destino es una **bodega**, no una finca — es el caso que hoy queda vacío.

### 4.6 No-regresión del módulo de Ajustes (obligatorio, y sustituto parcial de la validación que falta)
`php artisan test --filter=AdjustmentReportsConsistencyTest` y `--filter=AdjustmentTest` deben seguir **100% verdes**, en particular los casos que ya asertan `purchases == 0` y `variation == 0` en el destino de un traslado por ajuste (líneas 300-308 y 386-403) y el de unidad de empaque (416-447), más los asserts de `movement_date` (119/151/538/679/712/756). Además: crear/aprobar un ajuste `entry`, uno `exit` y uno `transfer` y correr el comando de auditoría → **0 divergencias**.
Lo que sabemos del módulo sin la validación que falta: `AdjustmentController:1105-1117` usa `movement_date` del ajuste y `applyTransfer` (`:904-921`) da la **misma fecha a ambas patas** (verificado en vivo con AJU-20260730-0003/0004); `applyEntry`/`applyExit` (`:924-944`) escriben `inventory` **y** `inventory_movements` en la misma transacción (`approve()` abre transacción en `:222-246`); y `adjustments` tiene **0 filas en producción**. Es decir: no puede haber daño en datos de producción por ese módulo hoy, pero **su comportamiento funcional sigue sin validar**.

### 4.7 Prueba de orden de despliegue
Entre PR-1 y la Migración A, registrar una recepción de salida retro-fechada y confirmar que **nace bien** (`movement_date == reception_date` en ambas patas) y que la migración posterior **no la toca** (su firma ya no coincide).

---

## 5. Decisiones que necesito del usuario

| # | Decisión | Recomendación |
|---|---|---|
| **1** | **¿Qué fecha manda en el movimiento de una salida/traslado: `output_date`, `reception_date` del lote, o creación?** | **`reception_date` del lote, la MISMA en ambas patas.** Razones: (a) el origen **no se debita al aprobar la salida** (`ProductOutputController:932` lo dice explícitamente), así que entre despacho y recepción la mercancía sigue físicamente en la bodega y un conteo de fin de mes la encontraría allí; (b) es lo que el exit ya hace por diseño deliberado (commit `75e36bf`) → fix de **1 argumento y 38 filas**, contra **947 filas** si se adopta `output_date`; (c) medido: ambas opciones dan **prácticamente el mismo cierre** (solo 3 de los 38 caen en meses distintos), así que no hay razón numérica para pagar la opción grande. Si el negocio decide que el inventario debe reflejar "lo despachado", entonces hay que modelar **mercancía en tránsito**: eso ya es una feature, no una decisión de fecha. |
| **2** | ¿Pueden origen y destino llevar **fechas distintas**? | **NO**, y no es opinable con el código actual: la columna "Enviado a finca X" del origen se calcula leyendo el `movement_date` de la **entrada en el destino** (`InventoryController:1487`) mientras el stock del origen usa la del **exit** (`:1526`). Fechas distintas **reproducen literalmente E3/E4** (SAFERMIX: −816 en junio, +816 en julio). Una fecha por documento. |
| **3** | **Alcance de la reparación de datos**: ¿mayo y anteriores quedan CONGELADOS? | **Sí.** Reparar solo los 38 con la firma del bug (todos en julio 2026) y **no** tocar los 169 pares pre-despliegue. Necesito esto **por escrito** antes de ejecutar la migración: es la salvaguarda del cierre alineado con Siigo. |
| **4** | **¿Cuál es la fuente de verdad del stock** en los 7 productos divergentes? (A materializar el faltante / B revertir el libro / C separar los dos conceptos) | **C.** Es la única que no inventa ni destruye stock sin evidencia física. A crea 3.449,50 kg que quizá no existen (merma futura); B rompe la alineación de mayo con Siigo que usted ya confirmó. Con C: mostrar ambas cifras y **hacer un conteo físico de los 7 productos** (QROP KS, KIESERITA, NITRABOR, TERRASSIL, BOROZINCO, COMBATRAN, KENDO) para materializar solo lo confirmado. |
| **5** | **Nombre y presentación de la columna nueva** para entradas recibidas que no son compra ni remanente | **"Envíos recibidos"** como columna propia. La alternativa (meterlas en "Aumentos") evita tocar el Excel pero mezcla ajustes con envíos y hace el informe menos auditable. |
| **6** | ¿"Disponible" en Nueva Salida = físico o físico − comprometido? | **Físico − comprometido**, porque es lo que el backend valida al guardar. Hoy el selector ofrece cantidades que la salida rechaza con "Stock insuficiente". Mostrar el desglose para que el cambio no se lea como pérdida de stock. |
| **7** | **¿Se permite fechar movimientos dentro de un mes ya conciliado?** ¿Y qué hacemos con la compra de 10.500 kg cargada el 29/07 con fecha 29/05, que dejó mayo de QROP KS en 45.050 contra 34.550 de Siigo? | **No permitirlo sin confirmación explícita** (PR-6). Sobre esa compra concreta necesito su instrucción: re-fecharla a su fecha real de recepción (y recuperar el cierre de mayo) o aceptar que mayo ya no cuadra con Siigo en ese producto. **No la toco sin su respuesta.** |
| **8** | ¿El informe mensual debe seguir permitiendo elegir una **FINCA**, existiendo `farm-monthly-report`? | **Mantenerlo** y aceptar la columna "Envíos recibidos" (hay 203.937,83 unidades de julio en juego que hoy salen como Compras). Restringirlo a bodegas hace el fix más pequeño pero elimina una vista que el cliente puede estar usando. |
| **9** | ¿Se corrige el **rótulo de "Compras"** en los meses de carga inicial? (abril 2026: 1.071.154,42 de 1.071.529,82 es carga Excel/Siigo, no compras) | Como mínimo **avisarle antes** de que concilie abril. Idealmente, segregar la carga inicial en su propia columna en un ciclo posterior. No es E2. |
| **10** | `closeOutputReception` (`POST receptions/{id}/close-with-available`, `routes/api.php:303`): ¿se corrige para aceptar fecha o **se retira la ruta**? | **Retirarla**: fue reemplazada por "finalizar recepción" y tiene 0 filas de producción. Menos superficie, menos recaídas. |
| **11** | `ImportController` (carga inicial y remanentes desde Excel): ¿se agrega un campo "fecha del corte" o se acepta el riesgo documentado? | Agregar el campo cuando se vuelva a importar. Mientras, **documentarlo**: una re-importación hoy fecharía toda la carga en el mes en curso. |
| **12** | **La validación del módulo de Ajustes nunca se entregó.** ¿Autoriza desplegar sin ella? | **Re-lanzar la validación antes del despliegue** (es barata: la suite ya existe + un E2E de crear/aprobar los 3 tipos de ajuste). Los ajustes son el módulo más nuevo y el único que escribe `inventory` **y** el kardex a la vez; desplegar cambios en los informes sin saber si el módulo funciona nos deja sin línea base para atribuir cualquier descuadre nuevo. |

---

## 6. Riesgos y orden de despliegue

### Orden (una sola ventana de mantenimiento, tres pasos con medición entre ellos)

**Paso 0 — preparación (sin desplegar)**
- Copia **limpia y exclusiva** de producción para el ensayo. `agriflor_bug` está siendo escrita por otro agente (pasó de 1993 a 2005 movimientos durante el análisis, 12 filas "QA"): sirve para diagnosticar, **no** para validar una migración.
- `mysqldump` de `inventory_movements` (y de `inventory` si se va a tocar E5).
- Guardar el **baseline** de los informes mensuales de mayo, junio y julio (BODEGA PRINCIPAL, Mansión, Melón) y del Excel de julio.
- Verificar que existen las tablas de respaldo de las conciliaciones de mayo.
- Ensayar la Migración A en la copia limpia: debe seleccionar **exactamente 38 filas, todas en 2026-07**.

**Paso 1 — deploy A: código sin cambio de semántica en los informes**
PR-1 (E3/E4 + blindaje) + PR-4 (E1) + PR-3 (UI kardex + comando de auditoría).
Verificar en prod: crear una recepción de salida retro-fechada de prueba y comprobar que ambas patas llevan la fecha del lote; correr el comando de auditoría y **anotar las 7 divergencias conocidas** (no deben ser 8).

**Paso 2 — Migración A (datos, 38 filas)**
Nunca antes del Paso 1. Ejecutar los checks (a)–(f) de 3.2. **Si mayo se movió: `down()` inmediato.**
Medir junio/julio: esperado 4/180,50 y 23/2.527,75.

**Paso 3 — deploy B: PR-2 (E2), con aviso previo al cliente**
Verificar julio: `total_purchases` 85.703,60, `returns` 2.708,25, junio `returns` 0; abrir el Excel y comprobar la columna nueva y el estilo completo; comprobar que Mansión/Melón muestran su total en "Envíos recibidos" y no en Compras.

**Después, en ciclos separados:** PR-5 (selector, con comunicación), PR-6 (candado de periodo, tras Decisión 7), PR-7 (rider UX, tras Decisión 1), reparación de datos de E5 (tras Decisión 4 + conteo físico), y los diferidos (`toBaseUnit`, `farmMonthlyReport`, fechas de importación).

### Riesgos, ordenados por gravedad
1. **Selector de migración demasiado amplio** → 234 filas en vez de 38, 186 de ellas en mayo → destruye el cierre conciliado con Siigo. *Mitigación:* firma del bug + conteo exacto de 38 + comparación del informe de mayo antes/después.
2. **Quitar recepciones de salida de `purchases` sin añadir `shipments_in`** → 203.937,83 unidades de julio se convierten en Variación en los informes por finca, justo en la columna que el cliente concilia. *Mitigación:* pasos 3 y 5 de PR-2 en el mismo commit; test T4.
3. **Aplicar la migración antes del fix de código** → cada traslado nuevo sigue naciendo mal fechado y hay que repetir la reparación. *Mitigación:* orden estricto + la prueba 4.7.
4. **Hacer `$movementDate` obligatorio y olvidar un call site** → `ArgumentCountError` en recepciones (funcionalidad crítica caída). *Mitigación:* grep de los 4 call sites + los tests de los 4 tipos de salida antes de mergear.
5. **Reparar E5 con `migrate:rollback`** → revierte los 62 movimientos de conciliación, no los 11 huérfanos, porque los `down()` borran por texto de `observations` sin filtrar por producto. *Mitigación:* prohibido; SQL dirigido por producto.
6. **Cambiar números ya conciliados sin avisar** → el cliente lo leerá como un fix que rompió sus informes. *Mitigación:* comunicación previa con los deltas exactos (finca: may −57.991,73 / jun −107.424,93 / jul −203.937,83; bodega julio Compras −2.708,25 = −3,06%) y el mensaje claro: **el número anterior estaba mal**.
7. **No prometer cuadre perfecto.** Aun con los dos fixes quedan: el descuadre **preexistente de mayo** (24 productos / 275.559,18, ajeno a todo esto), las 4 filas de kardex con saldo negativo nuevo, los 7 lotes con `reception_date` anterior al `output_date` (físicamente imposibles, se corrigen a mano), y las 180,50 unidades que "se mueven" de junio a julio en la columna Remanente.
8. **Rendimiento del informe.** Si PR-2 se implementa con el `whereExists` dentro del bucle de ~220 productos, el informe (que ya tarda segundos) puede volverse inusable. La precarga del paso 6 no es opcional.
9. **`inventory_movements` sin `updated_at`** → cualquier `Model::update()` masivo falla o se comporta raro. Solo `DB::statement`/`DB::table`.
10. **Rendimiento/UX del selector:** PR-5 reduce lo ofrecido; sin comunicación entra como "bug nuevo".

### Si algo sale mal
- **Deploy A o B:** `git revert` del PR + redeploy. Ninguno de los dos escribe datos; el efecto es inmediato y total.
- **Migración A:** `php artisan migrate:rollback --step=1` (su `down()` restaura desde `inventory_movements_md_backup`), o restaurar `inventory_movements` del dump. El stock físico **nunca** estuvo en riesgo: la migración solo toca `movement_date`, no `inventory`, no cantidades.
- **Reparación de E5 (cuando llegue):** dump previo + tablas de respaldo de las conciliaciones + SQL dirigido por producto con su propia tabla de respaldo. Nunca `migrate:rollback`.
- **Criterio de aborto durante el Paso 2:** cualquier cambio en un movimiento con `movement_date <= '2026-05-31'`, o un conteo distinto de 38 filas en el respaldo.

### Archivos clave (rutas absolutas)
- `/datos/Documentos/PERSONAL/AgriFlor/backend/app/Http/Controllers/Api/ReceptionController.php` (1784-1795 el fix; 1894-1905 y 1969 el blindaje; 1162/1204 y 1840 secundarios)
- `/datos/Documentos/PERSONAL/AgriFlor/backend/app/Http/Controllers/Api/InventoryController.php` (25, 1442, 1462-1471, 1521, 1568-1573, 1588, 1939-1952; **no tocar** 1647-1673, 1691-1720, 1749-1789; 1819-1820 diferido)
- `/datos/Documentos/PERSONAL/AgriFlor/backend/app/Http/Controllers/Api/ProductController.php` (235-341, 246)
- `/datos/Documentos/PERSONAL/AgriFlor/backend/app/Exports/MonthlyInventoryExport.php` (51-56, 87-92, **115**)
- `/datos/Documentos/PERSONAL/AgriFlor/backend/app/Services/InventoryService.php` (32-42, diferido)
- `/datos/Documentos/PERSONAL/AgriFlor/backend/database/migrations/2026_07_30_120000_realign_transfer_entry_movement_dates.php` (a crear; patrón: `2026_06_09_120000_realign_movement_dates_to_document.php`)
- `/datos/Documentos/PERSONAL/AgriFlor/frontend/src/pages/outputs/Outputs.tsx` (590, 679)
- `/datos/Documentos/PERSONAL/AgriFlor/frontend/src/pages/inventory/Inventory.tsx` (641-656)
- `/datos/Documentos/PERSONAL/AgriFlor/frontend/src/pages/reports/MonthlyInventoryReport.tsx` (38-42, 199-214, 247-261)
- `/datos/Documentos/PERSONAL/AgriFlor/frontend/src/pages/reception/Reception.tsx` (335, 521)
- `/datos/Documentos/PERSONAL/AgriFlor/backend/tests/Feature/AdjustmentReportsConsistencyTest.php` (patrón de los tests nuevos y red de seguridad de Ajustes)