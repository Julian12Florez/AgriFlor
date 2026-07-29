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

    public function show(string $id): JsonResponse
    {
        $adjustment = Adjustment::with(self::RELATIONS)->findOrFail($id);

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
        $adjustment = $this->createAdjustment($request->validated(), $request->user()->id);
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
     * Aplica el aislamiento por ubicación: los roles restringidos (supervisor, farm)
     * solo ven ajustes donde alguna de sus ubicaciones administradas participa como
     * origen o destino. Los demás roles (canViewAllLocations() === true) ven todo.
     */
    private function applyLocationScope(Builder $query, ?User $user): void
    {
        if (!$user || $user->canViewAllLocations()) {
            return;
        }

        $managedIds = $user->managedLocationIds();

        $query->where(function (Builder $q) use ($managedIds) {
            $q->whereIn('origin_location_id', $managedIds)
                ->orWhereIn('destination_location_id', $managedIds);
        });
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

    private function isDuplicateAdjustmentNumber(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062
            && str_contains($e->getMessage(), 'adjustment_number');
    }
}
