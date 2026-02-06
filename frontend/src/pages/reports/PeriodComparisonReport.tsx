import React, { useState } from 'react';
import { Button, Card, Row, Col, DatePicker, Select, Table, Space, message, Tag, List, Empty } from 'antd';
import {
  FileExcelOutlined,
  FilePdfOutlined,
  SearchOutlined,
  SwapOutlined,
  ArrowUpOutlined,
  ArrowDownOutlined,
  UserAddOutlined,
  UserDeleteOutlined,
  PlusCircleOutlined,
  MinusCircleOutlined,
} from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import { useQuery, useMutation } from '@tanstack/react-query';
import { liquidationAnalyticsApi, workersApi, tasksApi } from '../../services/api';
import { formatCurrency } from '../../utils/formatters';
import dayjs from 'dayjs';
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer,
} from 'recharts';

const { RangePicker } = DatePicker;
const { Option } = Select;

const PeriodComparisonReport: React.FC = () => {
  const [dateRange1, setDateRange1] = useState<[dayjs.Dayjs | null, dayjs.Dayjs | null]>([null, null]);
  const [dateRange2, setDateRange2] = useState<[dayjs.Dayjs | null, dayjs.Dayjs | null]>([null, null]);
  const [selectedWorkerId, setSelectedWorkerId] = useState<string | undefined>();
  const [selectedTaskId, setSelectedTaskId] = useState<string | undefined>();
  const [reportData, setReportData] = useState<any | null>(null);

  // Fetch workers and tasks for dropdowns
  const { data: workersData } = useQuery({
    queryKey: ['workers-simple'],
    queryFn: () => workersApi.listSimple({ status: 'active' }),
  });

  const { data: tasksData } = useQuery({
    queryKey: ['tasks-simple'],
    queryFn: () => tasksApi.listSimple({ status: 'active' }),
  });

  const workers = workersData?.data || [];
  const tasks = tasksData?.data || [];

  const generateMutation = useMutation({
    mutationFn: (data: any) => liquidationAnalyticsApi.periodComparison(data),
    onSuccess: (response: any) => {
      setReportData(response.data);
      message.success('Reporte generado exitosamente');
    },
    onError: (error: any) => {
      message.error(error.response?.data?.message || error.message || 'Error al generar reporte');
    },
  });

  const handleGenerate = () => {
    if (!dateRange1[0] || !dateRange1[1] || !dateRange2[0] || !dateRange2[1]) {
      message.warning('Seleccione ambos rangos de fechas');
      return;
    }

    generateMutation.mutate({
      period1_start: dateRange1[0].format('YYYY-MM-DD'),
      period1_end: dateRange1[1].format('YYYY-MM-DD'),
      period2_start: dateRange2[0].format('YYYY-MM-DD'),
      period2_end: dateRange2[1].format('YYYY-MM-DD'),
      worker_id: selectedWorkerId || null,
      task_id: selectedTaskId || null,
    });
  };

  const handleExportExcel = async () => {
    if (!dateRange1[0] || !dateRange1[1] || !dateRange2[0] || !dateRange2[1]) {
      message.warning('Seleccione ambos rangos de fechas');
      return;
    }

    try {
      const params: Record<string, string> = {
        period1_start: dateRange1[0].format('YYYY-MM-DD'),
        period1_end: dateRange1[1].format('YYYY-MM-DD'),
        period2_start: dateRange2[0].format('YYYY-MM-DD'),
        period2_end: dateRange2[1].format('YYYY-MM-DD'),
      };
      if (selectedWorkerId) params.worker_id = selectedWorkerId;
      if (selectedTaskId) params.task_id = selectedTaskId;

      await liquidationAnalyticsApi.exportExcel('period-comparison', params);
      message.success('Exportacion Excel completada');
    } catch (error: any) {
      message.error('Error al exportar Excel');
    }
  };

  const handleExportPdf = async () => {
    if (!dateRange1[0] || !dateRange1[1] || !dateRange2[0] || !dateRange2[1]) {
      message.warning('Seleccione ambos rangos de fechas');
      return;
    }

    try {
      const params: Record<string, string> = {
        period1_start: dateRange1[0].format('YYYY-MM-DD'),
        period1_end: dateRange1[1].format('YYYY-MM-DD'),
        period2_start: dateRange2[0].format('YYYY-MM-DD'),
        period2_end: dateRange2[1].format('YYYY-MM-DD'),
      };
      if (selectedWorkerId) params.worker_id = selectedWorkerId;
      if (selectedTaskId) params.task_id = selectedTaskId;

      await liquidationAnalyticsApi.exportPdf('period-comparison', params);
      message.success('Exportacion PDF completada');
    } catch (error: any) {
      message.error('Error al exportar PDF');
    }
  };

  const metrics = reportData?.metrics || [];
  const changes = reportData?.changes || {};

  // Get variation tag color
  const getVariationTagColor = (variationPct: number): string => {
    const abs = Math.abs(variationPct);
    if (abs < 5) return 'green';
    if (abs < 15) return 'orange';
    return 'red';
  };

  // Format variation display
  const formatVariation = (diff: number, _variationPct: number, metricName: string) => {
    const isPositiveGood = metricName.toLowerCase().includes('neto') ||
                           metricName.toLowerCase().includes('bruto') ||
                           metricName.toLowerCase().includes('trabajadores') ||
                           metricName.toLowerCase().includes('asignaciones');

    const isPositive = diff > 0;
    const isGood = isPositiveGood ? isPositive : !isPositive;

    return {
      color: isGood ? '#2E7D32' : '#C62828',
      icon: isPositive ? <ArrowUpOutlined /> : <ArrowDownOutlined />,
    };
  };

  // Metrics table columns
  const metricsColumns: ColumnsType<any> = [
    {
      title: 'Metrica',
      dataIndex: 'metric',
      key: 'metric',
      render: (text: string) => <strong>{text}</strong>,
    },
    {
      title: reportData?.period1_label || 'Periodo 1',
      dataIndex: 'period1_value',
      key: 'period1_value',
      align: 'right' as const,
      render: (val: number, record: any) => {
        if (record.is_currency) return formatCurrency(val);
        if (record.is_percentage) return `${(val || 0).toFixed(1)}%`;
        return (val || 0).toLocaleString();
      },
    },
    {
      title: reportData?.period2_label || 'Periodo 2',
      dataIndex: 'period2_value',
      key: 'period2_value',
      align: 'right' as const,
      render: (val: number, record: any) => {
        if (record.is_currency) return formatCurrency(val);
        if (record.is_percentage) return `${(val || 0).toFixed(1)}%`;
        return (val || 0).toLocaleString();
      },
    },
    {
      title: 'Diferencia',
      dataIndex: 'difference',
      key: 'difference',
      align: 'right' as const,
      render: (diff: number, record: any) => {
        const { color, icon } = formatVariation(diff, record.variation_pct || 0, record.metric);
        if (record.is_currency) {
          return (
            <span style={{ color, fontWeight: 600 }}>
              {icon} {formatCurrency(Math.abs(diff))}
            </span>
          );
        }
        if (record.is_percentage) {
          return (
            <span style={{ color, fontWeight: 600 }}>
              {icon} {Math.abs(diff).toFixed(1)}%
            </span>
          );
        }
        return (
          <span style={{ color, fontWeight: 600 }}>
            {icon} {Math.abs(diff || 0).toLocaleString()}
          </span>
        );
      },
    },
    {
      title: 'Variacion %',
      dataIndex: 'variation_pct',
      key: 'variation_pct',
      align: 'center' as const,
      width: 130,
      render: (pct: number, record: any) => {
        const tagColor = getVariationTagColor(pct);
        formatVariation(record.difference || 0, pct, record.metric);
        return (
          <Tag color={tagColor}>
            <span style={{ color: tagColor === 'green' ? '#2E7D32' : undefined }}>
              {pct > 0 ? '+' : ''}{(pct || 0).toFixed(1)}%
            </span>
          </Tag>
        );
      },
    },
  ];

  // Chart data for grouped bar chart
  const chartData = metrics
    .filter((m: any) => m.is_currency)
    .map((m: any) => ({
      name: m.metric,
      [reportData?.period1_label || 'Periodo 1']: m.period1_value || 0,
      [reportData?.period2_label || 'Periodo 2']: m.period2_value || 0,
    }));

  const newWorkers = changes.new_workers || [];
  const leftWorkers = changes.left_workers || [];
  const newTasks = changes.new_tasks || [];
  const leftTasks = changes.left_tasks || [];

  return (
    <div>
      <div style={{ marginBottom: 16 }}>
        <h1 style={{ margin: 0, color: '#2E7D32' }}>
          <SwapOutlined /> Comparativo entre Periodos
        </h1>
        <p style={{ color: '#666', margin: 0 }}>Comparacion de metricas entre dos periodos</p>
      </div>

      {/* Filters */}
      <Card style={{ marginBottom: 16 }}>
        <Row gutter={[16, 16]} align="bottom">
          <Col xs={24} sm={12} md={6}>
            <div style={{ marginBottom: 4, fontWeight: 500 }}>Periodo 1 *</div>
            <RangePicker
              style={{ width: '100%' }}
              format="YYYY-MM-DD"
              value={dateRange1 as [dayjs.Dayjs, dayjs.Dayjs]}
              onChange={(dates) => setDateRange1(dates ? [dates[0], dates[1]] : [null, null])}
              placeholder={['Inicio P1', 'Fin P1']}
            />
          </Col>
          <Col xs={24} sm={12} md={6}>
            <div style={{ marginBottom: 4, fontWeight: 500 }}>Periodo 2 *</div>
            <RangePicker
              style={{ width: '100%' }}
              format="YYYY-MM-DD"
              value={dateRange2 as [dayjs.Dayjs, dayjs.Dayjs]}
              onChange={(dates) => setDateRange2(dates ? [dates[0], dates[1]] : [null, null])}
              placeholder={['Inicio P2', 'Fin P2']}
            />
          </Col>
          <Col xs={24} sm={12} md={4}>
            <div style={{ marginBottom: 4, fontWeight: 500 }}>Trabajador (opcional)</div>
            <Select
              placeholder="Todos"
              allowClear
              showSearch
              optionFilterProp="children"
              style={{ width: '100%' }}
              value={selectedWorkerId}
              onChange={setSelectedWorkerId}
            >
              {workers.map((w: any) => (
                <Option key={w.id} value={w.id}>
                  {w.worker_code} - {w.full_name}
                </Option>
              ))}
            </Select>
          </Col>
          <Col xs={24} sm={12} md={4}>
            <div style={{ marginBottom: 4, fontWeight: 500 }}>Tarea (opcional)</div>
            <Select
              placeholder="Todas"
              allowClear
              showSearch
              optionFilterProp="children"
              style={{ width: '100%' }}
              value={selectedTaskId}
              onChange={setSelectedTaskId}
            >
              {tasks.map((t: any) => (
                <Option key={t.id} value={t.id}>
                  {t.code} - {t.name}
                </Option>
              ))}
            </Select>
          </Col>
          <Col xs={24} sm={24} md={4}>
            <Space wrap>
              <Button
                type="primary"
                icon={<SearchOutlined />}
                onClick={handleGenerate}
                loading={generateMutation.isPending}
                disabled={!dateRange1[0] || !dateRange1[1] || !dateRange2[0] || !dateRange2[1]}
              >
                Comparar
              </Button>
            </Space>
          </Col>
        </Row>
        {reportData && (
          <div style={{ marginTop: 16 }}>
            <Space wrap>
              <Button icon={<FileExcelOutlined />} onClick={handleExportExcel} style={{ color: '#217346' }}>
                Exportar Excel
              </Button>
              <Button icon={<FilePdfOutlined />} onClick={handleExportPdf} style={{ color: '#d32f2f' }}>
                Exportar PDF
              </Button>
            </Space>
          </div>
        )}
      </Card>

      {/* Report Results */}
      {reportData && (
        <>
          {/* Metrics Table */}
          <Card title="Comparativo de Metricas" style={{ marginBottom: 16 }}>
            <Table
              dataSource={metrics}
              columns={metricsColumns}
              rowKey={(record, index) => record.metric || `metric-${index}`}
              size="small"
              pagination={false}
              scroll={{ x: 'max-content' }}
            />
          </Card>

          {/* Changes Cards */}
          {(newWorkers.length > 0 || leftWorkers.length > 0 || newTasks.length > 0 || leftTasks.length > 0) && (
            <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
              <Col xs={24} sm={12} md={6}>
                <Card
                  title={
                    <span>
                      <UserAddOutlined style={{ color: '#2E7D32', marginRight: 8 }} />
                      Nuevos Trabajadores
                    </span>
                  }
                  size="small"
                  style={{ height: '100%' }}
                >
                  {newWorkers.length > 0 ? (
                    <List
                      size="small"
                      dataSource={newWorkers}
                      renderItem={(item: any) => (
                        <List.Item style={{ padding: '4px 0' }}>
                          <Tag color="green">{typeof item === 'string' ? item : item.name || item.full_name || 'N/A'}</Tag>
                        </List.Item>
                      )}
                    />
                  ) : (
                    <Empty description="Sin cambios" image={Empty.PRESENTED_IMAGE_SIMPLE} />
                  )}
                </Card>
              </Col>
              <Col xs={24} sm={12} md={6}>
                <Card
                  title={
                    <span>
                      <UserDeleteOutlined style={{ color: '#C62828', marginRight: 8 }} />
                      Trabajadores que salieron
                    </span>
                  }
                  size="small"
                  style={{ height: '100%' }}
                >
                  {leftWorkers.length > 0 ? (
                    <List
                      size="small"
                      dataSource={leftWorkers}
                      renderItem={(item: any) => (
                        <List.Item style={{ padding: '4px 0' }}>
                          <Tag color="red">{typeof item === 'string' ? item : item.name || item.full_name || 'N/A'}</Tag>
                        </List.Item>
                      )}
                    />
                  ) : (
                    <Empty description="Sin cambios" image={Empty.PRESENTED_IMAGE_SIMPLE} />
                  )}
                </Card>
              </Col>
              <Col xs={24} sm={12} md={6}>
                <Card
                  title={
                    <span>
                      <PlusCircleOutlined style={{ color: '#1565C0', marginRight: 8 }} />
                      Nuevas Tareas
                    </span>
                  }
                  size="small"
                  style={{ height: '100%' }}
                >
                  {newTasks.length > 0 ? (
                    <List
                      size="small"
                      dataSource={newTasks}
                      renderItem={(item: any) => (
                        <List.Item style={{ padding: '4px 0' }}>
                          <Tag color="blue">{typeof item === 'string' ? item : item.name || 'N/A'}</Tag>
                        </List.Item>
                      )}
                    />
                  ) : (
                    <Empty description="Sin cambios" image={Empty.PRESENTED_IMAGE_SIMPLE} />
                  )}
                </Card>
              </Col>
              <Col xs={24} sm={12} md={6}>
                <Card
                  title={
                    <span>
                      <MinusCircleOutlined style={{ color: '#E65100', marginRight: 8 }} />
                      Tareas que salieron
                    </span>
                  }
                  size="small"
                  style={{ height: '100%' }}
                >
                  {leftTasks.length > 0 ? (
                    <List
                      size="small"
                      dataSource={leftTasks}
                      renderItem={(item: any) => (
                        <List.Item style={{ padding: '4px 0' }}>
                          <Tag color="volcano">{typeof item === 'string' ? item : item.name || 'N/A'}</Tag>
                        </List.Item>
                      )}
                    />
                  ) : (
                    <Empty description="Sin cambios" image={Empty.PRESENTED_IMAGE_SIMPLE} />
                  )}
                </Card>
              </Col>
            </Row>
          )}

          {/* Grouped Bar Chart */}
          {chartData.length > 0 && (
            <Card title="Comparativo Visual" style={{ marginBottom: 16 }}>
              <ResponsiveContainer width="100%" height={400}>
                <BarChart data={chartData} margin={{ top: 5, right: 30, left: 20, bottom: 5 }}>
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="name" />
                  <YAxis tickFormatter={(value) => `$${(value / 1000).toFixed(0)}k`} />
                  <Tooltip formatter={(value: number) => formatCurrency(value)} />
                  <Legend />
                  <Bar
                    dataKey={reportData?.period1_label || 'Periodo 1'}
                    name={reportData?.period1_label || 'Periodo 1'}
                    fill="#1565C0"
                  />
                  <Bar
                    dataKey={reportData?.period2_label || 'Periodo 2'}
                    name={reportData?.period2_label || 'Periodo 2'}
                    fill="#2E7D32"
                  />
                </BarChart>
              </ResponsiveContainer>
            </Card>
          )}
        </>
      )}
    </div>
  );
};

export default PeriodComparisonReport;
