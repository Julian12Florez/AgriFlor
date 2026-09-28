<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\TaskCatalog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El número de trabajadores de la finca tiene que VOLVER del API, y no puede
 * borrarse cuando se guarda otro cambio de la misma finca.
 *
 * LO QUE REPORTÓ EL CLIENTE
 * =========================
 * "Guardan los trabajadores de las fincas y no están guardando, o entran de nuevo
 * a la finca y no los ven."
 *
 * Eran DOS defectos encadenados sobre el mismo campo, `locations.total_workers`:
 *
 *   1. `LocationResource` no lo exponía. Se guardaba bien en la base, pero el API
 *      nunca lo devolvía, así que la ficha de la finca abría el campo VACÍO. De ahí
 *      el "no está guardando": sí guardaba, no se veía.
 *
 *   2. El formulario lo mandaba como `values.total_workers ?? null`. Como el campo
 *      llegaba vacío (por el defecto 1) o ni se pintaba (solo aparece cuando el tipo
 *      es finca), al guardar CUALQUIER otro cambio se enviaba null y el número
 *      guardado se BORRABA.
 *
 * MEDIDO EN LA AUDITORÍA DE PRODUCCIÓN
 * ====================================
 * 17 borrados registrados. El 24-jun-2026, entre las 16:29 y las 16:34, catorce
 * fincas perdieron el dato de una sentada: en el `old_values` se ve que lo único
 * que el usuario estaba cambiando era el responsable, y `total_workers` se fue de
 * arrastre. Ejemplo textual de esa tanda (finca Mansión):
 *
 *     old_values: {"responsible_user_id":"a1d1527a-...","total_workers":40}
 *     new_values: {"responsible_user_id":"a219ce5d-...","total_workers":null}
 *
 * Tocó volver a teclear los 14 el 11-sep. Y ese mismo día, a las 14:51, Naranjos
 * volvió a perderlo (3 -> null).
 *
 * POR QUÉ DUELE
 * =============
 * El campo no es decorativo: sin él NO SE PUEDE PROGRAMAR TAREAS con trabajadores
 * propios. `TaskScheduleController::store` responde 422 "La finca no tiene
 * registrado el número de trabajadores". El cliente iba a la ficha a arreglarlo,
 * veía el campo vacío, lo escribía, y al siguiente guardado volvía a quedar en
 * blanco: el círculo completo.
 */
class LocationWorkersPersistenceTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // 1. El dato tiene que volver del API
    // ------------------------------------------------------------------

    /** Defecto 1: el listado omitía el campo, y la ficha abría en blanco. */
    public function test_el_listado_devuelve_el_numero_de_trabajadores(): void
    {
        $admin = $this->admin();
        $finca = $this->finca('Mansión', 47);

        $fila = collect(
            $this->actingAs($admin, 'api')->getJson('/api/locations')->json('data')
        )->firstWhere('id', $finca->id);

        $this->assertArrayHasKey(
            'total_workers',
            $fila,
            'Si el API no manda el campo, el formulario lo abre vacío y el usuario cree que no se guardó.'
        );
        $this->assertSame(47, $fila['total_workers']);
    }

    /** Y la ficha individual también: es la que consulta la pantalla de la finca. */
    public function test_la_ficha_individual_devuelve_el_numero_de_trabajadores(): void
    {
        $admin = $this->admin();
        $finca = $this->finca('Melon', 28);

        $this->actingAs($admin, 'api')
            ->getJson('/api/locations/' . $finca->id)
            ->assertOk()
            ->assertJsonPath('data.total_workers', 28);
    }

    /** El filtro de fincas es el que alimenta el selector de programación de tareas. */
    public function test_el_listado_de_fincas_devuelve_el_numero_de_trabajadores(): void
    {
        $admin = $this->admin();
        $this->finca('Villa', 16);

        $fila = collect(
            $this->actingAs($admin, 'api')->getJson('/api/locations/type/farms')->json('data')
        )->firstWhere('name', 'Villa');

        $this->assertSame(
            16,
            $fila['total_workers'] ?? null,
            'La pantalla de programación muestra "(N trab.)" al lado de cada finca leyendo este campo.'
        );
    }

    // ------------------------------------------------------------------
    // 2. Guardar otra cosa NO puede borrarlo
    // ------------------------------------------------------------------

    /**
     * EL CASO DE PRODUCCIÓN: se cambia solo el responsable y el número de
     * trabajadores tiene que quedarse quieto.
     */
    public function test_cambiar_el_responsable_no_borra_el_numero_de_trabajadores(): void
    {
        $admin = $this->admin();
        $otro = $this->admin('Nuevo Responsable');
        $finca = $this->finca('Mansión', 40);

        $this->actingAs($admin, 'api')
            ->putJson('/api/locations/' . $finca->id, [
                'name' => 'Mansión',
                'type' => 'farm',
                'responsible_user_id' => $otro->id,
            ])
            ->assertOk();

        $this->assertSame(
            40,
            $finca->fresh()->total_workers,
            'Es exactamente la tanda del 24-jun: cambiaron el responsable y se perdieron 14 fincas.'
        );
    }

    /** Lo mismo con cualquier otro campo. */
    public function test_cambiar_la_direccion_no_borra_el_numero_de_trabajadores(): void
    {
        $admin = $this->admin();
        $finca = $this->finca('Breva', 8);

        $this->actingAs($admin, 'api')
            ->putJson('/api/locations/' . $finca->id, ['address' => 'vereda La Clara'])
            ->assertOk();

        $this->assertSame(8, $finca->fresh()->total_workers);
    }

    /** Y la respuesta del PUT también lo trae, para que el formulario quede al día. */
    public function test_la_respuesta_del_guardado_devuelve_el_numero_de_trabajadores(): void
    {
        $admin = $this->admin();
        $finca = $this->finca('Saladeros', 15);

        $this->actingAs($admin, 'api')
            ->putJson('/api/locations/' . $finca->id, ['municipality' => 'URRAO'])
            ->assertOk()
            ->assertJsonPath('data.total_workers', 15);
    }

    // ------------------------------------------------------------------
    // 3. Lo que SÍ debe poder hacerse
    // ------------------------------------------------------------------

    /** Cambiarlo a otro número, claro. */
    public function test_se_puede_actualizar_el_numero(): void
    {
        $admin = $this->admin();
        $finca = $this->finca('Mansión', 40);

        $this->actingAs($admin, 'api')
            ->putJson('/api/locations/' . $finca->id, ['total_workers' => 47])
            ->assertOk()
            ->assertJsonPath('data.total_workers', 47);

        $this->assertSame(47, $finca->fresh()->total_workers);
    }

    /**
     * Y borrarlo A PROPÓSITO. La corrección no puede volver el campo irreversible:
     * si el usuario lo deja en blanco de forma explícita, se limpia. La diferencia
     * es que ahora hay que MANDARLO en null, no basta con omitirlo.
     */
    public function test_se_puede_borrar_a_proposito_mandando_null(): void
    {
        $admin = $this->admin();
        $finca = $this->finca('Toronjas 2', 5);

        $this->actingAs($admin, 'api')
            ->putJson('/api/locations/' . $finca->id, ['total_workers' => null])
            ->assertOk();

        $this->assertNull($finca->fresh()->total_workers);
    }

    /**
     * LA PUERTA DE ATRÁS. El casillero solo se pinta cuando el tipo es finca, así
     * que al editar una BODEGA el formulario nunca traía el campo y el `?? null`
     * lo mandaba igual. Si mañana una bodega llegara a tener el dato, ese guardado
     * se lo llevaría por delante.
     */
    public function test_editar_una_bodega_no_toca_el_numero_de_trabajadores(): void
    {
        $admin = $this->admin();
        $bodega = Location::create([
            'name' => 'BODEGA PRINCIPAL',
            'type' => 'warehouse',
            'status' => 'active',
            'total_workers' => 3,
        ]);

        $this->actingAs($admin, 'api')
            ->putJson('/api/locations/' . $bodega->id, ['address' => 'calle 5'])
            ->assertOk();

        $this->assertSame(3, $bodega->fresh()->total_workers);
    }

    /** Se guarda desde la creación. */
    public function test_se_guarda_al_crear_la_finca(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'api')
            ->postJson('/api/locations', [
                'name' => 'Finca Nueva',
                'type' => 'farm',
                'total_workers' => 12,
            ])
            ->assertCreated()
            ->assertJsonPath('data.total_workers', 12);

        $this->assertSame(12, Location::where('name', 'Finca Nueva')->first()->total_workers);
    }

    // ------------------------------------------------------------------
    // 4. El acople que hacía daño: la programación de tareas
    // ------------------------------------------------------------------

    /**
     * POR QUÉ ESTO IMPORTA. Los trabajadores son obligatorios para programar: si el
     * campo está en blanco, la programación con gente propia se rechaza. Con el
     * defecto activo el usuario quedaba en un círculo — el sistema lo mandaba a
     * llenar la ficha, la ficha se veía vacía, la llenaba, y al siguiente guardado
     * se borraba otra vez.
     */
    public function test_sin_numero_de_trabajadores_no_se_puede_programar_con_gente_propia(): void
    {
        $admin = $this->admin();
        $finca = $this->finca('Arandanos', null);

        $this->actingAs($admin, 'api')
            ->postJson('/api/performance/schedules', $this->programacion($finca))
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    /** Con el número puesto, la misma programación pasa. */
    public function test_con_el_numero_puesto_la_programacion_pasa(): void
    {
        $admin = $this->admin();
        $finca = $this->finca('Mansión', 47);

        $this->actingAs($admin, 'api')
            ->postJson('/api/performance/schedules', $this->programacion($finca))
            ->assertCreated();
    }

    /**
     * EL CÍRCULO COMPLETO, de punta a punta: la finca tiene su número, alguien
     * cambia el responsable, y la programación tiene que seguir funcionando. Antes
     * ese guardado dejaba la finca sin trabajadores y rompía la programación.
     */
    public function test_editar_la_finca_no_deja_la_programacion_rota(): void
    {
        $admin = $this->admin();
        $otro = $this->admin('Otro Responsable');
        $finca = $this->finca('Melon', 28);

        $this->actingAs($admin, 'api')
            ->putJson('/api/locations/' . $finca->id, ['responsible_user_id' => $otro->id])
            ->assertOk();

        $this->actingAs($admin, 'api')
            ->postJson('/api/performance/schedules', $this->programacion($finca))
            ->assertCreated();
    }

    // ------------------------------------------------------------------

    private function programacion(Location $finca): array
    {
        return [
            'task_catalog_id' => $this->tarea()->id,
            'location_id' => $finca->id,
            'total_quantity' => 100,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(5)->format('Y-m-d'),
            'planned_persons' => 2,
        ];
    }

    private function tarea(): TaskCatalog
    {
        return TaskCatalog::firstOrCreate(
            ['code' => 'TSK-TRAB'],
            [
                'name' => 'Poda de prueba',
                'unit' => 'arbol',
                'reference_yield' => 20,
                'active' => true,
            ]
        );
    }

    private function finca(string $nombre, ?int $trabajadores): Location
    {
        return Location::create([
            'name' => $nombre,
            'type' => 'farm',
            'status' => 'active',
            'total_workers' => $trabajadores,
        ]);
    }

    private function admin(string $nombre = 'Administrador'): User
    {
        return User::create([
            'name' => $nombre,
            'email' => 'trab_' . uniqid() . '@agriflor.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);
    }
}
