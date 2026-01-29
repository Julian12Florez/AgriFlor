<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        // Rename legacy role names to match the enum used in routes and forms
        Role::where('name', 'warehouse_operator')->update(['name' => 'warehouse']);
        Role::where('name', 'farm_operator')->update(['name' => 'farm']);

        // 1. ADMINISTRATOR - Full access to everything
        $admin = Role::firstOrCreate(
            ['name' => 'admin'],
            [
                'display_name' => 'Administrador',
                'description' => 'Acceso completo a todos los módulos del sistema incluyendo Administración',
                'has_full_access' => true,
                'excluded_modules' => null,
            ]
        );

        // Admin has all permissions
        $allPermissions = Permission::all();
        $admin->permissions()->syncWithoutDetaching($allPermissions);

        // 2. AGRONOMIST - Technical processes only + outputs/receptions
        $agronomist = Role::firstOrCreate(
            ['name' => 'agronomist'],
            [
                'display_name' => 'Agrónomo',
                'description' => 'Acceso a procesos técnicos, salidas y recepciones',
                'has_full_access' => false,
                'excluded_modules' => null,
            ]
        );
        // Update description if role already exists
        $agronomist->update(['description' => 'Acceso a procesos técnicos, salidas y recepciones']);

        $agronomistPermissions = Permission::whereIn('module', ['technical', 'outputs', 'reception'])
            ->get();
        $agronomist->permissions()->sync($agronomistPermissions);

        // 3. SUPERVISOR - Reception, Outputs, Inventory (NO reports)
        $supervisor = Role::firstOrCreate(
            ['name' => 'supervisor'],
            [
                'display_name' => 'Supervisor',
                'description' => 'Acceso a recepción, salidas e inventario',
                'has_full_access' => false,
                'excluded_modules' => null,
            ]
        );
        // Update description if role already exists
        $supervisor->update(['description' => 'Acceso a recepción, salidas e inventario']);

        $supervisorPermissions = Permission::whereIn('module', ['reception', 'outputs', 'inventory'])
            ->get();
        $supervisor->permissions()->sync($supervisorPermissions);

        // 4. WAREHOUSE (Bodeguero) - Outputs and Reception only
        $warehouse = Role::firstOrCreate(
            ['name' => 'warehouse'],
            [
                'display_name' => 'Bodeguero',
                'description' => 'Acceso a salidas y recepciones',
                'has_full_access' => false,
                'excluded_modules' => null,
            ]
        );
        // Update description if role already exists
        $warehouse->update(['description' => 'Acceso a salidas y recepciones']);

        $warehousePermissions = Permission::whereIn('module', ['outputs', 'reception'])
            ->get();
        $warehouse->permissions()->sync($warehousePermissions);

        // 5. FARM (Operario de Finca) - Reception and Outputs
        $farm = Role::firstOrCreate(
            ['name' => 'farm'],
            [
                'display_name' => 'Operario de Finca',
                'description' => 'Acceso a recepción y salidas en finca',
                'has_full_access' => false,
                'excluded_modules' => null,
            ]
        );

        $farmPermissions = Permission::whereIn('module', ['reception', 'outputs'])
            ->get();
        $farm->permissions()->sync($farmPermissions);

        // 6. PURCHASING (Encargado de Compras) - Masters, Purchases, Outputs, Reception
        $purchasing = Role::firstOrCreate(
            ['name' => 'purchasing'],
            [
                'display_name' => 'Encargado de Compras',
                'description' => 'Acceso a datos maestros, compras, salidas y recepciones',
                'has_full_access' => false,
                'excluded_modules' => null,
            ]
        );
        // Update description if role already exists
        $purchasing->update(['description' => 'Acceso a datos maestros, compras, salidas y recepciones']);

        $purchasingPermissions = Permission::whereIn('module', ['master', 'purchases', 'products', 'outputs', 'reception'])
            ->get();
        $purchasing->permissions()->sync($purchasingPermissions);

        // 7. FINANCIERO - Reports, Alerts, Inventory, Outputs, Reception
        $financiero = Role::firstOrCreate(
            ['name' => 'financiero'],
            [
                'display_name' => 'Financiero',
                'description' => 'Acceso a reportes, alertas, inventario, salidas y recepciones',
                'has_full_access' => false,
                'excluded_modules' => null,
            ]
        );
        // Update description if role already exists
        $financiero->update(['description' => 'Acceso a reportes, alertas, inventario, salidas y recepciones']);

        $financieroPermissions = Permission::whereIn('module', ['reports', 'inventory', 'outputs', 'reception'])
            ->get();
        $financiero->permissions()->sync($financieroPermissions);

        $this->command->info('Roles seeded successfully with the following structure:');
        $this->command->info('1. admin - Administrador (Full access)');
        $this->command->info('2. agronomist - Agrónomo (Technical + Outputs + Reception)');
        $this->command->info('3. supervisor - Supervisor (Reception + Outputs + Inventory)');
        $this->command->info('4. warehouse - Bodeguero (Outputs + Reception)');
        $this->command->info('5. farm - Operario de Finca (Outputs + Reception)');
        $this->command->info('6. purchasing - Encargado de Compras (Master + Purchases + Products + Outputs + Reception)');
        $this->command->info('7. financiero - Financiero (Reports + Inventory + Outputs + Reception)');
    }
}
