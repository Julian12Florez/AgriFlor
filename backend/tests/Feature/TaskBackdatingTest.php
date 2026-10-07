<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\TaskCatalog;
use App\Models\TaskSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Registrar tareas con fecha pasada: hasta UN MES atrás, ni un día más.
 *
 * Pedido del cliente (7-oct-2026): "hoy queremos registrar una tarea de ayer o
 * del pasado, máximo un mes hacia atrás; en el registro diario debemos hacer lo
 * mismo".
 *
 * Cómo estaba:
 *  - Programación: la fecha de inicio no podía ser anterior a hoy.
 *  - Tarea no programada (ad-hoc): aceptaba cualquier fecha pasada, sin tope.
 *  - Registro diario: aceptaba avance de cualquier antigüedad dentro de la tarea
 *    (pasados 30 días solo avisaba) y, por backend, también fechas futuras.
 *
 * La regla ahora es una sola para los tres: desde hoy menos un mes calendario
 * (7-oct -> 7-sep) y, para el avance, nunca a futuro.
 */
class TaskBackdatingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Location $finca;
    private TaskCatalog $tarea;

    protected function setUp(): void
    {
        parent::setUp();

        // Miércoles 7 de octubre de 2026: un mes atrás es el 7 de septiembre.
        Carbon::setTestNow(Carbon::parse('2026-10-07 10:00:00'));

        $this->admin = User::create([
            'name' => 'Admin Fechas',
            'email' => 'fechas_' . uniqid() . '@agriflor.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);
        $this->finca = Location::create([
            'name' => 'Finca Fechas',
            'type' => 'farm',
            'status' => 'active',
            'total_workers' => 50,
        ]);
        $this->tarea = TaskCatalog::create([
            'code' => 'TSK-FECHA',
            'name' => 'Poda de prueba',
            'unit' => 'arbol',
            'reference_yield' => 20,
            'active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // 1. Programación
    // ------------------------------------------------------------------

    /** EL PEDIDO: hoy se registra una tarea que empezó ayer. */
    public function test_se_puede_programar_una_tarea_que_empezo_ayer(): void
    {
        $this->programar('2026-10-06', '2026-10-09')->assertCreated();
    }

    /** El borde exacto: justo un mes atrás todavía se puede. */
    public function test_se_puede_programar_con_inicio_hace_exactamente_un_mes(): void
    {
        $this->programar('2026-09-07', '2026-09-12')->assertCreated();
    }

    /** Un día más atrás ya no, y el mensaje dice desde cuándo se puede. */
    public function test_no_se_puede_programar_con_inicio_de_hace_mas_de_un_mes(): void
    {
        $respuesta = $this->programar('2026-09-06', '2026-09-12')->assertStatus(422);

        $this->assertStringContainsString('07/09/2026', json_encode($respuesta->json(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->assertSame(0, TaskSchedule::count());
    }

    /** Programar a futuro sigue igual que siempre. */
    public function test_programar_a_futuro_sigue_funcionando(): void
    {
        $this->programar('2026-10-20', '2026-10-24')->assertCreated();
    }

    /**
     * Fin de mes: el 31 de marzo menos un mes no es "31 de febrero". Se toma el
     * último día de febrero, sin desbordar a marzo.
     */
    public function test_el_mes_hacia_atras_no_se_desborda_en_fin_de_mes(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-03-31 10:00:00'));

        $this->programar('2027-02-28', '2027-03-03')->assertCreated();
        $this->programar('2027-02-27', '2027-03-03')->assertStatus(422);
    }

    // ------------------------------------------------------------------
    // 2. Tarea no programada (ad-hoc)
    // ------------------------------------------------------------------

    public function test_la_tarea_no_programada_de_ayer_se_puede_registrar(): void
    {
        $this->programar('2026-10-06', '2026-10-06', ['is_ad_hoc' => true, 'ad_hoc_motive' => 'Emergencia fitosanitaria'])
            ->assertCreated();
    }

    /** Antes no tenía tope hacia atrás; ahora obedece el mismo mes. */
    public function test_la_tarea_no_programada_tambien_tiene_el_tope_de_un_mes(): void
    {
        $this->programar('2026-08-01', '2026-08-01', ['is_ad_hoc' => true, 'ad_hoc_motive' => 'Emergencia fitosanitaria'])
            ->assertStatus(422);
    }

    // ------------------------------------------------------------------
    // 3. Registro diario (avance)
    // ------------------------------------------------------------------

    /** EL PEDIDO: hoy se registra el avance de ayer. */
    public function test_se_puede_registrar_el_avance_de_ayer(): void
    {
        $programacion = $this->programacionExistente('2026-09-20', '2026-10-20');

        $this->avance($programacion, '2026-10-06')->assertCreated();
    }

    public function test_se_puede_registrar_avance_de_hace_exactamente_un_mes(): void
    {
        $programacion = $this->programacionExistente('2026-08-15', '2026-10-20');

        $this->avance($programacion, '2026-09-07')->assertCreated();
    }

    /** Antes solo avisaba pasados 30 días; ahora no deja. */
    public function test_no_se_puede_registrar_avance_de_hace_mas_de_un_mes(): void
    {
        $programacion = $this->programacionExistente('2026-08-15', '2026-10-20');

        $respuesta = $this->avance($programacion, '2026-09-06')->assertStatus(422);

        $this->assertStringContainsString('07/09/2026', (string) $respuesta->json('message'));
        $this->assertSame(0, $programacion->logs()->count());
    }

    /** El backend aceptaba avance con fecha futura; el avance es de algo que ya pasó. */
    public function test_no_se_puede_registrar_avance_a_futuro(): void
    {
        $programacion = $this->programacionExistente('2026-09-20', '2026-10-20');

        $this->avance($programacion, '2026-10-08')->assertStatus(422);
    }

    /** Sigue sin poderse registrar avance antes de que la tarea empezara. */
    public function test_el_avance_sigue_dentro_de_las_fechas_de_la_tarea(): void
    {
        $programacion = $this->programacionExistente('2026-10-01', '2026-10-20');

        $this->avance($programacion, '2026-09-25')->assertStatus(422);
    }

    /**
     * De punta a punta, como lo hará el cliente: programa hoy una tarea que
     * empezó hace diez días y registra el avance de cada día pasado.
     */
    public function test_tarea_atrasada_con_su_avance_de_dias_pasados(): void
    {
        $id = $this->programar('2026-09-27', '2026-10-10')->assertCreated()->json('data.id');
        $programacion = TaskSchedule::findOrFail($id);

        $this->avance($programacion, '2026-09-28')->assertCreated();
        $this->avance($programacion, '2026-10-06')->assertCreated();
        $this->avance($programacion, '2026-10-07')->assertCreated();

        $this->assertSame(3, $programacion->logs()->count());
    }

    // ------------------------------------------------------------------

    private function programar(string $inicio, string $fin, array $extra = [])
    {
        return $this->actingAs($this->admin, 'api')->postJson('/api/performance/schedules', array_merge([
            'task_catalog_id' => $this->tarea->id,
            'location_id' => $this->finca->id,
            'total_quantity' => 100,
            'start_date' => $inicio,
            'end_date' => $fin,
            'planned_persons' => 2,
        ], $extra));
    }

    private function avance(TaskSchedule $programacion, string $fecha)
    {
        return $this->actingAs($this->admin, 'api')->postJson(
            "/api/performance/schedules/{$programacion->id}/logs",
            ['log_date' => $fecha, 'persons_today' => 2, 'advance_pct_today' => 5]
        );
    }

    /** Programación ya existente (creada sin pasar por la validación de fechas). */
    private function programacionExistente(string $inicio, string $fin): TaskSchedule
    {
        return TaskSchedule::create([
            'code' => 'PROG-' . str_pad((string) (TaskSchedule::count() + 1), 4, '0', STR_PAD_LEFT),
            'task_catalog_id' => $this->tarea->id,
            'location_id' => $this->finca->id,
            'total_quantity' => 100,
            'start_date' => $inicio,
            'end_date' => $fin,
            'working_days' => 20,
            'planned_persons' => 2,
            'budgeted_jornales' => 40,
            'accumulated_pct' => 0,
            'real_jornales' => 0,
            'status' => 'planificada',
            'created_by' => $this->admin->id,
        ]);
    }
}
