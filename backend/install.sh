#!/bin/bash

# Script de instalación para AgriFlor Backend
# Este script configura el proyecto Laravel con Docker

echo "====================================="
echo " AgriFlor Backend - Instalación"
echo "====================================="
echo ""

# Detener contenedores si existen
echo "[1/6] Deteniendo contenedores existentes..."
docker-compose down 2>/dev/null

# Construir imágenes
echo "[2/6] Construyendo imágenes Docker..."
docker-compose build

# Levantar servicios
echo "[3/6] Levantando servicios..."
docker-compose up -d

# Esperar a que MySQL esté listo
echo "[4/6] Esperando a que MySQL esté listo..."
sleep 15

# Instalar Laravel
echo "[5/6] Instalando Laravel..."
docker-compose exec -T app composer create-project --prefer-dist laravel/laravel tmp "11.*"
docker-compose exec -T app sh -c "shopt -s dotglob && mv tmp/* ./ && rm -rf tmp"

# Configurar permisos
echo "[6/6] Configurando permisos..."
docker-compose exec -T app chmod -R 775 storage bootstrap/cache
docker-compose exec -T app chown -R agriflor:www-data storage bootstrap/cache

# Copiar .env
docker-compose exec -T app cp .env.example .env

# Generar key
docker-compose exec -T app php artisan key:generate

echo ""
echo "====================================="
echo " Instalación completada!"
echo "====================================="
echo ""
echo "Servicios disponibles:"
echo "- API Laravel: http://localhost:8000"
echo "- MySQL: localhost:3307"
echo "- phpMyAdmin: http://localhost:8083"
echo "- Redis: localhost:6380"
echo ""
echo "Para acceder al contenedor:"
echo "  docker-compose exec app bash"
echo ""
echo "Para ver logs:"
echo "  docker-compose logs -f"
echo ""
