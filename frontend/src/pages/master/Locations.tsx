import React, { useState, useEffect } from 'react';
import { Button, Input, Space, Card, Tag, Popconfirm, message, Modal, Form, Row, Col, Select, Badge, Descriptions, Divider, InputNumber } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined, EnvironmentOutlined, HomeOutlined, BankOutlined, MinusCircleOutlined, PlusCircleOutlined } from '@ant-design/icons';
import ResponsiveTable from '../../components/ResponsiveTable';
import type { ColumnsType } from 'antd/es/table';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { locationsApi, farmLotsApi, usersApi } from '../../services/api';
import type { Location } from '../../data/types';

const { Search } = Input;
const { Option } = Select;

const Locations: React.FC = () => {
  const queryClient = useQueryClient();
  const [isMobile, setIsMobile] = useState(false);
  const [isModalVisible, setIsModalVisible] = useState(false);
  const [editingLocation, setEditingLocation] = useState<Location | null>(null);
  const [searchText, setSearchText] = useState('');
  const [typeFilter, setTypeFilter] = useState<string | undefined>();
  const [statusFilter, setStatusFilter] = useState<string | undefined>();
  const [selectedLocationType, setSelectedLocationType] = useState<string | undefined>();
  const [form] = Form.useForm();

  // Fetch locations from API
  const { data: locationsData, isLoading: locationsLoading } = useQuery({
    queryKey: ['locations', searchText, typeFilter, statusFilter],
    queryFn: () => locationsApi.list({
      search: searchText || undefined,
      type: typeFilter || undefined,
      status: statusFilter || undefined
    }),
  });

  const locations = locationsData?.data || [];

  // Fetch users for responsible select
  const { data: usersData, isLoading: usersLoading } = useQuery({
    queryKey: ['users-simple', 'active'],
    queryFn: () => usersApi.listSimple({ status: 'active' }),
  });
  const users = usersData?.data || [];

  // Create location mutation
  const createLocationMutation = useMutation({
    mutationFn: (data: any) => locationsApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['locations'] });
      setIsModalVisible(false);
      form.resetFields();
      message.success('Ubicación creada exitosamente');
    },
    onError: (error: any) => {
      message.error(`Error al crear ubicación: ${error.message}`);
    },
  });

  // Update location mutation
  const updateLocationMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: any }) => locationsApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['locations'] });
      setIsModalVisible(false);
      form.resetFields();
      setEditingLocation(null);
      message.success('Ubicación actualizada exitosamente');
    },
    onError: (error: any) => {
      message.error(`Error al actualizar ubicación: ${error.message}`);
    },
  });

  // Delete location mutation
  const deleteLocationMutation = useMutation({
    mutationFn: (id: string) => locationsApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['locations'] });
      message.success('Ubicación eliminada exitosamente');
    },
    onError: (error: any) => {
      message.error(`Error al eliminar ubicación: ${error.message}`);
    },
  });

  useEffect(() => {
    const checkScreenSize = () => {
      setIsMobile(window.innerWidth < 768);
    };

    checkScreenSize();
    window.addEventListener('resize', checkScreenSize);
    return () => window.removeEventListener('resize', checkScreenSize);
  }, []);

  const getTypeIcon = (type: string) => {
    return type === 'farm' ? <HomeOutlined /> : <BankOutlined />;
  };

  const getTypeColor = (type: string) => {
    return type === 'farm' ? 'green' : 'blue';
  };

  const getTypeLabel = (type: string) => {
    return type === 'farm' ? 'Finca' : 'Bodega';
  };

  const handleEdit = async (record: Location) => {
    setEditingLocation(record);

    // Cargar lotes si es una finca
    let lots = [];
    if (record.type === 'farm') {
      try {
        const response = await farmLotsApi.getByLocation(record.id);
        lots = response.data || [];
      } catch (error) {
        console.error('Error cargando lotes:', error);
      }
    }

    form.setFieldsValue({
      name: record.name,
      type: record.type,
      municipality: record.municipality,
      address: record.address,
      responsible_user_id: record.responsible_user_id,
      status: record.status,
      coordinates: record.coordinates ? {
        lat: record.coordinates.lat,
        lng: record.coordinates.lng
      } : undefined,
      lots: lots // Agregar los lotes al formulario
    });
    setIsModalVisible(true);
  };

  const handleDelete = (id: string) => {
    deleteLocationMutation.mutate(id);
  };

  const handleSave = async (values: any) => {
    try {
      const lat = values.coordinates?.lat;
      const lng = values.coordinates?.lng;

      // Format data for backend API (snake_case)
      const locationData = {
        name: values.name,
        type: values.type,
        municipality: values.municipality,
        address: values.address,
        responsible_user_id: values.responsible_user_id,
        status: values.status || 'active',
        coordinates: (lat && lng && !isNaN(Number(lat)) && !isNaN(Number(lng)))
          ? { lat: Number(lat), lng: Number(lng) }
          : undefined
      };

      let locationId: string;

      if (editingLocation) {
        // Actualizar ubicación
        await updateLocationMutation.mutateAsync({
          id: editingLocation.id,
          data: locationData
        });
        locationId = editingLocation.id;
      } else {
        // Crear ubicación
        const result = await createLocationMutation.mutateAsync(locationData);
        locationId = result.data.id;
      }

      // Si es finca y tiene lotes, crear/actualizar lotes
      if (values.type === 'farm' && values.lots && values.lots.length > 0) {
        for (const lot of values.lots) {
          const lotData = {
            location_id: locationId,
            name: lot.name,
            area: lot.area || null,
            area_unit: lot.area_unit || 'hectares',
            description: lot.description || null,
            status: lot.status || 'active',
          };

          if (lot.id) {
            // Actualizar lote existente
            await farmLotsApi.update(lot.id, lotData);
          } else {
            // Crear nuevo lote
            await farmLotsApi.create(lotData);
          }
        }
      }

      message.success(editingLocation ? 'Ubicación actualizada exitosamente' : 'Ubicación creada exitosamente');
      setIsModalVisible(false);
      form.resetFields();
      setEditingLocation(null);
    } catch (error: any) {
      message.error(`Error: ${error.message}`);
    }
  };

  // Backend handles filtering, so no need to filter locally
  const filteredLocations = locations;

  const mobileColumns: ColumnsType<Location> = [
    {
      title: 'Ubicación',
      key: 'location',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: '14px', marginBottom: 4 }}>
            {getTypeIcon(record.type)}
            <span style={{ marginLeft: 8 }}>{record.name}</span>
          </div>
          <div style={{ fontSize: '12px', color: '#666' }}>
            <Tag color={getTypeColor(record.type)} size="small" icon={getTypeIcon(record.type)}>
              {getTypeLabel(record.type)}
            </Tag>
            <span style={{ marginLeft: 8 }}>{record.municipality}</span>
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
        <Badge
          status={status === 'active' ? 'success' : 'error'}
          text={status === 'active' ? 'Activo' : 'Inactivo'}
        />
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

  const desktopColumns: ColumnsType<Location> = [
    {
      title: 'Ubicación',
      key: 'location',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: 14, marginBottom: 4 }}>
            {getTypeIcon(record.type)}
            <span style={{ marginLeft: 8 }}>{record.name}</span>
          </div>
          <div style={{ color: '#666', fontSize: 12 }}>
            <EnvironmentOutlined style={{ marginRight: 4 }} />
            {record.address}, {record.municipality}
          </div>
        </div>
      ),
    },
    {
      title: 'Tipo',
      dataIndex: 'type',
      key: 'type',
      render: (type: string) => (
        <Tag color={getTypeColor(type)} icon={getTypeIcon(type)}>
          {getTypeLabel(type)}
        </Tag>
      ),
      filters: [
        { text: 'Finca', value: 'farm' },
        { text: 'Bodega', value: 'warehouse' },
      ],
      onFilter: (value, record) => record.type === value,
    },
    {
      title: 'Responsable',
      dataIndex: 'responsible_user',
      key: 'responsible_user',
      render: (user: { id: string; name: string; email: string } | undefined) => (
        <span style={{ color: '#2E7D32' }}>{user?.name || 'Sin asignar'}</span>
      ),
    },
    {
      title: 'Coordenadas',
      dataIndex: 'coordinates',
      key: 'coordinates',
      render: (coordinates: { lat: number; lng: number } | undefined) => (
        coordinates && typeof coordinates.lat === 'number' && typeof coordinates.lng === 'number' ? (
          <div style={{ fontSize: 12 }}>
            <div>Lat: {coordinates.lat.toFixed(4)}</div>
            <div>Lng: {coordinates.lng.toFixed(4)}</div>
          </div>
        ) : (
          <span style={{ color: '#999', fontSize: 12 }}>No definidas</span>
        )
      ),
    },
    {
      title: 'Estado',
      dataIndex: 'status',
      key: 'status',
      render: (status: string) => (
        <Badge
          status={status === 'active' ? 'success' : 'error'}
          text={status === 'active' ? 'Activo' : 'Inactivo'}
        />
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
            title="¿Está seguro de eliminar esta ubicación?"
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

  const expandedRowRender = (record: Location) => (
    <Descriptions size="small" column={1}>
      <Descriptions.Item label="Dirección">{record.address}</Descriptions.Item>
      <Descriptions.Item label="Responsable">{record.responsible_user?.name || 'Sin asignar'}</Descriptions.Item>
      {record.coordinates && typeof record.coordinates.lat === 'number' && typeof record.coordinates.lng === 'number' ? (
        <>
          <Descriptions.Item label="Latitud">{record.coordinates.lat.toFixed(6)}</Descriptions.Item>
          <Descriptions.Item label="Longitud">{record.coordinates.lng.toFixed(6)}</Descriptions.Item>
        </>
      ) : (
        <Descriptions.Item label="Coordenadas">No definidas</Descriptions.Item>
      )}
      <Descriptions.Item label="Fecha de creación">
        {record.created_at ? new Date(record.created_at).toLocaleDateString('es-CO') : 'N/A'}
      </Descriptions.Item>
    </Descriptions>
  );

  return (
    <div>
      <div style={{ marginBottom: 16, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div>
          <h1 style={{ margin: 0, color: '#2E7D32' }}>Fincas y Bodegas</h1>
          <p style={{ color: '#666', margin: 0 }}>
            Administra las ubicaciones físicas del sistema
          </p>
        </div>
        <Button
          type="primary"
          icon={<PlusOutlined />}
          onClick={() => {
            setEditingLocation(null);
            form.resetFields();
            setIsModalVisible(true);
          }}
        >
          Nueva Ubicación
        </Button>
      </div>

      <Card>
        <div style={{ marginBottom: 16 }}>
          <Space>
            <Search
              placeholder="Buscar ubicaciones..."
              allowClear
              style={{ width: 300 }}
              value={searchText}
              onChange={(e) => setSearchText(e.target.value)}
            />
            <Select
              placeholder="Filtrar por tipo"
              allowClear
              style={{ width: 150 }}
              value={typeFilter}
              onChange={setTypeFilter}
            >
              <Option value="farm">Finca</Option>
              <Option value="warehouse">Bodega</Option>
            </Select>
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
          dataSource={filteredLocations}
          rowKey="id"
          loading={locationsLoading || createLocationMutation.isPending || updateLocationMutation.isPending || deleteLocationMutation.isPending}
          expandedRowRender={expandedRowRender}
          entityName="ubicaciones"
          pagination={{
            total: filteredLocations.length,
            pageSize: 10,
          }}
        />
      </Card>

      <Modal
        title={editingLocation ? 'Editar Ubicación' : 'Nueva Ubicación'}
        open={isModalVisible}
        onCancel={() => {
          setIsModalVisible(false);
          form.resetFields();
          setEditingLocation(null);
        }}
        footer={null}
        width={700}
      >
        <Form
          form={form}
          layout="vertical"
          onFinish={handleSave}
        >
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item
                name="name"
                label="Nombre"
                rules={[{ required: true, message: 'El nombre es requerido' }]}
              >
                <Input placeholder="Ingrese el nombre de la ubicación" />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item
                name="type"
                label="Tipo"
                rules={[{ required: true, message: 'El tipo es requerido' }]}
              >
                <Select placeholder="Seleccione el tipo">
                  <Option value="farm">
                    <HomeOutlined style={{ marginRight: 8 }} />
                    Finca
                  </Option>
                  <Option value="warehouse">
                    <BankOutlined style={{ marginRight: 8 }} />
                    Bodega
                  </Option>
                </Select>
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col span={12}>
              <Form.Item
                name="municipality"
                label="Municipio"
                rules={[{ required: true, message: 'El municipio es requerido' }]}
              >
                <Input placeholder="Ingrese el municipio" />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item
                name="responsible_user_id"
                label="Responsable"
                rules={[{ required: true, message: 'El responsable es requerido' }]}
              >
                <Select
                  showSearch
                  placeholder="Seleccione el responsable"
                  optionFilterProp="children"
                  loading={usersLoading}
                  filterOption={(input, option) =>
                    (option?.label ?? '').toLowerCase().includes(input.toLowerCase())
                  }
                  options={users?.map((user: any) => ({
                    value: user.id,
                    label: user.name
                  }))}
                />
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col span={24}>
              <Form.Item
                name="address"
                label="Dirección"
                rules={[{ required: true, message: 'La dirección es requerida' }]}
              >
                <Input.TextArea
                  rows={2}
                  placeholder="Ingrese la dirección completa"
                />
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col span={8}>
              <Form.Item
                name={['coordinates', 'lat']}
                label="Latitud"
              >
                <Input
                  type="number"
                  step="0.0001"
                  placeholder="4.6097"
                />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item
                name={['coordinates', 'lng']}
                label="Longitud"
              >
                <Input
                  type="number"
                  step="0.0001"
                  placeholder="-74.0817"
                />
              </Form.Item>
            </Col>
            <Col span={8}>
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

          {/* Sección de Lotes - Solo visible si tipo es 'farm' */}
          <Form.Item
            noStyle
            shouldUpdate={(prevValues, currentValues) => prevValues.type !== currentValues.type}
          >
            {({ getFieldValue }) =>
              getFieldValue('type') === 'farm' ? (
                <>
                  <Divider>Lotes de Finca</Divider>
                  <Form.List name="lots">
                    {(fields, { add, remove }) => (
                      <>
                        {fields.map(({ key, name, ...restField }) => (
                          <Card key={key} size="small" style={{ marginBottom: 16, backgroundColor: '#f9f9f9' }}>
                            <Row gutter={16}>
                              <Col span={12}>
                                <Form.Item
                                  {...restField}
                                  name={[name, 'name']}
                                  label="Nombre del Lote"
                                  rules={[{ required: true, message: 'Nombre requerido' }]}
                                >
                                  <Input placeholder="Ej: Lote A" />
                                </Form.Item>
                              </Col>
                              <Col span={6}>
                                <Form.Item
                                  {...restField}
                                  name={[name, 'area']}
                                  label="Área"
                                >
                                  <InputNumber
                                    min={0}
                                    precision={2}
                                    style={{ width: '100%' }}
                                    placeholder="0.00"
                                  />
                                </Form.Item>
                              </Col>
                              <Col span={6}>
                                <Form.Item
                                  {...restField}
                                  name={[name, 'area_unit']}
                                  label="Unidad"
                                  initialValue="hectares"
                                >
                                  <Select>
                                    <Option value="hectares">Hectáreas</Option>
                                    <Option value="m2">m²</Option>
                                    <Option value="acres">Acres</Option>
                                  </Select>
                                </Form.Item>
                              </Col>
                            </Row>
                            <Row gutter={16}>
                              <Col span={20}>
                                <Form.Item
                                  {...restField}
                                  name={[name, 'description']}
                                  label="Descripción"
                                >
                                  <Input.TextArea rows={2} placeholder="Descripción del lote..." />
                                </Form.Item>
                              </Col>
                              <Col span={4} style={{ display: 'flex', alignItems: 'flex-end' }}>
                                <Form.Item>
                                  <Button
                                    danger
                                    icon={<MinusCircleOutlined />}
                                    onClick={() => remove(name)}
                                  >
                                    Eliminar
                                  </Button>
                                </Form.Item>
                              </Col>
                            </Row>
                            <Form.Item
                              {...restField}
                              name={[name, 'status']}
                              hidden
                              initialValue="active"
                            >
                              <Input />
                            </Form.Item>
                            <Form.Item
                              {...restField}
                              name={[name, 'id']}
                              hidden
                            >
                              <Input />
                            </Form.Item>
                          </Card>
                        ))}
                        <Form.Item>
                          <Button
                            type="dashed"
                            onClick={() => add()}
                            block
                            icon={<PlusCircleOutlined />}
                          >
                            Agregar Lote
                          </Button>
                        </Form.Item>
                      </>
                    )}
                  </Form.List>
                </>
              ) : null
            }
          </Form.Item>

          <div style={{ textAlign: 'right', marginTop: 24 }}>
            <Space>
              <Button onClick={() => {
                setIsModalVisible(false);
                form.resetFields();
                setEditingLocation(null);
              }}>
                Cancelar
              </Button>
              <Button type="primary" htmlType="submit">
                {editingLocation ? 'Actualizar' : 'Crear'}
              </Button>
            </Space>
          </div>
        </Form>
      </Modal>
    </div>
  );
};

export default Locations;