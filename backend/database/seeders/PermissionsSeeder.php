<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Reception module
            [
                'name' => 'view_reception',
                'display_name' => 'Ver Recepciones',
                'module' => 'reception',
                'description' => 'Ver lista de recepciones en finca',
            ],
            [
                'name' => 'create_reception',
                'display_name' => 'Crear Recepción',
                'module' => 'reception',
                'description' => 'Crear nuevas recepciones en finca',
            ],
            [
                'name' => 'edit_reception',
                'display_name' => 'Editar Recepción',
                'module' => 'reception',
                'description' => 'Editar recepciones existentes',
            ],
            [
                'name' => 'delete_reception',
                'display_name' => 'Eliminar Recepción',
                'module' => 'reception',
                'description' => 'Eliminar recepciones',
            ],

            // Outputs module
            [
                'name' => 'view_outputs',
                'display_name' => 'Ver Salidas',
                'module' => 'outputs',
                'description' => 'Ver lista de salidas de bodega',
            ],
            [
                'name' => 'create_output',
                'display_name' => 'Crear Salida',
                'module' => 'outputs',
                'description' => 'Crear nuevas salidas de bodega',
            ],
            [
                'name' => 'edit_output',
                'display_name' => 'Editar Salida',
                'module' => 'outputs',
                'description' => 'Editar salidas existentes',
            ],
            [
                'name' => 'delete_output',
                'display_name' => 'Eliminar Salida',
                'module' => 'outputs',
                'description' => 'Eliminar salidas',
            ],
            [
                'name' => 'approve_output',
                'display_name' => 'Aprobar Salida',
                'module' => 'outputs',
                'description' => 'Aprobar o rechazar salidas',
            ],

            // Products module
            [
                'name' => 'view_products',
                'display_name' => 'Ver Productos',
                'module' => 'products',
                'description' => 'Ver lista de productos',
            ],
            [
                'name' => 'create_product',
                'display_name' => 'Crear Producto',
                'module' => 'products',
                'description' => 'Crear nuevos productos',
            ],
            [
                'name' => 'edit_product',
                'display_name' => 'Editar Producto',
                'module' => 'products',
                'description' => 'Editar productos existentes',
            ],
            [
                'name' => 'delete_product',
                'display_name' => 'Eliminar Producto',
                'module' => 'products',
                'description' => 'Eliminar productos',
            ],

            // Purchases module
            [
                'name' => 'view_purchases',
                'display_name' => 'Ver Compras',
                'module' => 'purchases',
                'description' => 'Ver lista de compras',
            ],
            [
                'name' => 'create_purchase',
                'display_name' => 'Crear Compra',
                'module' => 'purchases',
                'description' => 'Crear nuevas compras',
            ],
            [
                'name' => 'edit_purchase',
                'display_name' => 'Editar Compra',
                'module' => 'purchases',
                'description' => 'Editar compras existentes',
            ],
            [
                'name' => 'delete_purchase',
                'display_name' => 'Eliminar Compra',
                'module' => 'purchases',
                'description' => 'Eliminar compras',
            ],

            // Inventory module
            [
                'name' => 'view_inventory',
                'display_name' => 'Ver Inventario',
                'module' => 'inventory',
                'description' => 'Ver inventario y kardex',
            ],
            [
                'name' => 'adjust_inventory',
                'display_name' => 'Ajustar Inventario',
                'module' => 'inventory',
                'description' => 'Realizar ajustes de inventario',
            ],

            // Reports module
            [
                'name' => 'view_reports',
                'display_name' => 'Ver Reportes',
                'module' => 'reports',
                'description' => 'Ver reportes del sistema',
            ],
            [
                'name' => 'export_reports',
                'display_name' => 'Exportar Reportes',
                'module' => 'reports',
                'description' => 'Exportar reportes a Excel/PDF',
            ],

            // Master data modules
            [
                'name' => 'view_master_data',
                'display_name' => 'Ver Datos Maestros',
                'module' => 'master',
                'description' => 'Ver datos maestros (marcas, proveedores, ubicaciones)',
            ],
            [
                'name' => 'manage_master_data',
                'display_name' => 'Gestionar Datos Maestros',
                'module' => 'master',
                'description' => 'Crear, editar y eliminar datos maestros',
            ],

            // Technical modules
            [
                'name' => 'view_technical',
                'display_name' => 'Ver Procesos Técnicos',
                'module' => 'technical',
                'description' => 'Ver recetas y órdenes técnicas',
            ],
            [
                'name' => 'manage_technical',
                'display_name' => 'Gestionar Procesos Técnicos',
                'module' => 'technical',
                'description' => 'Crear y editar recetas y órdenes técnicas',
            ],

            // Admin module - ONLY for administrators
            [
                'name' => 'view_admin',
                'display_name' => 'Ver Administración',
                'module' => 'admin',
                'description' => 'Acceder al módulo de administración',
            ],
            [
                'name' => 'manage_users',
                'display_name' => 'Gestionar Usuarios',
                'module' => 'admin',
                'description' => 'Crear, editar y eliminar usuarios',
            ],
            [
                'name' => 'manage_roles',
                'display_name' => 'Gestionar Roles',
                'module' => 'admin',
                'description' => 'Gestionar roles y permisos',
            ],
            [
                'name' => 'system_settings',
                'display_name' => 'Configuración del Sistema',
                'module' => 'admin',
                'description' => 'Modificar configuraciones del sistema',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }

        $this->command->info('Permissions seeded successfully.');
    }
}
