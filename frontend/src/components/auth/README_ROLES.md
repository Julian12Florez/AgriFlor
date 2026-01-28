# Sistema de Roles y Permisos - Guía de Integración

## Archivos Creados

1. **`/src/context/AuthContext.tsx`** - Context Provider para autenticación y permisos
2. **`/src/components/auth/ProtectedRoute.tsx`** - Componente para proteger rutas
3. **`/src/hooks/usePermissions.ts`** - Hook existente (ya implementado)

---

## 1. Configurar el AuthProvider en App.tsx

Envuelve tu aplicación con el `AuthProvider`:

```tsx
// src/App.tsx
import { AuthProvider } from './context/AuthContext';
import { BrowserRouter, Routes, Route } from 'react-router-dom';

function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          {/* Tus rutas aquí */}
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}

export default App;
```

---

## 2. Proteger Rutas con ProtectedRoute

### Ejemplo 1: Proteger por permiso único

```tsx
import ProtectedRoute from './components/auth/ProtectedRoute';
import UsersPage from './pages/admin/UsersPage';

<Route
  path="/admin/users"
  element={
    <ProtectedRoute requiredPermission="manage_users">
      <UsersPage />
    </ProtectedRoute>
  }
/>
```

### Ejemplo 2: Proteger por múltiples permisos (ANY)

```tsx
<Route
  path="/inventory"
  element={
    <ProtectedRoute requiredPermissions={["view_inventory", "manage_inventory"]}>
      <InventoryPage />
    </ProtectedRoute>
  }
/>
```

### Ejemplo 3: Proteger por múltiples permisos (ALL)

```tsx
<Route
  path="/reports/export"
  element={
    <ProtectedRoute
      requiredPermissions={["view_inventory", "export_reports"]}
      requireAllPermissions={true}
    >
      <ExportReportsPage />
    </ProtectedRoute>
  }
/>
```

### Ejemplo 4: Proteger por módulo

```tsx
<Route
  path="/warehouse"
  element={
    <ProtectedRoute requiredModule="warehouse">
      <WarehousePage />
    </ProtectedRoute>
  }
/>
```

---

## 3. Usar useAuth en Componentes

### Ejemplo 1: Mostrar/ocultar elementos según permisos

```tsx
import { useAuth } from '../context/AuthContext';

function MyComponent() {
  const { user, hasPermission, isAdmin } = useAuth();

  return (
    <div>
      <h1>Bienvenido, {user?.name}</h1>

      {hasPermission('manage_users') && (
        <Button onClick={openUserModal}>Crear Usuario</Button>
      )}

      {isAdmin() && (
        <div>Contenido solo para administradores</div>
      )}
    </div>
  );
}
```

### Ejemplo 2: Verificar acceso a módulos

```tsx
import { useAuth } from '../context/AuthContext';

function Sidebar() {
  const { hasModuleAccess } = useAuth();

  return (
    <Menu>
      {hasModuleAccess('warehouse') && (
        <Menu.Item icon={<InboxOutlined />}>
          <Link to="/warehouse">Almacén</Link>
        </Menu.Item>
      )}

      {hasModuleAccess('purchases') && (
        <Menu.Item icon={<ShoppingCartOutlined />}>
          <Link to="/purchases">Compras</Link>
        </Menu.Item>
      )}

      {hasModuleAccess('reports') && (
        <Menu.Item icon={<BarChartOutlined />}>
          <Link to="/reports">Reportes</Link>
        </Menu.Item>
      )}
    </Menu>
  );
}
```

### Ejemplo 3: Verificar múltiples permisos

```tsx
import { useAuth } from '../context/AuthContext';

function InventoryActions() {
  const { hasAnyPermission, hasAllPermissions } = useAuth();

  // Usuario necesita AL MENOS UNO de estos permisos
  const canViewInventory = hasAnyPermission('view_inventory', 'manage_inventory');

  // Usuario necesita TODOS estos permisos
  const canExportReports = hasAllPermissions('view_inventory', 'export_reports');

  return (
    <Space>
      {canViewInventory && (
        <Button onClick={viewInventory}>Ver Inventario</Button>
      )}

      {canExportReports && (
        <Button icon={<DownloadOutlined />} onClick={exportReport}>
          Exportar
        </Button>
      )}
    </Space>
  );
}
```

---

## 4. Agregar Página de Acceso Denegado

```tsx
// src/App.tsx
import { UnauthorizedPage } from './components/auth/ProtectedRoute';

<Routes>
  <Route path="/unauthorized" element={<UnauthorizedPage />} />
  {/* Otras rutas */}
</Routes>
```

---

## 5. Ejemplo Completo de Integración en App.tsx

```tsx
import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import ProtectedRoute, { UnauthorizedPage } from './components/auth/ProtectedRoute';

// Páginas
import LoginPage from './pages/auth/LoginPage';
import DashboardPage from './pages/Dashboard';
import UsersPage from './pages/admin/UsersPage';
import InventoryPage from './pages/inventory/InventoryPage';
import PurchasesPage from './pages/purchases/PurchasesPage';
// NOTA: El módulo de Aplicaciones fue eliminado - las aplicaciones se crean automáticamente en Recepciones

function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          {/* Rutas públicas */}
          <Route path="/login" element={<LoginPage />} />
          <Route path="/unauthorized" element={<UnauthorizedPage />} />

          {/* Rutas protegidas */}
          <Route
            path="/dashboard"
            element={
              <ProtectedRoute>
                <DashboardPage />
              </ProtectedRoute>
            }
          />

          {/* Administración de usuarios (solo admin) */}
          <Route
            path="/admin/users"
            element={
              <ProtectedRoute requiredPermission="manage_users">
                <UsersPage />
              </ProtectedRoute>
            }
          />

          {/* Inventario (admin, warehouse, supervisor) */}
          <Route
            path="/inventory"
            element={
              <ProtectedRoute requiredModule="inventory">
                <InventoryPage />
              </ProtectedRoute>
            }
          />

          {/* Compras (admin, warehouse) */}
          <Route
            path="/purchases"
            element={
              <ProtectedRoute requiredPermissions={["manage_purchases", "view_purchases"]}>
                <PurchasesPage />
              </ProtectedRoute>
            }
          />

          {/* NOTA: Módulo Aplicaciones eliminado - se crean automáticamente en Recepciones */}

          {/* Redirección por defecto */}
          <Route path="/" element={<Navigate to="/dashboard" replace />} />
          <Route path="*" element={<Navigate to="/dashboard" replace />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}

export default App;
```

---

## 6. Sidebar Dinámico (Filtrar menú según permisos)

```tsx
// src/components/Layout/Sidebar.tsx
import React from 'react';
import { Menu } from 'antd';
import {
  DashboardOutlined,
  UserOutlined,
  InboxOutlined,
  ShoppingCartOutlined,
  ExperimentOutlined,
  BarChartOutlined,
} from '@ant-design/icons';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';

const Sidebar: React.FC = () => {
  const navigate = useNavigate();
  const { hasModuleAccess, hasPermission } = useAuth();

  // Configuración de elementos del menú
  const menuItems = [
    {
      key: 'dashboard',
      icon: <DashboardOutlined />,
      label: 'Dashboard',
      path: '/dashboard',
      show: true, // Siempre visible
    },
    {
      key: 'users',
      icon: <UserOutlined />,
      label: 'Usuarios',
      path: '/admin/users',
      show: hasPermission('manage_users'),
    },
    {
      key: 'inventory',
      icon: <InboxOutlined />,
      label: 'Inventario',
      path: '/inventory',
      show: hasModuleAccess('inventory'),
    },
    {
      key: 'purchases',
      icon: <ShoppingCartOutlined />,
      label: 'Compras',
      path: '/purchases',
      show: hasModuleAccess('purchases'),
    },
    // NOTA: Módulo Aplicaciones eliminado - se crean automáticamente en Recepciones
    {
      key: 'reports',
      icon: <BarChartOutlined />,
      label: 'Reportes',
      path: '/reports',
      show: hasModuleAccess('reports'),
    },
  ];

  // Filtrar elementos que el usuario puede ver
  const visibleItems = menuItems
    .filter((item) => item.show)
    .map((item) => ({
      key: item.key,
      icon: item.icon,
      label: item.label,
      onClick: () => navigate(item.path),
    }));

  return <Menu mode="inline" items={visibleItems} />;
};

export default Sidebar;
```

---

## 7. Permisos Disponibles en el Sistema

### Módulos:
- `all` - Acceso completo (admin)
- `warehouse` - Gestión de almacén
- `purchases` - Gestión de compras
- `inventory` - Gestión de inventario
- `reports` - Reportes y análisis
- `applications` - Aplicaciones de productos (NOTA: módulo eliminado, se crean automáticamente)

### Permisos específicos:
- `manage_users` - Administrar usuarios
- `view_inventory` - Ver inventario
- `manage_inventory` - Gestionar inventario
- `view_purchases` - Ver compras
- `manage_purchases` - Gestionar compras
- `export_reports` - Exportar reportes
- `approve_outputs` - Aprobar salidas
- `manage_applications` - Gestionar aplicaciones
- `view_applications` - Ver aplicaciones

---

## 8. Validación en Backend

Recuerda que las validaciones de frontend son solo para UX. El backend SIEMPRE debe validar permisos:

```php
// Laravel Backend - Middleware de roles
Route::middleware('role:admin,warehouse')->group(function () {
    Route::post('applications', [ApplicationController::class, 'store']);
});

// Laravel Backend - Middleware de permisos
Route::middleware('permission:manage_applications')->group(function () {
    Route::post('applications/{id}/approve', [ApplicationController::class, 'approve']);
});
```

---

## Notas Importantes

1. **Seguridad**: Las validaciones de permisos en frontend son solo para UX. SIEMPRE valida en backend.

2. **Carga inicial**: El hook `usePermissions` hace una llamada a `/api/auth/me` para obtener permisos del usuario.

3. **Cache**: Los datos se cachean por 5 minutos usando React Query.

4. **Redirección**: Por defecto, usuarios sin permisos son redirigidos a `/unauthorized`. Puedes cambiarlo con la prop `redirectTo`.

5. **Loading**: Mientras se cargan los permisos, `ProtectedRoute` muestra un spinner.

---

## Preguntas Frecuentes

**P: ¿Cómo agregar un nuevo permiso?**

R: Agrega el permiso en el backend (tabla `permissions`), asígnalo a roles, y úsalo en frontend con `hasPermission('nuevo_permiso')`.

**P: ¿Cómo ocultar un botón según permisos?**

R: Usa el hook `useAuth`:
```tsx
const { hasPermission } = useAuth();
{hasPermission('manage_users') && <Button>Crear Usuario</Button>}
```

**P: ¿Puedo proteger solo una parte de una página?**

R: Sí, usa `useAuth` dentro del componente:
```tsx
const { hasPermission } = useAuth();
return (
  <div>
    <p>Contenido público</p>
    {hasPermission('admin') && <AdminPanel />}
  </div>
);
```
