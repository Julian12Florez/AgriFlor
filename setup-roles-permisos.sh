#!/bin/bash

# Script para configurar el sistema de roles y permisos
# Ejecutar desde la raíz del proyecto: bash setup-roles-permisos.sh

echo "🚀 Configurando Sistema de Roles y Permisos - AgriFlor"
echo "========================================================="
echo ""

# Navegar al directorio backend
cd backend

echo "📝 Paso 1: Ejecutando migraciones..."
php artisan migrate --force
if [ $? -eq 0 ]; then
    echo "✅ Migraciones ejecutadas correctamente"
else
    echo "❌ Error al ejecutar migraciones"
    exit 1
fi
echo ""

echo "🌱 Paso 2: Ejecutando seeders (Roles y Permisos)..."
php artisan db:seed --class=PermissionsSeeder --force
if [ $? -eq 0 ]; then
    echo "✅ Permisos creados correctamente"
else
    echo "❌ Error al crear permisos"
    exit 1
fi

php artisan db:seed --class=RolesSeeder --force
if [ $? -eq 0 ]; then
    echo "✅ Roles creados correctamente"
else
    echo "❌ Error al crear roles"
    exit 1
fi
echo ""

echo "👤 Paso 3: Asignando rol de Administrador al primer usuario..."
php artisan tinker --execute="
\$adminRole = \App\Models\Role::where('name', 'admin')->first();
\$user = \App\Models\User::first();
if (\$user && \$adminRole) {
    \$user->role_id = \$adminRole->id;
    \$user->save();
    echo 'Usuario ' . \$user->email . ' asignado como Administrador\n';
} else {
    echo 'No se encontraron usuarios o rol de administrador\n';
}
"
echo ""

echo "📊 Resumen del sistema:"
php artisan tinker --execute="
echo '   Roles creados: ' . \App\Models\Role::count() . '\n';
echo '   Permisos creados: ' . \App\Models\Permission::count() . '\n';
echo '   Usuarios con rol asignado: ' . \App\Models\User::whereNotNull('role_id')->count() . '\n';
"
echo ""

echo "✨ ¡Configuración completada!"
echo ""
echo "📋 Próximos pasos:"
echo "   1. Verifica que tu usuario tenga el rol correcto"
echo "   2. Haz login y verifica que el JWT incluya 'roleData' y 'permissions'"
echo "   3. Lee el archivo IMPLEMENTACION_ROLES_EXPORTACIONES.md para continuar con las exportaciones"
echo ""
echo "🔐 Para asignar roles manualmente:"
echo "   php artisan tinker"
echo "   \$user = User::where('email', 'tu@email.com')->first();"
echo "   \$role = Role::where('name', 'supervisor')->first();"
echo "   \$user->role_id = \$role->id;"
echo "   \$user->save();"
echo ""
