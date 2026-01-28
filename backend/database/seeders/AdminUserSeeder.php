<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get admin role (must exist first)
        $adminRole = \App\Models\Role::where('name', 'admin')->first();

        if (!$adminRole) {
            $this->command->error('ERROR: Admin role not found. Run PermissionsSeeder and RolesSeeder first.');
            return;
        }

        $adminEmail = 'admin@agriflor.com';

        // Use updateOrCreate to handle both new and existing users
        $admin = User::updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Administrador',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'role_id' => $adminRole->id,
                'status' => 'active',
            ]
        );

        $this->command->info('Admin user created/updated successfully!');
        $this->command->info('Email: ' . $adminEmail);
        $this->command->info('Password: admin123');
        $this->command->info('Role: Administrador (ID: ' . $adminRole->id . ')');
        $this->command->newLine();
        $this->command->warn('⚠️  IMPORTANTE: Cambie la contraseña en producción');
    }
}
