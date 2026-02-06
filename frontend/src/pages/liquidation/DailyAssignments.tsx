import React, { useState } from 'react';
import { Button, Input, Card, Tag, message, Row, Col, DatePicker, Upload, Table, Alert, Space, Statistic, Divider, Select } from 'antd';
import { UploadOutlined, CheckCircleOutlined, CloseCircleOutlined, InboxOutlined, DownloadOutlined, PlusCircleOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { dailyAssignmentsApi, workersApi, tasksApi } from '../../services/api';
import { formatCurrency } from '../../utils/formatters';
import ResponsiveTable from '../../components/ResponsiveTable';
import type { UploadFile } from 'antd/es/upload/interface';
import dayjs from 'dayjs';

const { Dragger } = Upload;
const { Search } = Input;

const DailyAssignments: React.FC = () => {
  const queryClient = useQueryClient();
  const [selectedDate, setSelectedDate] = useState<dayjs.Dayjs | null>(null);
  const [fileList, setFileList] = useState<UploadFile[]>([]);
  const [previewData, setPreviewData] = useState<any | null>(null);
  const [searchText, setSearchText] = useState('');
  const [filterDate, setFilterDate] = useState<string | undefined>();

  // Manual assignment state
  const [manualDate, setManualDate] = useState<dayjs.Dayjs | null>(dayjs());
  const [manualWorkerId, setManualWorkerId] = useState<string | undefined>();
  const [manualTaskId, setManualTaskId] = useState<string | undefined>();

  // Workers and tasks for manual form
  const { data: workersData } = useQuery({
    queryKey: ['workers-simple-active'],
    queryFn: () => workersApi.listSimple({ status: 'active' }),
  });

  const { data: tasksData } = useQuery({
    queryKey: ['tasks-simple-active'],
    queryFn: () => tasksApi.listSimple({ status: 'active' }),
  });

  // Net amount preview when task is selected
  const { data: netAmountData, isFetching: isLoadingNetAmount } = useQuery({
    queryKey: ['task-net-amount', manualTaskId],
    queryFn: () => tasksApi.getNetAmount(manualTaskId!),
    enabled: !!manualTaskId,
  });

  const workers = workersData?.data || [];
  const tasks = tasksData?.data || [];

  // Fetch existing assignments
  const { data: assignmentsData, isLoading } = useQuery({
    queryKey: ['daily-assignments', searchText, filterDate],
    queryFn: () => dailyAssignmentsApi.list({
      search: searchText || undefined,
      date: filterDate || undefined,
      per_page: 50,
    }),
  });

  const assignments = assignmentsData?.data || [];

  // Preview mutation
  const previewMutation = useMutation({
    mutationFn: (formData: FormData) => dailyAssignmentsApi.preview(formData),
    onSuccess: (response: any) => {
      setPreviewData(response.data);
      message.success('Vista previa generada exitosamente');
    },
    onError: (error: any) => {
      message.error(error.response?.data?.message || 'Error al procesar el archivo');
    },
  });

  // Process mutation
  const processMutation = useMutation({
    mutationFn: (data: any) => dailyAssignmentsApi.process(data),
    onSuccess: async (response: any) => {
      await queryClient.refetchQueries({ queryKey: ['daily-assignments'] });
      setPreviewData(null);
      setFileList([]);
      setSelectedDate(null);
      message.success(response.message || 'Asignaciones procesadas exitosamente');
    },
    onError: (error: any) => {
      message.error(error.message || 'Error al procesar asignaciones');
    },
  });

  // Manual assignment mutation
  const createMutation = useMutation({
    mutationFn: (data: { date: string; worker_id: string; task_id: string }) =>
      dailyAssignmentsApi.create(data),
    onSuccess: (response: any) => {
      queryClient.invalidateQueries({ queryKey: ['daily-assignments'] });
      setManualWorkerId(undefined);
      setManualTaskId(undefined);
      message.success(response.message || 'Asignación creada exitosamente');
    },
    onError: (error: any) => {
      message.error(error.response?.data?.message || 'Error al crear asignación');
    },
  });

  const handleManualCreate = () => {
    if (!manualDate || !manualWorkerId || !manualTaskId) {
      message.warning('Complete todos los campos: fecha, trabajador y tarea');
      return;
    }
    createMutation.mutate({
      date: manualDate.format('YYYY-MM-DD'),
      worker_id: manualWorkerId,
      task_id: manualTaskId,
    });
  };

  const handleDownloadTemplate = async () => {
    try {
      await dailyAssignmentsApi.downloadTemplate();
      message.success('Plantilla descargada');
    } catch {
      message.error('Error al descargar la plantilla');
    }
  };

  const handlePreview = () => {
    if (!fileList.length || !selectedDate) {
      message.warning('Seleccione un archivo y una fecha');
      return;
    }

    const formData = new FormData();
    formData.append('file', fileList[0] as any);
    formData.append('date', selectedDate.format('YYYY-MM-DD'));

    previewMutation.mutate(formData);
  };

  const handleProcess = () => {
    if (!previewData || !previewData.valid?.length) {
      message.warning('No hay registros válidos para procesar');
      return;
    }

    const data = {
      date: previewData.date,
      assignments: previewData.valid.map((row: any) => ({
        worker_code: row.worker_code,
        task_code: row.task_code,
      })),
    };

    processMutation.mutate(data);
  };

  const validColumns: ColumnsType<any> = [
    { title: 'Fila', dataIndex: 'row', key: 'row', width: 60 },
    { title: 'Código Empleado', dataIndex: 'worker_code', key: 'worker_code' },
    { title: 'Nombre', dataIndex: 'worker_name', key: 'worker_name' },
    { title: 'Código Tarea', dataIndex: 'task_code', key: 'task_code' },
    { title: 'Tarea', dataIndex: 'task_name', key: 'task_name' },
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

  const invalidColumns: ColumnsType<any> = [
    { title: 'Fila', dataIndex: 'row', key: 'row', width: 60 },
    { title: 'Código Empleado', dataIndex: 'worker_code', key: 'worker_code' },
    { title: 'Código Tarea', dataIndex: 'task_code', key: 'task_code' },
    {
      title: 'Errores',
      dataIndex: 'errors',
      key: 'errors',
      render: (errors: string[]) => (
        <div>
          {errors.map((err, i) => (
            <div key={i} style={{ color: '#f5222d', fontSize: '12px' }}>{err}</div>
          ))}
        </div>
      ),
    },
  ];

  // History table columns
  const mobileHistoryColumns: ColumnsType<any> = [
    {
      title: 'Asignación',
      key: 'assignment',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: '14px', marginBottom: 4 }}>
            {record.worker?.fullName || record.worker?.full_name || record.workerCode || record.worker_code}
          </div>
          <div style={{ fontSize: '12px', color: '#666' }}>
            {record.date} | {record.task?.name || record.taskCode || record.task_code}
          </div>
          <div style={{ fontSize: '12px', color: '#2E7D32', fontWeight: 600 }}>
            Neto: {formatCurrency(record.netAmount || record.net_amount)}
          </div>
        </div>
      ),
    },
  ];

  const desktopHistoryColumns: ColumnsType<any> = [
    {
      title: 'Fecha',
      dataIndex: 'date',
      key: 'date',
      render: (date: string) => date ? new Date(date).toLocaleDateString('es-CO') : 'N/A',
    },
    {
      title: 'Trabajador',
      key: 'worker',
      render: (_, record) => record.worker?.fullName || record.worker?.full_name || record.workerCode || record.worker_code,
    },
    {
      title: 'Tarea',
      key: 'task',
      render: (_, record) => record.task?.name || record.taskCode || record.task_code,
    },
    {
      title: 'Bruto',
      key: 'grossAmount',
      render: (_, record) => formatCurrency(record.grossAmount || record.gross_amount),
      align: 'right' as const,
    },
    {
      title: 'Deducciones',
      key: 'totalDeductions',
      render: (_, record) => <span style={{ color: '#f5222d' }}>{formatCurrency(record.totalDeductions || record.total_deductions)}</span>,
      align: 'right' as const,
    },
    {
      title: 'Neto',
      key: 'netAmount',
      render: (_, record) => <span style={{ color: '#2E7D32', fontWeight: 600 }}>{formatCurrency(record.netAmount || record.net_amount)}</span>,
      align: 'right' as const,
    },
  ];

  return (
    <div>
      <div style={{ marginBottom: 16 }}>
        <h1 style={{ margin: 0, color: '#2E7D32' }}>Asignaciones Diarias</h1>
        <p style={{ color: '#666', margin: 0 }}>Asignación manual o carga masiva desde archivo Excel</p>
      </div>

      {/* Manual Assignment Section */}
      <Card title="Asignación Manual" style={{ marginBottom: 16 }}>
        <Row gutter={[16, 16]} align="bottom">
          <Col xs={24} sm={12} md={6}>
            <div style={{ marginBottom: 8 }}>Fecha:</div>
            <DatePicker
              style={{ width: '100%' }}
              format="YYYY-MM-DD"
              value={manualDate}
              onChange={setManualDate}
              placeholder="Seleccione fecha"
            />
          </Col>
          <Col xs={24} sm={12} md={7}>
            <div style={{ marginBottom: 8 }}>Trabajador:</div>
            <Select
              style={{ width: '100%' }}
              placeholder="Buscar trabajador..."
              showSearch
              allowClear
              value={manualWorkerId}
              onChange={setManualWorkerId}
              optionFilterProp="label"
              options={workers.map((w: any) => ({
                value: w.id,
                label: `${w.worker_code || w.workerCode} - ${w.full_name || w.fullName}`,
              }))}
            />
          </Col>
          <Col xs={24} sm={12} md={7}>
            <div style={{ marginBottom: 8 }}>Tarea:</div>
            <Select
              style={{ width: '100%' }}
              placeholder="Buscar tarea..."
              showSearch
              allowClear
              value={manualTaskId}
              onChange={setManualTaskId}
              optionFilterProp="label"
              options={tasks.map((t: any) => ({
                value: t.id,
                label: `${t.code} - ${t.name}`,
              }))}
            />
          </Col>
          <Col xs={24} sm={12} md={4} style={{ display: 'flex', alignItems: 'flex-end' }}>
            <Button
              type="primary"
              icon={<PlusCircleOutlined />}
              onClick={handleManualCreate}
              loading={createMutation.isPending}
              disabled={!manualDate || !manualWorkerId || !manualTaskId}
              block
            >
              Asignar
            </Button>
          </Col>
        </Row>
        {manualTaskId && netAmountData?.data && (
          <Row gutter={[16, 16]} style={{ marginTop: 12 }}>
            <Col xs={8} sm={6}>
              <Statistic
                title="Bruto"
                value={netAmountData.data.gross_amount || netAmountData.data.grossAmount || 0}
                precision={0}
                prefix="$"
                loading={isLoadingNetAmount}
              />
            </Col>
            <Col xs={8} sm={6}>
              <Statistic
                title="Deducciones"
                value={netAmountData.data.total_deductions || netAmountData.data.totalDeductions || 0}
                precision={0}
                prefix="$"
                valueStyle={{ color: '#f5222d' }}
                loading={isLoadingNetAmount}
              />
            </Col>
            <Col xs={8} sm={6}>
              <Statistic
                title="Neto"
                value={netAmountData.data.net_amount || netAmountData.data.netAmount || 0}
                precision={0}
                prefix="$"
                valueStyle={{ color: '#2E7D32', fontWeight: 600 }}
                loading={isLoadingNetAmount}
              />
            </Col>
          </Row>
        )}
      </Card>

      {/* Upload Section */}
      <Card
        title="Carga Masiva desde Excel"
        style={{ marginBottom: 16 }}
        extra={
          <Button
            icon={<DownloadOutlined />}
            onClick={handleDownloadTemplate}
            size="small"
          >
            Descargar Plantilla
          </Button>
        }
      >
        <Row gutter={[16, 16]}>
          <Col xs={24} sm={12} md={8}>
            <div style={{ marginBottom: 8 }}>Fecha de Asignación:</div>
            <DatePicker
              style={{ width: '100%' }}
              format="YYYY-MM-DD"
              value={selectedDate}
              onChange={setSelectedDate}
              placeholder="Seleccione la fecha"
            />
          </Col>
          <Col xs={24} sm={12} md={10}>
            <div style={{ marginBottom: 8 }}>Archivo Excel (codigo_empleado, codigo_tarea):</div>
            <Upload
              beforeUpload={(file) => {
                setFileList([file]);
                return false;
              }}
              fileList={fileList}
              onRemove={() => { setFileList([]); setPreviewData(null); }}
              accept=".xlsx,.xls,.csv"
              maxCount={1}
            >
              <Button icon={<UploadOutlined />}>Seleccionar archivo</Button>
            </Upload>
          </Col>
          <Col xs={24} sm={24} md={6} style={{ display: 'flex', alignItems: 'flex-end' }}>
            <Button
              type="primary"
              onClick={handlePreview}
              loading={previewMutation.isPending}
              disabled={!fileList.length || !selectedDate}
              block
            >
              Vista Previa
            </Button>
          </Col>
        </Row>
      </Card>

      {/* Preview Section */}
      {previewData && (
        <Card title="Vista Previa" style={{ marginBottom: 16 }}>
          <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
            <Col xs={12} sm={6}>
              <Statistic title="Total Filas" value={previewData.total_rows} />
            </Col>
            <Col xs={12} sm={6}>
              <Statistic
                title="Válidos"
                value={previewData.valid_count}
                valueStyle={{ color: '#3f8600' }}
                prefix={<CheckCircleOutlined />}
              />
            </Col>
            <Col xs={12} sm={6}>
              <Statistic
                title="Inválidos"
                value={previewData.invalid_count}
                valueStyle={{ color: '#cf1322' }}
                prefix={<CloseCircleOutlined />}
              />
            </Col>
            <Col xs={12} sm={6}>
              <Statistic
                title="Total Neto"
                value={previewData.totals?.net_amount || 0}
                precision={0}
                prefix="$"
                valueStyle={{ color: '#2E7D32' }}
              />
            </Col>
          </Row>

          {previewData.invalid?.length > 0 && (
            <>
              <Alert
                type="warning"
                message={`${previewData.invalid_count} registros con errores (no se procesarán)`}
                style={{ marginBottom: 12 }}
              />
              <Table
                dataSource={previewData.invalid}
                columns={invalidColumns}
                rowKey="row"
                size="small"
                pagination={false}
                scroll={{ x: 'max-content' }}
                style={{ marginBottom: 16 }}
              />
            </>
          )}

          {previewData.valid?.length > 0 && (
            <>
              <Divider orientation="left">Registros Válidos ({previewData.valid_count})</Divider>
              <Table
                dataSource={previewData.valid}
                columns={validColumns}
                rowKey="row"
                size="small"
                pagination={false}
                scroll={{ x: 'max-content' }}
                summary={() => (
                  <Table.Summary.Row>
                    <Table.Summary.Cell index={0} colSpan={5} align="right">
                      <strong>TOTALES:</strong>
                    </Table.Summary.Cell>
                    <Table.Summary.Cell index={1} align="right">
                      <strong>{formatCurrency(previewData.totals?.gross_amount)}</strong>
                    </Table.Summary.Cell>
                    <Table.Summary.Cell index={2} align="right">
                      <strong style={{ color: '#f5222d' }}>{formatCurrency(previewData.totals?.total_deductions)}</strong>
                    </Table.Summary.Cell>
                    <Table.Summary.Cell index={3} align="right">
                      <strong style={{ color: '#2E7D32' }}>{formatCurrency(previewData.totals?.net_amount)}</strong>
                    </Table.Summary.Cell>
                  </Table.Summary.Row>
                )}
              />

              <div style={{ textAlign: 'right', marginTop: 16 }}>
                <Space>
                  <Button onClick={() => setPreviewData(null)}>Cancelar</Button>
                  <Button
                    type="primary"
                    onClick={handleProcess}
                    loading={processMutation.isPending}
                    icon={<CheckCircleOutlined />}
                  >
                    Confirmar Procesamiento ({previewData.valid_count} asignaciones)
                  </Button>
                </Space>
              </div>
            </>
          )}
        </Card>
      )}

      {/* History Section */}
      <Card title="Historial de Asignaciones">
        <div style={{ marginBottom: 16 }}>
          <Row gutter={[16, 16]}>
            <Col xs={24} sm={12} md={8}>
              <Search
                placeholder="Buscar por trabajador o tarea..."
                allowClear
                value={searchText}
                onChange={(e) => setSearchText(e.target.value)}
              />
            </Col>
            <Col xs={24} sm={12} md={8}>
              <DatePicker
                style={{ width: '100%' }}
                format="YYYY-MM-DD"
                onChange={(date) => setFilterDate(date ? date.format('YYYY-MM-DD') : undefined)}
                placeholder="Filtrar por fecha"
                allowClear
              />
            </Col>
          </Row>
        </div>

        <ResponsiveTable
          mobileColumns={mobileHistoryColumns}
          desktopColumns={desktopHistoryColumns}
          dataSource={assignments}
          rowKey="id"
          loading={isLoading}
          entityName="asignaciones"
          pagination={{ total: assignments.length, pageSize: 15 }}
        />
      </Card>
    </div>
  );
};

export default DailyAssignments;
