<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perfiles y permisos, parte 1: los permisos pasan a describir de verdad lo que
 * cada perfil puede hacer.
 *
 * Hasta hoy lo que un perfil podía GUARDAR estaba fijo en routes/api.php
 * (`role:admin,supervisor,...`) y los permisos de la base solo armaban el menú.
 * Desde esta migración cada ruta de escritura pide un permiso del catálogo
 * (App\Support\PermissionCatalog) y el menú sale del permiso "ver" del módulo.
 *
 * El día uno nadie gana ni pierde acceso: cada permiso queda asignado a los
 * mismos perfiles que pasaban la lista `role:` de esas rutas. Lo demuestra
 * tests/Feature/PermissionParityTest.php.
 *
 * Columnas nuevas de `permissions`, para la pantalla de Perfiles:
 *  - group_label  fila de la tabla (Productos, Compras, Programación...)
 *  - action       view | create | edit | delete | special
 *  - is_menu      el permiso que hace visible el módulo en el menú
 *  - sort_order   orden de presentación
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('group_label')->nullable()->after('module');
            $table->string('action', 20)->default('special')->after('group_label');
            $table->boolean('is_menu')->default(false)->after('action');
            $table->unsignedInteger('sort_order')->default(0)->after('is_menu');
        });

        PermissionCatalog::syncDefinitions();
        PermissionCatalog::applyDefaultAssignments();
    }

    /**
     * Solo quita las columnas. Los permisos y sus asignaciones no se devuelven
     * al estado anterior: para eso está el respaldo de la base previo al
     * despliegue (el código viejo no usa estas columnas, pero sí las listas
     * `role:` de routes/api.php, que vuelven con el código).
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn(['group_label', 'action', 'is_menu', 'sort_order']);
        });
    }
};
