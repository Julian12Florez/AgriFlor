# Rendimiento con y sin internet — plan de implementación

> **Para agentes:** SUB-SKILL REQUERIDA: usar superpowers:subagent-driven-development (recomendado) o superpowers:executing-plans para ejecutar este plan tarea por tarea. Los pasos usan casillas (`- [ ]`) para el seguimiento.

**Objetivo:** que el responsable de cada finca use las pantallas de Rendimiento con o sin internet (hasta 2 días), en iPhone o Android, y que todo se sincronice al volver la señal sin perder ni duplicar nada.

**Arquitectura:** las pantallas existentes leen de una copia local persistida (TanStack Query + IndexedDB) y escriben a través de una cola local de operaciones (Dexie) que se envía de inmediato con señal y en orden al reconectar. El backend usa los mismos endpoints, ampliados con idempotencia (`Idempotency-Key`), identificador del registro generado en el teléfono, fecha de captura y dispositivo. La app se vuelve instalable (PWA) con `vite-plugin-pwa`.

**Stack:** Laravel 11 (PHP 8.2), MySQL 8, React 19 + TypeScript + Vite 7 + Ant Design 5, TanStack Query 5, `vite-plugin-pwa` (ya en package.json), `dexie`, `@tanstack/query-sync-storage-persister` o `@tanstack/query-async-storage-persister` + `idb-keyval`, Python Playwright (Chromium + WebKit) para recorridos.

**Diseño:** `docs/superpowers/specs/2026-10-13-modo-campo-sin-internet-design.md` (se ejecuta junto con este plan).

## Restricciones globales

- Peor caso: **2 días** sin internet; teléfonos **iPhone y Android**, **uno por persona**; instalación **desde el navegador** (sin tiendas); **sin PIN**.
- **Con señal, cada pantalla se comporta exactamente como hoy** (errores inmediatos, mismas reglas).
- Solo el **responsable de la finca** (`locations.responsible_user_id`) actúa sobre ella desde el teléfono.
- Sin señal: crear programaciones y tareas no programadas, registrar avances, finalizar, editar trabajadores y lotes de **su** finca. **Cancelar** y **crear tareas del catálogo** piden señal.
- Marcas solo para lo capturado sin señal: **"sobrecupo"** (programación que excede trabajadores propios) y **"sospechoso"** (avance que lleva el acumulado por encima de 100 %, acumulado con tope 100 %).
- **Informes de rendimiento sin señal: opción A** — última versión descargada, con el aviso "datos al … · no incluye lo pendiente de envío".
- Tope de fecha de un mes contado desde la **fecha de captura**. Reloj del teléfono: captura con fecha futura (más de 5 min respecto del servidor) o **anterior a la última descarga de datos del teléfono menos un día** → "con problema: revise la fecha del teléfono". (No hay tope por días sin señal: quien pase más de 2 días sin señal no pierde lo capturado.)
- Sesión: no se cierra sin señal; se renueva sola al volver la señal (ventana de 14 días, `JWT_REFRESH_TTL=20160`); **no se puede cerrar sesión con pendientes**.
- Fotos: hasta **3 por avance**, comprimidas en el teléfono (lado mayor **1600 px**, ~**300 KB**); GPS opcional con precisión; guardadas en `/var/www/storage` (volumen persistente) y en el respaldo diario.
- Toda escritura nueva del API lleva su `permission:` y se declara en `tests/Fixtures/route_access_antes_de_perfiles.json` (ver memoria del proyecto).
- Todo modelo auditable nuevo usa el trait `Auditado` + alias en el morph map; un fallo de auditoría nunca corta la operación.
- Cada entrega: respaldo de producción antes de desplegar, suite completa (única falla aceptada: `TransferenciaBugPreciseTest`), verificación en producción.

## Foco de revisión (lo que más puede fallar y ninguna prueba "natural" cubre)

1. **Señal intermitente a mitad de un envío** (el servidor guardó pero la respuesta no llegó) → al reintentar no debe duplicar. Prueba: Tarea 1.1 `test_la_misma_operacion_dos_veces_no_duplica`.
2. **La app se cierra o el teléfono se reinicia con pendientes** → al reabrir sin señal la cola sigue ahí y se envía después. Prueba: Tarea 1.10, recorrido `cola_sobrevive_al_reinicio`.
3. **Reloj del teléfono equivocado** → no se aceptan fechas futuras ni muy viejas. Prueba: Tarea 1.2 `test_captura_con_fecha_futura_vuelve_con_problema`.
4. **Abrir la app sin señal con la sesión vencida** → no manda a la pantalla de ingreso. Prueba: Tarea 1.10, recorrido `abre_sin_senal_con_sesion_vencida`.
5. **Una pantalla de oficina cambia de comportamiento con señal** → los recorridos con señal deben dar lo mismo que hoy. Prueba: Tarea 1.10, recorrido `oficina_con_senal_igual_que_hoy`.

---

## Cómo se ejecuta este plan

### Quién hace qué

| Rol | Hace |
|---|---|
| **Julián** | Aprueba el plan y cada entrega; hace los ajustes de configuración (Perfiles, responsables de finca); prueba en el iPhone y el Android con el guion que le entrego; coordina el piloto con las fincas; da el "sí, a producción". |
| **Claude (hilo principal)** | Escribe los contratos entre piezas antes de lanzar agentes; lanza y coordina los agentes en paralelo; integra; corre la suite y los recorridos; revisa; despliega a pruebas y a producción con respaldo; verifica en producción; informa. |
| **Agentes (en paralelo)** | Implementan cada pista de la entrega con pruebas primero (TDD), sin commit ni despliegue. Un agente revisor independiente revisa cada entrega antes de desplegar. |

### Ciclo de cada entrega

1. Julián escribe: **"ejecuta la entrega N del plan"**.
2. Claude escribe/actualiza el contrato de la entrega (tipos y endpoints) y lanza las pistas en paralelo.
3. Claude integra, corre suite + recorridos (Chromium y WebKit), lanza el revisor y aplica sus hallazgos.
4. Claude despliega al **ambiente de pruebas** y le entrega a Julián el **guion de prueba en teléfono**.
5. Julián prueba en iPhone y Android (modo avión) y responde **"aprobado"** o lo que encontró.
6. Claude toma respaldo, despliega a **producción**, verifica y reporta.

### Lista de tareas de Julián

- [ ] **J1.** Aprobar este plan.
- [ ] **J2.** Tener a mano un **iPhone** y un **Android** para las pruebas de cada entrega (decir quién los tendrá).
- [ ] **J3.** Si Claude lo pide en la Entrega 0: en su PC, `sudo npx playwright install-deps webkit` (dependencias del sistema para el motor de Safari).
- [ ] **J4.** Antes de la Entrega 3: en **Administración → Perfiles → Supervisor**, marcar *Datos Maestros → Lotes de finca → Crear* y *Editar*.
- [ ] **J5.** (Opcional) Asignar responsable a las 2 fincas activas que no lo tienen, si se van a usar desde el campo.
- [ ] **J6.** En cada entrega: ejecutar el guion de prueba en los dos teléfonos y responder "aprobado" o lo encontrado.
- [ ] **J7.** Elegir 1–2 fincas y supervisores para el **piloto de una semana** después de la Entrega 2, y avisarles que instalen la app (Claude le da las instrucciones de instalación para iPhone y Android).
- [ ] **J8.** Al cerrar el piloto: decidir si se extiende a todas las fincas.

### Pistas en paralelo por entrega (Claude)

| Entrega | Pistas de agentes en paralelo | Revisor |
|---|---|---|
| 0. Ambiente de pruebas | Claude directamente (toca el servidor compartido) | — |
| 1. Base + Registro Diario | A: backend idempotencia y reglas de avance · B: base sin conexión (PWA, sesión, copia local, cola, franja, Pendientes) · C: Registro Diario sin conexión y en celular · D: recorridos con y sin señal | 1 |
| 2. Programación + no programadas + informes (A) | A: backend creación sin señal (sobrecupo, duplicado, código) · B: Programación y no programadas sin conexión y en celular, informes con última versión · C: recorridos | 1 |
| 3. Mi finca | A: backend trabajadores y lotes (conflicto por cambio en servidor) · B: recuadro Mi finca + recorridos | 1 |
| 4. Fotos y ubicación | A: backend fotos (subida, almacenamiento, respaldo, vista oficina) · B: cámara, compresión, GPS, cola de fotos + recorridos | 1 |

Las Entregas 2 a 4 se detallan a nivel de pasos (como la Entrega 1) **al iniciarlas**, con lo aprendido en la anterior; aquí quedan sus tareas, contratos y criterios de aceptación.

---

## Estructura de archivos

**Backend (nuevos)**
- `backend/database/migrations/2026_10_14_100000_create_field_operations_table.php` — registro de operaciones recibidas (idempotencia).
- `backend/database/migrations/2026_10_14_100100_add_field_capture_columns.php` — `captured_at`, `captured_offline`, `device_id` en `task_schedules` y `task_daily_logs`; `over_capacity` en `task_schedules`.
- `backend/app/Http/Middleware/IdempotentFieldOperation.php` — responde lo ya guardado si la operación se repite.
- `backend/app/Support/Campo/CapturaDeCampo.php` — lee y valida `captured_at`, `captured_offline`, `device_id` de la petición.
- `backend/app/Models/FieldOperation.php`
- `backend/tests/Feature/Campo/IdempotenciaTest.php`, `AvanceSinSenalTest.php` (Entrega 1), `ProgramacionSinSenalTest.php` (2), `MiFincaSinSenalTest.php` (3), `FotosDeAvanceTest.php` (4).

**Backend (modificados)**
- `backend/app/Http/Controllers/Api/TaskScheduleController.php` — `storeLog`, `finalize` (E1); `store` (E2).
- `backend/app/Models/TaskSchedule.php`, `TaskDailyLog.php` — fillable/casts nuevos.
- `backend/routes/api.php` — middleware `idempotente` en las escrituras del módulo.
- `backend/bootstrap/app.php` — alias del middleware.

**Frontend (nuevos)**
- `frontend/src/offline/tipos.ts` — contrato (Tarea 1.0).
- `frontend/src/offline/db.ts` — base Dexie (`operaciones`, `meta`).
- `frontend/src/offline/cola.ts` — encolar, enviar, reintentar, dependencias, con problema.
- `frontend/src/offline/conexion.ts` — estado en línea / sin señal (onlineManager de TanStack + ping).
- `frontend/src/offline/persistencia.ts` — persistencia del cache de TanStack Query en IndexedDB.
- `frontend/src/offline/hooks.ts` — `useEstadoCampo()`, `useOperaciones()`, `usePendientesDe(entidadId)`.
- `frontend/src/offline/calculos.ts` — panel del día y disponibilidad de trabajadores calculados localmente.
- `frontend/src/components/offline/FranjaDeEstado.tsx`, `InstalarAppAviso.tsx`.
- `frontend/src/pages/performance/PendientesDeEnvio.tsx`.
- `frontend/src/offline/__tests__/*.test.ts` (Vitest + fake-indexeddb).
- `e2e/campo/*.py` — recorridos Playwright con y sin señal (versionados, no en `.dumps/`).

**Frontend (modificados)**
- `frontend/vite.config.ts` — `VitePWA`.
- `frontend/src/App.tsx` — `PersistQueryClientProvider`, ruta `/performance/pendientes`.
- `frontend/src/services/api.ts` — distinguir "sin red" de 401; `patch` ya existe; cabeceras de campo.
- `frontend/src/hooks/usePermissions.ts`, `frontend/src/components/ProtectedRoute.tsx` — no mandar a ingreso sin señal si hay sesión guardada.
- `frontend/src/components/layout/MainLayout.tsx` — franja, menú "Pendientes de envío", cerrar sesión bloqueado con pendientes.
- `frontend/src/pages/performance/DailyPanel.tsx` (E1), `ScheduleList.tsx` y `PerformanceReports.tsx` (E2).

---

## Entrega 0 — Ambiente de pruebas (Claude) · ~3–4 h

Ambiente aislado en el mismo servidor: **frontend** `https://staging.agriflor.pages.dev`, **API** `https://agriflor-api.duckdns.org/staging/api`, base `agriflor_staging` (copia de producción) en el mismo MySQL, contenedor `agriflor-staging-backend`. CORS ya acepta `*.agriflor.pages.dev`.

### Tarea 0.1: Base y backend de pruebas

**Archivos (servidor):** `/opt/agriflor-staging/docker-compose.yml`, `/opt/agriflor-staging/.env`, `/opt/agriflor-staging/repo` (clon del repositorio, rama `staging`).

- [ ] **Paso 1:** Respaldar `nginx.conf` y `docker-compose.yml` de producción (`cp … …bak-staging-$(date +%Y%m%d%H%M%S)`).
- [ ] **Paso 2:** Crear base y usuario:
```sql
CREATE DATABASE agriflor_staging CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'agriflor_staging'@'%' IDENTIFIED BY '<clave generada con openssl rand -hex 24>';
GRANT ALL PRIVILEGES ON agriflor_staging.* TO 'agriflor_staging'@'%';
```
- [ ] **Paso 3:** Cargar la copia: `mysqldump --single-transaction agriflor | mysql agriflor_staging` (dentro de `agriflor-mysql`).
- [ ] **Paso 4:** Crear rama `staging` desde `main`, clonar en `/opt/agriflor-staging/repo` y escribir el compose con un único servicio:
```yaml
services:
  backend-staging:
    build: { context: ./repo/backend, dockerfile: Dockerfile }
    image: agriflor-backend:staging
    container_name: agriflor-staging-backend
    env_file: .env          # APP_ENV=staging, DB_HOST=mysql, DB_DATABASE=agriflor_staging,
                            # DB_USERNAME=agriflor_staging, JWT_SECRET propio, APP_KEY propio
    volumes: [ "staging-storage:/var/www/storage" ]
    networks: [ agriflor_internal, agriflor_web ]
    restart: unless-stopped
volumes: { staging-storage: {} }
networks:
  agriflor_internal: { external: true }
  agriflor_web: { external: true }
```
(Confirmar los nombres reales de las redes con `docker network ls` antes de escribirlo.)
- [ ] **Paso 5:** `docker compose up -d --build` y `docker exec agriflor-staging-backend php artisan migrate --force`.
- [ ] **Paso 6 (verificación):** `docker exec agriflor-staging-backend php artisan tinker --execute 'echo DB::connection()->getDatabaseName();'` → `agriflor_staging`.

### Tarea 0.2: Ruta `/staging/` en el nginx compartido

- [ ] **Paso 1:** En el bloque `server` HTTPS de `agriflor-api.duckdns.org`, **antes** de `location /`:
```nginx
    location ^~ /staging/ {
        resolver 127.0.0.11 valid=10s;
        set $agriflor_staging_upstream agriflor-staging-backend;
        rewrite ^/staging/(.*)$ /$1 break;
        proxy_pass http://$agriflor_staging_upstream:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        client_max_body_size 20m;
    }
```
- [ ] **Paso 2:** `docker exec agriflor-nginx nginx -t` → `syntax is ok`; solo entonces `nginx -s reload`. Si falla, restaurar el respaldo.
- [ ] **Paso 3 (verificación):** `curl -s -o /dev/null -w "%{http_code}" -H "Accept: application/json" https://agriflor-api.duckdns.org/staging/api/auth/me` → `401`; y producción sigue: mismo comando sin `/staging` → `401`; `https://aycgroup.duckdns.org` y `https://avoterraexports.co` responden como antes.

### Tarea 0.3: Despliegue automático de la rama `staging`

**Archivo:** `.github/workflows/deploy-staging.yml` (nuevo; no toca `deploy.yml`).

- [ ] **Paso 1:** Workflow en `push` a `staging`: (a) frontend: `npx vite build` con `VITE_API_URL=https://agriflor-api.duckdns.org/staging/api` y `wrangler pages deploy frontend/dist --project-name agriflor --branch staging`; (b) backend: SSH → `cd /opt/agriflor-staging/repo && git fetch origin staging && git reset --hard origin/staging && cd .. && docker compose build backend-staging && docker compose up -d backend-staging && docker exec agriflor-staging-backend php artisan migrate --force && … config:cache && … route:cache`.
- [ ] **Paso 2 (verificación):** push vacío a `staging` → el workflow termina en verde; `https://staging.agriflor.pages.dev` abre el login y entra con `admin@agriflor.com`.

### Tarea 0.4: Motor de Safari para pruebas locales

- [ ] **Paso 1:** `python3 -m playwright install webkit`. Si pide dependencias del sistema, pedir a Julián **J3**.
- [ ] **Paso 2 (verificación):** `python3 -c "from playwright.sync_api import sync_playwright as s; p=s().start(); b=p.webkit.launch(); print(b.version); b.close(); p.stop()"` imprime una versión.

---

## Entrega 1 — Base sin conexión + Registro Diario · ~8–14 h de IA

### Tarea 1.0: Contratos (Claude, antes de lanzar las pistas)

**Archivos:** Crear `frontend/src/offline/tipos.ts`; documentar el contrato HTTP al inicio de `backend/app/Support/Campo/CapturaDeCampo.php`.

**Produce (todas las pistas lo usan tal cual):**

```ts
// frontend/src/offline/tipos.ts
export type TipoOperacion =
  | 'registrar_avance'      // E1
  | 'finalizar_tarea'       // E1
  | 'crear_programacion'    // E2
  | 'crear_no_programada'   // E2
  | 'editar_trabajadores'   // E3
  | 'crear_lote'            // E3
  | 'editar_lote'           // E3
  | 'subir_foto';           // E4

export type EstadoOperacion = 'pendiente' | 'enviando' | 'enviada' | 'con_problema';

export interface OperacionDeCampo {
  id: string;                 // UUID de la operación = cabecera Idempotency-Key
  tipo: TipoOperacion;
  usuarioId: string;          // dueño; otro usuario en el teléfono no la ve ni la envía
  fincaId: string;            // las de una finca se envían una tras otra
  entidadId: string;          // UUID del registro que crea o toca (avance, programación…)
  dependeDe?: string;         // id de otra operación que debe quedar 'enviada' antes
  metodo: 'POST' | 'PUT' | 'PATCH';
  ruta: string;               // p. ej. `/performance/schedules/${programacionId}/logs`
  cuerpo: Record<string, unknown>;
  capturadoEn: string;        // ISO 8601 con zona, reloj del teléfono
  estado: EstadoOperacion;
  intentos: number;
  motivo?: string;            // mensaje del servidor cuando queda 'con_problema'
  respuesta?: unknown;        // cuerpo de la respuesta cuando queda 'enviada'
}

export interface EstadoCampo {
  enLinea: boolean;
  datosAl: string | null;     // ISO de la última descarga completa
  pendientes: number;         // estado 'pendiente' | 'enviando'
  conProblema: number;
  masViejaSinEnviar: string | null; // capturadoEn de la pendiente más antigua
}
```

**Contrato HTTP (backend):** toda escritura del módulo de tareas acepta, además de su cuerpo de hoy:
- Cabecera `Idempotency-Key: <uuid>` → si ya se procesó para ese usuario, responde **el mismo código y cuerpo** sin volver a ejecutar.
- Cabeceras `X-Captured-At: <ISO 8601>`, `X-Captured-Offline: 1`, `X-Device-Id: <uuid estable por instalación>`, `X-Data-Since: <ISO 8601 de la última descarga de datos del teléfono>`.
- En creación: campo `id` (UUID generado en el teléfono) para el registro nuevo.
- Sin esas cabeceras, el comportamiento es **exactamente el de hoy**.

- [ ] **Paso 1:** Escribir `tipos.ts` y el bloque de contrato; commit `docs(campo): contrato entre el telefono y el servidor`.

### Tarea 1.1: Backend — idempotencia y datos de captura (Pista A)

**Archivos:**
- Crear: `backend/database/migrations/2026_10_14_100000_create_field_operations_table.php`, `…100100_add_field_capture_columns.php`, `backend/app/Models/FieldOperation.php`, `backend/app/Http/Middleware/IdempotentFieldOperation.php`, `backend/app/Support/Campo/CapturaDeCampo.php`, `backend/tests/Feature/Campo/IdempotenciaTest.php`
- Modificar: `backend/bootstrap/app.php` (alias `idempotente`), `backend/routes/api.php` (agregar `idempotente` a `POST performance/schedules/{id}/logs` y `POST performance/schedules/{id}/finalize`), `backend/app/Models/TaskDailyLog.php`, `TaskSchedule.php`.

**Interfaces:**
- Produce: middleware alias `idempotente`; `CapturaDeCampo::desde(Request): CapturaDeCampo` con `->capturadoEn(): ?Carbon`, `->sinSenal(): bool`, `->dispositivo(): ?string`; columnas `captured_at`, `captured_offline`, `device_id`.

- [ ] **Paso 1: Pruebas que fallan**
```php
// backend/tests/Feature/Campo/IdempotenciaTest.php
public function test_la_misma_operacion_dos_veces_no_duplica(): void
{
    $clave = (string) Str::uuid();
    $cuerpo = ['log_date' => now()->toDateString(), 'persons_today' => 2, 'advance_pct_today' => 10];
    $cabeceras = ['Idempotency-Key' => $clave, 'X-Captured-At' => now()->subHours(5)->toIso8601String(), 'X-Captured-Offline' => '1'];

    $primera = $this->como($this->supervisor)->withHeaders($cabeceras)
        ->postJson("/api/performance/schedules/{$this->programacion->id}/logs", $cuerpo)->assertSuccessful();
    $segunda = $this->como($this->supervisor)->withHeaders($cabeceras)
        ->postJson("/api/performance/schedules/{$this->programacion->id}/logs", $cuerpo);

    $segunda->assertStatus($primera->status());
    $this->assertSame($primera->json(), $segunda->json());
    $this->assertSame(1, TaskDailyLog::where('task_schedule_id', $this->programacion->id)->count());
    $this->assertEquals(10, $this->programacion->fresh()->accumulated_pct);
}

public function test_la_misma_clave_de_otro_usuario_no_reutiliza_la_respuesta(): void { /* otro usuario con la misma clave → se procesa aparte (403 si la finca no es suya) */ }

public function test_sin_cabeceras_se_comporta_como_hoy(): void
{
    $this->como($this->supervisor)->postJson("/api/performance/schedules/{$this->programacion->id}/logs",
        ['log_date' => now()->toDateString(), 'persons_today' => 2, 'advance_pct_today' => 10])->assertSuccessful();
    $log = TaskDailyLog::latest('created_at')->first();
    $this->assertNull($log->captured_at);
    $this->assertFalse((bool) $log->captured_offline);
}

public function test_guarda_fecha_de_captura_y_dispositivo(): void { /* X-Captured-At y X-Device-Id quedan en el avance */ }
```
(`setUp`: finca con responsable = supervisor, programación `en_progreso` de esa finca, como en `TaskBackdatingTest`.)
- [ ] **Paso 2:** `docker exec -e DB_DATABASE=agriflor_test_a agriflor-app php artisan test tests/Feature/Campo/IdempotenciaTest.php` → FALLA.
- [ ] **Paso 3: Implementación**
```php
// field_operations: id (uuid = Idempotency-Key), user_id, method, path, status_code, response_body (json), created_at
// PK compuesta lógica: (id, user_id) única.

final class IdempotentFieldOperation
{
    public function handle(Request $request, Closure $next): Response
    {
        $clave = $request->header('Idempotency-Key');
        if (!$clave || !Str::isUuid($clave) || !$request->user()) {
            return $next($request);
        }
        $previa = FieldOperation::where('id', $clave)->where('user_id', $request->user()->id)->first();
        if ($previa) {
            return response()->json($previa->response_body, $previa->status_code);
        }
        $respuesta = $next($request);
        // Solo se guardan respuestas definitivas: 2xx y 4xx de negocio (422/409/403/404).
        // 5xx y 401 no se guardan: el teléfono reintenta.
        if ($respuesta->getStatusCode() < 500 && $respuesta->getStatusCode() !== 401) {
            FieldOperation::create([
                'id' => $clave, 'user_id' => $request->user()->id,
                'method' => $request->method(), 'path' => $request->path(),
                'status_code' => $respuesta->getStatusCode(),
                'response_body' => json_decode($respuesta->getContent(), true),
            ]);
        }
        return $respuesta;
    }
}
```
Además: en `storeLog`, guardar `captured_at`, `captured_offline`, `device_id` desde `CapturaDeCampo` y aceptar `id` (UUID) si viene. Dos peticiones simultáneas con la misma clave: envolver ejecución + registro en `DB::transaction` con `lockForUpdate` sobre una fila de `field_operations` insertada como "en curso" (`status_code = 0`) — si ya existe "en curso", responder `409` y el teléfono reintenta.
- [ ] **Paso 4:** misma orden → PASA. Declarar en la foto de rutas que las 2 rutas siguen con el mismo acceso (no cambia: el middleware no es de acceso). Correr `PermissionParityTest`.
- [ ] **Paso 5:** commit `feat(campo): operaciones del telefono idempotentes y con fecha de captura`.

### Tarea 1.2: Backend — reglas del avance y finalizar sin señal (Pista A)

**Archivos:** Crear `backend/tests/Feature/Campo/AvanceSinSenalTest.php`; Modificar `TaskScheduleController::storeLog`, `::finalize`.

**Interfaces:** Consume `CapturaDeCampo` (1.1). Produce mensajes de error estables (el teléfono los muestra tal cual).

- [ ] **Paso 1: Pruebas que fallan**
```php
public function test_avances_sin_senal_que_pasan_del_100_quedan_sospechosos_con_tope(): void
{   // acumulado 90 %; llega un avance sin señal de +30 %
    $this->avanceSinSenal(['advance_pct_today' => 30]);
    $p = $this->programacion->fresh();
    $this->assertEquals(100, $p->accumulated_pct);
    $this->assertTrue((bool) TaskDailyLog::latest('created_at')->first()->suspicious);
}
public function test_con_senal_el_tope_sigue_como_hoy(): void { /* sin X-Captured-Offline: acumulado 100, suspicious = false */ }
public function test_el_mes_se_cuenta_desde_la_captura(): void
{   // capturado hace 29 días con log_date de hace 30 días, sincroniza hoy → se acepta
}
public function test_captura_con_fecha_futura_vuelve_con_problema(): void
{   $this->avanceSinSenal([], capturadoEn: now()->addHours(2))
        ->assertStatus(422)->assertJsonFragment(['message' => 'Revise la fecha del teléfono: el registro dice capturado en el futuro.']);
}
public function test_avance_sobre_tarea_cancelada_vuelve_con_problema(): void { /* 422 con 'La tarea fue cancelada…' */ }
public function test_finalizar_dos_veces_con_la_misma_clave_responde_igual(): void { }
public function test_finalizar_una_tarea_ya_finalizada_por_otro_vuelve_con_problema(): void { /* clave distinta → 422 informativo */ }
public function test_supervisor_no_registra_en_finca_ajena(): void { /* 403 como hoy */ }
```
- [ ] **Paso 2:** correr → FALLA.
- [ ] **Paso 3:** implementar en `storeLog`: si `sinSenal()`: validar `capturadoEn` (no futura: `> now()->addMinutes(5)` → 422 "Revise la fecha del teléfono: el registro dice capturado en el futuro."; no anterior a `X-Data-Since` menos 1 día → 422 "Revise la fecha del teléfono: el registro es anterior a los datos que tenía el teléfono."); calcular la fecha mínima de un mes desde `capturadoEn`; si `accumulated + advance > 100` → guardar con `suspicious = true` y acumulado `min(100, …)`. En `finalize`: si ya está `completada`/`cancelada` → 422 con mensaje informativo. Agregar a la prueba: `test_captura_anterior_a_los_datos_del_telefono_vuelve_con_problema` y `test_quien_pasa_mas_de_dos_dias_sin_senal_no_pierde_lo_capturado` (captura de hace 4 días con `X-Data-Since` de hace 5 → se acepta).
- [ ] **Paso 3b (auditoría):** en `Vocabulario`/`AuditController`, las columnas `captured_at`, `captured_offline`, `device_id` se muestran como una sola línea **"Capturado sin conexión el 10/10/2026 6:15 a. m. · sincronizado el 10/10/2026 6:40 p. m."** (la sincronización = `created_at` del registro). Prueba: `test_la_auditoria_dice_capturado_sin_conexion`.
- [ ] **Paso 4:** correr → PASA; suite completa (`agriflor_test_a`) sin fallas nuevas.
- [ ] **Paso 5:** commit `feat(campo): reglas del avance y finalizar capturados sin senal`.

### Tarea 1.3: Frontend — sesión y arranque sin señal (Pista B)

**Archivos:** Modificar `frontend/src/services/api.ts`, `frontend/src/hooks/usePermissions.ts`, `frontend/src/components/ProtectedRoute.tsx`, `frontend/src/components/layout/MainLayout.tsx`; Crear `frontend/src/offline/conexion.ts`, `frontend/src/offline/__tests__/conexion.test.ts`.

**Interfaces:** Produce `export class SinConexionError extends Error {}` (lanzado por `api.request` cuando `fetch` falla por red, nunca por 401); `export function useEnLinea(): boolean`.

- [ ] **Paso 1:** Agregar `vitest`, `@vitest/coverage-v8`, `fake-indexeddb`, `jsdom` como devDependencies; script `"test:unit": "vitest run"`.
- [ ] **Paso 2: Pruebas que fallan** (`conexion.test.ts`): un `fetch` que rechaza con `TypeError('Failed to fetch')` produce `SinConexionError` y **no** borra el token ni redirige; un 401 con refresh fallido **sí** cierra sesión (como hoy).
- [ ] **Paso 3:** Implementar: en `request`, `catch (e) { if (e instanceof TypeError) throw new SinConexionError(); … }`; `ProtectedRoute` y `usePermissions`: si hay token y `['auth-me']` persistido, usar ese usuario aunque la consulta falle por `SinConexionError` (no navegar a `/login`); al volver la red (`onlineManager`), `refreshAuthToken()` y reintentar `auth-me`. `MainLayout`: "Cerrar sesión" deshabilitado con tooltip si `pendientes > 0`.
- [ ] **Paso 4:** `npm run test:unit` → PASA; `vite build` OK; `tsc -b` sin errores nuevos frente a `HEAD`.
- [ ] **Paso 5:** commit `feat(campo): la sesion no se cierra por falta de senal`.

### Tarea 1.4: Frontend — app instalable (Pista B)

**Archivos:** Modificar `frontend/vite.config.ts`, `frontend/index.html`; Crear `frontend/public/icons/*` (192, 512, maskable, apple-touch 180), `frontend/src/components/offline/InstalarAppAviso.tsx`.

- [ ] **Paso 1:** `VitePWA({ registerType: 'autoUpdate', workbox: { navigateFallback: '/index.html', globPatterns: ['**/*.{js,css,html,svg,png,woff2}'] }, manifest: { name: 'AgriFlor', short_name: 'AgriFlor', start_url: '/performance/daily', display: 'standalone', theme_color: '#2E7D32', background_color: '#ffffff', icons: [...] } })`. **No** cachear respuestas del API con Workbox (los datos los maneja la persistencia de TanStack, Tarea 1.5).
- [ ] **Paso 2:** `InstalarAppAviso`: en iPhone sin `navigator.standalone`, muestra cómo "Agregar a inicio" y que el trabajo sin señal solo se habilita instalada; en Android usa `beforeinstallprompt`. Pedir `navigator.storage.persist()` al instalar.
- [ ] **Paso 3 (verificación):** `vite build` genera `sw.js` y `manifest.webmanifest`; Lighthouse PWA en Chromium: instalable.
- [ ] **Paso 4:** commit `feat(campo): AgriFlor se puede instalar en el telefono`.

### Tarea 1.5: Frontend — copia local y cola de operaciones (Pista B)

**Archivos:** Crear `frontend/src/offline/db.ts`, `persistencia.ts`, `cola.ts`, `hooks.ts`, `__tests__/cola.test.ts`; Modificar `frontend/src/App.tsx` (`PersistQueryClientProvider` con `maxAge: 3 días`, `buster` = versión de la app; las consultas del módulo con `networkMode: 'offlineFirst'`, `gcTime: 3 días`).

**Interfaces (Produce):**
```ts
export function encolar(op: Omit<OperacionDeCampo, 'estado' | 'intentos'>): Promise<void>; // con señal envía de inmediato
export function sincronizar(): Promise<{ enviadas: number; conProblema: number }>;      // orden por finca + dependencias
export function reintentar(id: string, cuerpoCorregido?: Record<string, unknown>): Promise<void>;
export function descartar(id: string): Promise<void>;
export function useEstadoCampo(): EstadoCampo;
export function useOperaciones(filtro?: Partial<Pick<OperacionDeCampo, 'estado' | 'fincaId'>>): OperacionDeCampo[];
export function usePendientesDe(entidadId: string): OperacionDeCampo[];
export function prepararDatosDeCampo(usuarioId: string): Promise<void>; // descarga y persiste lo de sus fincas; actualiza datosAl
export function idDispositivo(): string; // UUID estable por instalación (localStorage 'agriflor_device_id'), va en X-Device-Id
```
- [ ] **Paso 1: Pruebas que fallan** (`cola.test.ts`, con `fake-indexeddb` y `fetch` simulado):
  - `envia_en_orden_por_finca`: tres operaciones de la misma finca salen una tras otra.
  - `respeta_dependencias`: un avance con `dependeDe` de una programación `con_problema` queda `pendiente` (no se envía).
  - `422_queda_con_problema_con_motivo`; `5xx_o_sin_red_se_reintenta`; `409_en_curso_se_reintenta`.
  - `misma_clave_en_reintentos`: el `Idempotency-Key` es siempre el `id` de la operación.
  - `otro_usuario_no_ve_ni_envia`: operaciones de `usuarioId` distinto no aparecen ni salen.
  - `cola_sobrevive_al_reinicio`: cerrar y reabrir la base conserva el estado.
- [ ] **Paso 2:** correr → FALLA. **Paso 3:** implementar. **Paso 4:** PASA.
- [ ] **Paso 5:** `prepararDatosDeCampo`: con señal, al iniciar sesión y cada 15 min con la app abierta: catálogo, fincas a cargo, lotes, programaciones activas y de los últimos 30 días de esas fincas, sus avances, `auth-me`; guarda `datosAl`.
- [ ] **Paso 6:** commit `feat(campo): copia local de datos y cola de envio en el telefono`.

### Tarea 1.6: Frontend — franja de estado y Pendientes de envío (Pista B)

**Archivos:** Crear `frontend/src/components/offline/FranjaDeEstado.tsx`, `frontend/src/pages/performance/PendientesDeEnvio.tsx`; Modificar `MainLayout.tsx` (franja en las pantallas de Rendimiento; menú Rendimiento → "Pendientes de envío" con contador), `App.tsx` (ruta `/performance/pendientes`, `module` igual que las demás de Rendimiento).

- [ ] **Paso 1:** Franja: "Sin conexión · datos al 13/10 6:00 a. m. · 5 pendientes de envío" / "En línea · 2 enviando" / en rojo si `masViejaSinEnviar` > 24 h: "Abra AgriFlor con señal para enviar N registros". `data-testid="franja-campo"`.
- [ ] **Paso 2:** Pendientes de envío: tres grupos (pendientes, con problema, enviados hoy); en "con problema", el motivo y los botones **Corregir y reenviar** (abre el formulario original con los datos) y **Descartar** (con confirmación). `data-testid`: `pendientes-lista`, `operacion-<id>`, `reenviar-<id>`, `descartar-<id>`.
- [ ] **Paso 3:** Fuera de Rendimiento, sin señal: tarjeta "Esta sección necesita conexión" en lugar del contenido y botones de guardar deshabilitados (envolver en `ProtectedRoute` para módulos ≠ `performance`).
- [ ] **Paso 4:** commit `feat(campo): estado de conexion y pantalla de pendientes de envio`.

### Tarea 1.7: Frontend — Registro Diario con y sin señal (Pista C)

**Archivos:** Modificar `frontend/src/pages/performance/DailyPanel.tsx`; Crear `frontend/src/offline/calculos.ts`, `frontend/src/offline/__tests__/calculos.test.ts`.

**Interfaces:** Consume `encolar`, `usePendientesDe`, `useEstadoCampo` (1.5), `SinConexionError` (1.3). Produce `calcularPanelDelDia(fecha, programaciones, avances, fincaId?)` con la misma forma que `GET /performance/panel` (`summary.totalTasks|totalPersons|programmedCount|adHocCount`, `activeSchedules[]`).

- [ ] **Paso 1: Pruebas que fallan** (`calculos.test.ts`): con datos fijos, `calcularPanelDelDia` devuelve exactamente lo mismo que la respuesta real del servidor guardada como fixture (`__tests__/fixtures/panel-2026-10-08.json`, sacada del ambiente de pruebas).
- [ ] **Paso 2:** implementar; el panel usa la respuesta del servidor con señal y `calcularPanelDelDia` sin señal.
- [ ] **Paso 3:** "Registrar avance": genera `id` (UUID) del avance y `id` de operación; `encolar({ tipo: 'registrar_avance', ruta: …/logs, cuerpo: {...form, id}, … })`; actualiza el acumulado en la copia local al instante; la fila muestra "pendiente de envío" (`data-testid="pendiente-<entidadId>"`). Con señal, el error 422 se muestra en el formulario como hoy.
- [ ] **Paso 4:** "Finalizar": igual, `tipo: 'finalizar_tarea'`.
- [ ] **Paso 5:** commit `feat(campo): registro diario funciona con y sin senal`.

### Tarea 1.8: Frontend — Registro Diario en celular (Pista C)

**Archivos:** Modificar `DailyPanel.tsx` (y extraer `frontend/src/pages/performance/components/TarjetaDeTarea.tsx` si el archivo supera ~800 líneas).

- [ ] **Paso 1:** con `Grid.useBreakpoint()`, por debajo de `md` las tablas se muestran como tarjetas (tarea, finca/lote, acumulado, personas de hoy, botones Registrar avance / Finalizar); botones de al menos 44 px de alto; formularios en `Drawer` a pantalla completa.
- [ ] **Paso 2 (verificación):** captura en 390×844 (iPhone 14) y 412×915 (Android) sin desbordes horizontales; escritorio idéntico a hoy (comparar captura de 1600×1000 antes/después).
- [ ] **Paso 3:** commit `feat(campo): registro diario adaptado al celular`.

### Tarea 1.9: Recorridos con y sin señal (Pista D)

**Archivos:** Crear `e2e/campo/comun.py`, `e2e/campo/registro_diario_sin_senal.py`, `e2e/campo/oficina_con_senal.py`, `e2e/campo/README.md`.

Cada recorrido corre en **Chromium** (contexto Android 412×915) y **WebKit** (contexto iPhone 390×844), contra `localhost` y contra el ambiente de pruebas (`BASE`/`API` por variable de entorno). Usa `context.set_offline(True/False)`.

- [ ] **Recorrido `registro_diario_sin_senal`:** entra como supervisor con señal → espera `datosAl` → corta la red → recarga la página (sigue dentro, no va a `/login`) → registra 2 avances y finaliza 1 tarea → cierra el contexto y abre otro con el mismo almacenamiento (`storage_state` + IndexedDB persistida) → sigue viendo 3 pendientes → vuelve la red → en ≤ 60 s quedan 0 pendientes → verifica por API que hay exactamente 2 avances nuevos y la tarea finalizada (sin duplicados) y que la auditoría muestra "capturado sin conexión".
- [ ] **Recorrido `abre_sin_senal_con_sesion_vencida`:** fuerza `exp` del token al pasado en `localStorage`, abre sin red → sigue dentro; vuelve la red → renueva sesión sola y envía.
- [ ] **Recorrido `reintento_tras_corte`:** intercepta la respuesta del primer envío y la descarta (el servidor guardó, el teléfono no se enteró) → al reintentar no hay duplicado.
- [ ] **Recorrido `con_problema`:** cancela la tarea por API (como la oficina) mientras el teléfono está sin señal con un avance pendiente → al reconectar el avance queda "con problema" con el motivo; "Descartar" lo quita.
- [ ] **Recorrido `oficina_con_senal_igual_que_hoy`:** admin y supervisor con señal en escritorio: programar, avanzar, finalizar, cancelar — mismos mensajes y resultados que hoy (comparar contra `main` antes del cambio).
- [ ] **Recorrido `otro_usuario_en_el_mismo_telefono`:** usuario A deja 1 pendiente sin señal, cierra sesión → bloqueado; (forzado) entra B → no ve ni envía lo de A.
- [ ] Todos limpian lo que crean (por API, como admin) y terminan con `N de N pasos OK`.

### Tarea 1.10: Revisión, ambiente de pruebas y producción (Claude + Julián)

- [ ] **Paso 1 (Claude):** integrar las pistas; suite completa; `npm run test:unit`; `vite build`; `tsc -b` sin errores nuevos; los 6 recorridos en Chromium y WebKit en local.
- [ ] **Paso 2 (Claude):** agente revisor independiente sobre el diff de la entrega (foco: duplicados, pérdida de datos, sesión, regresiones de oficina); aplicar hallazgos con pruebas.
- [ ] **Paso 3 (Claude):** commits por pista; push a `staging`; recorridos contra el ambiente de pruebas.
- [ ] **Paso 4 (Claude → Julián):** entregar el **guion de prueba en teléfono** (instalar desde `https://staging.agriflor.pages.dev`, entrar, modo avión, registrar 2 avances y finalizar 1, cerrar la app, reabrir en modo avión, quitar modo avión, ver 0 pendientes, revisar en el computador).
- [ ] **Paso 5 (Julián):** ejecutar el guion en iPhone y Android → "aprobado" o lo encontrado.
- [ ] **Paso 6 (Claude):** respaldo de producción, merge a `main`, despliegue, verificación de solo lectura (rutas, columnas nuevas, `manifest.webmanifest`, `sw.js`), informe.

**Criterio de aceptación de la Entrega 1:** con el teléfono en modo avión se registran avances y se finalizan tareas de la propia finca; al cerrar y reabrir la app sin señal todo sigue ahí; al volver la señal todo sube una sola vez; con señal, la oficina no nota ningún cambio.

---

## Entrega 2 — Programación, tareas no programadas e informes (opción A) · ~7–12 h de IA

Se detalla a nivel de pasos al iniciarla. Tareas, contratos y aceptación:

### Tarea 2.1: Backend — crear programación y no programada sin señal (Pista A)
- `store` acepta `id` (UUID del teléfono) + middleware `idempotente`.
- Código definitivo `PROG-XXXX` al guardar; el teléfono muestra "PROG-… provisional" hasta la respuesta.
- Sin señal y excede trabajadores propios → se guarda con `over_capacity = true` (columna de la Tarea 1.1) en lugar de 422; con señal, 422 como hoy.
- Duplicado (misma tarea, mismo lote, fechas solapadas, activa) → 422 con `data.existente_id` para "pasar los avances a la existente".
- Pruebas `ProgramacionSinSenalTest`: idempotencia, sobrecupo solo sin señal, duplicado con `existente_id`, código definitivo único bajo concurrencia (dos teléfonos de fincas distintas a la vez), tope de un mes desde la captura, finca ajena 403.
- Oficina: la lista de Programación muestra la etiqueta **"Sobrecupo"** y permite filtrarla.

### Tarea 2.2: Frontend — Programación y no programadas con y sin señal + celular (Pista B)
- `ScheduleList.tsx` y el botón "Tarea No Programada" de `DailyPanel.tsx` usan `encolar` (`crear_programacion`, `crear_no_programada`).
- Disponibilidad de trabajadores local: `calcularDisponibilidad(finca, desde, hasta, programaciones)` = `total_workers − Σ planned_persons` de activas solapadas; mismo resultado que `GET /performance/worker-availability` (prueba con fixture).
- Avances de una programación creada sin señal llevan `dependeDe` = id de la operación de creación.
- "Con problema: duplicado" ofrece **Pasar los avances a la existente** (reescribe `ruta` de los avances dependientes y los reenvía).
- Cancelar: deshabilitado sin señal con tooltip "Necesita conexión".
- Vista de tarjetas en celular, como en la Tarea 1.8.

### Tarea 2.3: Informes de rendimiento — opción A (Pista B)
- Con señal y la app abierta, `prepararDatosDeCampo` también descarga las 6 pestañas de `PerformanceReports.tsx` para el periodo actual y las fincas del usuario.
- Sin señal: muestra lo guardado con el aviso fijo **"Datos al 13/10 6:00 a. m. · no incluye lo pendiente de envío"**; si una pestaña nunca se descargó: "Disponible cuando haya conexión".

### Tarea 2.4: Recorridos (Pista C)
- `programar_sin_senal` (incluye avance sobre la programación recién creada sin señal, y su código definitivo al sincronizar), `sobrecupo`, `duplicado_y_pasar_avances`, `informes_sin_senal`, y repetir `oficina_con_senal_igual_que_hoy` con Programación.

### Tarea 2.5: Revisión, pruebas en teléfono y producción (igual que la Tarea 1.10) → **inicio del piloto (J7)**.

**Aceptación:** en modo avión se programa una tarea, se le registran avances y se finaliza; al volver la señal recibe su código definitivo y sus avances quedan unidos a ella; los informes muestran la última versión con el aviso.

---

## Entrega 3 — Mi finca (trabajadores y lotes) · ~3–6 h de IA

**Requiere J4** (Supervisor con *Lotes de finca → Crear / Editar*).

### Tarea 3.1: Backend (Pista A)
- `PUT locations/{id}` (solo `total_workers`) y `POST/PUT farm-lots` aceptan `idempotente` + cabeceras de captura; crear lote acepta `id`.
- Conflicto: el teléfono envía `X-Base-Updated-At` (cuándo descargó el registro). Si el registro cambió en el servidor después → 409 con ambos valores (`data.servidor`, `data.telefono`). Para `locations` (sin `updated_at`, ver memoria `audits-traza-forense`): comparar contra el último registro de `audits` de esa ubicación.
- Lote con nombre repetido en la finca → 422.
- Pruebas `MiFincaSinSenalTest`.

### Tarea 3.2: Frontend (Pista B)
- Recuadro **Mi finca** en Registro Diario (solo fincas a cargo): trabajadores (número) y lotes (crear/editar), con y sin señal (`editar_trabajadores`, `crear_lote`, `editar_lote`); "con problema: cambió en la oficina" muestra ambos valores y deja elegir.
- Recorrido `mi_finca_sin_senal`.

### Tarea 3.3: Revisión, pruebas en teléfono y producción (como 1.10).

**Aceptación:** sin señal se cambia el número de trabajadores y se crea un lote; al sincronizar quedan aplicados, o si la oficina los cambió entretanto, el teléfono muestra ambos valores.

---

## Entrega 4 — Fotos y ubicación · ~6–10 h de IA

### Tarea 4.1: Backend (Pista A)
- Tabla `task_log_photos` (id uuid, task_daily_log_id, path, size, width, height, latitude, longitude, accuracy_m, captured_at, created_by) + modelo `LineaDeDocumento` del avance (auditoría).
- `POST performance/logs/{id}/photos` (multipart, máx. 3 por avance, ≤ 1 MB cada una, JPEG/WebP) con `idempotente`; permiso `register_task_log`; declarar en la foto de rutas.
- `GET performance/photos/{id}` (stream con autorización: la oficina y el responsable de la finca).
- Columnas `latitude`, `longitude`, `accuracy_m` en `task_daily_logs` (GPS opcional del avance).
- Respaldo: `backup.sh` del servidor incluye `storage/app/campo/fotos` (respaldo incremental con `rsync` a `/opt/agriflor/backups/fotos`).
- Pruebas `FotosDeAvanceTest`: límite de 3, tamaño, tipo, idempotencia, finca ajena 403, auditoría.

### Tarea 4.2: Frontend (Pista B)
- Al registrar avance: hasta 3 fotos (`<input type="file" accept="image/*" capture="environment">` + galería); compresión en el teléfono a 1600 px / ~300 KB (canvas → JPEG 0.8); ubicación con `navigator.geolocation` (timeout 10 s; si falla, se guarda sin ella).
- Fotos guardadas como `Blob` en Dexie; operaciones `subir_foto` con `dependeDe` = operación del avance; se envían después del resto.
- Oficina: en el detalle de la programación, cada avance muestra miniaturas y "Ver en el mapa" (enlace a `https://www.google.com/maps?q=lat,lng`).
- Recorrido `fotos_sin_senal` (Chromium y WebKit) con imágenes de prueba de 4000×3000.

### Tarea 4.3: Revisión, pruebas en teléfono (fotos reales) y producción (como 1.10).

**Aceptación:** sin señal se registra un avance con 3 fotos y ubicación; al volver la señal suben después del avance, comprimidas; la oficina las ve en el avance y en el mapa; quedan en el respaldo.

---

## Estimación (realista, con agentes en paralelo)

| Entrega | IA (horas de agentes) | Calendario | Tiempo de Julián |
|---|---|---|---|
| 0. Ambiente de pruebas | 3–4 | ½ día | J3 si hace falta |
| 1. Base + Registro Diario | 8–14 | 2 días | 1–2 h (teléfonos) |
| 2. Programación + informes | 7–12 | 1½ días | 1–2 h + inicio piloto |
| 3. Mi finca | 3–6 | 1 día | J4 + 1 h |
| 4. Fotos y ubicación | 6–10 | 1½ días | 1–2 h |
| Integración y despliegues | incluidos | — | — |
| **Total** | **27–46 h** | **6–8 días hábiles + 1 semana de piloto** (peor caso 3 semanas) | **6–10 h** |

Incluye un 30–50 % de pausas por límites de uso con varios agentes en paralelo. Costo de IA equivalente en API (Claude Opus 5.5): **USD 300–600**, peor caso ~USD 800. Infraestructura adicional: **USD 0**.
