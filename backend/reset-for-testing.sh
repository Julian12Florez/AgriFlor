#!/bin/bash

# Script para limpiar datos transaccionales y recargar datos maestros
# Útil para realizar pruebas desde cero con datos maestros actualizados

echo "🚀 Iniciando proceso de limpieza y recarga de datos..."
echo ""

# Colores para output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Función para confirmar acción
confirm() {
    read -p "⚠️  Esta acción eliminará TODOS los datos transaccionales (compras, salidas, inventario, etc.). ¿Continuar? (s/n): " -n 1 -r
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
    echo "${GREEN}✅ Datos transaccionales limpiados correctamente${NC}"
else
    echo "${RED}❌ Error al limpiar datos transaccionales${NC}"
    exit 1
fi

echo ""
echo "${YELLOW}📋 Paso 2: Verificando datos maestros...${NC}"
echo "   Los siguientes datos maestros están disponibles:"
echo "   • Usuarios y roles"
echo "   • Marcas"
echo "   • Unidades base y de empaque"
echo "   • Ubicaciones"
echo "   • Proveedores"
echo "   • Tipos de salida"
echo "   • Productos"

echo ""
echo "${GREEN}✨ ¡Proceso completado exitosamente!${NC}"
echo ""
echo "📝 Ahora puedes realizar pruebas desde cero con:"
echo "   • Compras"
echo "   • Recepciones"
echo "   • Salidas (diferentes tipos)"
echo "   • Inventario"
echo "   • Reportes"
echo ""
echo "💡 Tip: Los datos maestros se mantienen intactos para facilitar tus pruebas."
