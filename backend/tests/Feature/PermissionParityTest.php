<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RutaLaravel;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Perfiles y permisos, parte 1: al pasar de listas `role:` a permisos, NADIE
 * gana ni pierde acceso.
 *
 * Hasta el 7-oct-2026 lo que cada perfil podía guardar estaba fijo en
 * routes/api.php, en 20 listas `role:admin,supervisor,...`. Ese día cada ruta de
 * escritura pasó a pedir un permiso del catálogo (PermissionCatalog), para que
 * el administrador pueda configurar los perfiles desde una pantalla.
 *
 * El riesgo de ese cambio es que, sin querer, un perfil quede pudiendo algo que
 * no podía, o sin poder algo que hacía a diario. Esta prueba lo descarta ruta por
 * ruta: compara la FOTO DEL ANTES (tests/Fixtures/route_access_antes_de_perfiles.json,
 * sacada de `route:list` antes del cambio) con lo que deciden hoy los
 * middlewares reales de acceso, para cada una de las 239 rutas y cada uno de los
 * 8 perfiles.
 *
 * Si esta prueba falla después de un cambio deliberado de permisos por defecto,
 * lo que hay que actualizar es la foto, a conciencia y ruta por ruta.
 *
 * Las rutas creadas después (las 6 de la pantalla de Perfiles, parte 2) se
 * declaran en la misma foto con el acceso que deben tener el día uno.
 */
class PermissionParityTest extends TestCase
{
    use RefreshDatabase;

    private const PERFILES = ['admin', 'agronomist', 'auditor', 'farm', 'financiero', 'purchasing', 'supervisor', 'warehouse'];

    /**
     * Módulos del menú por perfil, medidos en producción el 7-oct-2026 antes del
     * cambio. Único ajuste: 'products' ya no existe como módulo (ninguna pantalla
     * lo usaba; Productos vive en Datos Maestros, módulo 'master').
     */
    private const MENU_ANTES = [
        'admin' => ['all'],
        'agronomist' => ['inventory', 'outputs', 'reception', 'technical'],
        'auditor' => ['audit'],
        'farm' => ['outputs', 'reception'],
        'financiero' => ['inventory', 'outputs', 'reception', 'reports'],
        'purchasing' => ['master', 'outputs', 'purchases', 'reception'],
        'supervisor' => ['inventory', 'outputs', 'reception'],
        'warehouse' => ['inventory', 'outputs', 'reception'],
    ];

    /** Quién tenía `export_reports` antes (la única ruta que ya iba por permiso). */
    private const EXPORTABAN = ['admin', 'financiero'];

    /** @var array<string, User> */
    private array $usuarios = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Perfiles de fábrica + permisos del día uno (el 'auditor' ya lo creó su
        // migración; RolesSeeder aplica el catálogo a todos).
        $this->seed(RolesSeeder::class);

        foreach (self::PERFILES as $perfil) {
            $rol = Role::where('name', $perfil)->firstOrFail();
            $this->usuarios[$perfil] = User::create([
                'name' => "Usuario {$perfil}",
                'email' => "paridad_{$perfil}_" . uniqid() . '@agriflor.com',
                'password' => bcrypt('password'),
                'role' => $perfil,
                'role_id' => $rol->id,
                'status' => 'active',
            ]);
        }
    }

    /** LA PRUEBA: 239 rutas × 8 perfiles, mismo veredicto que antes. */
    public function test_cada_ruta_decide_igual_que_antes_para_cada_perfil(): void
    {
        $foto = json_decode(file_get_contents(base_path('tests/Fixtures/route_access_antes_de_perfiles.json')), true);
        $diferencias = [];
        $vistas = [];

        foreach ($this->rutasDelApi() as $clave => $ruta) {
            $vistas[$clave] = true;

            if (!isset($foto[$clave])) {
                $diferencias[] = "RUTA NUEVA sin declarar en la foto: {$clave}";
                continue;
            }

            foreach (self::PERFILES as $perfil) {
                $antes = $this->podiaAntes($foto[$clave], $perfil);
                $ahora = $this->puedeAhora($ruta, $this->usuarios[$perfil]);

                if ($antes !== $ahora) {
                    $diferencias[] = sprintf(
                        '%s · %s: antes %s, ahora %s',
                        $clave,
                        $perfil,
                        $antes ? 'PODÍA' : 'no podía',
                        $ahora ? 'PUEDE' : 'no puede'
                    );
                }
            }
        }

        foreach (array_diff_key($foto, $vistas) as $clave => $_) {
            $diferencias[] = "RUTA DESAPARECIDA: {$clave}";
        }

        $this->assertSame([], $diferencias, "Cambió el acceso de alguien:\n" . implode("\n", $diferencias));
        $this->assertCount(245, $vistas, 'La foto del antes tenía 239 rutas; la pantalla de Perfiles agregó 6.');
    }

    /** El menú de cada perfil es el mismo: nadie ve una sección de más ni de menos. */
    public function test_el_menu_de_cada_perfil_no_cambia(): void
    {
        foreach (self::MENU_ANTES as $perfil => $esperado) {
            $modulos = Role::where('name', $perfil)->firstOrFail()->getAccessibleModules();
            sort($modulos);

            $this->assertSame($esperado, $modulos, "Menú del perfil {$perfil}");
        }
    }

    /** Toda ruta pide un permiso que existe en el catálogo: un nombre mal escrito dejaría la ruta cerrada para todos. */
    public function test_todo_permiso_pedido_por_una_ruta_existe_en_el_catalogo(): void
    {
        $catalogo = PermissionCatalog::names();
        $desconocidos = [];

        foreach ($this->rutasDelApi() as $clave => $ruta) {
            foreach ($this->puertasDe($ruta) as [$clase, $parametros]) {
                if (str_ends_with($clase, 'CheckPermission') && !in_array($parametros[0], $catalogo, true)) {
                    $desconocidos[] = "{$clave} pide '{$parametros[0]}'";
                }
            }
        }

        $this->assertSame([], $desconocidos);
    }

    /** Cada módulo tiene exactamente un permiso de menú: es el que decide si se ve. */
    public function test_cada_modulo_tiene_un_solo_permiso_de_menu(): void
    {
        $porModulo = [];
        foreach (PermissionCatalog::all() as $p) {
            if ($p['menu']) {
                $porModulo[$p['module']][] = $p['name'];
            }
        }

        foreach ($porModulo as $modulo => $permisos) {
            $this->assertCount(1, $permisos, "El módulo {$modulo} tiene más de un permiso de menú");
        }
        $this->assertSame('view_outputs', PermissionCatalog::menuPermission('outputs'));
        $this->assertNull(PermissionCatalog::menuPermission('performance'), 'Rendimiento sigue abierto a todos en la parte 1.');
    }

    // ------------------------------------------------------------------
    // Por HTTP de verdad, en los casos que más importan
    // ------------------------------------------------------------------

    public function test_por_http_las_puertas_responden_igual_que_antes(): void
    {
        $inexistente = '00000000-0000-0000-0000-000000000000';

        // Compras: Encargado de Compras y Bodeguero pasan la puerta (422 = llegó a
        // la validación); el Operario de Finca no.
        $this->como('purchasing')->postJson('/api/purchases', [])->assertStatus(422);
        $this->como('warehouse')->postJson('/api/purchases', [])->assertStatus(422);
        $this->como('farm')->postJson('/api/purchases', [])->assertStatus(403);

        // Aprobar salidas: solo Supervisor (y admin). La base decía que 6 perfiles
        // tenían 'approve_output', pero el API nunca los dejó.
        $this->assertNotSame(403, $this->como('supervisor')->postJson("/api/product-outputs/{$inexistente}/approve")->status());
        $this->como('farm')->postJson("/api/product-outputs/{$inexistente}/approve")->assertStatus(403);
        $this->como('warehouse')->postJson("/api/product-outputs/{$inexistente}/approve")->assertStatus(403);

        // Programar tareas: Supervisor sí, Agrónomo no (el 403 que ya conocía).
        $this->como('supervisor')->postJson('/api/performance/schedules', [])->assertStatus(422);
        $this->como('agronomist')->postJson('/api/performance/schedules', [])->assertStatus(403);

        // Usuarios: solo el administrador.
        $this->como('admin')->getJson('/api/users')->assertOk();
        $this->como('supervisor')->getJson('/api/users')->assertStatus(403);

        // Auditoría: sigue por NOMBRE de perfil. Ni el administrador la ve.
        $this->como('auditor')->getJson('/api/audits')->assertOk();
        $this->como('admin')->getJson('/api/audits')->assertStatus(403);
    }

    // ------------------------------------------------------------------

    private function como(string $perfil): self
    {
        return $this->actingAs($this->usuarios[$perfil], 'api');
    }

    /** @return array<string, RutaLaravel> clave "METODO uri" => ruta */
    private function rutasDelApi(): array
    {
        $rutas = [];
        foreach (Route::getRoutes() as $ruta) {
            if (str_starts_with($ruta->uri(), 'api/')) {
                $rutas[$ruta->methods()[0] . ' ' . $ruta->uri()] = $ruta;
            }
        }

        return $rutas;
    }

    private function podiaAntes(array $puerta, string $perfil): bool
    {
        return match ($puerta['tipo']) {
            'publica', 'sesion' => true,
            'perfiles' => in_array($perfil, $puerta['perfiles'], true),
            'permiso' => in_array($perfil, self::EXPORTABAN, true),
        };
    }

    /**
     * Ejecuta los middlewares REALES de acceso de la ruta (CheckRole,
     * CheckPermission, CheckModuleAccess) con ese usuario, sin llegar al
     * controlador: lo que se mide es la puerta, no lo que hay detrás.
     */
    private function puedeAhora(RutaLaravel $ruta, User $usuario): bool
    {
        $this->actingAs($usuario->fresh(), 'api');

        foreach ($this->puertasDe($ruta) as [$clase, $parametros]) {
            $peticion = Request::create('/' . $ruta->uri(), $ruta->methods()[0]);
            $peticion->setUserResolver(fn () => auth()->user());

            $respuesta = app($clase)->handle($peticion, fn () => response('paso', 200), ...$parametros);

            if (in_array($respuesta->getStatusCode(), [401, 403], true)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<int, array{0: string, 1: array<int, string>}> [clase, parámetros] */
    private function puertasDe(RutaLaravel $ruta): array
    {
        $puertas = [];
        foreach (app('router')->gatherRouteMiddleware($ruta) as $middleware) {
            if (!is_string($middleware)) {
                continue;
            }
            [$clase, $parametros] = array_pad(explode(':', $middleware, 2), 2, '');
            if (preg_match('/\\\\Check(Role|Permission|ModuleAccess)$/', $clase)) {
                $puertas[] = [$clase, $parametros === '' ? [] : explode(',', $parametros)];
            }
        }

        return $puertas;
    }
}
