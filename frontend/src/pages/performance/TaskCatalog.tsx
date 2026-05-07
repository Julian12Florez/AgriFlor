import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Table, Button, Modal, Form, Input, Select, InputNumber, Tag, Space,
  Card, Switch, Divider, Tooltip, message, Row, Col, Typography
} from 'antd';
import {
  PlusOutlined, EditOutlined, SettingOutlined,
  CheckCircleOutlined, CloseCircleOutlined
} from '@ant-design/icons';
import { taskCatalogApi, taskCategoryApi, handleApiError } from '../../services/api';

const { Option } = Select;
const { Title, Text } = Typography;

const UNIT_LABELS: Record<string, string> = {
  arbol: 'Árbol',
  hectarea: 'Hectárea',
  m2: 'm²',
  metro: 'Metro',
};

const UNIT_COLORS: Record<string, string> = {
  arbol: 'green',
  hectarea: 'blue',
  m2: 'purple',
  metro: 'orange',
};

const TaskCatalog = () => {
  const [form] = Form.useForm();
  const [isModalVisible, setIsModalVisible] = useState(false);
  const [editingTask, setEditingTask] = useState<any>(null);
  const [showOverrides, setShowOverrides] = useState(false);
  const [filters, setFilters] = useState<Record<string, any>>({ per_page: 50 });
  const queryClient = useQueryClient();

  const { data, isLoading } = useQuery({
    queryKey: ['taskCatalog', filters],
    queryFn: () => taskCatalogApi.list(filters),
  });

  const { data: categoriesData } = useQuery({
    queryKey: ['taskCategories'],
    queryFn: () => taskCategoryApi.list({ active: true }),
  });

  const [catModal, setCatModal] = useState(false);
  const [catForm] = Form.useForm();

  const catMutation = useMutation({
    mutationFn: (values: any) => taskCategoryApi.create(values),
    onSuccess: () => {
      message.success('Categoría creada');
      queryClient.invalidateQueries({ queryKey: ['taskCategories'] });
      setCatModal(false);
      catForm.resetFields();
    },
    onError: (err: any) => handleApiError(err),
  });

  const saveMutation = useMutation({
    mutationFn: (values: any) => {
      if (editingTask) {
        return taskCatalogApi.update(editingTask.id, values);
      }
      return taskCatalogApi.create(values);
    },
    onSuccess: (res: any) => {
      message.success(res.message || 'Tarea guardada');
      queryClient.invalidateQueries({ queryKey: ['taskCatalog'] });
      queryClient.invalidateQueries({ queryKey: ['taskCatalogCategories'] });
      closeModal();
    },
    onError: (err: any) => handleApiError(err),
  });

  const toggleMutation = useMutation({
    mutationFn: (id: string) => taskCatalogApi.toggleActive(id),
    onSuccess: (res: any) => {
      message.success(res.message || 'Estado cambiado');
      queryClient.invalidateQueries({ queryKey: ['taskCatalog'] });
    },
    onError: (err: any) => handleApiError(err),
  });

  const openCreate = () => {
    setEditingTask(null);
    setShowOverrides(false);
    form.resetFields();
    setIsModalVisible(true);
  };

  const openEdit = (record: any) => {
    setEditingTask(record);
    setShowOverrides(record.metricsConfigured);
    form.setFieldsValue({
      name: record.name,
      unit: record.unit,
      category: record.category,
      category_id: record.categoryId,
      description: record.description,
      reference_yield: record.referenceYield ? Number(record.referenceYield) : null,
      override_sobrepaso_pct: record.overrideSobrepasoPct ? Number(record.overrideSobrepasoPct) : null,
      override_alto_pct: record.overrideAltoPct ? Number(record.overrideAltoPct) : null,
      override_medio_pct: record.overrideMedioPct ? Number(record.overrideMedioPct) : null,
      override_k_factor: record.overrideKFactor ? Number(record.overrideKFactor) : null,
    });
    setIsModalVisible(true);
  };

  const closeModal = () => {
    setIsModalVisible(false);
    setEditingTask(null);
    form.resetFields();
  };

  const handleSave = (values: any) => {
    if (!showOverrides) {
      values.override_sobrepaso_pct = null;
      values.override_alto_pct = null;
      values.override_medio_pct = null;
      values.override_k_factor = null;
    }
    saveMutation.mutate(values);
  };

  const tasks = data?.data || [];
  const categories: any[] = (categoriesData as any)?.data || [];

  const columns = [
    {
      title: 'Código',
      dataIndex: 'code',
      key: 'code',
      width: 90,
      render: (code: string) => <Tag>{code}</Tag>,
    },
    {
      title: 'Nombre',
      dataIndex: 'name',
      key: 'name',
    },
    {
      title: 'Unidad',
      dataIndex: 'unit',
      key: 'unit',
      width: 100,
      render: (unit: string) => (
        <Tag color={UNIT_COLORS[unit]}>{UNIT_LABELS[unit] || unit}</Tag>
      ),
    },
    {
      title: 'Categoría',
      dataIndex: 'category',
      key: 'category',
      width: 140,
    },
    {
      title: 'Rend. Ref.',
      dataIndex: 'referenceYield',
      key: 'referenceYield',
      width: 100,
      render: (val: any, record: any) =>
        val ? `${val} ${UNIT_LABELS[record.unit] || record.unit}/jornal` : '-',
    },
    {
      title: 'Estado',
      dataIndex: 'active',
      key: 'active',
      width: 80,
      render: (active: boolean) =>
        active ? (
          <Tag color="green" icon={<CheckCircleOutlined />}>Activa</Tag>
        ) : (
          <Tag color="default" icon={<CloseCircleOutlined />}>Inactiva</Tag>
        ),
    },
    {
      title: 'Métricas',
      key: 'metrics',
      width: 90,
      render: (_: any, record: any) =>
        record.metricsConfigured ? (
          <Tooltip title="Tiene override de métricas">
            <Tag color="blue" icon={<SettingOutlined />}>Override</Tag>
          </Tooltip>
        ) : (
          <Tag>Global</Tag>
        ),
    },
    {
      title: 'Acciones',
      key: 'actions',
      width: 160,
      render: (_: any, record: any) => (
        <Space size="small">
          <Button size="small" icon={<EditOutlined />} onClick={() => openEdit(record)}>
            Editar
          </Button>
          <Switch
            size="small"
            checked={record.active}
            onChange={() => toggleMutation.mutate(record.id)}
            checkedChildren="On"
            unCheckedChildren="Off"
          />
        </Space>
      ),
    },
  ];

  return (
    <div style={{ padding: 24 }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
        <div>
          <Title level={3} style={{ margin: 0 }}>Catálogo de Tareas Agrícolas</Title>
          <Text type="secondary">Define las labores y sus rendimientos de referencia</Text>
        </div>
        <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>
          Nueva Tarea
        </Button>
      </div>

      <Card size="small" style={{ marginBottom: 16 }}>
        <Space wrap>
          <Input.Search
            placeholder="Buscar por nombre o código..."
            allowClear
            style={{ width: 250 }}
            onSearch={(v) => setFilters({ ...filters, search: v || undefined })}
          />
          <Select
            placeholder="Categoría"
            allowClear
            style={{ width: 160 }}
            onChange={(v) => setFilters({ ...filters, category: v || undefined })}
          >
            {categories.map((cat: any) => (
              <Option key={cat.id || cat} value={cat.name || cat}>
                {cat.color && <span style={{ display: 'inline-block', width: 10, height: 10, borderRadius: '50%', background: cat.color, marginRight: 6 }} />}
                {cat.name || cat}
              </Option>
            ))}
          </Select>
          <Select
            placeholder="Unidad"
            allowClear
            style={{ width: 130 }}
            onChange={(v) => setFilters({ ...filters, unit: v || undefined })}
          >
            {Object.entries(UNIT_LABELS).map(([k, v]) => (
              <Option key={k} value={k}>{v}</Option>
            ))}
          </Select>
          <Select
            placeholder="Estado"
            allowClear
            style={{ width: 120 }}
            onChange={(v) => setFilters({ ...filters, active: v ?? undefined })}
          >
            <Option value="true">Activas</Option>
            <Option value="false">Inactivas</Option>
          </Select>
        </Space>
      </Card>

      <Table
        columns={columns}
        dataSource={tasks}
        rowKey="id"
        loading={isLoading}
        pagination={{
          total: (data as any)?.meta?.total,
          pageSize: filters.per_page,
          showSizeChanger: true,
          showTotal: (total: number) => `${total} tareas`,
        }}
        size="middle"
        scroll={{ x: 900 }}
      />

      <Modal
        title={editingTask ? 'Editar Tarea' : 'Nueva Tarea Agrícola'}
        open={isModalVisible}
        onCancel={closeModal}
        footer={null}
        width={600}
        destroyOnClose
      >
        <Form form={form} layout="vertical" onFinish={handleSave}>
          <Row gutter={16}>
            <Col span={16}>
              <Form.Item name="name" label="Nombre de la Tarea" rules={[{ required: true, message: 'Requerido' }]}>
                <Input placeholder="Ej: Podas de formación" />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item name="unit" label="Unidad Base" rules={[{ required: true, message: 'Requerido' }]}>
                <Select placeholder="Seleccionar">
                  {Object.entries(UNIT_LABELS).map(([k, v]) => (
                    <Option key={k} value={k}>{v}</Option>
                  ))}
                </Select>
              </Form.Item>
            </Col>
          </Row>
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item name="category_id" label={<Space>Categoría <Button type="link" size="small" onClick={() => setCatModal(true)}>+ Nueva</Button></Space>}>
                <Select placeholder="Seleccionar" allowClear showSearch optionFilterProp="children">
                  {categories.map((cat: any) => (
                    <Option key={cat.id} value={cat.id}>
                      {cat.color && <span style={{ display: 'inline-block', width: 10, height: 10, borderRadius: '50%', background: cat.color, marginRight: 6 }} />}
                      {cat.name}
                    </Option>
                  ))}
                </Select>
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item
                name="reference_yield"
                label="Rendimiento Referencia"
                tooltip="Cuántas unidades se esperan por jornal. Ej: 80 árboles/jornal."
              >
                <InputNumber min={0.01} precision={2} style={{ width: '100%' }} placeholder="Ej: 80" />
              </Form.Item>
            </Col>
          </Row>
          <Form.Item name="description" label="Descripción">
            <Input.TextArea rows={2} placeholder="Descripción adicional..." />
          </Form.Item>

          <Divider plain>
            <Space>
              <Switch
                checked={showOverrides}
                onChange={setShowOverrides}
                size="small"
              />
              <Text type="secondary">Override de métricas (opcional)</Text>
            </Space>
          </Divider>

          {showOverrides && (
            <Card size="small" style={{ marginBottom: 16, backgroundColor: '#fafafa' }}>
              <Text type="secondary" style={{ display: 'block', marginBottom: 12 }}>
                Si se definen, estos valores reemplazan la configuración global solo para esta tarea.
              </Text>
              <Row gutter={12}>
                <Col span={6}>
                  <Form.Item name="override_sobrepaso_pct" label="Sobrepaso %" tooltip="Umbral donde se marca sospechoso">
                    <InputNumber min={100} max={300} style={{ width: '100%' }} placeholder="130" />
                  </Form.Item>
                </Col>
                <Col span={6}>
                  <Form.Item name="override_alto_pct" label="Alto %">
                    <InputNumber min={1} style={{ width: '100%' }} placeholder="100" />
                  </Form.Item>
                </Col>
                <Col span={6}>
                  <Form.Item name="override_medio_pct" label="Medio %">
                    <InputNumber min={1} style={{ width: '100%' }} placeholder="80" />
                  </Form.Item>
                </Col>
                <Col span={6}>
                  <Form.Item name="override_k_factor" label="Factor K" tooltip="Multiplicador para detectar avance diario sospechoso">
                    <InputNumber min={1} max={10} precision={1} style={{ width: '100%' }} placeholder="3" />
                  </Form.Item>
                </Col>
              </Row>
            </Card>
          )}

          <div style={{ textAlign: 'right' }}>
            <Space>
              <Button onClick={closeModal}>Cancelar</Button>
              <Button type="primary" htmlType="submit" loading={saveMutation.isPending}>
                {editingTask ? 'Actualizar' : 'Crear'}
              </Button>
            </Space>
          </div>
        </Form>
      </Modal>

      {/* Modal Nueva Categoría */}
      <Modal
        title="Nueva Categoría de Tarea"
        open={catModal}
        onCancel={() => { setCatModal(false); catForm.resetFields(); }}
        footer={null}
        width={400}
        destroyOnClose
      >
        <Form form={catForm} layout="vertical" onFinish={(v) => catMutation.mutate(v)}>
          <Form.Item name="name" label="Nombre" rules={[{ required: true, message: 'Requerido' }]}>
            <Input placeholder="Ej: Nutrición" />
          </Form.Item>
          <Form.Item name="color" label="Color (hex)" initialValue="#1890ff">
            <Input placeholder="#1890ff" />
          </Form.Item>
          <Form.Item name="description" label="Descripción">
            <Input.TextArea rows={2} />
          </Form.Item>
          <div style={{ textAlign: 'right' }}>
            <Space>
              <Button onClick={() => { setCatModal(false); catForm.resetFields(); }}>Cancelar</Button>
              <Button type="primary" htmlType="submit" loading={catMutation.isPending}>Crear</Button>
            </Space>
          </div>
        </Form>
      </Modal>
    </div>
  );
};

export default TaskCatalog;
