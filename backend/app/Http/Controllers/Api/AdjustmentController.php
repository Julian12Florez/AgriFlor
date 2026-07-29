<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdjustmentRequest;
use App\Http\Resources\AdjustmentResource;
use App\Models\Adjustment;
use App\Models\AdjustmentReason;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
     * Marcas de las observaciones que los informes usan para clasificar un
     * movimiento como aumento o disminución de existencias
     * (InventoryController::monthlyReport busca 'aumento'/'ajuste positivo' y
     * 'disminuc'/'ajuste negativo' con LIKE). Cambiar estos textos rompe las
     * columnas "Aumentos"/"Disminuciones" del informe mensual.
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
     */
    public function reasons(Request $request): JsonResponse
    {
        $query = AdjustmentReason::query()->active();

        if ($request->filled('direction')) {
            $query->where('direction', $request->input('direction'));
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
            if (Adjustment::whereKey($adjustment->id)->lockForUpdate()->value('status') !== 'pending') {
                DB::rollBack();

                return $this->alreadyProcessedResponse();
            }

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
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo aplicar el ajuste: ' . $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ajuste aprobado y aplicado al inventario.',
            'data' => new AdjustmentResource($adjustment->fresh(self::RELATIONS)),
        ]);
    }

    private function alreadyProcessedResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'La solicitud ya fue procesada.',
        ], 422);
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
            throw new \RuntimeException('El modo de cantidad absoluto solo aplica a entradas o salidas.');
        }

        [$currentBase] = $this->stockSnapshot($adjustment, $this->stockLocationId($adjustment), true);
        $targetBase = $this->toBase($adjustment, (float) $adjustment->quantity);

        return $this->absoluteDeltaForType($adjustment, $currentBase, $targetBase);
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
            throw new \RuntimeException(
                "El lote '{$adjustment->batch_number}' ya tiene la cantidad indicada ({$current}): no hay nada que ajustar."
            );
        }

        if ($adjustment->type === 'entry' && $delta < 0) {
            throw new \RuntimeException(
                "El valor absoluto indicado ({$target}) es menor al stock actual del lote " .
                "'{$adjustment->batch_number}' ({$current}); use un ajuste de salida."
            );
        }

        if ($adjustment->type === 'exit' && $delta > 0) {
            throw new \RuntimeException(
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
     * La entrada se valora al costo del stock que salió del origen (promedio
     * ponderado calculado ANTES de reducir, que es lo que devuelve applyExit):
     * un traslado reubica mercancía, no la revalúa. Si el origen no tuviera
     * costo registrado se cae al unit_price del ajuste, y en última instancia a
     * 0 (addStock rechaza precios negativos, no un costo desconocido).
     */
    private function applyTransfer(Adjustment $adjustment, float $deltaBase, string $userId): void
    {
        $costInBase = $this->applyExit(
            $adjustment,
            $deltaBase,
            $userId,
            $this->transferExitObservations($adjustment)
        );

        $this->applyEntry(
            $adjustment,
            $adjustment->destination_location_id,
            $deltaBase,
            $costInBase,
            $userId,
            $this->transferEntryObservations($adjustment)
        );
    }

    private function applyEntry(
        Adjustment $adjustment,
        string $locationId,
        float $deltaBase,
        float $unitPriceInBase,
        string $userId,
        string $observations
    ): void {
        $this->inventoryService->addStock(
            $adjustment->product_id,
            $adjustment->brand_id,
            $locationId,
            $deltaBase,
            $unitPriceInBase,
            $adjustment->batch_number ?: 'AJU-' . substr($adjustment->id, 0, 8),
            null
        );

        $this->recordMovement($adjustment, 'entry', $locationId, $deltaBase, $unitPriceInBase, $userId, $observations);
    }

    /**
     * Salida en la ubicación de origen, consumiendo por FIFO.
     *
     * @return float Costo por unidad base del stock consumido (lo usa el traslado
     *               para valorar la entrada en destino).
     */
    private function applyExit(Adjustment $adjustment, float $deltaBase, string $userId, string $observations): float
    {
        $locationId = $adjustment->origin_location_id;

        // Re-validación del stock EN EL MOMENTO DE APROBAR, con las filas
        // bloqueadas: entre la solicitud y la aprobación pudo consumirse.
        [$availableBase, $unitPriceInBase] = $this->stockSnapshot($adjustment, $locationId, true);
        $this->assertSufficientStock($adjustment, $availableBase, $deltaBase);

        $this->inventoryService->reduceInventoryFIFO(
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

        $this->recordMovement($adjustment, 'exit', $locationId, $deltaBase, $unitPriceInBase, $userId, $observations);

        return $unitPriceInBase;
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

        throw new \RuntimeException(
            "Stock insuficiente de '{$productName}' en {$locationName}{$batch}: se requieren " .
            round($requiredBase, 2) . " {$baseUnit} y solo hay " . round($availableBase, 2) .
            ' (faltan ' . round($requiredBase - $availableBase, 2) . ').'
        );
    }

    /**
     * Existencia disponible y costo promedio ponderado del producto/marca del
     * ajuste en una ubicación, ambos en unidad base (inventory.unit_price se
     * almacena por unidad base desde la migración que corrigió los precios de
     * presentación). Si el ajuste apunta a un lote concreto, solo mira ese lote.
     *
     * Con $lock, bloquea las filas para que la re-validación, el cálculo del
     * delta absoluto y la reducción posterior vean exactamente el mismo estado.
     *
     * @return array{0: float, 1: float} [disponible en base, costo por unidad base]
     */
    private function stockSnapshot(Adjustment $adjustment, string $locationId, bool $lock = false): array
    {
        $query = Inventory::where('product_id', $adjustment->product_id)
            ->where('brand_id', $adjustment->brand_id)
            ->where('location_id', $locationId)
            ->where('quantity', '>', 0);

        if ($adjustment->batch_number !== null) {
            $query->where('batch_number', $adjustment->batch_number);
        }

        if ($lock) {
            $query->lockForUpdate();
        }

        $totalBase = 0.0;
        $totalValue = 0.0;

        foreach ($query->get() as $batch) {
            $quantityInBase = $this->inventoryService->toBaseUnit(
                (float) $batch->quantity,
                $batch->unit,
                $adjustment->product_id
            );

            $totalBase += $quantityInBase;
            $totalValue += $quantityInBase * (float) $batch->unit_price;
        }

        $averagePrice = $totalBase > 0
            ? $totalValue / $totalBase
            : (float) ($adjustment->unit_price ?? 0);

        return [$totalBase, $averagePrice];
    }

    /**
     * Movimiento de kardex ligado al ajuste.
     *
     * `type` solo puede ser 'entry' o 'exit': el enum de inventory_movements es
     * ('entry','exit','transfer','application') y NO incluye 'adjustment'.
     *
     * La cantidad se registra en la unidad que eligió el solicitante (kg,
     * Bulto...), pero el VALOR del movimiento no depende de la presentación:
     * total = cantidad_base * costo_por_unidad_base. El precio unitario se
     * deriva de ese total para conservar el invariante del proyecto
     * (total_price = quantity * unit_price) también cuando la unidad es una
     * presentación.
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
        $quantity = $this->inventoryService->fromBaseUnit($deltaBase, $adjustment->unit, $adjustment->product_id);
        $totalPrice = $deltaBase * $unitPriceInBase;

        InventoryMovement::create([
            'type' => $type,
            'product_id' => $adjustment->product_id,
            'brand_id' => $adjustment->brand_id,
            'location_id' => $locationId,
            'quantity' => $quantity,
            'unit' => $adjustment->unit,
            'movement_date' => $adjustment->movement_date?->toDateString(),
            'unit_price' => $quantity > 0 ? $totalPrice / $quantity : $unitPriceInBase,
            'total_price' => $totalPrice,
            'responsible_user' => $userId,
            'related_document_id' => $adjustment->id,
            'related_document_type' => self::MOVEMENT_DOCUMENT_TYPE,
            'observations' => $observations,
        ]);
    }

    /**
     * Salida de un traslado: deliberadamente SIN las palabras
     * 'disminución'/'ajuste negativo'.
     *
     * InventoryController::monthlyReport ya contabiliza esta salida en la matriz
     * de envíos a fincas (empareja las entradas del destino con los exit del
     * origen por related_document_id, que ambas patas comparten). Marcarla
     * además como disminución la restaría DOS veces del movimiento total y
     * descuadraría el informe justo en el caso dominante (bodega → finca).
     * Un traslado, además, no cambia el inventario total de la empresa: solo lo
     * reubica; las columnas Aumentos/Disminuciones existen para ajustes netos
     * (mermas, sobrantes), que son los que se concilian contra contabilidad.
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
     * filtro de compras exige related_document_type Reception/Purchase o nulo,
     * y el nuestro es Adjustment). Sin la marca quedaría como "Variación" —un
     * descuadre aparente— en el informe del destino. Contabilizarla aquí no
     * duplica nada: la columna de envíos de la ubicación ORIGEN usa esta misma
     * fila, pero en otro informe y con otro propósito.
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
