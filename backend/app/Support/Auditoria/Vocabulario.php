<?php

namespace App\Support\Auditoria;

use App\Models\AdjustmentReason;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\FarmLot;
use App\Models\Location;
use App\Models\OutputType;
use App\Models\PackagingUnit;
use App\Models\Product;
use App\Models\ProductOutput;
use App\Models\Purchase;
use App\Models\Reception;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\TaskCatalog;
use App\Models\TaskCategory;
use App\Models\TaskSchedule;
use App\Models\TechnicalOrder;
use App\Models\TechnicalRecipe;
use App\Models\User;
use App\Models\Worker;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;

/**
 * Cómo se dice en español cada cosa que guarda la auditoría.
 *
 * Una sola fuente para las dos puntas:
 *  - al ESCRIBIR, {@see FotoDeAuditoria} arma la foto del documento con estas
 *    etiquetas y formatos;
 *  - al LEER, {@see \App\Http\Controllers\Api\AuditController} traduce campo a
 *    campo los registros (también los viejos, que no tienen foto).
 *
 * Regla de oro de la pantalla: una persona nunca ve un UUID ni un nombre de
 * columna. Todo `*_id` / `*_by` se resuelve a un nombre (REFERENCIAS) o se
 * oculta (OCULTOS); si lo referenciado ya no existe se dice "(registro
 * eliminado)".
 */
final class Vocabulario
{
    /**
     * La base guarda en UTC; la pantalla pinta la hora del registro con la
     * hora local del navegador (Colombia). Las fechas con hora que van DENTRO
     * del texto (p. ej. "Fecha de aprobación") se pasan a la misma zona para
     * que no aparezcan cinco horas corridas frente a la columna "Fecha".
     */
    public const ZONA_HORARIA = 'America/Bogota';

    public const ELIMINADO = '(registro eliminado)';

    public const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /**
     * Alias del morph map → nombre de la entidad. Es lo que sale en el filtro
     * "Entidad" de la pantalla, así que todo modelo auditable tiene que estar.
     */
    public const ENTIDADES = [
        'product' => 'Producto',
        'purchase' => 'Compra',
        'output' => 'Salida',
        'reception' => 'Recepción',
        'adjustment' => 'Ajuste',
        'brand' => 'Marca',
        'location' => 'Ubicación',
        'supplier' => 'Proveedor',
        'application' => 'Aplicación',
        'user' => 'Usuario',
        'role' => 'Perfil',
        'company' => 'Empresa',
        'category' => 'Categoría',
        'base_unit' => 'Unidad base',
        'packaging_unit' => 'Unidad de empaque',
        'output_type' => 'Tipo de salida',
        'farm_lot' => 'Lote de finca',
        'technical_recipe' => 'Receta técnica',
        'technical_order' => 'Orden técnica',
        'task_catalog' => 'Tarea de rendimiento',
        'task_schedule' => 'Programación de tarea',
        'task_daily_log' => 'Registro diario de avance',
        'performance_settings' => 'Configuración de rendimiento',
        'alert' => 'Alerta',
        'worker' => 'Trabajador',
        'task' => 'Tarea de liquidación',
        'daily_assignment' => 'Asignación diaria',
        'task_deduction' => 'Deducción de tarea',
    ];

    /** Columna → etiqueta. */
    public const CAMPOS = [
        // Documentos
        'order_number' => 'N° de orden',
        'reception_number' => 'N° de recepción',
        'output_number' => 'N° de salida',
        'adjustment_number' => 'N° de ajuste',
        'supplier_id' => 'Proveedor',
        'company_id' => 'Empresa',
        'origin_location_id' => 'Origen',
        'destination_location_id' => 'Destino',
        'location_id' => 'Ubicación',
        'farm_id' => 'Finca',
        'purchase_date' => 'Fecha de compra',
        'output_date' => 'Fecha de salida',
        'shipment_date' => 'Fecha de envío',
        'expected_delivery' => 'Entrega esperada',
        'reception_date' => 'Fecha de recepción',
        'expiration_date' => 'Vencimiento',
        'movement_date' => 'Fecha del movimiento',
        'scheduled_date' => 'Fecha programada',
        'output_type_id' => 'Tipo de salida',
        'technical_order_id' => 'Orden técnica',
        'source_type' => 'Tipo de documento de origen',
        'product_id' => 'Producto',
        'brand_id' => 'Marca',
        'packaging_unit_id' => 'Empaque',
        'category_id' => 'Categoría',
        'reason_id' => 'Motivo',
        'recipe_id' => 'Receta',
        'base_unit' => 'Unidad',
        'unit' => 'Unidad',
        'quantity' => 'Cantidad',
        'quantity_mode' => 'Modo de cantidad',
        'quantity_base' => 'Cantidad aplicada (unidad base)',
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
        'estimated_cost' => 'Costo estimado',
        'total_expected' => 'Total esperado',
        'total_received' => 'Total recibido',
        'completion_percentage' => '% completado',
        'batch_number' => 'Lote',
        'rejection_reason' => 'Motivo del rechazo',
        'status' => 'Estado',
        'condition' => 'Condición',
        'observations' => 'Observaciones',
        'notes' => 'Notas',
        // Personas
        'responsible_user' => 'Responsable',
        'responsible_user_id' => 'Responsable',
        'responsible_agronomist' => 'Agrónomo responsable',
        'received_by' => 'Recibido por',
        'created_by' => 'Creado por',
        'user_id' => 'Usuario',
        'approved_by' => 'Aprobado por',
        'applied_by' => 'Aplicado por',
        'resolved_by' => 'Resuelto por',
        'processed_by' => 'Procesado por',
        'approved_at' => 'Fecha de aprobación',
        'applied_at' => 'Fecha de aplicación',
        'resolved_at' => 'Fecha de resolución',
        'processed_at' => 'Fecha de proceso',
        'completed_at' => 'Fecha de cierre',
        'last_used' => 'Último uso',
        // Datos maestros
        'name' => 'Nombre',
        'nit' => 'NIT',
        'municipality' => 'Municipio',
        'address' => 'Dirección',
        'city' => 'Ciudad',
        'phone' => 'Teléfono',
        'email' => 'Correo',
        'type' => 'Tipo',
        'code' => 'Código',
        'product_code' => 'Código',
        'symbol' => 'Símbolo',
        'active_ingredient' => 'Principio activo',
        'description' => 'Descripción',
        'payment_terms' => 'Términos de pago',
        'iva' => 'IVA',
        'min_stock' => 'Stock mínimo',
        'base_quantity' => 'Cantidad por empaque',
        'requires_lots' => 'Exige lotes de cultivo',
        'total_workers' => 'Trabajadores',
        'coordinates_lat' => 'Latitud',
        'coordinates_lng' => 'Longitud',
        'legal_rep' => 'Representante legal',
        'tax_regime' => 'Régimen tributario',
        'ciiu' => 'CIIU',
        'template' => 'Plantilla de documentos',
        'is_default' => 'Empresa por defecto',
        'logo' => 'Logo',
        'role' => 'Rol',
        // Lotes de finca
        'area' => 'Área',
        'area_unit' => 'Unidad de área',
        'total_trees' => 'Árboles',
        'area_hectares' => 'Hectáreas',
        'total_cubic_meters' => 'Metros cúbicos',
        'total_linear_meters' => 'Metros lineales',
        // Recetas y órdenes técnicas
        'category' => 'Categoría',
        'application_instructions' => 'Instrucciones de aplicación',
        'safety_notes' => 'Notas de seguridad',
        'usage_count' => 'Veces usada',
        'application_rate' => 'Dosis',
        // Rendimiento
        'task_catalog_id' => 'Tarea',
        'task_schedule_id' => 'Programación',
        'lot_id' => 'Lote',
        'reference_yield' => 'Rendimiento de referencia',
        'reference_yield_used' => 'Rendimiento usado',
        'active' => 'Activa',
        'override_sobrepaso_pct' => 'Umbral propio de sobrepaso',
        'override_alto_pct' => 'Umbral propio alto',
        'override_medio_pct' => 'Umbral propio medio',
        'override_k_factor' => 'Factor K propio',
        'total_quantity' => 'Cantidad total',
        'start_date' => 'Inicio',
        'end_date' => 'Fin',
        'working_days' => 'Días hábiles',
        'planned_persons' => 'Personas planeadas',
        'external_farm_workers' => 'Trabajadores de otras fincas',
        'third_party_workers' => 'Trabajadores de terceros',
        'budgeted_jornales' => 'Jornales presupuestados',
        'suggested_persons' => 'Personas sugeridas',
        'suggested_working_days' => 'Días sugeridos',
        'suggested_end_date' => 'Fin sugerido',
        'accumulated_pct' => 'Avance acumulado',
        'real_jornales' => 'Jornales reales',
        'final_performance_pct' => 'Rendimiento final',
        'final_level' => 'Nivel final',
        'cancellation_reason' => 'Motivo de cancelación',
        'is_ad_hoc' => 'Tarea no programada',
        'ad_hoc_motive' => 'Motivo (no programada)',
        'log_date' => 'Fecha del avance',
        'mode' => 'Modo',
        'advance_pct_today' => 'Avance del día',
        'accumulated_snapshot_pct' => 'Avance acumulado',
        'persons_today' => 'Personas',
        'suspicious' => 'Marcado como sospechoso',
        'suspicious_confirmed' => 'Sospecha confirmada',
        'global_sobrepaso_pct' => 'Umbral de sobrepaso',
        'global_alto_pct' => 'Umbral alto',
        'global_medio_pct' => 'Umbral medio',
        'global_k_factor' => 'Factor K',
        // Alertas
        'title' => 'Título',
        'severity' => 'Severidad',
        // Liquidación
        'worker_code' => 'Código del trabajador',
        'full_name' => 'Nombre',
        'document_id' => 'Documento de identidad',
        'hire_date' => 'Fecha de ingreso',
        'duration_hours' => 'Duración (horas)',
        'daily_cost' => 'Costo por día',
        'date' => 'Fecha',
        'worker_id' => 'Trabajador',
        'task_id' => 'Tarea',
        'task_code' => 'Código de la tarea',
        'gross_amount' => 'Valor bruto',
        'total_deductions' => 'Descuentos',
        'net_amount' => 'Valor neto',
        'deduction_name' => 'Deducción',
        'percentage' => 'Porcentaje',
        'is_active' => 'Activa',
        // Perfiles (Administración → Perfiles)
        'display_name' => 'Nombre del perfil',
        'location_scoped' => 'Solo ve las fincas a su cargo (inventario, salidas, recepciones, ajustes)',
        'schedule_scoped' => 'Solo ve las programaciones de las fincas a su cargo',
        'has_full_access' => 'Acceso total',
        'permisos_agregados' => 'Permisos agregados',
        'permisos_quitados' => 'Permisos quitados',
    ];

    /** Cuando la misma columna significa otra cosa en una entidad ("alias.columna"). */
    public const CAMPOS_POR_ENTIDAD = [
        'adjustment.responsible_user' => 'Solicitó',
        'adjustment.approved_by' => 'Aprobó',
        'adjustment.unit_price' => 'Costo unitario',
        'product.base_unit' => 'Unidad base',
        'packaging_unit.base_unit' => 'Unidad base',
        'task_schedule.location_id' => 'Finca',
        'task_schedule.created_by' => 'Programó',
        'task_daily_log.created_by' => 'Registró',
        'farm_lot.location_id' => 'Finca',
        'alert.location_id' => 'Ubicación',
        'technical_recipe.created_by' => 'Creada por',
        'task_catalog.active' => 'Activa',
        'daily_assignment.processed_by' => 'Registró',
    ];

    /** Columnas que no le dicen nada a una persona (o que ya se cuentan de otra forma). */
    public const OCULTOS = [
        'id', 'created_at', 'updated_at', 'deleted_at', 'received_at', 'registered_at',
        'quantity_in_base_units', 'iva_percentage', 'tax_amount',
        'source_id', 'password', 'remember_token', 'role_id', 'slug',
        'logo_base64', 'logo_mime', 'deductions_detail',
    ];

    /**
     * Columna (o "alias.columna") que guarda el ID de otro registro → modelo
     * donde buscar su nombre.
     */
    public const REFERENCIAS = [
        'supplier_id' => Supplier::class,
        'company_id' => Company::class,
        'origin_location_id' => Location::class,
        'destination_location_id' => Location::class,
        'location_id' => Location::class,
        'farm_id' => Location::class,
        'output_type_id' => OutputType::class,
        'product_id' => Product::class,
        'brand_id' => Brand::class,
        'packaging_unit_id' => PackagingUnit::class,
        'category_id' => Category::class,
        'task_catalog.category_id' => TaskCategory::class,
        'reason_id' => AdjustmentReason::class,
        'technical_order_id' => TechnicalOrder::class,
        'recipe_id' => TechnicalRecipe::class,
        'task_catalog_id' => TaskCatalog::class,
        'lot_id' => FarmLot::class,
        'farm_lot_id' => FarmLot::class,
        'task_schedule_id' => TaskSchedule::class,
        'worker_id' => Worker::class,
        'task_id' => Task::class,
        'product_output_id' => ProductOutput::class,
        'responsible_user' => User::class,
        'responsible_user_id' => User::class,
        'responsible_agronomist' => User::class,
        'received_by' => User::class,
        'created_by' => User::class,
        'user_id' => User::class,
        'approved_by' => User::class,
        'applied_by' => User::class,
        'resolved_by' => User::class,
        'processed_by' => User::class,
        'changed_by' => User::class,
    ];

    /** Columna con el nombre visible de cada modelo (por defecto, `name`). */
    public const COLUMNA_NOMBRE = [
        Worker::class => 'full_name',
        TechnicalOrder::class => 'order_number',
        TaskSchedule::class => 'code',
        ProductOutput::class => 'output_number',
        Purchase::class => 'order_number',
        Reception::class => 'reception_number',
        Role::class => 'display_name',
    ];

    /** Valores con traducción ("alias.columna" manda sobre "columna"). */
    public const VALORES = [
        'status' => [
            'pending' => 'Pendiente', 'completed' => 'Completada', 'in_transit' => 'En tránsito',
            'partial' => 'Parcial', 'approved' => 'Aprobada', 'cancelled' => 'Cancelada',
            'canceled' => 'Cancelada', 'rejected' => 'Rechazada', 'active' => 'Activo',
            'inactive' => 'Inactivo', 'draft' => 'Borrador', 'received' => 'Recibida',
            'in_progress' => 'En proceso', 'finished' => 'Finalizada', 'closed' => 'Cerrada',
            'ordered' => 'Ordenada', 'resolved' => 'Resuelta', 'dismissed' => 'Descartada',
            'planificada' => 'Planificada', 'en_progreso' => 'En progreso',
            'completada' => 'Completada', 'cancelada' => 'Cancelada',
        ],
        'condition' => ['good' => 'Buen estado', 'damaged' => 'Dañado', 'expired' => 'Vencido'],
        'source_type' => ['purchase' => 'Compra', 'output' => 'Salida'],
        'quantity_mode' => ['delta' => 'Mover una cantidad (+/-)', 'absolute' => 'Fijar el saldo del lote'],
        'severity' => ['high' => 'Alta', 'medium' => 'Media', 'low' => 'Baja'],
        'mode' => ['programada' => 'Programada', 'ad_hoc' => 'No programada', 'retroactiva' => 'Con fecha pasada'],
        'final_level' => ['sobrepaso' => 'Sobrepaso', 'alto' => 'Alto', 'medio' => 'Medio', 'bajo' => 'Bajo'],
        'type' => ['warehouse' => 'Bodega', 'farm' => 'Finca'],
        'location.type' => ['warehouse' => 'Bodega', 'farm' => 'Finca'],
        'adjustment.type' => ['entry' => 'Entrada', 'exit' => 'Salida', 'transfer' => 'Traslado'],
        'alert.type' => ['error' => 'Error', 'warning' => 'Advertencia', 'info' => 'Información', 'success' => 'Resuelta'],
        'task_catalog.unit' => ['arbol' => 'Árbol', 'hectarea' => 'Hectárea', 'm2' => 'Metro cuadrado', 'metro' => 'Metro'],
        'technical_recipe.category' => [
            'fertilization' => 'Fertilización', 'pest_control' => 'Control de plagas',
            'disease_control' => 'Control de enfermedades', 'weed_control' => 'Control de malezas',
            'other' => 'Otra',
        ],
    ];

    /** Casillas sí/no. */
    public const SI_NO = [
        'location_scoped', 'schedule_scoped', 'has_full_access', 'requires_lots', 'active',
        'is_active', 'is_default', 'suspicious', 'suspicious_confirmed', 'is_ad_hoc',
    ];

    public const DINERO = [
        'total', 'subtotal', 'tax', 'total_cost', 'unit_price', 'estimated_cost',
        'daily_cost', 'gross_amount', 'total_deductions', 'net_amount',
    ];

    public const PORCENTAJES = [
        'completion_percentage', 'accumulated_pct', 'advance_pct_today', 'accumulated_snapshot_pct',
        'final_performance_pct', 'percentage', 'iva', 'global_sobrepaso_pct', 'global_alto_pct',
        'global_medio_pct', 'override_sobrepaso_pct', 'override_alto_pct', 'override_medio_pct',
    ];

    /**
     * Columnas numéricas que se muestran sin ceros de sobra (5.00 → 5). Es una
     * lista y no "todo lo que parezca número" para que un nombre, un NIT o un
     * lote que sean solo dígitos se muestren tal cual. Las que empiezan por
     * `quantity` también cuentan. Las coordenadas van con todos sus decimales.
     */
    public const NUMEROS = [
        'total_expected', 'total_received', 'total_quantity', 'reference_yield', 'reference_yield_used',
        'base_quantity', 'area', 'area_hectares', 'total_trees', 'total_cubic_meters', 'total_linear_meters',
        'min_stock', 'duration_hours', 'global_k_factor', 'override_k_factor', 'total_workers', 'working_days',
        'planned_persons', 'external_farm_workers', 'third_party_workers', 'budgeted_jornales',
        'suggested_persons', 'suggested_working_days', 'real_jornales', 'persons_today', 'usage_count',
    ];

    public const FECHAS_CON_HORA = [
        'approved_at', 'applied_at', 'resolved_at', 'processed_at', 'completed_at', 'last_used', 'registered_at',
    ];

    public static function entidad(string $alias): string
    {
        return self::ENTIDADES[$alias] ?? ucfirst(str_replace('_', ' ', $alias));
    }

    public static function etiqueta(string $alias, string $campo): string
    {
        return self::CAMPOS_POR_ENTIDAD["{$alias}.{$campo}"]
            ?? self::CAMPOS[$campo]
            ?? ucfirst(str_replace('_', ' ', $campo));
    }

    public static function oculto(string $campo): bool
    {
        return in_array($campo, self::OCULTOS, true);
    }

    /** Modelo al que apunta la columna, o null si no es una referencia. */
    public static function referencia(string $alias, string $campo): ?string
    {
        return self::REFERENCIAS["{$alias}.{$campo}"] ?? self::REFERENCIAS[$campo] ?? null;
    }

    public static function columnaNombre(string $clase): string
    {
        return self::COLUMNA_NOMBRE[$clase] ?? 'name';
    }

    public static function esUuid(mixed $valor): bool
    {
        return is_string($valor) && preg_match(self::UUID, $valor) === 1;
    }

    /**
     * Valor crudo → texto para una persona.
     *
     * @param  array<string, string>  $nombres  ID → nombre ya conocido (la foto del registro)
     * @param  array<string, \Illuminate\Support\Collection|array>  $porClase  modelo → [ID → nombre] cargados en lote
     * @param  array<string, string>  $roles  nombre técnico del perfil → nombre visible
     */
    public static function presentar(string $alias, string $campo, mixed $valor, array $nombres = [], array $porClase = [], array $roles = []): ?string
    {
        if ($valor === null || (is_string($valor) && trim($valor) === '')) {
            return null;
        }

        if ($clase = self::referencia($alias, $campo)) {
            if (!is_scalar($valor)) {
                return null;
            }
            $id = (string) $valor;

            return $nombres[$id] ?? ($porClase[$clase][$id] ?? null) ?? (self::esUuid($id) ? self::ELIMINADO : $id);
        }

        // Un ID suelto en una columna que no conocemos no se le muestra a nadie.
        if (self::esUuid($valor)) {
            return null;
        }

        if (in_array($campo, self::SI_NO, true)) {
            return filter_var($valor, FILTER_VALIDATE_BOOLEAN) ? 'Sí' : 'No';
        }

        if ($campo === 'role' && $alias === 'user' && is_string($valor)) {
            return $roles[$valor] ?? $valor;
        }

        if (is_scalar($valor) && ($mapa = self::VALORES["{$alias}.{$campo}"] ?? self::VALORES[$campo] ?? null)) {
            return $mapa[(string) $valor] ?? (string) $valor;
        }

        if (in_array($campo, self::FECHAS_CON_HORA, true)) {
            return self::fechaHora($valor);
        }

        if (str_contains($campo, 'date') || in_array($campo, ['expected_delivery', 'date'], true)) {
            return self::fecha($valor);
        }

        if (is_numeric($valor) && in_array($campo, self::DINERO, true)) {
            return self::dinero($valor);
        }

        if (is_numeric($valor) && in_array($campo, self::PORCENTAJES, true)) {
            return self::numero($valor) . '%';
        }

        if (is_numeric($valor) && (str_starts_with($campo, 'quantity') || in_array($campo, self::NUMEROS, true))) {
            return self::numero($valor);
        }

        if (is_array($valor)) {
            return json_encode($valor, JSON_UNESCAPED_UNICODE);
        }

        if (is_bool($valor)) {
            return $valor ? 'Sí' : 'No';
        }

        return self::texto((string) $valor);
    }

    /**
     * ¿Dos valores crudos dicen lo mismo? Es lo que separa una edición real de
     * una "edición" que solo cambió espacios, saltos de línea (CRLF ↔ LF),
     * el formato de un número (5.00 ↔ 5) o la hora de una columna que solo
     * guarda la fecha.
     *
     * Solo se comparan como NÚMERO las columnas numéricas (ver esNumerico):
     * un NIT, un documento, un lote o un teléfono son texto aunque tengan solo
     * dígitos, y "0123" → "123" es un cambio real.
     */
    public static function equivalentes(mixed $antes, mixed $despues, ?string $cast = null, ?string $campo = null): bool
    {
        $vacio = fn ($v) => $v === null || (is_string($v) && trim($v) === '');

        if ($vacio($antes) && $vacio($despues)) {
            return true;
        }
        if ($vacio($antes) || $vacio($despues)) {
            return false;
        }

        $tipo = $cast ? strtolower(explode(':', $cast)[0]) : null;

        if (in_array($tipo, ['bool', 'boolean'], true) || is_bool($antes) || is_bool($despues)) {
            return filter_var($antes, FILTER_VALIDATE_BOOLEAN) === filter_var($despues, FILTER_VALIDATE_BOOLEAN);
        }

        if (in_array($tipo, ['date', 'immutable_date', 'datetime', 'immutable_datetime', 'timestamp'], true)) {
            try {
                $formato = in_array($tipo, ['date', 'immutable_date'], true) ? 'Y-m-d' : 'Y-m-d H:i:s';

                return Carbon::parse($antes)->format($formato) === Carbon::parse($despues)->format($formato);
            } catch (\Throwable $e) {
                // Si no es una fecha válida, se compara como texto.
            }
        }

        if (is_numeric($antes) && is_numeric($despues) && self::esNumerico($tipo, $campo)) {
            try {
                return BigDecimal::of((string) $antes)->isEqualTo((string) $despues);
            } catch (\Throwable $e) {
                return (float) $antes === (float) $despues;
            }
        }

        if (is_scalar($antes) && is_scalar($despues)) {
            return self::texto((string) $antes) === self::texto((string) $despues);
        }

        return json_encode($antes) === json_encode($despues);
    }

    /** ¿La columna guarda una cantidad (y no un código hecho de dígitos)? */
    public static function esNumerico(?string $tipoDeCast, ?string $campo): bool
    {
        if (in_array($tipoDeCast, ['decimal', 'float', 'double', 'real', 'int', 'integer'], true)) {
            return true;
        }
        if ($campo === null) {
            return false;
        }

        return str_starts_with($campo, 'quantity')
            || str_ends_with($campo, '_pct')
            || in_array($campo, self::NUMEROS, true)
            || in_array($campo, self::DINERO, true)
            || in_array($campo, self::PORCENTAJES, true);
    }

    /** Texto tal como se lee: sin espacios de sobra ni diferencias de salto de línea. */
    public static function texto(string $valor): string
    {
        return trim(preg_replace('/\s+/u', ' ', $valor) ?? $valor);
    }

    /** 5.00 → "5", 2.50 → "2,5", 10500 → "10.500", 0.3 → "0,3". */
    public static function numero(mixed $valor): string
    {
        $texto = number_format((float) $valor, 2, ',', '.');

        return str_contains($texto, ',') ? rtrim(rtrim($texto, '0'), ',') : $texto;
    }

    public static function dinero(mixed $valor): string
    {
        return '$' . number_format((float) $valor, 0, ',', '.');
    }

    public static function fecha(mixed $valor): string
    {
        try {
            return Carbon::parse($valor)->format('d/m/Y');
        } catch (\Throwable $e) {
            return (string) $valor;
        }
    }

    public static function fechaHora(mixed $valor): string
    {
        try {
            return Carbon::parse($valor, config('app.timezone'))->setTimezone(self::ZONA_HORARIA)->format('d/m/Y H:i');
        } catch (\Throwable $e) {
            return (string) $valor;
        }
    }
}
