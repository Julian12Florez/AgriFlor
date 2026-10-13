# Plan: Re-baseline de inventario al 31-jul-2026

**Objetivo:** cargar el inventario de julio (archivo del cliente) como línea base oficial al
31-jul-2026, conservando intactos los movimientos de agosto, y dejando **libro (kardex) y
bodega (stock físico) sincronizados** en todas las ubicaciones.

**Estado actual verificado en prod (20-ago-2026):**
- 362 productos en catálogo · 224 con stock · 18 ubicaciones con stock · 1.233 filas de lote
- 438 movimientos en agosto (49 productos, 14 ubicaciones), del 03 al 20 de agosto
- 7 productos con libro ≠ bodega (herencia del bug de mayo: corrección escrita solo en el libro)
- Módulo de Ajustes desplegado y EN USO (3 ajustes: 2 aprobados, 1 rechazado)

---

## 1. REGLA DE CORTE (lo que entra y lo que no)

**El corte es por `movement_date` (fecha del movimiento), NUNCA por `created_at` (fecha de registro).**

| Movimiento | Criterio | Tratamiento |
|---|---|---|
| `movement_date <= 2026-07-31` | Pertenece a julio o antes | **SE ABSORBE** en la línea base. El archivo de julio ya lo incluye. No se cuenta dos veces. No se borra: queda en la historia, pero el saldo al 31-jul se fuerza al valor del archivo. |
| `movement_date >= 2026-08-01` | Es agosto | **SE CONSERVA Y SE REPRODUCE** sobre la nueva base. No se toca ni uno. |

**Casos concretos auditados:**
- `AJU-20260820-0001` NITRABOR +1.675 kg, fechado **21-jul**, registrado el 20-ago
  → **se absorbe** (el archivo de julio ya lo tiene). También se normaliza su lote físico `AJU-20260820-0001`.
- `AJU-20260803-0001` ANTRACOL −7.600 g, fechado **03-ago** → **se conserva**.
- 0 movimientos fechados en agosto registrados antes. 0 movimientos con fecha futura. Corte limpio.

---

## 2. INSUMO REQUERIDO

Archivo de inventario de julio con, como mínimo:

| Columna | Obligatoria | Notas |
|---|---|---|
| Código de producto | Sí | Debe casar con `products.product_code` |
| Nombre producto | Recomendada | Solo para validación cruzada |
| Unidad | Sí | Si difiere de la unidad base, se convierte con `InventoryService::toBaseUnit` |
| Cantidad | Sí | Saldo al 31-jul |
| **Precio unitario** | **Sí** | **Costo al 31-jul. Se carga y corrige la valorización (ver §5-bis)** |
| Ubicación | Sí | Una columna por finca/bodega, o filas producto+ubicación |

Sin la ubicación no se puede hacer: hoy hay stock en 18 ubicaciones y el consolidado no basta.

---

## 3. ALGORITMO — CORREGIDO tras auditoría (v2)

> ⚠️ **La v1 de este algoritmo era INCORRECTA.** Suponía que `AJUSTE_LIBRO` tocaba solo el kardex y
> `AJUSTE_FISICO` solo el stock físico. **Es falso:** el módulo de Ajustes desplegado escribe SIEMPRE
> en las dos tablas (`applyEntry()` = `addStock()` + `recordMovement()`; `applyExit()` =
> `reduceInventoryFIFO()` + `recordMovement()`). Con la v1, los ~651 triples sanos habrían recibido
> **doble descuento** y los 18 rotos **no se habrían arreglado** (la brecha es invariante bajo
> escrituras duales). Verificado en `AdjustmentController.php` e `InventoryService.php`.

**Granularidad: producto + MARCA + ubicación** (669 triples). No producto+ubicación: hay productos
cuyas marcas se cancelan entre sí y parecerían sanos (ABONO ORGANICO esconde ±244.562 kg).

```
J   = saldo del archivo de julio (bodega: INVENTARIOS!INVENTARIO FINAL; fincas: REMANENTES)
K31 = kardex al 31-jul   = Σ(entradas − salidas) con movement_date <= 2026-07-31
A   = delta de agosto    = Σ(entradas − salidas) con movement_date >= 2026-08-01
P   = stock físico actual (tabla inventory)

q = (J + A) − P        ← AJUSTE SIMÉTRICO (escribe kardex Y físico)
G = P − (K31 + A)      ← CORRECCIÓN SOLO-KARDEX (la brecha heredada de mayo)
```

**Ambas escrituras se fechan `2026-07-31`** (el stock físico no tiene fecha; fechar el ajuste físico
"hoy" contaminaría agosto y rompería `initial_stock(agosto) = J`).

| Escritura | Cómo se aplica | Alcance |
|---|---|---|
| **q** | Documento real del módulo de Ajustes (`entry` si q>0, `exit` si q<0), lote `BASE-JUL-2026`, motivo "conteo físico" | Todos los triples con q ≠ 0 |
| **G** | Fila en `adjustments` (approved) **+** movimiento en `inventory_movements`, **sin** pasar por `applyEntry/applyExit` | **Solo 18 triples** (los 7 productos rotos), listados nominalmente y firmados |

**Comprobación algebraica:**
- `kardex@31jul = K31 + G + q = J` → agosto abre exactamente en el archivo ✓
- `kardex_hoy = J + A` ✓ · `físico_hoy = P + q = J + A` ✓
- `G + q = J − K31` → el total del plan v1 era correcto; **lo que estaba mal era el reparto y la fecha**.

*Verificación con TERRASSIL (J = 1.025):*
`q = 15,10 − 277,60 = −262,50` · `G = 277,60 − (−88,90) = +366,50` · `G + q = +104` ✓
Kardex al 31-jul = 921 + 366,50 − 262,50 = **1.025 = J** ✓ · Físico = 277,60 − 262,50 = **15,10** ✓

**Por qué G no puede ir por el módulo:** `applyExit()` re-valida contra el stock físico y consume
FIFO. Un G negativo (QROP KS: G = −3.449,50) o se rechaza con 422 o **se lleva por delante
existencias reales**. `q` sí puede ir por el módulo: con el pre-flight `J+A ≥ 0`, se cumple `|q| ≤ P`.

**Por qué esto NO repite el bug de mayo:** aquel fue una escritura **oculta y condicional**
(`if (!$hasJune)`) que tocaba una fuente sí y otra no, sin declararlo. Aquí la escritura solo-kardex
es **una sola, declarada, acotada a 18 triples nominados, con documento en `adjustments`, firmada**,
y el resultado se verifica por triple contra el archivo dentro de la transacción.

---

## 3-bis. CORRECCIONES BLOQUEANTES ADICIONALES (auditoría)

1. **NO convertir unidades.** `quantity` ya está en escala base (la conversión ocurre aguas arriba en
   `ReceptionController`). Las 10 filas con `unit ≠ base_unit` son **errores de etiqueta, no de
   magnitud** (convertir MOLIB-K daría 40,7 toneladas). Regla: si el archivo trae una unidad distinta
   a `products.base_unit` → **falla dura de la fila**, nunca `toBaseUnit`.
2. **Pre-flight bloqueante todo-o-nada:** abortar la corrida completa si algún triple da `J + A < 0`.
   Hay **11 combos** así hoy (RIDOMIL GOLD, NITRABOR, MAP AVAIL, QROP KS, TERRASSIL, OXICLORURO,
   SAFARI, SULFATO DE MAGNESIO, GREEN MARINE POWDER, KSC ADUR, PROAXIS).
3. **No usar `reduceInventoryFIFO()` tal cual:** hace `DELETE` físico de lotes (contradice "preservar
   vencimientos") y consume primero los 547 lotes sin costo. Usar reductor propio con
   `ORDER BY expiration_date IS NULL, expiration_date ASC, created_at ASC` y respaldo de fila completa.
4. **Paso previo:** consolidar las **34 pseudo-marcas "Sin Marca"** a su marca real hermana (50 lotes,
   2 colisiones de `batch_number`). Deja 0 casos multimarca y elimina la regla de reparto por marca.
5. **Recalcular K31, A y P dentro de la transacción con `lockForUpdate()`**, nunca reutilizar los
   deltas del Excel de simulación. Es la única idempotencia real.
6. **Verificaciones independientes del algoritmo:** por triple, `kardex_hoy == J + A` y
   `físico == J + A` **contra el archivo** (no tabla contra tabla, que es tautológico).
7. **Universo = unión** de claves de `inventory` (653) y de `inventory_movements` (669).
8. **Ensayo obligatorio contra restauración del dump** (`/opt/agriflor/backups/`) antes de tocar prod.
9. **`ADJUSTMENTS_CLOSED_PERIOD_UNTIL`** (hoy `2026-05-31` por default en `config/adjustments.php`)
   se sube a `2026-07-31` **DESPUÉS** de la corrida, nunca antes (si no, el módulo rechaza los 669 docs).
10. **Rollback real = restaurar el dump.** `cancel()` exige `status='pending'`: **los ajustes aprobados
    no se pueden anular**. Es un punto de no retorno mientras dure el congelamiento.
11. **Ventana de congelamiento: NO 15 minutos** — debe cubrir desde el snapshot hasta la verificación
    completa (30 usuarios activos, hasta 90 movimientos/día).
12. Marcador de idempotencia determinista: `adjustment_number = REBASE-JUL26-####`,
    `batch_number = BASE-JUL-2026`; abortar si ya existen salvo `--force`.

---

## 4. CASOS ESPECIALES (auditados en prod)

| Caso | Cantidad | Tratamiento |
|---|---|---|
| **Multi-marca**: producto+ubicación con más de una marca | **34** | El archivo no trae marca. Regla: aplicar el delta a la marca con mayor saldo; si el delta es negativo, consumir por FIFO entre marcas. **Se listan aparte en la simulación para decisión manual.** |
| **Multi-unidad**: producto con más de una unidad en inventario | **4** | Normalizar todo a unidad base antes de comparar. |
| **Filas con unidad ≠ unidad base** | **10** | Convertir con `toBaseUnit` (soporta unidades de empaque). |
| **Lotes con fecha de vencimiento** | **540** | **NO se borran los lotes.** Se ajusta por delta (ver §5), preservando vencimientos, costos y orden FIFO. |
| **Producto en el archivo que no existe en el sistema** | ? | Se reporta, NO se crea automáticamente. |
| **Producto con stock en el sistema pero ausente del archivo** | ? | Se reporta. **No se asume cero.** Decisión del usuario. |
| **Resultado negativo tras J + A** | ? | Se marca en rojo: significa que el dato de julio o algún movimiento de agosto está mal. Se detiene ese producto, no se aplica. |
| **Compras pendientes** (5 docs, $186.140.000) | 5 | No afectan: aún no entraron a inventario. Se recepcionan normal después. |

---

## 5. CÓMO SE APLICA EL AJUSTE FÍSICO (preservando lotes)

**Prohibido:** borrar todas las filas de `inventory` y crear un lote único por producto
(destruiría 540 vencimientos, los costos y el orden FIFO).

- **Delta positivo** → crear un lote nuevo `BASE-JUL-2026` con el costo promedio actual del
  producto en esa ubicación (o el del archivo si lo trae).
- **Delta negativo** → reducir por **FIFO** desde los lotes existentes (`reduceInventoryFIFO`),
  respetando vencimiento y antigüedad.
- **Delta cero** → no se toca.

---

## 5-bis. CARGA DE COSTO UNITARIO Y VALORIZACIÓN

El archivo de julio trae **precio unitario**, así que el re-baseline corrige **cantidad Y valor**.
### Estado actual de costos en producción (auditado 20-ago-2026)

| Métrica | Valor |
|---|---|
| Filas de lote en inventario | 1.233 |
| **Lotes SIN costo** (`unit_price` nulo o 0) | **609 (49,4 %)** |
| Cantidad sin valorizar | 732.891,24 unidades |
| Valorización total actual | $2.272.560.639 |
| Filas con `total_value` ≠ `quantity × unit_price` | 38 |
| Producto+ubicación con precios distintos entre lotes | 144 |

**Casi la mitad del inventario no tiene costo cargado.** Esto refuerza la decisión: si el archivo de
julio trae precio, cargarlo no solo re-basa la valorización — **repara un problema de calidad de datos
que hoy deja $0 en 609 lotes**. También hay que recalcular los 38 `total_value` inconsistentes.

### Regla de aplicación del costo

| Situación | Qué se hace con el precio |
|---|---|
| Se crea el lote base `BASE-JUL-2026` (delta positivo) | `unit_price` = **precio del archivo** |
| Existen lotes previos del mismo producto+ubicación | **Se re-precian al valor del archivo** (ver decisión abajo) |
| Delta negativo (se reduce por FIFO) | Los lotes que sobreviven quedan al precio del archivo |
| El archivo trae precio 0 o vacío | **No se re-precia**: se conserva el costo actual y se reporta en la simulación |

`total_value` se recalcula siempre como `quantity * unit_price` en cada fila tocada.

### Decisión requerida: ¿re-preciar todos los lotes o solo el nuevo?

| Opción | Efecto | Cuándo conviene |
|---|---|---|
| **A. Re-preciar todos los lotes** del producto+ubicación al precio del archivo | La valorización al 31-jul queda EXACTA. Se pierde el costo histórico por lote. | **Recomendada** si el archivo es la fuente contable oficial (alineado con Siigo) |
| B. Solo el lote nuevo lleva el precio del archivo | Conserva el costo por lote. La valorización queda como promedio mezclado, no exacta. | Si se quiere preservar trazabilidad de costo por lote |

**Recomendación: Opción A** — si el cliente entrega precio, es porque esa es la valorización que debe
quedar. Se respalda el costo anterior en la tabla de backup para poder revertir.

### Impacto en los movimientos generados

Cada movimiento de ajuste debe llevar:
- `unit_price` = precio del archivo
- `total_price` = `quantity * unit_price`

Así el kardex valorizado y los informes de valor quedan consistentes con la nueva base.

### Verificación adicional (se suma a la Etapa 3)

7. `inventory.total_value == quantity * unit_price` en todas las filas tocadas
8. Valorización total por ubicación al 31-jul == suma de `cantidad * precio` del archivo
9. Reporte de productos cuyo costo cambió, con valor antes/después y la diferencia total en $

---

## 6. REGLA DE ORO (la que evita repetir el bug de mayo)

> **El invariante es de RESULTADO, no de delta:** al cerrar la transacción, para CADA triple debe
> cumplirse `kardex_hoy == físico == J + A` verificado **contra el archivo**. Si no, rollback.
>
> La regla v1 ("todo ajuste escribe en las dos fuentes") era matemáticamente lo que **impedía**
> cerrar la brecha heredada. La única escritura solo-kardex permitida es **G**, declarada y acotada
> a 18 triples nominados.

El desastre actual lo causó exactamente esto en la migración de mayo:
```php
if (!$hasJune) {          // ← este if es el bug
    // corregir el stock físico
}
```
En este plan **no existe ningún `if` que decida si sincronizar o no**.

Además, cada movimiento generado debe cumplir:
- `type` = `entry` o `exit` — **nunca** `adjustment` (no está en el enum de la BD y los reportes lo ignoran)
- `related_document_type` = `App\Models\Adjustment` (para que NO se cuente como Compra en el informe)
- `observations` con las palabras clave: `"aumento"` / `"ajuste positivo"` o `"disminución"` / `"ajuste negativo"`
- `movement_date` explícito (31-jul para el libro, hoy para el físico)

---

## 7. ETAPAS DE EJECUCIÓN

### Etapa 0 — Congelamiento
El módulo de Ajustes está vivo (hoy se registró uno a las 14:11). Ventana de freeze (~15 min),
aviso a usuarios, y **snapshot completo de la BD** como punto de retorno.

### Etapa 1 — Simulación (NO toca nada)
Genera un Excel con una fila por producto+ubicación:
`código · producto · ubicación · unidad · J (archivo) · K31 · A (agosto) · P (físico) ·
ajuste_libro · ajuste_físico · resultado_final · alerta`

Alertas: negativos, multi-marca, producto no encontrado, producto sin dato en archivo,
diferencia > umbral configurable.

**Se revisa con bodega antes de continuar.**

### Etapa 2 — Aplicación (reversible)
- Todo dentro de una transacción
- Respaldo previo: `inventory_rebaseline_backup` e `inventory_movements_rebaseline_backup`
- Los ajustes se crean como registros reales del **módulo de Ajustes** (auditables, visibles en la UI),
  motivo "Conteo físico / re-baseline julio 2026"
- Idempotente: si se corre dos veces, la segunda no genera cambios

### Etapa 3 — Verificación automática
1. `kardex_hoy == físico_hoy` para los 224 productos con stock, en las 18 ubicaciones
2. Cero saldos negativos
3. Informe mensual de agosto: `INV FINAL == STOCK ACTUAL` para todos
4. Saldo inicial de agosto == valores del archivo de julio
5. Los 438 movimientos de agosto siguen intactos (conteo y suma idénticos)
6. Ningún ajuste clasificado como "Compras" en el informe

Si algo falla → rollback automático desde los respaldos.

---

## 8. REVERSIBILIDAD

- Snapshot completo de BD antes de empezar (Etapa 0)
- Tablas de respaldo por fila afectada (Etapa 2)
- Los ajustes quedan como documentos del módulo → se pueden anular uno a uno
- Comando de rollback que restaura `inventory` y borra los movimientos generados

---

## 9. RIESGOS Y MITIGACIÓN

| Riesgo | Mitigación |
|---|---|
| Archivo de julio con datos erróneos | Simulación previa + revisión de bodega + alertas de negativos |
| Alguien registra un ajuste durante la corrida | Ventana de congelamiento + snapshot |
| Pérdida de vencimientos/costos | No se borran lotes; ajuste por delta con FIFO |
| Multi-marca mal distribuida | Los 34 casos se listan aparte para decisión manual |
| Diferencia de unidades | Normalización a unidad base con `toBaseUnit` |
| Que el informe cuente los ajustes como Compras | `related_document_type = App\Models\Adjustment` |
| Re-preciar altere la valorización contable de forma inesperada | Simulación muestra el delta en $ por producto y el total; respaldo de costos previos; decisión A/B explícita del usuario |
| Archivo con precio 0 o vacío | No se re-precia; se conserva el costo actual y se reporta |

---

## 10. ANÁLISIS DE LOS ARCHIVOS ENTREGADOS (auditado 20-ago-2026)

### 10.1 `Valoración de inventarios.xlsx` — fuente de PRECIO
- Hoja `Sheet1`, encabezado en **fila 5**, datos filas 6-221.
- Columnas: `Código producto · Nombre producto · Referencia fábrica · Categoría · Saldo cantidades · Vlr Und · Valor total`
- **216 productos**, período Julio 2026, valor total **$818.738.513,60**, cantidad total 802.808,73.
- **Cobertura de precio: 100 %.** Los 216 productos con stock en julio tienen precio, ninguno en 0. ✅

### 10.2 `INVENTARIO JULIO FINAL.xlsx` — fuente de CANTIDAD por ubicación
7 hojas. Las relevantes:

| Hoja | Contenido | Uso |
|---|---|---|
| `INVENTARIOS` (998×28) | Matriz mensual. Col **`INVENTARIO FINAL` (índice 26 / col AA)** = saldo **BODEGA** | **Cantidad bodega** |
| `REMANENTES` (391×26) | Stock por finca, 17 columnas de finca | **Cantidad fincas** |
| `saldo final julio` (225×7) | Consolidado contable al 31-jul (216 prods) | Validación cruzada |
| `conteo`, `compras`, `saldos iniciales`, `Hoja1` | Auxiliares | No se usan |

- 360 productos con código, **todos únicos**, **0 cantidades negativas**.
- Suma bodega = **843.293,58** · Suma fincas = **13.172,95** · Total = **856.466,53**
- Unidades presentes: `LITRO, KILOGRAMOS, KILOS, GRAMOS, MILILITROS, CENTIMETROS`
  ⚠️ **`KILOS` y `KILOGRAMOS` conviven** — hay que mapear ambas a `kg`.

### 10.3 Cruce contra la BD de producción
| Verificación | Resultado |
|---|---|
| Ubicaciones de `REMANENTES` que casan con la BD | **17 / 17** ✅ |
| Ubicaciones en BD sin columna en el archivo | `Breva Lote 7`, `Laboratorio` — **ambas sin stock** ✅ sin impacto |
| Productos del Excel que no existen en la BD | **1**: código `1107` (PREZA) ⚠️ |
| Productos con stock en julio | 216, **todos con precio** ✅ |

### 10.4 Discrepancia entre fuentes (DECISIÓN REQUERIDA)

| Fuente | Total cantidad |
|---|---|
| `Valoración` (contable) | 802.808,73 |
| `saldo final julio` (contable) | 820.808,73 |
| `INVENTARIOS` + `REMANENTES` (operativo) | **856.466,53** |

- `Valoración` vs `saldo final julio`: **1 producto difiere** (código `9716`: 21.590 vs 39.590 → 18.000).
- `saldo final julio` vs operativo: **34 productos difieren**, total **35.657,80**.
  De esos, **24 tienen `saldo final julio` == solo bodega** → confirma que **el consolidado contable
  NO incluye los remanentes en finca** (13.172,95).

**Conclusión:** son dos vistas distintas, no un error.
- **Contable (Valoración / saldo final julio)** = Siigo, solo bodega.
- **Operativo (INVENTARIOS + REMANENTES)** = realidad por ubicación.

**Para el re-baseline se usa el OPERATIVO** (es el único con desglose por ubicación, que es lo que
exige la tabla `inventory`), y **el precio sale de `Valoración`**. El producto `9716` y los 34
divergentes se reportan en la simulación para revisión.

---

## 11. EVALUACIÓN DEL SCRIPT DE CARGA EXISTENTE

`backend/app/Console/Commands/ImportExcelInventory.php` (502 líneas, `import:excel-inventory`).

**Veredicto: NO es reutilizable para este re-baseline.** Sirvió para la carga inicial (febrero),
pero aplicarlo ahora causaría daño silencioso:

| # | Defecto | Consecuencia hoy |
|---|---|---|
| 1 | **Columna hardcodeada** `$cells[25]` para "INVENTARIO FINAL" | En el archivo nuevo el índice 25 es **`REMANENTE`**; el 26 es `INVENTARIO FINAL`. **Cargaría la columna equivocada sin avisar.** |
| 2 | `if ($existingInv) { skip }` — solo crea si NO hay stock | Hoy **224 productos ya tienen stock** → los saltaría casi todos. **No puede ajustar por delta ni bajar cantidades.** |
| 3 | `Inventory::create` **sin `unit_price` ni `total_value`** | Deja los lotes en $0. **Es el origen de los 609 lotes sin costo.** |
| 4 | **No setea `movement_date`** | El movimiento quedaría fechado hoy, no al 31-jul → rompe todos los informes históricos. |
| 5 | `unit => $product->base_unit` sin convertir desde la unidad del Excel | Cantidades mal escaladas si la unidad difiere. |
| 6 | `unitMap` no contempla **`KILOS`** (solo `KILOGRAMOS`) | Productos en `KILOS` fallan o caen a la unidad equivocada. |
| 7 | Sin respaldo, sin idempotencia, sin reversibilidad | No se puede volver atrás. |
| 8 | No conoce el concepto de "movimientos de agosto a respetar" | Podría duplicar o pisar saldos. |

**Decisión: comando NUEVO** `inventario:rebaseline`, reutilizando de él solo lo que sí sirve:
el `unitMap` (ampliado con `KILOS`), el matcheo de ubicaciones por nombre normalizado, y el patrón
`--dry-run`.

---

## 12. PASOS DETALLADOS DE LA CARGA

**Paso 1 — Parseo y validación (sin BD).** Lee `INVENTARIOS!INVENTARIO FINAL` (bodega),
`REMANENTES` (17 fincas) y `Valoración!Vlr Und` (precio). Normaliza unidades (incl. `KILOS`→`kg`),
resuelve códigos y ubicaciones. **Aborta** si algún encabezado esperado no aparece **por nombre**
(nunca por índice fijo). Reporta: productos sin match, precios faltantes, negativos, duplicados.

**Paso 2 — Cálculo de deltas.** Para cada producto+ubicación con la fórmula de §3, usando
`movement_date` como corte: `K31` (≤31-jul), `A` (≥01-ago), `P` (físico actual).

**Paso 3 — Simulación (`--dry-run`).** Excel con una fila por producto+ubicación:
`J · K31 · A · P · ajuste_libro · ajuste_físico · precio_archivo · precio_actual · Δvalor $ · alerta`.
**Nada se escribe.** Se revisa con bodega.

**Paso 4 — Congelamiento y respaldo.** Ventana de freeze del módulo de Ajustes + snapshot de BD +
tablas de respaldo por fila.

**Paso 5 — Aplicación.** Transacción única. Ajuste al libro fechado 31-jul, ajuste al físico según
§7 (delta, preservando lotes y vencimientos), precios aplicados según §5-bis. Etiquetado
`related_document_type = App\Models\Adjustment` + palabras clave en observaciones.

**Paso 6 — Verificación.** Las 9 comprobaciones de la Etapa 3 (§7) + §5-bis. Rollback si falla.

**Paso 7 — Cierre.** Se descongela, se informa, y se documenta el resultado.
