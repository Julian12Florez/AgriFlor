<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
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

        // 2. SUPERVISOR - Access only to Reception and Outputs
        $supervisor = Role::firstOrCreate(
            ['name' => 'supervisor'],
            [
                'display_name' => 'Supervisor',
                'description' => 'Acceso solo a módulos de Recepción y Salida',
                'has_full_access' => false,
                'excluded_modules' => null,
            ]
        );

        $supervisorPermissions = Permission::whereIn('module', ['reception', 'outputs'])
            ->get();
        $supervisor->permissions()->syncWithoutDetaching($supervisorPermissions);

        // 3. WAREHOUSE OPERATOR (Bodeguero) - Access only to Reception and Outputs
        $warehouseOperator = Role::firstOrCreate(
            ['name' => 'warehouse_operator'],
            [
                'display_name' => 'Bodeguero',
                'description' => 'Acceso solo a módulos de Recepción y Salida',
                'has_full_access' => false,
                'excluded_modules' => null,
            ]
        );

        $warehouseOperatorPermissions = Permission::whereIn('module', ['reception', 'outputs'])
            ->get();
        $warehouseOperator->permissions()->syncWithoutDetaching($warehouseOperatorPermissions);

        // 4. FARM OPERATOR (Operario de Finca) - Access only to Reception and Outputs
        $farmOperator = Role::firstOrCreate(
            ['name' => 'farm_operator'],
            [
                'display_name' => 'Operario de Finca',
                'description' => 'Acceso solo a módulos de Recepción y Salida',
                'has_full_access' => false,
                'excluded_modules' => null,
            ]
        );

        $farmOperatorPermissions = Permission::whereIn('module', ['reception', 'outputs'])
            ->get();
        $farmOperator->permissions()->syncWithoutDetaching($farmOperatorPermissions);

        // 5. PURCHASING - Access to all modules EXCEPT Administration
        $purchasing = Role::firstOrCreate(
            ['name' => 'purchasing'],
            [
                'display_name' => 'Compras',
                'description' => 'Acceso a todos los módulos EXCEPTO Administración',
                'has_full_access' => false,
                'excluded_modules' => ['admin'], // Explicitly exclude admin module
            ]
        );

        // Get all permissions except admin module
        $purchasingPermissions = Permission::where('module', '!=', 'admin')->get();
        $purchasing->permissions()->syncWithoutDetaching($purchasingPermissions);

        $this->command->info('Roles seeded successfully with the following structure:');
        $this->command->info('1. Administrador - Full access (including Admin module)');
        $this->command->info('2. Supervisor - Reception & Outputs only');
        $this->command->info('3. Bodeguero - Reception & Outputs only');
        $this->command->info('4. Operario de Finca - Reception & Outputs only');
        $this->command->info('5. Compras - All modules EXCEPT Admin');
    }
}
