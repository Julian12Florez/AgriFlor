<?php

namespace App\Support;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Catálogo ÚNICO de permisos del sistema.
 *
 * Es la fuente de verdad de qué permisos existen, a qué sección del menú
 * pertenecen y cómo se muestran en la pantalla de Perfiles. Cada ruta de
 * escritura del API pide uno de estos permisos (`permission:<nombre>` en
 * routes/api.php).
 *
 * POR QUÉ EXISTE
 * ==============
 * Hasta el 7-oct-2026 lo que cada perfil podía GUARDAR estaba fijo en el código,
 * en 20 listas `role:admin,supervisor,...` repartidas por routes/api.php, y los
 * permisos de la base solo servían para armar el menú — ni siquiera coincidían
 * con lo que el API dejaba hacer (la base decía que el Operario de Finca podía
 * aprobar salidas; el API no lo dejaba). El cliente pidió poder configurar los
 * perfiles desde una pantalla, y que TODO el sistema obedezca esa configuración.
 *
 * LA REGLA DEL DÍA UNO
 * ====================
 * `roles` de cada permiso es la lista de perfiles que lo tienen al desplegar:
 * exactamente los que hoy pasan la lista `role:` de esas rutas. Nadie gana ni
 * pierde acceso. Lo demuestra tests/Feature/PermissionParityTest.php contra la
 * foto del "antes" (tests/Fixtures/route_access_antes_de_perfiles.json).
 *
 * Este archivo lo generó .dumps/generar_permisos_parte1.py a partir de esa foto.
 * De ahí en adelante se mantiene a mano: un permiso nuevo se agrega aquí y se
 * usa en la ruta.
 *
 * CAMPOS
 * ======
 *  - name    nombre técnico (el que va en `permission:`).
 *  - label   texto para la pantalla de Perfiles.
 *  - module  sección del menú a la que pertenece.
 *  - group   fila de la tabla de la pantalla de Perfiles.
 *  - action  view | create | edit | delete | special  (la columna de la tabla).
 *  - menu    true = es EL permiso que hace visible el módulo en el menú y deja
 *            abrir sus vistas (uno por módulo).
 *  - roles   perfiles que lo tienen el día uno (sin contar los de acceso total).
 */
final class PermissionCatalog
{
    /** Secciones del menú, en el orden en que se muestran. */
    public const MODULES = [
/*MODULOS*/
    ];

    /**
     * Permisos que ya no significan nada y se eliminan: o no protegían ninguna
     * ruta, o los reemplazó uno más preciso (crear/editar/eliminar por separado).
     */
    public const OBSOLETE = [/*OBSOLETOS*/];

    /**
     * @return array<int, array{name: string, label: string, module: string, group: string, action: string, menu: bool, sort: int, roles: array<int, string>}>
     */
    public static function all(): array
    {
        return [
/*PERMISOS*/
        ];
    }

    /** @return array<int, string> */
    public static function names(): array
    {
        return array_column(self::all(), 'name');
    }

    /** El permiso que hace visible un módulo en el menú, o null si no tiene. */
    public static function menuPermission(string $module): ?string
    {
        foreach (self::all() as $p) {
            if ($p['menu'] && $p['module'] === $module) {
                return $p['name'];
            }
        }

        return null;
    }

    /**
     * Crea o actualiza las filas de `permissions` según el catálogo y elimina
     * los obsoletos. Idempotente: se puede correr en cada despliegue. NO toca
     * qué perfil tiene qué permiso.
     */
    public static function syncDefinitions(): void
    {
        foreach (self::all() as $p) {
            $datos = [
                'display_name' => $p['label'],
                'module' => $p['module'],
                'group_label' => $p['group'],
                'action' => $p['action'],
                'is_menu' => $p['menu'],
                'sort_order' => $p['sort'],
                'updated_at' => now(),
            ];

            $existente = DB::table('permissions')->where('name', $p['name'])->first();
            if ($existente) {
                DB::table('permissions')->where('id', $existente->id)->update($datos);
            } else {
                DB::table('permissions')->insert($datos + [
                    'id' => (string) Str::uuid(),
                    'name' => $p['name'],
                    'created_at' => now(),
                ]);
            }
        }

        $obsoletos = DB::table('permissions')->whereIn('name', self::OBSOLETE)->pluck('id');
        DB::table('role_permission')->whereIn('permission_id', $obsoletos)->delete();
        DB::table('permissions')->whereIn('id', $obsoletos)->delete();
    }

    /**
     * Deja cada permiso asignado a los perfiles del día uno (`roles`), y todos
     * los permisos a los perfiles de acceso total.
     *
     * SOLO se corre en la migración que introduce el catálogo (y en seeders y
     * pruebas). Después, quién tiene qué lo decide el administrador en la
     * pantalla de Perfiles: volver a correrlo pisaría sus cambios.
     */
    public static function applyDefaultAssignments(): void
    {
        $roles = DB::table('roles')->get(['id', 'name', 'has_full_access']);
        $porNombre = $roles->keyBy('name');
        $accesoTotal = $roles->where('has_full_access', true)->pluck('id')->all();
        $ahora = now();

        foreach (self::all() as $p) {
            $permisoId = DB::table('permissions')->where('name', $p['name'])->value('id');
            if (!$permisoId) {
                continue;
            }

            $perfiles = $accesoTotal;
            foreach ($p['roles'] as $nombre) {
                if (isset($porNombre[$nombre])) {
                    $perfiles[] = $porNombre[$nombre]->id;
                }
            }

            DB::table('role_permission')->where('permission_id', $permisoId)->delete();
            foreach (array_unique($perfiles) as $roleId) {
                DB::table('role_permission')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permisoId,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }
    }
}
