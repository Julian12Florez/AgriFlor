#!/bin/bash

# Script de instalación del sistema de roles y permisos
# AgriFlor - Sistema de Gestión Agrícola

echo "=================================="
echo "Instalación de Roles y Permisos"
echo "=================================="
echo ""

# Verificar que estamos en el directorio correcto
if [ ! -f "artisan" ]; then
    echo "Error: Este script debe ejecutarse desde el directorio raíz del proyecto Laravel"
    exit 1
fi

# Paso 1: Ejecutar migraciones
echo "Paso 1: Ejecutando migraciones..."
php artisan migrate --force

if [ $? -ne 0 ]; then
    echo "Error: Las migraciones fallaron"
    exit 1
fi

echo "✓ Migraciones completadas exitosamente"
echo ""

# Paso 2: Ejecutar seeder de permisos
echo "Paso 2: Creando permisos..."
php artisan db:seed --class=PermissionsSeeder --force

if [ $? -ne 0 ]; then
    echo "Error: El seeder de permisos falló"
    exit 1
fi

echo "✓ Permisos creados exitosamente"
echo ""

# Paso 3: Ejecutar seeder de roles
echo "Paso 3: Creando roles..."
php artisan db:seed --class=RolesSeeder --force

if [ $? -ne 0 ]; then
    echo "Error: El seeder de roles falló"
    exit 1
fi

echo "✓ Roles creados exitosamente"
echo ""

# Paso 4: Asignar rol de admin al primer usuario
echo "Paso 4: Asignando rol de administrador al primer usuario..."
php artisan tinker --execute="
    \$adminRole = App\Models\Role::where('name', 'admin')->first();
    \$firstUser = App\Models\User::first();
    if (\$adminRole && \$firstUser) {
        \$firstUser->role_id = \$adminRole->id;
        \$firstUser->save();
        echo 'Usuario ' . \$firstUser->email . ' ahora es administrador';
    } else {
        echo 'No se encontró usuario o rol admin';
    }
"

echo ""
echo "=================================="
echo "✓ Instalación completada exitosamente"
echo "=================================="
echo ""
echo "Roles creados:"
echo "  - Administrador (admin)"
echo "  - Supervisor (supervisor)"
echo "  - Bodeguero (warehouse_operator)"
echo "  - Operario de Finca (farm_operator)"
echo "  - Compras (purchasing)"
echo ""
echo "Puedes verificar los roles con:"
echo "  php artisan tinker --execute=\"App\Models\Role::all(['name', 'display_name'])\""
echo ""
