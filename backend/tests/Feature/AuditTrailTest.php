<?php

namespace Tests\Feature;

use App\Models\Adjustment;
use App\Models\AdjustmentReason;
use App\Models\Alert;
use App\Models\BaseUnit;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\DailyAssignment;
use App\Models\FarmLot;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\OutputType;
use App\Models\PackagingUnit;
use App\Models\PerformanceSettings;
use App\Models\Product;
use App\Models\ProductOutput;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\RecipeProduct;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\TaskCatalog;
use App\Models\TaskDailyLog;
use App\Models\TaskDeduction;
use App\Models\TaskSchedule;
use App\Models\TechnicalOrder;
use App\Models\TechnicalOrderProduct;
use App\Models\TechnicalRecipe;
use App\Models\User;
use App\Models\Worker;
use App\Support\Auditoria\AuditoriaDeLaPeticion;
use Database\Seeders\AdjustmentReasonSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use OwenIt\Auditing\Contracts\Auditable;
use Tests\TestCase;

/**
 * Auditoría completa y legible.
 *
 * Pedido del cliente (7-oct-2026): que la auditoría diga, para cada movimiento,
 * QUIÉN, CUÁNDO, QUÉ (producto, cantidad, documento) y CÓMO, en palabras:
 * "ajustó el producto X agregando la cantidad N, por Juan Camilo", "creó la
 * compra PUR-xxx con los productos …".
 *
 * Lo que se midió en la copia de producción y que estas pruebas fijan:
 *
 *  1. Los ajustes salían crudos: entidad "adjustment", `reason_id: a261…`,
 *     `quantity_mode: delta`, `Tipo: entry`, resumen = "adjustment".
 *  2. Se veían UUIDs (por ejemplo `company_id` en las compras).
 *  3. El resumen se armaba con el documento de HOY: un registro viejo mostraba
 *     los productos actuales y uno eliminado quedaba en "Compra", sin nada.
 *     Además el encabezado se guarda ANTES que sus líneas, así que la foto
 *     tomada en el evento `created` del encabezado no tenía productos.
 *  4. Faltaban módulos por auditar (categorías, recetas, órdenes técnicas,
 *     rendimiento, alertas, liquidación…).
 *  5. Ediciones sin cambio real ensuciaban el registro.
 *
 * Y lo que no se puede romper: los registros que ya existen (sin foto) se
 * siguen mostrando, y la lista no hace una consulta por registro.
 */
class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private const UUID = '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i';

    /** @var array<string, mixed> */
    private array $f;

    protected function setUp(): void
    {
        parent::setUp();

        // Ningún documento de prueba puede caer en un periodo ya cerrado.
        config([
            'inventory.closed_period_until' => '2020-01-01',
            'adjustments.closed_period_until' => '2020-01-01',
        ]);

        $this->seed(RolesSeeder::class);
        $this->seed(AdjustmentReasonSeeder::class);

        // El catálogo se arma ANTES de encender la auditoría: así la lista solo
        // trae lo que hace cada prueba.
        $this->f = $this->catalogo();

        $this->auditarComoEnProduccion();
    }

    // ------------------------------------------------------------------
    // 1. Ajustes en palabras
    // ------------------------------------------------------------------

    public function test_un_ajuste_aprobado_se_lee_en_palabras(): void
    {
        $id = $this->como($this->f['bodeguero'])->postJson('/api/adjustments', [
            'type' => 'entry',
            'reason_id' => $this->f['conteo']->id,
            'notes' => 'Sobrante encontrado en el conteo',
            'product_id' => $this->f['sportak']->id,
            'brand_id' => $this->f['sinMarca']->id,
            'unit' => 'L',
            'quantity_mode' => 'delta',
            'quantity' => 0.3,
            'destination_location_id' => $this->f['bodega']->id,
            'unit_price' => 1000,
            'movement_date' => now()->toDateString(),
        ])->assertStatus(201)->json('data.id');

        $this->como($this->f['admin'])->putJson("/api/adjustments/{$id}/approve")->assertOk();

        $filas = $this->auditoria('adjustment');
        $this->assertCount(2, $filas);

        [$aprobacion, $solicitud] = $filas; // la más reciente primero

        $this->assertSame('Ajuste', $solicitud['modelLabel']);
        $this->assertSame('Creó', $solicitud['eventLabel']);
        $this->assertSame('Juan Camilo Rojas', $solicitud['userName']);
        $this->assertStringContainsString('Ajuste de entrada', $solicitud['summary']);
        $this->assertStringContainsString('SPORTAK (Sin Marca)', $solicitud['summary']);
        $this->assertStringContainsString('+0,3 L', $solicitud['summary']);
        $this->assertStringContainsString('BODEGA PRINCIPAL', $solicitud['summary']);
        $this->assertStringContainsString('Motivo: Conteo físico', $solicitud['summary']);
        $this->assertStringContainsString('Estado: Pendiente', $solicitud['summary']);

        $this->assertSame('Editó', $aprobacion['eventLabel']);
        $this->assertSame('Administrador AgriFlor', $aprobacion['userName']);
        $this->assertStringContainsString('Estado: Aprobada', $aprobacion['summary']);
        $this->assertStringContainsString('Aprobó: Administrador AgriFlor', $aprobacion['summary']);
        $this->assertContains(['label' => 'Estado', 'from' => 'Pendiente', 'to' => 'Aprobada'], $aprobacion['changes']);
        $this->assertContains('Administrador AgriFlor', array_column($aprobacion['changes'], 'to'));

        foreach ($filas as $fila) {
            $texto = $this->textoVisible($fila);
            $this->assertSinUuids($texto);
            foreach (['adjustment', 'entry', 'delta', 'quantity_base', 'approved_by', 'approved_at', 'reason_id', 'notes', 'quantity_mode'] as $crudo) {
                $this->assertDoesNotMatchRegularExpression('/\b' . $crudo . '\b/', $texto, "Se coló el texto crudo '{$crudo}'.");
            }
        }

        $this->assertContains(
            ['value' => 'adjustment', 'label' => 'Ajuste'],
            $this->como($this->f['auditor'])->getJson('/api/audits/filters')->assertOk()->json('data.models')
        );
    }

    // ------------------------------------------------------------------
    // 2 y 3. Documentos tal como eran, sin UUIDs
    // ------------------------------------------------------------------

    public function test_la_compra_muestra_sus_productos_tal_como_eran_en_cada_momento(): void
    {
        $id = $this->crearCompra([
            $this->itemCompra($this->f['sportak'], $this->f['galon'], 2, 50000),
            $this->itemCompra($this->f['kendo'], $this->f['bulto'], 1, 120000),
        ]);

        // Se edita: SPORTAK pasa de 2 a 3 galones y KENDO se quita.
        $this->como($this->f['admin'])->putJson("/api/purchases/{$id}", [
            'items' => [$this->itemCompra($this->f['sportak'], $this->f['galon'], 3, 50000)],
        ])->assertOk();

        [$edicion, $creacion] = $this->auditoria('purchase');

        // Lo que se creó: los DOS productos con las cantidades de ese momento.
        $this->assertStringContainsString('Compra PUR-AUD-001', $creacion['summary']);
        $this->assertStringContainsString('Proveedor: AGROINSUMOS DEL CAMPO', $creacion['summary']);
        $this->assertStringContainsString('Empresa: AGRIFLOR S.A.S', $creacion['summary']);
        $this->assertCount(2, $creacion['lines']);
        $this->assertStringContainsString('2 Galón SPORTAK (Sin Marca)', implode(' | ', $creacion['lines']));
        $this->assertStringContainsString('1 Bulto KENDO (Bayer)', implode(' | ', $creacion['lines']));
        $this->assertStringNotContainsString('3 Galón', $this->textoVisible($creacion));

        // La edición: lo que quedó y qué cambió línea por línea.
        $this->assertCount(1, $edicion['lines']);
        $this->assertStringContainsString('3 Galón SPORTAK (Sin Marca)', $edicion['lines'][0]);
        $cambios = json_encode($edicion['lineChanges'], JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('KENDO', $cambios, 'Tiene que decir que se quitó KENDO.');
        $this->assertStringContainsString('2 Galón SPORTAK', $cambios);
        $this->assertStringContainsString('3 Galón SPORTAK', $cambios);

        $this->assertSinUuids($this->textoVisible($creacion) . $this->textoVisible($edicion));
    }

    public function test_la_salida_creada_trae_sus_productos_y_no_deja_una_edicion_suelta_del_costo(): void
    {
        $this->stockInicial($this->f['sportak'], 'L', 50);

        $id = $this->crearSalida(5);

        $filas = $this->auditoria('output');

        // El encabezado se guarda antes que sus líneas y el costo se calcula
        // después: todo es UNA acción del usuario y queda en UN registro.
        $this->assertCount(1, $filas, 'Crear una salida no puede dejar además un "Editó Salida: Costo total".');
        $fila = $filas[0];

        $numero = ProductOutput::findOrFail($id)->output_number;
        $this->assertSame('Creó', $fila['eventLabel']);
        $this->assertStringContainsString("Salida {$numero}", $fila['summary']);
        $this->assertSame(['5 L SPORTAK (Sin Marca)'], $fila['lines']);
        $this->assertStringContainsString('BODEGA PRINCIPAL → FINCA LA ESPERANZA', $fila['summary']);
        $this->assertStringContainsString('Tipo: Traslado', $fila['summary']);
        $this->assertSinUuids($this->textoVisible($fila));
    }

    public function test_una_salida_eliminada_conserva_su_resumen(): void
    {
        $this->stockInicial($this->f['sportak'], 'L', 50);
        $id = $this->crearSalida(5);
        $numero = ProductOutput::findOrFail($id)->output_number;

        $this->como($this->f['admin'])->deleteJson("/api/product-outputs/{$id}")->assertOk();

        $eliminacion = collect($this->auditoria('output'))->firstWhere('event', 'deleted');

        $this->assertNotNull($eliminacion);
        $this->assertSame('Eliminó', $eliminacion['eventLabel']);
        $this->assertStringContainsString("Salida {$numero}", $eliminacion['summary']);
        $this->assertSame(['5 L SPORTAK (Sin Marca)'], $eliminacion['lines'], 'Lo eliminado tiene que seguir diciendo qué productos llevaba.');
        $this->assertStringContainsString('BODEGA PRINCIPAL → FINCA LA ESPERANZA', $eliminacion['summary']);
    }

    /**
     * Eliminar una compra borra sus líneas ANTES que el encabezado (y con un
     * borrado en lote, sin eventos): la foto tiene que tomarse antes de eso.
     */
    public function test_una_compra_eliminada_conserva_sus_productos_y_el_motivo(): void
    {
        $id = $this->crearCompra([$this->itemCompra($this->f['sportak'], $this->f['galon'], 2, 50000)]);

        $this->como($this->f['admin'])->postJson("/api/purchases/{$id}/reverse", [
            'motivo' => 'Se registró dos veces por error de digitación',
        ])->assertOk();

        $filas = collect($this->auditoria('purchase'));
        $eliminacion = $filas->firstWhere('event', 'deleted');
        $motivo = $filas->firstWhere('event', 'updated');

        $this->assertStringContainsString('Compra PUR-AUD-001', $eliminacion['summary']);
        $this->assertSame(['2 Galón SPORTAK (Sin Marca) = 8 L · $50.000 c/u'], $eliminacion['lines']);

        $this->assertNotNull($motivo, 'El motivo queda como edición de la compra.');
        $this->assertStringContainsString('Se registró dos veces', json_encode($motivo['changes'], JSON_UNESCAPED_UNICODE));
        $this->assertSinUuids($this->textoVisible($eliminacion) . $this->textoVisible($motivo));
    }

    public function test_la_recepcion_muestra_lo_recibido_y_el_documento_de_origen(): void
    {
        $compra = $this->crearCompra([$this->itemCompra($this->f['sportak'], $this->f['galon'], 2, 50000)]);

        $this->como($this->f['bodeguero'])->postJson('/api/receptions/direct-reception', [
            'source_id' => $compra,
            'source_type' => 'purchase',
            'reception_date' => now()->toDateString(),
            'received_by' => $this->f['bodeguero']->id,
            'items' => [[
                'product_id' => $this->f['sportak']->id,
                'brand_id' => $this->f['sinMarca']->id,
                'quantity_received' => 8,
                'condition' => 'good',
            ]],
        ])->assertStatus(201);

        $filas = $this->auditoria('reception');

        // Crear la recepción, calcular lo esperado y recibir el lote es UNA acción.
        $this->assertCount(1, $filas);
        $fila = $filas[0];

        $this->assertSame('Creó', $fila['eventLabel']);
        $this->assertStringContainsString('Recepción REC-', $fila['summary']);
        $this->assertStringContainsString('Compra PUR-AUD-001', $fila['summary']);
        $this->assertStringContainsString('Destino: BODEGA PRINCIPAL', $fila['summary']);
        $this->assertStringContainsString('Estado: Completada', $fila['summary']);
        $this->assertSame(['8 de 8 L SPORTAK (Sin Marca)'], $fila['lines']);
        $this->assertSinUuids($this->textoVisible($fila));

        // Y la compra dice que quedó recibida, con sus productos.
        $recibida = collect($this->auditoria('purchase'))->firstWhere('event', 'updated');
        $this->assertContains(['label' => 'Estado', 'from' => 'Pendiente', 'to' => 'Recibida'], $recibida['changes']);
        $this->assertSinUuids($this->textoVisible($recibida));
    }

    // ------------------------------------------------------------------
    // 4. Módulos que faltaban
    // ------------------------------------------------------------------

    public function test_los_modulos_que_faltaban_quedan_auditados_con_nombre_legible(): void
    {
        $admin = $this->f['admin'];

        $categoria = Category::create(['name' => 'Fungicidas', 'slug' => 'fungicidas', 'status' => 'active']);
        BaseUnit::create(['name' => 'Gramos', 'symbol' => 'g', 'status' => 'active']);
        PackagingUnit::create(['name' => 'Caneca', 'base_quantity' => 20, 'base_unit' => 'L']);
        OutputType::create(['name' => 'Donación', 'code' => 'donation', 'requires_lots' => false, 'status' => 'active']);
        $lote = FarmLot::create(['location_id' => $this->f['finca']->id, 'name' => 'Lote 7', 'status' => 'active']);

        $receta = TechnicalRecipe::create(['name' => 'Mezcla foliar', 'category' => 'fertilization', 'status' => 'active', 'created_by' => $admin->id]);
        RecipeProduct::create(['recipe_id' => $receta->id, 'product_id' => $this->f['sportak']->id, 'brand_id' => $this->f['sinMarca']->id, 'quantity' => 1.5, 'unit' => 'L']);

        $orden = TechnicalOrder::create(['order_number' => 'OT-0001', 'scheduled_date' => now()->toDateString(), 'status' => 'draft', 'responsible_agronomist' => $admin->id]);
        TechnicalOrderProduct::create(['technical_order_id' => $orden->id, 'product_id' => $this->f['sportak']->id, 'brand_id' => $this->f['sinMarca']->id, 'quantity' => 12, 'unit' => 'L']);
        $orden->farms()->attach($this->f['finca']->id);

        $tarea = TaskCatalog::create(['code' => 'TSK-001', 'name' => 'Poda de formación', 'unit' => 'arbol', 'reference_yield' => 20, 'active' => true, 'created_by' => $admin->id]);
        $programacion = TaskSchedule::create([
            'code' => 'PROG-0001', 'task_catalog_id' => $tarea->id, 'location_id' => $this->f['finca']->id, 'lot_id' => $lote->id,
            'total_quantity' => 100, 'start_date' => now()->toDateString(), 'end_date' => now()->addDays(4)->toDateString(),
            'working_days' => 5, 'planned_persons' => 2, 'budgeted_jornales' => 10, 'accumulated_pct' => 0,
            'real_jornales' => 0, 'status' => 'planificada', 'created_by' => $admin->id,
        ]);
        TaskDailyLog::create([
            'task_schedule_id' => $programacion->id, 'log_date' => now()->toDateString(), 'registered_at' => now(),
            'mode' => 'programada', 'advance_pct_today' => 12.5, 'accumulated_snapshot_pct' => 12.5, 'persons_today' => 3,
            'created_by' => $admin->id,
        ]);
        // El registro único lo crea la migración: aquí se cambia un umbral.
        PerformanceSettings::current()->update(['global_k_factor' => 4]);
        Alert::create(['type' => 'warning', 'title' => 'Stock bajo de SPORTAK', 'location_id' => $this->f['bodega']->id, 'product_id' => $this->f['sportak']->id, 'severity' => 'high', 'status' => 'active']);

        $trabajador = Worker::create(['worker_code' => 'W-001', 'full_name' => 'Juan Camilo Pérez', 'document_id' => '1020304050', 'hire_date' => '2026-01-15', 'status' => 'active']);
        $labor = Task::create(['code' => 'T-01', 'name' => 'Guadaña', 'duration_hours' => 8, 'daily_cost' => 60000, 'status' => 'active']);
        TaskDeduction::create(['task_id' => $labor->id, 'deduction_name' => 'Salud', 'percentage' => 4, 'is_active' => true]);
        DailyAssignment::create([
            'date' => now()->toDateString(), 'worker_id' => $trabajador->id, 'task_id' => $labor->id,
            'worker_code' => 'W-001', 'task_code' => 'T-01', 'gross_amount' => 60000, 'total_deductions' => 2400,
            'net_amount' => 57600, 'processed_by' => $admin->id, 'processed_at' => now(),
        ]);

        $esperado = [
            'category' => ['Categoría', 'Fungicidas'],
            'base_unit' => ['Unidad base', 'Gramos'],
            'packaging_unit' => ['Unidad de empaque', 'Caneca'],
            'output_type' => ['Tipo de salida', 'Donación'],
            'farm_lot' => ['Lote de finca', 'Lote 7'],
            'technical_recipe' => ['Receta técnica', 'Mezcla foliar'],
            'technical_order' => ['Orden técnica', 'OT-0001'],
            'task_catalog' => ['Tarea de rendimiento', 'Poda de formación'],
            'task_schedule' => ['Programación de tarea', 'PROG-0001'],
            'task_daily_log' => ['Registro diario de avance', 'PROG-0001'],
            'performance_settings' => ['Configuración de rendimiento', 'Umbrales'],
            'alert' => ['Alerta', 'Stock bajo de SPORTAK'],
            'worker' => ['Trabajador', 'Juan Camilo Pérez'],
            'task' => ['Tarea de liquidación', 'Guadaña'],
            'task_deduction' => ['Deducción de tarea', 'Salud'],
            'daily_assignment' => ['Asignación diaria', 'Juan Camilo Pérez'],
        ];

        $filtros = collect($this->como($this->f['auditor'])->getJson('/api/audits/filters')->assertOk()->json('data.models'))
            ->pluck('label', 'value');

        foreach ($esperado as $alias => [$etiqueta, $nombre]) {
            $this->assertSame($etiqueta, $filtros[$alias] ?? null, "Falta '{$alias}' en el filtro de entidades.");

            $filas = $this->auditoria($alias);
            $this->assertNotEmpty($filas, "'{$alias}' no quedó en la auditoría.");
            $this->assertSame($etiqueta, $filas[0]['modelLabel']);
            $this->assertStringContainsString($nombre, $filas[0]['summary'], "El resumen de '{$alias}' no dice de qué se trata.");
            $this->assertSinUuids($this->textoVisible($filas[0]));
        }

        // Los documentos con líneas traen sus productos aunque se hayan
        // guardado DESPUÉS del encabezado (aquí sin transacción).
        $this->assertSame(['1,5 L SPORTAK (Sin Marca)'], $this->auditoria('technical_recipe')[0]['lines']);
        $ordenFila = $this->auditoria('technical_order')[0];
        $this->assertSame(['12 L SPORTAK (Sin Marca)'], $ordenFila['lines']);
        $this->assertStringContainsString('FINCA LA ESPERANZA', $ordenFila['summary']);

        $this->assertStringContainsString('Poda de formación', $this->auditoria('task_daily_log')[0]['summary']);
        $this->assertStringContainsString('Guadaña', $this->auditoria('daily_assignment')[0]['summary']);
    }

    public function test_todo_modelo_pedido_es_auditable_y_tiene_alias(): void
    {
        $pedidos = [
            Category::class, BaseUnit::class, PackagingUnit::class, OutputType::class, FarmLot::class,
            TechnicalRecipe::class, TechnicalOrder::class, TaskCatalog::class, TaskSchedule::class,
            TaskDailyLog::class, PerformanceSettings::class, Alert::class, Worker::class, Task::class,
            DailyAssignment::class, TaskDeduction::class,
        ];

        foreach ($pedidos as $clase) {
            $this->assertTrue(is_subclass_of($clase, Auditable::class), "{$clase} no es auditable.");
            $this->assertNotSame($clase, (new $clase())->getMorphClass());
        }
    }

    /**
     * Recetas y órdenes técnicas por el API (antes daban 500: las dos tablas
     * solo tienen `created_at`). Al editarlas, la auditoría dice qué productos
     * cambiaron y a qué fincas quedó asignada la orden.
     */
    public function test_recetas_y_ordenes_tecnicas_por_el_api_quedan_auditadas_con_sus_productos_y_fincas(): void
    {
        $otraFinca = Location::create(['name' => 'FINCA EL ROBLE', 'type' => 'farm', 'status' => 'active']);
        $producto = fn (float $litros) => [['product_id' => $this->f['sportak']->id, 'brand_id' => $this->f['sinMarca']->id, 'quantity' => $litros, 'unit' => 'L']];

        $receta = $this->como($this->f['admin'])->postJson('/api/technical-recipes', [
            'name' => 'Mezcla foliar', 'category' => 'fertilization', 'products' => $producto(2),
        ])->assertCreated()->json('data.id');
        $this->como($this->f['admin'])->putJson("/api/technical-recipes/{$receta}", [
            'name' => 'Mezcla foliar', 'category' => 'fertilization', 'products' => $producto(5),
        ])->assertOk();

        $orden = $this->como($this->f['admin'])->postJson('/api/technical-orders', [
            'order_number' => 'OT-AUD-001', 'scheduled_date' => now()->toDateString(),
            'farm_ids' => [$this->f['finca']->id], 'responsible_agronomist' => $this->f['admin']->id,
            'products' => $producto(12),
        ])->assertCreated()->json('data.id');
        // Solo cambian las fincas y los productos: el encabezado queda igual.
        $this->como($this->f['admin'])->putJson("/api/technical-orders/{$orden}", [
            'farm_ids' => [$otraFinca->id], 'products' => $producto(10),
        ])->assertOk();

        [$recetaEditada, $recetaCreada] = $this->auditoria('technical_recipe');
        $this->assertSame(['2 L SPORTAK (Sin Marca)'], $recetaCreada['lines']);
        $this->assertSame(['5 L SPORTAK (Sin Marca)'], $recetaEditada['lines']);
        $this->assertContains(['label' => 'Producto cambiado', 'from' => '2 L SPORTAK (Sin Marca)', 'to' => '5 L SPORTAK (Sin Marca)'], $recetaEditada['lineChanges']);

        [$ordenEditada, $ordenCreada] = $this->auditoria('technical_order');
        $this->assertSame(['12 L SPORTAK (Sin Marca)'], $ordenCreada['lines']);
        $this->assertStringContainsString('Fincas: FINCA LA ESPERANZA', $ordenCreada['summary']);
        $this->assertSame('Editó', $ordenEditada['eventLabel']);
        $this->assertStringContainsString('Fincas: FINCA EL ROBLE', $ordenEditada['summary']);
        $this->assertContains(['label' => 'Fincas', 'from' => 'FINCA LA ESPERANZA', 'to' => 'FINCA EL ROBLE'], $ordenEditada['changes']);
        $this->assertContains(['label' => 'Producto cambiado', 'from' => '12 L SPORTAK (Sin Marca)', 'to' => '10 L SPORTAK (Sin Marca)'], $ordenEditada['lineChanges']);
        $this->assertSinUuids($this->textoVisible($ordenEditada) . $this->textoVisible($recetaEditada));
    }

    // ------------------------------------------------------------------
    // 5. Ediciones sin cambio real
    // ------------------------------------------------------------------

    public function test_una_edicion_sin_cambio_real_no_se_guarda(): void
    {
        // Leído de nuevo, como en una petición real (la instancia del catálogo
        // es anterior a encender la auditoría).
        $proveedor = Supplier::findOrFail($this->f['proveedor']->id);
        $proveedor->update(['address' => "Calle 10 # 20-30\r\nBodega 2"]);
        $antes = $this->filasDe($proveedor);

        // Mismo texto: cambia el salto de línea (CRLF → LF) y sobra un espacio.
        $proveedor->update(['address' => "Calle 10 # 20-30\nBodega 2 "]);
        // Misma fecha con otra hora: una columna DATE no guarda la hora.
        $salida = $this->salidaDirecta();
        $salida->update(['output_date' => now()->toDateString() . ' 05:00:00']);

        $this->assertSame($antes, $this->filasDe($proveedor), 'Un cambio de espacios o saltos de línea no es una edición.');
        $this->assertSame(0, DB::table('audits')->where('auditable_type', 'output')->where('event', 'updated')->count());

        // Un cambio real sí queda, y solo con lo que cambió de verdad.
        $proveedor->update(['address' => "Calle 10 # 20-30\r\nBodega 2", 'phone' => '3001234567']);
        $ultima = DB::table('audits')->where('auditable_type', 'supplier')->where('event', 'updated')->orderByDesc('id')->first();
        $this->assertSame(['phone'], array_keys(json_decode($ultima->new_values, true)));
    }

    /**
     * Un NIT, un documento o un código de lote son texto aunque solo tengan
     * dígitos: "0123" → "123" es un cambio real y tiene que quedar.
     */
    public function test_un_nit_o_documento_con_cero_inicial_si_es_un_cambio(): void
    {
        $proveedor = Supplier::findOrFail($this->f['proveedor']->id);
        $proveedor->update(['nit' => '0123']);
        $antes = $this->filasDe($proveedor);
        $proveedor->update(['nit' => '123']);
        $this->assertSame($antes + 1, $this->filasDe($proveedor), 'Cambiar el NIT de 0123 a 123 no dejó registro.');

        // Creado aparte: crear y editar en la misma acción se integra al
        // registro de creación, y aquí se quiere ver la edición sola.
        $trabajador = Worker::withoutAuditing(fn () => Worker::create(['worker_code' => 'W-007', 'full_name' => 'Ana Ruiz', 'document_id' => '01020304', 'hire_date' => '2026-01-15', 'status' => 'active']));
        $trabajador->update(['document_id' => '1020304']);
        $this->assertSame(1, DB::table('audits')->where('auditable_type', 'worker')->where('event', 'updated')->count());

        $this->assertContains(['label' => 'NIT', 'from' => '0123', 'to' => '123'], $this->auditoria('supplier')[0]['changes']);
        $this->assertContains(['label' => 'Documento de identidad', 'from' => '01020304', 'to' => '1020304'], $this->auditoria('worker')[0]['changes']);
    }

    public function test_un_registro_viejo_sin_cambio_real_no_se_muestra(): void
    {
        $compra = $this->compraDirecta();

        $this->registroViejo('purchase', $compra->id, 'updated', ['observations' => "Llega el lunes\r\npor la tarde"], ['observations' => "Llega el lunes\npor la tarde "]);
        $this->registroViejo('purchase', $compra->id, 'updated', ['subtotal' => '5.00'], ['subtotal' => 5]);

        $filas = $this->auditoria('purchase');

        $this->assertSame([], array_values(array_filter($filas, fn ($f) => $f['event'] === 'updated')));
        $this->assertStringNotContainsString('Llega el lunes', json_encode($filas, JSON_UNESCAPED_UNICODE));
    }

    // ------------------------------------------------------------------
    // Lo que no se puede romper
    // ------------------------------------------------------------------

    /**
     * Los 4.374 registros de producción no tienen foto: se siguen mostrando
     * con el comportamiento de siempre, pero sin UUIDs y en español.
     */
    public function test_los_registros_viejos_sin_foto_se_siguen_mostrando(): void
    {
        $ajuste = Adjustment::withoutAuditing(fn () => Adjustment::create([
            'adjustment_number' => 'AJU-20261005-0007',
            'type' => 'entry',
            'reason_id' => $this->f['conteo']->id,
            'product_id' => $this->f['sportak']->id,
            'brand_id' => $this->f['sinMarca']->id,
            'unit' => 'L',
            'quantity_mode' => 'delta',
            'quantity' => 0.3,
            'quantity_base' => 0.3,
            'destination_location_id' => $this->f['bodega']->id,
            'movement_date' => '2026-10-05',
            'status' => 'approved',
            'responsible_user' => $this->f['bodeguero']->id,
            'approved_by' => $this->f['admin']->id,
            'approved_at' => '2026-10-05 19:47:06',
        ]));

        $this->registroViejo('adjustment', $ajuste->id, 'created', [], [
            'adjustment_number' => 'AJU-20261005-0007', 'type' => 'entry', 'reason_id' => $this->f['conteo']->id,
            'notes' => 'conteo', 'product_id' => $this->f['sportak']->id, 'brand_id' => $this->f['sinMarca']->id,
            'unit' => 'L', 'quantity_mode' => 'delta', 'quantity' => 0.3, 'destination_location_id' => $this->f['bodega']->id,
            'movement_date' => '2026-10-05', 'responsible_user' => $this->f['bodeguero']->id, 'status' => 'pending',
        ]);
        $this->registroViejo('adjustment', $ajuste->id, 'updated',
            ['quantity_base' => null, 'status' => 'pending', 'approved_by' => null, 'approved_at' => null],
            ['quantity_base' => 0.3, 'status' => 'approved', 'approved_by' => $this->f['admin']->id, 'approved_at' => '2026-10-05 19:47:06']
        );

        $compra = Purchase::withoutAuditing(fn () => $this->compraDirecta());
        $this->registroViejo('purchase', $compra->id, 'created', [], [
            'order_number' => $compra->order_number, 'company_id' => $this->f['empresa']->id, 'supplier_id' => $this->f['proveedor']->id,
            'destination_location_id' => $this->f['bodega']->id, 'status' => 'pending', 'total' => '100000.00',
        ]);
        // Una referencia a algo que ya no existe: no puede asomar el UUID.
        $this->registroViejo('product', $this->f['sportak']->id, 'updated', ['brand_id' => '9a9a9a9a-0000-4000-8000-000000000000'], ['brand_id' => $this->f['sinMarca']->id]);

        $respuesta = $this->como($this->f['auditor'])->getJson('/api/audits?per_page=50')->assertOk();
        $filas = $respuesta->json('data');

        $this->assertCount(4, $filas);
        foreach ($filas as $fila) {
            $this->assertSinUuids($this->textoVisible($fila));
        }

        $porTipo = collect($filas)->groupBy('model');
        $creado = $porTipo['adjustment']->firstWhere('event', 'created');
        $aprobado = $porTipo['adjustment']->firstWhere('event', 'updated');

        $this->assertStringContainsString('Ajuste de entrada', $creado['summary']);
        $this->assertStringContainsString('SPORTAK (Sin Marca)', $creado['summary']);
        $this->assertStringContainsString('+0,3 L', $creado['summary']);
        $this->assertContains(['label' => 'Motivo', 'from' => null, 'to' => 'Conteo físico'], $creado['changes']);
        $this->assertContains(['label' => 'Tipo', 'from' => null, 'to' => 'Entrada'], $creado['changes']);
        $this->assertContains(['label' => 'Estado', 'from' => 'Pendiente', 'to' => 'Aprobada'], $aprobado['changes']);
        $this->assertContains(['label' => 'Aprobó', 'from' => null, 'to' => 'Administrador AgriFlor'], $aprobado['changes']);

        $compraFila = $porTipo['purchase']->first();
        $this->assertContains(['label' => 'Empresa', 'from' => null, 'to' => 'AGRIFLOR S.A.S'], $compraFila['changes']);

        $producto = $porTipo['product']->first();
        $this->assertContains(['label' => 'Marca', 'from' => '(registro eliminado)', 'to' => 'Sin Marca'], $producto['changes']);
    }

    /**
     * Un perfil eliminado: sus ediciones viejas tienen que seguir diciendo de
     * cuál se trataba (el nombre sale de los otros registros del perfil).
     */
    public function test_la_edicion_de_un_perfil_ya_eliminado_dice_cual_era(): void
    {
        $id = (string) \Illuminate\Support\Str::uuid();
        $this->registroViejo('role', $id, 'created', [], ['name' => 'temporal', 'display_name' => 'Perfil temporal']);
        $this->registroViejo('role', $id, 'updated', ['permisos_quitados' => 'Ver compras'], ['permisos_agregados' => 'Crear compras']);
        $this->registroViejo('role', $id, 'deleted', ['name' => 'temporal', 'display_name' => 'Perfil temporal'], []);

        $edicion = collect($this->auditoria('role'))->firstWhere('event', 'updated');

        $this->assertSame('Perfil: Perfil temporal', $edicion['summary']);
    }

    /**
     * Ningún fallo de la auditoría puede tumbar la operación: si la foto
     * revienta (al tomarla antes, al escribirla o al completarla después del
     * commit), la compra se crea, se edita y se elimina igual, y el fallo
     * queda reportado en el log. El registro de auditoría se guarda sin foto.
     */
    public function test_si_la_foto_de_auditoria_falla_la_operacion_sigue(): void
    {
        $this->app->singleton(AuditoriaDeLaPeticion::class, fn () => new class extends AuditoriaDeLaPeticion {
            protected function foto(Model $documento): array
            {
                throw new \RuntimeException('Foto rota a propósito');
            }
        });
        Exceptions::fake();

        $id = $this->crearCompra([$this->itemCompra($this->f['sportak'], $this->f['galon'], 2, 50000)]);
        $this->assertSame('2.00', PurchaseItem::where('purchase_id', $id)->value('quantity'));

        $this->como($this->f['admin'])->putJson("/api/purchases/{$id}", [
            'items' => [$this->itemCompra($this->f['sportak'], $this->f['galon'], 3, 50000)],
        ])->assertOk();
        $this->assertSame('3.00', PurchaseItem::where('purchase_id', $id)->value('quantity'));

        $this->como($this->f['admin'])->postJson("/api/purchases/{$id}/reverse", ['motivo' => 'Prueba de una foto que falla'])->assertOk();
        $this->assertNull(Purchase::find($id));

        Exceptions::assertReported(fn (\RuntimeException $e) => $e->getMessage() === 'Foto rota a propósito');

        // La auditoría sigue: quién y qué hizo, sin foto; y la pantalla la lee.
        $eventos = DB::table('audits')->where('auditable_type', 'purchase')->where('auditable_id', $id)->orderBy('id')->pluck('event')->all();
        $this->assertSame(['created', 'updated', 'updated', 'deleted'], $eventos);
        $this->assertSame(0, DB::table('audits')->whereNotNull('snapshot')->count());
        $this->assertCount(4, $this->auditoria('purchase'));
    }

    public function test_la_lista_no_hace_una_consulta_por_registro(): void
    {
        // Todo lo hace el mismo usuario: así las dos listas cargan lo mismo
        // (el autor de cada registro se trae en lote, una sola consulta).
        $this->actingAs($this->f['admin'], 'api');

        $this->compraDirecta();
        $this->compraDirecta();
        $pocas = $this->consultasDeLaLista();

        for ($i = 0; $i < 6; $i++) {
            $this->compraDirecta();
        }
        $muchas = $this->consultasDeLaLista();

        $this->assertSame($pocas, $muchas, 'La lista de auditoría hace una consulta por registro (N+1).');
    }

    /**
     * Auditar no puede multiplicar las consultas de una escritura masiva:
     * procesar 50 asignaciones de liquidación (un documento sin líneas) debe
     * costar, por fila, el registro de auditoría y la foto, sin rehacerla
     * después ni volver a buscar los mismos nombres.
     */
    public function test_procesar_un_lote_de_asignaciones_no_dispara_consultas_de_mas(): void
    {
        $tarea = Task::create(['code' => 'T-LOTE', 'name' => 'Guadaña', 'duration_hours' => 8, 'daily_cost' => 60000, 'status' => 'active']);
        TaskDeduction::create(['task_id' => $tarea->id, 'deduction_name' => 'Salud', 'percentage' => 4, 'is_active' => true]);
        $filas = [];
        for ($i = 1; $i <= 50; $i++) {
            Worker::create(['worker_code' => "W-{$i}", 'full_name' => "Trabajador {$i}", 'document_id' => (string) (1000 + $i), 'hire_date' => '2026-01-15', 'status' => 'active']);
            $filas[] = ['worker_code' => "W-{$i}", 'task_code' => 'T-LOTE'];
        }

        $procesar = function (string $fecha) use ($filas): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->como($this->f['admin'])->postJson('/api/daily-assignments/process', ['date' => $fecha, 'assignments' => $filas])
                ->assertOk()->assertJsonPath('data.processed', 50);
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };

        DailyAssignment::disableAuditing();
        $sinAuditoria = $procesar('2026-10-01');
        DailyAssignment::enableAuditing();
        $conAuditoria = $procesar('2026-10-02');

        $this->assertSame(50, DB::table('audits')->where('auditable_type', 'daily_assignment')->count());
        $this->assertLessThanOrEqual(
            $sinAuditoria + 2 * 50 + 5,
            $conAuditoria,
            "Auditar 50 asignaciones costó " . ($conAuditoria - $sinAuditoria) . " consultas de más (sin auditoría: {$sinAuditoria}, con: {$conAuditoria})."
        );
    }

    /**
     * El logo de una empresa vive como base64 (hasta 512 KB): copiarlo a la
     * auditoría reventaba el guardado (la columna es TEXT) y no le dice nada a
     * un humano. Queda constancia de que se cambió, sin el contenido.
     */
    public function test_cambiar_el_logo_de_una_empresa_no_copia_la_imagen_a_la_auditoria(): void
    {
        Company::findOrFail($this->f['empresa']->id)->update(['logo_mime' => 'image/png', 'logo_base64' => str_repeat('A', 120000)]);

        $fila = DB::table('audits')->where('auditable_type', 'company')->orderByDesc('id')->first();

        $this->assertNotNull($fila);
        $this->assertStringNotContainsString('AAAAAAAAAA', $fila->new_values);

        $visible = $this->auditoria('company')[0];
        $this->assertContains('Logo', array_column($visible['changes'], 'label'));
    }

    // ------------------------------------------------------------------

    /** @return array<int, array<string, mixed>> filas de la auditoría (la más reciente primero) */
    private function auditoria(string $modelo): array
    {
        return $this->como($this->f['auditor'])
            ->getJson('/api/audits?per_page=100&model=' . $modelo)
            ->assertOk()
            ->json('data');
    }

    /** Todo lo que la pantalla le muestra a una persona de un registro. */
    private function textoVisible(array $fila): string
    {
        $cambios = array_merge($fila['changes'] ?? [], $fila['lineChanges'] ?? []);

        return implode("\n", array_merge(
            [$fila['modelLabel'], $fila['eventLabel'], $fila['userName'], $fila['summary'], (string) ($fila['document'] ?? '')],
            array_map(fn ($d) => ($d['label'] ?? '') . ': ' . $d['value'], $fila['details'] ?? []),
            $fila['lines'] ?? [],
            array_map(fn ($c) => $c['label'] . ': ' . ($c['from'] ?? '') . ' → ' . ($c['to'] ?? ''), $cambios),
        ));
    }

    private function assertSinUuids(string $texto): void
    {
        $this->assertDoesNotMatchRegularExpression(self::UUID, $texto, "Se ve un UUID:\n{$texto}");
    }

    private function filasDe(Model $modelo): int
    {
        return DB::table('audits')->where('auditable_type', $modelo->getMorphClass())->where('auditable_id', $modelo->getKey())->count();
    }

    private function consultasDeLaLista(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->como($this->f['auditor'])->getJson('/api/audits?per_page=100')->assertOk();
        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $total;
    }

    /** Un registro escrito como los que ya hay en producción: sin foto. */
    private function registroViejo(string $tipo, string $id, string $evento, array $antes, array $despues): void
    {
        DB::table('audits')->insert([
            'user_type' => 'user',
            'user_id' => $this->f['admin']->id,
            'event' => $evento,
            'auditable_type' => $tipo,
            'auditable_id' => $id,
            'old_values' => json_encode($antes),
            'new_values' => json_encode($despues),
            'url' => 'https://agriflor-api.duckdns.org/api/prueba',
            'ip_address' => '200.189.27.42',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function crearCompra(array $items): string
    {
        return $this->como($this->f['admin'])->postJson('/api/purchases', [
            'order_number' => 'PUR-AUD-001',
            'company_id' => $this->f['empresa']->id,
            'supplier_id' => $this->f['proveedor']->id,
            'destination_location_id' => $this->f['bodega']->id,
            'purchase_date' => now()->toDateString(),
            'items' => $items,
        ])->assertStatus(201)->json('data.id');
    }

    private function itemCompra(Product $producto, PackagingUnit $empaque, float $cantidad, float $precio): array
    {
        return [
            'product_id' => $producto->id,
            'brand_id' => $producto->brand_id,
            'packaging_unit_id' => $empaque->id,
            'quantity' => $cantidad,
            'unit_price' => $precio,
        ];
    }

    private function crearSalida(float $litros): string
    {
        return $this->como($this->f['admin'])->postJson('/api/product-outputs', [
            'company_id' => $this->f['empresa']->id,
            'output_type_id' => $this->f['traslado']->id,
            'output_date' => now()->toDateString(),
            'origin_location_id' => $this->f['bodega']->id,
            'destination_location_id' => $this->f['finca']->id,
            'products' => [[
                'product_id' => $this->f['sportak']->id,
                'brand_id' => $this->f['sinMarca']->id,
                'quantity_requested' => $litros,
                'quantity_delivered' => $litros,
                'unit' => 'L',
            ]],
        ])->assertStatus(201)->json('data.id');
    }

    /** Compra hecha por código (sin pasar por el API), con una línea. */
    private function compraDirecta(): Purchase
    {
        $compra = Purchase::create([
            'order_number' => 'PUR-DIR-' . uniqid(),
            'company_id' => $this->f['empresa']->id,
            'supplier_id' => $this->f['proveedor']->id,
            'destination_location_id' => $this->f['bodega']->id,
            'purchase_date' => now()->toDateString(),
            'status' => 'pending',
            'subtotal' => 100000,
            'tax' => 0,
            'total' => 100000,
            'created_by' => $this->f['admin']->id,
        ]);

        PurchaseItem::create([
            'purchase_id' => $compra->id,
            'product_id' => $this->f['sportak']->id,
            'brand_id' => $this->f['sinMarca']->id,
            'packaging_unit_id' => $this->f['galon']->id,
            'quantity' => 2,
            'quantity_in_base_units' => 8,
            'unit_price' => 50000,
            'subtotal' => 100000,
            'total' => 100000,
        ]);

        return $compra;
    }

    private function salidaDirecta(): ProductOutput
    {
        return ProductOutput::create([
            'output_number' => ProductOutput::generateOutputNumber(),
            'company_id' => $this->f['empresa']->id,
            'output_type_id' => $this->f['traslado']->id,
            'output_date' => now()->toDateString(),
            'origin_location_id' => $this->f['bodega']->id,
            'destination_location_id' => $this->f['finca']->id,
            'status' => 'pending',
            'responsible_user' => $this->f['admin']->id,
        ]);
    }

    private function stockInicial(Product $producto, string $unidad, float $cantidad): void
    {
        Inventory::create([
            'product_id' => $producto->id,
            'brand_id' => $producto->brand_id,
            'location_id' => $this->f['bodega']->id,
            'batch_number' => 'LOTE-INICIAL',
            'quantity' => $cantidad,
            'unit' => $unidad,
            'unit_price' => 1000,
            'total_value' => $cantidad * 1000,
            'status' => 'good',
        ]);

        InventoryMovement::create([
            'type' => 'entry',
            'product_id' => $producto->id,
            'brand_id' => $producto->brand_id,
            'location_id' => $this->f['bodega']->id,
            'quantity' => $cantidad,
            'unit' => $unidad,
            'movement_date' => now()->subMonth()->toDateString(),
            'unit_price' => 1000,
            'total_price' => $cantidad * 1000,
            'responsible_user' => $this->f['admin']->id,
            'observations' => 'Stock inicial (prueba)',
        ]);
    }

    /**
     * config/audit.php no audita desde consola, y las pruebas corren en consola.
     * En producción la petición es HTTP y sí se audita. Los modelos ya cargados
     * se vuelven a iniciar para que tomen el observador de auditoría.
     */
    private function auditarComoEnProduccion(): void
    {
        config(['audit.console' => true]);
        Model::clearBootedModels();
    }

    private function como(User $usuario): self
    {
        return $this->actingAs($usuario->fresh(), 'api');
    }

    private function usuario(string $perfil, string $nombre): User
    {
        return User::create([
            'name' => $nombre,
            'email' => "{$perfil}_" . uniqid() . '@agriflor.com',
            'password' => bcrypt('password'),
            'role' => $perfil,
            'role_id' => Role::where('name', $perfil)->value('id'),
            'status' => 'active',
        ]);
    }

    /** @return array<string, mixed> */
    private function catalogo(): array
    {
        $admin = $this->usuario('admin', 'Administrador AgriFlor');

        foreach ([['L', 'Litros'], ['kg', 'Kilogramos']] as [$simbolo, $nombre]) {
            BaseUnit::firstOrCreate(['symbol' => $simbolo], ['name' => $nombre, 'status' => 'active']);
        }

        $sinMarca = Brand::create(['name' => 'Sin Marca', 'status' => 'active']);
        $bayer = Brand::create(['name' => 'Bayer', 'status' => 'active']);

        return [
            'admin' => $admin,
            // Solicitar ajustes (`request_adjustment`) nace sin asignar a ningún
            // perfil: hoy solo lo hace el de acceso total. Lo que importa aquí
            // es que la auditoría diga QUIÉN, no qué perfil tiene.
            'bodeguero' => $this->usuario('admin', 'Juan Camilo Rojas'),
            'auditor' => $this->usuario('auditor', 'Auditora Externa'),
            'sinMarca' => $sinMarca,
            'sportak' => Product::create([
                'name' => 'SPORTAK', 'brand_id' => $sinMarca->id, 'base_unit' => 'L', 'iva' => 0,
                'active_ingredient' => 'Procloraz', 'min_stock' => 0, 'status' => 'active', 'created_by' => $admin->id,
            ]),
            'kendo' => Product::create([
                'name' => 'KENDO', 'brand_id' => $bayer->id, 'base_unit' => 'kg', 'iva' => 0,
                'active_ingredient' => 'Lambda', 'min_stock' => 0, 'status' => 'active', 'created_by' => $admin->id,
            ]),
            'bodega' => Location::create(['name' => 'BODEGA PRINCIPAL', 'type' => 'warehouse', 'status' => 'active']),
            'finca' => Location::create(['name' => 'FINCA LA ESPERANZA', 'type' => 'farm', 'status' => 'active', 'total_workers' => 8]),
            'proveedor' => Supplier::create(['name' => 'AGROINSUMOS DEL CAMPO', 'nit' => '900123456', 'status' => 'active']),
            'empresa' => Company::create(['name' => 'AGRIFLOR S.A.S', 'nit' => '901000000', 'status' => 'active']),
            'galon' => PackagingUnit::create(['name' => 'Galón', 'base_quantity' => 4, 'base_unit' => 'L']),
            'bulto' => PackagingUnit::create(['name' => 'Bulto', 'base_quantity' => 50, 'base_unit' => 'kg']),
            'traslado' => OutputType::firstOrCreate(['code' => 'transfer'], ['name' => 'Traslado', 'requires_lots' => false, 'status' => 'active']),
            'conteo' => AdjustmentReason::where('code', 'conteo_fisico')->firstOrFail(),
        ];
    }
}
