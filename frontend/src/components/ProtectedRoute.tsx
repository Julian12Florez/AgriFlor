import React from 'react';
import { Navigate } from 'react-router-dom';
import { Spin, Result } from 'antd';
import { LockOutlined } from '@ant-design/icons';
import usePermissions from '../hooks/usePermissions';
import { getAuthToken } from '../services/api';

interface ProtectedRouteProps {
  children: React.ReactNode;
  /**
   * Required permission to access this route
   */
  permission?: string;
  /**
   * Alternative: Required permissions (user must have ANY of these)
   */
  anyPermission?: string[];
  /**
   * Alternative: Required permissions (user must have ALL of these)
   */
  allPermissions?: string[];
  /**
   * Required module access
   */
  module?: string;
  /**
   * Fallback path if permission is denied (default: '/dashboard')
   */
  fallbackPath?: string;
  /**
   * Show access denied page instead of redirecting
   */
  showAccessDenied?: boolean;
  /**
   * Skip permission check (only check authentication)
   */
  authOnly?: boolean;
}

/**
 * ProtectedRoute component
 *
 * Protects routes based on authentication and user permissions/module access.
 * First checks if user is authenticated, then checks permissions.
 * Redirects to login if not authenticated, or shows access denied if no permission.
 *
 * @example
 * // Only require authentication (no permission check)
 * <ProtectedRoute authOnly>
 *   <Dashboard />
 * </ProtectedRoute>
 *
 * @example
 * // Require specific permission
 * <ProtectedRoute permission="view_admin">
 *   <AdminPage />
 * </ProtectedRoute>
 *
 * @example
 * // Require module access
 * <ProtectedRoute module="admin">
 *   <AdminDashboard />
 * </ProtectedRoute>
 *
 * @example
 * // Require any of multiple permissions
 * <ProtectedRoute anyPermission={['view_reports', 'export_reports']}>
 *   <ReportsPage />
 * </ProtectedRoute>
 */
export const ProtectedRoute: React.FC<ProtectedRouteProps> = ({
  children,
  permission,
  anyPermission,
  allPermissions,
  module,
  fallbackPath = '/dashboard',
  showAccessDenied = false,
  authOnly = false,
}) => {
  const {
    user,
    isLoading,
    hasPermission,
    hasAnyPermission,
    hasAllPermissions,
    hasModuleAccess,
  } = usePermissions();

  // Check if user has a valid token
  const token = getAuthToken();

  // If no token, redirect to login immediately
  if (!token) {
    return <Navigate to="/login" replace />;
  }

  // Show loading spinner while checking permissions
  if (isLoading) {
    return (
      <div
        style={{
          display: 'flex',
          justifyContent: 'center',
          alignItems: 'center',
          height: '100vh',
        }}
      >
        <Spin size="large" tip="Verificando permisos..." />
      </div>
    );
  }

  // If user data couldn't be loaded (token might be invalid), redirect to login
  if (!user) {
    return <Navigate to="/login" replace />;
  }

  // If authOnly is true, skip permission checks
  if (authOnly) {
    return <>{children}</>;
  }

  // Check permissions
  let hasAccess = true;

  if (permission) {
    hasAccess = hasPermission(permission);
  }

  if (anyPermission && anyPermission.length > 0) {
    hasAccess = hasAccess && hasAnyPermission(...anyPermission);
  }

  if (allPermissions && allPermissions.length > 0) {
    hasAccess = hasAccess && hasAllPermissions(...allPermissions);
  }

  if (module) {
    hasAccess = hasAccess && hasModuleAccess(module);
  }

  // If no access, either show denied page or redirect
  if (!hasAccess) {
    if (showAccessDenied) {
      return (
        <div
          style={{
            display: 'flex',
            justifyContent: 'center',
            alignItems: 'center',
            height: '100vh',
            padding: '20px',
          }}
        >
          <Result
            status="403"
            title="403"
            subTitle="Lo sentimos, no tienes permisos para acceder a esta página."
            icon={<LockOutlined style={{ fontSize: 72, color: '#ff4d4f' }} />}
            extra={
              <a href="/dashboard" style={{ color: '#1890ff' }}>
                Volver al Dashboard
              </a>
            }
          />
        </div>
      );
    }

    return <Navigate to={fallbackPath} replace />;
  }

  // User has access, render children
  return <>{children}</>;
};

export default ProtectedRoute;
