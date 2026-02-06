import React, { useState } from 'react';
import { Button, Card, Row, Col, DatePicker, Select, Table, Statistic, Space, message, Progress } from 'antd';
import { FileExcelOutlined, FilePdfOutlined, SearchOutlined, TeamOutlined, TrophyOutlined, RiseOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import { useMutation } from '@tanstack/react-query';
import { liquidationAnalyticsApi } from '../../services/api';
import { formatCurrency } from '../../utils/formatters';
import dayjs from 'dayjs';
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from 'recharts';

const { RangePicker } = DatePicker;
const { Option } = Select;

const WorkerProductivityReport: React.FC = () => {
  const [dateRange, setDateRange] = useState<[dayjs.Dayjs | null, dayjs.Dayjs | null]>([null, null]);
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [sortBy, setSortBy] = useState<string>('total_neto');
  const [reportData, setReportData] = useState<any | null>(null);

  const generateMutation = useMutation({
    mutationFn: (data: any) => liquidationAnalyticsApi.workerProductivity(data),
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
      status: statusFilter !== 'all' ? statusFilter : null,
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
      if (statusFilter !== 'all') params.status = statusFilter;

      await liquidationAnalyticsApi.exportExcel('worker-productivity', params);
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
      if (statusFilter !== 'all') params.status = statusFilter;

      await liquidationAnalyticsApi.exportPdf('worker-productivity', params);
      message.success('Exportacion PDF completada');
    } catch (error: any) {
      message.error('Error al exportar PDF');
    }
  };

  const totals = reportData?.totals || {};
  const workersReport = reportData?.workers || [];

  // Top 10 workers chart data
  const top10Data = [...workersReport]
    .sort((a: any, b: any) => (b.total_neto || 0) - (a.total_neto || 0))
    .slice(0, 10)
    .map((w: any) => ({
      name: w.full_name
        ? (w.full_name.length > 20 ? w.full_name.substring(0, 20) + '...' : w.full_name)
        : 'N/A',
      total_neto: w.total_neto || 0,
    }));

  const columns: ColumnsType<any> = [
    {
      title: '#',
      key: 'rank',
      width: 50,
      render: (_: any, __: any, index: number) => (
        <span style={{
          fontWeight: index < 3 ? 700 : 400,
          color: index === 0 ? '#F9A825' : index === 1 ? '#9E9E9E' : index === 2 ? '#8D6E63' : undefined,
        }}>
          {index + 1}
        </span>
      ),
    },
    {
      title: 'Codigo',
      dataIndex: 'worker_code',
      key: 'worker_code',
      width: 100,
    },
    {
      title: 'Nombre',
      dataIndex: 'full_name',
      key: 'full_name',
      render: (name: string, _: any, index: number) => (
        <span style={{ fontWeight: index < 3 ? 600 : 400 }}>
          {index === 0 && <TrophyOutlined style={{ color: '#F9A825', marginRight: 4 }} />}
          {name}
        </span>
      ),
    },
    {
      title: 'Documento',
      dataIndex: 'document_id',
      key: 'document_id',
      width: 120,
    },
    {
      title: 'Dias',
      dataIndex: 'days_worked',
      key: 'days_worked',
      width: 70,
      align: 'center' as const,
      sorter: (a: any, b: any) => (a.days_worked || 0) - (b.days_worked || 0),
    },
    {
      title: '% Actividad',
      dataIndex: 'activity_percentage',
      key: 'activity_percentage',
      width: 150,
      render: (val: number) => {
        const percentage = val || 0;
        const color = percentage >= 80 ? '#2E7D32' : percentage >= 50 ? '#E65100' : '#C62828';
        return (
          <Progress
            percent={Math.round(percentage)}
            size="small"
            strokeColor={color}
            format={(p) => `${p}%`}
          />
        );
      },
      sorter: (a: any, b: any) => (a.activity_percentage || 0) - (b.activity_percentage || 0),
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
      title: 'Promedio',
      dataIndex: 'promedio_diario',
      key: 'promedio_diario',
      render: (val: number) => formatCurrency(val),
      align: 'right' as const,
      sorter: (a: any, b: any) => (a.promedio_diario || 0) - (b.promedio_diario || 0),
    },
  ];

  return (
    <div>
      <div style={{ marginBottom: 16 }}>
        <h1 style={{ margin: 0, color: '#2E7D32' }}>
          <TrophyOutlined /> Productividad por Trabajador
        </h1>
        <p style={{ color: '#666', margin: 0 }}>Ranking de trabajadores por rendimiento en el periodo</p>
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
            <div style={{ marginBottom: 4, fontWeight: 500 }}>Estado</div>
            <Select
              style={{ width: '100%' }}
              value={statusFilter}
              onChange={setStatusFilter}
            >
              <Option value="all">Todos</Option>
              <Option value="active">Activos</Option>
              <Option value="inactive">Inactivos</Option>
            </Select>
          </Col>
          <Col xs={24} sm={12} md={5}>
            <div style={{ marginBottom: 4, fontWeight: 500 }}>Ordenar por</div>
            <Select
              style={{ width: '100%' }}
              value={sortBy}
              onChange={setSortBy}
            >
              <Option value="days_worked">Dias trabajados</Option>
              <Option value="total_neto">Total neto</Option>
              <Option value="promedio_diario">Promedio diario</Option>
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
                  title="Total Trabajadores"
                  value={totals.total_workers || 0}
                  prefix={<TeamOutlined />}
                />
              </Col>
              <Col xs={12} sm={8} md={4}>
                <Statistic
                  title="Total Asignaciones"
                  value={totals.total_assignments || 0}
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
                  title="Total Neto"
                  value={totals.total_neto || 0}
                  precision={0}
                  prefix="$"
                  valueStyle={{ color: '#2E7D32', fontWeight: 700 }}
                />
              </Col>
              <Col xs={12} sm={8} md={4}>
                <Statistic
                  title="Promedio Dias"
                  value={totals.avg_days_worked || 0}
                  precision={1}
                />
              </Col>
              <Col xs={12} sm={8} md={4}>
                <Statistic
                  title="Max Dias"
                  value={totals.max_days_worked || 0}
                  prefix={<RiseOutlined />}
                  valueStyle={{ color: '#1565C0' }}
                />
              </Col>
            </Row>
          </Card>

          {/* Top 10 Chart */}
          {top10Data.length > 0 && (
            <Card title="Top 10 Trabajadores por Total Neto" style={{ marginBottom: 16 }}>
              <ResponsiveContainer width="100%" height={400}>
                <BarChart data={top10Data} layout="vertical" margin={{ left: 120, right: 30, top: 5, bottom: 5 }}>
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis type="number" tickFormatter={(value) => `$${(value / 1000).toFixed(0)}k`} />
                  <YAxis type="category" dataKey="name" width={120} />
                  <Tooltip formatter={(value: number) => formatCurrency(value)} />
                  <Bar dataKey="total_neto" name="Total Neto" fill="#2E7D32" />
                </BarChart>
              </ResponsiveContainer>
            </Card>
          )}

          {/* Data Table */}
          <Card title={`Ranking de Trabajadores (${workersReport.length})`}>
            <Table
              dataSource={workersReport}
              columns={columns}
              rowKey={(record, index) => record.worker_id || record.id || `worker-${index}`}
              size="small"
              pagination={{ pageSize: 50, showSizeChanger: true, showTotal: (total) => `Total: ${total} trabajadores` }}
              scroll={{ x: 'max-content' }}
              summary={() => {
                if (workersReport.length === 0) return null;
                return (
                  <Table.Summary.Row style={{ background: '#f6ffed', fontWeight: 600 }}>
                    <Table.Summary.Cell index={0} colSpan={4}>
                      <strong>TOTAL</strong>
                    </Table.Summary.Cell>
                    <Table.Summary.Cell index={4} align="center">
                      -
                    </Table.Summary.Cell>
                    <Table.Summary.Cell index={5} align="center">
                      -
                    </Table.Summary.Cell>
                    <Table.Summary.Cell index={6} align="right">
                      {formatCurrency(totals.total_bruto || 0)}
                    </Table.Summary.Cell>
                    <Table.Summary.Cell index={7} align="right">
                      <span style={{ color: '#f5222d' }}>{formatCurrency(totals.total_deducciones || 0)}</span>
                    </Table.Summary.Cell>
                    <Table.Summary.Cell index={8} align="right">
                      <span style={{ color: '#2E7D32' }}>{formatCurrency(totals.total_neto || 0)}</span>
                    </Table.Summary.Cell>
                    <Table.Summary.Cell index={9} align="right">
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

export default WorkerProductivityReport;
