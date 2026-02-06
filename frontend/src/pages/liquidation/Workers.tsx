import React, { useState, useRef } from 'react';
import { Button, Input, Select, Space, Card, Tag, Popconfirm, message, Modal, Form, Row, Col, DatePicker, Upload, Table, Alert, Statistic, Divider } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined, UploadOutlined, DownloadOutlined, CheckCircleOutlined, CloseCircleOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import type { UploadFile } from 'antd/es/upload/interface';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { workersApi } from '../../services/api';
import ResponsiveTable from '../../components/ResponsiveTable';
import dayjs from 'dayjs';

const { Search } = Input;
const { Option } = Select;

const Workers: React.FC = () => {
  const queryClient = useQueryClient();
  const [isModalVisible, setIsModalVisible] = useState(false);
  const [editingWorker, setEditingWorker] = useState<any | null>(null);
  const [form] = Form.useForm();
  const [searchText, setSearchText] = useState('');
  const [statusFilter, setStatusFilter] = useState<string | undefined>();

  // Ref to prevent double submissions (synchronous check)
  const isSubmittingRef = useRef(false);

  // Bulk upload state
  const [fileList, setFileList] = useState<UploadFile[]>([]);
  const [previewData, setPreviewData] = useState<any | null>(null);

  const { data: workersData, isLoading } = useQuery({
    queryKey: ['workers', searchText, statusFilter],
    queryFn: () => workersApi.list({
      search: searchText || undefined,
      status: statusFilter || undefined,
      per_page: 100,
    }),
  });

  const workers = workersData?.data || [];

  const createMutation = useMutation({
    mutationFn: (data: any) => workersApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['workers'] });
      setIsModalVisible(false);
      form.resetFields();
      message.success('Trabajador creado exitosamente');
    },
    onError: (error: any) => {
      if (error.response?.data?.errors) {
        const backendErrors = error.response.data.errors;
        const firstError = Object.values(backendErrors)[0];
        message.error(Array.isArray(firstError) ? firstError[0] : String(firstError));
        const formErrors = Object.keys(backendErrors).map(key => ({
          name: key === 'worker_code' ? 'workerCode' :
                key === 'full_name' ? 'fullName' :
                key === 'document_id' ? 'documentId' :
                key === 'hire_date' ? 'hireDate' : key,
          errors: backendErrors[key],
        }));
        form.setFields(formErrors);
      } else {
        message.error(error.response?.data?.message || 'Error al crear trabajador');
      }
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: any }) => workersApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['workers'] });
      setIsModalVisible(false);
      form.resetFields();
      setEditingWorker(null);
      message.success('Trabajador actualizado exitosamente');
    },
    onError: (error: any) => {
      if (error.response?.data?.errors) {
        const backendErrors = error.response.data.errors;
        const firstError = Object.values(backendErrors)[0];
        message.error(Array.isArray(firstError) ? firstError[0] : String(firstError));
        const formErrors = Object.keys(backendErrors).map(key => ({
          name: key === 'worker_code' ? 'workerCode' :
                key === 'full_name' ? 'fullName' :
                key === 'document_id' ? 'documentId' :
                key === 'hire_date' ? 'hireDate' : key,
          errors: backendErrors[key],
        }));
        form.setFields(formErrors);
      } else {
        message.error(error.response?.data?.message || 'Error al actualizar trabajador');
      }
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => workersApi.delete(id),
    onSuccess: (response: any) => {
      queryClient.invalidateQueries({ queryKey: ['workers'] });
      message.success(response?.message || 'Trabajador eliminado exitosamente');
    },
    onError: (error: any) => {
      message.error(error.response?.data?.message || 'Error al eliminar trabajador');
    },
  });

  // Bulk upload mutations
  const previewMutation = useMutation({
    mutationFn: (formData: FormData) => workersApi.preview(formData),
    onSuccess: (response: any) => {
      setPreviewData(response.data);
      message.success('Vista previa generada exitosamente');
    },
    onError: (error: any) => {
      message.error(error.response?.data?.message || 'Error al procesar el archivo');
    },
  });

  const importMutation = useMutation({
    mutationFn: (data: any) => workersApi.processImport(data),
    onSuccess: async (response: any) => {
      await queryClient.refetchQueries({ queryKey: ['workers'] });
      setPreviewData(null);
      setFileList([]);
      message.success(response.message || 'Importación exitosa');
    },
    onError: (error: any) => {
      message.error(error.message || 'Error al importar');
    },
  });

  const handleEdit = (record: any) => {
    setEditingWorker(record);
    form.setFieldsValue({
      workerCode: record.workerCode || record.worker_code,
      fullName: record.fullName || record.full_name,
      documentId: record.documentId || record.document_id,
      hireDate: record.hireDate || record.hire_date ? dayjs(record.hireDate || record.hire_date) : null,
      status: record.status,
    });
    setIsModalVisible(true);
  };

  const handleSave = (values: any) => {
    // Prevent multiple submissions using ref (synchronous check)
    if (isSubmittingRef.current) {
      return;
    }
    isSubmittingRef.current = true;

    const workerData = {
      worker_code: values.workerCode,
      full_name: values.fullName,
      document_id: values.documentId,
      hire_date: values.hireDate ? values.hireDate.format('YYYY-MM-DD') : undefined,
      status: values.status || 'active',
    };

    const mutationOptions = {
      onSettled: () => {
        isSubmittingRef.current = false;
      }
    };

    if (editingWorker) {
      updateMutation.mutate({ id: editingWorker.id, data: workerData }, mutationOptions);
    } else {
      createMutation.mutate(workerData, mutationOptions);
    }
  };

  const handlePreview = () => {
    if (fileList.length === 0) {
      message.warning('Seleccione un archivo');
      return;
    }
    const formData = new FormData();
    formData.append('file', fileList[0] as any);
    previewMutation.mutate(formData);
  };

  const handleConfirmImport = () => {
    if (!previewData?.valid?.length) return;
    importMutation.mutate({ workers: previewData.valid });
  };

  const handleDownloadTemplate = async () => {
    try {
      await workersApi.downloadTemplate();
      message.success('Plantilla descargada');
    } catch {
      message.error('Error al descargar plantilla');
    }
  };

  // Preview table columns
  const validPreviewColumns: ColumnsType<any> = [
    { title: 'Fila', dataIndex: 'row', key: 'row', width: 60 },
    { title: 'Código', dataIndex: 'worker_code', key: 'worker_code' },
    { title: 'Nombre Completo', dataIndex: 'full_name', key: 'full_name' },
    { title: 'Documento', dataIndex: 'document_id', key: 'document_id' },
    { title: 'Fecha Ingreso', dataIndex: 'hire_date', key: 'hire_date' },
  ];

  const invalidPreviewColumns: ColumnsType<any> = [
    { title: 'Fila', dataIndex: 'row', key: 'row', width: 60 },
    { title: 'Código', dataIndex: 'worker_code', key: 'worker_code' },
    { title: 'Nombre', dataIndex: 'full_name', key: 'full_name' },
    { title: 'Documento', dataIndex: 'document_id', key: 'document_id' },
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

  const mobileColumns: ColumnsType<any> = [
    {
      title: 'Trabajador',
      key: 'worker',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: '14px', marginBottom: 4 }}>
            {record.fullName || record.full_name}
          </div>
          <div style={{ fontSize: '12px', color: '#666' }}>
            <Tag color="blue" size="small">{record.workerCode || record.worker_code}</Tag>
            Doc: {record.documentId || record.document_id}
          </div>
        </div>
      ),
    },
    {
      title: 'Estado',
      dataIndex: 'status',
      key: 'status',
      width: 80,
      render: (status: string) => (
        <Tag color={status === 'active' ? 'green' : 'red'} size="small">
          {status === 'active' ? 'Activo' : 'Inactivo'}
        </Tag>
      ),
    },
    {
      title: 'Acciones',
      key: 'actions',
      width: 100,
      fixed: 'right' as const,
      render: (_, record) => (
        <Space size="small">
          <Button type="link" icon={<EditOutlined />} onClick={() => handleEdit(record)} size="small" />
          <Popconfirm
            title="¿Eliminar este trabajador?"
            onConfirm={() => deleteMutation.mutate(record.id)}
            okText="Sí"
            cancelText="No"
          >
            <Button type="link" danger icon={<DeleteOutlined />} size="small" />
          </Popconfirm>
        </Space>
      ),
    },
  ];

  const desktopColumns: ColumnsType<any> = [
    {
      title: 'Código',
      key: 'workerCode',
      render: (_, record) => record.workerCode || record.worker_code,
      sorter: (a, b) => (a.workerCode || a.worker_code || '').localeCompare(b.workerCode || b.worker_code || ''),
    },
    {
      title: 'Nombre Completo',
      key: 'fullName',
      render: (_, record) => record.fullName || record.full_name,
      sorter: (a, b) => (a.fullName || a.full_name || '').localeCompare(b.fullName || b.full_name || ''),
    },
    {
      title: 'Documento',
      key: 'documentId',
      render: (_, record) => record.documentId || record.document_id,
    },
    {
      title: 'Fecha Ingreso',
      key: 'hireDate',
      render: (_, record) => {
        const date = record.hireDate || record.hire_date;
        return date ? new Date(date).toLocaleDateString('es-CO') : 'N/A';
      },
      sorter: (a, b) => new Date(a.hireDate || a.hire_date || 0).getTime() - new Date(b.hireDate || b.hire_date || 0).getTime(),
    },
    {
      title: 'Estado',
      dataIndex: 'status',
      key: 'status',
      render: (status: string) => (
        <Tag color={status === 'active' ? 'green' : 'red'}>
          {status === 'active' ? 'Activo' : 'Inactivo'}
        </Tag>
      ),
      filters: [
        { text: 'Activo', value: 'active' },
        { text: 'Inactivo', value: 'inactive' },
      ],
      onFilter: (value, record) => record.status === value,
    },
    {
      title: 'Acciones',
      key: 'actions',
      fixed: 'right' as const,
      width: 150,
      render: (_, record) => (
        <Space size="middle">
          <Button type="link" icon={<EditOutlined />} onClick={() => handleEdit(record)}>
            Editar
          </Button>
          <Popconfirm
            title="¿Está seguro de eliminar este trabajador?"
            onConfirm={() => deleteMutation.mutate(record.id)}
            okText="Sí"
            cancelText="No"
          >
            <Button type="link" danger icon={<DeleteOutlined />}>
              Eliminar
            </Button>
          </Popconfirm>
        </Space>
      ),
    },
  ];

  return (
    <div>
      <div style={{ marginBottom: 16, display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ margin: 0, color: '#2E7D32' }}>Gestión de Trabajadores</h1>
          <p style={{ color: '#666', margin: 0 }}>Administra los trabajadores del campo</p>
        </div>
        <Button
          type="primary"
          icon={<PlusOutlined />}
          onClick={() => {
            setEditingWorker(null);
            form.resetFields();
            setIsModalVisible(true);
          }}
        >
          Nuevo Trabajador
        </Button>
      </div>

      {/* Bulk Upload Section */}
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
        <Row gutter={[16, 16]} align="middle">
          <Col xs={24} sm={12} md={10}>
            <div style={{ marginBottom: 8 }}>Archivo Excel (codigo_empleado, nombre_completo, documento_identidad, fecha_ingreso):</div>
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
          <Col xs={24} sm={12} md={6} style={{ display: 'flex', alignItems: 'flex-end' }}>
            <Button
              type="primary"
              onClick={handlePreview}
              loading={previewMutation.isPending}
              disabled={fileList.length === 0}
              block
            >
              Vista Previa
            </Button>
          </Col>
        </Row>
      </Card>

      {/* Preview Section */}
      {previewData && (
        <Card title="Vista Previa de Importación" style={{ marginBottom: 16 }}>
          <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
            <Col xs={8} sm={6}>
              <Statistic title="Total Filas" value={previewData.total_rows} />
            </Col>
            <Col xs={8} sm={6}>
              <Statistic
                title="Válidos"
                value={previewData.valid_count}
                valueStyle={{ color: '#3f8600' }}
                prefix={<CheckCircleOutlined />}
              />
            </Col>
            <Col xs={8} sm={6}>
              <Statistic
                title="Inválidos"
                value={previewData.invalid_count}
                valueStyle={{ color: '#cf1322' }}
                prefix={<CloseCircleOutlined />}
              />
            </Col>
          </Row>

          {previewData.invalid?.length > 0 && (
            <>
              <Alert
                type="warning"
                message={`${previewData.invalid_count} registros con errores (no se importarán)`}
                style={{ marginBottom: 12 }}
              />
              <Table
                dataSource={previewData.invalid}
                columns={invalidPreviewColumns}
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
                columns={validPreviewColumns}
                rowKey="row"
                size="small"
                pagination={false}
                scroll={{ x: 'max-content' }}
              />

              <div style={{ textAlign: 'right', marginTop: 16 }}>
                <Space>
                  <Button onClick={() => { setPreviewData(null); setFileList([]); }}>Cancelar</Button>
                  <Button
                    type="primary"
                    onClick={handleConfirmImport}
                    loading={importMutation.isPending}
                    icon={<CheckCircleOutlined />}
                  >
                    Confirmar Importación ({previewData.valid_count} trabajadores)
                  </Button>
                </Space>
              </div>
            </>
          )}
        </Card>
      )}

      {/* Workers Table */}
      <Card>
        <div style={{ marginBottom: 16 }}>
          <Row gutter={[16, 16]}>
            <Col xs={24} sm={12} md={8}>
              <Search
                placeholder="Buscar por nombre, código o documento..."
                allowClear
                value={searchText}
                onChange={(e) => setSearchText(e.target.value)}
              />
            </Col>
            <Col xs={24} sm={12} md={8}>
              <Select
                placeholder="Filtrar por estado"
                allowClear
                style={{ width: '100%' }}
                value={statusFilter}
                onChange={setStatusFilter}
              >
                <Option value="active">Activo</Option>
                <Option value="inactive">Inactivo</Option>
              </Select>
            </Col>
          </Row>
        </div>

        <ResponsiveTable
          mobileColumns={mobileColumns}
          desktopColumns={desktopColumns}
          dataSource={workers}
          rowKey="id"
          loading={isLoading || createMutation.isPending || updateMutation.isPending || deleteMutation.isPending}
          entityName="trabajadores"
          pagination={{ total: workers.length, pageSize: 10 }}
        />
      </Card>

      <Modal
        title={editingWorker ? 'Editar Trabajador' : 'Nuevo Trabajador'}
        open={isModalVisible}
        onCancel={() => {
          setIsModalVisible(false);
          form.resetFields();
          setEditingWorker(null);
        }}
        footer={null}
        width={500}
      >
        <Form form={form} layout="vertical" onFinish={handleSave}>
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item
                name="workerCode"
                label="Código"
                rules={[{ required: true, message: 'El código es requerido' }]}
              >
                <Input placeholder="Ej: TRB-001" />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item
                name="documentId"
                label="Documento de Identidad"
                rules={[{ required: true, message: 'El documento es requerido' }]}
              >
                <Input placeholder="Ej: 1234567890" />
              </Form.Item>
            </Col>
          </Row>

          <Form.Item
            name="fullName"
            label="Nombre Completo"
            rules={[{ required: true, message: 'El nombre es requerido' }]}
          >
            <Input placeholder="Nombre completo del trabajador" />
          </Form.Item>

          <Row gutter={16}>
            <Col span={12}>
              <Form.Item
                name="hireDate"
                label="Fecha de Ingreso"
                rules={[{ required: true, message: 'La fecha es requerida' }]}
              >
                <DatePicker style={{ width: '100%' }} format="YYYY-MM-DD" />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item
                name="status"
                label="Estado"
                initialValue="active"
              >
                <Select>
                  <Option value="active">Activo</Option>
                  <Option value="inactive">Inactivo</Option>
                </Select>
              </Form.Item>
            </Col>
          </Row>

          <div style={{ textAlign: 'right' }}>
            <Space>
              <Button onClick={() => { setIsModalVisible(false); form.resetFields(); setEditingWorker(null); }}>
                Cancelar
              </Button>
              <Button
                type="primary"
                htmlType="submit"
                loading={createMutation.isPending || updateMutation.isPending}
                disabled={createMutation.isPending || updateMutation.isPending}
              >
                {editingWorker ? 'Actualizar' : 'Crear'}
              </Button>
            </Space>
          </div>
        </Form>
      </Modal>
    </div>
  );
};

export default Workers;
