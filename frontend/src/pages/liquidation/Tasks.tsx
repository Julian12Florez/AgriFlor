import React, { useState, useRef } from 'react';
import { Button, Input, Select, Space, Card, Tag, Popconfirm, message, Modal, Form, Row, Col, InputNumber, Table, Switch, Divider } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined, DollarOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { tasksApi } from '../../services/api';
import { formatCurrency } from '../../utils/formatters';
import ResponsiveTable from '../../components/ResponsiveTable';

const { Search } = Input;
const { Option } = Select;

const Tasks: React.FC = () => {
  const queryClient = useQueryClient();
  const [isModalVisible, setIsModalVisible] = useState(false);
  const [isDeductionsModalVisible, setIsDeductionsModalVisible] = useState(false);
  const [editingTask, setEditingTask] = useState<any | null>(null);
  const [selectedTask, setSelectedTask] = useState<any | null>(null);
  const [form] = Form.useForm();
  const [deductionForm] = Form.useForm();
  const [searchText, setSearchText] = useState('');
  const [statusFilter, setStatusFilter] = useState<string | undefined>();
  const [editingDeduction, setEditingDeduction] = useState<any | null>(null);

  // Ref to prevent double submissions (synchronous check)
  const isSubmittingRef = useRef(false);
  const isDeductionSubmittingRef = useRef(false);

  const { data: tasksData, isLoading } = useQuery({
    queryKey: ['tasks', searchText, statusFilter],
    queryFn: () => tasksApi.list({
      search: searchText || undefined,
      status: statusFilter || undefined,
      per_page: 100,
    }),
  });

  const tasks = tasksData?.data || [];

  const createMutation = useMutation({
    mutationFn: (data: any) => tasksApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
      setIsModalVisible(false);
      form.resetFields();
      message.success('Tarea creada exitosamente');
    },
    onError: (error: any) => {
      if (error.response?.data?.errors) {
        const backendErrors = error.response.data.errors;
        const firstError = Object.values(backendErrors)[0];
        message.error(Array.isArray(firstError) ? firstError[0] : String(firstError));
        const formErrors = Object.keys(backendErrors).map(key => ({
          name: key === 'duration_hours' ? 'durationHours' :
                key === 'daily_cost' ? 'dailyCost' : key,
          errors: backendErrors[key],
        }));
        form.setFields(formErrors);
      } else {
        message.error(error.response?.data?.message || 'Error al crear tarea');
      }
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: any }) => tasksApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
      setIsModalVisible(false);
      form.resetFields();
      setEditingTask(null);
      message.success('Tarea actualizada exitosamente');
    },
    onError: (error: any) => {
      if (error.response?.data?.errors) {
        const backendErrors = error.response.data.errors;
        const firstError = Object.values(backendErrors)[0];
        message.error(Array.isArray(firstError) ? firstError[0] : String(firstError));
      } else {
        message.error(error.response?.data?.message || 'Error al actualizar tarea');
      }
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => tasksApi.delete(id),
    onSuccess: (response: any) => {
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
      message.success(response?.message || 'Tarea eliminada exitosamente');
    },
    onError: (error: any) => {
      message.error(error.response?.data?.message || 'Error al eliminar tarea');
    },
  });

  const addDeductionMutation = useMutation({
    mutationFn: ({ taskId, data }: { taskId: string; data: any }) => tasksApi.addDeduction(taskId, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
      deductionForm.resetFields();
      setEditingDeduction(null);
      message.success('Deducción agregada exitosamente');
      // Refresh selected task data
      if (selectedTask) {
        tasksApi.get(selectedTask.id).then((res: any) => setSelectedTask(res.data));
      }
    },
    onError: (error: any) => {
      message.error(error.response?.data?.message || 'Error al agregar deducción');
    },
  });

  const updateDeductionMutation = useMutation({
    mutationFn: ({ taskId, deductionId, data }: { taskId: string; deductionId: string; data: any }) =>
      tasksApi.updateDeduction(taskId, deductionId, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
      deductionForm.resetFields();
      setEditingDeduction(null);
      message.success('Deducción actualizada exitosamente');
      if (selectedTask) {
        tasksApi.get(selectedTask.id).then((res: any) => setSelectedTask(res.data));
      }
    },
    onError: (error: any) => {
      message.error(error.response?.data?.message || 'Error al actualizar deducción');
    },
  });

  const deleteDeductionMutation = useMutation({
    mutationFn: ({ taskId, deductionId }: { taskId: string; deductionId: string }) =>
      tasksApi.deleteDeduction(taskId, deductionId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
      message.success('Deducción eliminada exitosamente');
      if (selectedTask) {
        tasksApi.get(selectedTask.id).then((res: any) => setSelectedTask(res.data));
      }
    },
    onError: (error: any) => {
      message.error(error.response?.data?.message || 'Error al eliminar deducción');
    },
  });

  const handleEdit = (record: any) => {
    setEditingTask(record);
    form.setFieldsValue({
      code: record.code,
      name: record.name,
      durationHours: record.durationHours || record.duration_hours,
      dailyCost: record.dailyCost || record.daily_cost,
      description: record.description,
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

    const taskData = {
      code: values.code,
      name: values.name,
      duration_hours: values.durationHours,
      daily_cost: values.dailyCost,
      description: values.description || null,
      status: values.status || 'active',
    };

    const mutationOptions = {
      onSettled: () => {
        isSubmittingRef.current = false;
      }
    };

    if (editingTask) {
      updateMutation.mutate({ id: editingTask.id, data: taskData }, mutationOptions);
    } else {
      createMutation.mutate(taskData, mutationOptions);
    }
  };

  const openDeductions = (record: any) => {
    setSelectedTask(record);
    setEditingDeduction(null);
    deductionForm.resetFields();
    setIsDeductionsModalVisible(true);
  };

  const handleDeductionSave = (values: any) => {
    // Prevent multiple submissions using ref (synchronous check)
    if (isDeductionSubmittingRef.current) {
      return;
    }
    isDeductionSubmittingRef.current = true;

    const deductionData = {
      deduction_name: values.deductionName,
      percentage: values.percentage,
      is_active: values.isActive ?? true,
    };

    const mutationOptions = {
      onSettled: () => {
        isDeductionSubmittingRef.current = false;
      }
    };

    if (editingDeduction) {
      updateDeductionMutation.mutate({
        taskId: selectedTask.id,
        deductionId: editingDeduction.id,
        data: deductionData,
      }, mutationOptions);
    } else {
      addDeductionMutation.mutate({
        taskId: selectedTask.id,
        data: deductionData,
      }, mutationOptions);
    }
  };

  const deductionColumns: ColumnsType<any> = [
    {
      title: 'Nombre',
      key: 'deductionName',
      render: (_, record) => record.deductionName || record.deduction_name,
    },
    {
      title: 'Porcentaje',
      dataIndex: 'percentage',
      key: 'percentage',
      render: (val: number) => `${val}%`,
    },
    {
      title: 'Activa',
      key: 'isActive',
      render: (_, record) => (
        <Switch
          checked={record.isActive ?? record.is_active}
          size="small"
          onChange={(checked) => {
            updateDeductionMutation.mutate({
              taskId: selectedTask.id,
              deductionId: record.id,
              data: { is_active: checked },
            });
          }}
        />
      ),
    },
    {
      title: 'Acciones',
      key: 'actions',
      width: 120,
      render: (_, record) => (
        <Space size="small">
          <Button
            type="link"
            size="small"
            icon={<EditOutlined />}
            onClick={() => {
              setEditingDeduction(record);
              deductionForm.setFieldsValue({
                deductionName: record.deductionName || record.deduction_name,
                percentage: record.percentage,
                isActive: record.isActive ?? record.is_active,
              });
            }}
          />
          <Popconfirm
            title="¿Eliminar esta deducción?"
            onConfirm={() => deleteDeductionMutation.mutate({ taskId: selectedTask.id, deductionId: record.id })}
            okText="Sí"
            cancelText="No"
          >
            <Button type="link" danger size="small" icon={<DeleteOutlined />} />
          </Popconfirm>
        </Space>
      ),
    },
  ];

  const mobileColumns: ColumnsType<any> = [
    {
      title: 'Tarea',
      key: 'task',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: '14px', marginBottom: 4 }}>
            {record.name}
          </div>
          <div style={{ fontSize: '12px', color: '#666' }}>
            <Tag color="blue" size="small">{record.code}</Tag>
            {formatCurrency(record.dailyCost || record.daily_cost)}
          </div>
        </div>
      ),
    },
    {
      title: 'Neto',
      key: 'netAmount',
      width: 100,
      render: (_, record) => (
        <div style={{ fontSize: '12px' }}>
          {record.netAmount !== undefined ? formatCurrency(record.netAmount) :
           record.net_amount !== undefined ? formatCurrency(record.net_amount) :
           formatCurrency(record.dailyCost || record.daily_cost)}
        </div>
      ),
    },
    {
      title: 'Acciones',
      key: 'actions',
      width: 100,
      fixed: 'right' as const,
      render: (_, record) => (
        <Space size="small">
          <Button type="link" icon={<DollarOutlined />} onClick={() => openDeductions(record)} size="small" />
          <Button type="link" icon={<EditOutlined />} onClick={() => handleEdit(record)} size="small" />
        </Space>
      ),
    },
  ];

  const desktopColumns: ColumnsType<any> = [
    {
      title: 'Código',
      dataIndex: 'code',
      key: 'code',
      sorter: (a, b) => a.code.localeCompare(b.code),
    },
    {
      title: 'Nombre',
      dataIndex: 'name',
      key: 'name',
      sorter: (a, b) => a.name.localeCompare(b.name),
    },
    {
      title: 'Duración (h)',
      key: 'durationHours',
      render: (_, record) => `${record.durationHours || record.duration_hours}h`,
      sorter: (a, b) => (a.durationHours || a.duration_hours || 0) - (b.durationHours || b.duration_hours || 0),
    },
    {
      title: 'Costo Diario',
      key: 'dailyCost',
      render: (_, record) => formatCurrency(record.dailyCost || record.daily_cost),
      sorter: (a, b) => (a.dailyCost || a.daily_cost || 0) - (b.dailyCost || b.daily_cost || 0),
    },
    {
      title: 'Deducciones',
      key: 'totalDeductionPercentage',
      render: (_, record) => {
        const pct = record.totalDeductionPercentage || record.total_deduction_percentage;
        return pct !== undefined ? `${pct}%` : '0%';
      },
    },
    {
      title: 'Valor Neto',
      key: 'netAmount',
      render: (_, record) => {
        const net = record.netAmount ?? record.net_amount;
        return net !== undefined ? formatCurrency(net) : formatCurrency(record.dailyCost || record.daily_cost);
      },
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
    },
    {
      title: 'Acciones',
      key: 'actions',
      fixed: 'right' as const,
      width: 220,
      render: (_, record) => (
        <Space size="middle">
          <Button type="link" icon={<DollarOutlined />} onClick={() => openDeductions(record)}>
            Deducciones
          </Button>
          <Button type="link" icon={<EditOutlined />} onClick={() => handleEdit(record)}>
            Editar
          </Button>
          <Popconfirm
            title="¿Está seguro de eliminar esta tarea?"
            onConfirm={() => deleteMutation.mutate(record.id)}
            okText="Sí"
            cancelText="No"
          >
            <Button type="link" danger icon={<DeleteOutlined />} />
          </Popconfirm>
        </Space>
      ),
    },
  ];

  return (
    <div>
      <div style={{ marginBottom: 16, display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ margin: 0, color: '#2E7D32' }}>Gestión de Tareas</h1>
          <p style={{ color: '#666', margin: 0 }}>Administra las tareas laborales y sus deducciones</p>
        </div>
        <Button
          type="primary"
          icon={<PlusOutlined />}
          onClick={() => {
            setEditingTask(null);
            form.resetFields();
            setIsModalVisible(true);
          }}
        >
          Nueva Tarea
        </Button>
      </div>

      <Card>
        <div style={{ marginBottom: 16 }}>
          <Row gutter={[16, 16]}>
            <Col xs={24} sm={12} md={8}>
              <Search
                placeholder="Buscar por nombre o código..."
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
          dataSource={tasks}
          rowKey="id"
          loading={isLoading || createMutation.isPending || updateMutation.isPending || deleteMutation.isPending}
          entityName="tareas"
          pagination={{ total: tasks.length, pageSize: 10 }}
        />
      </Card>

      {/* Task Create/Edit Modal */}
      <Modal
        title={editingTask ? 'Editar Tarea' : 'Nueva Tarea'}
        open={isModalVisible}
        onCancel={() => { setIsModalVisible(false); form.resetFields(); setEditingTask(null); }}
        footer={null}
        width={500}
      >
        <Form form={form} layout="vertical" onFinish={handleSave}>
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item name="code" label="Código" rules={[{ required: true, message: 'El código es requerido' }]}>
                <Input placeholder="Ej: TAR-001" />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="name" label="Nombre" rules={[{ required: true, message: 'El nombre es requerido' }]}>
                <Input placeholder="Nombre de la tarea" />
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col span={12}>
              <Form.Item name="durationHours" label="Duración (horas)" rules={[{ required: true, message: 'La duración es requerida' }]}>
                <InputNumber min={0.01} max={24} step={0.5} style={{ width: '100%' }} placeholder="Ej: 8" />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="dailyCost" label="Costo Diario ($)" rules={[{ required: true, message: 'El costo es requerido' }]}>
                <InputNumber min={0} step={1000} style={{ width: '100%' }} placeholder="Ej: 80000" />
              </Form.Item>
            </Col>
          </Row>

          <Form.Item name="description" label="Descripción">
            <Input.TextArea rows={2} placeholder="Descripción de la tarea (opcional)" />
          </Form.Item>

          <Form.Item name="status" label="Estado" initialValue="active">
            <Select>
              <Option value="active">Activo</Option>
              <Option value="inactive">Inactivo</Option>
            </Select>
          </Form.Item>

          <div style={{ textAlign: 'right' }}>
            <Space>
              <Button onClick={() => { setIsModalVisible(false); form.resetFields(); setEditingTask(null); }}>
                Cancelar
              </Button>
              <Button
                type="primary"
                htmlType="submit"
                loading={createMutation.isPending || updateMutation.isPending}
                disabled={createMutation.isPending || updateMutation.isPending}
              >
                {editingTask ? 'Actualizar' : 'Crear'}
              </Button>
            </Space>
          </div>
        </Form>
      </Modal>

      {/* Deductions Modal */}
      <Modal
        title={`Deducciones - ${selectedTask?.name || ''}`}
        open={isDeductionsModalVisible}
        onCancel={() => { setIsDeductionsModalVisible(false); setSelectedTask(null); setEditingDeduction(null); deductionForm.resetFields(); }}
        footer={null}
        width={650}
      >
        {selectedTask && (
          <>
            <div style={{ marginBottom: 16, padding: '12px', background: '#f5f5f5', borderRadius: 8 }}>
              <Row gutter={16}>
                <Col span={8}>
                  <div style={{ fontSize: '12px', color: '#666' }}>Costo Diario</div>
                  <div style={{ fontWeight: 600 }}>{formatCurrency(selectedTask.dailyCost || selectedTask.daily_cost)}</div>
                </Col>
                <Col span={8}>
                  <div style={{ fontSize: '12px', color: '#666' }}>Total Deducciones</div>
                  <div style={{ fontWeight: 600, color: '#f5222d' }}>
                    {selectedTask.totalDeductionPercentage || selectedTask.total_deduction_percentage || 0}%
                    ({formatCurrency(selectedTask.totalDeductions || selectedTask.total_deductions || 0)})
                  </div>
                </Col>
                <Col span={8}>
                  <div style={{ fontSize: '12px', color: '#666' }}>Valor Neto</div>
                  <div style={{ fontWeight: 600, color: '#2E7D32' }}>
                    {formatCurrency(selectedTask.netAmount ?? selectedTask.net_amount ?? (selectedTask.dailyCost || selectedTask.daily_cost))}
                  </div>
                </Col>
              </Row>
            </div>

            <Table
              dataSource={selectedTask.deductions || []}
              columns={deductionColumns}
              rowKey="id"
              size="small"
              pagination={false}
              scroll={{ x: 'max-content' }}
            />

            <Divider />

            <Form form={deductionForm} layout="inline" onFinish={handleDeductionSave} style={{ marginTop: 12 }}>
              <Form.Item name="deductionName" rules={[{ required: true, message: 'Nombre requerido' }]}>
                <Input placeholder="Nombre deducción" style={{ width: 180 }} />
              </Form.Item>
              <Form.Item name="percentage" rules={[{ required: true, message: 'Requerido' }]}>
                <InputNumber min={0.01} max={100} step={0.5} placeholder="%" style={{ width: 100 }} />
              </Form.Item>
              <Form.Item name="isActive" valuePropName="checked" initialValue={true}>
                <Switch checkedChildren="Activa" unCheckedChildren="Inactiva" defaultChecked />
              </Form.Item>
              <Form.Item>
                <Button
                  type="primary"
                  htmlType="submit"
                  icon={editingDeduction ? <EditOutlined /> : <PlusOutlined />}
                  loading={addDeductionMutation.isPending || updateDeductionMutation.isPending}
                  disabled={addDeductionMutation.isPending || updateDeductionMutation.isPending}
                >
                  {editingDeduction ? 'Actualizar' : 'Agregar'}
                </Button>
                {editingDeduction && (
                  <Button
                    style={{ marginLeft: 8 }}
                    onClick={() => { setEditingDeduction(null); deductionForm.resetFields(); }}
                  >
                    Cancelar
                  </Button>
                )}
              </Form.Item>
            </Form>
          </>
        )}
      </Modal>
    </div>
  );
};

export default Tasks;
