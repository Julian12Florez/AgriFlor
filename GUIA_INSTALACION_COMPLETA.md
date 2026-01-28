# Guía de Instalación Completa
## Sistema de Roles, Permisos y Exportación de Reportes - AgriFlor

---

## 📋 Tabla de Contenidos

1. [Descripción General](#descripción-general)
2. [Instalación](#instalación)
3. [Estructura de Roles y Permisos](#estructura-de-roles-y-permisos)
4. [Funcionalidades Implementadas](#funcionalidades-implementadas)
5. [Uso del Sistema](#uso-del-sistema)
6. [Testing](#testing)
7. [Troubleshooting](#troubleshooting)

---

## 🎯 Descripción General

Este sistema implementa:

✅ **Sistema de Roles y Permisos Completo**
- 5 roles predefinidos con permisos específicos
- Control de acceso basado en módulos
- Middleware de protección de rutas
- Menú dinámico según permisos del usuario

✅ **Exportación de Reportes**
- Exportación a Excel y PDF
- 3 reportes: Stock, Consumo, y Movimientos de Inventario
- Filtros dinámicos conservados en la exportación
- Diseño profesional replicando la vista web

---

## 🚀 Instalación

### Opción 1: Script Automático (Recomendado)

```bash
cd /home/julian/Documentos/AgriFlor/backend

# Dar permisos de ejecución
chmod +x setup-roles-permisos.sh

# Ejecutar dentro del contenedor Docker
docker exec agriflor-app bash -c "cd /var/www/html && php artisan migrate --force"
docker exec agriflor-app bash -c "cd /var/www/html && php artisan db:seed --class=PermissionsSeeder --force"
docker exec agriflor-app bash -c "cd /var/www/html && php artisan db:seed --class=RolesSeeder --force"
```

### Opción 2: Manual

```bash
# Ejecutar migraciones
docker exec agriflor-app php artisan migrate --force

# Ejecutar seeders
docker exec agriflor-app php artisan db:seed --class=PermissionsSeeder --force
docker exec agriflor-app php artisan db:seed --class=RolesSeeder --force

# Asignar rol admin al primer usuario
docker exec agriflor-app php artisan tinker --execute="
    \$adminRole = App\Models\Role::where('name', 'admin')->first();
    \$firstUser = App\Models\User::first();
    if (\$adminRole && \$firstUser) {
        \$firstUser->role_id = \$adminRole->id;
        \$firstUser->save();
        echo 'Usuario ' . \$firstUser->email . ' ahora es administrador';
    }
"
```

### Verificación de Instalación

```bash
# Verificar roles creados
docker exec agriflor-app php artisan tinker --execute="
    App\Models\Role::all(['name', 'display_name'])->each(function(\$r) {
        echo \$r->name . ' - ' . \$r->display_name . PHP_EOL;
    });
"

# Verificar permisos totales
docker exec agriflor-app php artisan tinker --execute="
    echo 'Total Permisos: ' . App\Models\Permission::count() . PHP_EOL;
"
```

---

## 👥 Estructura de Roles y Permisos

### Roles Creados

| Rol | Nombre Sistema | Descripción | Acceso |
|-----|---------------|-------------|--------|
| **Administrador** | `admin` | Acceso total al sistema | Todos los módulos + Admin |
| **Supervisor** | `supervisor` | Supervisor de operaciones | Solo Recepción y Salidas |
| **Bodeguero** | `warehouse_operator` | Operador de bodega | Solo Recepción y Salidas |
| **Operario de Finca** | `farm_operator` | Trabajador de finca | Solo Recepción y Salidas |
| **Compras** | `purchasing` | Gestión de compras | Todos los módulos EXCEPTO Admin |

### Módulos del Sistema

| Módulo | Código | Descripción |
|--------|--------|-------------|
| Recepción | `reception` | Recepción en Finca y Transferencias |
| Salidas | `outputs` | Salidas de Bodega |
| Productos | `products` | Gestión de productos |
| Compras | `purchases` | Gestión de compras y entradas |
| Inventario | `inventory` | Inventario y Kardex |
| Reportes | `reports` | Reportes y Alertas |
| Datos Maestros | `master` | Productos, Marcas, Proveedores, Ubicaciones |
| Procesos Técnicos | `technical` | Recetas y Órdenes Técnicas |
| Administración | `admin` | Usuarios y Configuración |

### Permisos por Módulo

#### Módulo: Recepción (9 permisos)
- `view_receptions` - Ver recepciones
- `create_reception` - Crear recepción
- `edit_reception` - Editar recepción
- `delete_reception` - Eliminar recepción
- `view_transfers` - Ver transferencias
- `create_transfer` - Crear transferencia
- `edit_transfer` - Editar transferencia
- `delete_transfer` - Eliminar transferencia
- `approve_reception` - Aprobar recepción

#### Módulo: Salidas (4 permisos)
- `view_outputs` - Ver salidas
- `create_output` - Crear salida
- `edit_output` - Editar salida
- `delete_output` - Eliminar salida

#### Módulo: Productos (4 permisos)
- `view_products` - Ver productos
- `create_product` - Crear producto
- `edit_product` - Editar producto
- `delete_product` - Eliminar producto

#### Módulo: Compras (4 permisos)
- `view_purchases` - Ver compras
- `create_purchase` - Crear compra
- `edit_purchase` - Editar compra
- `delete_purchase` - Eliminar compra

#### Módulo: Inventario (2 permisos)
- `view_inventory` - Ver inventario
- `adjust_inventory` - Ajustar inventario

#### Módulo: Reportes (2 permisos)
- `view_reports` - Ver reportes
- `export_reports` - Exportar reportes

#### Módulo: Datos Maestros (1 permiso)
- `manage_master_data` - Gestionar datos maestros

#### Módulo: Procesos Técnicos (1 permiso)
- `manage_technical` - Gestionar procesos técnicos

#### Módulo: Administración (2 permisos)
- `manage_users` - Gestionar usuarios
- `manage_settings` - Gestionar configuración

**Total: 29 permisos**

### Distribución de Permisos por Rol

| Rol | Cantidad de Permisos | Permisos Específicos |
|-----|---------------------|---------------------|
| **Administrador** | 29 (todos) | Full access = true |
| **Supervisor** | 9 | Recepción (9) |
| **Bodeguero** | 9 | Recepción (9) |
| **Operario de Finca** | 9 | Recepción (9) |
| **Compras** | 25 | Todos excepto Admin (2) |

---

## ⚙️ Funcionalidades Implementadas

### Backend

#### 1. Migraciones
- `2025_12_13_090001_create_permissions_table.php` - Tabla de permisos
- `2025_12_13_090002_create_roles_table.php` - Tabla de roles
- `2025_12_13_090003_create_role_permission_table.php` - Relación roles-permisos
- `2025_12_13_090004_add_role_id_to_users_table.php` - Campo role_id en usuarios

#### 2. Modelos
- **Permission** (`app/Models/Permission.php`)
  - Campos: name, display_name, module, description
  - Relación: belongsToMany(Role)

- **Role** (`app/Models/Role.php`)
  - Campos: name, display_name, description, has_full_access, excluded_modules
  - Métodos: hasPermission(), hasModuleAccess(), getAccessibleModules()
  - Relación: belongsToMany(Permission)

- **User** (actualizado)
  - Campo adicional: role_id
  - Relación: belongsTo(Role)
  - Métodos: hasPermission(), hasModuleAccess(), getPermissions(), getAccessibleModules()
  - JWT incluye: roleData, permissions, accessibleModules

#### 3. Middleware
- **CheckPermission** (`app/Http/Middleware/CheckPermission.php`)
  - Alias: `permission`
  - Uso: `Route::middleware('permission:view_reports')`

- **CheckModuleAccess** (`app/Http/Middleware/CheckModuleAccess.php`)
  - Alias: `module`
  - Uso: `Route::middleware('module:admin')`

#### 4. Controllers
- **AuthController** (actualizado)
  - login() - Incluye roleData y permissions en JWT
  - me() - Retorna usuario con roleData completa

- **ReportExportController** (`app/Http/Controllers/Api/ReportExportController.php`)
  - exportStockExcel() / exportStockPdf()
  - exportConsumptionExcel() / exportConsumptionPdf()
  - exportMovementsExcel() / exportMovementsPdf()

#### 5. Exports (Excel)
- **StockReportExport** (`app/Exports/StockReportExport.php`)
  - Agrupación por producto o ubicación
  - Formato con estilos y filtros
  - Columnas dinámicas según agrupación

- **ConsumptionReportExport** (`app/Exports/ConsumptionReportExport.php`)
  - 11 columnas de información
  - Encabezados con estilos

- **InventoryMovementsReportExport** (`app/Exports/InventoryMovementsReportExport.php`)
  - 13 columnas incluyendo tipo, origen, destino
  - Formato de cantidades con signo +/-

#### 6. PDFs (Blade Templates)
- `resources/views/exports/pdf/stock-report.blade.php`
- `resources/views/exports/pdf/consumption-report.blade.php`
- `resources/views/exports/pdf/movements-report.blade.php`

Características:
- Layout profesional con encabezado AgriFlor
- Estadísticas resaltadas en cards
- Información de filtros aplicados
- Tablas responsivas
- Footer con fecha de generación

#### 7. Rutas API
```php
// Grupo protegido con permission:export_reports
Route::get('reports/stock/export-excel', [ReportExportController::class, 'exportStockExcel']);
Route::get('reports/stock/export-pdf', [ReportExportController::class, 'exportStockPdf']);
Route::get('reports/consumption/export-excel', [ReportExportController::class, 'exportConsumptionExcel']);
Route::get('reports/consumption/export-pdf', [ReportExportController::class, 'exportConsumptionPdf']);
Route::get('reports/movements/export-excel', [ReportExportController::class, 'exportMovementsExcel']);
Route::get('reports/movements/export-pdf', [ReportExportController::class, 'exportMovementsPdf']);
```

### Frontend

#### 1. Hooks
- **usePermissions** (`src/hooks/usePermissions.ts`)
  - Métodos disponibles:
    - `hasPermission(permissionName)` - Verifica un permiso específico
    - `hasAnyPermission(...permissions)` - Verifica si tiene alguno de los permisos
    - `hasAllPermissions(...permissions)` - Verifica si tiene todos los permisos
    - `hasModuleAccess(moduleName)` - Verifica acceso a módulo
    - `isAdmin()` - Verifica si es administrador
    - `getRoleName()` - Obtiene nombre del rol
    - `getRoleDisplayName()` - Obtiene nombre para mostrar
    - `getPermissions()` - Lista de permisos del usuario
    - `getAccessibleModules()` - Lista de módulos accesibles

#### 2. Componentes
- **ProtectedRoute** (`src/components/ProtectedRoute.tsx`)
  - Props:
    - `permission` - Permiso requerido
    - `anyPermission` - Array de permisos (OR)
    - `allPermissions` - Array de permisos (AND)
    - `module` - Módulo requerido
    - `fallbackPath` - Ruta de redirección (default: /dashboard)
    - `showAccessDenied` - Mostrar página 403 en lugar de redirigir

- **MainLayout** (actualizado)
  - Menú dinámico según permisos
  - Muestra nombre y rol del usuario
  - Secciones del menú visibles solo si tiene acceso al módulo

#### 3. Servicios
- **reportExportsApi** (`src/services/api.ts`)
  - `exportStockExcel(params)` - Exporta stock a Excel
  - `exportStockPdf(params)` - Exporta stock a PDF
  - `exportConsumptionExcel(params)` - Exporta consumo a Excel
  - `exportConsumptionPdf(params)` - Exporta consumo a PDF
  - `exportMovementsExcel(params)` - Exporta movimientos a Excel
  - `exportMovementsPdf(params)` - Exporta movimientos a PDF

#### 4. Reportes Actualizados
- **StockReport.tsx** - Botones de exportación con permisos
- **ConsumptionReport.tsx** - Botones de exportación con permisos
- **InventoryMovementsReport.tsx** - Botones de exportación con permisos

Características:
- Botones solo visibles si `hasPermission('export_reports')`
- Estados de carga durante exportación
- Mensajes de éxito/error
- Paso de parámetros de filtros a exportación

---

## 📖 Uso del Sistema

### Proteger Rutas Backend

```php
// Por permiso específico
Route::middleware('permission:view_reports')->group(function () {
    Route::get('/reports', [ReportController::class, 'index']);
});

// Por módulo
Route::middleware('module:admin')->group(function () {
    Route::resource('users', UserController::class);
});
```

### Proteger Componentes Frontend

```typescript
// En rutas (App.tsx o router)
<Route
  path="/admin/users"
  element={
    <ProtectedRoute module="admin" showAccessDenied>
      <UsersPage />
    </ProtectedRoute>
  }
/>

// Con múltiples permisos (OR)
<ProtectedRoute anyPermission={['view_reports', 'export_reports']}>
  <ReportsPage />
</ProtectedRoute>

// Con todos los permisos (AND)
<ProtectedRoute allPermissions={['view_products', 'edit_product']}>
  <ProductEditPage />
</ProtectedRoute>
```

### Verificar Permisos en Componentes

```typescript
import usePermissions from '../hooks/usePermissions';

function MyComponent() {
  const { hasPermission, hasModuleAccess, isAdmin } = usePermissions();

  if (isAdmin()) {
    return <AdminPanel />;
  }

  return (
    <div>
      {hasPermission('view_reports') && <ReportsLink />}
      {hasModuleAccess('admin') && <AdminLink />}
    </div>
  );
}
```

### Exportar Reportes

```typescript
import { reportExportsApi } from '../../services/api';

// Exportar a Excel
const handleExportExcel = async () => {
  const params = {
    start_date: '2025-01-01',
    end_date: '2025-01-31',
    product_id: 'uuid-del-producto',
  };

  await reportExportsApi.exportStockExcel(params);
  message.success('Reporte exportado exitosamente');
};

// Exportar a PDF
const handleExportPDF = async () => {
  await reportExportsApi.exportStockPdf(params);
  message.success('Reporte exportado exitosamente');
};
```

---

## 🧪 Testing

### 1. Verificar Roles y Permisos

```bash
# Ver todos los roles
docker exec agriflor-app php artisan tinker --execute="
    App\Models\Role::with('permissions')->get()->each(function(\$role) {
        echo \$role->display_name . ': ' . \$role->permissions->count() . ' permisos' . PHP_EOL;
    });
"

# Ver permisos de un rol específico
docker exec agriflor-app php artisan tinker --execute="
    \$role = App\Models\Role::where('name', 'supervisor')->first();
    echo 'Permisos de ' . \$role->display_name . ':' . PHP_EOL;
    \$role->permissions->each(function(\$p) {
        echo '  - ' . \$p->display_name . ' (' . \$p->module . ')' . PHP_EOL;
    });
"
```

### 2. Crear Usuario de Prueba

```bash
docker exec agriflor-app php artisan tinker --execute="
    \$supervisorRole = App\Models\Role::where('name', 'supervisor')->first();

    \$user = App\Models\User::create([
        'id' => Str::uuid(),
        'name' => 'Supervisor Test',
        'email' => 'supervisor@test.com',
        'password' => Hash::make('password'),
        'role' => 'supervisor',
        'role_id' => \$supervisorRole->id,
        'status' => 'active'
    ]);

    echo 'Usuario creado: ' . \$user->email . PHP_EOL;
"
```

### 3. Verificar JWT con Permisos

```bash
# 1. Login y obtener token
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"supervisor@test.com","password":"password"}'

# 2. Usar token para obtener datos del usuario
curl -X GET http://localhost:8000/api/auth/me \
  -H "Authorization: Bearer TU_TOKEN_AQUI"
```

### 4. Probar Exportación de Reportes

```bash
# Exportar Stock a Excel (requiere permiso export_reports)
curl -X GET "http://localhost:8000/api/reports/stock/export-excel?group_by=product" \
  -H "Authorization: Bearer TU_TOKEN_AQUI" \
  --output stock-report.xlsx

# Exportar Consumo a PDF
curl -X GET "http://localhost:8000/api/reports/consumption/export-pdf?start_date=2025-01-01&end_date=2025-01-31" \
  -H "Authorization: Bearer TU_TOKEN_AQUI" \
  --output consumption-report.pdf
```

### 5. Verificar Acceso al Menú

1. Login con diferentes usuarios (admin, supervisor, purchasing)
2. Verificar que el menú muestre solo las opciones permitidas:
   - Admin: Debe ver todas las secciones incluyendo Administración
   - Supervisor: Solo debe ver Dashboard y Gestión de Inventario (con Recepción y Salidas)
   - Compras: Debe ver todo excepto Administración

---

## 🔧 Troubleshooting

### Problema: No se muestran los permisos en JWT

**Solución:**
```bash
# Limpiar cache de JWT
docker exec agriflor-app php artisan cache:clear
docker exec agriflor-app php artisan config:clear

# Logout y login nuevamente para obtener nuevo token
```

### Problema: Usuario no tiene acceso a ningún módulo

**Verificar:**
```bash
# 1. Ver rol del usuario
docker exec agriflor-app php artisan tinker --execute="
    \$user = App\Models\User::where('email', 'EMAIL_USUARIO')->first();
    echo 'Rol ID: ' . \$user->role_id . PHP_EOL;
    echo 'Rol: ' . (\$user->roleRelation ? \$user->roleRelation->name : 'No asignado') . PHP_EOL;
"

# 2. Asignar rol si no tiene
docker exec agriflor-app php artisan tinker --execute="
    \$user = App\Models\User::where('email', 'EMAIL_USUARIO')->first();
    \$role = App\Models\Role::where('name', 'NOMBRE_ROL')->first();
    \$user->role_id = \$role->id;
    \$user->save();
    echo 'Rol asignado correctamente' . PHP_EOL;
"
```

### Problema: Error al exportar reportes

**Verificar:**
1. Usuario tiene permiso `export_reports`
2. Hay datos en el reporte con los filtros aplicados
3. Los paquetes están instalados:
```bash
docker exec agriflor-app composer show | grep excel
docker exec agriflor-app composer show | grep dompdf
```

### Problema: Menú no se actualiza dinámicamente

**Verificar:**
1. Hook usePermissions está importado correctamente
2. El componente MainLayout está usando el hook
3. Cache del navegador (Ctrl+Shift+R para forzar recarga)

---

## 📦 Dependencias Instaladas

### Backend
- `maatwebsite/excel: ^3.1.67` - Exportación a Excel
- `barryvdh/laravel-dompdf: ^3.0.0` - Generación de PDFs

### Frontend
- Ant Design componentes (ya existente)
- React Query (ya existente)
- Custom hooks: usePermissions

---

## 📝 Notas Importantes

1. **Seguridad JWT**: Los tokens JWT ahora incluyen información de permisos. Si cambias los permisos de un usuario, debe hacer logout/login para obtener un nuevo token.

2. **Roles Inmutables**: Los 5 roles principales no deben ser eliminados. Si necesitas roles adicionales, créalos sin modificar los existentes.

3. **Exportaciones**: Las exportaciones replican exactamente los filtros aplicados en la vista web. Asegúrate de aplicar los filtros deseados antes de exportar.

4. **Performance**: El menú dinámico usa `useMemo` para optimizar re-renders. No remover esta optimización.

5. **Backward Compatibility**: El campo `role` en la tabla users se mantiene por compatibilidad. El sistema usa `role_id` como fuente de verdad.

---

## 🎉 Conclusión

El sistema está completamente funcional y listo para producción. Todos los componentes backend y frontend han sido integrados y probados exitosamente.

Para soporte o preguntas adicionales, consultar la documentación técnica en:
- Backend: `/backend/IMPLEMENTACION_ROLES_EXPORTACIONES.md`
- Frontend: Comentarios en código de componentes y hooks

**¡Feliz desarrollo! 🚀**
