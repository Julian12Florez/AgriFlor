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
 * Este archivo lo generó
 * docs/superpowers/specs/2026-10-07-perfiles-permisos/generar_permisos_parte1.py
 * a partir de esa foto.
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
        'master' => 'Datos Maestros',
        'technical' => 'Procesos Técnicos',
        'purchases' => 'Compras',
        'outputs' => 'Salidas',
        'reception' => 'Recepción',
        'inventory' => 'Inventario',
        'reports' => 'Reportes',
        'liquidation' => 'Liquidación',
        'performance' => 'Rendimiento',
        'admin' => 'Administración',
        'audit' => 'Auditoría',
    ];

    /**
     * Permisos que ya no significan nada y se eliminan: o no protegían ninguna
     * ruta, o los reemplazó uno más preciso (crear/editar/eliminar por separado).
     *
     * `adjust_inventory` ("reservado") no protegía nada; lo reemplazó
     * `request_adjustment` el 7-oct-2026, que nace SIN asignar (decisión del
     * cliente: solicitar ajustes queda apagado hasta que el administrador lo
     * habilite a un perfil).
     */
    public const OBSOLETE = ['view_products', 'manage_master_data', 'manage_technical', 'delete_reception', 'system_settings', 'adjust_inventory'];

    /**
     * @return array<int, array{name: string, label: string, module: string, group: string, action: string, menu: bool, sort: int, roles: array<int, string>}>
     */
    public static function all(): array
    {
        return [
            ['name' => 'view_master_data', 'label' => 'Ver Datos Maestros', 'module' => 'master', 'group' => 'Menú', 'action' => 'view', 'menu' => true, 'sort' => 10, 'roles' => ['purchasing']],
            ['name' => 'create_product', 'label' => 'Crear productos', 'module' => 'master', 'group' => 'Productos', 'action' => 'create', 'menu' => false, 'sort' => 20, 'roles' => ['agronomist', 'purchasing', 'warehouse']],
            ['name' => 'edit_product', 'label' => 'Editar productos', 'module' => 'master', 'group' => 'Productos', 'action' => 'edit', 'menu' => false, 'sort' => 30, 'roles' => ['agronomist', 'purchasing', 'warehouse']],
            ['name' => 'delete_product', 'label' => 'Eliminar productos', 'module' => 'master', 'group' => 'Productos', 'action' => 'delete', 'menu' => false, 'sort' => 40, 'roles' => ['agronomist', 'purchasing', 'warehouse']],
            ['name' => 'create_master_data', 'label' => 'Crear catálogos', 'module' => 'master', 'group' => 'Catálogos (marcas, categorías, unidades, proveedores)', 'action' => 'create', 'menu' => false, 'sort' => 50, 'roles' => ['purchasing']],
            ['name' => 'edit_master_data', 'label' => 'Editar catálogos', 'module' => 'master', 'group' => 'Catálogos (marcas, categorías, unidades, proveedores)', 'action' => 'edit', 'menu' => false, 'sort' => 60, 'roles' => ['purchasing']],
            ['name' => 'delete_master_data', 'label' => 'Eliminar catálogos', 'module' => 'master', 'group' => 'Catálogos (marcas, categorías, unidades, proveedores)', 'action' => 'delete', 'menu' => false, 'sort' => 70, 'roles' => ['purchasing']],
            ['name' => 'create_location', 'label' => 'Crear ubicaciones', 'module' => 'master', 'group' => 'Ubicaciones', 'action' => 'create', 'menu' => false, 'sort' => 80, 'roles' => ['purchasing', 'supervisor', 'warehouse']],
            ['name' => 'edit_location', 'label' => 'Editar ubicaciones', 'module' => 'master', 'group' => 'Ubicaciones', 'action' => 'edit', 'menu' => false, 'sort' => 90, 'roles' => ['purchasing', 'supervisor', 'warehouse']],
            ['name' => 'delete_location', 'label' => 'Eliminar ubicaciones', 'module' => 'master', 'group' => 'Ubicaciones', 'action' => 'delete', 'menu' => false, 'sort' => 100, 'roles' => ['purchasing', 'supervisor', 'warehouse']],
            ['name' => 'create_farm_lot', 'label' => 'Crear lotes de finca', 'module' => 'master', 'group' => 'Lotes de finca', 'action' => 'create', 'menu' => false, 'sort' => 110, 'roles' => ['warehouse']],
            ['name' => 'edit_farm_lot', 'label' => 'Editar lotes de finca', 'module' => 'master', 'group' => 'Lotes de finca', 'action' => 'edit', 'menu' => false, 'sort' => 120, 'roles' => ['warehouse']],
            ['name' => 'delete_farm_lot', 'label' => 'Eliminar lotes de finca', 'module' => 'master', 'group' => 'Lotes de finca', 'action' => 'delete', 'menu' => false, 'sort' => 130, 'roles' => ['warehouse']],
            ['name' => 'manage_output_types', 'label' => 'Administrar tipos de salida', 'module' => 'master', 'group' => 'Tipos de salida', 'action' => 'special', 'menu' => false, 'sort' => 140, 'roles' => []],
            ['name' => 'view_technical', 'label' => 'Ver Procesos Técnicos', 'module' => 'technical', 'group' => 'Menú', 'action' => 'view', 'menu' => true, 'sort' => 150, 'roles' => ['agronomist']],
            ['name' => 'view_recipes', 'label' => 'Ver recetas', 'module' => 'technical', 'group' => 'Recetas técnicas', 'action' => 'view', 'menu' => false, 'sort' => 160, 'roles' => ['agronomist']],
            ['name' => 'create_recipe', 'label' => 'Crear recetas', 'module' => 'technical', 'group' => 'Recetas técnicas', 'action' => 'create', 'menu' => false, 'sort' => 170, 'roles' => ['agronomist']],
            ['name' => 'edit_recipe', 'label' => 'Editar recetas', 'module' => 'technical', 'group' => 'Recetas técnicas', 'action' => 'edit', 'menu' => false, 'sort' => 180, 'roles' => ['agronomist']],
            ['name' => 'delete_recipe', 'label' => 'Eliminar recetas', 'module' => 'technical', 'group' => 'Recetas técnicas', 'action' => 'delete', 'menu' => false, 'sort' => 190, 'roles' => ['agronomist']],
            ['name' => 'view_technical_orders', 'label' => 'Ver órdenes técnicas', 'module' => 'technical', 'group' => 'Órdenes técnicas', 'action' => 'view', 'menu' => false, 'sort' => 200, 'roles' => ['agronomist', 'supervisor']],
            ['name' => 'create_technical_order', 'label' => 'Crear órdenes técnicas', 'module' => 'technical', 'group' => 'Órdenes técnicas', 'action' => 'create', 'menu' => false, 'sort' => 210, 'roles' => ['agronomist']],
            ['name' => 'edit_technical_order', 'label' => 'Editar órdenes técnicas', 'module' => 'technical', 'group' => 'Órdenes técnicas', 'action' => 'edit', 'menu' => false, 'sort' => 220, 'roles' => ['agronomist']],
            ['name' => 'delete_technical_order', 'label' => 'Eliminar órdenes técnicas', 'module' => 'technical', 'group' => 'Órdenes técnicas', 'action' => 'delete', 'menu' => false, 'sort' => 230, 'roles' => ['agronomist']],
            ['name' => 'process_technical_order', 'label' => 'Aprobar, completar y cancelar órdenes técnicas', 'module' => 'technical', 'group' => 'Órdenes técnicas', 'action' => 'special', 'menu' => false, 'sort' => 240, 'roles' => ['agronomist']],
            ['name' => 'view_purchases', 'label' => 'Ver Compras', 'module' => 'purchases', 'group' => 'Menú', 'action' => 'view', 'menu' => true, 'sort' => 250, 'roles' => ['purchasing']],
            ['name' => 'create_purchase', 'label' => 'Crear compras', 'module' => 'purchases', 'group' => 'Compras', 'action' => 'create', 'menu' => false, 'sort' => 260, 'roles' => ['purchasing']],
            ['name' => 'edit_purchase', 'label' => 'Editar compras', 'module' => 'purchases', 'group' => 'Compras', 'action' => 'edit', 'menu' => false, 'sort' => 270, 'roles' => ['purchasing']],
            ['name' => 'delete_purchase', 'label' => 'Eliminar compras', 'module' => 'purchases', 'group' => 'Compras', 'action' => 'delete', 'menu' => false, 'sort' => 280, 'roles' => ['purchasing']],
            ['name' => 'reverse_purchase', 'label' => 'Eliminar compra recibida (revierte inventario)', 'module' => 'purchases', 'group' => 'Compras', 'action' => 'special', 'menu' => false, 'sort' => 290, 'roles' => []],
            ['name' => 'view_outputs', 'label' => 'Ver Salidas', 'module' => 'outputs', 'group' => 'Salidas', 'action' => 'view', 'menu' => true, 'sort' => 300, 'roles' => ['agronomist', 'farm', 'financiero', 'purchasing', 'supervisor', 'warehouse']],
            ['name' => 'create_output', 'label' => 'Crear salidas', 'module' => 'outputs', 'group' => 'Salidas', 'action' => 'create', 'menu' => false, 'sort' => 310, 'roles' => ['agronomist', 'farm', 'financiero', 'purchasing', 'supervisor', 'warehouse']],
            ['name' => 'edit_output', 'label' => 'Editar salidas', 'module' => 'outputs', 'group' => 'Salidas', 'action' => 'edit', 'menu' => false, 'sort' => 320, 'roles' => ['agronomist', 'farm', 'financiero', 'purchasing', 'supervisor', 'warehouse']],
            ['name' => 'delete_output', 'label' => 'Eliminar salidas', 'module' => 'outputs', 'group' => 'Salidas', 'action' => 'delete', 'menu' => false, 'sort' => 330, 'roles' => ['agronomist', 'farm', 'financiero', 'purchasing', 'supervisor', 'warehouse']],
            ['name' => 'approve_output', 'label' => 'Aprobar salidas', 'module' => 'outputs', 'group' => 'Salidas', 'action' => 'special', 'menu' => false, 'sort' => 340, 'roles' => ['supervisor']],
            ['name' => 'register_application', 'label' => 'Registrar aplicaciones', 'module' => 'outputs', 'group' => 'Aplicaciones', 'action' => 'special', 'menu' => false, 'sort' => 350, 'roles' => ['agronomist', 'warehouse']],
            ['name' => 'approve_application', 'label' => 'Aprobar aplicaciones', 'module' => 'outputs', 'group' => 'Aplicaciones', 'action' => 'special', 'menu' => false, 'sort' => 360, 'roles' => ['warehouse']],
            ['name' => 'view_reception', 'label' => 'Ver Recepción', 'module' => 'reception', 'group' => 'Menú', 'action' => 'view', 'menu' => true, 'sort' => 370, 'roles' => ['agronomist', 'farm', 'financiero', 'purchasing', 'supervisor', 'warehouse']],
            ['name' => 'create_reception', 'label' => 'Crear recepciones', 'module' => 'reception', 'group' => 'Recepciones', 'action' => 'create', 'menu' => false, 'sort' => 380, 'roles' => ['agronomist', 'farm', 'financiero', 'purchasing', 'supervisor', 'warehouse']],
            ['name' => 'edit_reception', 'label' => 'Completar, cancelar y finalizar recepciones', 'module' => 'reception', 'group' => 'Recepciones', 'action' => 'edit', 'menu' => false, 'sort' => 390, 'roles' => ['agronomist', 'farm', 'financiero', 'purchasing', 'supervisor', 'warehouse']],
            ['name' => 'view_inventory', 'label' => 'Ver Inventario', 'module' => 'inventory', 'group' => 'Menú', 'action' => 'view', 'menu' => true, 'sort' => 400, 'roles' => ['agronomist', 'financiero', 'supervisor', 'warehouse']],
            ['name' => 'request_adjustment', 'label' => 'Solicitar y cancelar ajustes', 'module' => 'inventory', 'group' => 'Ajustes', 'action' => 'create', 'menu' => false, 'sort' => 410, 'roles' => []],
            ['name' => 'approve_adjustment', 'label' => 'Aprobar y rechazar ajustes', 'module' => 'inventory', 'group' => 'Ajustes', 'action' => 'special', 'menu' => false, 'sort' => 420, 'roles' => []],
            ['name' => 'manage_alerts', 'label' => 'Crear y resolver alertas', 'module' => 'inventory', 'group' => 'Alertas', 'action' => 'special', 'menu' => false, 'sort' => 430, 'roles' => ['financiero', 'supervisor', 'warehouse']],
            ['name' => 'view_reports', 'label' => 'Ver Reportes', 'module' => 'reports', 'group' => 'Menú', 'action' => 'view', 'menu' => true, 'sort' => 440, 'roles' => ['financiero']],
            ['name' => 'export_reports', 'label' => 'Exportar informes a Excel y PDF', 'module' => 'reports', 'group' => 'Informes', 'action' => 'special', 'menu' => false, 'sort' => 450, 'roles' => ['financiero']],
            ['name' => 'view_liquidation', 'label' => 'Ver Liquidación', 'module' => 'liquidation', 'group' => 'Trabajadores, tareas y asignaciones', 'action' => 'view', 'menu' => true, 'sort' => 460, 'roles' => []],
            ['name' => 'create_liquidation', 'label' => 'Crear en liquidación', 'module' => 'liquidation', 'group' => 'Trabajadores, tareas y asignaciones', 'action' => 'create', 'menu' => false, 'sort' => 470, 'roles' => []],
            ['name' => 'edit_liquidation', 'label' => 'Editar en liquidación', 'module' => 'liquidation', 'group' => 'Trabajadores, tareas y asignaciones', 'action' => 'edit', 'menu' => false, 'sort' => 480, 'roles' => []],
            ['name' => 'delete_liquidation', 'label' => 'Eliminar en liquidación', 'module' => 'liquidation', 'group' => 'Trabajadores, tareas y asignaciones', 'action' => 'delete', 'menu' => false, 'sort' => 490, 'roles' => []],
            ['name' => 'view_liquidation_reports', 'label' => 'Ver y exportar informes de liquidación', 'module' => 'liquidation', 'group' => 'Informes', 'action' => 'special', 'menu' => false, 'sort' => 500, 'roles' => ['financiero', 'supervisor']],
            ['name' => 'create_schedule', 'label' => 'Crear programaciones', 'module' => 'performance', 'group' => 'Programación', 'action' => 'create', 'menu' => false, 'sort' => 510, 'roles' => ['supervisor']],
            ['name' => 'edit_schedule', 'label' => 'Editar y cancelar programaciones', 'module' => 'performance', 'group' => 'Programación', 'action' => 'edit', 'menu' => false, 'sort' => 520, 'roles' => ['supervisor']],
            ['name' => 'finalize_schedule', 'label' => 'Finalizar programaciones', 'module' => 'performance', 'group' => 'Programación', 'action' => 'special', 'menu' => false, 'sort' => 530, 'roles' => ['supervisor']],
            ['name' => 'create_task_catalog', 'label' => 'Crear catálogo de tareas', 'module' => 'performance', 'group' => 'Catálogo de tareas', 'action' => 'create', 'menu' => false, 'sort' => 540, 'roles' => ['supervisor']],
            ['name' => 'edit_task_catalog', 'label' => 'Editar catálogo de tareas', 'module' => 'performance', 'group' => 'Catálogo de tareas', 'action' => 'edit', 'menu' => false, 'sort' => 550, 'roles' => ['supervisor']],
            ['name' => 'delete_task_catalog', 'label' => 'Eliminar catálogo de tareas', 'module' => 'performance', 'group' => 'Catálogo de tareas', 'action' => 'delete', 'menu' => false, 'sort' => 560, 'roles' => ['supervisor']],
            ['name' => 'register_task_log', 'label' => 'Registrar avance diario', 'module' => 'performance', 'group' => 'Registro diario', 'action' => 'special', 'menu' => false, 'sort' => 570, 'roles' => ['farm', 'supervisor']],
            ['name' => 'delete_task_log', 'label' => 'Eliminar registros de avance', 'module' => 'performance', 'group' => 'Registro diario', 'action' => 'special', 'menu' => false, 'sort' => 580, 'roles' => []],
            ['name' => 'manage_performance_settings', 'label' => 'Cambiar la configuración de rendimiento', 'module' => 'performance', 'group' => 'Configuración', 'action' => 'special', 'menu' => false, 'sort' => 590, 'roles' => []],
            ['name' => 'view_admin', 'label' => 'Ver Administración', 'module' => 'admin', 'group' => 'Menú', 'action' => 'view', 'menu' => true, 'sort' => 600, 'roles' => []],
            ['name' => 'manage_users', 'label' => 'Administrar usuarios', 'module' => 'admin', 'group' => 'Usuarios', 'action' => 'special', 'menu' => false, 'sort' => 610, 'roles' => []],
            ['name' => 'manage_companies', 'label' => 'Administrar empresas', 'module' => 'admin', 'group' => 'Empresas', 'action' => 'special', 'menu' => false, 'sort' => 620, 'roles' => []],
            ['name' => 'manage_roles', 'label' => 'Administrar perfiles y permisos', 'module' => 'admin', 'group' => 'Perfiles', 'action' => 'special', 'menu' => false, 'sort' => 630, 'roles' => []],
            ['name' => 'audit.view', 'label' => 'Ver la auditoría', 'module' => 'audit', 'group' => 'Menú', 'action' => 'view', 'menu' => true, 'sort' => 640, 'roles' => ['auditor']],
        ];
    }

    /**
     * Permisos que existen pero NO se ofrecen en la pantalla de Perfiles:
     *  - audit.view: la auditoría va por nombre de perfil (`role:auditor`); ni
     *    el administrador la ve, así que tampoco puede regalarla. El Auditor es
     *    un perfil único e invisible para todos los demás.
     * Al guardar un perfil desde la pantalla estos no se tocan.
     */
    public const HIDDEN_FROM_PROFILES = ['audit.view'];

    /**
     * Los permisos que el administrador puede marcar en la pantalla de Perfiles.
     *
     * @return array<int, array{name: string, label: string, module: string, group: string, action: string, menu: bool, sort: int, roles: array<int, string>}>
     */
    public static function assignable(): array
    {
        return array_values(array_filter(
            self::all(),
            fn (array $p) => !in_array($p['name'], self::HIDDEN_FROM_PROFILES, true)
        ));
    }

    /** @return array<int, string> */
    public static function assignableNames(): array
    {
        return array_column(self::assignable(), 'name');
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
