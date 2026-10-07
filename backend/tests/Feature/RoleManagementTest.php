<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Role;
use App\Models\TaskCatalog;
use App\Models\TaskSchedule;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Contracts\Auditable;
use Tests\TestCase;

/**
 * Perfiles y permisos, parte 2: la pantalla Administración → Perfiles.
 *
 * Pedido del cliente (7-oct-2026): "en los perfiles que yo pueda asignar
 * permisos y el menú", con la condición de que "todo el sistema, botones,
 * permisos, vistas, debe funcionar con esta configuración de perfiles".
 *
 * La parte 1 dejó el API decidiendo por permisos. Aquí se prueba que esos
 * permisos se pueden CONFIGURAR y que lo configurado manda de verdad:
 *
 *  - un perfil nuevo hace exactamente lo que se le marcó, ni más ni menos;
 *  - cambiar una casilla cambia lo que el API deja guardar y lo que sale en el menú;
 *  - un usuario puede tener un perfil creado por el administrador (antes
 *    `users.role` era una lista fija de nombres);
 *  - "solo ve su finca" es una casilla del perfil, no una lista de nombres en el código;
 *  - el Administrador no se puede dañar y el Auditor no existe para esta pantalla;
 *  - todo cambio queda en la auditoría.
 */
class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->admin = $this->usuarioCon('admin');
    }

    // ------------------------------------------------------------------
    // 1. La pantalla: listar, crear, editar, eliminar
    // ------------------------------------------------------------------

    public function test_el_administrador_lista_los_perfiles_y_el_auditor_no_aparece(): void
    {
        $this->usuarioCon('supervisor');

        $perfiles = collect($this->comoAdmin()->getJson('/api/roles')->assertOk()->json('data'))->keyBy('name');

        $this->assertEqualsCanonicalizing(
            ['admin', 'agronomist', 'farm', 'financiero', 'purchasing', 'supervisor', 'warehouse'],
            $perfiles->keys()->all()
        );
        $this->assertSame('Supervisor', $perfiles['supervisor']['displayName']);
        $this->assertSame(1, $perfiles['supervisor']['usersCount']);
        $this->assertContains('approve_output', $perfiles['supervisor']['permissions']);
        $this->assertTrue($perfiles['supervisor']['editable']);
        $this->assertFalse($perfiles['admin']['editable'], 'El Administrador no se edita.');
    }

    public function test_el_catalogo_trae_los_permisos_por_modulo_y_no_ofrece_la_auditoria(): void
    {
        $modulos = collect($this->comoAdmin()->getJson('/api/roles/catalog')->assertOk()->json('data'))->keyBy('key');

        $this->assertArrayNotHasKey('audit', $modulos->all(), 'La auditoría no es configurable.');
        $this->assertSame('Compras', $modulos['purchases']['label']);
        $this->assertSame('view_purchases', $modulos['purchases']['menuPermission']);
        $this->assertNull($modulos['performance']['menuPermission'], 'Rendimiento se ve siempre.');

        $compras = collect($modulos['purchases']['permissions'])->keyBy('name');
        $this->assertSame('create', $compras['create_purchase']['action']);
        $this->assertSame('special', $compras['reverse_purchase']['action']);
        $this->assertSame('Compras', $compras['create_purchase']['group']);
    }

    public function test_se_crea_un_perfil_nuevo_con_sus_permisos(): void
    {
        $respuesta = $this->comoAdmin()->postJson('/api/roles', [
            'display_name' => 'Jefe de Bodega',
            'description' => 'Recibe y despacha',
            'location_scoped' => true,
            'permissions' => ['view_inventory', 'view_purchases', 'create_purchase'],
        ])->assertCreated();

        $this->assertSame('jefe_de_bodega', $respuesta->json('data.name'));

        $perfil = Role::where('name', 'jefe_de_bodega')->firstOrFail();
        $this->assertSame('Jefe de Bodega', $perfil->display_name);
        $this->assertFalse($perfil->has_full_access, 'Desde la pantalla nunca se crea otro perfil de acceso total.');
        $this->assertTrue($perfil->location_scoped);
        $this->assertEqualsCanonicalizing(
            ['view_inventory', 'view_purchases', 'create_purchase'],
            $perfil->permissions()->pluck('name')->all()
        );
    }

    /** LA PRUEBA: un usuario con un perfil hecho a mano hace lo marcado y nada más. */
    public function test_un_usuario_con_perfil_nuevo_obedece_esa_configuracion(): void
    {
        $this->comoAdmin()->postJson('/api/roles', [
            'display_name' => 'Comprador Junior',
            'permissions' => ['view_purchases', 'create_purchase'],
        ])->assertCreated();

        // Antes `users.role` era una lista fija: un nombre nuevo no cabía.
        $this->comoAdmin()->postJson('/api/users', [
            'name' => 'Usuario Junior',
            'email' => 'junior@agriflor.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'comprador_junior',
        ])->assertCreated();

        $junior = User::where('email', 'junior@agriflor.com')->firstOrFail();
        $this->assertSame('comprador_junior', $junior->role);
        $this->assertSame(Role::where('name', 'comprador_junior')->value('id'), $junior->role_id);

        // Lo que se le marcó: crear compras (422 = pasó la puerta y llegó a la validación).
        $this->como($junior)->postJson('/api/purchases', [])->assertStatus(422);
        // Lo que NO se le marcó.
        $this->como($junior)->postJson('/api/product-outputs', [])->assertStatus(403);
        $this->como($junior)->postJson('/api/performance/schedules', [])->assertStatus(403);
        $this->como($junior)->getJson('/api/users')->assertStatus(403);

        // Y el menú: solo Compras.
        $yo = $this->como($junior)->getJson('/api/auth/me')->assertOk();
        $this->assertSame(['purchases'], $yo->json('data.accessibleModules'));
        $this->assertEqualsCanonicalizing(['view_purchases', 'create_purchase'], $yo->json('data.permissions'));
    }

    public function test_cambiar_las_casillas_de_un_perfil_cambia_lo_que_el_api_deja_hacer(): void
    {
        $supervisor = $this->usuarioCon('supervisor');
        $perfil = Role::where('name', 'supervisor')->firstOrFail();
        $permisos = $this->casillasDe($perfil);

        // Día uno: el Supervisor programa tareas y no crea compras.
        $this->como($supervisor)->postJson('/api/performance/schedules', [])->assertStatus(422);
        $this->como($supervisor)->postJson('/api/purchases', [])->assertStatus(403);

        $nuevos = array_values(array_diff($permisos, ['create_schedule']));
        $nuevos[] = 'create_purchase';

        $this->comoAdmin()->putJson("/api/roles/{$perfil->id}", [
            'display_name' => 'Supervisor',
            'permissions' => $nuevos,
        ])->assertOk();

        // Sin tocar al usuario ni volver a iniciar sesión: el perfil manda.
        $this->como($supervisor)->postJson('/api/performance/schedules', [])->assertStatus(403);
        $this->como($supervisor)->postJson('/api/purchases', [])->assertStatus(422);
    }

    public function test_el_menu_sigue_a_la_casilla_de_ver(): void
    {
        $operario = $this->usuarioCon('farm');
        $perfil = Role::where('name', 'farm')->firstOrFail();

        $antes = $this->como($operario)->getJson('/api/auth/me')->json('data.accessibleModules');
        $this->assertEqualsCanonicalizing(['outputs', 'reception'], $antes);

        $permisos = array_values(array_diff($this->casillasDe($perfil), ['view_outputs']));
        $permisos[] = 'view_reports';

        $this->comoAdmin()->putJson("/api/roles/{$perfil->id}", [
            'display_name' => 'Operario de Finca',
            'permissions' => $permisos,
        ])->assertOk();

        $despues = $this->como($operario)->getJson('/api/auth/me')->json('data.accessibleModules');
        $this->assertEqualsCanonicalizing(['reception', 'reports'], $despues);
    }

    public function test_el_nombre_tecnico_no_cambia_al_renombrar_un_perfil(): void
    {
        $perfil = Role::where('name', 'warehouse')->firstOrFail();

        $this->comoAdmin()->putJson("/api/roles/{$perfil->id}", [
            'display_name' => 'Almacenista',
            'permissions' => $this->casillasDe($perfil),
        ])->assertOk();

        $perfil->refresh();
        $this->assertSame('Almacenista', $perfil->display_name);
        $this->assertSame('warehouse', $perfil->name, 'El nombre técnico enlaza a los usuarios: no se toca.');
    }

    /**
     * `adjust_inventory` está reservado: existe, lo tienen tres perfiles, pero no
     * protege nada y por eso la pantalla no lo muestra. Guardar un perfil desde
     * la pantalla no puede quitárselo a quien lo tenía.
     */
    public function test_guardar_un_perfil_conserva_los_permisos_que_la_pantalla_no_muestra(): void
    {
        $perfil = Role::where('name', 'supervisor')->firstOrFail();
        $this->assertTrue($perfil->hasPermission('adjust_inventory'));

        $casillas = $this->casillasDe($perfil);
        $this->assertNotContains('adjust_inventory', $casillas);

        $this->comoAdmin()->putJson("/api/roles/{$perfil->id}", [
            'display_name' => 'Supervisor',
            'permissions' => array_values(array_diff($casillas, ['approve_output'])),
        ])->assertOk();

        $this->assertTrue($perfil->fresh()->hasPermission('adjust_inventory'));
        $this->assertFalse($perfil->fresh()->hasPermission('approve_output'));
    }

    public function test_no_se_repiten_nombres_de_perfil(): void
    {
        $this->comoAdmin()->postJson('/api/roles', ['display_name' => 'Supervisor', 'permissions' => []])
            ->assertStatus(422);

        // Dos nombres distintos que darían el mismo nombre técnico no chocan.
        $this->comoAdmin()->postJson('/api/roles', ['display_name' => 'Jefe-Bodega', 'permissions' => []])->assertCreated();
        $segundo = $this->comoAdmin()->postJson('/api/roles', ['display_name' => 'Jefe Bodega', 'permissions' => []])->assertCreated();

        $this->assertSame('jefe_bodega_2', $segundo->json('data.name'));
    }

    // ------------------------------------------------------------------
    // 2. Lo que la pantalla NO deja hacer
    // ------------------------------------------------------------------

    public function test_el_administrador_no_se_puede_editar_ni_eliminar(): void
    {
        $perfil = Role::where('name', 'admin')->firstOrFail();

        $this->comoAdmin()->putJson("/api/roles/{$perfil->id}", ['display_name' => 'Admin', 'permissions' => []])
            ->assertStatus(422);
        $this->comoAdmin()->deleteJson("/api/roles/{$perfil->id}")->assertStatus(422);

        $perfil->refresh();
        $this->assertSame('Administrador', $perfil->display_name);
        $this->assertTrue($perfil->has_full_access);
        $this->assertTrue($this->admin->fresh()->hasPermission('manage_roles'));
    }

    public function test_el_auditor_no_existe_para_la_pantalla(): void
    {
        $auditor = Role::where('name', 'auditor')->firstOrFail();

        $this->comoAdmin()->putJson("/api/roles/{$auditor->id}", ['display_name' => 'Auditor', 'permissions' => []])
            ->assertStatus(404);
        $this->comoAdmin()->deleteJson("/api/roles/{$auditor->id}")->assertStatus(404);

        // Ni se puede regalar la auditoría a otro perfil.
        $supervisor = Role::where('name', 'supervisor')->firstOrFail();
        $this->comoAdmin()->putJson("/api/roles/{$supervisor->id}", [
            'display_name' => 'Supervisor',
            'permissions' => ['view_outputs', 'audit.view'],
        ])->assertStatus(422);
        $this->assertFalse($supervisor->fresh()->hasPermission('audit.view'));
    }

    public function test_no_se_acepta_un_permiso_que_no_existe(): void
    {
        $this->comoAdmin()->postJson('/api/roles', [
            'display_name' => 'Con permiso inventado',
            'permissions' => ['view_purchases', 'borrar_todo'],
        ])->assertStatus(422);

        $this->assertNull(Role::where('display_name', 'Con permiso inventado')->first());
    }

    public function test_no_se_elimina_un_perfil_que_tiene_usuarios(): void
    {
        $perfil = Role::where('name', 'financiero')->firstOrFail();
        $usuario = $this->usuarioCon('financiero');

        $respuesta = $this->comoAdmin()->deleteJson("/api/roles/{$perfil->id}")->assertStatus(422);
        $this->assertStringContainsString('1 usuario', $respuesta->json('message'));
        $this->assertNotNull(Role::find($perfil->id));

        // Con el usuario en otro perfil, sí.
        $this->comoAdmin()->putJson("/api/users/{$usuario->id}", ['role' => 'warehouse'])->assertOk();
        $this->comoAdmin()->deleteJson("/api/roles/{$perfil->id}")->assertOk();

        $this->assertNull(Role::find($perfil->id));
        $this->assertSame(0, DB::table('role_permission')->where('role_id', $perfil->id)->count());
    }

    public function test_solo_quien_tiene_el_permiso_administra_perfiles(): void
    {
        $supervisor = $this->usuarioCon('supervisor');
        $perfil = Role::where('name', 'supervisor')->firstOrFail();

        $this->como($supervisor)->getJson('/api/roles')->assertStatus(403);
        $this->como($supervisor)->getJson('/api/roles/catalog')->assertStatus(403);
        $this->como($supervisor)->postJson('/api/roles', ['display_name' => 'X', 'permissions' => []])->assertStatus(403);
        $this->como($supervisor)->putJson("/api/roles/{$perfil->id}", ['display_name' => 'X', 'permissions' => []])->assertStatus(403);
        $this->como($supervisor)->deleteJson("/api/roles/{$perfil->id}")->assertStatus(403);

        // Y es un permiso como cualquier otro: si el administrador lo da, entra.
        $permisos = $this->casillasDe($perfil);
        $this->comoAdmin()->putJson("/api/roles/{$perfil->id}", [
            'display_name' => 'Supervisor',
            'permissions' => array_merge($permisos, ['view_admin', 'manage_roles']),
        ])->assertOk();

        $this->como($supervisor)->getJson('/api/roles')->assertOk();
    }

    // ------------------------------------------------------------------
    // 3. El registro de usuarios
    // ------------------------------------------------------------------

    public function test_el_formulario_de_usuario_recibe_los_perfiles_con_su_menu(): void
    {
        $opciones = collect(
            $this->como($this->usuarioCon('warehouse'))->getJson('/api/roles/options')->assertOk()->json('data')
        )->keyBy('name');

        $this->assertArrayNotHasKey('auditor', $opciones->all());
        $this->assertSame('Bodeguero', $opciones['warehouse']['displayName']);
        $this->assertEqualsCanonicalizing(['Inventario', 'Salidas', 'Recepción'], $opciones['warehouse']['menu']);
        $this->assertTrue($opciones['admin']['hasFullAccess']);
    }

    public function test_a_un_usuario_no_se_le_asigna_el_auditor_ni_un_perfil_inexistente(): void
    {
        $datos = [
            'name' => 'Usuario X',
            'email' => 'x@agriflor.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $this->comoAdmin()->postJson('/api/users', $datos + ['role' => 'auditor'])->assertStatus(422);
        $this->comoAdmin()->postJson('/api/users', $datos + ['role' => 'no_existe'])->assertStatus(422);
        $this->assertNull(User::where('email', 'x@agriflor.com')->first());
    }

    public function test_cambiar_el_perfil_de_un_usuario_actualiza_nombre_y_enlace(): void
    {
        $usuario = $this->usuarioCon('warehouse');
        $this->comoAdmin()->postJson('/api/roles', ['display_name' => 'Portería', 'permissions' => ['view_reception']])->assertCreated();

        $this->comoAdmin()->putJson("/api/users/{$usuario->id}", ['role' => 'porteria'])->assertOk();

        $usuario->refresh();
        $this->assertSame('porteria', $usuario->role);
        $this->assertSame(Role::where('name', 'porteria')->value('id'), $usuario->role_id);
        $this->assertSame(['reception'], $usuario->effectiveRole()->getAccessibleModules());
    }

    /**
     * `manage_users` ahora se puede dar a otros perfiles. Sin este candado, quien
     * lo reciba podría nombrarse administrador o cambiarle la clave al que ya lo es.
     */
    public function test_quien_no_tiene_acceso_total_no_crea_ni_toca_administradores(): void
    {
        $perfil = Role::where('name', 'financiero')->firstOrFail();
        $this->comoAdmin()->putJson("/api/roles/{$perfil->id}", [
            'display_name' => 'Financiero',
            'permissions' => array_merge($this->casillasDe($perfil), ['view_admin', 'manage_users']),
        ])->assertOk();
        $delegado = $this->usuarioCon('financiero');

        $nuevo = [
            'name' => 'Nuevo',
            'email' => 'nuevo@agriflor.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        // Lo normal sí lo puede hacer.
        $this->como($delegado)->postJson('/api/users', $nuevo + ['role' => 'supervisor'])->assertCreated();

        // Nombrar administradores o tocar al que ya lo es, no.
        $this->como($delegado)->postJson('/api/users', ['email' => 'otro@agriflor.com'] + $nuevo + ['role' => 'admin'])->assertStatus(403);
        $this->como($delegado)->putJson("/api/users/{$delegado->id}", ['role' => 'admin'])->assertStatus(403);
        $this->como($delegado)->putJson("/api/users/{$this->admin->id}", ['password' => 'robada123', 'password_confirmation' => 'robada123'])->assertStatus(403);
        $this->como($delegado)->patchJson("/api/users/{$this->admin->id}/status", ['status' => 'inactive'])->assertStatus(403);
        $this->como($delegado)->deleteJson("/api/users/{$this->admin->id}")->assertStatus(403);

        $this->assertSame('financiero', $delegado->fresh()->role);
        $this->assertSame('active', $this->admin->fresh()->status);
    }

    // ------------------------------------------------------------------
    // 4. "Solo ve su finca" es una casilla del perfil
    // ------------------------------------------------------------------

    public function test_el_dia_uno_las_casillas_de_finca_quedan_como_estaba_la_regla(): void
    {
        $perfiles = Role::all()->keyBy('name');

        // Inventario, salidas, recepciones y ajustes: Supervisor y Operario de Finca.
        foreach ($perfiles as $nombre => $perfil) {
            $this->assertSame(
                in_array($nombre, ['supervisor', 'farm'], true),
                $perfil->location_scoped,
                "location_scoped de {$nombre}"
            );
            // Programaciones de tareas: solo el Operario de Finca.
            $this->assertSame($nombre === 'farm', $perfil->schedule_scoped, "schedule_scoped de {$nombre}");
        }

        $this->assertFalse($this->usuarioCon('supervisor')->canViewAllLocations());
        $this->assertFalse($this->usuarioCon('farm')->canViewAllLocations());
        $this->assertTrue($this->usuarioCon('warehouse')->canViewAllLocations());
        $this->assertTrue($this->admin->canViewAllLocations());
    }

    public function test_la_casilla_solo_ve_su_finca_decide_el_alcance(): void
    {
        $perfil = Role::where('name', 'supervisor')->firstOrFail();
        $supervisor = $this->usuarioCon('supervisor');
        $permisos = $this->casillasDe($perfil);

        $this->assertFalse($this->como($supervisor)->getJson('/api/auth/me')->json('data.roleData.canViewAllLocations'));

        $this->comoAdmin()->putJson("/api/roles/{$perfil->id}", [
            'display_name' => 'Supervisor',
            'location_scoped' => false,
            'permissions' => $permisos,
        ])->assertOk();

        $this->assertTrue($supervisor->fresh()->canViewAllLocations());
        $this->assertTrue($this->como($supervisor)->getJson('/api/auth/me')->json('data.roleData.canViewAllLocations'));

        // Y un perfil nuevo queda restringido con solo marcarla.
        $this->comoAdmin()->postJson('/api/roles', [
            'display_name' => 'Mayordomo',
            'location_scoped' => true,
            'permissions' => ['view_inventory'],
        ])->assertCreated();

        $this->assertFalse($this->usuarioCon('mayordomo')->canViewAllLocations());
    }

    public function test_las_programaciones_de_otra_finca_siguen_la_casilla_del_perfil(): void
    {
        $this->programacionEnFincaAjena();
        $perfil = Role::where('name', 'farm')->firstOrFail();
        $operario = $this->usuarioCon('farm');
        $supervisor = $this->usuarioCon('supervisor');

        // Día uno, igual que antes: el Operario de Finca solo ve las de su finca; el Supervisor, todas.
        $this->assertCount(0, $this->como($operario)->getJson('/api/performance/schedules')->assertOk()->json('data'));
        $this->assertCount(1, $this->como($supervisor)->getJson('/api/performance/schedules')->assertOk()->json('data'));

        $this->comoAdmin()->putJson("/api/roles/{$perfil->id}", [
            'display_name' => 'Operario de Finca',
            'schedule_scoped' => false,
            'permissions' => $this->casillasDe($perfil),
        ])->assertOk();

        $this->assertCount(1, $this->como($operario)->getJson('/api/performance/schedules')->assertOk()->json('data'));
    }

    // ------------------------------------------------------------------
    // 5. Auditoría
    // ------------------------------------------------------------------

    public function test_los_cambios_de_un_perfil_quedan_en_la_auditoria(): void
    {
        $this->auditarComoEnProduccion();

        $perfil = Role::where('name', 'supervisor')->firstOrFail();
        $permisos = array_values(array_diff($this->casillasDe($perfil), ['approve_output']));
        $permisos[] = 'create_purchase';

        $this->comoAdmin()->putJson("/api/roles/{$perfil->id}", [
            'display_name' => 'Supervisor',
            'location_scoped' => false,
            'permissions' => $permisos,
        ])->assertOk();

        $filas = DB::table('audits')
            ->where('auditable_type', (new Role())->getMorphClass())
            ->where('auditable_id', $perfil->id)
            ->get();

        $this->assertGreaterThanOrEqual(1, $filas->count());
        $this->assertSame($this->admin->id, $filas->first()->user_id, 'Queda quién lo hizo.');

        // El auditor lo lee en palabras, no en IDs.
        $auditor = $this->usuarioCon('auditor');
        $texto = json_encode(
            $this->como($auditor)->getJson('/api/audits?model=role')->assertOk()->json('data'),
            JSON_UNESCAPED_UNICODE
        );

        $this->assertStringContainsString('Perfil: Supervisor', $texto);
        $this->assertStringContainsString('Crear compras', $texto);
        $this->assertStringContainsString('Aprobar salidas', $texto);
        $this->assertStringContainsString('Permisos agregados', $texto);
        $this->assertStringContainsString('Permisos quitados', $texto);
    }

    public function test_cambiar_el_perfil_de_un_usuario_queda_en_la_auditoria(): void
    {
        $this->auditarComoEnProduccion();
        $usuario = $this->usuarioCon('warehouse');

        $this->comoAdmin()->putJson("/api/users/{$usuario->id}", ['role' => 'purchasing'])->assertOk();

        $fila = DB::table('audits')
            ->where('auditable_type', (new User())->getMorphClass())
            ->where('auditable_id', $usuario->id)
            ->where('event', 'updated')
            ->first();

        $this->assertNotNull($fila, 'Cambiar el perfil de alguien es cambiarle el acceso: tiene que quedar.');
        $this->assertSame('warehouse', json_decode($fila->old_values, true)['role']);
        $this->assertSame('purchasing', json_decode($fila->new_values, true)['role']);
        $this->assertStringNotContainsString('password', $fila->new_values . $fila->old_values);
    }

    /**
     * El proyecto obliga a declarar un alias (morph map) para todo modelo que
     * se guarde en una columna *_type. Un modelo auditable sin alias revienta
     * con error 500 al guardarse (pasaba con Empresas).
     */
    public function test_todo_modelo_auditable_tiene_alias_para_la_auditoria(): void
    {
        $sinAlias = [];

        foreach (glob(app_path('Models/*.php')) as $archivo) {
            $clase = 'App\\Models\\' . basename($archivo, '.php');
            if (!is_subclass_of($clase, Auditable::class)) {
                continue;
            }
            try {
                (new $clase())->getMorphClass();
            } catch (\Throwable $e) {
                $sinAlias[] = $clase;
            }
        }

        $this->assertSame([], $sinAlias);
    }

    // ------------------------------------------------------------------

    /**
     * config/audit.php no audita desde consola, y las pruebas corren en consola.
     * En producción la petición es HTTP y sí se audita. Los modelos ya cargados
     * en setUp() se vuelven a iniciar para que tomen el observador de auditoría.
     */
    private function auditarComoEnProduccion(): void
    {
        config(['audit.console' => true]);
        Model::clearBootedModels();
    }

    private function comoAdmin(): self
    {
        return $this->como($this->admin);
    }

    private function como(User $usuario): self
    {
        return $this->actingAs($usuario->fresh(), 'api');
    }

    /**
     * Las casillas que la pantalla muestra marcadas para un perfil: es lo que
     * el formulario devuelve al guardar (más o menos lo que se cambie).
     *
     * @return array<int, string>
     */
    private function casillasDe(Role $perfil): array
    {
        return collect($this->comoAdmin()->getJson('/api/roles')->assertOk()->json('data'))
            ->firstWhere('id', $perfil->id)['permissions'];
    }

    private function usuarioCon(string $perfil): User
    {
        return User::create([
            'name' => "Usuario {$perfil}",
            'email' => "{$perfil}_" . uniqid() . '@agriflor.com',
            'password' => bcrypt('password'),
            'role' => $perfil,
            'role_id' => Role::where('name', $perfil)->value('id'),
            'status' => 'active',
        ]);
    }

    /** Una programación en una finca de la que NINGUNO de los usuarios de prueba es responsable. */
    private function programacionEnFincaAjena(): TaskSchedule
    {
        $finca = Location::create(['name' => 'Finca Ajena', 'type' => 'farm', 'status' => 'active', 'total_workers' => 10]);
        $tarea = TaskCatalog::create(['code' => 'TSK-PERFIL', 'name' => 'Poda', 'unit' => 'arbol', 'reference_yield' => 20, 'active' => true]);

        return TaskSchedule::create([
            'code' => 'PROG-0001',
            'task_catalog_id' => $tarea->id,
            'location_id' => $finca->id,
            'total_quantity' => 100,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'working_days' => 5,
            'planned_persons' => 2,
            'budgeted_jornales' => 10,
            'accumulated_pct' => 0,
            'real_jornales' => 0,
            'status' => 'planificada',
            'created_by' => $this->admin->id,
        ]);
    }
}
