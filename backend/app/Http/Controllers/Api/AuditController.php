<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Models\Audit;

/**
 * Consulta del registro de auditoría (quién hizo qué en el core).
 * Solo lectura. Acceso EXCLUSIVO del rol 'auditor' (ni siquiera admin);
 * el control real está en routes/api.php (middleware role:auditor).
 *
 * Objetivo: que la auditoría sea HUMANAMENTE ENTENDIBLE. En lugar de mostrar
 * columnas e IDs crudos (output_type_id: a1ee..., origin_location_id: ...),
 * resuelve los IDs a nombres, traduce los campos a español y arma un resumen
 * legible por documento (qué producto, cuánto, de qué ubicación a cuál).
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

    // Campo (columna) → etiqueta en español
    private const FIELD_LABELS = [
        'order_number' => 'N° de orden',
        'reception_number' => 'N° de recepción',
        'output_number' => 'N° de salida',
        'supplier_id' => 'Proveedor',
        'origin_location_id' => 'Origen',
        'destination_location_id' => 'Destino',
        'location_id' => 'Ubicación',
        'purchase_date' => 'Fecha de compra',
        'output_date' => 'Fecha de salida',
        'shipment_date' => 'Fecha de envío',
        'expected_delivery' => 'Entrega esperada',
        'reception_date' => 'Fecha de recepción',
        'expiration_date' => 'Vencimiento',
        'output_type_id' => 'Tipo de salida',
        'product_id' => 'Producto',
        'brand_id' => 'Marca',
        'packaging_unit_id' => 'Empaque',
        'base_unit' => 'Unidad',
        'unit' => 'Unidad',
        'quantity' => 'Cantidad',
        'quantity_received' => 'Cantidad recibida',
        'quantity_delivered' => 'Cantidad entregada',
        'quantity_requested' => 'Cantidad solicitada',
        'quantity_expected' => 'Cantidad esperada',
        'quantity_pending' => 'Cantidad pendiente',
        'unit_price' => 'Precio unitario',
        'subtotal' => 'Subtotal',
        'tax' => 'Impuesto',
        'total' => 'Total',
        'total_cost' => 'Costo total',
        'total_expected' => 'Total esperado',
        'total_received' => 'Total recibido',
        'completion_percentage' => '% completado',
        'status' => 'Estado',
        'condition' => 'Condición',
        'name' => 'Nombre',
        'nit' => 'NIT',
        'observations' => 'Observaciones',
        'responsible_user' => 'Responsable',
        'responsible_user_id' => 'Responsable',
        'received_by' => 'Recibido por',
        'created_by' => 'Creado por',
        'user_id' => 'Usuario',
        'municipality' => 'Municipio',
        'address' => 'Dirección',
        'city' => 'Ciudad',
        'phone' => 'Teléfono',
        'email' => 'Correo',
        'type' => 'Tipo',
        'code' => 'Código',
        'active_ingredient' => 'Principio activo',
        'description' => 'Descripción',
        'payment_terms' => 'Términos de pago',
        'role' => 'Rol',
    ];

    // Campo → "bucket" de modelo para resolver el ID a un nombre legible
    private const FK_RESOLVERS = [
        'supplier_id' => 'supplier',
        'origin_location_id' => 'location',
        'destination_location_id' => 'location',
        'location_id' => 'location',
        'output_type_id' => 'output_type',
        'product_id' => 'product',
        'brand_id' => 'brand',
        'packaging_unit_id' => 'packaging_unit',
        'responsible_user' => 'user',
        'responsible_user_id' => 'user',
        'received_by' => 'user',
        'created_by' => 'user',
        'user_id' => 'user',
    ];

    // Columnas de ruido que no aportan a un humano
    private const HIDDEN = [
        'id', 'created_at', 'updated_at', 'deleted_at', 'received_at',
        'quantity_in_base_units', 'iva_percentage', 'tax_amount',
        'source_id', 'technical_order_id', 'password', 'role_id',
    ];

    private const MONEY = ['total', 'subtotal', 'tax', 'total_cost', 'total_expected', 'total_received', 'unit_price'];

    private const STATUS = [
        'pending' => 'Pendiente', 'completed' => 'Completada', 'in_transit' => 'En tránsito',
        'partial' => 'Parcial', 'approved' => 'Aprobada', 'cancelled' => 'Cancelada',
        'canceled' => 'Cancelada', 'rejected' => 'Rechazada', 'active' => 'Activo',
        'inactive' => 'Inactivo', 'draft' => 'Borrador', 'received' => 'Recibida',
        'in_progress' => 'En proceso', 'finished' => 'Finalizada', 'closed' => 'Cerrada',
    ];
    private const CONDITION = ['good' => 'Buen estado', 'damaged' => 'Dañado', 'expired' => 'Vencido'];
    private const LOC_TYPE = ['warehouse' => 'Bodega', 'farm' => 'Finca'];
    private const SOURCE_TYPE = ['purchase' => 'Compra', 'output' => 'Salida'];

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 30);

        $query = Audit::query()->with('user')->orderByDesc('created_at');

        if ($request->filled('model')) {
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
        $items = $audits->items();

        // --- Resolución en lote (evita N+1) ---
        $lk = $this->loadLookups($items);
        $docs = $this->loadDocuments($items);

        $data = collect($items)->map(function (Audit $a) use ($lk, $docs) {
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
                // NUEVO: descripción humana + cambios resueltos
                'summary' => $this->buildSummary($a, $docs, $lk),
                'changes' => $this->buildChanges($a, $lk),
                // Se mantienen los crudos por compatibilidad
                'oldValues' => $a->old_values,
                'newValues' => $a->new_values,
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
     * Carga en lote los nombres de las entidades referenciadas por ID.
     */
    private function loadLookups(array $items): array
    {
        $bucket = ['supplier' => [], 'location' => [], 'output_type' => [], 'product' => [], 'brand' => [], 'packaging_unit' => [], 'user' => []];
        foreach ($items as $a) {
            foreach ([$a->old_values ?? [], $a->new_values ?? []] as $vals) {
                if (!is_array($vals)) continue;
                foreach ($vals as $field => $val) {
                    $r = self::FK_RESOLVERS[$field] ?? null;
                    if ($r && is_string($val) && $val !== '') {
                        $bucket[$r][] = $val;
                    }
                }
            }
        }
        $pluck = fn($model, $ids) => empty($ids) ? collect() : $model::whereIn('id', array_values(array_unique($ids)))->pluck('name', 'id');

        return [
            'supplier' => $pluck(\App\Models\Supplier::class, $bucket['supplier']),
            'location' => $pluck(\App\Models\Location::class, $bucket['location']),
            'output_type' => $pluck(\App\Models\OutputType::class, $bucket['output_type']),
            'product' => $pluck(\App\Models\Product::class, $bucket['product']),
            'brand' => $pluck(\App\Models\Brand::class, $bucket['brand']),
            'packaging_unit' => $pluck(\App\Models\PackagingUnit::class, $bucket['packaging_unit']),
            'user' => $pluck(\App\Models\User::class, $bucket['user']),
        ];
    }

    /**
     * Carga en lote los documentos (con sus items) para construir el resumen.
     */
    private function loadDocuments(array $items): array
    {
        $ids = ['purchase' => [], 'output' => [], 'reception' => []];
        foreach ($items as $a) {
            if (isset($ids[$a->auditable_type])) {
                $ids[$a->auditable_type][] = $a->auditable_id;
            }
        }
        return [
            'purchase' => empty($ids['purchase']) ? collect() : \App\Models\Purchase::with(['purchaseItems.product', 'purchaseItems.brand', 'supplier', 'destinationLocation'])->whereIn('id', array_unique($ids['purchase']))->get()->keyBy('id'),
            'output' => empty($ids['output']) ? collect() : \App\Models\ProductOutput::with(['outputProducts.product', 'originLocation', 'destinationLocation', 'outputType'])->whereIn('id', array_unique($ids['output']))->get()->keyBy('id'),
            'reception' => empty($ids['reception']) ? collect() : \App\Models\Reception::with(['receptionItems.product', 'originLocation', 'destinationLocation'])->whereIn('id', array_unique($ids['reception']))->get()->keyBy('id'),
        ];
    }

    /**
     * Resumen humano de la acción (qué producto, cuánto, de dónde a dónde).
     */
    private function buildSummary(Audit $a, array $docs, array $lk): string
    {
        $type = $a->auditable_type;

        if ($type === 'purchase' && ($p = $docs['purchase']->get($a->auditable_id))) {
            $its = $p->purchaseItems->map(fn($it) => $this->num($it->quantity) . ' ' . ($it->product->name ?? 'producto') . ($it->brand ? ' (' . $it->brand->name . ')' : ''))->implode(', ');
            $parts = array_filter([
                $its ?: null,
                $p->supplier ? 'Proveedor: ' . $p->supplier->name : null,
                $p->destinationLocation ? 'Destino: ' . $p->destinationLocation->name : null,
                $p->total ? 'Total: ' . $this->money($p->total) : null,
            ]);
            return 'Compra ' . $p->order_number . ($parts ? ' — ' . implode(' · ', $parts) : '');
        }

        if ($type === 'output' && ($o = $docs['output']->get($a->auditable_id))) {
            $its = $o->outputProducts->map(fn($it) => $this->num($it->quantity_delivered ?: $it->quantity_requested) . ' ' . ($it->unit ?: '') . ' ' . ($it->product->name ?? 'producto'))->implode(', ');
            $route = ($o->originLocation->name ?? '?') . ' → ' . ($o->destinationLocation->name ?? '?');
            $t = $o->outputType->name ?? 'Salida';
            return trim('Salida (' . $t . '): ' . ($its ? $its . ' · ' : '') . $route);
        }

        if ($type === 'reception' && ($r = $docs['reception']->get($a->auditable_id))) {
            $its = $r->receptionItems->map(fn($it) => $this->num($it->quantity_received ?: $it->quantity_expected) . ' ' . ($it->unit ?: '') . ' ' . ($it->product->name ?? 'producto'))->implode(', ');
            return trim('Recepción ' . $r->reception_number . ': ' . ($its ?: 'sin items') . ' en ' . ($r->destinationLocation->name ?? '?'));
        }

        // Datos maestros (o documento ya eliminado): usar el nombre disponible
        $vals = (!empty($a->new_values) ? $a->new_values : $a->old_values) ?? [];
        $name = $vals['name'] ?? $vals['order_number'] ?? $vals['reception_number'] ?? $vals['output_number'] ?? null;
        $label = $this->modelNames[$type] ?? $type;
        return $name ? "$label: $name" : $label;
    }

    /**
     * Lista de cambios campo a campo, ya resueltos y traducidos.
     */
    private function buildChanges(Audit $a, array $lk): array
    {
        if ($a->event === 'deleted') {
            return [];
        }
        $old = is_array($a->old_values) ? $a->old_values : [];
        $new = is_array($a->new_values) ? $a->new_values : [];
        $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
        $out = [];
        foreach ($keys as $k) {
            if (in_array($k, self::HIDDEN, true)) {
                continue;
            }
            $from = $this->present($k, $old[$k] ?? null, $lk);
            $to = $this->present($k, $new[$k] ?? null, $lk);
            if ($a->event === 'created') {
                if ($to === null || $to === '') continue;
                $out[] = ['label' => self::FIELD_LABELS[$k] ?? $k, 'from' => null, 'to' => $to];
            } else {
                if ($from === $to) continue;
                $out[] = ['label' => self::FIELD_LABELS[$k] ?? $k, 'from' => $from, 'to' => $to];
            }
        }
        return $out;
    }

    /**
     * Presenta un valor crudo de forma legible (resuelve ID, traduce, formatea).
     */
    private function present(string $field, $value, array $lk): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        // ID → nombre
        $r = self::FK_RESOLVERS[$field] ?? null;
        if ($r && is_string($value) && isset($lk[$r][$value])) {
            return $lk[$r][$value];
        }
        // Traducciones de valores conocidos
        if ($field === 'status') return self::STATUS[$value] ?? $value;
        if ($field === 'condition') return self::CONDITION[$value] ?? $value;
        if ($field === 'type') return self::LOC_TYPE[$value] ?? $value;
        if ($field === 'source_type') return self::SOURCE_TYPE[$value] ?? $value;
        // Fechas
        if (str_contains($field, 'date') || $field === 'expected_delivery') {
            return $this->fmtDate($value);
        }
        // Dinero
        if (in_array($field, self::MONEY, true) && is_numeric($value)) {
            return $this->money($value);
        }
        // Porcentaje
        if ($field === 'completion_percentage' && is_numeric($value)) {
            return $this->num($value) . '%';
        }
        // Cantidades numéricas: quitar ceros sobrantes (5.00 → 5)
        if (is_numeric($value) && str_starts_with($field, 'quantity')) {
            return $this->num($value);
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        return (string) $value;
    }

    private function fmtDate($value): string
    {
        try {
            return Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    private function money($value): string
    {
        return '$' . number_format((float) $value, 0, ',', '.');
    }

    /** Formatea cantidad quitando ceros decimales sobrantes (5.00 → 5, 2.50 → 2.5). */
    private function num($value): string
    {
        $f = (float) $value;
        return rtrim(rtrim(number_format($f, 2, '.', ''), '0'), '.');
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
