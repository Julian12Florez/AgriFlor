# Errores reportados en producción — AgriFlor (fuente: errores_prod.pdf, 28-29/07/2026)

Reportados por el cliente (Juan Camilo Aguacates) sobre datos REALES de producción.

---

## E1 — La columna "Destino" de Salidas muestra una FECHA, no la ubicación

**Reporte textual:** *"una mejora sera que puede salir la ubicacion de la salida? a donde se fue? es que sale es la fecha"*

**Evidencia (captura 1):** pantalla `Salidas de Productos` (ruta `/outputs`). La columna encabezada **"Destino"** muestra un icono de ubicación seguido de un valor tipo **`24/08/2026` / `24/09/2026`** (una fecha) en TODAS las filas visibles (SAL-20260624-0010, -0016, -0018, -0002, -0003, -0004). El usuario no puede saber a qué ubicación se fue la salida.

**Interpretación:** no es solo una mejora: la columna rotulada "Destino" está pintando la fecha. Falta el nombre de la ubicación destino (y, según el tipo de salida, podría ser lote/finca).

**Área probable:** `frontend/src/pages/outputs/Outputs.tsx` (definición de columnas mobile/desktop) y/o el campo que expone `ProductOutputResource` (¿`destination_location_name` viene vacío y se cae a otro valor?).

---

## E2 — Los remanentes de finca se cuentan como COMPRAS en el Inventario Mensual

**Reporte textual:** *"se esta haciendo remanentes desde las fincas y quedan registrados como compras / y deberia de quedar como remanentes"*

**Evidencia (captura 2):** `Inventario Mensual`, July 2026, BODEGA PRINCIPAL, filtro `sulfato de m`. Fila **SULFATO DE MAGNESIO (cód. 1380)**: la columna **Compras = 92,9** y **Total Mov. = 92,9**; las columnas de fincas y **Remanente** aparecen vacías/en guion. Tarjetas: `Total Compras (July) 88.199,35`, `Total Enviado a Fincas 80.630,54`.

**Interpretación:** un movimiento de entrada en bodega originado por un **remanente** (salida tipo `remanente` de finca → bodega, recibida por el flujo de recepción) está cayendo en la columna **Compras** en vez de **Remanente**.

**Hipótesis de causa raíz (a validar):** en `InventoryController::monthlyReport`, `purchases` se calcula como `type='entry'` + ubicación + rango + `(related_document_type LIKE '%Reception' OR LIKE '%Purchase' OR IS NULL)`. Un remanente se recibe vía recepción, así que su entrada lleva `related_document_type='App\Models\Reception'` y **matchea el filtro de compras**. En paralelo, `returns` (columna Remanente) se calcula aparte desde `product_outputs` con `outputQtyByDestination(..., 'remanente', ...)`. Resultado esperado: el mismo movimiento se cuenta DOS veces (en Compras y en Remanente) o aparece solo en Compras. Hay que verificar por qué en la captura Remanente sale vacío.

**Área probable:** `backend/app/Http/Controllers/Api/InventoryController.php` (bloques `purchases` ~1454-1470 y `returns`/`outputQtyByDestination`).

---

## E3 — Recepción fechada en JUNIO aparece en JULIO

**Reporte textual:** *"por que sera que esta salida quedo en julio cuando se le colocalor las fechas de junio"*

**Evidencia (captura 3):** modal `Recepción REC-2026-000371 - Salida SAL-20260728-0029`. Tipo: Salida. Estado: Completada. Destino: **Melon**. Producto **TERRASSIL SILICIO** (Sin Marca): Esperado 140,00 kg / Recibido 140,00 kg / Pendiente 0,00. **Historial de Recepciones: "Recepción #1 — 17/06/2026 20:00 — SUPERVISOR MELON — TERRASSIL SILICIO 140,00 kg Bueno"**.

**Interpretación:** el lote de recepción está fechado **17/06/2026** (junio) pero el efecto quedó en julio.

**Hipótesis a validar:** ¿qué `movement_date` tienen los `inventory_movements` de esa recepción? Si es julio, la fecha del lote no se propagó (o el registro es anterior al despliegue del campo `movement_date`, 16/07/2026, y quedó con `DATE(created_at)` por el backfill). Verificar contra datos reales.

**Área probable:** `backend/app/Http/Controllers/Api/ReceptionController.php` (propagación de `reception_date` a `movement_date` en `createReceptionWithBatch`/`addBatch`), y el backfill de la migración `2026_07_15_120000_add_movement_date_to_inventory_movements`.

---

## E4 — Salida con fecha de JUNIO aparece en el informe de JULIO

**Reporte textual:** *"se hizo todo el proceso para que quedara la salida en junio y quedo en julio"*

**Evidencia (captura 4):** `Salidas de Productos`, fila **SAL-20260729-0023**, detalle expandido: **Fecha de salida: 15/06/2026**, Responsable María González (Bodeguera), Producto **SAFERMIX** (Sin Marca) 818 g, Entregado 818 g, Lote `REC-a31d4f81-?`. Segunda captura: `Inventario Mensual` de **July 2026**, BODEGA PRINCIPAL, filtro `SAFERM`: fila **SAFERMIX (cód. 2405)** con `Inv. Inicial 3.000`, `Inv. Final (cierre mes) 3.000`, `Stock actual (bodega) 3.000`, Diferencia 3.000(?), y la salida no aparece en junio.

**Interpretación:** la salida se fechó 15/06 pero su movimiento cayó en julio. Muy probablemente porque el movimiento de salida toma su fecha del **lote de recepción** (cuándo se recibió/confirmó la salida, en julio) y NO de la **fecha de la salida** (`product_outputs.output_date`, junio) que es la que el usuario configuró.

**Decisión de producto a resolver:** para una salida/traslado, ¿qué fecha debe llevar el movimiento de inventario: la fecha de la SALIDA (lo que espera el usuario) o la fecha de la RECEPCIÓN de esa salida? E3 y E4 apuntan al mismo problema desde dos ángulos.

**Área probable:** `ReceptionController::processInventoryMovements` / `createExitMovement` / `createEntryMovement` (qué `movement_date` reciben), y `ProductOutput::output_date`.

---

## E5 — El stock disponible por lotes en el formulario de Salida NO cuadra con el saldo del Kardex

**Reporte textual:** *"TENEMOS un caso particular con el qrops ks ahi sumamos lo que sale disponible 5873+252.5+150+250 y nos da 6525.5 y en el kardex nos sale [10.525,00]"*

**Evidencia (captura 5a):** modal `Nueva Salida`, Tipo `Orden Técnica`, Fecha 28/07/2026, Origen `BODEGA PRINCIPAL`, Responsable Ana Martínez (Compras). Desplegable de producto al escribir `QROP` muestra las opciones (producto ordenado por vencimiento):
- `QROP KS 12-0-46+25 [1537] - Sin Marca - Sin vencimiento - 2...`
- `QROP KS 12-0-46+25 [1537] - Sin Marca - 28/03/2026 - 150,0...`
- `QROP KS 12-0-46+25 [1537] - Sin Marca - 28/03/2027 - 5.873...`
- `QROP KS 12-0-46+25 [1537] - Sin Marca - 28/03/2027 - 550,0...`
- `QROP KS 12-0-46+25 [1537] - Sin Marca - 28/03/2027 - 250,0...`

**Evidencia (captura 5b):** `Kardex — QROP KS 12-0-46+25` (cód. 1537, Fertilizante): **Total Entradas 50.302,00 kg**, **Total Salidas 39.777,00 kg**, **Saldo Actual 10.525,00 kg**, 20 movimientos. Valor total $31.604.136,00. Movimientos visibles (Sin Marca / BODEGA PRINCIPAL): 20/04 +4.852,50 (saldo 4.852,50, precio 0,00); 03/05 −100,00 (4.752,50); 05/05 −1.300,00 (3.452,50, precio 3.880,00); 07/05 +7.500,00 (10.952,50, $4.632); 10/05 +10.000,00 (20.952,50); 12/05 −2.288,00 (18.792,50); 18/05 −6.601,00 (12.100,50); 22/05 +12.000,00 (24.100,50); 22/05 +10.505,00 (38.600,00); 25/05 −9.900,00 (31.106,50)...

**Interpretación:** el disponible que suma el usuario desde el selector de lotes (≈6.525,5 kg) difiere del saldo del kardex (10.525,0 kg) en **≈3.999,5 kg**. Dos fuentes distintas: el selector lee la tabla `inventory` (posiblemente descontando lo comprometido por salidas aprobadas no recibidas, y/o filtrando por estado/vencimiento), mientras el kardex deriva el saldo de `inventory_movements` (entradas − salidas, con conversión de unidades). Es la divergencia conocida `inventory` (físico) vs `inventory_movements` (histórico).

**Hipótesis a validar con datos:** (a) lotes excluidos del selector por estado `expired`/`near_expiry` o por cantidad ≤0; (b) stock comprometido por salidas aprobadas no recibidas restado en el selector pero no en el kardex; (c) movimientos con unidades no convertidas inflando el saldo del kardex; (d) lotes borrados por FIFO cuyo histórico sigue en movimientos.

**Área probable:** `productsApi.getForOutputs` / endpoint que alimenta el selector (¿`ProductController::getForOutputs`?), `InventoryController::productKardex`, y el cálculo de comprometido en `ProductOutputController`.

---

## Requisitos transversales del cliente

1. **Corregir la FUENTE del error** (el código) **y los DATOS de los casos reportados** (las filas ya afectadas en producción).
2. **Validar que el nuevo módulo de Ajustes de Inventario no introdujo errores de estos mismos tipos**, en particular:
   - E1: ¿la UI de ajustes muestra correctamente las ubicaciones (no fechas)?
   - E2: ¿los ajustes se clasifican en la columna correcta del Inventario Mensual (no como Compras)?
   - E3/E4: ¿el `movement_date` de los movimientos de ajuste respeta la fecha que registra el usuario?
   - E5: ¿los ajustes agravan la divergencia `inventory` vs `inventory_movements`?
