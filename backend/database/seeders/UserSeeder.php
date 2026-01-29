<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;

class UserSeeder extends Seeder
{


    /**
     * Run the database seeds.
     *
     * Creates test users for each role defined in RolesSeeder.
     * IMPORTANT: Run PermissionsSeeder and RolesSeeder before this seeder.
     *
     * Test Credentials:
     * - Admin: admin@agriflor.com / admin123
     * - Supervisor: supervisor@agriflor.com / supervisor123
     * - Bodeguero: bodega@agriflor.com / bodega123
     * - Operario Finca: finca@agriflor.com / finca123
     * - Compras: compras@agriflor.com / compras123
     * - Financiero: financiero@agriflor.com / financiero123
     */
    public function run(): void
    {
        // Get roles from database (must be seeded first)
        $adminRole = Role::where('name', 'admin')->first();
        $supervisorRole = Role::where('name', 'supervisor')->first();
        $warehouseRole = Role::where('name', 'warehouse')->first();
        $farmRole = Role::where('name', 'farm')->first();
        $purchasingRole = Role::where('name', 'purchasing')->first();
        $financieroRole = Role::where('name', 'financiero')->first();

        // Verify required roles exist
        $missingRoles = [];
        if (!$adminRole) $missingRoles[] = 'admin';
        if (!$supervisorRole) $missingRoles[] = 'supervisor';
        if (!$warehouseRole) $missingRoles[] = 'warehouse';
        if (!$farmRole) $missingRoles[] = 'farm';
        if (!$purchasingRole) $missingRoles[] = 'purchasing';
        if (!$financieroRole) $missingRoles[] = 'financiero';

        if (!empty($missingRoles)) {
            $this->command->error('ERROR: Roles not found: ' . implode(', ', $missingRoles));
            $this->command->info('Please run PermissionsSeeder and RolesSeeder first:');
            $this->command->info('php artisan db:seed --class=PermissionsSeeder');
            $this->command->info('php artisan db:seed --class=RolesSeeder');
            return;
        }

        $users = [
            [
                'name' => 'Administrador AgriFlor',
                'email' => 'admin@agriflor.com',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'role_id' => $adminRole->id,
                'status' => 'active',
            ],
            [
                'name' => 'Carlos Rodríguez (Supervisor)',
                'email' => 'supervisor@agriflor.com',
                'password' => Hash::make('supervisor123'),
                'role' => 'supervisor',
                'role_id' => $supervisorRole->id,
                'status' => 'active',
            ],
            [
                'name' => 'María González (Bodeguera)',
                'email' => 'bodega@agriflor.com',
                'password' => Hash::make('bodega123'),
                'role' => 'warehouse',
                'role_id' => $warehouseRole->id,
                'status' => 'active',
            ],
            [
                'name' => 'Pedro López (Operario Finca)',
                'email' => 'finca@agriflor.com',
                'password' => Hash::make('finca123'),
                'role' => 'farm',
                'role_id' => $farmRole->id,
                'status' => 'active',
            ],
            [
                'name' => 'Ana Martínez (Compras)',
                'email' => 'compras@agriflor.com',
                'password' => Hash::make('compras123'),
                'role' => 'purchasing',
                'role_id' => $purchasingRole->id,
                'status' => 'active',
            ],
            [
                'name' => 'Laura Sánchez (Financiera)',
                'email' => 'financiero@agriflor.com',
                'password' => Hash::make('financiero123'),
                'role' => 'financiero',
                'role_id' => $financieroRole->id,
                'status' => 'active',
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }

        $this->command->info('Users seeded successfully:');
        $this->command->table(
            ['Email', 'Role', 'Password'],
            [
                ['admin@agriflor.com', 'Administrador', 'admin123'],
                ['supervisor@agriflor.com', 'Supervisor', 'supervisor123'],
                ['bodega@agriflor.com', 'Bodeguero', 'bodega123'],
                ['finca@agriflor.com', 'Operario Finca', 'finca123'],
                ['compras@agriflor.com', 'Encargado Compras', 'compras123'],
                ['financiero@agriflor.com', 'Financiero', 'financiero123'],
            ]
        );
    }
}
/*
ultrathink me puedes crear un prompt para dar indicaciones a un agente para que analice todo el codigo actual se contextualice correctamente tanto frontend como back y en caso de que yo le solicite que se contextualice o analice un modulo especifico también
  lo haga, quiero decirle al agente analiza x modulo y lo haga completamente de la amenra mas profesional como experto en laravel, mysql y react o analiza todo el proyecto el agente debe ser totalmetne inteligente para entregar el resultado de los analisis
  incluso proponer mejoras en la logica o implementación, este mismo agente despues de analizar debe preguntar que queremos corregir, impleemtnar o trabajr de nuevo tatno en frotnend como backend y finalmente realizar pruebas   */
