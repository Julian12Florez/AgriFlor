<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // IMPORTANT: Order matters due to foreign key dependencies
        // Seeders must be called in this specific order to satisfy database constraints
        $this->call([
            PermissionsSeeder::class,    // 1. Permissions (no dependencies) - MUST BE FIRST
            RolesSeeder::class,          // 2. Roles (depends on permissions) - MUST BE SECOND
            UserSeeder::class,           // 3. Users (depends on roles)
            BrandSeeder::class,          // 4. Brands (no dependencies)
            BaseUnitSeeder::class,       // 5. Base Units (no dependencies)
            PackagingUnitSeeder::class,  // 6. Packaging Units (depends on base_units)
            LocationSeeder::class,       // 7. Locations (no dependencies)
            SupplierSeeder::class,       // 8. Suppliers (no dependencies)
            OutputTypeSeeder::class,     // 9. Output Types (no dependencies)
            ProductSeeder::class,        // 10. Products (depends on brands, packaging_units)
        ]);
    }
}
