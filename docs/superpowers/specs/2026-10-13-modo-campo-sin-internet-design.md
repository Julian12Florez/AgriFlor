# Modo Campo: el módulo de tareas sin internet — diseño

Fecha: 2026-10-13 · Estado: **propuesto, pendiente de aprobación del cliente**.

## 1. Objetivo

Que el responsable de cada finca pueda trabajar el módulo de tareas (Rendimiento)
**sin internet hasta 2 días**, en iPhone o Android, y que al volver la señal todo se
sincronice **sin perder ni duplicar nada**, con la misma auditoría y las mismas
reglas que hoy.

Hoy casi nada se registra desde el campo: de 613 salidas, 2 son de un supervisor;
los 22 avances diarios los registró el administrador. El dato nace en la oficina,
tarde. Este diseño permite que nazca donde ocurre.

## 2. Decisiones del cliente (13-oct-2026)

| # | Pregunta | Decisión |
|---|---|---|
| — | Peor caso a soportar | **2 días** sin internet |
| — | Teléfonos | **iPhone y Android**, **uno por persona** |
| — | Alcance | **Módulo de tareas completo**: programar, tareas no programadas, avances, finalizar, fincas (trabajadores y lotes) |
| 1 | ¿Quién registra en cada finca? | **Solo el responsable de esa finca** |
| 2 | Programación que excede el cupo de trabajadores | **Se acepta marcada "sobrecupo"** para revisión |
| 3 | Dos avances que pasan del 100 % | **Tope 100 %**, el excedente se marca **"sospechoso"** |
| 4 | Finalizar / cancelar sin señal | **Finalizar sí; cancelar solo con señal** |
| 5 | "Trabajadores" | **El número de trabajadores de la finca y sus lotes** (no una lista con nombres) |
| 6 | Crear tareas del catálogo en campo | **No**: en campo se elige del catálogo; lo nuevo va como tarea no programada |
| 7 | Instalación | **Desde el navegador** (app instalable / PWA), sin tiendas |
| 8 | Fotos y ubicación | **Sí, diseñadas desde ya** |

## 3. Por qué por módulo y no toda la aplicación

El inventario es compartido: dos personas sin señal despachando el mismo producto
ven el mismo stock y lo dejan en negativo al sincronizar; reservas, FIFO y
aprobaciones necesitan el estado real del momento. Por eso **compras, salidas de
bodega, ajustes, inventario, perfiles e informes siguen siendo solo con internet**.

El módulo de tareas sí sirve sin señal porque cada finca tiene **un solo dueño**
(decisión 1): nadie más escribe sobre sus datos en esos dos días, así que los
choques son excepcionales y tienen regla (sección 7).

## 4. Qué se puede hacer sin internet

| Acción | Sin internet | Notas |
|---|---|---|
| Ver mis fincas, sus lotes, trabajadores, programaciones y avances | ✅ consulta | Con aviso "datos al 13/10, 6:00 a. m." |
| Programar una tarea (del catálogo) | ✅ | Código provisional hasta sincronizar (sección 7.1) |
| Registrar una tarea no programada | ✅ | Para labores que no están en el catálogo |
| Registrar avance diario | ✅ | Con fotos y ubicación opcionales |
| Finalizar una tarea | ✅ | |
| Editar número de trabajadores de la finca | ✅ | |
| Crear / editar lotes de la finca | ✅ | Requiere el permiso (sección 10) |
| Cancelar una tarea | ❌ solo con señal | Decisión 4 |
| Crear tareas del catálogo | ❌ solo con señal | Decisión 6 |
| Configuración de rendimiento, informes | ❌ solo con señal | Son de oficina |

## 5. Cómo se ve: "Modo Campo"

Una sección nueva, **pensada para el teléfono**, en `/campo`, con cuatro pantallas
de botones grandes:

1. **Mis fincas** — trabajadores, lotes y tareas activas de cada finca a cargo.
2. **Programar / tarea no programada.**
3. **Registrar avance** (con fotos y ubicación) y **finalizar**.
4. **Pendientes de envío** — lo capturado que aún no subió, lo que subió y lo que
   volvió "con problema" (con el motivo y cómo corregirlo).

Arriba, siempre visible: estado de conexión, "datos al …" y "N sin enviar".

**Por qué una sección nueva y no adaptar las pantallas actuales:** las pantallas
actuales consultan el servidor en cada clic y están hechas para escritorio.
Volverlas tolerantes a la falta de señal es más riesgoso y más largo que una
sección pequeña, enfocada y probada para trabajar sin conexión. La oficina sigue
usando las pantallas de siempre, con conexión, y ve lo que llega del campo.

## 6. Arquitectura

- **App instalable (PWA)** sobre el mismo frontend React: un *service worker*
  guarda la aplicación en el teléfono para que abra sin señal.
- **Base de datos local** en el teléfono (IndexedDB) con:
  - el **paquete de datos** de las fincas a cargo (catálogo de tareas, lotes,
    programaciones activas y recientes, avances recientes, trabajadores, permisos);
  - la **cola de envío**: cada acción del usuario es una *operación* con su
    identificador único, fecha de captura y estado.
- **Descarga del paquete**: automática cada vez que hay señal y la app está
  abierta, y con un botón "Preparar para campo". `GET /api/campo/paquete`.
- **Sincronización**: al volver la señal (o al abrir la app con señal) se envía la
  cola **en orden** en un lote. `POST /api/campo/sincronizar`. El servidor
  procesa cada operación en su propia transacción y devuelve el resultado de cada
  una: *aplicada*, *aplicada con marca* (sobrecupo / sospechoso) o *con problema*.
- **Fotos**: se suben aparte (`POST /api/campo/fotos`), después de su avance, para
  que una foto pesada no frene el resto.

## 7. Reglas de sincronización (integridad)

**Generales**
- **Nada se duplica.** Cada registro nace en el teléfono con su identificador
  único (el sistema ya usa UUID). Si una operación llega dos veces (señal que se
  corta a mitad del envío), el servidor la reconoce y responde con el resultado
  ya guardado, sin crearla otra vez. Se registra cada operación recibida en una
  tabla de operaciones de campo.
- **El servidor manda.** Vuelve a validar todo con las reglas de hoy: permisos,
  fechas, que la finca sea del usuario, que la tarea siga abierta.
- **Nada se pierde en silencio.** Lo rechazado queda en "Pendientes de envío →
  con problema" con el motivo; el usuario lo corrige y lo reenvía, o lo descarta.
- **Orden y dependencias.** Una programación creada sin señal y sus avances viajan
  en el mismo lote, en orden; si la programación vuelve con problema, sus avances
  quedan en espera con ella (no se pierden ni se aplican sueltos).
- **Doble fecha y dispositivo.** Cada registro guarda la fecha de captura en campo
  y la de sincronización, y el dispositivo; la auditoría muestra "capturado sin
  conexión el 10/10 6:15 a. m., sincronizado el 10/10 6:40 p. m.".
- **Reloj del teléfono.** Si la fecha de captura queda en el futuro respecto del
  servidor, o antes de la última descarga del paquete menos 3 días, la operación
  vuelve con problema "revise la fecha del teléfono".

**Por operación**

| Operación | Regla al sincronizar |
|---|---|
| 7.1 Programar tarea | Se crea con su código definitivo (PROG-XXXX); en el teléfono se ve "provisional" hasta entonces. Si excede el cupo de trabajadores propios → **se acepta marcada "sobrecupo"** (decisión 2). Si ya existe una programación activa de la misma tarea en el mismo lote con fechas solapadas → **con problema**, con la opción de pasar sus avances a la existente (evita contar doble). |
| 7.2 Tarea no programada | Igual que hoy (fecha de inicio no futura, tope de un mes). |
| 7.3 Avance diario | Se aplican en orden de fecha. Acumulado con **tope 100 %**; lo que exceda se guarda marcado **"sospechoso"** (decisión 3). Fecha: no futura y con el tope de un mes contado desde la **fecha de captura**, no desde la sincronización. Si la tarea fue cancelada en el servidor → con problema. |
| 7.4 Finalizar | Si la tarea ya está finalizada o cancelada en el servidor → con problema (informativo). |
| 7.5 Trabajadores de la finca | Si nadie lo cambió en el servidor desde la descarga → se aplica. Si cambió → con problema, mostrando ambos valores. |
| 7.6 Lotes | Crear: si ya existe un lote con el mismo nombre en la finca → con problema. Editar: misma regla que 7.5. |

## 8. Sesión sin internet

- La sesión dura hoy 4 horas y se puede renovar hasta **14 días** después: sobra
  para 2 días. Sin señal, la app **no saca al usuario** aunque la sesión venza; al
  volver la señal la renueva y sincroniza.
- Si la renovación ya no es posible (más de 14 días), pide la contraseña **sin
  perder la cola**.
- **No se puede cerrar sesión con pendientes sin enviar.**
- Los datos guardados en el teléfono quedan atados al usuario: si otra persona
  inicia sesión en ese teléfono, no ve ni envía lo del anterior.

## 9. Fotos y ubicación

- Hasta **3 fotos por avance** (propuesta), tomadas con la cámara o la galería,
  **comprimidas en el teléfono** (lado mayor 1600 px, ~300 KB) antes de guardarse.
- **Ubicación GPS opcional**: si el teléfono la da, se guarda con su precisión; si
  no, el avance se guarda igual.
- **Servidor**: en el volumen persistente que ya existe (`/var/www/storage`, que
  sobrevive a los despliegues). Estimado: 17 fincas × ~5 fotos/día × 300 KB ≈
  25 MB/día ≈ 9 GB/año; hay 48 GB libres. Se agrega al respaldo diario.
- En la oficina, el avance muestra sus fotos y un enlace al mapa.

## 10. Permisos

Se usan los mismos permisos de hoy (Perfiles); no se crea ninguno nuevo. El Modo
Campo muestra cada acción solo si el perfil tiene su permiso, y el servidor lo
vuelve a validar.

- El Supervisor ya tiene: programar, editar programación, finalizar, registrar
  avance, editar ubicaciones (trabajadores de la finca).
- **El Supervisor NO tiene crear ni editar lotes.** Para usar 7.6 el
  administrador debe marcarle *Lotes de finca → Crear / Editar* en Perfiles.
- Solo puede actuar sobre **las fincas de las que es responsable**. Hoy hay 18
  fincas con responsable (17 supervisores y 1 bodeguero); **2 fincas no tienen
  responsable** y no podrán usarse desde el campo hasta asignarles uno.

## 11. Particularidades de iPhone (instalación desde el navegador)

- La app **debe agregarse a la pantalla de inicio** (Safari → Compartir →
  "Agregar a inicio"); la app lo explica y no permite el modo campo desde la
  pestaña normal de Safari, donde Apple puede borrar los datos guardados.
- **iPhone no sincroniza en segundo plano**: envía cuando la app está abierta y
  hay señal. La app avisa "Abra AgriFlor con señal para enviar N registros" y
  muestra en rojo lo que lleva más de 24 h sin enviar.
- Se pide al navegador **almacenamiento persistente**.
- Se prueba en un iPhone y un Android reales antes de liberar.

## 12. Riesgos y límites

- **Teléfono perdido, dañado o borrado antes de sincronizar = lo no enviado se
  pierde.** Mitigación: sincroniza en cuanto hay señal y alerta lo que lleva más
  de 24 h sin enviar.
- Un iPhone usado desde la pestaña de Safari (no instalado) no garantiza los
  datos: por eso se exige instalar.
- La oficina ve lo del campo **cuando sincroniza**, no antes.

## 13. Fuera de alcance

Compras, salidas, recepciones, ajustes e inventario sin internet; trabajadores con
nombre (liquidación); cancelar tareas y crear tareas del catálogo sin señal;
publicación en App Store / Play Store.

## 14. Pruebas

- **Backend**: pruebas de cada regla de la sección 7 (operación repetida no
  duplica, sobrecupo, sospechoso, duplicado de tarea/lote, fecha del teléfono,
  sesión vencida, finca ajena, permisos).
- **Navegador sin conexión**: recorrido automático que corta la red, captura
  durante "dos días" simulados, reinicia la app, vuelve la señal y verifica en el
  servidor — en Chromium (Android) y WebKit (motor de Safari/iPhone).
- **Teléfonos reales**: un iPhone y un Android, con modo avión.

## 15. Entregas

1. **Base + avances**: app instalable, sesión de campo, paquete de datos, cola,
   "Pendientes de envío", registrar avance y finalizar.
2. **Programar** tareas y tareas no programadas.
3. **Fincas**: trabajadores y lotes.
4. **Fotos y ubicación.**

Cada entrega se despliega y verifica por separado (respaldo previo, pruebas,
verificación en producción).
