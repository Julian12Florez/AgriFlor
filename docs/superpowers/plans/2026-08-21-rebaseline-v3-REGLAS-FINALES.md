# Re-baseline inventario al 31-jul-2026 — REGLAS FINALES (v3, aprobadas)

## Fuentes
- **Cantidad bodega:** `INVENTARIO JULIO FINAL.xlsx` → hoja `INVENTARIOS`, columna **`INVENTARIO FINAL`** (buscar por NOMBRE, nunca por índice)
- **Cantidad fincas:** misma hoja `REMANENTES` (17 columnas de finca; solo 5 tienen datos)
- **Precio:** `Valoración de inventarios.xlsx` → hoja `Sheet1`, encabezado en **fila 5**, columna `Vlr Und` (216 productos, cobertura 100 %)

## Reglas de apertura al 31-jul (J)

| Ámbito | J |
|---|---|
| **BODEGA PRINCIPAL** | Valor del archivo (`INVENTARIO FINAL`). Todo lo anterior se absorbe. |
| **FINCAS** | Remanente del archivo (`REMANENTES`) si existe; si no, **0** |
| **FINCAS — excepción (16 casos)** | Si con esa apertura el saldo quedaría negativo → `J = |delta de agosto|`, de modo que **la finca cierre exactamente en 0**. Total a reconocer: **374,74 unidades** |

## Corte de agosto (INVIOLABLE)
- Se conservan los movimientos con `movement_date >= 2026-08-01` **EXCEPTO los ajustes**.
- **Los AJUSTES se eliminan TODOS** (decisión del cliente, 21-ago): tanto las filas de `adjustments`
  como sus movimientos en `inventory_movements` (`related_document_type = 'App\Models\Adjustment'`).
  - `AJU-20260820-0001` NITRABOR +1.675 kg (fecha 21-jul) → se elimina (además ya quedaba absorbido)
  - `AJU-20260803-0001` ANTRACOL −7.600 g (fecha 03-ago) → **se elimina**
  - Consecuencia registrada: ANTRACOL en bodega quedará en **153.776 g** en vez de 146.176 g
    (se revierte la merma que se había registrado el 3-ago por conteo físico).
- Por tanto: `A` = suma de movimientos de agosto **excluyendo** los ligados a ajustes.
- Todo lo fechado `<= 2026-07-31` queda absorbido por la apertura.
- El corte es por **`movement_date`**, jamás por `created_at`.

## Escrituras (por producto + marca + ubicación)
```
K31 = kardex al 31-jul      A = delta de agosto      P = stock físico actual

1) UN movimiento al kardex, fechado 2026-07-31, cantidad (J − K31)
   type = entry si (J−K31) > 0, exit si < 0
   related_document_type = 'App\Models\Adjustment'  (+ fila real en `adjustments`)
   observations con "aumento"/"ajuste positivo" o "disminución"/"ajuste negativo"

2) Stock físico se FIJA a (J + A)   [valor absoluto, no delta]
   lote BASE-JUL-2026 · unit_price = precio del archivo · total_value = cantidad × precio
```
Resultado exigido: `kardex@31jul = J` · `kardex_hoy = J + A` · `físico = J + A`

## Precios
- **Opción A aprobada:** se re-precia con el valor del archivo. Se respalda el costo anterior.
- Si el archivo no trae precio para un producto → se conserva el costo actual y se reporta.

## Reglas duras
1. **NO convertir unidades.** Si la unidad del archivo ≠ `products.base_unit` → **falla dura de esa fila**, se reporta y no se procesa.
2. **Pre-flight bloqueante:** si algún triple da `J + A < 0` → **aborta toda la corrida**.
3. Granularidad **producto + marca + ubicación**.
4. `K31`, `A` y `P` se recalculan **dentro de la transacción** con `lockForUpdate()`.
5. Verificación **contra el archivo** (no tabla contra tabla), por triple. Si falla → rollback.
6. Idempotencia: `adjustment_number = REBASE-JUL26-####`, `batch_number = BASE-JUL-2026`. Abortar si ya existen (salvo `--force`).
7. Producto `1107` (PREZA): **se crea** en el catálogo antes de la carga.
8. Producto `9716` (CALFOS): vale **21.590** (columna INV del archivo), no 39.590.
9. Productos con stock en el sistema y ausentes del archivo → **el archivo manda** (van a 0), y se reportan.

## Cambio de fondo (aprobado, va en este mismo trabajo)
**"Orden Técnica" deja de acreditar stock a la finca.** Hoy `processInventoryMovements()` solo trata
como consumo el código `consumption`; se extiende a `technical_order`:
- Bodega: descarga (exit) ✅
- Movimiento registrado y trazable a la finca ✅
- Finca: **no se le suma stock** ✅
- Se crea la `Application` automática (ya existe ese comportamiento)

Motivo: 327 salidas por `technical_order` (425.142 unidades) acreditaron stock a fincas que en
realidad se aplicó al cultivo → 420.823 unidades fantasma. Sin este cambio el problema reaparece
en un mes.

## Ejecución
1. Simulación (`--dry-run`) contra la restauración del dump → Excel de revisión
2. Revisión con bodega/contabilidad
3. Congelamiento + snapshot + aplicación en producción
4. Verificación automática (rollback si falla)
5. Subir `ADJUSTMENTS_CLOSED_PERIOD_UNTIL=2026-07-31` **después** de la corrida
