#!/bin/bash

# Script completo para resetear la base de datos con opción de recargar seeders maestros
# Útil cuando los seeders maestros han sido actualizados

echo "🚀 Iniciando proceso completo de reset de base de datos..."
echo ""

# Colores para output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Función para confirmar acción
confirm() {
    echo "${RED}⚠️  ADVERTENCIA: Esta acción realizará lo siguiente:${NC}"
    echo "   1. Eliminará TODOS los datos transaccionales"
    echo "   2. Eliminará y recargará TODOS los datos maestros"
    echo ""
    read -p "¿Estás seguro de continuar? (s/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Ss]$ ]]
    then
        echo "${RED}❌ Operación cancelada${NC}"
        exit 1
    fi
}

# Confirmar con el usuario
confirm

echo ""
echo "${YELLOW}📋 Paso 1: Limpiando datos transaccionales...${NC}"
php artisan db:seed --class=CleanTransactionalDataSeeder

if [ $? -eq 0 ]; then
    echo "${GREEN}✅ Datos transaccionales limpiados${NC}"
else
    echo "${RED}❌ Error al limpiar datos transaccionales${NC}"
    exit 1
fi

echo ""
echo "${YELLOW}📋 Paso 2: Ejecutando migrate:fresh...${NC}"
php artisan migrate:fresh --force

if [ $? -eq 0 ]; then
    echo "${GREEN}✅ Base de datos recreada${NC}"
else
    echo "${RED}❌ Error al recrear base de datos${NC}"
    exit 1
fi

echo ""
echo "${YELLOW}📋 Paso 3: Recargando seeders maestros...${NC}"
php artisan db:seed

if [ $? -eq 0 ]; then
    echo "${GREEN}✅ Seeders maestros cargados${NC}"
else
    echo "${RED}❌ Error al cargar seeders${NC}"
    exit 1
fi

echo ""
echo "${GREEN}✨ ¡Proceso completado exitosamente!${NC}"
echo ""
echo "${BLUE}📊 Estado de la base de datos:${NC}"
echo "   ✅ Permisos y roles"
echo "   ✅ Usuarios (admin)"
echo "   ✅ Marcas"
echo "   ✅ Unidades base"
echo "   ✅ Unidades de empaque"
echo "   ✅ Ubicaciones"
echo "   ✅ Proveedores"
echo "   ✅ Tipos de salida"
echo "   ✅ Productos"
echo ""
echo "   ❌ Compras (limpias)"
echo "   ❌ Inventario (limpio)"
echo "   ❌ Salidas (limpias)"
echo "   ❌ Recepciones (limpias)"
echo ""
echo "💡 Ahora puedes iniciar tus pruebas desde cero."
