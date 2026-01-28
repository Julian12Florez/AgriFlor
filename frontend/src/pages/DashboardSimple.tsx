import React from 'react';
import { Row, Col, Card, Statistic } from 'antd';
import { DatabaseOutlined, ShoppingOutlined } from '@ant-design/icons';

const DashboardSimple: React.FC = () => {
  return (
    <div style={{ padding: '24px' }}>
      {/* Header */}
      <div style={{ marginBottom: 24 }}>
        <h1 style={{ margin: 0, color: '#2E7D32' }}>🌿 Dashboard Principal</h1>
        <p style={{ color: '#666', margin: 0 }}>
          Resumen general del sistema AgriFlor
        </p>
      </div>

      {/* KPIs simplificados */}
      <Row gutter={[16, 16]} style={{ marginBottom: 24 }}>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="Productos Totales"
              value={1453}
              prefix={<DatabaseOutlined style={{ color: '#4CAF50' }} />}
              valueStyle={{ color: '#2E7D32' }}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="Compras del Mes"
              value={245000}
              prefix={<ShoppingOutlined style={{ color: '#2196F3' }} />}
              precision={0}
              valueStyle={{ color: '#1976D2' }}
              suffix="COP"
            />
          </Card>
        </Col>
      </Row>

      {/* Información adicional */}
      <Row gutter={[16, 16]}>
        <Col xs={24} lg={12}>
          <Card title="Sistema Funcionando" style={{ height: 200 }}>
            <p>✅ Dashboard cargado correctamente</p>
            <p>✅ Ant Design funcionando</p>
            <p>✅ Componentes renderizando</p>
            <p>✅ Estilos aplicados</p>
          </Card>
        </Col>
        <Col xs={24} lg={12}>
          <Card title="Próximos Pasos" style={{ height: 200 }}>
            <p>🔄 Agregar más componentes gradualmente</p>
            <p>🔄 Probar navegación completa</p>
            <p>🔄 Verificar todas las páginas</p>
          </Card>
        </Col>
      </Row>
    </div>
  );
};

export default DashboardSimple;