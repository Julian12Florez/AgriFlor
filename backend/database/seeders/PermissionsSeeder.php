<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PermissionsSeeder extends Seeder
{
    /**
     * Los permisos se definen en UN solo sitio: App\Support\PermissionCatalog.
     * Este seeder solo los vuelca a la base (crea, actualiza y borra obsoletos).
     */
    public function run(): void
    {
        PermissionCatalog::syncDefinitions();
    }
}
