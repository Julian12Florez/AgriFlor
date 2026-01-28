import { useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { authApi, getAuthToken } from '../services/api';

export interface RoleData {
  id: string;
  name: string;
  displayName: string;
  description?: string;
  hasFullAccess: boolean;
  excludedModules: string[];
}

export interface UserWithPermissions {
  id: string;
  email: string;
  name: string;
  role: string; // Backward compatibility
  status: string;
  roleData?: RoleData;
  permissions?: string[];
  accessibleModules?: string[];
  createdAt?: string;
  updatedAt?: string;
}

/**
 * Custom hook to manage user permissions
 *
 * Provides methods to check permissions and module access based on user's role
 */
export const usePermissions = () => {
  // Only fetch if user has a token
  const token = getAuthToken();

  // Get current user data with permissions
  const { data: userData, isLoading } = useQuery<{
    success: boolean;
    data: UserWithPermissions;
  }>({
    queryKey: ['auth-me'],
    queryFn: () => authApi.me(),
    staleTime: 5 * 60 * 1000, // 5 minutes
    enabled: !!token, // Only execute query if token exists
  });

  const user = userData?.data;

  // Memoize permission checks for performance
  const permissionHelpers = useMemo(() => {
    const permissions = user?.permissions || [];
    const accessibleModules = user?.accessibleModules || [];
    const hasFullAccess = user?.roleData?.hasFullAccess || false;
    const excludedModules = user?.roleData?.excludedModules || [];

    return {
      /**
       * Check if user has a specific permission
       */
      hasPermission: (permissionName: string): boolean => {
        if (hasFullAccess) return true;
        return permissions.includes(permissionName);
      },

      /**
       * Check if user has ANY of the specified permissions
       */
      hasAnyPermission: (...permissionNames: string[]): boolean => {
        if (hasFullAccess) return true;
        return permissionNames.some((p) => permissions.includes(p));
      },

      /**
       * Check if user has ALL of the specified permissions
       */
      hasAllPermissions: (...permissionNames: string[]): boolean => {
        if (hasFullAccess) return true;
        return permissionNames.every((p) => permissions.includes(p));
      },

      /**
       * Check if user has access to a specific module
       */
      hasModuleAccess: (moduleName: string): boolean => {
        if (hasFullAccess && !excludedModules.includes(moduleName)) {
          return true;
        }
        return accessibleModules.includes(moduleName) || accessibleModules.includes('all');
      },

      /**
       * Check if user is admin (has full access)
       */
      isAdmin: (): boolean => {
        return hasFullAccess;
      },

      /**
       * Get user's role name
       */
      getRoleName: (): string => {
        return user?.roleData?.name || user?.role || 'unknown';
      },

      /**
       * Get user's role display name
       */
      getRoleDisplayName: (): string => {
        return user?.roleData?.displayName || user?.role || 'Unknown';
      },

      /**
       * Get all user permissions
       */
      getPermissions: (): string[] => {
        return permissions;
      },

      /**
       * Get all accessible modules
       */
      getAccessibleModules: (): string[] => {
        return accessibleModules;
      },
    };
  }, [user]);

  return {
    user,
    isLoading,
    ...permissionHelpers,
  };
};

export default usePermissions;
