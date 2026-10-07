<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    /**
     * Perfiles de fábrica. QUÉ puede hacer cada uno no se decide aquí: sale de
     * App\Support\PermissionCatalog (campo `roles` de cada permiso), que es la
     * misma fuente que usa la migración que introdujo el catálogo.
     *
     * Antes este seeder repartía los permisos "por módulo" (todo lo de salidas,
     * todo lo de recepción...), y por eso la base decía, por ejemplo, que el
     * Operario de Finca podía aprobar salidas cuando el API no lo dejaba.
     */
    public function run(): void
    {
        // Rename legacy role names to match the enum used in routes and forms
        Role::where('name', 'warehouse_operator')->update(['name' => 'warehouse']);
        Role::where('name', 'farm_operator')->update(['name' => 'farm']);

        $perfiles = [
            ['admin', 'Administrador', 'Acceso completo a todos los módulos del sistema incluyendo Administración', true],
            ['agronomist', 'Agrónomo', 'Acceso a procesos técnicos, salidas y recepciones', false],
            ['supervisor', 'Supervisor', 'Acceso a recepción, salidas e inventario', false],
            ['warehouse', 'Bodeguero', 'Acceso a salidas, recepciones e inventario', false],
            ['farm', 'Operario de Finca', 'Acceso a recepción y salidas en finca', false],
            ['purchasing', 'Encargado de Compras', 'Acceso a datos maestros, compras, salidas y recepciones', false],
            ['financiero', 'Financiero', 'Acceso a reportes, alertas, inventario, salidas y recepciones', false],
        ];

        foreach ($perfiles as [$nombre, $etiqueta, $descripcion, $accesoTotal]) {
            Role::firstOrCreate(
                ['name' => $nombre],
                [
                    'display_name' => $etiqueta,
                    'description' => $descripcion,
                    'has_full_access' => $accesoTotal,
                    'excluded_modules' => null,
                    // "Solo ve su finca": la regla que antes vivía en el código
                    // (User::LOCATION_SCOPED_ROLES y `farm` en las programaciones).
                    'location_scoped' => in_array($nombre, ['supervisor', 'farm'], true),
                    'schedule_scoped' => $nombre === 'farm',
                ]
            );
        }

        // El perfil 'auditor' lo crea su propia migración (2026_06_03_010000).

        PermissionCatalog::syncDefinitions();
        PermissionCatalog::applyDefaultAssignments();

        $this->command?->info('Perfiles creados; permisos asignados según App\\Support\\PermissionCatalog.');
    }
}
