<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El agrónomo pasa a VER el inventario.
 *
 * El cliente pidió "que el perfil del ingeniero vea el inventario". El rol
 * `agronomist` tenía 11 permisos y ninguno del módulo `inventory`, así que el
 * ticket se había "resuelto" antes subiendo a ese usuario a `admin`
 * (ingeniero@agriflor.com, 28-ago-2026) — lo que de paso le entregó
 * administración de usuarios, aprobación de ajustes y auditoría.
 *
 * Concederle al ROL lo que de verdad necesita permite deshacer ese atajo sin
 * quitarle visibilidad a nadie. Se otorga únicamente `view_inventory`: ajustar
 * existencias sigue siendo de bodega y administración.
 *
 * Idempotente: si el rol ya lo tiene, no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rolId = DB::table('roles')->where('name', 'agronomist')->value('id');
        $permisoId = DB::table('permissions')->where('name', 'view_inventory')->value('id');

        if (!$rolId || !$permisoId) {
            return; // entorno sin datos base: no-op
        }

        $yaLoTiene = DB::table('role_permission')
            ->where('role_id', $rolId)
            ->where('permission_id', $permisoId)
            ->exists();

        if ($yaLoTiene) {
            return;
        }

        DB::table('role_permission')->insert([
            'role_id' => $rolId,
            'permission_id' => $permisoId,
        ]);
    }

    public function down(): void
    {
        $rolId = DB::table('roles')->where('name', 'agronomist')->value('id');
        $permisoId = DB::table('permissions')->where('name', 'view_inventory')->value('id');

        if (!$rolId || !$permisoId) {
            return;
        }

        DB::table('role_permission')
            ->where('role_id', $rolId)
            ->where('permission_id', $permisoId)
            ->delete();
    }
};
