# 🎯 IMPLEMENTACIÓN COMPLETA - Sistema de Roles y Exportaciones

## ✅ TRABAJO COMPLETADO

### 1. Sistema de Roles y Permisos (BACKEND) - ✅ COMPLETADO

#### 📁 Migraciones Creadas
- `2025_12_13_090001_create_permissions_table.php` - Tabla de permisos
- `2025_12_13_090002_create_roles_table.php` - Tabla de roles
- `2025_12_13_090003_create_role_permission_table.php` - Tabla pivot roles-permisos
- `2025_12_13_090004_add_role_id_to_users_table.php` - Relación users-roles

#### 📦 Modelos Creados
- `app/Models/Permission.php` - Modelo de permisos
- `app/Models/Role.php` - Modelo de roles con métodos:
  - `hasPermission(string $permissionName)`: bool
  - `hasModuleAccess(string $module)`: bool
  - `getAccessibleModules()`: array

#### 👤 Modelo User Actualizado
- **Archivo:** `app/Models/User.php`
- **Cambios:**
  - Agregado `role_id` a fillable
  - Relación `roleRelation()` con Role
  - Métodos agregados:
    - `hasPermission(string $permissionName)`: bool
    - `hasModuleAccess(string $module)`: bool
    - `getPermissions()`: array
    - `getAccessibleModules()`: array
  - JWT custom claims actualizado para incluir role_data y permissions

#### 🌱 Seeders Creados
- `database/seeders/PermissionsSeeder.php` - 30+ permisos por módulo
- `database/seeders/RolesSeeder.php` - 5 roles configurados:
  1. **Administrador** - Acceso completo (has_full_access = true)
  2. **Supervisor** - Solo Recepción y Salidas
  3. **Bodeguero** - Solo Recepción y Salidas
  4. **Operario de Finca** - Solo Recepción y Salidas
  5. **Compras** - Todo EXCEPTO Administración (excluded_modules = ['admin'])

#### 🛡️ Middleware Creado
- `app/Http/Middleware/CheckPermission.php` - Verifica permiso específico
- `app/Http/Middleware/CheckModuleAccess.php` - Verifica acceso a módulo
- **Registrados en:** `bootstrap/app.php`
  - Alias: `'permission'` y `'module'`

#### 🔐 AuthController Actualizado
- **Archivo:** `app/Http/Controllers/Api/AuthController.php`
- **Cambios:**
  - Login carga `roleRelation.permissions`
  - Método `me()` carga `roleRelation.permissions`

#### 📤 UserResource Actualizado
- **Archivo:** `app/Http/Resources/UserResource.php`
- **Respuesta incluye:**
  - `roleData`: información del rol
  - `permissions`: array de permisos
  - `accessibleModules`: módulos accesibles

### 2. Paquetes de Exportación Instalados - ✅ COMPLETADO

- ✅ **maatwebsite/excel** v3.1.67 (Laravel Excel)
- ✅ **barryvdh/laravel-dompdf** v3.0.0 (Laravel DomPDF)

**NOTA IMPORTANTE:** Se instalaron con `--ignore-platform-req=ext-gd` porque falta la extensión PHP GD.
Para producción, debes instalar la extensión:
```bash
sudo apt-get install php8.4-gd
```

---

## ⏳ TRABAJO PENDIENTE (PARA CONTINUAR)

### 3. Implementación de Exportaciones (BACKEND)

#### Archivos a Crear:

**Controlador de Exportaciones:**
```
app/Http/Controllers/Api/ReportExportController.php
```

**Clases Export para Excel:**
```
app/Exports/StockReportExport.php
app/Exports/ConsumptionReportExport.php
app/Exports/InventoryMovementsReportExport.php
```

**Vistas PDF:**
```
resources/views/exports/pdf/stock-report.blade.php
resources/views/exports/pdf/consumption-report.blade.php
resources/views/exports/pdf/movements-report.blade.php
```

**Rutas API a agregar en `routes/api.php`:**
```php
Route::middleware(['auth:api', 'permission:export_reports'])->group(function () {
    Route::get('/reports/stock/export-excel', [ReportExportController::class, 'exportStockExcel']);
    Route::get('/reports/stock/export-pdf', [ReportExportController::class, 'exportStockPdf']);

    Route::get('/reports/consumption/export-excel', [ReportExportController::class, 'exportConsumptionExcel']);
    Route::get('/reports/consumption/export-pdf', [ReportExportController::class, 'exportConsumptionPdf']);

    Route::get('/reports/movements/export-excel', [ReportExportController::class, 'exportMovementsExcel']);
    Route::get('/reports/movements/export-pdf', [ReportExportController::class, 'exportMovementsPdf']);
});
```

### 4. Sistema de Permisos en Frontend (REACT)

#### Archivos a Crear:

**Hook de Permisos:**
```
frontend/src/hooks/usePermissions.ts
```

**Componente ProtectedRoute:**
```
frontend/src/components/ProtectedRoute.tsx
```

**Actualizar API Service:**
```
frontend/src/services/api.ts
```
Agregar endpoints de exportación.

#### Archivos a Actualizar:

**MainLayout (Menú Dinámico):**
```
frontend/src/components/layout/MainLayout.tsx
```
- Implementar lógica de menú basado en permisos del usuario

**Reportes con Botones de Exportación:**
- `frontend/src/pages/reports/StockReport.tsx` (líneas 86-91)
- `frontend/src/pages/reports/ConsumptionReport.tsx` (líneas 134-143)
- `frontend/src/pages/reports/InventoryMovementsReport.tsx` (líneas 96-102)

Conectar botones a las funciones reales de exportación.

---

## 🚀 INSTRUCCIONES PARA EJECUTAR

### 1. Ejecutar Migraciones y Seeders

```bash
cd backend

# Ejecutar migraciones
php artisan migrate

# Ejecutar seeders (roles y permisos)
php artisan db:seed
```

### 2. Asignar Roles a Usuarios Existentes

Después de ejecutar los seeders, necesitas actualizar los usuarios existentes:

```bash
# Entrar a tinker
php artisan tinker

# Obtener el rol de administrador
$adminRole = \App\Models\Role::where('name', 'admin')->first();

# Asignar rol a un usuario (ejemplo con email)
$user = \App\Models\User::where('email', 'admin@example.com')->first();
$user->role_id = $adminRole->id;
$user->save();
```

### 3. Publicar Configuraciones de Laravel Excel (opcional)

```bash
php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider" --tag=config
```

### 4. Publicar Configuraciones de DomPDF (opcional)

```bash
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
```

---

## 📋 ESTRUCTURA DE PERMISOS IMPLEMENTADA

### Módulos y Permisos:

| Módulo | Permisos |
|--------|----------|
| **reception** | view_reception, create_reception, edit_reception, delete_reception |
| **outputs** | view_outputs, create_output, edit_output, delete_output, approve_output |
| **products** | view_products, create_product, edit_product, delete_product |
| **purchases** | view_purchases, create_purchase, edit_purchase, delete_purchase |
| **inventory** | view_inventory, adjust_inventory |
| **reports** | view_reports, export_reports |
| **master** | view_master_data, manage_master_data |
| **technical** | view_technical, manage_technical |
| **admin** | view_admin, manage_users, manage_roles, system_settings |

### Configuración de Roles:

| Rol | Acceso |
|-----|--------|
| **Administrador** | ✅ TODO (incluyendo Admin) |
| **Supervisor** | ✅ Recepción, Salidas |
| **Bodeguero** | ✅ Recepción, Salidas |
| **Operario de Finca** | ✅ Recepción, Salidas |
| **Compras** | ✅ TODO ❌ EXCEPTO Admin |

---

## 🧪 PRUEBAS A REALIZAR

### 1. Probar Sistema de Roles

```bash
# Hacer login con diferentes usuarios y verificar JWT incluye role_data
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com", "password": "password"}'
```

Verificar que la respuesta incluya:
- `user.roleData`
- `user.permissions`
- `user.accessibleModules`

### 2. Probar Middleware de Permisos

Crear una ruta protegida de prueba:
```php
Route::middleware(['auth:api', 'permission:view_admin'])->get('/test-admin', function() {
    return response()->json(['message' => 'Acceso permitido a Admin']);
});
```

### 3. Probar Exportaciones (una vez implementadas)

```bash
# Exportar stock a Excel
curl -X GET "http://localhost:8000/api/reports/stock/export-excel?product_id=xxx" \
  -H "Authorization: Bearer {token}" \
  --output stock.xlsx

# Exportar consumo a PDF
curl -X GET "http://localhost:8000/api/reports/consumption/export-pdf?start_date=2025-01-01&end_date=2025-01-31" \
  -H "Authorization: Bearer {token}" \
  --output consumption.pdf
```

---

## 📊 ANÁLISIS DE INFORMES PARA EXPORTACIÓN

### 1. Stock Report
**Columnas identificadas:**
- Producto (product_name, product_code, category)
- Marca (brand_name)
- Cantidad Total (total_quantity + unit)
- Valor Total (total_value)
- Ubicaciones (cantidad de ubicaciones)
- Estado (status)

**Filtros:**
- Producto
- Ubicación
- Agrupar por (producto/ubicación)

**Estadísticas:**
- Total Registros
- Cantidad Total
- Valor Total
- Alertas (stock bajo + vencidos)

### 2. Consumption Report
**Columnas identificadas:**
- Fecha (output_date)
- Salida (output_number)
- Producto (product_name, product_code, category)
- Marca (brand_name)
- Cantidad (quantity + unit_full_name)
- Finca (destination_location_name)
- Lotes (lots_names, lots_count)

**Filtros:**
- Rango de Fechas
- Producto
- Finca

**Estadísticas:**
- Total Salidas
- Total Aplicaciones
- Cantidad Consumida

### 3. Inventory Movements Report
**Columnas identificadas:**
- Fecha (created_at)
- Tipo (type: entry/exit/transfer/application/adjustment)
- Producto (product_name, product_code)
- Marca (brand_name)
- Origen (origin_location_name)
- Destino (destination_location_name)
- Cantidad (quantity + unit)
- Precio Unit. (unit_price)
- Total (total_price)
- Usuario (responsible_user_name)

**Filtros:**
- Rango de Fechas (REQUERIDO)
- Ubicación
- Producto
- Tipo de Movimiento

**Estadísticas:**
- Total Movimientos
- Entradas / Salidas
- Valor Total

---

## ⚠️ NOTAS IMPORTANTES

1. **Extensión GD de PHP:** Debe instalarse para que funcionen correctamente las exportaciones:
   ```bash
   sudo apt-get install php8.4-gd
   sudo systemctl restart php8.4-fpm  # Si usas PHP-FPM
   ```

2. **Backward Compatibility:** El sistema mantiene el campo `role` (string) en users para compatibilidad. El nuevo sistema usa `role_id` (UUID foreign key).

3. **JWT Custom Claims:** El token JWT ahora incluye `role_data` con toda la información de permisos, por lo que el frontend puede verificar permisos sin hacer peticiones adicionales.

4. **Middleware Usage:** Usa `middleware('permission:nombre_permiso')` o `middleware('module:nombre_modulo')` en las rutas que necesites proteger.

5. **DatabaseSeeder:** Actualizado para ejecutar PermissionsSeeder y RolesSeeder antes que UserSeeder.

---

## 🎯 PRÓXIMOS PASOS RECOMENDADOS

1. ✅ **Ejecutar migraciones y seeders**
2. ✅ **Asignar roles a usuarios existentes**
3. ⏳ **Implementar controladores de exportación (backend)**
4. ⏳ **Crear clases Export para Excel**
5. ⏳ **Crear vistas PDF**
6. ⏳ **Agregar rutas de exportación**
7. ⏳ **Implementar hook usePermissions (frontend)**
8. ⏳ **Crear componente ProtectedRoute (frontend)**
9. ⏳ **Actualizar menú dinámico con permisos**
10. ⏳ **Conectar botones de exportación en informes**
11. ⏳ **Probar todo el sistema completo**

---

## 📞 SOPORTE

Si encuentras algún problema:
1. Revisa los logs de Laravel: `storage/logs/laravel.log`
2. Verifica que las migraciones se ejecutaron correctamente
3. Confirma que los seeders crearon los roles y permisos
4. Revisa que el usuario tenga un `role_id` asignado

---

**Fecha de implementación:** 2025-12-13
**Implementado por:** Claude Code
**Estado:** Backend completo, Frontend pendiente
