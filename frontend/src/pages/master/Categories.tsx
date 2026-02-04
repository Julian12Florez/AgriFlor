import React, { useState, useRef } from 'react';
import { Button, Input, Space, Card, Tag, Popconfirm, message, Modal, Form, Row, Col, Select, Descriptions } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import ResponsiveTable from '../../components/ResponsiveTable';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { categoriesApi } from '../../services/api';

const { Search } = Input;
const { Option } = Select;
const { TextArea } = Input;

interface Category {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  status: 'active' | 'inactive';
  productsCount?: number;
  createdAt: string;
}

const Categories: React.FC = () => {
  const queryClient = useQueryClient();
  const [isModalVisible, setIsModalVisible] = useState(false);
  const [editingCategory, setEditingCategory] = useState<Category | null>(null);
  const [searchText, setSearchText] = useState('');
  const [statusFilter, setStatusFilter] = useState<string | undefined>();
  const [form] = Form.useForm();

  // Ref to prevent double submissions
  const isSubmittingRef = useRef(false);

  // Fetch categories from API
  const { data: categoriesData, isLoading: categoriesLoading } = useQuery({
    queryKey: ['categories', searchText, statusFilter],
    queryFn: () => categoriesApi.list({
      search: searchText || undefined,
      status: statusFilter || undefined
    }),
  });

  const categories = categoriesData?.data || [];

  // Create mutation
  const createCategoryMutation = useMutation({
    mutationFn: (data: any) => categoriesApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['categories'] });
      setIsModalVisible(false);
      form.resetFields();
      message.success('Categoría creada exitosamente');
    },
    onError: (error: any) => {
      message.error(`Error: ${error.message}`);
    },
  });

  // Update mutation
  const updateCategoryMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: any }) => categoriesApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['categories'] });
      setIsModalVisible(false);
      form.resetFields();
      setEditingCategory(null);
      message.success('Categoría actualizada exitosamente');
    },
    onError: (error: any) => {
      message.error(`Error: ${error.message}`);
    },
  });

  // Delete mutation
  const deleteCategoryMutation = useMutation({
    mutationFn: (id: string) => categoriesApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['categories'] });
      message.success('Categoría eliminada exitosamente');
    },
    onError: (error: any) => {
      message.error(`Error: ${error.message}`);
    },
  });

  const handleEdit = (record: Category) => {
    setEditingCategory(record);
    form.setFieldsValue({
      name: record.name,
      description: record.description,
      status: record.status
    });
    setIsModalVisible(true);
  };

  const handleDelete = (id: string) => {
    deleteCategoryMutation.mutate(id);
  };

  const handleSave = (values: any) => {
    if (isSubmittingRef.current) {
      return;
    }
    isSubmittingRef.current = true;

    const categoryData = {
      name: values.name,
      description: values.description || null,
      status: values.status || 'active',
    };

    const mutationOptions = {
      onSettled: () => {
        isSubmittingRef.current = false;
      }
    };

    if (editingCategory) {
      updateCategoryMutation.mutate({ id: editingCategory.id, data: categoryData }, mutationOptions);
    } else {
      createCategoryMutation.mutate(categoryData, mutationOptions);
    }
  };

  const mobileColumns: ColumnsType<Category> = [
    {
      title: 'Categoría',
      key: 'category',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: '14px', marginBottom: 4 }}>
            {record.name}
          </div>
          <div style={{ fontSize: '12px', color: '#666', marginBottom: 4 }}>
            {record.description || 'Sin descripción'}
          </div>
          <div style={{ fontSize: '12px', color: '#666' }}>
            <Tag color={record.status === 'active' ? 'green' : 'red'}>
              {record.status === 'active' ? 'Activo' : 'Inactivo'}
            </Tag>
            {record.productsCount !== undefined && (
              <span style={{ marginLeft: 8 }}>{record.productsCount} productos</span>
            )}
          </div>
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
          <Button
            type="link"
            icon={<EditOutlined />}
            onClick={() => handleEdit(record)}
            size="small"
          />
          <Popconfirm
            title="¿Eliminar?"
            description={record.productsCount && record.productsCount > 0
              ? "Esta categoría tiene productos asociados"
              : "Esta acción no se puede deshacer"}
            onConfirm={() => handleDelete(record.id)}
            okText="Sí"
            cancelText="No"
            disabled={record.productsCount !== undefined && record.productsCount > 0}
          >
            <Button
              type="link"
              danger
              icon={<DeleteOutlined />}
              size="small"
              disabled={record.productsCount !== undefined && record.productsCount > 0}
            />
          </Popconfirm>
        </Space>
      ),
    },
  ];

  const desktopColumns: ColumnsType<Category> = [
    {
      title: 'Nombre',
      dataIndex: 'name',
      key: 'name',
      sorter: (a, b) => a.name.localeCompare(b.name),
      render: (text: string) => (
        <span style={{ fontWeight: 500 }}>{text}</span>
      ),
    },
    {
      title: 'Descripción',
      dataIndex: 'description',
      key: 'description',
      render: (text: string) => text || <span style={{ color: '#999' }}>Sin descripción</span>,
    },
    {
      title: 'Productos',
      dataIndex: 'productsCount',
      key: 'productsCount',
      render: (count: number) => count ?? 0,
      sorter: (a, b) => (a.productsCount ?? 0) - (b.productsCount ?? 0),
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
          <Button
            type="link"
            icon={<EditOutlined />}
            onClick={() => handleEdit(record)}
          >
            Editar
          </Button>
          <Popconfirm
            title="¿Está seguro de eliminar esta categoría?"
            description={record.productsCount && record.productsCount > 0
              ? "Esta categoría tiene productos asociados y no puede eliminarse"
              : "Esta acción no se puede deshacer"}
            onConfirm={() => handleDelete(record.id)}
            okText="Sí"
            cancelText="No"
            disabled={record.productsCount !== undefined && record.productsCount > 0}
          >
            <Button
              type="link"
              danger
              icon={<DeleteOutlined />}
              disabled={record.productsCount !== undefined && record.productsCount > 0}
            >
              Eliminar
            </Button>
          </Popconfirm>
        </Space>
      ),
    },
  ];

  const expandedRowRender = (record: Category) => (
    <Descriptions size="small" column={1}>
      <Descriptions.Item label="Nombre">{record.name}</Descriptions.Item>
      <Descriptions.Item label="Identificador">{record.slug}</Descriptions.Item>
      <Descriptions.Item label="Descripción">{record.description || 'Sin descripción'}</Descriptions.Item>
      <Descriptions.Item label="Productos asociados">{record.productsCount ?? 0}</Descriptions.Item>
      <Descriptions.Item label="Estado">
        <Tag color={record.status === 'active' ? 'green' : 'red'}>
          {record.status === 'active' ? 'Activo' : 'Inactivo'}
        </Tag>
      </Descriptions.Item>
    </Descriptions>
  );

  return (
    <div>
      <div style={{ marginBottom: 16, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div>
          <h1 style={{ margin: 0, color: '#2E7D32' }}>Gestión de Categorías</h1>
          <p style={{ color: '#666', margin: 0 }}>
            Administra las categorías de productos químicos agrícolas
          </p>
        </div>
        <Button
          type="primary"
          icon={<PlusOutlined />}
          onClick={() => {
            setEditingCategory(null);
            form.resetFields();
            setIsModalVisible(true);
          }}
        >
          Nueva Categoría
        </Button>
      </div>

      <Card>
        <div style={{ marginBottom: 16 }}>
          <Space>
            <Search
              placeholder="Buscar categorías..."
              allowClear
              style={{ width: 300 }}
              value={searchText}
              onChange={(e) => setSearchText(e.target.value)}
            />
            <Select
              placeholder="Filtrar por estado"
              allowClear
              style={{ width: 150 }}
              value={statusFilter}
              onChange={setStatusFilter}
            >
              <Option value="active">Activo</Option>
              <Option value="inactive">Inactivo</Option>
            </Select>
          </Space>
        </div>

        <ResponsiveTable
          mobileColumns={mobileColumns}
          desktopColumns={desktopColumns}
          dataSource={categories}
          rowKey="id"
          loading={categoriesLoading || createCategoryMutation.isPending || updateCategoryMutation.isPending || deleteCategoryMutation.isPending}
          expandedRowRender={expandedRowRender}
          entityName="categorías"
          pagination={{
            total: categories.length,
            pageSize: 10,
          }}
        />
      </Card>

      <Modal
        title={editingCategory ? 'Editar Categoría' : 'Nueva Categoría'}
        open={isModalVisible}
        onCancel={() => {
          setIsModalVisible(false);
          form.resetFields();
          setEditingCategory(null);
        }}
        footer={null}
        width={500}
      >
        <Form
          form={form}
          layout="vertical"
          onFinish={handleSave}
        >
          <Row gutter={16}>
            <Col span={24}>
              <Form.Item
                name="name"
                label="Nombre de la Categoría"
                rules={[
                  { required: true, message: 'El nombre es requerido' },
                  { min: 2, message: 'El nombre debe tener al menos 2 caracteres' },
                  { max: 50, message: 'El nombre no puede exceder 50 caracteres' }
                ]}
              >
                <Input
                  placeholder="Ingrese el nombre de la categoría"
                  maxLength={50}
                />
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col span={24}>
              <Form.Item
                name="description"
                label="Descripción"
              >
                <TextArea
                  placeholder="Descripción de la categoría (opcional)"
                  rows={3}
                  maxLength={500}
                  showCount
                />
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col span={24}>
              <Form.Item
                name="status"
                label="Estado"
                rules={[{ required: true, message: 'El estado es requerido' }]}
              >
                <Select placeholder="Seleccione el estado">
                  <Option value="active">Activo</Option>
                  <Option value="inactive">Inactivo</Option>
                </Select>
              </Form.Item>
            </Col>
          </Row>

          <div style={{ textAlign: 'right', marginTop: 24 }}>
            <Space>
              <Button onClick={() => {
                setIsModalVisible(false);
                form.resetFields();
                setEditingCategory(null);
              }}>
                Cancelar
              </Button>
              <Button
                type="primary"
                htmlType="submit"
                loading={createCategoryMutation.isPending || updateCategoryMutation.isPending}
                disabled={createCategoryMutation.isPending || updateCategoryMutation.isPending}
              >
                {editingCategory ? 'Actualizar' : 'Crear'}
              </Button>
            </Space>
          </div>
        </Form>
      </Modal>
    </div>
  );
};

export default Categories;
