<?php

namespace App\Support\Auditoria;

use App\Models\Brand;
use App\Models\FarmLot;
use App\Models\Location;
use App\Models\OutputFarmLot;
use App\Models\OutputProduct;
use App\Models\PackagingUnit;
use App\Models\Product;
use App\Models\ProductOutput;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\ReceptionItem;
use App\Models\RecipeProduct;
use App\Models\Task;
use App\Models\TaskCatalog;
use App\Models\TaskSchedule;
use App\Models\TechnicalOrderFarm;
use App\Models\TechnicalOrderProduct;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Model;

/**
 * La foto de un registro tal como estaba EN EL MOMENTO de la acción, ya en
 * palabras. Se guarda en `audits.snapshot` y es lo que la pantalla muestra
 * como resumen del documento.
 *
 * Por qué una foto y no "el documento de hoy": un registro de hace un mes
 * tiene que decir los productos y cantidades de hace un mes, y uno de un
 * documento eliminado tiene que seguir diciendo qué llevaba. Por eso todo se
 * guarda RESUELTO (nombres, no IDs): si mañana se borra el proveedor o el
 * producto, el registro se sigue leyendo igual.
 *
 * Forma (versión 1):
 *   titulo  "Compra PUR-2026-0012"
 *   datos   [[etiqueta|null, valor], ...]        "Proveedor: AGRO S.A.S", "BODEGA → FINCA"
 *   lineas  [['clave' => ..., 'texto' => ...]]   "2 Galón SPORTAK (Sin Marca) = 8 L"
 *   nombres [ID => nombre]                        de toda referencia del registro
 *
 * `clave` identifica la línea (producto, marca, empaque…) para poder decir
 * qué línea se agregó, se quitó o cambió entre dos fotos.
 *
 * Se arma al escribir, una vez por registro: aquí sí se consulta la base (unas
 * pocas consultas por documento). La pantalla, en cambio, solo lee la foto.
 */
final class FotoDeAuditoria
{
    public const VERSION = 1;

    /** Entidades cuya foto lleva líneas (productos). */
    public const CON_LINEAS = ['purchase', 'output', 'reception', 'technical_order', 'technical_recipe'];

    /**
     * Datos maestros: [columna con el nombre, columnas que van en la foto].
     */
    private const MAESTROS = [
        'product' => ['name', ['product_code', 'brand_id', 'category_id', 'base_unit', 'status']],
        'brand' => ['name', ['status']],
        'location' => ['name', ['type', 'municipality', 'responsible_user_id', 'status']],
        'supplier' => ['name', ['nit', 'status']],
        'company' => ['name', ['nit', 'status']],
        'category' => ['name', ['status']],
        'base_unit' => ['name', ['symbol', 'status']],
        'packaging_unit' => ['name', []],
        'output_type' => ['name', ['code', 'requires_lots', 'status']],
        'farm_lot' => ['name', ['location_id', 'status']],
        'task_catalog' => ['name', ['code', 'unit', 'reference_yield', 'active']],
        'alert' => ['title', ['type', 'severity', 'product_id', 'location_id', 'status']],
        'worker' => ['full_name', ['worker_code', 'document_id', 'status']],
        'task' => ['name', ['code', 'daily_cost', 'status']],
    ];

    /** @var array<string, array<string, string|null>> modelo → [ID → nombre] */
    private array $memo = [];

    private readonly string $alias;

    /**
     * @param  array<string, string>  $conocidos  ID → nombre ya resueltos (evita consultas)
     */
    private function __construct(private readonly Model $m, private readonly array $conocidos)
    {
        $this->alias = $m->getMorphClass();
    }

    /**
     * @param  array<string, string>  $conocidos  ID → nombre ya resueltos por quien llama
     * @return array{v: int, titulo: string, datos: array<int, array{0: ?string, 1: string}>, lineas: array<int, array{clave: string, texto: string}>, nombres: array<string, string>}
     */
    public static function de(Model $modelo, array $conocidos = []): array
    {
        return (new self($modelo, $conocidos))->tomar();
    }

    public static function tieneLineas(Model $modelo): bool
    {
        return in_array($modelo->getMorphClass(), self::CON_LINEAS, true);
    }

    private function tomar(): array
    {
        $nombres = $this->nombresDeReferencias();

        [$titulo, $datos, $lineas] = match ($this->alias) {
            'purchase' => $this->compra(),
            'output' => $this->salida(),
            'reception' => $this->recepcion(),
            'adjustment' => $this->ajuste(),
            'technical_order' => $this->ordenTecnica(),
            'technical_recipe' => $this->receta(),
            'task_schedule' => $this->programacion(),
            'task_daily_log' => $this->avance(),
            'daily_assignment' => $this->asignacion(),
            'task_deduction' => $this->deduccion(),
            'packaging_unit' => $this->empaque(),
            'performance_settings' => $this->umbrales(),
            default => $this->maestro(),
        };

        $limpios = [];
        foreach ($datos as [$etiqueta, $valor]) {
            if ($valor !== null && $valor !== '') {
                $limpios[] = [$etiqueta, (string) $valor];
            }
        }

        return [
            'v' => self::VERSION,
            'titulo' => $titulo,
            'datos' => $limpios,
            'lineas' => $lineas,
            // Lo que se resolvió para las líneas también se guarda: si el
            // registro trae ese ID en sus cambios, ya tiene nombre.
            'nombres' => $nombres + $this->nombresUsados(),
        ];
    }

    // ------------------------------------------------------------------
    // Documentos
    // ------------------------------------------------------------------

    private function compra(): array
    {
        $items = PurchaseItem::where('purchase_id', $this->m->getKey())->orderBy('id')->get();
        $this->precargar(Product::class, $items->pluck('product_id'));
        $this->precargar(Brand::class, $items->pluck('brand_id'));
        $empaques = PackagingUnit::whereIn('id', $items->pluck('packaging_unit_id')->filter()->unique())->get()->keyBy('id');

        $lineas = $items->map(function (PurchaseItem $it) use ($empaques) {
            $empaque = $empaques->get($it->packaging_unit_id);
            $producto = $this->productoYMarca($it->product_id, $it->brand_id);
            $texto = trim(Vocabulario::numero($it->quantity) . ' ' . ($empaque->name ?? '') . ' ' . $producto);

            $base = $it->quantity_in_base_units;
            if ($empaque && $base !== null && (float) $base !== (float) $it->quantity) {
                $texto .= ' = ' . Vocabulario::numero($base) . ' ' . $empaque->base_unit;
            }
            if ($it->unit_price !== null) {
                $texto .= ' · ' . Vocabulario::dinero($it->unit_price) . ' c/u';
            }

            return ['clave' => $producto . '|' . ($empaque->name ?? ''), 'texto' => $texto];
        })->values()->all();

        return ['Compra ' . $this->v('order_number'), [
            ['Proveedor', $this->campo('supplier_id')],
            ['Empresa', $this->campo('company_id')],
            ['Origen', $this->campo('origin_location_id')],
            ['Destino', $this->campo('destination_location_id')],
            ['Fecha', $this->campo('purchase_date')],
            ['Estado', $this->campo('status')],
            ['Total', $this->campo('total')],
        ], $lineas];
    }

    private function salida(): array
    {
        $items = OutputProduct::where('output_id', $this->m->getKey())->orderBy('id')->get();
        $this->precargar(Product::class, $items->pluck('product_id'));
        $this->precargar(Brand::class, $items->pluck('brand_id'));

        $lineas = $items->map(function (OutputProduct $it) {
            $producto = $this->productoYMarca($it->product_id, $it->brand_id);
            $entregado = (float) $it->quantity_delivered;
            $pedido = (float) $it->quantity_requested;
            $texto = Vocabulario::numero($entregado > 0 ? $entregado : $pedido) . ' ' . trim((string) $it->unit . ' ' . $producto);
            if ($entregado > 0 && $pedido > 0 && abs($entregado - $pedido) > 0.0001) {
                $texto .= ' (solicitado ' . Vocabulario::numero($pedido) . ')';
            }
            if ($it->batch_number) {
                $texto .= ' · lote ' . $it->batch_number;
            }

            return ['clave' => $producto . '|' . $it->batch_number, 'texto' => $texto];
        })->values()->all();

        $lotes = FarmLot::whereIn('id', OutputFarmLot::where('product_output_id', $this->m->getKey())->pluck('farm_lot_id'))
            ->orderBy('name')->pluck('name')->implode(', ');

        return ['Salida ' . $this->v('output_number'), [
            ['Tipo', $this->campo('output_type_id')],
            ['Ruta', $this->ruta()],
            ['Fecha', $this->campo('output_date')],
            ['Estado', $this->campo('status')],
            ['Orden técnica', $this->campo('technical_order_id')],
            ['Lotes', $lotes],
            ['Empresa', $this->campo('company_id')],
        ], $lineas];
    }

    private function recepcion(): array
    {
        $items = ReceptionItem::where('reception_id', $this->m->getKey())->orderBy('id')->get();
        $this->precargar(Product::class, $items->pluck('product_id'));
        $this->precargar(Brand::class, $items->pluck('brand_id'));

        $lineas = $items->map(function (ReceptionItem $it) {
            $producto = $this->productoYMarca($it->product_id, $it->brand_id);
            $texto = Vocabulario::numero($it->quantity_received) . ' de ' . Vocabulario::numero($it->quantity_expected)
                . ' ' . trim((string) $it->unit . ' ' . $producto);
            if ($it->condition && $it->condition !== 'good') {
                $texto .= ' · ' . Vocabulario::presentar('reception', 'condition', $it->condition);
            }

            return ['clave' => $producto, 'texto' => $texto];
        })->values()->all();

        // El documento de origen se dice por su número: una recepción sin su
        // compra o su salida no se entiende.
        $origen = null;
        if ($id = $this->v('source_id')) {
            $origen = match ($this->v('source_type')) {
                'purchase' => ($n = Purchase::whereKey($id)->value('order_number')) ? "Compra {$n}" : null,
                'output' => ($n = ProductOutput::whereKey($id)->value('output_number')) ? "Salida {$n}" : null,
                default => null,
            };
        }

        $avance = $this->v('completion_percentage');

        return ['Recepción ' . $this->v('reception_number'), [
            ['Documento', $origen],
            ['Origen', $this->campo('origin_location_id')],
            ['Destino', $this->campo('destination_location_id')],
            ['Estado', $this->campo('status')],
            ['Avance', $avance !== null ? Vocabulario::numero($avance) . '%' : null],
        ], $lineas];
    }

    /**
     * Un ajuste es un documento de una sola línea: el producto y la cantidad
     * van en el resumen mismo. "Ajuste de entrada AJU-… · SPORTAK (Sin Marca)
     * · +0,3 L · BODEGA PRINCIPAL · Motivo: Conteo físico · Estado: Aprobada ·
     * Aprobó: Administrador AgriFlor".
     *
     * La cantidad se dice como en la pantalla de Ajustes: en modo delta es lo
     * que se mueve, con signo; en modo absoluto es el saldo que debe quedar en
     * el lote, y lo que realmente se movió solo se sabe al aprobar
     * (`quantity_base`, siempre positivo y en unidad base).
     */
    private function ajuste(): array
    {
        $tipo = $this->v('type');
        $titulo = match ($tipo) {
            'entry' => 'Ajuste de entrada',
            'exit' => 'Ajuste de salida',
            'transfer' => 'Traslado por ajuste',
            default => 'Ajuste',
        } . ' ' . $this->v('adjustment_number');

        $signo = match ($tipo) {
            'entry' => '+',
            'exit' => '-',
            default => '',
        };
        $unidad = (string) $this->v('unit');

        if ($this->v('quantity_mode') === 'absolute') {
            $cantidad = 'Fijar saldo en ' . Vocabulario::numero($this->v('quantity')) . " {$unidad}";
            if (($aplicado = $this->v('quantity_base')) !== null) {
                $cantidad .= ' (movió ' . ($tipo === 'exit' ? '-' : '+') . Vocabulario::numero($aplicado) . ' ' . ($this->unidadBase($this->v('product_id')) ?? $unidad) . ')';
            }
        } else {
            $cantidad = $signo . Vocabulario::numero($this->v('quantity')) . " {$unidad}";
        }

        $ubicacion = match ($tipo) {
            'entry' => $this->campo('destination_location_id'),
            'exit' => $this->campo('origin_location_id'),
            default => $this->ruta(),
        };

        $rechazada = $this->v('status') === 'rejected';

        return [$titulo, [
            [null, $this->productoYMarca($this->v('product_id'), $this->v('brand_id'))],
            [null, trim($cantidad)],
            [null, $ubicacion],
            ['Lote', $this->v('batch_number')],
            ['Motivo', $this->campo('reason_id')],
            ['Estado', $this->campo('status')],
            [$rechazada ? 'Rechazó' : 'Aprobó', $this->campo('approved_by')],
            ['Motivo del rechazo', $rechazada ? $this->campo('rejection_reason') : null],
            ['Fecha del movimiento', $this->campo('movement_date')],
        ], []];
    }

    private function ordenTecnica(): array
    {
        $items = TechnicalOrderProduct::where('technical_order_id', $this->m->getKey())->orderBy('id')->get();
        $lineas = $this->lineasSimples($items);

        $fincas = Location::whereIn('id', TechnicalOrderFarm::where('technical_order_id', $this->m->getKey())->pluck('farm_id'))
            ->orderBy('name')->pluck('name')->implode(', ');

        return ['Orden técnica ' . $this->v('order_number'), [
            ['Fecha programada', $this->campo('scheduled_date')],
            ['Estado', $this->campo('status')],
            ['Receta', $this->campo('recipe_id')],
            ['Agrónomo', $this->campo('responsible_agronomist')],
            ['Fincas', $fincas],
        ], $lineas];
    }

    private function receta(): array
    {
        $items = RecipeProduct::where('recipe_id', $this->m->getKey())->orderBy('id')->get();

        return ['Receta técnica ' . $this->v('name'), [
            ['Categoría', $this->campo('category')],
            ['Estado', $this->campo('status')],
        ], $this->lineasSimples($items, 'application_rate')];
    }

    private function programacion(): array
    {
        $tarea = $this->tareaDeCatalogo($this->v('task_catalog_id'));
        $unidad = $tarea ? Vocabulario::presentar('task_catalog', 'unit', $tarea->unit) : null;
        $inicio = $this->campo('start_date');
        $fin = $this->campo('end_date');

        return ['Programación ' . $this->v('code'), [
            ['Tarea', $tarea->name ?? $this->campo('task_catalog_id')],
            ['Finca', $this->campo('location_id')],
            ['Lote', $this->campo('lot_id')],
            ['Cantidad', $this->v('total_quantity') !== null ? trim(Vocabulario::numero($this->v('total_quantity')) . ' ' . $unidad) : null],
            ['Fechas', $inicio && $fin ? "{$inicio} al {$fin}" : ($inicio ?? $fin)],
            ['Personas', $this->campo('planned_persons')],
            ['Estado', $this->campo('status')],
            ['Avance', $this->campo('accumulated_pct')],
        ], []];
    }

    private function avance(): array
    {
        $programacion = ($id = $this->v('task_schedule_id'))
            ? TaskSchedule::find($id, ['id', 'code', 'task_catalog_id', 'location_id'])
            : null;
        $tarea = $programacion ? $this->tareaDeCatalogo($programacion->task_catalog_id) : null;

        $titulo = 'Avance de ' . ($programacion->code ?? 'una programación eliminada')
            . ($tarea ? " ({$tarea->name})" : '');

        return [$titulo, [
            ['Fecha', $this->campo('log_date')],
            ['Avance del día', $this->campo('advance_pct_today')],
            ['Acumulado', $this->campo('accumulated_snapshot_pct')],
            ['Personas', $this->campo('persons_today')],
            ['Finca', $programacion ? $this->nombre(Location::class, $programacion->location_id) : null],
            ['Modo', $this->campo('mode')],
            ['Marcado como sospechoso', $this->v('suspicious') ? 'Sí' : null],
        ], []];
    }

    private function asignacion(): array
    {
        $trabajador = $this->nombre(Worker::class, $this->v('worker_id'));
        $tarea = $this->nombre(Task::class, $this->v('task_id'));

        return ['Asignación del ' . $this->campo('date'), [
            ['Trabajador', $trabajador ? trim("{$trabajador} ({$this->v('worker_code')})") : $this->v('worker_code')],
            ['Tarea', $tarea ? trim("{$tarea} ({$this->v('task_code')})") : $this->v('task_code')],
            ['Valor bruto', $this->campo('gross_amount')],
            ['Descuentos', $this->campo('total_deductions')],
            ['Valor neto', $this->campo('net_amount')],
        ], []];
    }

    private function deduccion(): array
    {
        return ['Deducción ' . $this->v('deduction_name'), [
            ['Tarea', $this->campo('task_id')],
            ['Porcentaje', $this->campo('percentage')],
            ['Activa', $this->campo('is_active')],
        ], []];
    }

    private function empaque(): array
    {
        return ['Unidad de empaque: ' . $this->v('name'), [
            ['Equivale a', trim(Vocabulario::numero($this->v('base_quantity')) . ' ' . $this->v('base_unit'))],
        ], []];
    }

    private function umbrales(): array
    {
        return ['Umbrales de rendimiento', [
            ['Sobrepaso', $this->campo('global_sobrepaso_pct')],
            ['Alto', $this->campo('global_alto_pct')],
            ['Medio', $this->campo('global_medio_pct')],
            ['Factor K', $this->campo('global_k_factor')],
        ], []];
    }

    private function maestro(): array
    {
        [$columnaNombre, $columnas] = self::MAESTROS[$this->alias] ?? ['name', []];
        $nombre = $this->v($columnaNombre) ?? $this->v('name') ?? $this->v('code');

        $datos = [];
        foreach ($columnas as $columna) {
            $datos[] = [Vocabulario::etiqueta($this->alias, $columna), $this->campo($columna)];
        }

        return [Vocabulario::entidad($this->alias) . ($nombre !== null ? ": {$nombre}" : ''), $datos, []];
    }

    // ------------------------------------------------------------------
    // Ayudas
    // ------------------------------------------------------------------

    /** Valor crudo de una columna del registro. */
    private function v(string $columna): mixed
    {
        return $this->m->getAttributes()[$columna] ?? null;
    }

    /** Valor de una columna ya en palabras (con su referencia resuelta). */
    private function campo(string $columna): ?string
    {
        $valor = $this->v($columna);

        if ($clase = Vocabulario::referencia($this->alias, $columna)) {
            return $this->nombre($clase, $valor) ?? (Vocabulario::esUuid($valor) ? Vocabulario::ELIMINADO : null);
        }

        return Vocabulario::presentar($this->alias, $columna, $valor);
    }

    private function ruta(): ?string
    {
        $origen = $this->campo('origin_location_id');
        $destino = $this->campo('destination_location_id');

        return $origen || $destino ? ($origen ?? '?') . ' → ' . ($destino ?? '?') : null;
    }

    private function productoYMarca(?string $productoId, ?string $marcaId): string
    {
        $producto = $this->nombre(Product::class, $productoId) ?? Vocabulario::ELIMINADO;
        $marca = $this->nombre(Brand::class, $marcaId);

        return $marca ? "{$producto} ({$marca})" : $producto;
    }

    /** "12 L SPORTAK (Sin Marca)" para recetas y órdenes técnicas. */
    private function lineasSimples($items, ?string $detalle = null): array
    {
        $this->precargar(Product::class, $items->pluck('product_id'));
        $this->precargar(Brand::class, $items->pluck('brand_id'));

        return $items->map(function (Model $it) use ($detalle) {
            $producto = $this->productoYMarca($it->product_id, $it->brand_id);
            $texto = Vocabulario::numero($it->quantity) . ' ' . trim((string) $it->unit . ' ' . $producto);
            if ($detalle && $it->{$detalle}) {
                $texto .= ' · dosis ' . $it->{$detalle};
            }

            return ['clave' => $producto, 'texto' => $texto];
        })->values()->all();
    }

    private function tareaDeCatalogo(?string $id): ?TaskCatalog
    {
        return $id ? TaskCatalog::find($id, ['id', 'name', 'unit']) : null;
    }

    private function unidadBase(?string $productoId): ?string
    {
        if (!$productoId) {
            return null;
        }
        if ($this->m->relationLoaded('product') && $this->m->getRelation('product')) {
            return $this->m->getRelation('product')->base_unit;
        }

        return Product::whereKey($productoId)->value('base_unit');
    }

    /**
     * Nombre de lo que referencia cada columna del registro, con los valores
     * de ahora y los originales (en una edición, los dos lados del cambio).
     *
     * @return array<string, string>
     */
    private function nombresDeReferencias(): array
    {
        $porClase = [];
        foreach ([$this->m->getAttributes(), $this->m->getRawOriginal()] as $atributos) {
            foreach ($atributos as $columna => $valor) {
                if (is_string($valor) && $valor !== '' && ($clase = Vocabulario::referencia($this->alias, $columna))) {
                    $porClase[$clase][] = $valor;
                }
            }
        }

        $nombres = [];
        foreach ($porClase as $clase => $ids) {
            $this->precargar($clase, collect($ids));
            foreach (array_unique($ids) as $id) {
                if (($n = $this->memo[$clase][$id] ?? null) !== null) {
                    $nombres[$id] = $n;
                }
            }
        }

        return $nombres;
    }

    /** @return array<string, string> todo lo que se resolvió al armar la foto */
    private function nombresUsados(): array
    {
        $nombres = [];
        foreach ($this->memo as $porId) {
            foreach ($porId as $id => $n) {
                if ($n !== null) {
                    $nombres[$id] = $n;
                }
            }
        }

        return $nombres;
    }

    private function precargar(string $clase, $ids): void
    {
        $faltan = collect($ids)->filter(fn ($id) => is_string($id) && $id !== '')
            ->unique()
            ->reject(fn ($id) => array_key_exists($id, $this->memo[$clase] ?? []))
            ->values();

        // Lo que ya trae quien llama (también "no existe": null) no se consulta.
        foreach ($faltan as $i => $id) {
            if (array_key_exists($id, $this->conocidos)) {
                $this->memo[$clase][$id] = $this->conocidos[$id];
                $faltan->forget($i);
            }
        }

        if ($faltan->isEmpty()) {
            return;
        }

        $encontrados = $clase::whereIn('id', $faltan->all())->pluck(Vocabulario::columnaNombre($clase), 'id');
        foreach ($faltan as $id) {
            $this->memo[$clase][$id] = $encontrados[$id] ?? null;
        }
    }

    private function nombre(string $clase, mixed $id): ?string
    {
        if (!is_string($id) || $id === '') {
            return null;
        }
        $this->precargar($clase, [$id]);

        return $this->memo[$clase][$id] ?? null;
    }
}
