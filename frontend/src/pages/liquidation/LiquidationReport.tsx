import React, { useState } from 'react';
import { Button, Card, Row, Col, DatePicker, Select, Table, Statistic, Space, message, Collapse, Tag, Divider } from 'antd';
import { FileExcelOutlined, FilePdfOutlined, SearchOutlined, DollarOutlined, TeamOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import { useQuery, useMutation } from '@tanstack/react-query';
import { liquidationReportsApi, workersApi, tasksApi } from '../../services/api';
import { formatCurrency } from '../../utils/formatters';
import dayjs from 'dayjs';

const { RangePicker } = DatePicker;
const { Option } = Select;

const LiquidationReport: React.FC = () => {
  const [dateRange, setDateRange] = useState<[dayjs.Dayjs | null, dayjs.Dayjs | null]>([null, null]);
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
    mutationFn: (data: any) => liquidationReportsApi.generate(data),
    onSuccess: (response: any) => {
      setReportData(response.data);
      message.success('Reporte generado exitosamente');
    },
    onError: (error: any) => {
      message.error(error.response?.data?.message || 'Error al generar reporte');
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
      };
      if (selectedWorkerId) params.worker_id = selectedWorkerId;
      if (selectedTaskId) params.task_id = selectedTaskId;

      await liquidationReportsApi.exportExcel(params);
      message.success('Exportación Excel completada');
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
      if (selectedTaskId) params.task_id = selectedTaskId;

      await liquidationReportsApi.exportPdf(params);
      message.success('Exportación PDF completada');
    } catch (error: any) {
      message.error('Error al exportar PDF');
    }
  };

  const assignmentColumns: ColumnsType<any> = [
    {
      title: 'Fecha',
      dataIndex: 'date',
      key: 'date',
      render: (date: string) => date ? new Date(date).toLocaleDateString('es-CO') : 'N/A',
    },
    {
      title: 'Código Tarea',
      dataIndex: 'task_code',
      key: 'task_code',
    },
    {
      title: 'Tarea',
      dataIndex: 'task_name',
      key: 'task_name',
    },
    {
      title: 'Bruto',
      dataIndex: 'gross_amount',
      key: 'gross_amount',
      render: (val: number) => formatCurrency(val),
      align: 'right' as const,
    },
    {
      title: 'Deducciones',
      dataIndex: 'total_deductions',
      key: 'total_deductions',
      render: (val: number) => <span style={{ color: '#f5222d' }}>{formatCurrency(val)}</span>,
      align: 'right' as const,
    },
    {
      title: 'Neto',
      dataIndex: 'net_amount',
      key: 'net_amount',
      render: (val: number) => <span style={{ color: '#2E7D32', fontWeight: 600 }}>{formatCurrency(val)}</span>,
      align: 'right' as const,
    },
  ];

  return (
    <div>
      <div style={{ marginBottom: 16 }}>
        <h1 style={{ margin: 0, color: '#2E7D32' }}>Reporte de Liquidación</h1>
        <p style={{ color: '#666', margin: 0 }}>Genera reportes de liquidación por rango de fechas</p>
      </div>

      {/* Filters */}
      <Card style={{ marginBottom: 16 }}>
        <Row gutter={[16, 16]} align="bottom">
          <Col xs={24} sm={12} md={8}>
            <div style={{ marginBottom: 4, fontWeight: 500 }}>Rango de Fechas *</div>
            <RangePicker
              style={{ width: '100%' }}
              format="YYYY-MM-DD"
              value={dateRange as [dayjs.Dayjs, dayjs.Dayjs]}
              onChange={(dates) => setDateRange(dates ? [dates[0], dates[1]] : [null, null])}
            />
          </Col>
          <Col xs={24} sm={12} md={6}>
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
          <Col xs={24} sm={12} md={6}>
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
                  value={reportData.totals?.total_workers || 0}
                  prefix={<TeamOutlined />}
                />
              </Col>
              <Col xs={12} sm={8} md={4}>
                <Statistic
                  title="Asignaciones"
                  value={reportData.totals?.total_assignments || 0}
                />
              </Col>
              <Col xs={12} sm={8} md={5}>
                <Statistic
                  title="Total Bruto"
                  value={reportData.totals?.gross_amount || 0}
                  precision={0}
                  prefix="$"
                />
              </Col>
              <Col xs={12} sm={8} md={5}>
                <Statistic
                  title="Total Deducciones"
                  value={reportData.totals?.total_deductions || 0}
                  precision={0}
                  prefix="$"
                  valueStyle={{ color: '#f5222d' }}
                />
              </Col>
              <Col xs={24} sm={8} md={6}>
                <Statistic
                  title="Total Neto"
                  value={reportData.totals?.net_amount || 0}
                  precision={0}
                  prefix={<DollarOutlined />}
                  valueStyle={{ color: '#2E7D32', fontWeight: 700, fontSize: '24px' }}
                />
              </Col>
            </Row>
          </Card>

          {/* Deduction Breakdown */}
          {reportData.deduction_breakdown && Object.keys(reportData.deduction_breakdown).length > 0 && (
            <Card title="Desglose de Deducciones" style={{ marginBottom: 16 }} size="small">
              <Row gutter={[16, 8]}>
                {Object.entries(reportData.deduction_breakdown).map(([name, amount]) => (
                  <Col key={name} xs={12} sm={8} md={6}>
                    <div style={{ padding: '8px', background: '#fff2f0', borderRadius: 4 }}>
                      <div style={{ fontSize: '12px', color: '#666' }}>{name}</div>
                      <div style={{ fontWeight: 600, color: '#f5222d' }}>{formatCurrency(amount as number)}</div>
                    </div>
                  </Col>
                ))}
              </Row>
            </Card>
          )}

          {/* Worker Groups */}
          <Card title="Detalle por Trabajador">
            <Collapse
              items={(reportData.workers || []).map((group: any, index: number) => ({
                key: index.toString(),
                label: (
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', width: '100%' }}>
                    <div>
                      <Tag color="blue">{group.worker.worker_code}</Tag>
                      <strong>{group.worker.full_name}</strong>
                      <span style={{ color: '#666', marginLeft: 8 }}>Doc: {group.worker.document_id}</span>
                    </div>
                    <div style={{ textAlign: 'right' }}>
                      <span style={{ marginRight: 16 }}>{group.subtotals.days_worked} días</span>
                      <span style={{ color: '#2E7D32', fontWeight: 600 }}>{formatCurrency(group.subtotals.net_amount)}</span>
                    </div>
                  </div>
                ),
                children: (
                  <Table
                    dataSource={group.assignments}
                    columns={assignmentColumns}
                    rowKey={(record, i) => `${record.date}-${record.task_code}-${i}`}
                    size="small"
                    pagination={false}
                    scroll={{ x: 'max-content' }}
                    summary={() => (
                      <Table.Summary.Row style={{ background: '#f6ffed' }}>
                        <Table.Summary.Cell index={0} colSpan={3} align="right">
                          <strong>Subtotal ({group.subtotals.days_worked} días):</strong>
                        </Table.Summary.Cell>
                        <Table.Summary.Cell index={1} align="right">
                          <strong>{formatCurrency(group.subtotals.gross_amount)}</strong>
                        </Table.Summary.Cell>
                        <Table.Summary.Cell index={2} align="right">
                          <strong style={{ color: '#f5222d' }}>{formatCurrency(group.subtotals.total_deductions)}</strong>
                        </Table.Summary.Cell>
                        <Table.Summary.Cell index={3} align="right">
                          <strong style={{ color: '#2E7D32' }}>{formatCurrency(group.subtotals.net_amount)}</strong>
                        </Table.Summary.Cell>
                      </Table.Summary.Row>
                    )}
                  />
                ),
              }))}
            />
          </Card>
        </>
      )}
    </div>
  );
};

export default LiquidationReport;
