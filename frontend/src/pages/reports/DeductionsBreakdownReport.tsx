import React, { useState } from 'react';
import { Button, Card, Row, Col, DatePicker, Select, Table, Statistic, Space, message, Input } from 'antd';
import { FileExcelOutlined, FilePdfOutlined, SearchOutlined, MinusCircleOutlined, DollarOutlined, TeamOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import { useQuery, useMutation } from '@tanstack/react-query';
import { liquidationAnalyticsApi, workersApi } from '../../services/api';
import { formatCurrency } from '../../utils/formatters';
import dayjs from 'dayjs';
import {
  PieChart,
  Pie,
  Cell,
  Tooltip,
  Legend,
  ResponsiveContainer,
} from 'recharts';

const { RangePicker } = DatePicker;
const { Option } = Select;

const COLORS = ['#2E7D32', '#1565C0', '#E65100', '#7B1FA2', '#C62828', '#00838F', '#F9A825', '#4E342E'];

const DeductionsBreakdownReport: React.FC = () => {
  const [dateRange, setDateRange] = useState<[dayjs.Dayjs | null, dayjs.Dayjs | null]>([null, null]);
  const [selectedWorkerId, setSelectedWorkerId] = useState<string | undefined>();
  const [deductionName, setDeductionName] = useState<string>('');
  const [reportData, setReportData] = useState<any | null>(null);

  // Fetch workers for dropdown
  const { data: workersData } = useQuery({
    queryKey: ['workers-simple'],
    queryFn: () => workersApi.listSimple({ status: 'active' }),
  });

  const workers = workersData?.data || [];

  const generateMutation = useMutation({
    mutationFn: (data: any) => liquidationAnalyticsApi.deductionsBreakdown(data),
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
      worker_id: selectedWorkerId || null,
      deduction_name: deductionName || null,
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
      };
      if (selectedWorkerId) params.worker_id = selectedWorkerId;
      if (deductionName) params.deduction_name = deductionName;

      await liquidationAnalyticsApi.exportExcel('deductions-breakdown', params);
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
      };
      if (selectedWorkerId) params.worker_id = selectedWorkerId;
      if (deductionName) params.deduction_name = deductionName;

      await liquidationAnalyticsApi.exportPdf('deductions-breakdown', params);
      message.success('Exportacion PDF completada');
    } catch (error: any) {
      message.error('Error al exportar PDF');
    }
  };

  const totals = reportData?.totals || {};
  const deductionTypes = reportData?.deduction_types || [];
  const workerBreakdown = reportData?.worker_breakdown || [];

  // Pie chart data
  const pieChartData = deductionTypes.map((d: any) => ({
    name: d.name || 'N/A',
    value: d.total_amount || 0,
  }));

  // Build dynamic columns for worker breakdown table
  const buildWorkerColumns = (): ColumnsType<any> => {
    const baseColumns: ColumnsType<any> = [
      {
        title: 'Codigo',
        dataIndex: 'worker_code',
        key: 'worker_code',
        width: 100,
        fixed: 'left' as const,
      },
      {
        title: 'Trabajador',
        dataIndex: 'full_name',
        key: 'full_name',
        width: 180,
        fixed: 'left' as const,
      },
      {
        title: 'Documento',
        dataIndex: 'document_id',
        key: 'document_id',
        width: 120,
      },
      {
        title: 'Total Bruto',
        dataIndex: 'total_bruto',
        key: 'total_bruto',
        render: (val: number) => formatCurrency(val),
        align: 'right' as const,
        width: 130,
      },
    ];

    // Dynamic columns for each deduction type
    const deductionColumns: ColumnsType<any> = deductionTypes.map((d: any) => ({
      title: d.name || 'N/A',
      dataIndex: ['deductions', d.name],
      key: `deduction-${d.name}`,
      width: 120,
      render: (val: number) => val ? <span style={{ color: '#f5222d' }}>{formatCurrency(val)}</span> : '-',
      align: 'right' as const,
    }));

    const endColumns: ColumnsType<any> = [
      {
        title: 'Total Deducciones',
        dataIndex: 'total_deducciones',
        key: 'total_deducciones',
        render: (val: number) => <span style={{ color: '#f5222d', fontWeight: 600 }}>{formatCurrency(val)}</span>,
        align: 'right' as const,
        width: 140,
      },
      {
        title: 'Total Neto',
        dataIndex: 'total_neto',
        key: 'total_neto',
        render: (val: number) => <span style={{ color: '#2E7D32', fontWeight: 600 }}>{formatCurrency(val)}</span>,
        align: 'right' as const,
        width: 130,
      },
    ];

    return [...baseColumns, ...deductionColumns, ...endColumns];
  };

  return (
    <div>
      <div style={{ marginBottom: 16 }}>
        <h1 style={{ margin: 0, color: '#2E7D32' }}>
          <MinusCircleOutlined /> Desglose de Deducciones
        </h1>
        <p style={{ color: '#666', margin: 0 }}>Consolidado de deducciones por tipo y trabajador</p>
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
            <div style={{ marginBottom: 4, fontWeight: 500 }}>Deduccion (opcional)</div>
            <Input
              placeholder="Nombre de deduccion"
              allowClear
              style={{ width: '100%' }}
              value={deductionName}
              onChange={(e) => setDeductionName(e.target.value)}
            />
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
                  valueStyle={{ color: '#f5222d', fontWeight: 700 }}
                />
              </Col>
              <Col xs={12} sm={8} md={5}>
                <Statistic
                  title="Total Neto"
                  value={totals.total_neto || 0}
                  precision={0}
                  prefix={<DollarOutlined />}
                  valueStyle={{ color: '#2E7D32', fontWeight: 700 }}
                />
              </Col>
              <Col xs={12} sm={8} md={5}>
                <Statistic
                  title="Asignaciones"
                  value={totals.total_assignments || 0}
                />
              </Col>
              <Col xs={12} sm={8} md={4}>
                <Statistic
                  title="Trabajadores"
                  value={totals.total_workers || 0}
                  prefix={<TeamOutlined />}
                />
              </Col>
            </Row>
          </Card>

          {/* Deduction Type Cards */}
          {deductionTypes.length > 0 && (
            <Card title="Tipos de Deduccion" style={{ marginBottom: 16 }} size="small">
              <Row gutter={[16, 16]}>
                {deductionTypes.map((d: any, index: number) => (
                  <Col key={d.name || index} xs={12} sm={8} md={6}>
                    <div
                      style={{
                        padding: '16px',
                        background: '#fff2f0',
                        borderRadius: 8,
                        borderLeft: `4px solid ${COLORS[index % COLORS.length]}`,
                      }}
                    >
                      <div style={{ fontSize: '13px', color: '#666', marginBottom: 4 }}>{d.name || 'N/A'}</div>
                      <div style={{ fontWeight: 700, fontSize: '18px', color: '#f5222d', marginBottom: 4 }}>
                        {formatCurrency(d.total_amount || 0)}
                      </div>
                      <div style={{ fontSize: '12px', color: '#999' }}>
                        {d.count || 0} aplicaciones
                      </div>
                      <div style={{ fontSize: '12px', color: '#999' }}>
                        Promedio: {(d.avg_percentage || 0).toFixed(1)}%
                      </div>
                    </div>
                  </Col>
                ))}
              </Row>
            </Card>
          )}

          {/* Pie Chart */}
          {pieChartData.length > 0 && (
            <Card title="Distribucion por Tipo de Deduccion" style={{ marginBottom: 16 }}>
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
          )}

          {/* Worker Breakdown Table */}
          <Card title={`Desglose por Trabajador (${workerBreakdown.length})`}>
            <Table
              dataSource={workerBreakdown}
              columns={buildWorkerColumns()}
              rowKey={(record, index) => record.worker_id || record.id || `worker-${index}`}
              size="small"
              scroll={{ x: 'max-content' }}
              pagination={{ pageSize: 50, showSizeChanger: true, showTotal: (total) => `Total: ${total} trabajadores` }}
              summary={() => {
                if (workerBreakdown.length === 0) return null;
                return (
                  <Table.Summary.Row style={{ background: '#f6ffed', fontWeight: 600 }}>
                    <Table.Summary.Cell index={0} colSpan={3}>
                      <strong>TOTAL</strong>
                    </Table.Summary.Cell>
                    <Table.Summary.Cell index={3} align="right">
                      {formatCurrency(totals.total_bruto || 0)}
                    </Table.Summary.Cell>
                    {deductionTypes.map((d: any, i: number) => (
                      <Table.Summary.Cell key={`sum-${i}`} index={4 + i} align="right">
                        <span style={{ color: '#f5222d' }}>{formatCurrency(d.total_amount || 0)}</span>
                      </Table.Summary.Cell>
                    ))}
                    <Table.Summary.Cell index={4 + deductionTypes.length} align="right">
                      <span style={{ color: '#f5222d' }}>{formatCurrency(totals.total_deducciones || 0)}</span>
                    </Table.Summary.Cell>
                    <Table.Summary.Cell index={5 + deductionTypes.length} align="right">
                      <span style={{ color: '#2E7D32' }}>{formatCurrency(totals.total_neto || 0)}</span>
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

export default DeductionsBreakdownReport;
