<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Adjustment;
use App\Models\Role;
use App\Support\Auditoria\FotoDeAuditoria;
use App\Support\Auditoria\Vocabulario;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OwenIt\Auditing\Models\Audit;

/**
 * Consulta del registro de auditoría (quién hizo qué en el core).
 * Solo lectura. Acceso EXCLUSIVO del rol 'auditor' (ni siquiera admin);
 * el control real está en routes/api.php (middleware role:auditor).
 *
 * Objetivo: que la auditoría sea HUMANAMENTE ENTENDIBLE. Cada registro dice
 * quién, cuándo, qué (documento, productos, cantidades) y cómo, en palabras:
 *
 *  - Los registros nuevos traen una FOTO del documento tal como estaba en el
 *    momento de la acción (`audits.snapshot`, ver App\Support\Auditoria\
 *    FotoDeAuditoria): el resumen sale de ahí, aunque el documento haya
 *    cambiado o se haya eliminado después.
 *  - Los registros viejos (sin foto) se siguen mostrando como siempre: el
 *    resumen se arma con el documento actual.
 *  - En los dos casos los cambios se traducen campo a campo y ningún ID se ve:
 *    se resuelve a un nombre o se oculta (App\Support\Auditoria\Vocabulario).
 *
 * Todo nombre se carga en lote para la página completa (sin N+1).
 */
class AuditController extends Controller
{
    // Mapa de tipos morph → nombre legible en español (sale en el filtro "Entidad")
    private array $modelNames = Vocabulario::ENTIDADES;

    private array $eventNames = [
        'created' => 'Creó',
        'updated' => 'Editó',
        'deleted' => 'Eliminó',
        'restored' => 'Restauró',
    ];

    /**
     * Textos libres a los que se les suele AGREGAR al final (el motivo de una
     * eliminación va a las observaciones de la compra). En un nombre, en
     * cambio, "X → X (editada)" se entiende mejor completo.
     */
    private const TEXTOS_LIBRES = [
        'observations', 'notes', 'description', 'rejection_reason', 'cancellation_reason',
        'application_instructions', 'safety_notes', 'ad_hoc_motive',
    ];

    /** Columnas que dicen cómo se llama un registro, en orden de preferencia. */
    private const CAMPOS_DE_NOMBRE = [
        'display_name', 'name', 'full_name', 'title', 'order_number', 'output_number',
        'reception_number', 'adjustment_number', 'code',
    ];

    /** Documentos cuyo resumen viejo (sin foto) se arma con el documento actual. */
    private const DOCUMENTOS = ['purchase', 'output', 'reception', 'adjustment'];

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 30);

        $query = Audit::query()->with('user')->orderByDesc('created_at')->orderByDesc('id');

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
        $fotos = $this->leerFotos($items);
        $lk = $this->loadLookups($items, $fotos);
        $docs = $this->loadDocuments($items, $fotos);

        $data = [];
        foreach ($items as $a) {
            $fila = $this->presentarRegistro($a, $fotos[$a->id], $docs, $lk);
            if ($fila !== null) {
                $data[] = $fila;
            }
        }

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
     * Un registro, listo para la pantalla. Null si es una edición vacía
     * (registros viejos que solo cambiaron espacios, saltos de línea o el
     * formato de un número): no se muestran.
     */
    private function presentarRegistro(Audit $a, ?array $foto, array $docs, array $lk): ?array
    {
        // Registro viejo de un ajuste: su foto se arma con el ajuste de hoy.
        $fotoActual = $foto ?? $docs['fotoActual'][$a->id] ?? null;

        $changes = $this->buildChanges($a, $lk, $foto);
        $lineChanges = $foto ? $this->cambiosDeLineas($foto) : [];

        if ($this->esEdicionVacia($a, $foto, $lk, $lineChanges)) {
            return null;
        }

        $user = $a->user;

        return [
            'id' => $a->id,
            'event' => $a->event,
            'eventLabel' => $this->eventNames[$a->event] ?? $a->event,
            'model' => $a->auditable_type,
            'modelLabel' => Vocabulario::entidad($a->auditable_type),
            'auditableId' => $a->auditable_id,
            'userId' => $a->user_id,
            'userName' => $user->name ?? 'Sistema',
            'userEmail' => $user->email ?? null,
            'ipAddress' => $a->ip_address,
            // Descripción humana: el documento, sus datos y sus líneas
            'summary' => $fotoActual ? $this->resumenDeFoto($fotoActual) : $this->buildSummary($a, $docs),
            'document' => $fotoActual['titulo'] ?? null,
            'details' => $fotoActual ? array_map(fn ($d) => ['label' => $d[0], 'value' => $d[1]], $fotoActual['datos']) : [],
            'lines' => $fotoActual ? array_column($fotoActual['lineas'], 'texto') : [],
            'lineChanges' => $lineChanges,
            'changes' => $changes,
            // Se mantienen los crudos por compatibilidad
            'oldValues' => $a->old_values,
            'newValues' => $a->new_values,
            'createdAt' => $a->created_at?->toIso8601String(),
        ];
    }

    /** @return array<int, array|null> id del registro → foto (o null si es un registro viejo) */
    private function leerFotos(array $items): array
    {
        $fotos = [];
        foreach ($items as $a) {
            $crudo = $a->getAttribute('snapshot');
            $foto = is_string($crudo) ? json_decode($crudo, true) : (is_array($crudo) ? $crudo : null);
            $fotos[$a->id] = is_array($foto) && isset($foto['titulo'], $foto['datos'], $foto['lineas']) ? $foto : null;
        }

        return $fotos;
    }

    /**
     * Carga en lote los nombres de lo referenciado por ID que la foto del
     * registro no trae (en los registros viejos, todo).
     */
    private function loadLookups(array $items, array $fotos): array
    {
        $porClase = [];
        foreach ($items as $a) {
            $conocidos = $fotos[$a->id]['nombres'] ?? [];
            foreach ([$a->old_values ?? [], $a->new_values ?? []] as $vals) {
                if (!is_array($vals)) {
                    continue;
                }
                foreach ($vals as $campo => $valor) {
                    $clase = Vocabulario::referencia($a->auditable_type, (string) $campo);
                    if ($clase && is_string($valor) && $valor !== '' && !isset($conocidos[$valor])) {
                        $porClase[$clase][] = $valor;
                    }
                }
            }
        }

        $nombres = [];
        foreach ($porClase as $clase => $ids) {
            $nombres[$clase] = $clase::whereIn('id', array_values(array_unique($ids)))
                ->pluck(Vocabulario::columnaNombre($clase), 'id')
                ->all();
        }

        return [
            'porClase' => $nombres,
            // Nombre técnico del perfil (users.role) → nombre visible
            'roles' => Role::pluck('display_name', 'name')->all(),
        ];
    }

    /**
     * Carga en lote, SOLO para los registros viejos (sin foto), el documento
     * actual con sus líneas y el nombre actual de cada registro.
     */
    private function loadDocuments(array $items, array $fotos): array
    {
        $ids = [];
        foreach ($items as $a) {
            if ($fotos[$a->id] === null) {
                $ids[$a->auditable_type][] = $a->auditable_id;
            }
        }
        $de = fn (string $tipo) => array_values(array_unique($ids[$tipo] ?? []));

        $docs = [
            'purchase' => $de('purchase') === [] ? collect() : \App\Models\Purchase::with(['purchaseItems.product', 'purchaseItems.brand', 'supplier', 'destinationLocation'])->whereIn('id', $de('purchase'))->get()->keyBy('id'),
            'output' => $de('output') === [] ? collect() : \App\Models\ProductOutput::with(['outputProducts.product', 'outputProducts.brand', 'originLocation', 'destinationLocation', 'outputType'])->whereIn('id', $de('output'))->get()->keyBy('id'),
            'reception' => $de('reception') === [] ? collect() : \App\Models\Reception::with(['receptionItems.product', 'receptionItems.brand', 'originLocation', 'destinationLocation'])->whereIn('id', $de('reception'))->get()->keyBy('id'),
            'nombres' => [],
            'nombresGuardados' => [],
            'fotoActual' => [],
        ];

        // Un registro viejo de un ajuste se lee con la misma foto que los
        // nuevos, armada con el ajuste de hoy (un ajuste solo cambia de estado).
        if ($de('adjustment') !== []) {
            $ajustes = Adjustment::with(['reason:id,name', 'product:id,name,base_unit', 'brand:id,name', 'originLocation:id,name', 'destinationLocation:id,name', 'requester:id,name', 'approver:id,name'])
                ->whereIn('id', $de('adjustment'))->get()->keyBy('id');

            foreach ($items as $a) {
                if ($fotos[$a->id] === null && $a->auditable_type === 'adjustment' && ($ajuste = $ajustes->get($a->auditable_id))) {
                    $docs['fotoActual'][$a->id] = FotoDeAuditoria::de($ajuste, $this->nombresDeAjuste($ajuste));
                }
            }
        }

        // Nombre actual del registro (producto, proveedor, perfil…): una
        // edición vieja solo trae los campos que cambiaron.
        foreach ($ids as $tipo => $lista) {
            if (in_array($tipo, self::DOCUMENTOS, true) || !($clase = Relation::getMorphedModel($tipo))) {
                continue;
            }
            $docs['nombres'][$tipo] = $clase::whereIn((new $clase())->getKeyName(), array_values(array_unique($lista)))
                ->pluck(Vocabulario::columnaNombre($clase), (new $clase())->getKeyName())
                ->all();
        }

        // Lo que ya no existe (una compra eliminada, un perfil borrado) se
        // nombra con su último nombre conocido en los demás registros del
        // mismo (el de creación o el de eliminación lo traen), para decir
        // "Perfil: Supervisor de cosecha" y no solo "Perfil". Una consulta.
        $faltan = [];
        foreach ($ids as $tipo => $lista) {
            $existentes = match ($tipo) {
                'adjustment' => $ajustes ?? collect(),
                'purchase', 'output', 'reception' => $docs[$tipo],
                default => collect($docs['nombres'][$tipo] ?? []),
            };
            foreach (array_unique($lista) as $id) {
                if (!$existentes->has($id)) {
                    $faltan[] = $id;
                }
            }
        }
        if ($faltan !== []) {
            $otros = Audit::query()->whereIn('auditable_id', array_values(array_unique($faltan)))
                ->orderByDesc('id')
                ->get(['auditable_type', 'auditable_id', 'old_values', 'new_values']);
            foreach ($otros as $otro) {
                foreach ([$otro->new_values, $otro->old_values] as $vals) {
                    if (!is_array($vals)) {
                        continue;
                    }
                    foreach (self::CAMPOS_DE_NOMBRE as $campo) {
                        if (is_string($vals[$campo] ?? null) && $vals[$campo] !== '') {
                            $docs['nombresGuardados'][$otro->auditable_type][$otro->auditable_id] ??= $vals[$campo];
                            break;
                        }
                    }
                }
            }
        }

        return $docs;
    }

    /** ID → nombre de todo lo que referencia un ajuste, con sus relaciones ya cargadas. */
    private function nombresDeAjuste(Adjustment $ajuste): array
    {
        $nombres = [];
        foreach ([
            'reason_id' => 'reason', 'product_id' => 'product', 'brand_id' => 'brand',
            'origin_location_id' => 'originLocation', 'destination_location_id' => 'destinationLocation',
            'responsible_user' => 'requester', 'approved_by' => 'approver',
        ] as $columna => $relacion) {
            if ($id = $ajuste->getAttribute($columna)) {
                $nombres[$id] = $ajuste->getRelation($relacion)?->name;
            }
        }

        return $nombres;
    }

    /** "Compra PUR-x — 2 Galón SPORTAK (Sin Marca), … · Proveedor: … · Total: …" */
    private function resumenDeFoto(array $foto): string
    {
        $lineas = array_column($foto['lineas'], 'texto');
        $datos = array_map(fn ($d) => $d[0] !== null ? "{$d[0]}: {$d[1]}" : $d[1], $foto['datos']);

        return $foto['titulo']
            . ($lineas ? ' — ' . implode(', ', $lineas) : '')
            . ($datos ? ' · ' . implode(' · ', $datos) : '');
    }

    /**
     * Resumen de un registro viejo (sin foto), con el documento actual.
     */
    private function buildSummary(Audit $a, array $docs): string
    {
        $type = $a->auditable_type;
        $num = fn ($v) => Vocabulario::numero($v);

        if ($type === 'purchase' && ($p = $docs['purchase']->get($a->auditable_id))) {
            $its = $p->purchaseItems->map(fn ($it) => $num($it->quantity) . ' ' . ($it->product->name ?? 'producto') . ($it->brand ? ' (' . $it->brand->name . ')' : ''))->implode(', ');
            $parts = array_filter([
                $its ?: null,
                $p->supplier ? 'Proveedor: ' . $p->supplier->name : null,
                $p->destinationLocation ? 'Destino: ' . $p->destinationLocation->name : null,
                $p->total ? 'Total: ' . Vocabulario::dinero($p->total) : null,
            ]);
            return 'Compra ' . $p->order_number . ($parts ? ' — ' . implode(' · ', $parts) : '');
        }

        if ($type === 'output' && ($o = $docs['output']->get($a->auditable_id))) {
            $its = $o->outputProducts->map(fn ($it) => trim($num($it->quantity_delivered ?: $it->quantity_requested) . ' ' . ($it->unit ?: '') . ' ' . ($it->product->name ?? 'producto')))->implode(', ');
            $route = ($o->originLocation->name ?? '?') . ' → ' . ($o->destinationLocation->name ?? '?');
            $t = $o->outputType->name ?? 'Salida';
            return trim('Salida ' . $o->output_number . ' (' . $t . '): ' . ($its ? $its . ' · ' : '') . $route);
        }

        if ($type === 'reception' && ($r = $docs['reception']->get($a->auditable_id))) {
            $its = $r->receptionItems->map(fn ($it) => trim($num($it->quantity_received ?: $it->quantity_expected) . ' ' . ($it->unit ?: '') . ' ' . ($it->product->name ?? 'producto')))->implode(', ');
            return trim('Recepción ' . $r->reception_number . ': ' . ($its ?: 'sin items') . ' en ' . ($r->destinationLocation->name ?? '?'));
        }

        // Datos maestros (o documento ya eliminado): usar el nombre disponible
        $vals = (!empty($a->new_values) ? $a->new_values : $a->old_values) ?? [];
        $label = Vocabulario::entidad($type);

        $nombre = $docs['nombres'][$type][$a->auditable_id] ?? null;
        foreach (self::CAMPOS_DE_NOMBRE as $campo) {
            $nombre ??= is_string($vals[$campo] ?? null) && $vals[$campo] !== '' ? $vals[$campo] : null;
        }
        $nombre ??= $docs['nombresGuardados'][$type][$a->auditable_id] ?? null;

        return $nombre ? "$label: $nombre" : $label;
    }

    /**
     * Lista de cambios campo a campo, ya resueltos y traducidos.
     */
    private function buildChanges(Audit $a, array $lk, ?array $foto): array
    {
        if ($a->event === 'deleted') {
            return [];
        }
        $type = $a->auditable_type;
        $old = is_array($a->old_values) ? $a->old_values : [];
        $new = is_array($a->new_values) ? $a->new_values : [];
        $nombres = $foto['nombres'] ?? [];
        // Quien "aprueba" un ajuste rechazado lo rechazó.
        $rechazo = $type === 'adjustment' && ($new['status'] ?? null) === 'rejected';

        $out = [];
        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $k) {
            $k = (string) $k;
            if (Vocabulario::oculto($k)) {
                continue;
            }
            // De un perfil se muestra el nombre visible, no el técnico.
            if ($type === 'role' && in_array($k, ['name', 'excluded_modules'], true)) {
                continue;
            }
            $label = $k === 'approved_by' && $rechazo ? 'Rechazó' : Vocabulario::etiqueta($type, $k);
            $from = $this->present($type, $k, $old[$k] ?? null, $nombres, $lk);
            $to = $this->present($type, $k, $new[$k] ?? null, $nombres, $lk);

            if ($a->event === 'created') {
                if ($to === null || $to === '') continue;
                $out[] = ['label' => $label, 'from' => null, 'to' => $to];
                continue;
            }

            if ($from === $to) {
                continue;
            }

            // Texto agregado al final (p. ej. el motivo de una eliminación en
            // las observaciones): se muestra solo lo agregado, no todo dos veces.
            if (($agregado = $this->textoAgregado($type, $k, $old[$k] ?? null, $new[$k] ?? null)) !== null) {
                $out[] = ['label' => "$label (se agregó)", 'from' => null, 'to' => $agregado];
                continue;
            }

            $out[] = ['label' => $label, 'from' => $from, 'to' => $to];
        }

        return $out;
    }

    /**
     * Qué productos se agregaron, quitaron o cambiaron entre la foto de antes
     * y la de después de la acción. Las líneas que siguen igual no se nombran.
     */
    private function cambiosDeLineas(array $foto): array
    {
        if (!isset($foto['lineas_antes']) || !is_array($foto['lineas_antes'])) {
            return [];
        }

        $agrupar = function (array $lineas): array {
            $grupos = [];
            foreach ($lineas as $linea) {
                $grupos[$linea['clave'] ?? $linea['texto']][] = $linea['texto'];
            }
            return $grupos;
        };
        $antes = $agrupar($foto['lineas_antes']);
        $despues = $agrupar($foto['lineas']);

        $out = [];
        foreach (array_unique(array_merge(array_keys($antes), array_keys($despues))) as $clave) {
            $a = $antes[$clave] ?? [];
            $d = $despues[$clave] ?? [];
            foreach ($a as $i => $texto) {
                if (($j = array_search($texto, $d, true)) !== false) {
                    unset($a[$i], $d[$j]);
                }
            }
            $a = array_values($a);
            $d = array_values($d);

            for ($i = 0, $n = max(count($a), count($d)); $i < $n; $i++) {
                $from = $a[$i] ?? null;
                $to = $d[$i] ?? null;
                $out[] = [
                    'label' => $from === null ? 'Producto agregado' : ($to === null ? 'Producto quitado' : 'Producto cambiado'),
                    'from' => $from,
                    'to' => $to,
                ];
            }
        }

        return $out;
    }

    /**
     * ¿Una edición que no cambió nada? Todos sus pares antes/después dicen lo
     * mismo (crudos equivalentes o iguales al mostrarlos) y no tocó líneas.
     */
    private function esEdicionVacia(Audit $a, ?array $foto, array $lk, array $lineChanges): bool
    {
        if ($a->event !== 'updated' || $lineChanges !== []) {
            return false;
        }
        $old = is_array($a->old_values) ? $a->old_values : [];
        $new = is_array($a->new_values) ? $a->new_values : [];
        $nombres = $foto['nombres'] ?? [];

        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $k) {
            $k = (string) $k;
            $antes = $old[$k] ?? null;
            $despues = $new[$k] ?? null;
            if (Vocabulario::equivalentes($antes, $despues, null, $k)) {
                continue;
            }
            if ($this->present($a->auditable_type, $k, $antes, $nombres, $lk) !== $this->present($a->auditable_type, $k, $despues, $nombres, $lk)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Presenta un valor crudo de forma legible (resuelve ID, traduce, formatea).
     */
    private function present(string $type, string $field, $value, array $nombres, array $lk): ?string
    {
        return Vocabulario::presentar($type, $field, $value, $nombres, $lk['porClase'], $lk['roles']);
    }

    /** Lo que se agregó al final de un texto, o null si no es un agregado. */
    private function textoAgregado(string $type, string $field, $antes, $despues): ?string
    {
        if (!in_array($field, self::TEXTOS_LIBRES, true) || !is_string($antes) || !is_string($despues) || trim($antes) === '') {
            return null;
        }
        $antes = rtrim(str_replace(["\r\n", "\r"], "\n", $antes));
        $despues = str_replace(["\r\n", "\r"], "\n", $despues);

        if (strlen($despues) <= strlen($antes) || !str_starts_with($despues, $antes) || !ctype_space(substr($despues, strlen($antes), 1))) {
            return null;
        }

        return Vocabulario::texto(substr($despues, strlen($antes)));
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
