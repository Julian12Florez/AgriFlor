# 📦 Instrucciones de Instalación - AgriFlor

Sistema de Gestión de Inventario Agrícola con Trazabilidad Total

**Versión:** 1.0
**Fecha:** 2026-01-19

---

## 📋 Requisitos Previos

### Backend (Laravel/PHP)
- **PHP:** >= 8.2
- **Composer:** >= 2.6
- **MySQL/MariaDB:** >= 8.0 / >= 10.6
- **Extensiones PHP:**
  - OpenSSL
  - PDO
  - Mbstring
  - Tokenizer
  - XML
  - Ctype
  - JSON
  - BCMath
  - Fileinfo
  - GD

### Frontend (React/TypeScript)
- **Node.js:** >= 20.x LTS
- **npm:** >= 10.x
- **Navegadores soportados:**
  - Chrome >= 90
  - Firefox >= 88
  - Safari >= 14
  - Edge >= 90

---

## 🚀 Instalación del Backend

### 1. Clonar o Ubicar el Proyecto

```bash
cd /home/julian/Documentos/AgriFlor/backend
```

### 2. Instalar Dependencias de Composer

```bash
composer install
```

**Nota:** Si encuentras errores de dependencias, ejecuta:
```bash
composer update
```

### 3. Configurar Variables de Entorno

```bash
# Copiar el archivo de ejemplo (si existe)
cp .env.example .env

# O crear uno nuevo
nano .env
```

**Configuración mínima requerida en `.env`:**

```env
APP_NAME="AgriFlor"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=agriflor
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_contraseña

JWT_SECRET=
JWT_TTL=60
JWT_REFRESH_TTL=20160

CACHE_DRIVER=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
```

### 4. Generar Claves de Aplicación

```bash
# Generar APP_KEY
php artisan key:generate

# Generar JWT_SECRET
php artisan jwt:secret
```

### 5. Crear la Base de Datos

```bash
# Conectar a MySQL
mysql -u root -p

# Crear la base de datos
CREATE DATABASE agriflor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

### 6. Ejecutar Migraciones

```bash
# Ejecutar todas las migraciones
php artisan migrate

# Si necesitas resetear (⚠️ CUIDADO: Borra todos los datos)
# php artisan migrate:fresh
```

**Orden de ejecución de migraciones (automático):**
1. Tablas base (users, brands, locations, etc.)
2. Tablas de relaciones (product_packaging_units, etc.)
3. Tablas de transacciones (purchases, receptions, etc.)
4. Tablas de aplicaciones (applications, application_products)

### 7. Ejecutar Seeders (Datos Iniciales)

```bash
# Si existen seeders configurados
php artisan db:seed

# O seeders específicos
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan db:seed --class=UsersSeeder
```

### 8. Configurar Permisos de Almacenamiento

```bash
# Crear enlace simbólico para storage
php artisan storage:link

# Configurar permisos (Linux/Mac)
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# O para desarrollo local
chmod -R 777 storage bootstrap/cache
```

### 9. Iniciar el Servidor de Desarrollo

```bash
# Opción 1: Servidor incorporado de PHP
php artisan serve
# Backend disponible en: http://localhost:8000

# Opción 2: Con puerto personalizado
php artisan serve --port=8080

# Opción 3: Accesible desde red local
php artisan serve --host=0.0.0.0 --port=8000
```

---

## 🎨 Instalación del Frontend

### 1. Navegar al Directorio del Frontend

```bash
cd /home/julian/Documentos/AgriFlor/frontend
```

### 2. Instalar Dependencias de npm

```bash
npm install
```

**Si encuentras errores, intenta:**
```bash
# Limpiar caché y reinstalar
rm -rf node_modules package-lock.json
npm cache clean --force
npm install
```

### 3. Configurar Variables de Entorno

```bash
# Crear archivo .env
nano .env
```

**Contenido del archivo `.env`:**

```env
VITE_API_URL=http://localhost:8000/api
```

**Para producción:**
```env
VITE_API_URL=https://api.agriflor.com/api
```

### 4. Iniciar el Servidor de Desarrollo

```bash
# Modo desarrollo con hot-reload
npm run dev
# Frontend disponible en: http://localhost:5173

# O especificar puerto
npm run dev -- --port 3000
```

### 5. Compilar para Producción

```bash
# Generar build optimizado
npm run build

# Los archivos se generan en: /dist
# Sirve estos archivos con Nginx, Apache, o servidor de tu elección
```

---

## 🗄️ Estructura de la Base de Datos

### Tablas Principales (38 tablas)

#### Módulo de Maestros
- `users` - Usuarios del sistema
- `roles` - Roles (admin, warehouse, agronomist, etc.)
- `permissions` - Permisos del sistema
- `products` - Catálogo de productos
- `brands` - Marcas de productos
- `suppliers` - Proveedores
- `locations` - Ubicaciones (bodegas, fincas)
- `farm_lots` - Lotes de cultivo

#### Módulo de Compras
- `purchases` - Órdenes de compra
- `purchase_items` - Items de compra
- `purchase_attachments` - Adjuntos de órdenes

#### Módulo de Recepciones
- `receptions` - Recepciones de productos
- `reception_items` - Items recibidos
- `reception_batches` - Lotes de recepción
- `reception_batch_items` - Items por lote

#### Módulo de Salidas
- `product_outputs` - Salidas de productos
- `output_products` - Productos en salidas
- `output_types` - Tipos de salida

#### Módulo de Inventario
- `inventory` - Inventario actual
- `inventory_movements` - Movimientos de inventario
- `alerts` - Alertas de stock

#### Módulo de Aplicaciones (NUEVO)
- `applications` - Aplicaciones de productos a campo
- `application_products` - Productos aplicados

#### Módulo Técnico
- `technical_recipes` - Recetas técnicas
- `technical_orders` - Órdenes técnicas
- `recipe_products` - Productos en recetas

---

## 👤 Usuario Administrador Inicial

### Crear Usuario Admin Manualmente

```bash
# Conectar a MySQL
mysql -u root -p agriflor

# Crear usuario admin (ajustar según tu seeder)
INSERT INTO users (id, name, email, password, role_id, status, created_at, updated_at)
VALUES (
  UUID(),
  'Administrador',
  'admin@agriflor.com',
  '$2y$12$xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', -- Ver nota abajo
  (SELECT id FROM roles WHERE name = 'admin'),
  'active',
  NOW(),
  NOW()
);
```

**Generar contraseña encriptada:**
```bash
php artisan tinker
>>> bcrypt('tu_contraseña')
# Copiar el resultado y usarlo en el INSERT
```

### Credenciales por Defecto (si hay seeder)
- **Email:** admin@agriflor.com
- **Contraseña:** admin123 (CAMBIAR EN PRODUCCIÓN)

---

## 🔧 Configuración Adicional

### Configurar JWT Authentication

El sistema usa JWT para autenticación. Asegúrate de:

1. Tener instalado `tymon/jwt-auth`
2. Publicar la configuración:
```bash
php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"
```

### Configurar CORS

En `config/cors.php` (o middleware):
```php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_methods' => ['*'],
'allowed_origins' => ['http://localhost:5173', 'http://localhost:3000'],
'allowed_headers' => ['*'],
'supports_credentials' => true,
```

### Optimizar para Producción

```bash
# Cachear configuración
php artisan config:cache

# Cachear rutas
php artisan route:cache

# Optimizar autoload
composer dump-autoload --optimize

# Cachear vistas
php artisan view:cache
```

---

## 🧪 Testing y Verificación

### Verificar Backend

```bash
# Probar la API
curl http://localhost:8000/api/health

# O con Postman/Insomnia:
# POST http://localhost:8000/api/auth/login
# Body: { "email": "admin@agriflor.com", "password": "admin123" }
```

### Verificar Frontend

1. Abrir navegador en `http://localhost:5173`
2. Intentar login con credenciales de admin
3. Verificar que el Dashboard carga correctamente
4. Probar navegación entre módulos

### Logs de Depuración

```bash
# Ver logs en tiempo real (Backend)
tail -f storage/logs/laravel.log

# Limpiar logs
echo "" > storage/logs/laravel.log

# Frontend (en consola del navegador)
# Abrir DevTools (F12) → Console
```

---

## 📊 Datos de Prueba (Opcional)

### Crear Datos de Ejemplo

```bash
# Si tienes factories configurados
php artisan db:seed --class=TestDataSeeder

# O manualmente desde tinker
php artisan tinker
>>> \App\Models\Product::factory(10)->create()
>>> \App\Models\Location::factory(5)->create()
```

---

## 🚨 Solución de Problemas Comunes

### Error: "No application encryption key"
```bash
php artisan key:generate
```

### Error: "JWT secret not set"
```bash
php artisan jwt:secret
```

### Error: "SQLSTATE[HY000] [1045] Access denied"
- Verificar credenciales en `.env`
- Verificar que el usuario tiene permisos en la BD

### Error: "Class 'X' not found"
```bash
composer dump-autoload
```

### Error: Frontend no conecta con Backend
- Verificar `VITE_API_URL` en `.env` del frontend
- Verificar CORS en backend
- Verificar que el backend está corriendo

### Error: "419 CSRF token mismatch"
- Asegúrate de que `supports_credentials: true` en CORS
- Verifica la configuración de Sanctum

---

## 📚 Recursos Adicionales

### Documentación
- Laravel: https://laravel.com/docs
- React: https://react.dev
- Ant Design: https://ant.design
- TypeScript: https://www.typescriptlang.org/docs

### Comandos Útiles

```bash
# Backend
php artisan list                    # Ver todos los comandos
php artisan route:list             # Ver todas las rutas
php artisan migrate:status         # Ver estado de migraciones
php artisan queue:work             # Procesar cola (si se usa)

# Frontend
npm run dev                        # Desarrollo
npm run build                      # Producción
npm run preview                    # Preview del build
npm run lint                       # Linter
```

---

## 🔒 Seguridad

### Antes de Producción

1. **Cambiar contraseñas por defecto**
2. **Configurar `APP_ENV=production`**
3. **Configurar `APP_DEBUG=false`**
4. **Usar HTTPS (SSL/TLS)**
5. **Configurar firewall**
6. **Backups automáticos**
7. **Limitar CORS a dominios específicos**
8. **Configurar rate limiting**

---

## 📞 Soporte

Para problemas o dudas:
1. Revisar los logs (`storage/logs/laravel.log`)
2. Revisar la consola del navegador (F12)
3. Consultar este documento
4. Consultar la documentación de Laravel/React

---

**Documento creado:** 2026-01-19
**Última actualización:** 2026-01-19
**Versión:** 1.0
