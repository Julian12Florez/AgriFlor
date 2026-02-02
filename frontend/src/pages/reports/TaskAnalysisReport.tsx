import React, { useState } from 'react';
import { Button, Card, Row, Col, DatePicker, Select, Table, Statistic, Space, message } from 'antd';
import { FileExcelOutlined, FilePdfOutlined, SearchOutlined, ToolOutlined, DollarOutlined, BarChartOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import { useQuery, useMutation } from '@tanstack/react-query';
import { liquidationAnalyticsApi, tasksApi } from '../../services/api';
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

const TaskAnalysisReport: React.FC = () => {
  const [dateRange, setDateRange] = useState<[dayjs.Dayjs | null, dayjs.Dayjs | null]>([null, null]);
  const [selectedTaskId, setSelectedTaskId] = useState<string | undefined>();
  const [sortBy, setSortBy] = useState<string>('total_neto');
  const [reportData, setReportData] = useState<any | null>(null);

  // Fetch tasks for dropdown
  const { data: tasksData } = useQuery({
    queryKey: ['tasks-simple'],
    queryFn: () => tasksApi.listSimple({ status: 'active' }),
  });

  const tasksList = tasksData?.data || [];

  const generateMutation = useMutation({
    mutationFn: (data: any) => liquidationAnalyticsApi.taskAnalysis(data),
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
      task_id: selectedTaskId || null,
      sort_by: sortBy,
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
        sort_by: sortBy,
      };
      if (selectedTaskId) params.task_id = selectedTaskId;

      await liquidationAnalyticsApi.exportExcel('task-analysis', params);
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
        sort_by: sortBy,
      };
      if (selectedTaskId) params.task_id = selectedTaskId;

      await liquidationAnalyticsApi.exportPdf('task-analysis', params);
      message.success('Exportacion PDF completada');
    } catch (error: any) {
      message.error('Error al exportar PDF');
    }
  };

  const totals = reportData?.totals || {};
  const tasksReport = reportData?.tasks || [];

  // Pie chart: % costo total by task
  const pieChartData = tasksReport.map((t: any) => ({
    name: t.name || t.task_name || 'N/A',
    value: t.total_neto || 0,
  }));

  // Bar chart: frequency by task
  const barChartData = [...tasksReport]
    .sort((a: any, b: any) => (b.veces_asignada || 0) - (a.veces_asignada || 0))
    .slice(0, 15)
    .map((t: any) => ({
      name: (t.name || t.task_name || 'N/A').length > 15
        ? (t.name || t.task_name || 'N/A').substring(0, 15) + '...'
        : (t.name || t.task_name || 'N/A'),
      veces_asignada: t.veces_asignada || 0,
    }));

  const columns: ColumnsType<any> = [
    {
      title: 'Codigo',
      dataIndex: 'code',
      key: 'code',
      width: 100,
      render: (code: string) => <span style={{ fontWeight: 500 }}>{code || 'N/A'}</span>,
    },
    {
      title: 'Tarea',
      dataIndex: 'name',
      key: 'name',
      render: (name: string, record: any) => name || record.task_name || 'N/A',
    },
    {
      title: 'Costo Unitario',
      dataIndex: 'costo_unitario',
      key: 'costo_unitario',
      render: (val: number) => formatCurrency(val),
      align: 'right' as const,
    },
    {
      title: 'Veces Asignada',
      dataIndex: 'veces_asignada',
      key: 'veces_asignada',
      align: 'center' as const,
      sorter: (a: any, b: any) => (a.veces_asignada || 0) - (b.veces_asignada || 0),
    },
    {
      title: 'Trabajadores',
      dataIndex: 'total_workers',
      key: 'total_workers',
      align: 'center' as const,
    },
    {
      title: 'Total Bruto',
      dataIndex: 'total_bruto',
      key: 'total_bruto',
      render: (val: number) => formatCurrency(val),
      align: 'right' as const,
      sorter: (a: any, b: any) => (a.total_bruto || 0) - (b.total_bruto || 0),
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
      sorter: (a: any, b: any) => (a.total_neto || 0) - (b.total_neto || 0),
      defaultSortOrder: 'descend' as const,
    },
    {
      title: '% Costo',
      dataIndex: 'porcentaje_costo',
      key: 'porcentaje_costo',
      width: 100,
      render: (val: number) => {
        const pct = val || 0;
        return <span style={{ fontWeight: 500 }}>{pct.toFixed(1)}%</span>;
      },
      align: 'right' as const,
      sorter: (a: any, b: any) => (a.porcentaje_costo || 0) - (b.porcentaje_costo || 0),
    },
  ];

  return (
    <div>
      <div style={{ marginBottom: 16 }}>
        <h1 style={{ margin: 0, color: '#2E7D32' }}>
          <ToolOutlined /> Analisis de Tareas
        </h1>
        <p style={{ color: '#666', margin: 0 }}>Distribucion y costos por tipo de tarea</p>
      </div>

      {/* Filters */}
      <Card style={{ marginBottom: 16 }}>
        <Row gutter={[16, 16]} align="bottom">
          <Col xs={24} sm={12} md={7}>
            <div style={{ marginBottom: 4, fontWeight: 500 }}>Rango de Fechas *</div>
            <RangePicker
              style={{ width: '100%' }}
              format="YYYY-MM-DD"
              value={dateRange as [dayjs.Dayjs, dayjs.Dayjs]}
              onChange={(dates) => setDateRange(dates ? [dates[0], dates[1]] : [null, null])}
            />
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
              {tasksList.map((t: any) => (
                <Option key={t.id} value={t.id}>
                  {t.code} - {t.name}
                </Option>
              ))}
            </Select>
          </Col>
          <Col xs={24} sm={12} md={5}>
            <div style={{ marginBottom: 4, fontWeight: 500 }}>Ordenar por</div>
            <Select
              style={{ width: '100%' }}
              value={sortBy}
              onChange={setSortBy}
            >
              <Option value="veces_asignada">Frecuencia</Option>
              <Option value="total_neto">Costo total</Option>
              <Option value="costo_unitario">Costo unitario</Option>
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
              <Col xs={12} sm={8} md={5}>
                <Statistic
                  title="Total Tareas"
                  value={totals.total_tasks || tasksReport.length || 0}
                  prefix={<ToolOutlined />}
                />
              </Col>
              <Col xs={12} sm={8} md={5}>
                <Statistic
                  title="Total Asignaciones"
                  value={totals.total_assignments || 0}
                  prefix={<BarChartOutlined />}
                />
              </Col>
              <Col xs={12} sm={8} md={5}>
                <Statistic
                  title="Total Bruto"
                  value={totals.total_bruto || 0}
                  precision={0}
                  prefix="$"
                />
              </Col>
              <Col xs={12} sm={8} md={5}>
                <Statistic
                  title="Total Deducciones"
                  value={totals.total_deducciones || 0}
                  precision={0}
                  prefix="$"
                  valueStyle={{ color: '#f5222d' }}
                />
              </Col>
              <Col xs={24} sm={8} md={4}>
                <Statistic
                  title="Total Neto"
                  value={totals.total_neto || 0}
                  precision={0}
                  prefix={<DollarOutlined />}
                  valueStyle={{ color: '#2E7D32', fontWeight: 700 }}
                />
              </Col>
            </Row>
          </Card>

          {/* Charts */}
          {tasksReport.length > 0 && (
            <Row gutter={16} style={{ marginBottom: 16 }}>
              <Col xs={24} lg={12}>
                <Card title="% del Costo Total por Tarea">
                  <ResponsiveContainer width="100%" height={350}>
                    <PieChart>
                      <Pie
                        data={pieChartData}
                        cx="50%"
                        cy="50%"
                        labelLine={false}
                        label={(entry: any) => {
                          const name = entry.name?.length > 12 ? entry.name.substring(0, 12) + '...' : entry.name;
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
              <Col xs={24} lg={12}>
                <Card title="Frecuencia de Asignacion por Tarea">
                  <ResponsiveContainer width="100%" height={350}>
                    <BarChart data={barChartData} layout="vertical" margin={{ left: 100, right: 30, top: 5, bottom: 5 }}>
                      <CartesianGrid strokeDasharray="3 3" />
                      <XAxis type="number" />
                      <YAxis type="category" dataKey="name" width={100} />
                      <Tooltip formatter={(value: number) => `${value} asignaciones`} />
                      <Bar dataKey="veces_asignada" name="Veces Asignada" fill="#1565C0" />
                    </BarChart>
                  </ResponsiveContainer>
                </Card>
              </Col>
            </Row>
          )}

          {/* Data Table */}
          <Card title={`Detalle de Tareas (${tasksReport.length})`}>
            <Table
              dataSource={tasksReport}
              columns={columns}
              rowKey={(record, index) => record.task_id || record.id || `task-${index}`}
              size="small"
              pagination={false}
              summary={() => {
                if (tasksReport.length === 0) return null;
                const totalVeces = tasksReport.reduce((sum: number, t: any) => sum + (t.veces_asignada || 0), 0);
                return (
                  <Table.Summary.Row style={{ background: '#f6ffed', fontWeight: 600 }}>
                    <Table.Summary.Cell index={0} colSpan={3}>
                      <strong>TOTAL</strong>
                    </Table.Summary.Cell>
                    <Table.Summary.Cell index={3} align="center">
                      {totalVeces}
                    </Table.Summary.Cell>
                    <Table.Summary.Cell index={4} align="center">
                      -
                    </Table.Summary.Cell>
                    <Table.Summary.Cell index={5} align="right">
                      {formatCurrency(totals.total_bruto || 0)}
                    </Table.Summary.Cell>
                    <Table.Summary.Cell index={6} align="right">
                      <span style={{ color: '#f5222d' }}>{formatCurrency(totals.total_deducciones || 0)}</span>
                    </Table.Summary.Cell>
                    <Table.Summary.Cell index={7} align="right">
                      <span style={{ color: '#2E7D32' }}>{formatCurrency(totals.total_neto || 0)}</span>
                    </Table.Summary.Cell>
                    <Table.Summary.Cell index={8} align="right">
                      100.0%
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

export default TaskAnalysisReport;
