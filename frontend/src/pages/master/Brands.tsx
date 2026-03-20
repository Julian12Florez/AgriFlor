import React, { useState, useRef } from 'react';
import { Button, Input, Space, Card, Tag, Popconfirm, message, Modal, Form, Row, Col, Select, Descriptions } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined, SearchOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import ResponsiveTable from '../../components/ResponsiveTable';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { brandsApi, handleApiError } from '../../services/api';
import type { Brand } from '../../data/types';


const { Search } = Input;
const { Option } = Select;

const Brands: React.FC = () => {
  const queryClient = useQueryClient();
  const [isModalVisible, setIsModalVisible] = useState(false);
  const [editingBrand, setEditingBrand] = useState<any | null>(null);
  const [searchText, setSearchText] = useState('');
  const [statusFilter, setStatusFilter] = useState<string | undefined>();
  const [form] = Form.useForm();

  // Ref to prevent double submissions (synchronous check)
  const isSubmittingRef = useRef(false);

  // Fetch brands from API
  const { data: brandsData, isLoading: brandsLoading } = useQuery({
    queryKey: ['brands', searchText, statusFilter],
    queryFn: () => brandsApi.list({
      search: searchText || undefined,
      status: statusFilter || undefined
    }),
  });

  const brands = brandsData?.data || [];

  // Create mutation
  const createBrandMutation = useMutation({
    mutationFn: (data: any) => brandsApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['brands'] });
      setIsModalVisible(false);
      form.resetFields();
      message.success('Marca creada exitosamente');
    },
    onError: (error: any) => {
      handleApiError(error, 'Error al crear marca', form);
    },
  });

  // Update mutation
  const updateBrandMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: any }) => brandsApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['brands'] });
      setIsModalVisible(false);
      form.resetFields();
      setEditingBrand(null);
      message.success('Marca actualizada exitosamente');
    },
    onError: (error: any) => {
      handleApiError(error, 'Error al actualizar marca', form);
    },
  });

  // Delete mutation
  const deleteBrandMutation = useMutation({
    mutationFn: (id: string) => brandsApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['brands'] });
      message.success('Marca eliminada exitosamente');
    },
    onError: (error: any) => {
      handleApiError(error, 'Error al eliminar marca');
    },
  });

  const handleEdit = (record: any) => {
    setEditingBrand(record);
    form.setFieldsValue({
      name: record.name,
      status: record.status
    });
    setIsModalVisible(true);
  };

  const handleDelete = (id: string) => {
    deleteBrandMutation.mutate(id);
  };

  const handleSave = (values: any) => {
    // Prevent multiple submissions using ref (synchronous check)
    if (isSubmittingRef.current) {
      return;
    }
    isSubmittingRef.current = true;

    const brandData = {
      name: values.name,
      status: values.status || 'active',
    };

    const mutationOptions = {
      onSettled: () => {
        isSubmittingRef.current = false;
      }
    };

    if (editingBrand) {
      updateBrandMutation.mutate({ id: editingBrand.id, data: brandData }, mutationOptions);
    } else {
      createBrandMutation.mutate(brandData, mutationOptions);
    }
  };

  const filteredBrands = brands;

  const mobileColumns: ColumnsType<Brand> = [
    {
      title: 'Marca',
      key: 'brand',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: '14px', marginBottom: 4 }}>
            {record.name}
          </div>
          <div style={{ fontSize: '12px', color: '#666' }}>
            <Tag color={record.status === 'active' ? 'green' : 'red'} size="small">
              {record.status === 'active' ? 'Activo' : 'Inactivo'}
            </Tag>
            {record.created_at && <span style={{ marginLeft: 8 }}>{new Date(record.created_at).toLocaleDateString('es-CO')}</span>}
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
            onConfirm={() => handleDelete(record.id)}
            okText="Sí"
            cancelText="No"
          >
            <Button type="link" danger icon={<DeleteOutlined />} size="small" />
          </Popconfirm>
        </Space>
      ),
    },
  ];

  const desktopColumns: ColumnsType<Brand> = [
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
      title: 'Fecha de Creación',
      dataIndex: 'created_at',
      key: 'created_at',
      render: (date: string) => date ? new Date(date).toLocaleDateString('es-CO') : 'N/A',
      sorter: (a, b) => {
        if (!a.created_at || !b.created_at) return 0;
        return new Date(a.created_at).getTime() - new Date(b.created_at).getTime();
      },
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
            title="¿Está seguro de eliminar esta marca?"
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

  const expandedRowRender = (record: Brand) => (
    <Descriptions size="small" column={1}>
      <Descriptions.Item label="Nombre">{record.name}</Descriptions.Item>
      <Descriptions.Item label="Estado">
        <Tag color={record.status === 'active' ? 'green' : 'red'}>
          {record.status === 'active' ? 'Activo' : 'Inactivo'}
        </Tag>
      </Descriptions.Item>
      <Descriptions.Item label="Fecha de creación">
        {record.created_at ? new Date(record.created_at).toLocaleDateString('es-CO') : 'N/A'}
      </Descriptions.Item>
    </Descriptions>
  );

  return (
    <div>
      <div style={{ marginBottom: 16, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div>
          <h1 style={{ margin: 0, color: '#2E7D32' }}>Gestión de Marcas</h1>
          <p style={{ color: '#666', margin: 0 }}>
            Administra las marcas de productos químicos agrícolas
          </p>
        </div>
        <Button
          type="primary"
          icon={<PlusOutlined />}
          onClick={() => {
            setEditingBrand(null);
            form.resetFields();
            setIsModalVisible(true);
          }}
        >
          Nueva Marca
        </Button>
      </div>

      <Card>
        <div style={{ marginBottom: 16 }}>
          <Space>
            <Search
              placeholder="Buscar marcas..."
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
          dataSource={filteredBrands}
          rowKey="id"
          loading={brandsLoading || createBrandMutation.isPending || updateBrandMutation.isPending || deleteBrandMutation.isPending}
          expandedRowRender={expandedRowRender}
          entityName="marcas"
          pagination={{
            total: filteredBrands.length,
            pageSize: 10,
          }}
        />
      </Card>

      <Modal
        title={editingBrand ? 'Editar Marca' : 'Nueva Marca'}
        open={isModalVisible}
        onCancel={() => {
          setIsModalVisible(false);
          form.resetFields();
          setEditingBrand(null);
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
                label="Nombre de la Marca"
                rules={[
                  { required: true, message: 'El nombre es requerido' },
                  { min: 2, message: 'El nombre debe tener al menos 2 caracteres' },
                  { max: 50, message: 'El nombre no puede exceder 50 caracteres' }
                ]}
              >
                <Input
                  placeholder="Ingrese el nombre de la marca"
                  maxLength={50}
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
                setEditingBrand(null);
              }}>
                Cancelar
              </Button>
              <Button
                type="primary"
                htmlType="submit"
                loading={createBrandMutation.isPending || updateBrandMutation.isPending}
                disabled={createBrandMutation.isPending || updateBrandMutation.isPending}
              >
                {editingBrand ? 'Actualizar' : 'Crear'}
              </Button>
            </Space>
          </div>
        </Form>
      </Modal>
    </div>
  );
};

export default Brands;