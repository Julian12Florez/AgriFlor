<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Decisiones del cliente sobre los perfiles (7-oct-2026), aplicadas a los datos.
 *
 * 1. Solicitar y cancelar ajustes pasa a ser el permiso `request_adjustment`,
 *    y nace APAGADO: solo el administrador (acceso total) lo tiene, y él lo
 *    habilita a los perfiles que quiera desde Administración → Perfiles. Antes
 *    cualquier sesión podía solicitar ajustes, incluido el auditor. El permiso
 *    reservado `adjust_inventory`, que no protegía nada, se elimina.
 *
 * 2. El Bodeguero deja de poder crear, editar y eliminar compras: no ve el menú
 *    Compras y en producción nunca creó una (sus 129 cambios a compras son el
 *    efecto de recibir mercancía, que va por el permiso de recepción).
 *
 * 3. El Operario de Finca puede registrar el avance diario de las tareas de sus
 *    fincas (la ruta pedía un perfil `farm_operator` que no existe).
 *
 * Solo toca esas filas de role_permission: no reaplica los permisos de fábrica
 * (eso pisaría lo que el administrador haya configurado en la pantalla).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Crea request_adjustment y borra adjust_inventory (y sus asignaciones).
        PermissionCatalog::syncDefinitions();

        $permiso = fn (string $nombre) => DB::table('permissions')->where('name', $nombre)->value('id');
        $perfil = fn (string $nombre) => DB::table('roles')->where('name', $nombre)->value('id');

        if ($bodeguero = $perfil('warehouse')) {
            DB::table('role_permission')
                ->where('role_id', $bodeguero)
                ->whereIn('permission_id', array_filter([
                    $permiso('create_purchase'),
                    $permiso('edit_purchase'),
                    $permiso('delete_purchase'),
                ]))
                ->delete();
        }

        $operario = $perfil('farm');
        $avance = $permiso('register_task_log');
        if ($operario && $avance && !DB::table('role_permission')->where(['role_id' => $operario, 'permission_id' => $avance])->exists()) {
            DB::table('role_permission')->insert([
                'role_id' => $operario,
                'permission_id' => $avance,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Los de acceso total tienen todo por definición, pero se deja la fila
        // para que la base describa la realidad.
        $solicitar = $permiso('request_adjustment');
        foreach (DB::table('roles')->where('has_full_access', true)->pluck('id') as $total) {
            if ($solicitar && !DB::table('role_permission')->where(['role_id' => $total, 'permission_id' => $solicitar])->exists()) {
                DB::table('role_permission')->insert([
                    'role_id' => $total,
                    'permission_id' => $solicitar,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Decisión de negocio: no se revierte automáticamente. Para volver
        // atrás, el administrador marca o desmarca las casillas en Perfiles.
    }
};
