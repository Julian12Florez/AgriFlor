import React from 'react';
import { useNavigate } from 'react-router-dom';
import { Row, Col, Card, Statistic, Progress, Timeline, Spin, Alert } from 'antd';
import {
  ShoppingOutlined,
  DatabaseOutlined,
  WarningOutlined,
  LineChartOutlined,
  ExperimentOutlined
} from '@ant-design/icons';
import { useQuery } from '@tanstack/react-query';
import { dashboardApi } from '../services/api';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import 'dayjs/locale/es';

dayjs.extend(relativeTime);
dayjs.locale('es');

const Dashboard: React.FC = () => {
  const navigate = useNavigate();

  const handleQuickAction = (path: string) => {
    navigate(path);
  };

  // Fetch dashboard statistics
  const { data: statisticsData, isLoading: statisticsLoading, error: statisticsError } = useQuery({
    queryKey: ['dashboard-statistics'],
    queryFn: dashboardApi.getStatistics,
    refetchInterval: 60000, // Refresh every minute
  });

  // Fetch inventory by category
  const { data: inventoryData, isLoading: inventoryLoading } = useQuery({
    queryKey: ['dashboard-inventory-by-category'],
    queryFn: dashboardApi.getInventoryByCategory,
    refetchInterval: 60000,
  });

  // Fetch recent activity
  const { data: activityData, isLoading: activityLoading } = useQuery({
    queryKey: ['dashboard-recent-activity'],
    queryFn: () => dashboardApi.getRecentActivity({ limit: 10 }),
    refetchInterval: 30000, // Refresh every 30 seconds
  });

  const statistics = statisticsData?.data;
  const inventoryByCategory = inventoryData?.data;
  const recentActivity = activityData?.data || [];

  if (statisticsError) {
    return (
      <div>
        <div style={{ marginBottom: 24 }}>
          <h1 style={{ margin: 0, color: '#2E7D32' }}>Dashboard Principal</h1>
          <p style={{ color: '#666', margin: 0 }}>
            Resumen general del sistema AgriFlor
          </p>
        </div>
        <Alert
          message="Error al cargar dashboard"
          description="No se pudo cargar la información del dashboard. Por favor, intente nuevamente."
          type="error"
          showIcon
        />
      </div>
    );
  }

  return (
    <div>
      {/* Header */}
      <div style={{ marginBottom: 24 }}>
        <h1 style={{ margin: 0, color: '#2E7D32' }}>Dashboard Principal</h1>
        <p style={{ color: '#666', margin: 0 }}>
          Resumen general del sistema AgriFlor
        </p>
      </div>

      {/* KPIs Row */}
      <Row gutter={[16, 16]} style={{ marginBottom: 24 }}>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Spin spinning={statisticsLoading}>
              <Statistic
                title="Productos Totales"
                value={statistics?.total_products || 0}
                prefix={<DatabaseOutlined style={{ color: '#4CAF50' }} />}
                valueStyle={{ color: '#2E7D32' }}
              />
            </Spin>
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Spin spinning={statisticsLoading}>
              <Statistic
                title="Órdenes Activas"
                value={statistics?.active_orders || 0}
                prefix={<ExperimentOutlined style={{ color: '#FF9800' }} />}
                valueStyle={{ color: '#F57C00' }}
              />
            </Spin>
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Spin spinning={statisticsLoading}>
              <Statistic
                title="Compras del Mes"
                value={statistics?.monthly_purchases || 0}
                prefix={<ShoppingOutlined style={{ color: '#2196F3' }} />}
                precision={0}
                valueStyle={{ color: '#1976D2' }}
                suffix="COP"
              />
            </Spin>
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Spin spinning={statisticsLoading}>
              <Statistic
                title="Alertas Activas"
                value={statistics?.active_alerts || 0}
                prefix={<WarningOutlined style={{ color: '#F44336' }} />}
                valueStyle={{ color: '#D32F2F' }}
              />
            </Spin>
          </Card>
        </Col>
      </Row>

      {/* Charts and Tables Row */}
      <Row gutter={[16, 16]}>
        <Col xs={24} lg={16}>
          <Card title="Inventario por Categoría" style={{ height: 400 }}>
            <Spin spinning={inventoryLoading}>
              <Row gutter={16}>
                <Col span={12}>
                  <div style={{ marginBottom: 16 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                      <span>Fertilizantes</span>
                      <span>{inventoryByCategory?.fertilizante || 0}%</span>
                    </div>
                    <Progress percent={inventoryByCategory?.fertilizante || 0} strokeColor="#4CAF50" />
                  </div>
                </Col>
                <Col span={12}>
                  <div style={{ marginBottom: 16 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                      <span>Pesticidas</span>
                      <span>{inventoryByCategory?.pesticida || 0}%</span>
                    </div>
                    <Progress percent={inventoryByCategory?.pesticida || 0} strokeColor="#FF9800" />
                  </div>
                </Col>
                <Col span={12}>
                  <div style={{ marginBottom: 16 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                      <span>Herbicidas</span>
                      <span>{inventoryByCategory?.herbicida || 0}%</span>
                    </div>
                    <Progress percent={inventoryByCategory?.herbicida || 0} strokeColor="#2196F3" />
                  </div>
                </Col>
                <Col span={12}>
                  <div style={{ marginBottom: 16 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                      <span>Fungicidas</span>
                      <span>{inventoryByCategory?.fungicida || 0}%</span>
                    </div>
                    <Progress percent={inventoryByCategory?.fungicida || 0} strokeColor="#9C27B0" />
                  </div>
                </Col>
              </Row>
            </Spin>
          </Card>
        </Col>

        <Col xs={24} lg={8}>
          <Card title="Actividad Reciente" style={{ height: 400 }}>
            <Spin spinning={activityLoading}>
              {recentActivity.length === 0 ? (
                <div style={{ textAlign: 'center', padding: '40px 0', color: '#999' }}>
                  No hay actividad reciente
                </div>
              ) : (
                <Timeline
                  items={recentActivity.map((activity: any) => ({
                    color: activity.color,
                    children: (
                      <div>
                        <p style={{ margin: 0, fontWeight: 500 }}>{activity.title}</p>
                        <p style={{ margin: 0, fontSize: 12, color: '#666' }}>
                          {activity.description} - {dayjs(activity.timestamp).fromNow()}
                        </p>
                      </div>
                    ),
                  }))}
                />
              )}
            </Spin>
          </Card>
        </Col>
      </Row>

      {/* Quick Actions */}
      <Row gutter={[16, 16]} style={{ marginTop: 24 }}>
        <Col span={24}>
          <Card title="Acciones Rápidas">
            <Row gutter={[8, 8]}>
              <Col xs={12} sm={12} md={6} lg={6}>
                <Card
                  hoverable
                  style={{ textAlign: 'center', cursor: 'pointer', minHeight: '120px', display: 'flex', alignItems: 'center', justifyContent: 'center' }}
                  onClick={() => handleQuickAction('/technical/orders')}
                  bodyStyle={{ padding: '16px 8px' }}
                >
                  <ExperimentOutlined style={{ fontSize: 24, color: '#4CAF50', marginBottom: 8, display: 'block' }} />
                  <div style={{ fontSize: '12px', lineHeight: '1.2' }}>Nueva Orden Técnica</div>
                </Card>
              </Col>
              <Col xs={12} sm={12} md={6} lg={6}>
                <Card
                  hoverable
                  style={{ textAlign: 'center', cursor: 'pointer', minHeight: '120px', display: 'flex', alignItems: 'center', justifyContent: 'center' }}
                  onClick={() => handleQuickAction('/purchases')}
                  bodyStyle={{ padding: '16px 8px' }}
                >
                  <ShoppingOutlined style={{ fontSize: 24, color: '#2196F3', marginBottom: 8, display: 'block' }} />
                  <div style={{ fontSize: '12px', lineHeight: '1.2' }}>Registrar Compra</div>
                </Card>
              </Col>
              <Col xs={12} sm={12} md={6} lg={6}>
                <Card
                  hoverable
                  style={{ textAlign: 'center', cursor: 'pointer', minHeight: '120px', display: 'flex', alignItems: 'center', justifyContent: 'center' }}
                  onClick={() => handleQuickAction('/reception')}
                  bodyStyle={{ padding: '16px 8px' }}
                >
                  <DatabaseOutlined style={{ fontSize: 24, color: '#FF9800', marginBottom: 8, display: 'block' }} />
                  <div style={{ fontSize: '12px', lineHeight: '1.2' }}>Entrada a Bodega</div>
                </Card>
              </Col>
              <Col xs={12} sm={12} md={6} lg={6}>
                <Card
                  hoverable
                  style={{ textAlign: 'center', cursor: 'pointer', minHeight: '120px', display: 'flex', alignItems: 'center', justifyContent: 'center' }}
                  onClick={() => handleQuickAction('/reports')}
                  bodyStyle={{ padding: '16px 8px' }}
                >
                  <LineChartOutlined style={{ fontSize: 24, color: '#9C27B0', marginBottom: 8, display: 'block' }} />
                  <div style={{ fontSize: '12px', lineHeight: '1.2' }}>Ver Reportes</div>
                </Card>
              </Col>
            </Row>
          </Card>
        </Col>
      </Row>
    </div>
  );
};

export default Dashboard;