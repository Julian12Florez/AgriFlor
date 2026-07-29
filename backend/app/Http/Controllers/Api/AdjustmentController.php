<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdjustmentRequest;
use App\Http\Resources\AdjustmentResource;
use App\Models\Adjustment;
use App\Models\AdjustmentReason;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

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
     * Para roles restringidos por ubicación (supervisor, farm), exige que TODAS
     * las ubicaciones enviadas en el payload (origen y/o destino, según el tipo)
     * estén entre las administradas por el usuario. Sin esta validación, un
     * usuario restringido podía crear un ajuste sobre una ubicación ajena y la
     * solicitud quedaba "huérfana": ni él (fuera de su alcance normal) ni el
     * responsable real de esa ubicación la verían fácilmente en su flujo.
     * Devuelve el mensaje de error si debe denegarse, o null si puede continuar.
     */
    private function deniedLocationMessage(array $data, ?User $user): ?string
    {
        if (!$user || $user->canViewAllLocations()) {
            return null;
        }

        $managedIds = $user->managedLocationIds();

        foreach (['origin_location_id', 'destination_location_id'] as $field) {
            $locationId = $data[$field] ?? null;

            if ($locationId !== null && !in_array($locationId, $managedIds, true)) {
                return 'Solo puedes registrar ajustes en ubicaciones de las que eres responsable.';
            }
        }

        return null;
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
}
