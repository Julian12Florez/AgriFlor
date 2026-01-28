#!/bin/bash

# Script de instalación del sistema de roles y permisos para Docker
# AgriFlor - Sistema de Gestión Agrícola

echo "===================================================================="
echo "   Instalación de Sistema de Roles, Permisos y Exportaciones"
echo "   AgriFlor - Sistema de Gestión Agrícola"
echo "===================================================================="
echo ""

# Colores para output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Verificar que Docker está corriendo
if ! docker ps > /dev/null 2>&1; then
    echo -e "${RED}Error: Docker no está corriendo o no tienes permisos${NC}"
    exit 1
fi

# Verificar que el contenedor existe
if ! docker ps -a | grep -q "agriflor-app"; then
    echo -e "${RED}Error: El contenedor agriflor-app no existe${NC}"
    exit 1
fi

# Verificar que el contenedor está corriendo
if ! docker ps | grep -q "agriflor-app"; then
    echo -e "${YELLOW}Advertencia: El contenedor agriflor-app no está corriendo${NC}"
    echo "Iniciando contenedor..."
    docker start agriflor-app
    sleep 3
fi

echo -e "${BLUE}Paso 1/5:${NC} Ejecutando migraciones..."
echo "--------------------------------------------------------------------"
if docker exec agriflor-app php artisan migrate --force; then
    echo -e "${GREEN}✓ Migraciones completadas exitosamente${NC}"
else
    echo -e "${RED}✗ Error: Las migraciones fallaron${NC}"
    exit 1
fi
echo ""

echo -e "${BLUE}Paso 2/5:${NC} Creando permisos..."
echo "--------------------------------------------------------------------"
if docker exec agriflor-app php artisan db:seed --class=PermissionsSeeder --force; then
    echo -e "${GREEN}✓ Permisos creados exitosamente${NC}"
else
    echo -e "${RED}✗ Error: El seeder de permisos falló${NC}"
    exit 1
fi
echo ""

echo -e "${BLUE}Paso 3/5:${NC} Creando roles..."
echo "--------------------------------------------------------------------"
if docker exec agriflor-app php artisan db:seed --class=RolesSeeder --force; then
    echo -e "${GREEN}✓ Roles creados exitosamente${NC}"
else
    echo -e "${RED}✗ Error: El seeder de roles falló${NC}"
    exit 1
fi
echo ""

echo -e "${BLUE}Paso 4/5:${NC} Asignando rol de administrador al primer usuario..."
echo "--------------------------------------------------------------------"
docker exec agriflor-app php artisan tinker --execute="
    \$adminRole = App\Models\Role::where('name', 'admin')->first();
    \$firstUser = App\Models\User::first();
    if (\$adminRole && \$firstUser) {
        \$firstUser->role_id = \$adminRole->id;
        \$firstUser->save();
        echo 'Usuario ' . \$firstUser->email . ' ahora es administrador' . PHP_EOL;
    } else {
        echo 'Advertencia: No se encontró usuario o rol admin' . PHP_EOL;
    }
"
echo -e "${GREEN}✓ Rol asignado${NC}"
echo ""

echo -e "${BLUE}Paso 5/5:${NC} Verificando instalación..."
echo "--------------------------------------------------------------------"
docker exec agriflor-app php artisan tinker --execute="
    echo '=== RESUMEN DE INSTALACIÓN ===' . PHP_EOL;
    echo PHP_EOL;
    echo 'Roles creados: ' . App\Models\Role::count() . PHP_EOL;
    App\Models\Role::all(['name', 'display_name'])->each(function(\$role) {
        echo '  - ' . \$role->display_name . ' (' . \$role->name . ')' . PHP_EOL;
    });
    echo PHP_EOL;
    echo 'Permisos totales: ' . App\Models\Permission::count() . PHP_EOL;
    echo 'Usuarios con rol asignado: ' . App\Models\User::whereNotNull('role_id')->count() . PHP_EOL;
"
echo ""

echo "===================================================================="
echo -e "${GREEN}✓ ¡Instalación completada exitosamente!${NC}"
echo "===================================================================="
echo ""
echo -e "${YELLOW}Próximos pasos:${NC}"
echo ""
echo "1. Verificar que el frontend está corriendo:"
echo "   cd frontend && npm run dev"
echo ""
echo "2. Login con el usuario administrador:"
echo "   Email: admin@agriflor.com (o el primer usuario creado)"
echo ""
echo "3. Verificar menú dinámico:"
echo "   - Debe ver todas las secciones incluyendo 'Administración'"
echo ""
echo "4. Probar exportación de reportes:"
echo "   - Ir a Reportes > Reportes"
echo "   - Generar cualquier reporte"
echo "   - Verificar botones 'Exportar Excel' y 'Exportar PDF'"
echo ""
echo -e "${BLUE}Documentación completa en:${NC}"
echo "  - GUIA_INSTALACION_COMPLETA.md"
echo "  - backend/IMPLEMENTACION_ROLES_EXPORTACIONES.md"
echo ""
echo -e "${GREEN}¡Sistema listo para usar! 🚀${NC}"
echo ""
