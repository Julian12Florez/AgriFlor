import React, { useState, useMemo } from 'react';
import { Button, Input, Space, Card, Tag, Popconfirm, message, Modal, Form, Row, Col, Select, Switch } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import ResponsiveTable from '../../components/ResponsiveTable';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { outputTypesApi } from '../../services/api';

const { Search } = Input;
const { Option } = Select;
const { TextArea } = Input;

const OutputTypes: React.FC = () => {
  const queryClient = useQueryClient();
  const [isModalVisible, setIsModalVisible] = useState(false);
  const [editingType, setEditingType] = useState<any | null>(null);
  const [searchText, setSearchText] = useState('');
  const [statusFilter, setStatusFilter] = useState<string | undefined>();
  const [form] = Form.useForm();

  const { data: typesData, isLoading: typesLoading } = useQuery({
    queryKey: ['output-types', searchText, statusFilter],
    queryFn: () => outputTypesApi.list({
      search: searchText || undefined,
      status: statusFilter || undefined
    }),
  });

  // Fix: Ensure outputTypes is always an array with robust checking
  const outputTypes = useMemo(() => {
    if (!typesData) return [];
    if (Array.isArray(typesData.data)) return typesData.data;
    if (typesData.data && Array.isArray(typesData.data.data)) return typesData.data.data;
    return [];
  }, [typesData]);

  const createMutation = useMutation({
    mutationFn: (data: any) => outputTypesApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['output-types'] });
      setIsModalVisible(false);
      form.resetFields();
      message.success('Tipo de salida creado exitosamente');
    },
    onError: (error: any) => {
      message.error(`Error: ${error.message}`);
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: any }) => outputTypesApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['output-types'] });
      setIsModalVisible(false);
      form.resetFields();
      setEditingType(null);
      message.success('Tipo de salida actualizado exitosamente');
    },
    onError: (error: any) => {
      message.error(`Error: ${error.message}`);
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => outputTypesApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['output-types'] });
      message.success('Tipo de salida eliminado exitosamente');
    },
    onError: (error: any) => {
      message.error(`Error: ${error.message}`);
    },
  });

  const handleEdit = (record: any) => {
    setEditingType(record);
    form.setFieldsValue({
      name: record.name,
      code: record.code,
      description: record.description,
      requires_lots: record.requires_lots,
      status: record.status
    });
    setIsModalVisible(true);
  };

  const handleDelete = (id: string) => {
    deleteMutation.mutate(id);
  };

  const handleSave = (values: any) => {
    const typeData = {
      name: values.name,
      code: values.code,
      description: values.description,
      requires_lots: values.requires_lots || false,
      status: values.status || 'active',
    };

    if (editingType) {
      updateMutation.mutate({ id: editingType.id, data: typeData });
    } else {
      createMutation.mutate(typeData);
    }
  };

  const columns: ColumnsType<any> = [
    {
      title: 'Nombre',
      dataIndex: 'name',
      key: 'name',
      sorter: (a, b) => a.name.localeCompare(b.name),
    },
    {
      title: 'Código',
      dataIndex: 'code',
      key: 'code',
    },
    {
      title: 'Requiere Lotes',
      dataIndex: 'requires_lots',
      key: 'requires_lots',
      render: (requires: boolean) => (
        <Tag color={requires ? 'blue' : 'default'}>
          {requires ? 'Sí' : 'No'}
        </Tag>
      ),
    },
    {
      title: 'Estado',
      dataIndex: 'status',
      key: 'status',
      render: (status: string) => (
        <Tag color={status === 'active' ? 'success' : 'default'}>
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
          <Button
            type="link"
            icon={<EditOutlined />}
            onClick={() => handleEdit(record)}
          >
            Editar
          </Button>
          <Popconfirm
            title="¿Está seguro de eliminar este tipo de salida?"
            description="Esta acción no se puede deshacer"
            onConfirm={() => handleDelete(record.id)}
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
          <h1 style={{ margin: 0, color: '#2E7D32' }}>Tipos de Salida</h1>
          <p style={{ color: '#666', margin: 0 }}>
            Gestión de tipos de salida de productos
          </p>
        </div>
        <Button
          type="primary"
          icon={<PlusOutlined />}
          onClick={() => {
            setEditingType(null);
            form.resetFields();
            setIsModalVisible(true);
          }}
        >
          Nuevo Tipo
        </Button>
      </div>

      <Card>
        <div style={{ marginBottom: 16 }}>
          <Row gutter={[16, 16]}>
            <Col xs={24} sm={12} md={8}>
              <Search
                placeholder="Buscar tipos..."
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
          mobileColumns={columns}
          desktopColumns={columns}
          dataSource={outputTypes}
          rowKey="id"
          loading={typesLoading}
          entityName="tipos de salida"
          pagination={{
            total: outputTypes.length,
            pageSize: 10,
          }}
        />
      </Card>

      <Modal
        title={editingType ? 'Editar Tipo de Salida' : 'Nuevo Tipo de Salida'}
        open={isModalVisible}
        onCancel={() => {
          setIsModalVisible(false);
          form.resetFields();
          setEditingType(null);
        }}
        footer={null}
        width={600}
      >
        <Form
          form={form}
          layout="vertical"
          onFinish={handleSave}
        >
          <Form.Item
            name="name"
            label="Nombre"
            rules={[{ required: true, message: 'El nombre es requerido' }]}
          >
            <Input placeholder="Ej: Consumo" />
          </Form.Item>

          <Form.Item
            name="code"
            label="Código"
            rules={[{ required: true, message: 'El código es requerido' }]}
          >
            <Input placeholder="Ej: consumption" disabled={!!editingType} />
          </Form.Item>

          <Form.Item
            name="description"
            label="Descripción"
          >
            <TextArea rows={3} placeholder="Descripción del tipo de salida" />
          </Form.Item>

          <Form.Item
            name="requires_lots"
            label="¿Requiere Lotes de Finca?"
            valuePropName="checked"
          >
            <Switch />
          </Form.Item>

          <Form.Item
            name="status"
            label="Estado"
            rules={[{ required: true, message: 'El estado es requerido' }]}
          >
            <Select>
              <Option value="active">Activo</Option>
              <Option value="inactive">Inactivo</Option>
            </Select>
          </Form.Item>

          <div style={{ textAlign: 'right', marginTop: 24 }}>
            <Space>
              <Button
                onClick={() => {
                  setIsModalVisible(false);
                  form.resetFields();
                  setEditingType(null);
                }}
              >
                Cancelar
              </Button>
              <Button type="primary" htmlType="submit">
                {editingType ? 'Actualizar' : 'Crear'} Tipo
              </Button>
            </Space>
          </div>
        </Form>
      </Modal>
    </div>
  );
};

export default OutputTypes;
