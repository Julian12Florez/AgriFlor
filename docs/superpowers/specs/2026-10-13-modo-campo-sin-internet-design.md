# Rendimiento con y sin internet — diseño

Fecha: 2026-10-13 · Estado: **aprobado por el cliente (13-oct-2026)** · Plan: `docs/superpowers/plans/2026-10-13-rendimiento-con-y-sin-internet.md`.

## 1. Objetivo

Que el responsable de cada finca use **las mismas pantallas de Rendimiento** que
usa la oficina, **con o sin internet** (hasta 2 días sin señal), en iPhone o
Android, y que al volver la señal todo se sincronice **sin perder ni duplicar
nada**, con la misma auditoría y las mismas reglas de hoy.

Hoy casi nada se registra desde el campo: de 613 salidas, 2 son de un supervisor;
los 22 avances diarios los registró el administrador. El dato nace en la oficina,
tarde. Este diseño permite que nazca donde ocurre.

## 2. Decisiones del cliente (13-oct-2026)

| # | Pregunta | Decisión |
|---|---|---|
| — | Peor caso a soportar | **2 días** sin internet |
| — | Teléfonos | **iPhone y Android**, **uno por persona** |
| — | Alcance | **Módulo de tareas completo**: programar, tareas no programadas, avances, finalizar, finca (trabajadores y lotes) |
| — | Diseño | **Las mismas pantallas con y sin conexión** (no una sección aparte) |
| 1 | ¿Quién registra en cada finca? | **Solo el responsable de esa finca** |
| 2 | Programación sin señal que excede el cupo de trabajadores | **Se acepta marcada "sobrecupo"** para revisión |
| 3 | Avances sin señal que pasan del 100 % | **Tope 100 %**, el excedente se marca **"sospechoso"** |
| 4 | Finalizar / cancelar sin señal | **Finalizar sí; cancelar solo con señal** |
| 5 | "Trabajadores" | **El número de trabajadores de la finca y sus lotes** (no una lista con nombres) |
| 6 | Crear tareas del catálogo en campo | **No**: en campo se elige del catálogo; lo nuevo va como tarea no programada |
| 7 | Instalación | **Desde el navegador** (app instalable / PWA), sin tiendas |
| 8 | Fotos y ubicación | **Sí, desde el inicio del diseño** |
| 9 | Informes sin señal | **Opción A**: última versión descargada, con aviso |
| 10 | PIN para abrir la app | **No** (basta el bloqueo del teléfono) |
| 11 | Ambiente de pruebas | **En el mismo servidor** (API en `/staging/`, frontend en `staging.agriflor.pages.dev`) |

## 3. Por qué solo el módulo de tareas

El inventario es compartido: dos personas sin señal despachando el mismo producto
ven el mismo stock y lo dejan en negativo al sincronizar; reservas, FIFO y
aprobaciones necesitan el estado real del momento. Por eso **compras, salidas,
recepciones, ajustes, inventario, perfiles e informes siguen siendo solo con
internet**.

El módulo de tareas sí sirve sin señal porque cada finca tiene **un solo dueño**
(decisión 1): nadie más escribe sobre sus datos en esos dos días, así que los
choques son excepcionales y tienen regla (sección 7).

## 4. Cómo se comportan las pantallas

**Con señal: exactamente como hoy.** Al guardar se envía al instante; si el
servidor rechaza algo, el error aparece en el momento.

**Sin señal:**

| Pantalla | Sin internet |
|---|---|
| **Registro Diario** | ✅ Completa: ver el panel del día, registrar avance (con fotos y ubicación), finalizar, registrar tarea no programada. El panel se calcula en el teléfono con los datos guardados. |
| **Programación** | ✅ Ver y crear programaciones; finalizar. La disponibilidad de trabajadores se calcula en el teléfono. **Cancelar** pide señal (decisión 4). |
| **Mi finca** (recuadro nuevo dentro de Registro Diario) | ✅ Editar número de trabajadores y crear/editar lotes. |
| **Catálogo de tareas** | 👁 Solo consulta. Crear o editar tareas del catálogo pide señal (decisión 6). |
| **Informes de rendimiento** | 👁 **Última versión descargada** (decisión 13-oct, opción A), con el aviso "Datos al 13/10 6:00 a. m. · no incluye lo pendiente de envío". Si una pestaña nunca se descargó: "Disponible cuando haya conexión". |
| **Configuración de rendimiento** | ❌ Solo con señal. |
| **Resto del sistema** | ❌ Muestra "Esta sección necesita conexión"; los botones de guardar quedan desactivados. |

En todas las pantallas de Rendimiento, una franja fija indica el estado:
"Sin conexión · datos al 13/10 6:00 a. m. · 5 pendientes de envío". Lo guardado
sin señal se ve en las listas marcado **"pendiente de envío"**; las programaciones
creadas sin señal muestran un código **provisional** hasta sincronizar.

Una pantalla nueva y sencilla, **Pendientes de envío**, lista lo que falta subir,
lo que ya subió y lo que volvió **"con problema"** con su motivo, para corregirlo
y reenviarlo o descartarlo.

**En el teléfono**, las tablas de Programación y Registro Diario se muestran como
tarjetas (las pantallas hoy no están adaptadas a celular). En el computador de la
oficina se siguen viendo como hoy.

**Mi finca:** hoy trabajadores y lotes se editan en Datos Maestros → Ubicaciones,
que los supervisores no ven. Por eso se agrega el recuadro dentro de Registro
Diario, solo con las fincas a cargo del usuario.

## 5. Arquitectura

- **App instalable (PWA)** sobre el mismo frontend: un *service worker* guarda la
  aplicación en el teléfono para que abra sin señal.
- **Copia local de los datos** (IndexedDB) usando la librería que las pantallas ya
  usan (TanStack Query) con persistencia: catálogo, fincas a cargo, sus lotes y
  trabajadores, programaciones activas y recientes, avances recientes, permisos.
  Se actualiza sola cada vez que hay señal y la app está abierta.
- **Cola de envío**: cada acción guardada es una operación con su identificador
  único y su fecha de captura. Con señal se envía de inmediato; sin señal espera y
  sale **en orden** al reconectar (también después de cerrar y reabrir la app).
- **El servidor usa los mismos endpoints de hoy**, ampliados para aceptar el
  identificador que trae el teléfono, la fecha de captura y el dispositivo, y para
  responder igual si la misma operación llega dos veces.
- **Fotos**: se suben aparte, después de su avance, para que una foto pesada no
  frene el resto.

## 6. Sesión sin internet

- La sesión dura hoy 4 horas y se puede renovar hasta **14 días** después: sobra
  para 2 días. Sin señal, la app **no saca al usuario** aunque la sesión venza; al
  volver la señal la renueva y envía.
- Si la renovación ya no es posible (más de 14 días), pide la contraseña **sin
  perder la cola**.
- **No se puede cerrar sesión con pendientes sin enviar.**
- Los datos del teléfono quedan atados al usuario: si otra persona inicia sesión
  en ese teléfono, no ve ni envía lo del anterior.

## 7. Reglas de sincronización (integridad)

**Generales**
- **Nada se duplica.** Cada registro nace en el teléfono con su identificador
  único (el sistema ya usa UUID). Si una operación llega dos veces (señal que se
  corta a mitad del envío), el servidor la reconoce y responde con lo ya guardado.
- **El servidor manda.** Vuelve a validar todo con las reglas de hoy: permisos,
  fechas, que la finca sea del usuario, que la tarea siga abierta.
- **Nada se pierde en silencio.** Lo rechazado queda en "Pendientes de envío →
  con problema" con el motivo.
- **Orden y dependencias.** Las operaciones de una finca se envían una tras otra.
  Si una programación creada sin señal vuelve con problema, sus avances quedan en
  espera con ella (no se aplican sueltos).
- **Doble fecha y dispositivo.** Cada registro guarda la fecha de captura y la de
  sincronización, y el dispositivo; la auditoría muestra "capturado sin conexión
  el 10/10 6:15 a. m., sincronizado el 10/10 6:40 p. m.".
- **Reloj del teléfono.** Si la fecha de captura queda en el futuro respecto del
  servidor, o es anterior a la última descarga de datos del teléfono (con un día
  de margen), la operación vuelve con problema "revise la fecha del teléfono".
  No hay tope de días sin señal: quien pase más de 2 días no pierde lo capturado.

**Por operación** (las marcas "sobrecupo" y "sospechoso" aplican solo a lo
capturado sin señal; con señal se comporta como hoy)

| Operación | Regla al sincronizar |
|---|---|
| Programar tarea | Recibe su código definitivo (PROG-XXXX). Si excede el cupo de trabajadores propios → **se acepta marcada "sobrecupo"**. Si ya existe una programación activa de la misma tarea en el mismo lote con fechas solapadas → **con problema**, con la opción de pasar sus avances a la existente. |
| Tarea no programada | Igual que hoy (inicio no futuro, tope de un mes). |
| Avance diario | Se aplican en orden de fecha. Acumulado con **tope 100 %**; lo que exceda se guarda marcado **"sospechoso"**. Fecha: no futura y con el tope de un mes contado desde la **fecha de captura**. Si la tarea fue cancelada en el servidor → con problema. |
| Finalizar | Si ya está finalizada o cancelada en el servidor → con problema (informativo). |
| Trabajadores de la finca | Si nadie lo cambió en el servidor desde la descarga → se aplica. Si cambió → con problema, mostrando ambos valores. |
| Lotes | Crear: si ya existe uno con el mismo nombre en la finca → con problema. Editar: misma regla que trabajadores. |

## 8. Fotos y ubicación

- Hasta **3 fotos por avance**, de la cámara o la galería, **comprimidas en el
  teléfono** (lado mayor 1600 px, ~300 KB).
- **Ubicación GPS opcional**: si el teléfono la da, se guarda con su precisión; si
  no, el avance se guarda igual.
- **Servidor**: en el volumen persistente que ya existe (`/var/www/storage`, que
  sobrevive a los despliegues). Estimado: 17 fincas × ~5 fotos/día × 300 KB ≈
  25 MB/día ≈ 9 GB/año; hay 48 GB libres. Se agrega al respaldo diario.
- En la oficina, cada avance muestra sus fotos y un enlace al mapa.

## 9. Permisos

Se usan los permisos de hoy (Perfiles); no se crea ninguno nuevo. Cada botón se
muestra solo si el perfil tiene su permiso, y el servidor lo vuelve a validar.

- El Supervisor ya tiene: programar, editar programación, finalizar, registrar
  avance, editar ubicaciones (trabajadores de la finca).
- **El Supervisor NO tiene crear ni editar lotes.** Para usar "Mi finca → lotes"
  el administrador debe marcarle *Lotes de finca → Crear / Editar* en Perfiles.
- Solo puede actuar sobre **las fincas de las que es responsable**. Hoy 18 fincas
  tienen responsable (17 supervisores y 1 bodeguero); **2 fincas no tienen** y no
  podrán usarse desde el campo hasta asignarles uno.

## 10. iPhone (instalación desde el navegador)

- La app **debe agregarse a la pantalla de inicio** (Safari → Compartir →
  "Agregar a inicio"); la app lo explica, y el trabajo sin señal solo se habilita
  instalada (en la pestaña de Safari, Apple puede borrar los datos guardados).
- **iPhone no envía en segundo plano**: envía con la app abierta y con señal. La
  app avisa "Abra AgriFlor con señal para enviar N registros" y marca en rojo lo
  que lleva más de 24 h sin enviar.
- Se pide al navegador **almacenamiento persistente**.

## 11. Riesgos y límites

- **Teléfono perdido, dañado o borrado antes de sincronizar = lo no enviado se
  pierde.** Mitigación: envía en cuanto hay señal y alerta lo que lleva más de 24 h
  sin enviar.
- **Pantallas de uso diario de la oficina cambian por dentro.** Mitigación:
  pruebas automáticas de cada pantalla con y sin señal antes de cada despliegue, y
  ambiente de pruebas separado de producción.
- La oficina ve lo del campo **cuando sincroniza**, no antes.

## 12. Fuera de alcance

Compras, salidas, recepciones, ajustes e inventario sin internet; trabajadores con
nombre (liquidación); cancelar tareas y crear tareas del catálogo sin señal;
informes de rendimiento recalculados sin señal (se muestra la última versión descargada); PIN de la app; publicación en App Store / Play Store.

## 13. Prerrequisitos

1. **Ambiente de pruebas** en el mismo servidor (copia de la base, otra
   dirección), para probar en teléfonos reales sin tocar producción. El servidor
   tiene 2,4 GB de memoria y 48 GB de disco libres.
2. **Un iPhone y un Android** para las pruebas reales.
3. En Perfiles: dar a Supervisor *Lotes de finca → Crear / Editar*.
4. Asignar responsable a las 2 fincas que no lo tienen (si se van a usar).

## 14. Pruebas

- **Servidor**: cada regla de la sección 7 (operación repetida no duplica,
  sobrecupo, sospechoso, duplicado de tarea o lote, fecha del teléfono, sesión
  vencida, finca ajena, permisos).
- **Pantallas con y sin señal**: recorridos automáticos que cortan la red,
  capturan durante "dos días" simulados, cierran y reabren la app, vuelven la
  señal y verifican en el servidor; en Chromium (Android) y WebKit (motor de
  Safari/iPhone). Los mismos recorridos con señal confirman que la oficina no
  cambió.
- **Teléfonos reales**: un iPhone y un Android en modo avión.
- **Piloto**: una semana con 1 o 2 fincas antes de extenderlo a todas.

## 15. Entregas

1. **Base + Registro Diario**: app instalable, copia local, cola, franja de
   estado, Pendientes de envío, sesión sin señal; en Registro Diario, registrar
   avance y finalizar, en formato celular.
2. **Programación, tareas no programadas e informes**: crear programaciones y
   tareas no programadas, disponibilidad local, formato celular; informes con la
   última versión descargada.
3. **Mi finca**: trabajadores y lotes.
4. **Fotos y ubicación.**

Cada entrega se despliega y verifica por separado (respaldo previo, pruebas,
verificación en producción).

## 16. Estimación (con agentes de IA en paralelo)

| | |
|---|---|
| Tiempo de IA | 27–47 h, con 2 a 4 agentes en paralelo por entrega más un revisor |
| Calendario | 5–7 días hábiles de construcción + 1 semana de piloto; peor caso 3 semanas |
| Costo de IA (equivalente API, Claude Opus 5.5) | USD 300–600; peor caso ~USD 800 |
| Infraestructura | USD 0 adicional (servidor actual, sin tiendas) |
| Tiempo del responsable | 6–10 h (aprobaciones, pruebas en teléfonos, piloto) |
