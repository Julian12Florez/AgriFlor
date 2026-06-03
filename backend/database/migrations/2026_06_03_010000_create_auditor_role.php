<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;

/**
 * Crea el rol "auditor": un rol DEDICADO y AISLADO cuya unica funcion es ver
 * el registro de auditoria (quien creo / edito / elimino el core del sistema).
 *
 * Requisito de seguridad (explicito del cliente):
 *   "ninguno de los roles actuales debe ver la auditoria ni siquiera admin;
 *    crear un rol solo de auditor para ver todo".
 *
 * El bloqueo REAL se hace en routes/api.php: las rutas de 'audits' pasan al
 * middleware role:auditor, que compara el nombre del rol de forma EXACTA, por
 * lo que admin (y cualquier otro rol) queda excluido a nivel de API. Esta
 * migracion solo crea el rol, su permiso de modulo y el usuario auditor.
 *
 * El auditor es de SOLO LECTURA: no esta en ningun grupo de rutas de escritura,
 * por lo que no puede crear, editar ni eliminar datos. Su pantalla es el
 * registro de auditoria, que ya refleja todo lo que ocurre en el core.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Permitir 'auditor' en el enum users.role
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','agronomist','warehouse','supervisor','farm','purchasing','financiero','liquidador','auditor') NOT NULL DEFAULT 'warehouse'");

        // 2) Rol auditor (sin full access: solo vera la pantalla de Auditoria)
        $role = Role::firstOrCreate(
            ['name' => 'auditor'],
            [
                'display_name' => 'Auditor',
                'description' => 'Solo lectura del registro de auditoria del sistema. No puede crear, editar ni eliminar datos.',
                'has_full_access' => false,
                'excluded_modules' => null,
            ]
        );

        // 3) Permiso del modulo 'audit'
        $perm = Permission::firstOrCreate(
            ['name' => 'audit.view'],
            [
                'display_name' => 'Ver auditoria',
                'module' => 'audit',
                'description' => 'Ver el registro de auditoria (quien hizo que en el core del sistema).',
            ]
        );

        // 4) Vincular permiso al rol auditor
        $role->permissions()->syncWithoutDetaching([$perm->id]);

        // 5) Usuario auditor (idempotente)
        if (!User::where('email', 'auditor@agriflor.com')->exists()) {
            User::create([
                'email' => 'auditor@agriflor.com',
                'name' => 'Auditor del Sistema',
                'password' => Hash::make('auditor123'),
                'role' => 'auditor',
                'role_id' => $role->id,
                'status' => 'active',
            ]);
        }
    }

    public function down(): void
    {
        User::where('email', 'auditor@agriflor.com')->delete();

        $role = Role::where('name', 'auditor')->first();
        if ($role) {
            $role->permissions()->detach();
            $role->delete();
        }

        Permission::where('name', 'audit.view')->delete();

        // Revertir enum (quitar 'auditor')
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','agronomist','warehouse','supervisor','farm','purchasing','financiero','liquidador') NOT NULL DEFAULT 'warehouse'");
    }
};
