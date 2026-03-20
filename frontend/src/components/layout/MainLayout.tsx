import React, { useState, useMemo, useEffect } from 'react';
import { Layout, Menu, Button, Avatar, Dropdown, Badge } from 'antd';
import { useNavigate, useLocation } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import {
  MenuFoldOutlined,
  MenuUnfoldOutlined,
  DashboardOutlined,
  DatabaseOutlined,
  ExperimentOutlined,
  ShoppingCartOutlined,
  BankOutlined,
  BarChartOutlined,
  SettingOutlined,
  UserOutlined,
  BellOutlined,
  LogoutOutlined,
  LockOutlined,
  DollarOutlined,
} from '@ant-design/icons';
import type { MenuProps } from 'antd';
import usePermissions from '../../hooks/usePermissions';
import { setAuthToken } from '../../services/api';
import ChangePasswordModal from './ChangePasswordModal';

const { Header, Sider, Content } = Layout;

// Breakpoint para detectar móvil (lg = 992px según Ant Design)
const MOBILE_BREAKPOINT = 992;

interface MainLayoutProps {
  children: React.ReactNode;
}

const MainLayout: React.FC<MainLayoutProps> = ({ children }) => {
  const [collapsed, setCollapsed] = useState(window.innerWidth < MOBILE_BREAKPOINT);
  const [isMobile, setIsMobile] = useState(window.innerWidth < MOBILE_BREAKPOINT);
  const [changePasswordVisible, setChangePasswordVisible] = useState(false);
  const navigate = useNavigate();
  const location = useLocation();
  const queryClient = useQueryClient();
  const { user, hasModuleAccess, getRoleDisplayName } = usePermissions();

  // Detectar cambios de tamaño de pantalla
  useEffect(() => {
    const handleResize = () => {
      const mobile = window.innerWidth < MOBILE_BREAKPOINT;
      setIsMobile(mobile);
      if (mobile && !collapsed) {
        setCollapsed(true);
      }
    };
    window.addEventListener('resize', handleResize);
    return () => window.removeEventListener('resize', handleResize);
  }, [collapsed]);

  // Build menu dynamically based on user permissions
  const menuItems: MenuProps['items'] = useMemo(() => {
    const items: MenuProps['items'] = [
      {
        key: '/dashboard',
        icon: <DashboardOutlined />,
        label: 'Dashboard',
      },
    ];

    // Datos Maestros - module: 'master'
    if (hasModuleAccess('master')) {
      items.push({
        key: 'sub1',
        icon: <DatabaseOutlined />,
        label: 'Datos Maestros',
        children: [
          { key: '/master/products', label: 'Productos' },
          { key: '/master/base-units', label: 'Unidades Base' },
          { key: '/master/packaging-units', label: 'Unidades de Empaque' },
          { key: '/master/brands', label: 'Marcas' },
          { key: '/master/categories', label: 'Categorías' },
          { key: '/master/suppliers', label: 'Proveedores' },
          { key: '/master/locations', label: 'Ubicaciones' },
          { key: '/master/output-types', label: 'Tipos de Salida' },
        ],
      });
    }

    // Procesos Técnicos - module: 'technical'
    if (hasModuleAccess('technical')) {
      items.push({
        key: 'sub2',
        icon: <ExperimentOutlined />,
        label: 'Procesos Técnicos',
        children: [
          { key: '/technical/recipes', label: 'Recetas Técnicas' },
          { key: '/technical/orders', label: 'Órdenes Técnicas' },
        ],
      });
    }

    // Gestión de Compras y Operaciones - module: 'purchases'
    const purchaseChildren: any[] = [];
    if (hasModuleAccess('purchases')) {
      purchaseChildren.push({ key: '/purchases', label: 'Compras' });
    }
    if (hasModuleAccess('outputs')) {
      purchaseChildren.push({ key: '/outputs', label: 'Salidas' });
    }
    if (hasModuleAccess('reception')) {
      purchaseChildren.push({ key: '/reception', label: 'Recepción' });
    }

    if (purchaseChildren.length > 0) {
      items.push({
        key: 'sub3',
        icon: <ShoppingCartOutlined />,
        label: 'Gestión de Compras',
        children: purchaseChildren,
      });
    }

    // Inventario - module: 'inventory'
    if (hasModuleAccess('inventory')) {
      items.push({
        key: 'sub4',
        icon: <BankOutlined />,
        label: 'Inventario',
        children: [
          { key: '/inventory', label: 'Inventario y Kardex' },
          { key: '/reports/stock', label: 'Stock Actual' },
          { key: '/reports/monthly-inventory', label: 'Inventario Mensual' },
          { key: '/reports/product-listing', label: 'Listado por Fecha' },
        ],
      });
    }

    // Reportes y Alertas - module: 'reports'
    if (hasModuleAccess('reports')) {
      items.push({
        key: 'sub5',
        icon: <BarChartOutlined />,
        label: 'Reportes y Alertas',
        children: [
          { key: '/reports/consumption', label: 'Consumo de Productos' },
          { key: '/reports/movements', label: 'Movimientos de Inventario' },
          { key: '/reports/consolidated', label: 'Análisis Consolidado' },
          { key: '/reports/kardex', label: 'Kardex de Inventario' },
          { key: '/reports/audit', label: 'Auditoría de Inventario' },
          // TODO: Habilitar cuando se liberen a producción
          // { type: 'divider' as const },
          // { key: '/reports/labor-costs', label: 'Costos Laborales' },
          // { key: '/reports/worker-productivity', label: 'Productividad Trabajadores' },
          // { key: '/reports/task-analysis', label: 'Análisis de Tareas' },
          // { key: '/reports/deductions-breakdown', label: 'Desglose de Deducciones' },
          // { key: '/reports/period-comparison', label: 'Comparativo de Periodos' },
        ],
      });
    }

    // Liquidación - module: 'liquidation'
    if (hasModuleAccess('liquidation')) {
      items.push({
        key: 'sub7',
        icon: <DollarOutlined />,
        label: 'Liquidación',
        children: [
          { key: '/liquidation/workers', label: 'Trabajadores' },
          { key: '/liquidation/tasks', label: 'Tareas' },
          { key: '/liquidation/assignments', label: 'Asignaciones Diarias' },
          { key: '/liquidation/report', label: 'Reporte de Liquidación' },
        ],
      });
    }

    // Administración - module: 'admin'
    if (hasModuleAccess('admin')) {
      items.push({
        key: 'sub6',
        icon: <SettingOutlined />,
        label: 'Administración',
        children: [
          { key: '/admin/users', label: 'Usuarios' },
        ],
      });
    }

    return items;
  }, [hasModuleAccess]);

  // Dropdown del usuario
  const userMenuItems: MenuProps['items'] = [
    {
      key: 'profile',
      icon: <UserOutlined />,
      label: 'Mi Perfil',
    },
    {
      key: 'change-password',
      icon: <LockOutlined />,
      label: 'Cambiar Contraseña',
    },
    {
      type: 'divider',
    },
    {
      key: 'logout',
      icon: <LogoutOutlined />,
      label: 'Cerrar Sesión',
      danger: true,
    },
  ];

  const handleMenuClick: MenuProps['onClick'] = (e) => {
    if (e.key.startsWith('/')) {
      navigate(e.key);
    }
  };

  const handleUserMenuClick = (info: { key: string }) => {
    if (info.key === 'logout') {
      // Clear token and invalidate all queries
      setAuthToken(null);
      queryClient.clear();
      navigate('/login');
    } else if (info.key === 'change-password') {
      setChangePasswordVisible(true);
    }
  };

  return (
    <Layout style={{ minHeight: '100vh' }}>
      {/* Overlay para cerrar sidebar en móviles */}
      {isMobile && !collapsed && (
        <div
          onClick={() => setCollapsed(true)}
          style={{
            position: 'fixed',
            top: 0,
            left: 0,
            right: 0,
            bottom: 0,
            background: 'rgba(0, 0, 0, 0.45)',
            zIndex: 999,
          }}
        />
      )}
      <Sider
        trigger={null}
        collapsible
        collapsed={collapsed}
        breakpoint="lg"
        collapsedWidth={isMobile ? 0 : 80}
        onBreakpoint={(broken) => {
          setIsMobile(broken);
          if (broken) {
            setCollapsed(true);
          }
        }}
        style={{
          background: '#2E7D32',
          position: isMobile ? 'fixed' : 'relative',
          height: isMobile ? '100vh' : 'auto',
          left: 0,
          top: 0,
          zIndex: 1000,
        }}
        width={280}
      >
        {/* Logo */}
        <div
          style={{
            height: 64,
            margin: 16,
            background: 'rgba(255, 255, 255, 0.1)',
            borderRadius: 8,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            color: 'white',
            fontSize: collapsed ? 16 : 18,
            fontWeight: 'bold',
          }}
        >
          {collapsed ? '🌿' : '🌿 AgriFlor'}
        </div>

        {/* Menu */}
        <Menu
          theme="dark"
          mode="inline"
          selectedKeys={[location.pathname]}
          defaultOpenKeys={[]}
          items={menuItems}
          onClick={handleMenuClick}
        />
      </Sider>

      <Layout>
        {/* Header */}
        <Header
          style={{
            padding: isMobile ? '0 12px' : '0 24px',
            background: '#4CAF50',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            position: 'sticky',
            top: 0,
            zIndex: 100,
          }}
        >
          <div style={{ display: 'flex', alignItems: 'center' }}>
            <Button
              type="text"
              icon={collapsed ? <MenuUnfoldOutlined /> : <MenuFoldOutlined />}
              onClick={() => setCollapsed(!collapsed)}
              style={{
                fontSize: '16px',
                width: isMobile ? 48 : 64,
                height: 64,
                color: 'white',
              }}
            />
            {!isMobile && (
              <h2 style={{ color: 'white', margin: 0, marginLeft: 16 }}>
                Panel
              </h2>
            )}
          </div>

          <div style={{ display: 'flex', alignItems: 'center', gap: isMobile ? 8 : 16 }}>
            {/* Notificaciones */}
            <Badge count={3} size="small">
              <Button
                type="text"
                icon={<BellOutlined />}
                style={{ color: 'white', fontSize: '16px' }}
              />
            </Badge>

            {/* Usuario */}
            <Dropdown
              menu={{ items: userMenuItems, onClick: handleUserMenuClick }}
              placement="bottomRight"
            >
              <div style={{
                display: 'flex',
                alignItems: 'center',
                cursor: 'pointer',
                color: 'white',
                gap: 8,
              }}>
                <Avatar
                  size="small"
                  icon={<UserOutlined />}
                  style={{ background: '#2E7D32' }}
                />
                <div style={{
                  display: 'flex',
                  flexDirection: 'column',
                  alignItems: 'flex-start',
                  lineHeight: 1.3,
                  gap: 0,
                  maxWidth: isMobile ? 100 : 'none',
                }}>
                  <span style={{
                    fontSize: isMobile ? '12px' : '14px',
                    fontWeight: 500,
                    whiteSpace: 'nowrap',
                    overflow: 'hidden',
                    textOverflow: 'ellipsis',
                    maxWidth: isMobile ? 80 : 'none',
                  }}>
                    {user?.name || 'Usuario'}
                  </span>
                  <span style={{ fontSize: isMobile ? '10px' : '12px', opacity: 0.8 }}>
                    {getRoleDisplayName()}
                  </span>
                </div>
              </div>
            </Dropdown>
          </div>
        </Header>

        {/* Content */}
        <Content
          style={{
            margin: isMobile ? '12px' : '24px',
            padding: isMobile ? 12 : 24,
            background: '#fff',
            borderRadius: 8,
            minHeight: 280,
          }}
        >
          {children}
        </Content>
      </Layout>

      <ChangePasswordModal
        open={changePasswordVisible}
        onClose={() => setChangePasswordVisible(false)}
      />
    </Layout>
  );
};

export default MainLayout;