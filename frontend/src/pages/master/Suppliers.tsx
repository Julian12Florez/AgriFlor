import React, { useState } from 'react';
import { Button, Input, Space, Card, Tag, Popconfirm, message, Modal, Form, Row, Col, Select, Tabs, Descriptions } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined, UserOutlined, PhoneOutlined, MailOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { suppliersApi } from '../../services/api';
import ResponsiveTable from '../../components/ResponsiveTable';

interface SupplierContact {
  id: string;
  name: string;
  position: string;
  phone: string;
  email: string;
}

interface Supplier {
  id: string;
  name: string;
  nit: string;
  address: string;
  city: string;
  phone: string;
  email: string;
  contacts: SupplierContact[];
  status: 'active' | 'inactive';
  paymentTerms: string;
  createdAt: Date;
}

const { Search } = Input;
const { Option } = Select;
const { TabPane } = Tabs;

const Suppliers: React.FC = () => {
  const queryClient = useQueryClient();
  const [isModalVisible, setIsModalVisible] = useState(false);
  const [editingSupplier, setEditingSupplier] = useState<Supplier | null>(null);
  const [searchText, setSearchText] = useState('');
  const [statusFilter, setStatusFilter] = useState<string | undefined>();
  const [form] = Form.useForm();
  const [contactForm] = Form.useForm();

  // Fetch suppliers from API
  const { data: suppliersData, isLoading: suppliersLoading } = useQuery({
    queryKey: ['suppliers', searchText, statusFilter],
    queryFn: () => suppliersApi.list({
      search: searchText || undefined,
      status: statusFilter || undefined
    }),
  });

  const suppliers = suppliersData?.data || [];

  // Create supplier mutation
  const createSupplierMutation = useMutation({
    mutationFn: (data: any) => suppliersApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['suppliers'] });
      setIsModalVisible(false);
      form.resetFields();
      message.success('Proveedor creado exitosamente');
    },
    onError: (error: any) => {
      message.error(`Error al crear proveedor: ${error.message}`);
    },
  });

  // Update supplier mutation
  const updateSupplierMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: any }) => suppliersApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['suppliers'] });
      setIsModalVisible(false);
      form.resetFields();
      setEditingSupplier(null);
      message.success('Proveedor actualizado exitosamente');
    },
    onError: (error: any) => {
      message.error(`Error al actualizar proveedor: ${error.message}`);
    },
  });

  // Delete supplier mutation
  const deleteSupplierMutation = useMutation({
    mutationFn: (id: string) => suppliersApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['suppliers'] });
      message.success('Proveedor eliminado exitosamente');
    },
    onError: (error: any) => {
      message.error(`Error al eliminar proveedor: ${error.message}`);
    },
  });

  const handleEdit = (record: any) => {
    setEditingSupplier(record);
    form.setFieldsValue({
      name: record.name,
      nit: record.nit,
      address: record.address,
      city: record.city,
      phone: record.phone,
      email: record.email,
      paymentTerms: record.payment_terms,
      status: record.status
    });
    setIsModalVisible(true);
  };

  const handleDelete = (id: string) => {
    deleteSupplierMutation.mutate(id);
  };

  const handleSave = (values: any) => {
    // Format data for backend API (snake_case)
    const supplierData = {
      name: values.name,
      nit: values.nit,
      address: values.address,
      city: values.city,
      phone: values.phone,
      email: values.email,
      payment_terms: values.paymentTerms,
      status: values.status || 'active',
    };

    if (editingSupplier) {
      updateSupplierMutation.mutate({ id: editingSupplier.id, data: supplierData });
    } else {
      createSupplierMutation.mutate(supplierData);
    }
  };

  // Backend handles filtering, so no need to filter locally
  const filteredSuppliers = suppliers;

  const mobileColumns: ColumnsType<Supplier> = [
    {
      title: 'Proveedor',
      key: 'supplier',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: '14px', marginBottom: 4 }}>
            {record.name}
          </div>
          <div style={{ fontSize: '12px', color: '#666' }}>
            {record.status && (
              <Tag color={record.status === 'active' ? 'green' : 'red'} size="small">
                {record.status === 'active' ? 'Activo' : 'Inactivo'}
              </Tag>
            )}
            {record.city && <span style={{ marginLeft: 8 }}>{record.city}</span>}
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

  const desktopColumns: ColumnsType<Supplier> = [
    {
      title: 'Información Básica',
      key: 'basic',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: 14 }}>{record.name}</div>
          <div style={{ color: '#666', fontSize: 12 }}>NIT: {record.nit}</div>
          <div style={{ color: '#666', fontSize: 12 }}>{record.city}</div>
        </div>
      ),
    },
    {
      title: 'Contacto',
      key: 'contact',
      render: (_, record) => (
        <div>
          <div style={{ fontSize: 12, marginBottom: 2 }}>
            <PhoneOutlined style={{ marginRight: 4, color: '#4CAF50' }} />
            {record.phone}
          </div>
          <div style={{ fontSize: 12 }}>
            <MailOutlined style={{ marginRight: 4, color: '#2196F3' }} />
            {record.email}
          </div>
        </div>
      ),
    },
    {
      title: 'Condiciones',
      dataIndex: 'payment_terms',
      key: 'payment_terms',
      render: (terms: string) => (
        terms ? <Tag color="blue">{terms}</Tag> : <Tag>N/A</Tag>
      ),
    },
    {
      title: 'Contactos',
      dataIndex: 'contacts',
      key: 'contacts',
      render: (contacts: SupplierContact[]) => (
        <div>
          {contacts.length > 0 ? (
            contacts.map(contact => (
              <div key={contact.id} style={{ fontSize: 12, marginBottom: 2 }}>
                <UserOutlined style={{ marginRight: 4, color: '#FF9800' }} />
                {contact.name} - {contact.position}
              </div>
            ))
          ) : (
            <span style={{ color: '#999', fontSize: 12 }}>Sin contactos</span>
          )}
        </div>
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
            title="¿Está seguro de eliminar este proveedor?"
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

  const expandedRowRender = (record: Supplier) => (
    <Descriptions size="small" column={1}>
      <Descriptions.Item label="Nombre">{record.name}</Descriptions.Item>
      <Descriptions.Item label="NIT">{record.nit}</Descriptions.Item>
      <Descriptions.Item label="Dirección">{record.address}</Descriptions.Item>
      <Descriptions.Item label="Ciudad">{record.city}</Descriptions.Item>
      <Descriptions.Item label="Teléfono">
        <PhoneOutlined style={{ marginRight: 4, color: '#4CAF50' }} />
        {record.phone}
      </Descriptions.Item>
      <Descriptions.Item label="Email">
        <MailOutlined style={{ marginRight: 4, color: '#2196F3' }} />
        {record.email}
      </Descriptions.Item>
      <Descriptions.Item label="Condiciones de pago">
        {record.payment_terms ? <Tag color="blue">{record.payment_terms}</Tag> : 'N/A'}
      </Descriptions.Item>
      <Descriptions.Item label="Estado">
        <Tag color={record.status === 'active' ? 'green' : 'red'}>
          {record.status === 'active' ? 'Activo' : 'Inactivo'}
        </Tag>
      </Descriptions.Item>
      <Descriptions.Item label="Contactos">
        {record.contacts.length > 0 ? (
          record.contacts.map(contact => (
            <div key={contact.id} style={{ marginBottom: 8 }}>
              <div style={{ fontWeight: 500 }}>
                <UserOutlined style={{ marginRight: 4, color: '#FF9800' }} />
                {contact.name}
              </div>
              <div style={{ fontSize: 12, color: '#666' }}>
                {contact.position} |
                <PhoneOutlined style={{ margin: '0 4px', color: '#4CAF50' }} />
                {contact.phone} |
                <MailOutlined style={{ margin: '0 4px', color: '#2196F3' }} />
                {contact.email}
              </div>
            </div>
          ))
        ) : (
          <span style={{ color: '#999' }}>Sin contactos registrados</span>
        )}
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
          <h1 style={{ margin: 0, color: '#2E7D32' }}>Gestión de Proveedores</h1>
          <p style={{ color: '#666', margin: 0 }}>
            Administra los proveedores de productos químicos agrícolas
          </p>
        </div>
        <Button
          type="primary"
          icon={<PlusOutlined />}
          onClick={() => {
            setEditingSupplier(null);
            form.resetFields();
            setIsModalVisible(true);
          }}
        >
          Nuevo Proveedor
        </Button>
      </div>

      <Card>
        <div style={{ marginBottom: 16 }}>
          <Space>
            <Search
              placeholder="Buscar proveedores..."
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
          dataSource={filteredSuppliers}
          rowKey="id"
          loading={suppliersLoading || createSupplierMutation.isPending || updateSupplierMutation.isPending || deleteSupplierMutation.isPending}
          expandedRowRender={expandedRowRender}
          entityName="proveedores"
          pagination={{
            total: filteredSuppliers.length,
            pageSize: 10,
          }}
        />
      </Card>

      <Modal
        title={editingSupplier ? 'Editar Proveedor' : 'Nuevo Proveedor'}
        open={isModalVisible}
        onCancel={() => {
          setIsModalVisible(false);
          form.resetFields();
          setEditingSupplier(null);
        }}
        footer={null}
        width={800}
      >
        <Form
          form={form}
          layout="vertical"
          onFinish={handleSave}
        >
          <Tabs defaultActiveKey="1">
            <TabPane tab="Información General" key="1">
              <Row gutter={16}>
                <Col span={12}>
                  <Form.Item
                    name="name"
                    label="Nombre del Proveedor"
                    rules={[{ required: true, message: 'El nombre es requerido' }]}
                  >
                    <Input placeholder="Ingrese el nombre del proveedor" />
                  </Form.Item>
                </Col>
                <Col span={12}>
                  <Form.Item
                    name="nit"
                    label="NIT"
                    rules={[{ required: true, message: 'El NIT es requerido' }]}
                  >
                    <Input placeholder="Ingrese el NIT" />
                  </Form.Item>
                </Col>
              </Row>

              <Row gutter={16}>
                <Col span={12}>
                  <Form.Item
                    name="address"
                    label="Dirección"
                    rules={[{ required: true, message: 'La dirección es requerida' }]}
                  >
                    <Input placeholder="Ingrese la dirección" />
                  </Form.Item>
                </Col>
                <Col span={12}>
                  <Form.Item
                    name="city"
                    label="Ciudad"
                    rules={[{ required: true, message: 'La ciudad es requerida' }]}
                  >
                    <Input placeholder="Ingrese la ciudad" />
                  </Form.Item>
                </Col>
              </Row>

              <Row gutter={16}>
                <Col span={12}>
                  <Form.Item
                    name="phone"
                    label="Teléfono"
                    rules={[{ required: true, message: 'El teléfono es requerido' }]}
                  >
                    <Input placeholder="Ingrese el teléfono" />
                  </Form.Item>
                </Col>
                <Col span={12}>
                  <Form.Item
                    name="email"
                    label="Email"
                    rules={[
                      { required: true, message: 'El email es requerido' },
                      { type: 'email', message: 'Ingrese un email válido' }
                    ]}
                  >
                    <Input placeholder="Ingrese el email" />
                  </Form.Item>
                </Col>
              </Row>

              <Row gutter={16}>
                <Col span={12}>
                  <Form.Item
                    name="paymentTerms"
                    label="Condiciones de Pago"
                    rules={[{ required: true, message: 'Las condiciones de pago son requeridas' }]}
                  >
                    <Select placeholder="Seleccione las condiciones">
                      <Option value="Contado">Contado</Option>
                      <Option value="15 días">15 días</Option>
                      <Option value="30 días">30 días</Option>
                      <Option value="45 días">45 días</Option>
                      <Option value="60 días">60 días</Option>
                    </Select>
                  </Form.Item>
                </Col>
                <Col span={12}>
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
            </TabPane>
          </Tabs>

          <div style={{ textAlign: 'right', marginTop: 24 }}>
            <Space>
              <Button onClick={() => {
                setIsModalVisible(false);
                form.resetFields();
                setEditingSupplier(null);
              }}>
                Cancelar
              </Button>
              <Button type="primary" htmlType="submit">
                {editingSupplier ? 'Actualizar' : 'Crear'}
              </Button>
            </Space>
          </div>
        </Form>
      </Modal>
    </div>
  );
};

export default Suppliers;