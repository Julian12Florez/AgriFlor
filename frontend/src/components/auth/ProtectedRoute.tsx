import React from 'react';
import type { ReactNode } from 'react';
import { Navigate } from 'react-router-dom';
import { Result, Spin } from 'antd';
import { LockOutlined } from '@ant-design/icons';
import { useAuth } from '../../context/AuthContext';

interface ProtectedRouteProps {
  children: ReactNode;
  requiredPermission?: string;
  requiredPermissions?: string[];
  requireAllPermissions?: boolean;
  requiredModule?: string;
  redirectTo?: string;
}

/**
 * ProtectedRoute - Component to protect routes based on permissions
 *
 * Usage examples:
 *
 * 1. Protect route requiring a single permission:
 * ```tsx
 * <ProtectedRoute requiredPermission="manage_users">
 *   <UsersPage />
 * </ProtectedRoute>
 * ```
 *
 * 2. Protect route requiring ANY of multiple permissions:
 * ```tsx
 * <ProtectedRoute requiredPermissions={["view_inventory", "manage_inventory"]}>
 *   <InventoryPage />
 * </ProtectedRoute>
 * ```
 *
 * 3. Protect route requiring ALL of multiple permissions:
 * ```tsx
 * <ProtectedRoute
 *   requiredPermissions={["view_inventory", "export_reports"]}
 *   requireAllPermissions={true}
 * >
 *   <InventoryReportPage />
 * </ProtectedRoute>
 * ```
 *
 * 4. Protect route requiring module access:
 * ```tsx
 * <ProtectedRoute requiredModule="warehouse">
 *   <WarehousePage />
 * </ProtectedRoute>
 * ```
 *
 * 5. Custom redirect for unauthorized access:
 * ```tsx
 * <ProtectedRoute requiredPermission="admin_panel" redirectTo="/dashboard">
 *   <AdminPanel />
 * </ProtectedRoute>
 * ```
 */
const ProtectedRoute: React.FC<ProtectedRouteProps> = ({
  children,
  requiredPermission,
  requiredPermissions,
  requireAllPermissions = false,
  requiredModule,
  redirectTo = '/unauthorized',
}) => {
  const { hasPermission, hasAnyPermission, hasAllPermissions, hasModuleAccess, isLoading } = useAuth();

  // Show loading spinner while checking permissions
  if (isLoading) {
    return (
      <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', height: '100vh' }}>
        <Spin size="large" tip="Cargando permisos..." />
      </div>
    );
  }

  // Check single permission
  if (requiredPermission && !hasPermission(requiredPermission)) {
    return <Navigate to={redirectTo} replace />;
  }

  // Check multiple permissions
  if (requiredPermissions && requiredPermissions.length > 0) {
    const hasAccess = requireAllPermissions
      ? hasAllPermissions(...requiredPermissions)
      : hasAnyPermission(...requiredPermissions);

    if (!hasAccess) {
      return <Navigate to={redirectTo} replace />;
    }
  }

  // Check module access
  if (requiredModule && !hasModuleAccess(requiredModule)) {
    return <Navigate to={redirectTo} replace />;
  }

  // User has required permissions, render children
  return <>{children}</>;
};

/**
 * UnauthorizedPage - Page shown when user doesn't have required permissions
 *
 * Usage:
 * ```tsx
 * import { Route } from 'react-router-dom';
 * import { UnauthorizedPage } from './components/auth/ProtectedRoute';
 *
 * <Route path="/unauthorized" element={<UnauthorizedPage />} />
 * ```
 */
export const UnauthorizedPage: React.FC = () => {
  return (
    <div style={{ padding: '50px 20px' }}>
      <Result
        status="403"
        icon={<LockOutlined style={{ fontSize: 72, color: '#ff4d4f' }} />}
        title="403 - Acceso Denegado"
        subTitle="Lo sentimos, no tienes permisos para acceder a esta página."
        extra={
          <a href="/dashboard" style={{ color: '#1890ff', textDecoration: 'none' }}>
            Volver al Dashboard
          </a>
        }
      />
    </div>
  );
};

export default ProtectedRoute;
