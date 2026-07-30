<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AdjustmentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\RejectAdjustmentRequest;
use App\Http\Requests\StoreAdjustmentRequest;
use App\Http\Resources\AdjustmentResource;
use App\Models\Adjustment;
use App\Models\AdjustmentReason;
use App\Models\Brand;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AdjustmentController extends Controller
{
    /**
     * Relaciones que necesita AdjustmentResource para poblar los campos planos
     * (reason_name, product_name, etc.) sin incurrir en N+1.
     */
    private const RELATIONS = [
        'reason',
        'product',
        'brand',
        'originLocation',
        'destinationLocation',
        'requester',
        'approver',
    ];

    /**
     * Reintentos ante colisión del número de ajuste generado (ver
     * Adjustment::generateAdjustmentNumber, que no usa lock). Una colisión solo
     * puede ocurrir por una carrera entre dos solicitudes concurrentes del mismo día.
     */
    private const MAX_NUMBER_RETRIES = 3;

    /**
     * Nombre del índice UNIQUE de adjustment_number (convención de Laravel:
     * {tabla}_{columna}_unique). Se usa para distinguir ESTA colisión de
     * cualquier otro 1062 sobre la tabla adjustments (ver isDuplicateAdjustmentNumber).
     */
    private const ADJUSTMENT_NUMBER_UNIQUE_INDEX = 'adjustments_adjustment_number_unique';

    /**
     * Tipo de documento con el que se ligan los movimientos generados al aprobar.
     *
     * Literal a propósito (NO $adjustment->getMorphClass()): el proyecto llama a
     * Relation::enforceMorphMap() en AppServiceProvider, así que getMorphClass()
     * devolvería el alias 'adjustment', mientras que los informes clasifican
     * comparando contra el nombre de clase completo — InventoryController::
     * monthlyReport filtra `related_document_type LIKE '%Reception'` y
     * ReportExportController compara contra 'App\Models\Reception'. Guardar el
     * alias dejaría los movimientos de ajuste fuera de esa clasificación.
     */
    private const MOVEMENT_DOCUMENT_TYPE = 'App\Models\Adjustment';

    /**
     * Etiquetas legibles al inicio de las observaciones del movimiento, para que
     * el kardex y el detalle del ajuste digan de un vistazo si subió o bajó el
     * stock.
     *
     * NO son las que clasifican el informe mensual. Desde el endurecimiento de
     * InventoryController::whereClassifiedAsAdjustment, un movimiento cuyo
     * `related_document_type` es 'App\Models\Adjustment' se clasifica por
     * `adjustments.type` (el DOCUMENTO), y el texto de las observaciones solo se
     * usa como criterio para el histórico previo a este módulo, que no tiene
     * documento relacionado. Cambiar estos textos, por tanto, ya no rompe las
     * columnas "Aumentos"/"Disminuciones".
     *
     * Lo que SÍ sigue siendo load-bearing es no ponerle la etiqueta negativa a
     * la salida de un traslado (ver transferExitObservations): el histórico se
     * clasifica por texto, así que un mantenedor que "unifique" las
     * observaciones dejaría esa salida marcada como disminución y cualquier
     * consumidor que clasifique por texto —incluido cualquier informe futuro—
     * la restaría dos veces.
     */
    private const POSITIVE_TAG = '[AUMENTO / ajuste positivo]';
    private const NEGATIVE_TAG = '[DISMINUCIÓN / ajuste negativo]';

    /**
     * Tolerancia (en unidad base) para comparar cantidades, la misma que usa
     * InventoryService en su bucle FIFO: evita que un residuo de coma flotante
     * se lea como stock faltante o como un delta real que ajustar.
     */
    private const QUANTITY_EPSILON = 0.01;

    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    /**
     * Lista paginada de ajustes, con aislamiento por ubicación para los roles
     * restringidos (supervisor, farm) y filtro opcional por estado.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Adjustment::query()->with(self::RELATIONS);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $this->applyLocationScope($query, $request->user());

        $perPage = (int) $request->input('per_page', 15);

        $adjustments = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return AdjustmentResource::collection($adjustments);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $adjustment = Adjustment::with(self::RELATIONS)->findOrFail($id);

        $this->authorizeLocationAccess($adjustment, $request->user());

        return response()->json([
            'success' => true,
            'data' => new AdjustmentResource($adjustment),
        ]);
    }

    /**
     * Crea una solicitud de ajuste en estado 'pending'. NO toca inventory ni crea
     * InventoryMovement: el stock solo se afecta al aprobar (fuera del alcance
     * de este controlador).
     */
    public function store(StoreAdjustmentRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $deniedMessage = $this->deniedLocationMessage($data, $user);
        if ($deniedMessage !== null) {
            return response()->json([
                'success' => false,
                'message' => $deniedMessage,
            ], 403);
        }

        $inapplicableMessage = $this->inapplicableAdjustmentMessage($data);
        if ($inapplicableMessage !== null) {
            return response()->json([
                'success' => false,
                'message' => $inapplicableMessage,
            ], 422);
        }

        $adjustment = $this->createAdjustment($data, $user->id);
        $adjustment->load(self::RELATIONS);

        return response()->json([
            'success' => true,
            'message' => 'Solicitud de ajuste creada exitosamente.',
            'data' => new AdjustmentResource($adjustment),
        ], 201);
    }

    /**
     * Catálogo de motivos activos para poblar el selector del frontend.
     *
     * Filtrar por `direction` NO puede ser un match exacto: un motivo con
     * direction='any' es válido para CUALQUIER tipo de ajuste (así lo exige
     * StoreAdjustmentRequest::validateReasonDirection al crear la solicitud), así que
     * pedir ?direction=exit debe devolver los motivos 'exit' Y los 'any' —de lo
     * contrario el endpoint miente sobre qué motivos son realmente utilizables para
     * ese tipo, y cualquier consumidor que confíe en el filtro del servidor (en vez
     * de replicar la regla en cliente) ocultaría opciones legítimas.
     */
    public function reasons(Request $request): JsonResponse
    {
        $query = AdjustmentReason::query()->active();

        if ($request->filled('direction')) {
            $direction = $request->input('direction');
            $query->whereIn('direction', array_unique([$direction, 'any']));
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('name')->get(),
        ]);
    }

    /**
     * Aprueba una solicitud pendiente y APLICA el movimiento al inventario.
     *
     * Solo admin (restringido por el middleware `role:admin` en la ruta). Todo
     * el efecto sobre el stock ocurre dentro de una transacción con
     * lockForUpdate sobre los lotes implicados: el stock disponible se
     * RE-VALIDA en el momento de aprobar (pudo cambiar desde que se creó la
     * solicitud), y si algo falla se revierte por completo dejando la solicitud
     * en 'pending' y el inventario intacto.
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $adjustment = Adjustment::with(self::RELATIONS)->findOrFail($id);

        // Defensa en profundidad: hoy la ruta ya exige admin (que ve todas las
        // ubicaciones), pero si el rol permitido se ampliara, la aprobación
        // hereda el mismo guard de ubicación que show().
        $this->authorizeLocationAccess($adjustment, $request->user());

        if ($adjustment->status !== 'pending') {
            return $this->alreadyProcessedResponse();
        }

        try {
            DB::beginTransaction();

            // Re-lectura del estado CON la fila bloqueada: sin esto, dos
            // aprobaciones simultáneas del mismo ajuste pasarían ambas el
            // chequeo de arriba y aplicarían el stock dos veces.
            if (!$this->isPendingLocked($adjustment->id)) {
                DB::rollBack();

                return $this->alreadyProcessedResponse();
            }

            $this->assertMovementDateNotClosed($adjustment);

            $userId = $request->user()->id;
            $deltaBase = $this->resolveDeltaBase($adjustment);

            $this->applyStock($adjustment, $deltaBase, $userId);

            $adjustment->update([
                'status' => 'approved',
                'approved_by' => $userId,
                'approved_at' => now(),
                'quantity_base' => $deltaBase,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Adjustment approval failed', [
                'adjustment_id' => $adjustment->id,
                'type' => $adjustment->type,
                'quantity_mode' => $adjustment->quantity_mode,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            $isBusinessFailure = $this->isBusinessFailure($e);

            return response()->json([
                'success' => false,
                'message' => $isBusinessFailure
                    ? 'No se pudo aplicar el ajuste: ' . $e->getMessage()
                    : 'No se pudo aplicar el ajuste por un error interno. El detalle quedó registrado; contacte al administrador.',
            ], $isBusinessFailure ? 422 : 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ajuste aprobado y aplicado al inventario.',
            'data' => new AdjustmentResource($adjustment->fresh(self::RELATIONS)),
        ]);
    }

    /**
     * Rechaza una solicitud pendiente. SOLO admin (middleware `role:admin` en
     * la ruta, igual que approve) — deliberadamente NO se reutiliza
     * authorizeLocationAccess()/canAccessAdjustment() aquí: ese guard también
     * concede acceso al solicitante y a responsables de las ubicaciones
     * implicadas, y usarlo para autorizar el rechazo dejaría la puerta
     * abierta a que, si la ruta alguna vez se amplía a más roles, un
     * responsable de ubicación sin el rol admin pueda rechazar. La única
     * autorización de esta acción es la del middleware de la ruta.
     *
     * NO toca inventory ni inventory_movements bajo ninguna circunstancia:
     * rechazar es una decisión administrativa sobre la SOLICITUD, no sobre el
     * stock (que solo se afecta al aprobar).
     */
    public function reject(RejectAdjustmentRequest $request, string $id): JsonResponse
    {
        $adjustment = Adjustment::with(self::RELATIONS)->findOrFail($id);

        if ($adjustment->status !== 'pending') {
            return $this->alreadyProcessedResponse();
        }

        try {
            DB::beginTransaction();

            // Mismo patrón que approve(): re-lee el status con la fila
            // bloqueada para que un rechazo y una aprobación concurrentes del
            // mismo ajuste no pasen ambos el chequeo de arriba.
            if (!$this->isPendingLocked($adjustment->id)) {
                DB::rollBack();

                return $this->alreadyProcessedResponse();
            }

            $adjustment->update([
                'status' => 'rejected',
                'rejection_reason' => $request->validated('rejection_reason'),
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Adjustment rejection failed', [
                'adjustment_id' => $adjustment->id,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo rechazar la solicitud por un error interno. El detalle quedó registrado; contacte al administrador.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Solicitud de ajuste rechazada.',
            'data' => new AdjustmentResource($adjustment->fresh(self::RELATIONS)),
        ]);
    }

    /**
     * Cancela una solicitud pendiente. SOLO el solicitante original
     * (responsible_user), sin excepción: ni un admin distinto de quien la
     * creó puede cancelarla (para eso existe reject). La comparación es
     * explícita a propósito — canAccessAdjustment() NO sirve aquí porque
     * también concede acceso a responsables de las ubicaciones implicadas,
     * que no deben poder cancelar una solicitud ajena.
     *
     * NO toca inventory ni inventory_movements: una solicitud pendiente nunca
     * llegó a afectar el stock.
     *
     * Envuelto en el mismo try/catch que reject()/approve(): isPendingLocked()
     * adquiere un lockForUpdate sobre la fila, que es justo el escenario donde
     * un deadlock o lock wait timeout de MySQL (error 1205) es plausible —la
     * misma carrera de la que este método se defiende con el lock—, y sin el
     * catch esa excepción llegaría cruda al cliente en vez de pasar por el
     * mismo manejo controlado (rollback + distinción negocio/técnico) que ya
     * tienen sus hermanos.
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $adjustment = Adjustment::with(self::RELATIONS)->findOrFail($id);

        if ($adjustment->responsible_user !== $request->user()->id) {
            abort(403, 'Solo quien solicitó el ajuste puede cancelarlo.');
        }

        if ($adjustment->status !== 'pending') {
            return $this->alreadyProcessedResponse();
        }

        try {
            DB::beginTransaction();

            // Mismo patrón de re-lectura bloqueada que approve()/reject():
            // evita cancelar una solicitud que se está aprobando o
            // rechazando en paralelo.
            if (!$this->isPendingLocked($adjustment->id)) {
                DB::rollBack();

                return $this->alreadyProcessedResponse();
            }

            $adjustment->update(['status' => 'cancelled']);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Adjustment cancellation failed', [
                'adjustment_id' => $adjustment->id,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo cancelar la solicitud por un error interno. El detalle quedó registrado; contacte al administrador.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Solicitud de ajuste cancelada.',
            'data' => new AdjustmentResource($adjustment->fresh(self::RELATIONS)),
        ]);
    }

    /**
     * ¿El fallo es una regla de negocio, con un mensaje pensado para el usuario?
     *
     * Lo son las de AdjustmentException (las que lanza esta clase) y las de
     * InventoryService, que usa `new \Exception(...)` EXACTAMENTE —no una
     * subclase— para stock insuficiente, cantidad no positiva o producto
     * inexistente, siempre con texto en español.
     *
     * Todo lo demás es un fallo técnico cuyo mensaje NUNCA debe salir al
     * cliente: una QueryException, por ejemplo, incluye el SQL completo con sus
     * bindings. Esos casos van solo al log y responden 500 genérico.
     */
    private function isBusinessFailure(\Throwable $e): bool
    {
        return $e instanceof AdjustmentException || $e::class === \Exception::class;
    }

    private function alreadyProcessedResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'La solicitud ya fue procesada.',
        ], 422);
    }

    /**
     * Re-lee el status de un ajuste con la fila bloqueada (lockForUpdate),
     * DENTRO de una transacción abierta por el llamador. Compartido por
     * approve/reject/cancel: los tres resuelven una solicitud 'pending' y
     * deben evitar que dos resoluciones concurrentes (p. ej. aprobar y
     * rechazar el mismo ajuste al mismo tiempo) pasen ambas el chequeo hecho
     * antes de abrir la transacción.
     */
    private function isPendingLocked(string $adjustmentId): bool
    {
        return Adjustment::whereKey($adjustmentId)->lockForUpdate()->value('status') === 'pending';
    }

    /**
     * Aplica el aislamiento por ubicación al listado: los roles restringidos
     * (supervisor, farm) solo ven ajustes donde alguna de sus ubicaciones
     * administradas participa como origen o destino, O de los que ellos mismos
     * son el solicitante (responsible_user) — un solicitante siempre debe poder
     * ver su propia solicitud aunque, por ejemplo, ya no administre esa
     * ubicación al momento de consultar. Los demás roles ven todo.
     */
    private function applyLocationScope(Builder $query, ?User $user): void
    {
        if (!$user || $user->canViewAllLocations()) {
            return;
        }

        $managedIds = $user->managedLocationIds();
        $userId = $user->id;

        $query->where(function (Builder $q) use ($managedIds, $userId) {
            $q->whereIn('origin_location_id', $managedIds)
                ->orWhereIn('destination_location_id', $managedIds)
                ->orWhere('responsible_user', $userId);
        });
    }

    /**
     * Guard centralizado de acceso por ID: usado hoy por show() y pensado para
     * ser reutilizado tal cual por approve/reject/cancel (tareas siguientes),
     * que también resuelven un Adjustment por ID y heredarían el mismo hueco
     * de IDOR si cada una reimplementara su propia verificación.
     *
     * Responde 403 (con abort, formateado como JSON por el handler por defecto
     * ante peticiones que esperan JSON) si el usuario está restringido por
     * ubicación y el ajuste no lo involucra ni como responsable de una
     * ubicación relacionada ni como solicitante.
     */
    private function authorizeLocationAccess(Adjustment $adjustment, ?User $user): void
    {
        if ($this->canAccessAdjustment($adjustment, $user)) {
            return;
        }

        abort(403, 'No tienes acceso a este ajuste: no involucra ninguna ubicación de la que eres responsable ni fue creado por ti.');
    }

    private function canAccessAdjustment(Adjustment $adjustment, ?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->canViewAllLocations()) {
            return true;
        }

        if ($adjustment->responsible_user === $user->id) {
            return true;
        }

        $managedIds = $user->managedLocationIds();

        return in_array($adjustment->origin_location_id, $managedIds, true)
            || in_array($adjustment->destination_location_id, $managedIds, true);
    }

    /**
     * Para roles restringidos por ubicación (supervisor, farm), valida que el
     * usuario sea responsable de la(s) ubicación(es) relevante(s) del ajuste.
     * Devuelve el mensaje de error si debe denegarse, o null si puede continuar.
     *
     * La regla NO es uniforme por diseño:
     * - `entry`/`exit` tienen una sola ubicación relevante (destino u origen,
     *   respectivamente) — esa debe ser suya, sin excepción.
     * - `transfer` tiene DOS ubicaciones, y exigir que ambas sean suyas bloquea
     *   un caso de negocio legítimo y central del módulo: "devolver producto de
     *   mi finca a la bodega central" (origen mío, destino ajeno) o el
     *   simétrico "traer producto de la bodega central a mi finca" (destino
     *   mío, origen ajeno). Para `transfer` basta con ser responsable de UNA de
     *   las dos; la solicitud no queda huérfana aunque la otra sea ajena,
     *   porque el solicitante siempre la ve en su index vía
     *   origin/destination (la que sí administra) o vía
     *   `responsible_user` (orWhere en applyLocationScope).
     */
    private function deniedLocationMessage(array $data, ?User $user): ?string
    {
        if (!$user || $user->canViewAllLocations()) {
            return null;
        }

        $managedIds = $user->managedLocationIds();

        if (($data['type'] ?? null) === 'transfer') {
            return $this->deniedTransferLocationMessage($data, $managedIds);
        }

        foreach (['origin_location_id', 'destination_location_id'] as $field) {
            $locationId = $data[$field] ?? null;

            if ($locationId !== null && !in_array($locationId, $managedIds, true)) {
                return 'Solo puedes registrar ajustes en ubicaciones de las que eres responsable.';
            }
        }

        return null;
    }

    private function deniedTransferLocationMessage(array $data, array $managedIds): ?string
    {
        $originId = $data['origin_location_id'] ?? null;
        $destinationId = $data['destination_location_id'] ?? null;

        $isOriginManaged = $originId !== null && in_array($originId, $managedIds, true);
        $isDestinationManaged = $destinationId !== null && in_array($destinationId, $managedIds, true);

        if ($isOriginManaged || $isDestinationManaged) {
            return null;
        }

        return 'Para registrar un traslado debes ser responsable del origen o del destino.';
    }

    /**
     * Rechazo TEMPRANO (al solicitar) de un ajuste que la aprobación nunca podría
     * aplicar. Devuelve el mensaje de error, o null si la solicitud es viable.
     *
     * Sin esto, el error solo aparece al APROBAR —días después, en la pantalla de
     * otra persona— y el solicitante se queda en un callejón sin salida: el caso
     * real es el de la marca. `inventory.brand_id` puede diferir de
     * `products.brand_id` (en producción, 77 lotes con ~89.000 unidades están
     * guardados bajo una marca distinta a la del producto), así que una salida
     * pedida con la marca del PRODUCTO sobre un lote guardado con otra marca no
     * encuentra existencias: el desplegable muestra 500 kg y la aprobación
     * responde "solo hay 0". Validarlo aquí obliga a que el ajuste apunte al
     * (producto, marca, ubicación) que realmente tiene el stock.
     *
     * Ojo con lo que este chequeo NO hace: no compara contra la cantidad pedida.
     * El stock puede cambiar legítimamente entre la solicitud y la aprobación, y
     * la validación autoritativa (con las filas bloqueadas) vive en applyExit().
     * Aquí solo se descarta el caso imposible: que NO haya nada que ajustar.
     */
    private function inapplicableAdjustmentMessage(array $data): ?string
    {
        $type = $data['type'] ?? null;
        $productId = $data['product_id'] ?? null;
        $brandId = $data['brand_id'] ?? null;
        $batchNumber = $data['batch_number'] ?? null;

        $locationId = $type === 'entry'
            ? ($data['destination_location_id'] ?? null)
            : ($data['origin_location_id'] ?? null);

        if (!is_string($productId) || !is_string($brandId) || !is_string($locationId)) {
            // Faltan datos que la validación ya reportó (o el tipo no aplica).
            return null;
        }

        if (($data['quantity_mode'] ?? null) === 'absolute') {
            return $this->missingBatchForAbsoluteMessage($productId, $brandId, $locationId, $batchNumber);
        }

        if (!in_array($type, ['exit', 'transfer'], true)) {
            return null;
        }

        if ($this->stockInBase($productId, $brandId, $locationId, $batchNumber) > 0) {
            return null;
        }

        return sprintf(
            "No hay existencias de %s en %s%s: no se puede registrar una salida ni un traslado desde ahí. " .
            'Revise la marca y el lote del inventario que quiere ajustar (el stock puede estar registrado bajo otra marca).',
            $this->describeProductAndBrand($productId, $brandId),
            $this->locationName($locationId),
            $batchNumber ? " para el lote '{$batchNumber}'" : ''
        );
    }

    /**
     * El modo absoluto FIJA el saldo de un lote existente, así que exige que el
     * lote exista: si no existe, el "saldo que debe quedar" se aplicaría como un
     * delta completo sobre un lote nuevo y DUPLICARÍA las existencias del
     * producto en esa ubicación (medido: 16.044 g → 32.044 g al fijar el saldo
     * en 16.000 con un número de lote inventado).
     *
     * El mensaje empuja explícitamente a la entrada en modo DELTA, que es el
     * camino correcto para crear un lote nuevo.
     */
    private function missingBatchForAbsoluteMessage(
        string $productId,
        string $brandId,
        string $locationId,
        ?string $batchNumber
    ): ?string {
        if ($batchNumber === null || $batchNumber === '') {
            // Ya reportado por validateQuantityMode (batch_number requerido).
            return null;
        }

        if ($this->batchExists($productId, $brandId, $locationId, $batchNumber)) {
            return null;
        }

        return sprintf(
            "El lote '%s' de %s no existe en %s: fijar el saldo de un lote solo puede ajustar un lote que ya existe " .
            '(si no, el saldo indicado se sumaría como stock nuevo y duplicaría las existencias). ' .
            'Para cargar un lote nuevo registre un ajuste de ENTRADA en modo delta con la cantidad a ingresar.',
            $batchNumber,
            $this->describeProductAndBrand($productId, $brandId),
            $this->locationName($locationId)
        );
    }

    private function describeProductAndBrand(string $productId, string $brandId): string
    {
        $productName = Product::where('id', $productId)->value('name') ?? $productId;
        $brandName = Brand::where('id', $brandId)->value('name');

        return $brandName ? "'{$productName}' (marca {$brandName})" : "'{$productName}'";
    }

    private function locationName(string $locationId): string
    {
        return Location::where('id', $locationId)->value('name') ?? 'la ubicación indicada';
    }

    /**
     * ¿Existe la fila de `inventory` de ese lote exacto?
     *
     * Deliberadamente SIN filtrar por `quantity > 0`: un lote con saldo cero
     * sigue siendo un lote existente y addStock() lo reutiliza (la clave única es
     * product+brand+location+batch), así que fijarle el saldo es legítimo.
     */
    private function batchExists(
        string $productId,
        string $brandId,
        string $locationId,
        string $batchNumber,
        bool $lock = false
    ): bool {
        $query = Inventory::where('product_id', $productId)
            ->where('brand_id', $brandId)
            ->where('location_id', $locationId)
            ->where('batch_number', $batchNumber);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->exists();
    }

    /**
     * Crea el Adjustment con número autogenerado, reintentando ante una colisión de
     * UNIQUE en adjustment_number (generateAdjustmentNumber() no usa lock).
     */
    private function createAdjustment(array $data, string $userId): Adjustment
    {
        for ($attempt = 1; $attempt <= self::MAX_NUMBER_RETRIES; $attempt++) {
            try {
                return DB::transaction(function () use ($data, $userId) {
                    return Adjustment::create(array_merge($data, [
                        'adjustment_number' => Adjustment::generateAdjustmentNumber(),
                        'responsible_user' => $userId,
                        'status' => 'pending',
                    ]));
                });
            } catch (QueryException $e) {
                if (!$this->isDuplicateAdjustmentNumber($e) || $attempt === self::MAX_NUMBER_RETRIES) {
                    throw $e;
                }
            }
        }

        // Inalcanzable: cada iteración retorna o relanza dentro del catch.
        throw new \RuntimeException('No se pudo generar un número de ajuste único.');
    }

    /**
     * Distingue específicamente la colisión del índice UNIQUE de
     * adjustment_number de cualquier otro error 1062 sobre la tabla adjustments
     * (por ejemplo, si en el futuro se agregara otro UNIQUE). Buscar la
     * subcadena "adjustment_number" en el mensaje NO sirve: el mensaje de
     * MySQL para un 1062 incluye el SQL completo del INSERT fallido, que
     * siempre contiene esa columna sin importar cuál índice haya chocado.
     * Se compara contra el nombre real del índice (convención de Laravel:
     * {tabla}_{columna}_unique), que sí aparece en el mensaje de error de MySQL
     * ("Duplicate entry '...' for key 'adjustments_adjustment_number_unique'").
     */
    private function isDuplicateAdjustmentNumber(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062
            && str_contains($e->getMessage(), self::ADJUSTMENT_NUMBER_UNIQUE_INDEX);
    }

    // ------------------------------------------------------------------
    // Aprobación: cálculo del delta y aplicación al inventario.
    // Todos los métodos de esta sección corren dentro de la transacción de
    // approve(), y toda cantidad que manejan está en UNIDAD BASE del producto.
    // ------------------------------------------------------------------

    /**
     * Re-chequeo AUTORITATIVO del cierre contable al aprobar (ver
     * StoreAdjustmentRequest::validateMovementDateNotClosed, que ya lo valida
     * al CREAR la solicitud): la aprobación es la que aplica el stock, y una
     * solicitud creada ANTES de que Contabilidad moviera la fecha de cierre
     * hacia adelante no debe poder colarse solo porque ya pasó la validación
     * de creación. Corre dentro de la misma transacción que el resto de
     * approve(): lanzar AdjustmentException aquí hace rollback (stock intacto)
     * y responde 422, igual que cualquier otro fallo de negocio de este método.
     */
    private function assertMovementDateNotClosed(Adjustment $adjustment): void
    {
        $movementDate = $adjustment->movement_date;

        if ($movementDate === null) {
            return;
        }

        $closedUntil = Carbon::parse((string) config('adjustments.closed_period_until'))->startOfDay();

        if ($movementDate->copy()->startOfDay()->lte($closedUntil)) {
            throw new AdjustmentException(sprintf(
                'La fecha de movimiento (%s) cae en un periodo ya cerrado y conciliado con Contabilidad ' .
                '(hasta el %s inclusive): no se puede aprobar. Solicite la excepción a Contabilidad antes de continuar.',
                $movementDate->format('d/m/Y'),
                $closedUntil->format('d/m/Y')
            ));
        }
    }

    /**
     * Cantidad (en unidad base) que la aprobación debe mover, siempre positiva.
     */
    private function resolveDeltaBase(Adjustment $adjustment): float
    {
        if ($adjustment->quantity_mode === 'delta') {
            return $this->toBase($adjustment, (float) $adjustment->quantity);
        }

        return $this->resolveAbsoluteDeltaBase($adjustment);
    }

    /**
     * Modo absoluto: la cantidad de la solicitud es el saldo QUE DEBE QUEDAR en
     * el lote, así que el movimiento a aplicar es la diferencia contra lo que
     * hay hoy. El lote se bloquea (lockForUpdate) para que ese "hoy" no cambie
     * entre el cálculo y la aplicación.
     */
    private function resolveAbsoluteDeltaBase(Adjustment $adjustment): float
    {
        if (!in_array($adjustment->type, ['entry', 'exit'], true)) {
            throw new AdjustmentException('El modo de cantidad absoluto solo aplica a entradas o salidas.');
        }

        $locationId = $this->stockLocationId($adjustment);

        $this->assertBatchExistsForAbsolute($adjustment, $locationId);

        $currentBase = $this->availableStockInBase($adjustment, $locationId, true);
        $targetBase = $this->toBase($adjustment, (float) $adjustment->quantity);

        return $this->absoluteDeltaForType($adjustment, $currentBase, $targetBase);
    }

    /**
     * El modo absoluto FIJA el saldo de un lote, así que el lote tiene que
     * existir: sobre un lote inexistente el saldo objetivo se convierte en un
     * delta igual a sí mismo (target − 0) y la aprobación CREA un lote con el
     * saldo completo, duplicando las existencias de la ubicación en vez de
     * corregirlas (medido: BENOMYL 16.044 g → 32.044 g al "fijar el saldo" en
     * 16.000 con un número de lote que no existía).
     *
     * store() ya rechaza el caso al solicitar, pero la comprobación autoritativa
     * es esta: corre dentro de la transacción de approve() y con la fila
     * bloqueada, así que también cubre las solicitudes creadas antes de este fix
     * y el lote que se haya borrado (consumido) entre la solicitud y la
     * aprobación.
     */
    private function assertBatchExistsForAbsolute(Adjustment $adjustment, string $locationId): void
    {
        $batchNumber = $adjustment->batch_number;

        if ($batchNumber === null || $batchNumber === '') {
            throw new AdjustmentException(
                'Fijar el saldo de un lote exige indicar el número de lote. Para mover una cantidad sin ' .
                'identificar el lote, registre el ajuste en modo delta.'
            );
        }

        if ($this->batchExists($adjustment->product_id, $adjustment->brand_id, $locationId, $batchNumber, true)) {
            return;
        }

        $productName = $adjustment->product?->name ?? $adjustment->product_id;
        $brandName = $adjustment->brand?->name;
        $locationName = Location::where('id', $locationId)->value('name') ?? 'la ubicación del ajuste';

        throw new AdjustmentException(sprintf(
            "El lote '%s' de '%s'%s no existe en %s: fijar el saldo solo puede ajustar un lote que ya existe " .
            '(si no, el saldo indicado se sumaría como stock nuevo y duplicaría las existencias). ' .
            'Para cargar un lote nuevo registre un ajuste de ENTRADA en modo delta con la cantidad a ingresar.',
            $batchNumber,
            $productName,
            $brandName ? " (marca {$brandName})" : '',
            $locationName
        ));
    }

    /**
     * Valida que el sentido del ajuste absoluto coincida con su tipo y devuelve
     * la magnitud a mover. Un absoluto que contradice el tipo casi siempre es un
     * error de captura (se eligió "entrada" para bajar el saldo, o al revés).
     */
    private function absoluteDeltaForType(Adjustment $adjustment, float $currentBase, float $targetBase): float
    {
        $delta = $targetBase - $currentBase;
        $baseUnit = $this->inventoryService->baseUnitOf($adjustment->product_id);
        $current = round($currentBase, 2) . ' ' . $baseUnit;
        $target = round($targetBase, 2) . ' ' . $baseUnit;

        if (abs($delta) <= self::QUANTITY_EPSILON) {
            throw new AdjustmentException(
                "El lote '{$adjustment->batch_number}' ya tiene la cantidad indicada ({$current}): no hay nada que ajustar."
            );
        }

        if ($adjustment->type === 'entry' && $delta < 0) {
            throw new AdjustmentException(
                "El valor absoluto indicado ({$target}) es menor al stock actual del lote " .
                "'{$adjustment->batch_number}' ({$current}); use un ajuste de salida."
            );
        }

        if ($adjustment->type === 'exit' && $delta > 0) {
            throw new AdjustmentException(
                "El valor absoluto indicado ({$target}) es mayor al stock actual del lote " .
                "'{$adjustment->batch_number}' ({$current}); use un ajuste de entrada."
            );
        }

        return abs($delta);
    }

    /**
     * Ubicación cuyo stock se ve afectado por una entrada o una salida.
     */
    private function stockLocationId(Adjustment $adjustment): string
    {
        return $adjustment->type === 'entry'
            ? $adjustment->destination_location_id
            : $adjustment->origin_location_id;
    }

    private function applyStock(Adjustment $adjustment, float $deltaBase, string $userId): void
    {
        if ($adjustment->type === 'entry') {
            $this->applyEntry(
                $adjustment,
                $adjustment->destination_location_id,
                $deltaBase,
                (float) ($adjustment->unit_price ?? 0),
                $userId,
                self::POSITIVE_TAG . ' ' . $this->reasonAndNotes($adjustment)
            );

            return;
        }

        if ($adjustment->type === 'exit') {
            $this->applyExit(
                $adjustment,
                $deltaBase,
                $userId,
                self::NEGATIVE_TAG . ' ' . $this->reasonAndNotes($adjustment)
            );

            return;
        }

        $this->applyTransfer($adjustment, $deltaBase, $userId);
    }

    /**
     * Traslado = salida en origen + entrada en destino por la MISMA cantidad.
     *
     * La entrada hereda EXACTAMENTE el costo de los lotes que FIFO consumió en
     * el origen (no un promedio de toda la ubicación) y el vencimiento más
     * próximo entre ellos: un traslado reubica mercancía, no la revalúa ni la
     * rejuvenece. Cualquier otro costo crearía o destruiría valor de inventario.
     */
    private function applyTransfer(Adjustment $adjustment, float $deltaBase, string $userId): void
    {
        $consumed = $this->applyExit(
            $adjustment,
            $deltaBase,
            $userId,
            $this->transferExitObservations($adjustment)
        );

        $this->applyEntry(
            $adjustment,
            $adjustment->destination_location_id,
            $deltaBase,
            $consumed['unit_price_base'],
            $userId,
            $this->transferEntryObservations($adjustment),
            $consumed['expiration_date']
        );
    }

    private function applyEntry(
        Adjustment $adjustment,
        string $locationId,
        float $deltaBase,
        float $unitPriceInBase,
        string $userId,
        string $observations,
        ?string $expirationDate = null
    ): void {
        $this->inventoryService->addStock(
            $adjustment->product_id,
            $adjustment->brand_id,
            $locationId,
            $deltaBase,
            $unitPriceInBase,
            $adjustment->batch_number ?: 'AJU-' . substr($adjustment->id, 0, 8),
            $expirationDate
        );

        $this->recordMovement($adjustment, 'entry', $locationId, $deltaBase, $unitPriceInBase, $userId, $observations);
    }

    /**
     * Salida en la ubicación de origen, consumiendo por FIFO.
     *
     * El movimiento se valora al costo REAL de los lotes consumidos, que es lo
     * que devuelve reduceInventoryFIFO: valorar la salida a un promedio de toda
     * la ubicación descuadraría el valor del inventario (una salida de 4 kg que
     * consume 3@8 + 1@10 vale 34, no 4 × el promedio 9.4 = 37.6).
     *
     * @return array{unit_price_base: float, expiration_date: string|null} Costo por
     *         unidad base y vencimiento más próximo de lo consumido (los usa el traslado).
     */
    private function applyExit(Adjustment $adjustment, float $deltaBase, string $userId, string $observations): array
    {
        $locationId = $adjustment->origin_location_id;

        // Re-validación del stock EN EL MOMENTO DE APROBAR, con las filas
        // bloqueadas: entre la solicitud y la aprobación pudo consumirse.
        $availableBase = $this->availableStockInBase($adjustment, $locationId, true);
        $this->assertSufficientStock($adjustment, $availableBase, $deltaBase);

        $consumption = $this->inventoryService->reduceInventoryFIFO(
            $adjustment->product_id,
            $adjustment->brand_id,
            $locationId,
            $deltaBase,
            // $deltaBase YA está en unidad base y reduceInventoryFIFO convierte
            // internamente con la unidad que reciba: pasarle $adjustment->unit
            // (p. ej. "Bulto") aplicaría el factor DOS veces. Con la unidad base
            // la conversión interna es la identidad.
            $this->inventoryService->baseUnitOf($adjustment->product_id),
            $adjustment->batch_number
        );

        // El guard va sobre la CANTIDAD consumida, no sobre el valor: un costo de
        // 0 es un costo conocido y legítimo (producto donado, carga inicial sin
        // valorar), y sustituirlo por el unit_price del ajuste crearía valor de
        // la nada igual que valorar con un promedio ajeno a lo consumido. El
        // fallback queda solo para cuando no se consumió nada (y en última
        // instancia 0: addStock rechaza negativos, no un costo desconocido).
        $unitPriceInBase = $consumption['consumed_base_qty'] > 0
            ? $consumption['unit_price_base']
            : (float) ($adjustment->unit_price ?? 0);

        $this->recordMovement($adjustment, 'exit', $locationId, $deltaBase, $unitPriceInBase, $userId, $observations);

        return [
            'unit_price_base' => $unitPriceInBase,
            'expiration_date' => $consumption['earliest_expiration_date'],
        ];
    }

    private function assertSufficientStock(Adjustment $adjustment, float $availableBase, float $requiredBase): void
    {
        if ($availableBase + self::QUANTITY_EPSILON >= $requiredBase) {
            return;
        }

        $productName = $adjustment->product?->name ?? $adjustment->product_id;
        $locationName = $adjustment->originLocation?->name ?? 'la ubicación de origen';
        $baseUnit = $this->inventoryService->baseUnitOf($adjustment->product_id);
        $batch = $adjustment->batch_number ? " (lote '{$adjustment->batch_number}')" : '';

        throw new AdjustmentException(
            "Stock insuficiente de '{$productName}' en {$locationName}{$batch}: se requieren " .
            round($requiredBase, 2) . " {$baseUnit} y solo hay " . round($availableBase, 2) .
            ' (faltan ' . round($requiredBase - $availableBase, 2) . ').'
        );
    }

    /**
     * Existencia disponible (en unidad base) del producto/marca del ajuste en
     * una ubicación. Si el ajuste apunta a un lote concreto, solo mira ese lote.
     *
     * Con $lock, bloquea las filas para que la re-validación, el cálculo del
     * delta absoluto y la reducción posterior vean exactamente el mismo estado.
     */
    private function availableStockInBase(Adjustment $adjustment, string $locationId, bool $lock = false): float
    {
        return $this->stockInBase(
            $adjustment->product_id,
            $adjustment->brand_id,
            $locationId,
            $adjustment->batch_number,
            $lock
        );
    }

    /**
     * Misma existencia disponible que availableStockInBase(), a partir de los
     * identificadores sueltos: la comparte store(), que valida la viabilidad de
     * la solicitud ANTES de que exista el Adjustment.
     */
    private function stockInBase(
        string $productId,
        string $brandId,
        string $locationId,
        ?string $batchNumber,
        bool $lock = false
    ): float {
        $query = Inventory::where('product_id', $productId)
            ->where('brand_id', $brandId)
            ->where('location_id', $locationId)
            ->where('quantity', '>', 0);

        if ($batchNumber !== null) {
            $query->where('batch_number', $batchNumber);
        }

        if ($lock) {
            $query->lockForUpdate();
        }

        $totalBase = 0.0;

        foreach ($query->get() as $batch) {
            $totalBase += $this->inventoryService->toBaseUnit(
                (float) $batch->quantity,
                $batch->unit,
                $productId
            );
        }

        return $totalBase;
    }

    /**
     * Movimiento de kardex ligado al ajuste.
     *
     * `type` solo puede ser 'entry' o 'exit': el enum de inventory_movements es
     * ('entry','exit','transfer','application') y NO incluye 'adjustment'.
     *
     * La cantidad se registra SIEMPRE EN LA UNIDAD BASE del producto, no en la
     * unidad de captura, y esto NO es un detalle cosmético: los informes que el
     * cliente concilia contra contabilidad suman `inventory_movements.quantity`
     * EN CRUDO, sin convertir (InventoryController::monthlyReport y
     * farmMonthlyReport). Un movimiento guardado en "Bulto" se sumaría como si
     * fueran kilogramos y descuadraría el cierre del mes — medido: un ajuste
     * capturado en presentación dejó el informe con 15.694 kg contra 15.400
     * reales (−294 kg) y, peor, con "Variación 0", porque el mismo error entra
     * en los dos lados de la resta y el descuadre queda invisible.
     *
     * Lo que el usuario capturó (p. ej. "2 Bulto") no se pierde: queda en las
     * observaciones vía captureNote(), que es donde sirve para auditar sin
     * contaminar ninguna suma.
     *
     * El valor no depende de la presentación: total = cantidad_base *
     * costo_por_unidad_base, y con la cantidad ya en unidad base el precio
     * unitario del movimiento ES el costo por unidad base, conservando el
     * invariante del proyecto (total_price = quantity * unit_price).
     */
    private function recordMovement(
        Adjustment $adjustment,
        string $type,
        string $locationId,
        float $deltaBase,
        float $unitPriceInBase,
        string $userId,
        string $observations
    ): void {
        InventoryMovement::create([
            'type' => $type,
            'product_id' => $adjustment->product_id,
            'brand_id' => $adjustment->brand_id,
            'location_id' => $locationId,
            'quantity' => $deltaBase,
            'unit' => $this->inventoryService->baseUnitOf($adjustment->product_id),
            'movement_date' => $adjustment->movement_date?->toDateString(),
            'unit_price' => $unitPriceInBase,
            'total_price' => $deltaBase * $unitPriceInBase,
            'responsible_user' => $userId,
            'related_document_id' => $adjustment->id,
            'related_document_type' => self::MOVEMENT_DOCUMENT_TYPE,
            'observations' => $observations . $this->captureNote($adjustment),
        ]);
    }

    /**
     * Trazabilidad de la unidad de CAPTURA en las observaciones del movimiento,
     * ya que la cantidad se guarda convertida a unidad base (ver
     * recordMovement). Solo se añade cuando la unidad de captura no es la base,
     * que es el único caso en que el dato aporta algo.
     *
     * El texto se elige a propósito sin las palabras que clasifican un
     * movimiento en el informe mensual ('aumento', 'disminuc', 'ajuste
     * positivo/negativo'): añadirlas aquí metería la salida de un traslado en la
     * columna "Disminuciones", donde ya está contada como envío.
     */
    private function captureNote(Adjustment $adjustment): string
    {
        $baseUnit = $this->inventoryService->baseUnitOf($adjustment->product_id);
        $capturedUnit = (string) $adjustment->unit;

        if ($capturedUnit === '' || strcasecmp($capturedUnit, $baseUnit) === 0) {
            return '';
        }

        $quantity = rtrim(rtrim(number_format((float) $adjustment->quantity, 2, '.', ''), '0'), '.');

        return $adjustment->quantity_mode === 'absolute'
            ? " [saldo fijado en {$quantity} {$capturedUnit}]"
            : " [capturado como {$quantity} {$capturedUnit}]";
    }

    /**
     * Salida de un traslado: deliberadamente SIN las palabras
     * 'disminución'/'ajuste negativo'.
     *
     * Un traslado no cambia el inventario total de la empresa (solo lo reubica),
     * y su salida ya está contabilizada como TRASLADO SALIENTE en el informe
     * mensual del origen: como envío en la matriz de fincas cuando el destino es
     * una finca, o en la columna de traslados salientes cuando no lo es (ver
     * InventoryController::monthlyReport, pasos 3 y 3b). Las columnas
     * Aumentos/Disminuciones existen para ajustes NETOS (mermas, sobrantes).
     *
     * Que hoy el informe clasifique estos movimientos por `adjustments.type` y no
     * por texto NO vuelve inocuo añadir aquí la etiqueta negativa: el histórico
     * sin documento relacionado se sigue clasificando por texto, y cualquier
     * consumidor que lo haga (informes, exportaciones, integraciones futuras)
     * restaría esta salida dos veces y dejaría la "Variación" del origen —la
     * columna que el cliente concilia contra contabilidad— distinta de 0.
     */
    private function transferExitObservations(Adjustment $adjustment): string
    {
        $destination = $adjustment->destinationLocation?->name ?? 'la ubicación de destino';

        return "[TRASLADO POR AJUSTE] Salida hacia {$destination} - " . $this->reasonAndNotes($adjustment);
    }

    /**
     * Entrada de un traslado: SÍ lleva 'aumento'.
     *
     * Para la ubicación de destino esta entrada sí es un ingreso de existencias
     * y ningún otro concepto del informe mensual la explica (no es compra: el
     * filtro de compras exige related_document_type Reception/Purchase o nulo, y
     * el nuestro es Adjustment), así que cuenta en su columna "Aumentos" —hoy por
     * `adjustments.type`, que incluye 'transfer' entre los tipos que aumentan, y
     * por el texto en cualquier consumidor que clasifique al modo del histórico.
     * Sin ese conteo la entrada quedaría como "Variación" (un descuadre aparente)
     * en el informe del destino. No duplica nada: la ubicación ORIGEN usa esta
     * misma fila para su matriz de envíos, pero en otro informe.
     */
    private function transferEntryObservations(Adjustment $adjustment): string
    {
        $origin = $adjustment->originLocation?->name ?? 'la ubicación de origen';

        return "[TRASLADO POR AJUSTE] Entrada (aumento) desde {$origin} - " . $this->reasonAndNotes($adjustment);
    }

    private function reasonAndNotes(Adjustment $adjustment): string
    {
        $reason = $adjustment->reason?->name ?? 'Ajuste de inventario';

        return $adjustment->notes ? "{$reason} - {$adjustment->notes}" : $reason;
    }

    private function toBase(Adjustment $adjustment, float $quantity): float
    {
        return $this->inventoryService->toBaseUnit($quantity, $adjustment->unit, $adjustment->product_id);
    }
}
