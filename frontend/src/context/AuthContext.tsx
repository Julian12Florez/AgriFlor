import React, { createContext, useContext } from 'react';
import type { ReactNode } from 'react';
import usePermissions from '../hooks/usePermissions';
import type { UserWithPermissions } from '../hooks/usePermissions';

interface AuthContextType {
  user: UserWithPermissions | undefined;
  isLoading: boolean;
  hasPermission: (permissionName: string) => boolean;
  hasAnyPermission: (...permissionNames: string[]) => boolean;
  hasAllPermissions: (...permissionNames: string[]) => boolean;
  hasModuleAccess: (moduleName: string) => boolean;
  isAdmin: () => boolean;
  getRoleName: () => string;
  getRoleDisplayName: () => string;
  getPermissions: () => string[];
  getAccessibleModules: () => string[];
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

interface AuthProviderProps {
  children: ReactNode;
}

/**
 * AuthProvider - Provides authentication and permission context to the app
 *
 * Usage:
 * ```tsx
 * import { AuthProvider } from './context/AuthContext';
 *
 * function App() {
 *   return (
 *     <AuthProvider>
 *       <YourApp />
 *     </AuthProvider>
 *   );
 * }
 * ```
 */
export const AuthProvider: React.FC<AuthProviderProps> = ({ children }) => {
  const permissionsData = usePermissions();

  const value: AuthContextType = {
    user: permissionsData.user,
    isLoading: permissionsData.isLoading,
    hasPermission: permissionsData.hasPermission,
    hasAnyPermission: permissionsData.hasAnyPermission,
    hasAllPermissions: permissionsData.hasAllPermissions,
    hasModuleAccess: permissionsData.hasModuleAccess,
    isAdmin: permissionsData.isAdmin,
    getRoleName: permissionsData.getRoleName,
    getRoleDisplayName: permissionsData.getRoleDisplayName,
    getPermissions: permissionsData.getPermissions,
    getAccessibleModules: permissionsData.getAccessibleModules,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

/**
 * useAuth - Hook to access authentication context
 *
 * Usage:
 * ```tsx
 * import { useAuth } from './context/AuthContext';
 *
 * function MyComponent() {
 *   const { user, hasPermission, isAdmin } = useAuth();
 *
 *   if (hasPermission('manage_users')) {
 *     // Render admin content
 *   }
 *
 *   return <div>Hello {user?.name}</div>;
 * }
 * ```
 */
export const useAuth = (): AuthContextType => {
  const context = useContext(AuthContext);

  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }

  return context;
};

export default AuthContext;
