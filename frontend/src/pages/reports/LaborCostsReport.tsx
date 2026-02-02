import React, { useState } from 'react';
import { Button, Card, Row, Col, DatePicker, Select, Table, Statistic, Space, message } from 'antd';
import { FileExcelOutlined, FilePdfOutlined, SearchOutlined, DollarOutlined, TeamOutlined, CalendarOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import { useQuery, useMutation } from '@tanstack/react-query';
import { liquidationAnalyticsApi, workersApi, tasksApi } from '../../services/api';
import { formatCurrency } from '../../utils/formatters';
import dayjs from 'dayjs';
import {
  BarChart,
  Bar,
  PieChart,
  Pie,
  Cell,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer,
} from 'recharts';

const { RangePicker } = DatePicker;
const { Option } = Select;

const COLORS = ['#2E7D32', '#1565C0', '#E65100', '#7B1FA2', '#C62828', '#00838F', '#F9A825', '#4E342E'];

const LaborCostsReport: React.FC = () => {
  const [dateRange, setDateRange] = useState<[dayjs.Dayjs | null, dayjs.Dayjs | null]>([null, null]);
  const [groupBy, setGroupBy] = useState<string>('monthly');
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
    mutationFn: (data: any) => liquidationAnalyticsApi.laborCosts(data),
    onSuccess: (response: any) => {
      setReportData(response.data);
      message.success('Reporte generado exitosamente');
    },
    onError: (error: any) => {
      message.error(error.response?.data?.message || error.message || 'Error al generar reporte');
    },
  });

  const handleGenerate = () => {
    if (!dateRange[0] || !dateRange[1]) {
      message.warning('Seleccione un rango de fechas');
      return;
    }

    generateMutation.mutate({
      start_date: dateRange[0].format('YYYY-MM-DD'),
      end_date: dateRange[1].format('YYYY-MM-DD'),
      group_by: groupBy,
      worker_id: selectedWorkerId || null,
      task_id: selectedTaskId || null,
    });
  };

  const handleExportExcel = async () => {
    if (!dateRange[0] || !dateRange[1]) {
      message.warning('Seleccione un rango de fechas');
      return;
    }

    try {
      const params: Record<string, string> = {
        start_date: dateRange[0].format('YYYY-MM-DD'),
        end_date: dateRange[1].format('YYYY-MM-DD'),
        group_by: groupBy,
      };
      if (selectedWorkerId) params.worker_id = selectedWorkerId;
      if (selectedTaskId) params.task_id = selectedTaskId;

      await liquidationAnalyticsApi.exportExcel('labor-costs', params);
      message.success('Exportacion Excel completada');
    } catch (error: any) {
      message.error('Error al exportar Excel');
    }
  };

  const handleExportPdf = async () => {
    if (!dateRange[0] || !dateRange[1]) {
      message.warning('Seleccione un rango de fechas');
      return;
    }

    try {
      const params: Record<string, string> = {
        start_date: dateRange[0].format('YYYY-MM-DD'),
        end_date: dateRange[1].format('YYYY-MM-DD'),
        group_by: groupBy,
      };
      if (selectedWorkerId) params.worker_id = selectedWorkerId;
      if (selectedTaskId) params.task_id = selectedTaskId;

      await liquidationAnalyticsApi.exportPdf('labor-costs', params);
      message.success('Exportacion PDF completada');
    } catch (error: any) {
      message.error('Error al exportar PDF');
    }
  };

  const totals = reportData?.totals || {};
  const periods = reportData?.periods || [];
  const taskDistribution = reportData?.task_distribution || [];

  const columns: ColumnsType<any> = [
    {
      title: 'Periodo',
      dataIndex: 'period',
      key: 'period',
    },
    {
      title: 'Trabajadores',
      dataIndex: 'total_workers',
      key: 'total_workers',
      align: 'center' as const,
    },
    {
      title: 'Asignaciones',
      dataIndex: 'total_assignments',
      key: 'total_assignments',
      align: 'center' as const,
    },
    {
      title: 'Total Bruto',
      dataIndex: 'total_bruto',
      key: 'total_bruto',
      render: (val: number) => formatCurrency(val),
      align: 'right' as const,
    },
    {
      title: 'Deducciones',
      dataIndex: 'total_deducciones',
      key: 'total_deducciones',
      render: (val: number) => <span style={{ color: '#f5222d' }}>{formatCurrency(val)}</span>,
      align: 'right' as const,
    },
    {
      title: 'Total Neto',
      dataIndex: 'total_neto',
      key: 'total_neto',
      render: (val: number) => <span style={{ color: '#2E7D32', fontWeight: 600 }}>{formatCurrency(val)}</span>,
      align: 'right' as const,
    },
    {
      title: 'Promedio',
      dataIndex: 'promedio_diario',
      key: 'promedio_diario',
      render: (val: number) => formatCurrency(val),
      align: 'right' as const,
    },
  ];

  // Chart data for bar chart
  const barChartData = periods.map((p: any) => ({
    name: p.period,
    total_neto: p.total_neto || 0,
  }));

  // Chart data for pie chart
  const pieChartData = taskDistribution.map((t: any) => ({
    name: t.name || t.task_name,
    value: t.total_neto || 0,
  }));

  return (
    <div>
      <div style={{ marginBottom: 16 }}>
        <h1 style={{ margin: 0, color: '#2E7D32' }}>
          <DollarOutlined /> Costos Laborales por Periodo
        </h1>
        <p style={{ color: '#666', margin: 0 }}>Evolucion de costos de mano de obra por periodo</p>
      </div>

      {/* Filters */}
      <Card style={{ marginBottom: 16 }}>
        <Row gutter={[16, 16]} align="bottom">
          <Col xs={24} sm={12} md={6}>
            <div style={{ marginBottom: 4, fontWeight: 500 }}>Rango de Fechas *</div>
            <RangePicker
              style={{ width: '100%' }}
              format="YYYY-MM-DD"
              value={dateRange as [dayjs.Dayjs, dayjs.Dayjs]}
              onChange={(dates) => setDateRange(dates ? [dates[0], dates[1]] : [null, null])}
            />
          </Col>
          <Col xs={24} sm={12} md={4}>
            <div style={{ marginBottom: 4, fontWeight: 500 }}>Agrupacion</div>
            <Select
              style={{ width: '100%' }}
              value={groupBy}
              onChange={setGroupBy}
            >
              <Option value="weekly">Semanal</Option>
              <Option value="biweekly">Quincenal</Option>
              <Option value="monthly">Mensual</Option>
            </Select>
          </Col>
          <Col xs={24} sm={12} md={5}>
            <div style={{ marginBottom: 4, fontWeight: 500 }}>Trabajador (opcional)</div>
            <Select
              placeholder="Todos los trabajadores"
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
          <Col xs={24} sm={12} md={5}>
            <div style={{ marginBottom: 4, fontWeight: 500 }}>Tarea (opcional)</div>
            <Select
              placeholder="Todas las tareas"
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
                disabled={!dateRange[0] || !dateRange[1]}
              >
                Generar
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
          {/* Summary Statistics */}
          <Card style={{ marginBottom: 16 }}>
            <Row gutter={[16, 16]}>
              <Col xs={12} sm={8} md={4}>
                <Statistic
                  title="Trabajadores"
                  value={totals.total_workers || 0}
                  prefix={<TeamOutlined />}
                />
              </Col>
              <Col xs={12} sm={8} md={4}>
                <Statistic
                  title="Asignaciones"
                  value={totals.total_assignments || 0}
                  prefix={<CalendarOutlined />}
                />
              </Col>
              <Col xs={12} sm={8} md={4}>
                <Statistic
                  title="Total Bruto"
                  value={totals.total_bruto || 0}
                  precision={0}
                  prefix="$"
                />
              </Col>
              <Col xs={12} sm={8} md={4}>
                <Statistic
                  title="Total Deducciones"
                  value={totals.total_deducciones || 0}
                  precision={0}
                  prefix="$"
                  valueStyle={{ color: '#f5222d' }}
                />
              </Col>
              <Col xs={12} sm={8} md={4}>
                <Statistic
                  title="Total Neto"
                  value={totals.total_neto || 0}
                  precision={0}
                  prefix={<DollarOutlined />}
                  valueStyle={{ color: '#2E7D32', fontWeight: 700 }}
                />
              </Col>
              <Col xs={12} sm={8} md={4}>
                <Statistic
                  title="Promedio Diario"
                  value={totals.promedio_diario || 0}
                  precision={0}
                  prefix="$"
                  valueStyle={{ color: '#1565C0' }}
                />
              </Col>
            </Row>
          </Card>

          {/* Charts */}
          {(barChartData.length > 0 || pieChartData.length > 0) && (
            <Row gutter={16} style={{ marginBottom: 16 }}>
              {barChartData.length > 0 && (
                <Col xs={24} lg={pieChartData.length > 0 ? 14 : 24}>
                  <Card title="Total Neto por Periodo">
                    <ResponsiveContainer width="100%" height={350}>
                      <BarChart data={barChartData} margin={{ top: 5, right: 30, left: 20, bottom: 5 }}>
                        <CartesianGrid strokeDasharray="3 3" />
                        <XAxis dataKey="name" />
                        <YAxis tickFormatter={(value) => `$${(value / 1000).toFixed(0)}k`} />
                        <Tooltip formatter={(value: number) => formatCurrency(value)} />
                        <Legend />
                        <Bar dataKey="total_neto" name="Total Neto" fill="#2E7D32" />
                      </BarChart>
                    </ResponsiveContainer>
                  </Card>
                </Col>
              )}
              {pieChartData.length > 0 && (
                <Col xs={24} lg={barChartData.length > 0 ? 10 : 24}>
                  <Card title="Distribucion por Tarea">
                    <ResponsiveContainer width="100%" height={350}>
                      <PieChart>
                        <Pie
                          data={pieChartData}
                          cx="50%"
                          cy="50%"
                          labelLine={false}
                          label={(entry: any) => {
                            const name = entry.name?.length > 15 ? entry.name.substring(0, 15) + '...' : entry.name;
                            return name;
                          }}
                          outerRadius={120}
                          fill="#8884d8"
                          dataKey="value"
                        >
                          {pieChartData.map((_: any, index: number) => (
                            <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                          ))}
                        </Pie>
                        <Tooltip formatter={(value: number) => formatCurrency(value)} />
                        <Legend />
                      </PieChart>
                    </ResponsiveContainer>
                  </Card>
                </Col>
              )}
            </Row>
          )}

          {/* Data Table */}
          <Card title="Detalle por Periodo">
            <Table
              dataSource={periods}
              columns={columns}
              rowKey={(record, index) => `${record.period}-${index}`}
              size="small"
              pagination={false}
              summary={() => {
                if (periods.length === 0) return null;
                return (
                  <Table.Summary.Row style={{ background: '#f6ffed', fontWeight: 600 }}>
                    <Table.Summary.Cell index={0}>
                      <strong>TOTAL</strong>
                    </Table.Summary.Cell>
                    <Table.Summary.Cell index={1} align="center">
                      {totals.total_workers || 0}
                    </Table.Summary.Cell>
                    <Table.Summary.Cell index={2} align="center">
                      {totals.total_assignments || 0}
                    </Table.Summary.Cell>
                    <Table.Summary.Cell index={3} align="right">
                      {formatCurrency(totals.total_bruto || 0)}
                    </Table.Summary.Cell>
                    <Table.Summary.Cell index={4} align="right">
                      <span style={{ color: '#f5222d' }}>{formatCurrency(totals.total_deducciones || 0)}</span>
                    </Table.Summary.Cell>
                    <Table.Summary.Cell index={5} align="right">
                      <span style={{ color: '#2E7D32' }}>{formatCurrency(totals.total_neto || 0)}</span>
                    </Table.Summary.Cell>
                    <Table.Summary.Cell index={6} align="right">
                      {formatCurrency(totals.promedio_diario || 0)}
                    </Table.Summary.Cell>
                  </Table.Summary.Row>
                );
              }}
            />
          </Card>
        </>
      )}
    </div>
  );
};

export default LaborCostsReport;
