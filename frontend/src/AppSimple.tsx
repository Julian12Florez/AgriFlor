import React from 'react';
import { ConfigProvider } from 'antd';
import { Button, Card } from 'antd';

const AppSimple: React.FC = () => {
  return (
    <ConfigProvider>
      <div style={{ padding: '20px', backgroundColor: '#f0f0f0', minHeight: '100vh' }}>
        <h1 style={{ color: '#2E7D32', marginBottom: '20px' }}>🌿 AgriFlor - Test con Ant Design</h1>

        <Card title="Test de Ant Design" style={{ maxWidth: 600, marginBottom: '20px' }}>
          <p>Si ves esta tarjeta con estilo, Ant Design está funcionando correctamente.</p>
          <Button type="primary" style={{ backgroundColor: '#4CAF50', borderColor: '#4CAF50' }}>
            Botón de Test
          </Button>
        </Card>

        <Card title="Próximo Paso" style={{ maxWidth: 600 }}>
          <p>✅ React funcionando</p>
          <p>✅ Ant Design funcionando</p>
          <p>🔄 Siguiente: Probar React Router</p>
        </Card>
      </div>
    </ConfigProvider>
  );
};

export default AppSimple;