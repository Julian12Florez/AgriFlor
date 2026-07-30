# Notas / dudas a responder — no son errores del sistema

Casos surgidos del análisis de `errores_prod.pdf` que **NO son fallas de software**: el sistema hizo lo correcto, pero el dato necesita una decisión del negocio o de Contabilidad. **No se modificó ningún dato de producción en estos dos casos.**

Cada sección trae un **mensaje consolidado** listo para reenviar al cliente / al área contable.

---

## NOTA 1 — La compra de QROP KS de 10.500 kg que "reabrió" mayo

### Qué pasó (hechos verificados en producción)

| Dato | Valor |
|---|---|
| Compra | `PUR-2026-747484` |
| Proveedor | PUNTO CARDINAL DE ORIENTE S.A.S |
| Fecha de la compra | **28/05/2026** |
| Entrega esperada | **29/05/2026** |
| Recepción | `REC-2026-000405`, lote #1, fechado **29/05/2026** por María González (Bodeguera) |
| Cantidad | **10.500,00 kg** de QROP KS 12-0-46+25 (cód. 1537) |
| **Cuándo se cargó al sistema** | **29/07/2026** (dos meses después) |

Efecto: el cierre de mayo de ese producto pasó a **45.050 kg**, mientras la cifra que se alineó con Siigo era **34.550 kg**.

### Por qué NO es un error del sistema

La fecha es **correcta**: es una compra real de mayo (facturada el 28/05, recibida el 29/05) y la bodeguera la fechó bien. El sistema simplemente registró lo que se le indicó.

Lo que quedó desactualizado es el **cierre alineado con Siigo**, porque esa alineación se hizo **antes** de que este documento existiera en el sistema. Hoy el mayo de AgriFlor está **más completo** que la cifra alineada, no más incorrecto.

Re-fecharla a julio **falsearía** una compra que físicamente entró en mayo, así que no se tocó.

### Mensaje consolidado (para reenviar)

> Sobre el cierre de mayo de QROP KS 12-0-46+25: revisamos y **no hay un error del sistema**. Lo que ocurrió es que la compra `PUR-2026-747484` a PUNTO CARDINAL DE ORIENTE S.A.S (10.500 kg, facturada el 28/05 y recibida el 29/05) **se cargó al sistema el 29 de julio**, es decir, después de que ya habíamos alineado el cierre de mayo con Contabilidad.
>
> Por eso mayo pasó de 34.550 kg a 45.050 kg en ese producto: no es que se haya movido nada, es que **entró un documento real de mayo que antes no estaba capturado**. La fecha con la que quedó registrado (29/05) es la correcta.
>
> Necesitamos que Contabilidad nos confirme una de dos:
> 1. **Esa factura pertenece a mayo en sus libros** → entonces hay que actualizar el cierre de mayo a 45.050 kg (el de AgriFlor ya está correcto y más completo).
> 2. **Esa factura la registraron en julio** → entonces la movemos a julio en AgriFlor para que ambos sistemas coincidan.
>
> Mientras nos responden **no modificamos nada**, para no falsear la fecha de entrada de la mercancía.

### Recomendación interna

Opción 1 (dejarla en mayo y actualizar el cierre) es lo correcto contablemente si la factura es de mayo. Además conviene acordar una **regla de cierre**: una vez conciliado un mes, cargar documentos retroactivos a ese mes debe requerir aprobación explícita (hoy el sistema lo permite en silencio; ver el punto de "candado de periodo" en el plan de corrección).

---

## NOTA 2 — Por qué el disponible en Nueva Salida no coincide con el saldo del Kardex (QROP KS)

### Qué pasó (hechos verificados en producción)

Para QROP KS 12-0-46+25 (cód. 1537) en BODEGA PRINCIPAL:

| Fuente | Valor |
|---|---|
| **Lotes físicos** (lo que se puede despachar) | **10.125,50 kg** en 6 lotes |
| **Saldo del Kardex** (suma de movimientos) | **13.575,00 kg** |
| **Diferencia** | **3.449,50 kg** |

Los 6 lotes: 5.873,00 · 3.050,00 · 550,00 · 252,50 · 250,00 · 150,00 kg.

La diferencia corresponde **exactamente** a dos movimientos cargados el 28/05/2026 sin documento asociado:

- entrada **3.045,00 kg** — *"Corrección de cierre mayo - conciliación con conteo físico"*
- entrada **404,50 kg** — *"Ajuste cierre mayo - conciliación con conteo físico organizado"*

**3.045,00 + 404,50 = 3.449,50 kg**

### Por qué NO es un error del sistema

El Kardex sí se construye con los movimientos (el razonamiento del cliente es correcto). Lo que ocurrió es que, al alinear el cierre de mayo con el conteo físico, esas dos correcciones se escribieron **como línea del Kardex** pero **no se crearon como lote** en el inventario.

Resultado: el Kardex las cuenta (saldo contable) y el formulario de salida no las ve (porque lee los lotes reales que se pueden despachar). **Las dos pantallas miden cosas distintas** y hoy no lo dicen con claridad.

Nota adicional: al sumar el disponible se omitieron dos lotes, el de **550 kg** y el de **3.050 kg**. Los seis lotes suman 10.125,50 kg, no 6.525,50.

### Mensaje consolidado (para reenviar)

> Sobre el caso de QROP KS: revisamos y **el Kardex no está mal calculado**. Efectivamente se construye con los movimientos, y ahí está la explicación.
>
> En mayo, cuando alineamos el cierre con el conteo físico, se registraron dos correcciones (**3.045,00 kg** y **404,50 kg**, total **3.449,50 kg**) que quedaron **como movimiento en el Kardex pero sin lote en el inventario**. Por eso:
>
> - El **Kardex** muestra **13.575,00 kg** → es el *saldo contable*, incluye esas correcciones.
> - El formulario de **Nueva Salida** ofrece **10.125,50 kg** → es el *stock físico real*, lo que hay en lotes y se puede despachar.
> - La diferencia (**3.449,50 kg**) es exactamente esas dos correcciones de mayo.
>
> Dos aclaraciones prácticas:
> 1. Al sumar el disponible se pasaron por alto dos lotes (**550 kg** y **3.050 kg**). Los seis lotes suman **10.125,50 kg**.
> 2. Vamos a **renombrar esas dos cifras en pantalla** ("stock físico" vs "saldo contable") para que no se lean como si debieran coincidir.
>
> Lo que necesitamos de ustedes: **un conteo físico de estos 7 productos** para decidir si esos 3.449,50 kg existen hoy en bodega (y entonces los materializamos como lote) o si ya se consumieron (y ajustamos el saldo contable): **QROP KS 12-0-46+25, KIESERITA, NITRABOR, TERRASSIL, BOROZINCO, COMBATRAN y KENDO**.
>
> Mientras tanto **no tocamos ningún dato**, para no inventar ni borrar existencias sin respaldo físico.

### Recomendación interna

Sí corregir la parte que **es** defecto de software: hoy dos pantallas del mismo kardex usan rótulos distintos (`Saldo Actual` en Inventario y `Stock Actual` en el reporte) para conceptos diferentes. Renombrar y mostrar ambas cifras es barato y sin riesgo. La reparación de datos queda **bloqueada** hasta el conteo físico.

---

## Resumen de qué se toca y qué no

| Caso | ¿Es error del sistema? | Acción |
|---|---|---|
| **E1** columna Destino muestra la fecha | **Sí** | Se corrige el código |
| **E2** remanentes contados como Compras | **Sí** | Se corrige el código (no hay datos corruptos) |
| **E3/E4** movimientos de junio en julio | **Sí (regresión)** | Se corrige el código + se reparan los 38 movimientos |
| **NOTA 1** compra de mayo cargada en julio | **No** | Pendiente de Contabilidad. Datos intactos |
| **NOTA 2** 3.449,50 kg de diferencia stock/kardex | **No** (el dato viene de la conciliación de mayo) | Pendiente de conteo físico. Datos intactos. Sí se mejoran los rótulos en pantalla |
