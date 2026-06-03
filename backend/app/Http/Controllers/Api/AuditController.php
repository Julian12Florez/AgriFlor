<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OwenIt\Auditing\Models\Audit;

/**
 * Consulta del registro de auditoría (quién hizo qué en el core).
 * Solo lectura. Solo admin.
 */
class AuditController extends Controller
{
    // Mapa de tipos morph → nombre legible en español
    private array $modelNames = [
        'product' => 'Producto',
        'purchase' => 'Compra',
        'output' => 'Salida',
        'reception' => 'Recepción',
        'brand' => 'Marca',
        'location' => 'Ubicación',
        'supplier' => 'Proveedor',
        'application' => 'Aplicación',
        'user' => 'Usuario',
    ];

    private array $eventNames = [
        'created' => 'Creó',
        'updated' => 'Editó',
        'deleted' => 'Eliminó',
        'restored' => 'Restauró',
    ];

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 30);

        $query = Audit::query()->with('user')->orderByDesc('created_at');

        // Filtros opcionales
        if ($request->filled('model')) {
            // Acepta el alias morph (p.ej. 'purchase') o el FQCN
            $query->where('auditable_type', $request->get('model'));
        }
        if ($request->filled('event')) {
            $query->where('event', $request->get('event'));
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->get('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->get('to'));
        }

        $audits = $query->paginate($perPage);

        $data = collect($audits->items())->map(function (Audit $a) {
            $user = $a->user;
            return [
                'id' => $a->id,
                'event' => $a->event,
                'eventLabel' => $this->eventNames[$a->event] ?? $a->event,
                'model' => $a->auditable_type,
                'modelLabel' => $this->modelNames[$a->auditable_type] ?? $a->auditable_type,
                'auditableId' => $a->auditable_id,
                'userId' => $a->user_id,
                'userName' => $user->name ?? 'Sistema',
                'userEmail' => $user->email ?? null,
                'ipAddress' => $a->ip_address,
                'oldValues' => $a->old_values,
                'newValues' => $a->new_values,
                'url' => $a->url,
                'createdAt' => $a->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'total' => $audits->total(),
                'per_page' => $audits->perPage(),
                'current_page' => $audits->currentPage(),
                'last_page' => $audits->lastPage(),
            ],
        ]);
    }

    /**
     * Catálogos para los filtros (modelos y eventos disponibles).
     */
    public function filters(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'models' => collect($this->modelNames)->map(fn($label, $key) => ['value' => $key, 'label' => $label])->values(),
                'events' => collect($this->eventNames)->map(fn($label, $key) => ['value' => $key, 'label' => $label])->values(),
            ],
        ]);
    }
}
