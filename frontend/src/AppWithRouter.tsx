import React from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { ConfigProvider } from 'antd';
import { Card, Button } from 'antd';
import Dashboard from './pages/Dashboard';

// Componente simple de prueba
const TestDashboard: React.FC = () => (
  <div style={{ padding: '20px' }}>
    <h1 style={{ color: '#2E7D32' }}>🌿 Dashboard de AgriFlor</h1>
    <Card title="Dashboard Principal" style={{ maxWidth: 600 }}>
      <p>✅ React Router funcionando</p>
      <p>✅ Navegación básica funcionando</p>
      <p>✅ Ant Design integrado con Router</p>
    </Card>
  </div>
);

const TestProducts: React.FC = () => (
  <div style={{ padding: '20px' }}>
    <h1 style={{ color: '#2E7D32' }}>📦 Productos</h1>
    <Card title="Gestión de Productos" style={{ maxWidth: 600 }}>
      <p>Esta es la página de productos (ruta: /products)</p>
      <Button type="primary" style={{ backgroundColor: '#4CAF50', borderColor: '#4CAF50' }}>
        Nuevo Producto
      </Button>
    </Card>
  </div>
);

const AppWithRouter: React.FC = () => {
  return (
    <ConfigProvider>
      <Router>
        <div style={{ backgroundColor: '#f0f0f0', minHeight: '100vh' }}>
          <div style={{ padding: '20px', borderBottom: '1px solid #ddd', backgroundColor: 'white' }}>
            <h2 style={{ margin: 0, color: '#2E7D32' }}>🌿 AgriFlor - Test con Router</h2>
            <div style={{ marginTop: '10px' }}>
              <Button
                type="link"
                onClick={() => window.location.href = '/'}
                style={{ color: '#4CAF50' }}
              >
                Dashboard
              </Button>
              <Button
                type="link"
                onClick={() => window.location.href = '/products'}
                style={{ color: '#4CAF50' }}
              >
                Productos
              </Button>
            </div>
          </div>

          <Routes>
            <Route path="/" element={<Navigate to="/dashboard" replace />} />
            <Route path="/dashboard" element={<Dashboard />} />
            <Route path="/products" element={<TestProducts />} />
            <Route path="*" element={<Navigate to="/dashboard" replace />} />
          </Routes>
        </div>
      </Router>
    </ConfigProvider>
  );
};

export default AppWithRouter;