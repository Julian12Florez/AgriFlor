import React, { useState } from 'react';
import { Card, List, Badge, Tag, Button, Space, Select, Alert, Row, Col, Statistic, Tabs, message } from 'antd';
import { BellOutlined, WarningOutlined, ExclamationCircleOutlined, InfoCircleOutlined, CheckCircleOutlined, CloseOutlined } from '@ant-design/icons';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { alertsApi } from '../../services/api';

const { Option } = Select;
const { TabPane } = Tabs;

const Alerts: React.FC = () => {
  const queryClient = useQueryClient();
  const [filterType, setFilterType] = useState<string | undefined>();
  const [filterSeverity, setFilterSeverity] = useState<string | undefined>();
  const [filterStatus] = useState<string>('active');

  // Fetch alerts from API
  const { data: alertsData, isLoading: alertsLoading } = useQuery({
    queryKey: ['alerts', filterType, filterSeverity, filterStatus],
    queryFn: () => alertsApi.list({
      type: filterType || undefined,
      severity: filterSeverity || undefined,
      status: filterStatus || undefined
    }),
  });

  const alerts = alertsData?.data || [];

  // Resolve alert mutation
  const resolveAlertMutation = useMutation({
    mutationFn: (id: string) => alertsApi.resolve(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['alerts'] });
      message.success('Alerta resuelta correctamente');
    },
    onError: (error: any) => {
      message.error(`Error al resolver alerta: ${error.message}`);
    },
  });

  // Dismiss alert mutation
  const dismissAlertMutation = useMutation({
    mutationFn: (id: string) => alertsApi.dismiss(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['alerts'] });
      message.success('Alerta descartada correctamente');
    },
    onError: (error: any) => {
      message.error(`Error al descartar alerta: ${error.message}`);
    },
  });

  const getAlertIcon = (type: string) => {
    const icons = {
      error: <ExclamationCircleOutlined style={{ color: '#ff4d4f' }} />,
      warning: <WarningOutlined style={{ color: '#fa8c16' }} />,
      info: <InfoCircleOutlined style={{ color: '#1890ff' }} />,
      success: <CheckCircleOutlined style={{ color: '#52c41a' }} />
    };
    return icons[type as keyof typeof icons] || <BellOutlined />;
  };

  const getSeverityColor = (severity: string) => {
    const colors = {
      high: 'red',
      medium: 'orange',
      low: 'blue'
    };
    return colors[severity as keyof typeof colors] || 'default';
  };

  const getSeverityText = (severity: string) => {
    const texts = {
      high: 'Alta',
      medium: 'Media',
      low: 'Baja'
    };
    return texts[severity as keyof typeof texts] || severity;
  };

  const handleDismissAlert = (id: string) => {
    dismissAlertMutation.mutate(id);
  };

  const handleResolveAlert = (id: string) => {
    resolveAlertMutation.mutate(id);
  };

  // Backend handles filtering, so no need to filter locally
  const filteredAlerts = alerts;

  // Estadísticas
  const activeAlerts = alerts.filter(a => a.status === 'active').length;
  const highSeverityAlerts = alerts.filter(a => a.severity === 'high' && a.status === 'active').length;
  const warningAlerts = alerts.filter(a => a.type === 'warning' && a.status === 'active').length;
  const errorAlerts = alerts.filter(a => a.type === 'error' && a.status === 'active').length;

  return (
    <div>
      <div style={{ marginBottom: 16 }}>
        <h1 style={{ margin: 0, color: '#2E7D32' }}>Alertas del Sistema</h1>
        <p style={{ color: '#666', margin: 0 }}>
          Notificaciones y alertas importantes del sistema AgriFlor
        </p>
      </div>

      {/* Resumen de Alertas */}
      <Row gutter={[16, 16]} style={{ marginBottom: 24 }}>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="Alertas Activas"
              value={activeAlerts}
              prefix={<BellOutlined style={{ color: '#1890ff' }} />}
              valueStyle={{ color: activeAlerts > 0 ? '#1890ff' : '#666' }}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="Prioridad Alta"
              value={highSeverityAlerts}
              prefix={<ExclamationCircleOutlined style={{ color: '#ff4d4f' }} />}
              valueStyle={{ color: highSeverityAlerts > 0 ? '#ff4d4f' : '#666' }}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="Advertencias"
              value={warningAlerts}
              prefix={<WarningOutlined style={{ color: '#fa8c16' }} />}
              valueStyle={{ color: warningAlerts > 0 ? '#fa8c16' : '#666' }}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="Errores"
              value={errorAlerts}
              prefix={<ExclamationCircleOutlined style={{ color: '#ff4d4f' }} />}
              valueStyle={{ color: errorAlerts > 0 ? '#ff4d4f' : '#666' }}
            />
          </Card>
        </Col>
      </Row>

      {/* Alertas Críticas */}
      {highSeverityAlerts > 0 && (
        <Alert
          message="Alertas Críticas Detectadas"
          description={`Hay ${highSeverityAlerts} alerta(s) de prioridad alta que requieren atención inmediata.`}
          type="error"
          showIcon
          style={{ marginBottom: 16 }}
        />
      )}

      <Card>
        <Tabs defaultActiveKey="active">
          <TabPane tab={`Activas (${alerts.filter(a => a.status === 'active').length})`} key="active">
            <div style={{ marginBottom: 16 }}>
              <Space>
                <Select
                  placeholder="Filtrar por tipo"
                  allowClear
                  style={{ width: 150 }}
                  value={filterType}
                  onChange={setFilterType}
                >
                  <Option value="error">Error</Option>
                  <Option value="warning">Advertencia</Option>
                  <Option value="info">Información</Option>
                  <Option value="success">Éxito</Option>
                </Select>
                <Select
                  placeholder="Filtrar por severidad"
                  allowClear
                  style={{ width: 150 }}
                  value={filterSeverity}
                  onChange={setFilterSeverity}
                >
                  <Option value="high">Alta</Option>
                  <Option value="medium">Media</Option>
                  <Option value="low">Baja</Option>
                </Select>
              </Space>
            </div>

            <List
              itemLayout="horizontal"
              loading={alertsLoading || resolveAlertMutation.isPending || dismissAlertMutation.isPending}
              dataSource={filteredAlerts.filter(a => a.status === 'active')}
              renderItem={(alert) => (
                <List.Item
                  actions={[
                    <Button
                      type="link"
                      size="small"
                      onClick={() => handleResolveAlert(alert.id)}
                    >
                      Resolver
                    </Button>,
                    <Button
                      type="link"
                      size="small"
                      icon={<CloseOutlined />}
                      onClick={() => handleDismissAlert(alert.id)}
                    >
                      Descartar
                    </Button>
                  ]}
                >
                  <List.Item.Meta
                    avatar={
                      <Badge dot={alert.severity === 'high'}>
                        {getAlertIcon(alert.type)}
                      </Badge>
                    }
                    title={
                      <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <span>{alert.title}</span>
                        <Tag color={getSeverityColor(alert.severity)}>
                          {getSeverityText(alert.severity)}
                        </Tag>
                      </div>
                    }
                    description={
                      <div>
                        <div>{alert.description}</div>
                        <div style={{ marginTop: 4, fontSize: 12, color: '#666' }}>
                          {alert.location && <span>📍 {alert.location}</span>}
                          {alert.product && <span> • 📦 {alert.product}</span>}
                          <span> • ⏰ {alert.timestamp.toLocaleString('es-CO')}</span>
                        </div>
                      </div>
                    }
                  />
                </List.Item>
              )}
            />
          </TabPane>

          <TabPane tab={`Resueltas (${alerts.filter(a => a.status === 'resolved').length})`} key="resolved">
            <List
              itemLayout="horizontal"
              loading={alertsLoading}
              dataSource={alerts.filter(a => a.status === 'resolved')}
              renderItem={(alert) => (
                <List.Item>
                  <List.Item.Meta
                    avatar={getAlertIcon(alert.type)}
                    title={alert.title}
                    description={
                      <div>
                        <div>{alert.description}</div>
                        <div style={{ marginTop: 4, fontSize: 12, color: '#666' }}>
                          {alert.location && <span>📍 {alert.location}</span>}
                          <span> • ⏰ {alert.timestamp.toLocaleString('es-CO')}</span>
                        </div>
                      </div>
                    }
                  />
                </List.Item>
              )}
            />
          </TabPane>

          <TabPane tab={`Descartadas (${alerts.filter(a => a.status === 'dismissed').length})`} key="dismissed">
            <List
              itemLayout="horizontal"
              loading={alertsLoading}
              dataSource={alerts.filter(a => a.status === 'dismissed')}
              renderItem={(alert) => (
                <List.Item>
                  <List.Item.Meta
                    avatar={getAlertIcon(alert.type)}
                    title={<span style={{ opacity: 0.6 }}>{alert.title}</span>}
                    description={
                      <div style={{ opacity: 0.6 }}>
                        <div>{alert.description}</div>
                        <div style={{ marginTop: 4, fontSize: 12, color: '#666' }}>
                          {alert.location && <span>📍 {alert.location}</span>}
                          <span> • ⏰ {alert.timestamp.toLocaleString('es-CO')}</span>
                        </div>
                      </div>
                    }
                  />
                </List.Item>
              )}
            />
          </TabPane>
        </Tabs>
      </Card>
    </div>
  );
};

export default Alerts;