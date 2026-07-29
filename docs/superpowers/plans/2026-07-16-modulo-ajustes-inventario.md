# Módulo de Ajustes de Inventario — Plan de Implementación

> **Para ejecutores agénticos:** SUB-SKILL REQUERIDO: usar `superpowers:subagent-driven-development` (recomendado) o `superpowers:executing-plans` para ejecutar tarea por tarea. Los pasos usan checkbox (`- [ ]`). Ejecutar con **Opus 5** (o el modelo más capaz disponible: mejor análisis/codificación/criterio).

**Goal:** Un módulo de solicitudes de ajuste de stock (Entrada/Salida/Transferencia) con motivo, que cualquier rol crea en estado `pending` y que **solo se aplica al inventario cuando un admin la aprueba** (o la rechaza), dejando los informes correctos.

**Architecture:** Documento cabecera `Adjustment` (auditable) + catálogo `AdjustmentReason`. Crear = solo persiste `pending` (no toca stock). Aprobar (solo admin) = dentro de transacción con `lockForUpdate` aplica la lógica de stock por tipo reutilizando `InventoryService` (FIFO para reducir, nuevo `addStock` con promedio ponderado para aumentar), crea `InventoryMovement` compensatorios ligados (`related_document_type='App\Models\Adjustment'`) con palabras clave en observaciones para que los reportes clasifiquen bien. Frontend: página con formulario de solicitud + cola de aprobación para admin.

**Tech Stack:** Laravel 11 (PHP 8.2), MySQL, `owen-it/laravel-auditing`, JWT; React 19 + TS + Vite 7 + Ant Design 5 + React Query + Zustand. Entorno Docker (`backend/docker-compose.yml`, backend en `:8000`, front Vite en `:5173` con Node 22 vía nvm).

## Global Constraints (copiar verbatim a cada tarea)

- **NO usar `type='adjustment'`** en `inventory_movements` (enum BD = `entry|exit|transfer|application`). Ajustes se registran como `entry`/`exit`.
- Ajustes deben setear `related_document_type = 'App\Models\Adjustment'` (FQCN con backslashes) + `related_document_id`.
- Observaciones de ajuste: positivo contiene `"aumento"` o `"ajuste positivo"`; negativo contiene `"disminución"` o `"ajuste negativo"` (así caen en increases/decreases del `monthlyReport`).
- `movement_date` obligatorio en la solicitud; se usa en el movimiento (los informes filtran por `movement_date`).
- Toda mutación de inventario dentro de `DB::transaction` + `Inventory::lockForUpdate()`. Prevenir stock negativo.
- Conversión de unidades vía `InventoryService::toBaseUnit/fromBaseUnit` (soporta empaques).
- Backend: Resource dual naming (snake_case + flat `whenLoaded`), mensajes en español, UUIDs (`HasUuids`).
- Frontend: Ant Design 5 + Ant Form (NO Zod), React Query (invalidar `['inventory']`, `['adjustments']`, `['movements']`), `ResponsiveTable` (mobileColumns + desktopColumns), fetch vía `services/api.ts`.
- Aprobar/Rechazar = `role:admin` estricto. Crear = autenticado con acceso a inventario; aislamiento por ubicación (`User::canViewAllLocations()/managedLocationIds()`).
- Cumplir `dev-methodology:restricciones-calidad` (complejidad ciclomática, longitud de método, naming, sin duplicación) y `dev-methodology:restricciones-seguridad` (SQL injection, authz, input validation) en TODO código nuevo.

---

## Sección A — Skills, Agentes y Workflows (orquestación)

Usar los skills instalados en cada fase. Mapa de ejecución:

| Fase | Skill(s) a usar | Agentes / Workflow |
|---|---|---|
| **0. Montaje** | `superpowers:using-git-worktrees` (aislar en worktree), rama `feat/ajustes-inventario` | — |
| **1–3. Backend** | `superpowers:test-driven-development` (TDD por tarea), `dev-methodology:restricciones-calidad` + `restricciones-seguridad` (gates de código) | `superpowers:subagent-driven-development`: un subagente fresco por tarea, con revisión entre tareas |
| **4. Frontend** | `frontend-design:frontend-design` (estética/UX del módulo), TDD de tipos/lógica | Subagente por componente |
| **Paralelización** | `superpowers:dispatching-parallel-agents` | **Workflow**: backend (fase 1-3) y frontend (fase 4) tienen dependencia (contrato API), así que backend primero; DENTRO de backend, migraciones→modelos→servicio son secuenciales, pero los **tests** de cada tipo (entry/exit/transfer) se pueden escribir en paralelo |
| **5. Pruebas completas** | `/test-feature` (E2E + auto-fix del proyecto) **o** `/dev-agri` (Playwright en `:5173`), `dev-methodology:integration-test`, `verify`, `superpowers:verification-before-completion` | **Workflow de verificación** (ver Sección E): agentes en paralelo para reportes, aislamiento, aprobación/rechazo, y revisión adversarial |
| **Revisión** | `superpowers:requesting-code-review` + `/code-review` (o `dev-methodology:code-review-dispatch`) | Agente revisor adversarial del diff |
| **6. Deploy** | `/deploy` (Cloudflare Pages + VPS Hetzner; migración corre sola en el pipeline) | — |
| **Cierre** | `superpowers:finishing-a-development-branch`, `dev-methodology:close-5` | — |

**Regla:** al terminar cada tarea backend, correr sus tests (`php artisan test --filter=...` dentro del contenedor) antes de avanzar. Al terminar el módulo, correr el Workflow de verificación de la Sección E antes de pedir deploy.

---

## Sección B — Estructura de archivos

**Backend (crear):**
- `backend/database/migrations/2026_07_17_090000_create_adjustment_reasons_table.php`
- `backend/database/migrations/2026_07_17_090100_create_adjustments_table.php`
- `backend/database/seeders/AdjustmentReasonSeeder.php`
- `backend/app/Models/AdjustmentReason.php`
- `backend/app/Models/Adjustment.php`
- `backend/app/Http/Requests/StoreAdjustmentRequest.php`
- `backend/app/Http/Requests/RejectAdjustmentRequest.php`
- `backend/app/Http/Controllers/Api/AdjustmentController.php`
- `backend/app/Http/Resources/AdjustmentResource.php`
- `backend/tests/Feature/AdjustmentTest.php`
- `backend/tests/Feature/AdjustmentReportsConsistencyTest.php`

**Backend (modificar):**
- `backend/app/Services/InventoryService.php` → agregar `addStock(...)`.
- `backend/routes/api.php` → grupo de rutas de ajustes.
- `backend/database/seeders/DatabaseSeeder.php` → registrar `AdjustmentReasonSeeder`.
- `backend/app/Http/Controllers/Api/InventoryController.php:~1512-1531` → endurecer clasificación increases/decreases por `related_document_type`.

**Frontend (crear):**
- `frontend/src/pages/inventory/Adjustments.tsx`

**Frontend (modificar):**
- `frontend/src/services/api.ts` → `adjustmentsApi`, `adjustmentReasonsApi`.
- `frontend/src/components/layout/MainLayout.tsx` → entrada de menú bajo "Inventario" (`sub4`).
- `frontend/src/App.tsx` → ruta `/inventory/adjustments`.
- `frontend/src/types/index.ts` → tipos `Adjustment`, `AdjustmentReason`.

---

## Sección C — Fases y tareas (TDD, bite-sized)

Comandos base (contenedor): `BE=backend; DC="docker compose -f $BE/docker-compose.yml exec -T app"`. Tests: `$DC php artisan test --filter=<X>`. Lint: `php -l <archivo>`.

### Fase 0 — Montaje

- [ ] **Paso 1:** Invocar `superpowers:using-git-worktrees` y crear worktree/rama `feat/ajustes-inventario` desde `main` actualizado (`git fetch && git reset --hard origin/main`). Asegurar stack Docker arriba (`docker compose up -d mysql redis app nginx`) y `php artisan migrate --force`.

### Fase 1 — BD, catálogo y modelos

#### Task 1: Migración y modelo `AdjustmentReason`

**Files:** Create `..._create_adjustment_reasons_table.php`, `app/Models/AdjustmentReason.php`, `database/seeders/AdjustmentReasonSeeder.php`; Modify `DatabaseSeeder.php`; Test `tests/Feature/AdjustmentTest.php`.

**Interfaces — Produces:** `AdjustmentReason` con `code,name,direction,active`; seeder con 10 motivos.

- [ ] **Step 1: Migración**
```php
// ..._create_adjustment_reasons_table.php
Schema::create('adjustment_reasons', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->string('code')->unique();
    $t->string('name');
    $t->enum('direction', ['any','entry','exit','transfer'])->default('any');
    $t->boolean('active')->default(true);
    $t->timestamps();
});
```
- [ ] **Step 2: Modelo**
```php
// app/Models/AdjustmentReason.php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class AdjustmentReason extends Model {
    use HasUuids;
    protected $fillable = ['code','name','direction','active'];
    protected $casts = ['active' => 'boolean'];
    public function scopeActive($q){ return $q->where('active', true); }
}
```
- [ ] **Step 3: Seeder** (10 motivos de la spec; registrar en `DatabaseSeeder::run`)
```php
// database/seeders/AdjustmentReasonSeeder.php  (usar updateOrCreate por code)
$reasons = [
  ['code'=>'error_captura','name'=>'Error de captura','direction'=>'any'],
  ['code'=>'conteo_fisico','name'=>'Conteo físico','direction'=>'any'],
  ['code'=>'merma_dano','name'=>'Merma o daño','direction'=>'exit'],
  ['code'=>'vencimiento','name'=>'Producto vencido','direction'=>'exit'],
  ['code'=>'robo_perdida','name'=>'Robo o pérdida','direction'=>'exit'],
  ['code'=>'devolucion','name'=>'Devolución','direction'=>'any'],
  ['code'=>'compra_doble','name'=>'Compra/recepción doble','direction'=>'exit'],
  ['code'=>'salida_erronea','name'=>'Salida errónea (revertir)','direction'=>'entry'],
  ['code'=>'traslado_interno','name'=>'Traslado interno','direction'=>'transfer'],
  ['code'=>'ajuste_inicial','name'=>'Ajuste inicial','direction'=>'entry'],
];
foreach ($reasons as $r) { \App\Models\AdjustmentReason::updateOrCreate(['code'=>$r['code']], $r); }
```
- [ ] **Step 4: Test (falla primero)** — `AdjustmentTest::test_reasons_seeded`
```php
public function test_reasons_seeded(): void {
    $this->seed(\Database\Seeders\AdjustmentReasonSeeder::class);
    $this->assertDatabaseHas('adjustment_reasons', ['code'=>'compra_doble','direction'=>'exit']);
    $this->assertSame(10, \App\Models\AdjustmentReason::count());
}
```
- [ ] **Step 5:** `$DC php artisan migrate --force`; correr test → PASS. Commit `feat(ajustes): catálogo de motivos`.

#### Task 2: Migración y modelo `Adjustment`

**Files:** Create `..._create_adjustments_table.php`, `app/Models/Adjustment.php`; Test `AdjustmentTest.php`.

**Interfaces — Produces:** `Adjustment` (Auditable) con todos los campos de la spec; `Adjustment::generateAdjustmentNumber(): string`; relaciones `reason(), product(), brand(), originLocation(), destinationLocation(), requester(), approver()`.

- [ ] **Step 1: Migración** (todas las columnas de la tabla `adjustments` de la spec; FKs a products/brands/locations/users; `status` default `pending`; índices en `status`, `product_id`, `[related...]`). Enum `type` `['entry','exit','transfer']`, `quantity_mode` `['delta','absolute']`, `status` `['pending','approved','rejected','cancelled']`.
- [ ] **Step 2: Modelo** (`HasUuids`, implements `OwenIt\Auditing\Contracts\Auditable`, `use OwenIt\Auditing\Auditable;` — patrón `ProductOutput.php:7-12`). `fillable` con todos los campos; `casts`: `quantity/quantity_base/unit_price`=`decimal:2`, `movement_date`=`date`, `approved_at`=`datetime`. `generateAdjustmentNumber()` copia el patrón `ProductOutput::generateOutputNumber()` con prefijo `AJU-`. Relaciones belongsTo.
- [ ] **Step 3: Test (falla)** — `test_generate_number_format` (`/^AJU-\d{8}-\d{4}$/`) y `test_adjustment_is_auditable` (crear y assert `->audits()->count() >= 1`).
- [ ] **Step 4:** migrate + test PASS. Commit `feat(ajustes): documento Adjustment auditable`.

### Fase 2 — Servicio de stock, Request y Controller

#### Task 3: `InventoryService::addStock()` (aumento con promedio ponderado)

**Files:** Modify `app/Services/InventoryService.php`; Test `tests/Feature/InventoryServiceAddStockTest.php`.

**Interfaces — Produces:** `addStock(string $productId, string $brandId, string $locationId, float $quantityInBase, float $unitPrice, string $batchNumber, ?string $expirationDate = null): void` — crea el lote o suma con promedio ponderado. (Extraído del patrón `ReceptionController::updateInventoryStock` ~2024-2091.)

- [ ] **Step 1: Test (falla)** — dos casos:
```php
// lote nuevo
$svc->addStock($p, $b, $loc, 10, 5.0, 'AJU-x');
$this->assertDatabaseHas('inventory', ['batch_number'=>'AJU-x','quantity'=>10,'unit_price'=>5.0]);
// promedio ponderado: 10@5 + 10@7 => 20@6
$svc->addStock($p, $b, $loc, 10, 7.0, 'AJU-x');
$inv = Inventory::where('batch_number','AJU-x')->first();
$this->assertEqualsWithDelta(20, (float)$inv->quantity, 0.01);
$this->assertEqualsWithDelta(6.0, (float)$inv->unit_price, 0.01);
```
- [ ] **Step 2: Implementación** en `InventoryService` (usar `lockForUpdate`, `firstOrNew` por `[product,brand,location,batch_number]`; si existe: `newQty = qty + delta`, `newPrice = (qty*price + delta*unitPrice)/newQty`; si no: crear; recalcular `total_value`; `status='good'`). La cantidad llega ya en unidad base (el llamador convierte).
- [ ] **Step 3:** test PASS. Commit `feat(ajustes): InventoryService.addStock (promedio ponderado)`.
- [ ] **Step 4 (opcional, DRY):** refactor `ReceptionController::updateInventoryStock` para delegar en `addStock` (solo si no rompe tests de recepción; correr `--filter=Reception`). Si hay riesgo, DEJARLO y anotar deuda técnica.

#### Task 4: `StoreAdjustmentRequest`

**Files:** Create `app/Http/Requests/StoreAdjustmentRequest.php`; Test en `AdjustmentTest`.

**Interfaces — Produces:** validación de creación.

- [ ] **Step 1: Test (falla)** — `POST /api/adjustments` sin `type` → 422; con payload válido de Entrada → 201 status `pending`.
- [ ] **Step 2: Reglas** (`authorize(): true`; mensajes ES):
  - `type` in `entry,exit,transfer`; `reason_id` exists; `product_id`,`brand_id` exists; `unit` string; `quantity` numeric min 0.01; `quantity_mode` in `delta,absolute`; `movement_date` date required; `notes` nullable.
  - Condicionales (`withValidator`/`after`): `entry` requiere `destination_location_id` + `unit_price>=0`; `exit` requiere `origin_location_id`; `transfer` requiere ambos y `origin != destination`; `quantity_mode='absolute'` requiere `batch_number` y `type in [entry,exit]` (transfer no admite absoluto).
- [ ] **Step 3:** test PASS. Commit `feat(ajustes): StoreAdjustmentRequest`.

#### Task 5: `AdjustmentController::store` (crear solicitud pendiente, NO toca stock)

**Files:** Create `app/Http/Controllers/Api/AdjustmentController.php` (por ahora `store`, `index`, `show`); Test `AdjustmentTest`.

**Interfaces — Produces:** `store` crea `Adjustment` `pending` (responsible_user=auth). NO crea InventoryMovement ni toca inventory.

- [ ] **Step 1: Test (falla)** — al crear una Salida, el `inventory` y `inventory_movements` NO cambian; `adjustments` tiene 1 fila `pending` con `responsible_user = user->id`.
```php
$before = InventoryMovement::count();
$this->actingAs($bodega)->postJson('/api/adjustments', $payloadExitDelta)->assertStatus(201)
     ->assertJsonPath('data.status','pending');
$this->assertSame($before, InventoryMovement::count());
```
- [ ] **Step 2: Implementación** `store`: `DB::transaction`, `Adjustment::create([...$validated, 'adjustment_number'=>Adjustment::generateAdjustmentNumber(), 'responsible_user'=>auth()->id(), 'status'=>'pending'])`; devuelve `AdjustmentResource` 201. `index` con aislamiento por ubicación (`canViewAllLocations()` else `whereIn(origin/destination, managedLocationIds())`) y filtro `?status=`. `show`.
- [ ] **Step 3:** test PASS. Commit `feat(ajustes): crear solicitud pendiente`.

#### Task 6: `approve` (solo admin; aplica el stock)

**Files:** Modify `AdjustmentController` (+`approve`); Test `AdjustmentTest` (varios).

**Interfaces — Produces:** `approve(string $id)`: aplica lógica de stock por tipo/modo, crea movimiento(s) ligados, setea approved.

- [ ] **Step 1: Tests (fallan)** — 4 tests:
  1. `test_only_admin_can_approve`: bodega → 403; admin → 200.
  2. `test_approve_entry_delta_increases_stock`: Entrada delta 10 @ costo 5 → lote destino +10, `InventoryMovement type=entry`, `related_document_type='App\Models\Adjustment'`, observaciones contienen "aumento". `inventory` sube 10.
  3. `test_approve_exit_delta_reduces_stock_fifo`: con stock previo 10, Salida delta 4 → queda 6; movimiento `exit` con "disminución".
  4. `test_approve_exit_insufficient_stock_fails`: Salida > disponible → 422 con mensaje claro; stock intacto; adjustment sigue `pending`.
- [ ] **Step 2: Implementación** `approve` (solo tras middleware `role:admin`):
```php
public function approve(string $id): JsonResponse {
    $adj = Adjustment::with('reason')->findOrFail($id);
    if ($adj->status !== 'pending') return response()->json(['success'=>false,'message'=>'La solicitud ya fue procesada.'],422);
    try {
        DB::beginTransaction();
        $userId = auth()->id();
        $deltaBase = $this->resolveDeltaBase($adj); // maneja delta/absolute (+ lock del lote en absolute)
        $tag = fn(bool $pos) => ($pos ? '[AUMENTO / ajuste positivo] ' : '[DISMINUCIÓN / ajuste negativo] ')
                 . ($adj->reason->name ?? 'Ajuste') . ($adj->notes ? " - {$adj->notes}" : '');
        if ($adj->type === 'entry') {
            $this->applyEntry($adj, $deltaBase, $userId, $tag(true));
        } elseif ($adj->type === 'exit') {
            $this->applyExit($adj, $deltaBase, $userId, $tag(false));
        } else { // transfer
            $this->applyExit($adj, $deltaBase, $userId, '[Traslado por ajuste] salida ' . ($adj->reason->name ?? ''));
            $this->applyEntryForTransfer($adj, $deltaBase, $userId, '[Traslado por ajuste] entrada ' . ($adj->reason->name ?? ''));
        }
        $adj->update(['status'=>'approved','approved_by'=>$userId,'approved_at'=>now(),'quantity_base'=>$deltaBase]);
        DB::commit();
        return response()->json(['success'=>true,'message'=>'Ajuste aprobado y aplicado.','data'=>new AdjustmentResource($adj->fresh())]);
    } catch (\Throwable $e) {
        DB::rollBack();
        return response()->json(['success'=>false,'message'=>'No se pudo aplicar el ajuste: '.$e->getMessage()],422);
    }
}
```
  Helpers privados:
  - `resolveDeltaBase(Adjustment $adj): float` — `delta`: `toBaseUnit(quantity)`. `absolute`: `lockForUpdate` el lote `[product,brand,location,batch_number]`, `current=toBaseUnit(qty_lote)`, `target=toBaseUnit(quantity)`, `delta=target-current`; validar signo vs tipo (entry: delta>=0; exit: delta<=0 y devolver `abs`). Location = destination (entry) u origin (exit).
  - `applyEntry(adj, base, userId, obs)`: `InventoryService::addStock(product,brand,destination, base, unit_price, batch_number ?? 'AJU-'.substr(id,0,8), null)` + `InventoryMovement::create(type=entry, location=destination, quantity=fromBaseUnit(base,unit), unit, movement_date, unit_price, total_price, responsible_user=userId, related_document_type='App\\Models\\Adjustment', related_document_id=adj->id, observations=obs)`.
  - `applyExit(adj, base, userId, obs)`: `InventoryService::reduceInventoryFIFO(product,brand,origin, base, unit, batch_number)` + `InventoryMovement::create(type=exit, location=origin, ..., ligado, obs)`.
  - `applyEntryForTransfer(...)`: como applyEntry pero a destination con el costo del lote de origen (obtener costo del/los lote(s) consumidos; usar precio promedio del origen si simple).
- [ ] **Step 3:** tests PASS. Commit `feat(ajustes): aprobar aplica stock (solo admin)`.

#### Task 7: `reject` y `cancel`

**Files:** Modify `AdjustmentController` (+`reject`,`cancel`), Create `RejectAdjustmentRequest`; Test `AdjustmentTest`.

- [ ] **Step 1: Tests (fallan)** — `reject` solo admin, setea `rejected`+`rejection_reason`, no toca stock; `cancel` por el solicitante mientras `pending`.
- [ ] **Step 2: Implementación** `reject` (`role:admin`, requiere `rejection_reason`), `cancel` (solo `responsible_user` y `status=pending`).
- [ ] **Step 3:** tests PASS. Commit `feat(ajustes): rechazar/cancelar`.

### Fase 3 — Resource, rutas, y endurecimiento de reportes

#### Task 8: `AdjustmentResource` + rutas

**Files:** Create `AdjustmentResource.php`; Modify `routes/api.php`.

- [ ] **Step 1:** Resource dual naming (snake_case + flat `product_name`, `reason_name`, `origin_location_name`, `destination_location_name`, `requester_name`, `approver_name` con `whenLoaded`).
- [ ] **Step 2:** Rutas (dentro de `auth:api`):
```php
Route::get('adjustment-reasons', [AdjustmentController::class, 'reasons']);
Route::get('adjustments', [AdjustmentController::class, 'index']);
Route::get('adjustments/{id}', [AdjustmentController::class, 'show']);
// Crear/cancelar: CUALQUIER rol autenticado (el cliente pidió "cualquier rol hace ajustes").
Route::post('adjustments', [AdjustmentController::class, 'store']);
Route::put('adjustments/{id}/cancel', [AdjustmentController::class, 'cancel']);
// Aprobar/Rechazar: SOLO admin (rol exacto).
Route::middleware('role:admin')->group(function () {
    Route::put('adjustments/{id}/approve', [AdjustmentController::class, 'approve']);
    Route::put('adjustments/{id}/reject', [AdjustmentController::class, 'reject']);
});
```
  (Crear queda abierto a cualquier autenticado; el aislamiento por ubicación limita qué ubicaciones puede elegir/ver. Solo `approve`/`reject` son admin.)
- [ ] **Step 3: Test** — `$DC php artisan route:list | grep adjustment` muestra las 6 rutas. Commit `feat(ajustes): resource + rutas`.

#### Task 9: Endurecer clasificación de reportes

**Files:** Modify `InventoryController.php:~1512-1531` (increases/decreases); Test `tests/Feature/AdjustmentReportsConsistencyTest.php`.

**Interfaces — Consumes:** movimientos de ajuste con `related_document_type='App\Models\Adjustment'`.

- [ ] **Step 1: Test (falla)** — tras aprobar un ajuste positivo en la bodega del reporte, `monthlyReport` del mes de `movement_date` cuenta ese producto en `increases` (no en `purchases`), y `variation == 0`; un ajuste negativo cae en `decreases`.
- [ ] **Step 2: Implementación** — en el bloque `increases` añadir OR `related_document_type='App\\Models\\Adjustment'` (y análogo en `decreases`), manteniendo el match por texto para retrocompatibilidad:
```php
// increases
->where(function ($q) {
    $q->where('observations','like','%aumento%')
      ->orWhere('observations','like','%ajuste%positiv%')
      ->orWhere('related_document_type','App\\Models\\Adjustment');
})
// decreases  (análogo con related_document_type)
```
- [ ] **Step 3:** test PASS; correr `--filter=Monthly` si existe para no romper. Commit `feat(ajustes): reportes clasifican ajustes por documento`.

### Fase 4 — Frontend

**Skill:** invocar `frontend-design:frontend-design` antes de construir la página (estética/UX consistente con Ant Design del proyecto).

#### Task 10: `adjustmentsApi` + tipos

**Files:** Modify `services/api.ts`, `types/index.ts`.

- [ ] **Step 1:** `adjustmentsApi = { list(params), get(id), create(data), approve(id), reject(id, reason), cancel(id), reasons() }` (fetch a `/adjustments...`). `adjustmentReasonsApi.list()` → `/adjustment-reasons`. Tipos `Adjustment`, `AdjustmentReason`.
- [ ] **Step 2:** `cd frontend && nvm use 22 && npx tsc --noEmit` → 0 errores. Commit `feat(ajustes): api frontend`.

#### Task 11: Página `Adjustments.tsx`

**Files:** Create `pages/inventory/Adjustments.tsx` (patrón `pages/outputs/Outputs.tsx`).

- [ ] **Step 1:** Lista `ResponsiveTable` (mobile+desktop) con columnas: N° ajuste, tipo (tag), producto, ubicación(es), cantidad±, motivo, estado (tag), solicitante, fecha; filtro por estado. Query `['adjustments', filtros]`.
- [ ] **Step 2:** Modal "Nueva Solicitud" (Ant Form): `type` (Select entry/exit/transfer), `reason_id` (Select desde `adjustmentReasonsApi`, filtrado por `direction`), producto (Select con búsqueda), ubicación origen/destino (según type; aplicar filtro por responsable como en `Outputs.tsx`), `batch_number` (Select de lotes disponibles del producto en la ubicación, o "FIFO automático"), `quantity_mode` (Radio delta/absoluto), `quantity` (InputNumber), `unit` (Select unidades del producto), `unit_price` (solo entry), `movement_date` (DatePicker requerido), `notes` (TextArea). Mostrar stock disponible de referencia. Mutación `create` → invalida `['adjustments']`.
- [ ] **Step 3 (admin):** en la fila/detalle de un `pending`, botones **Aprobar** / **Rechazar** (Popconfirm; rechazar pide motivo). Solo visibles si `getRoleName()==='admin'`. Mutaciones `approve`/`reject` → invalidan `['adjustments','inventory','movements']`. Badge de pendientes.
- [ ] **Step 4:** `npx tsc --noEmit` 0 errores. Commit `feat(ajustes): página de ajustes`.

#### Task 12: Menú + ruta

**Files:** Modify `MainLayout.tsx` (`sub4` children), `App.tsx`.

- [ ] **Step 1:** Añadir child `{ key: '/inventory/adjustments', label: 'Ajustes de Inventario' }` dentro del bloque de Inventario (gated por `hasModuleAccess('inventory')`; opcional además `hasPermission('adjust_inventory')`). Ruta en `App.tsx` con `<ProtectedRoute>` que renderice `<Adjustments/>`.
- [ ] **Step 2:** `npx tsc --noEmit` + `npx vite build` OK. Commit `feat(ajustes): menú + ruta`.

### Fase 5 — Pruebas completas (ver Sección E)

### Fase 6 — Revisión y deploy

- [ ] **Task 13:** `superpowers:requesting-code-review` + `/code-review` sobre el diff completo; corregir findings critical/high.
- [ ] **Task 14:** Verificación final (`superpowers:verification-before-completion`) + Workflow de Sección E en verde.
- [ ] **Task 15:** `/deploy` (commit selectivo a `main`; el pipeline corre la migración + seeder en prod — **añadir `php artisan db:seed --class=AdjustmentReasonSeeder --force` al deploy** o correrlo manual post-deploy). Verificar salud prod + que `adjustment-reasons` responde.

---

## Sección D — Pruebas individuales (por componente)

Correr con `$DC php artisan test --filter=<clase o método>`.

**Backend (feature/unit):**
1. `AdjustmentReasonSeeder`: 10 motivos, `compra_doble` direction=exit.
2. `Adjustment::generateAdjustmentNumber` formato `AJU-YYYYMMDD-XXXX`, incremental.
3. `Adjustment` auditable (deja fila en `audits`).
4. `InventoryService::addStock`: lote nuevo; promedio ponderado (20@6 desde 10@5+10@7); lock.
5. `StoreAdjustmentRequest`: falta type→422; entry sin destination→422; transfer origin==destination→422; absolute sin batch→422; válido→201.
6. `store`: crea `pending`, NO toca inventory/movements.
7. `index`: aislamiento por ubicación (supervisor ve solo sus ubicaciones; admin todo).
8. `approve`: solo admin (403 a otros); entry sube stock + movimiento entry + observaciones "aumento" + related_document_type Adjustment; exit baja por FIFO + "disminución"; exit insuficiente→422 y stock intacto; transfer baja origen y sube destino (dos movimientos); absoluto fija lote y calcula delta; signo inválido→422.
9. `reject`: solo admin, setea rejected+motivo, no toca stock.
10. `cancel`: solo solicitante y solo pending.
11. Reportes: `AdjustmentReportsConsistencyTest` (ver E).

**Frontend:** `tsc --noEmit` 0 errores tras cada tarea; `vite build` OK al final.

---

## Sección E — Pruebas completas + verificación de informes (Workflow)

**Objetivo:** garantizar que tras aplicar ajustes, TODOS los informes quedan correctos, y el flujo E2E funciona. Ejecutar un **Workflow** con agentes en paralelo (opus). Prerequisitos: stack Docker arriba + datos (idealmente snapshot de prod — ver nota).

**E.1 — Test de consistencia de informes (`AdjustmentReportsConsistencyTest.php`, backend):**
Para cada tipo aprobado, aserta:
- **Inventario Mensual** (`monthlyReport` del mes de `movement_date`): ajuste `entry` → producto en `increases` (NO en `purchases`); `exit` → en `decreases`; `initial/final` derivados de movimientos cuadran; **`variation == 0`**.
- **Kardex** (`inventory/movements`, `inventory/movements/report`, `inventory/kardex/product/{id}`): el movimiento aparece con `type` correcto, `movement_date`, observaciones con la palabra clave, y `related_document_type='App\Models\Adjustment'`.
- **Stock actual** (`inventory`): suma de lotes = esperado tras el ajuste.
- **Product-listing / stock report / farm reports**: reflejan el nuevo stock (status 200 + valores).
- **Doble contabilización:** un ajuste NO debe aparecer a la vez en `purchases` e `increases`.

**E.2 — Workflow de verificación (agentes paralelos, opus):** replicar el patrón del workflow ya usado en este repo (`verify_workflow.js`):
- Agente **reportes**: como admin, tras crear+aprobar un ajuste de cada tipo, verificar los 17 endpoints de reporte (200 + clasificación correcta + `variation==0`).
- Agente **flujo aprobación**: crear como bodega (pending, stock intacto) → aprobar como admin (stock aplicado) → verificar; rechazar otra (sin efecto); intentar aprobar como no-admin (403); re-validación con dos pendientes sobre el mismo lote.
- Agente **escenario cliente**: simular compra doble recibida (dos recepciones), crear Salida del lote inflado, aprobar, verificar stock corregido + kardex con `exit` ligado al ajuste + reporte mensual cuadra.
- Agente **revisión adversarial**: `git diff` del módulo; auditar authz (solo admin aprueba), transacciones/lock, prevención de negativo, clasificación de reportes, y que no se use `type='adjustment'`.
- Agente **síntesis**: GO/NO-GO.

**E.3 — E2E Playwright (`/dev-agri` o `/test-feature`):**
- Login bodega → crear solicitud de cada tipo → ver `pending`.
- Login admin → ver cola de pendientes → aprobar una y rechazar otra → verificar estados y que el stock/kardex cambian solo tras aprobar.
- Verificar que un no-admin no ve botones Aprobar/Rechazar.
- Screenshots de evidencia.

**E.4 — `dev-methodology:integration-test`** para smoke E2E con auto-corrección pragmática.

**Nota (BD de prod):** para E.2/E.3 con datos reales, bajar un snapshot de prod al entorno local:
`ssh <VPS> "docker exec agriflor-mysql mysqldump -uagriflor -p<pass> agriflor" > /tmp/agriflor_prod.sql` y restaurar en el MySQL local Docker (`docker compose exec -T mysql mysql -uagriflor -psecret agriflor < /tmp/agriflor_prod.sql`). **Nunca** correr ajustes de prueba contra prod; solo contra el clon local.

---

## Sección F — Prompt de ejecución

> Copiar/pegar como tarea para el ejecutor (con `/model` en **Opus 5** o el más capaz):

```
Implementa el módulo de Ajustes de Inventario de AgriFlor siguiendo EXACTAMENTE el plan en
docs/superpowers/plans/2026-07-16-modulo-ajustes-inventario.md (y el spec en
docs/superpowers/specs/2026-07-16-modulo-ajustes-inventario-design.md). Usa el modelo más
capaz (Opus 5): máximo criterio de análisis, codificación y pruebas.

Proceso obligatorio:
1. Monta: superpowers:using-git-worktrees → rama feat/ajustes-inventario desde origin/main;
   stack Docker arriba + migrate.
2. Implementa tarea por tarea con superpowers:subagent-driven-development (subagente fresco por
   tarea, revisión entre tareas) y superpowers:test-driven-development (test falla → implementa →
   test pasa → commit). Un solo commit por tarea.
3. En TODO código nuevo cumple dev-methodology:restricciones-calidad y restricciones-seguridad.
4. Backend primero (Fases 0-3), luego Frontend (Fase 4) invocando frontend-design:frontend-design
   para la página. Paraleliza con superpowers:dispatching-parallel-agents donde el plan lo permita.
5. Respeta las Global Constraints del plan al pie de la letra (nunca type='adjustment';
   related_document_type='App\Models\Adjustment'; observaciones con palabras clave; movement_date;
   lockForUpdate; solo admin aprueba).
6. Pruebas: corre las pruebas individuales de la Sección D tras cada tarea. Al terminar, ejecuta el
   Workflow de verificación de la Sección E (agentes paralelos en opus) + E2E con /dev-agri o
   /test-feature, y verifica que los INFORMES quedan correctos (variation==0, increases/decreases,
   sin doble conteo). Si algo falla, usa superpowers:systematic-debugging.
7. Revisión: superpowers:requesting-code-review + /code-review; corrige critical/high.
8. Verificación final: superpowers:verification-before-completion (evidencia real, no aserciones).
9. NO despliegues automáticamente: deja todo verificado y pide confirmación para /deploy (incluye
   correr AdjustmentReasonSeeder en prod).

Entrega: código + tests en verde + reporte de evidencia (pruebas individuales, workflow de
verificación, E2E, y consistencia de informes).
```

## Self-Review (writing-plans)

- **Cobertura del spec:** modelo de datos (Tasks 1-2), stock por tipo/modo (Tasks 3,6), motivo+catálogo (Task 1), delta/absoluto (Task 6 `resolveDeltaBase`), aprobación obligatoria solo-admin (Tasks 6-7-8), auditoría (Task 2), reportes (Task 9 + Sección E), frontend + menú (Tasks 10-12), permisos/aislamiento (Tasks 5,8 + rutas). ✔
- **Sin placeholders críticos:** el código load-bearing (migraciones, addStock, approve, resolveDeltaBase, reportes) está explícito; el boilerplate referencia patrones exactos existentes (Outputs.tsx, ProductOutput.php, updateInventoryStock). Los helpers `applyEntry/applyExit/applyEntryForTransfer` están especificados con sus llamadas exactas.
- **Consistencia de tipos:** `addStock(...)` firma usada igual en Task 3 y Task 6; `related_document_type='App\Models\Adjustment'` consistente en Tasks 6, 9, E; `generateAdjustmentNumber` formato consistente.
- **Decisiones cerradas:** crear = cualquier rol autenticado (confirmado por el cliente); aprobar/rechazar = solo admin. Aislamiento por ubicación limita las ubicaciones seleccionables.
