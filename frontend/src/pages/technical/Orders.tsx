import React, { useState, useEffect, useRef } from 'react';
import { Button, Input, Space, Card, Tag, Popconfirm, message, Modal, Form, Row, Col, Select, DatePicker, Tabs, Steps, InputNumber, Drawer, Descriptions } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined, CalendarOutlined, EnvironmentOutlined, ExperimentOutlined, CheckCircleOutlined, ClockCircleOutlined, FileTextOutlined, PlusCircleOutlined, MinusCircleOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import ResponsiveTable from '../../components/ResponsiveTable';
import dayjs from 'dayjs';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { ordersApi, recipesApi, productsApi, brandsApi, locationsApi, handleApiError } from '../../services/api';
import type { TechnicalOrder } from '../../data/types';

// Interfaces importadas desde types.ts

const { Search } = Input;
const { Option } = Select;
const { TabPane } = Tabs;
const { Step } = Steps;

const Orders: React.FC = () => {
  const queryClient = useQueryClient();
  const [isMobile, setIsMobile] = useState(false);
  const [isModalVisible, setIsModalVisible] = useState(false);
  const [editingOrder, setEditingOrder] = useState<TechnicalOrder | null>(null);
  const [searchText, setSearchText] = useState('');
  const [statusFilter, setStatusFilter] = useState<string | undefined>();
  const [form] = Form.useForm();

  // Ref to prevent double submissions (synchronous check)
  const isSubmittingRef = useRef(false);

  // Fetch orders from API
  const { data: ordersData, isLoading: ordersLoading } = useQuery({
    queryKey: ['orders', searchText, statusFilter],
    queryFn: () => ordersApi.list({
      search: searchText || undefined,
      status: statusFilter || undefined
    }),
  });

  // Fetch recipes
  const { data: recipesData } = useQuery({
    queryKey: ['recipes'],
    queryFn: () => recipesApi.list(),
  });

  // Fetch products (per_page alto para traer todos)
  const { data: productsData } = useQuery({
    queryKey: ['products', 'all-for-select'],
    queryFn: () => productsApi.list({ per_page: 9999 }),
  });

  // Fetch brands
  const { data: brandsData } = useQuery({
    queryKey: ['brands', 'all-for-select'],
    queryFn: () => brandsApi.list({ per_page: 9999 }),
  });

  // Fetch locations
  const { data: locationsData } = useQuery({
    queryKey: ['locations'],
    queryFn: () => locationsApi.list({ per_page: 999 }),
  });

  const orders = ordersData?.data || [];
  const mockRecipes = recipesData?.data || [];
  const mockProducts = productsData?.data || [];
  const mockBrands = brandsData?.data || [];
  const mockLocations = locationsData?.data || [];

  // Create order mutation
  const createOrderMutation = useMutation({
    mutationFn: (data: any) => ordersApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['orders'] });
      setIsModalVisible(false);
      form.resetFields();
      message.success('Orden técnica creada correctamente');
    },
    onError: (error: any) => {
      handleApiError(error, 'Error al crear orden técnica', form);
    },
  });

  // Update order mutation
  const updateOrderMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: any }) => ordersApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['orders'] });
      setIsModalVisible(false);
      form.resetFields();
      setEditingOrder(null);
      message.success('Orden técnica actualizada correctamente');
    },
    onError: (error: any) => {
      handleApiError(error, 'Error al actualizar orden técnica', form);
    },
  });

  // Delete order mutation
  const deleteOrderMutation = useMutation({
    mutationFn: (id: string) => ordersApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['orders'] });
      message.success('Orden técnica eliminada correctamente');
    },
    onError: (error: any) => {
      handleApiError(error, 'Error al eliminar orden técnica');
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

  // Mock data
  const farms = mockLocations.filter(location => location.type === 'farm');
  const agronomists = [
    'Dr. Luis Martínez',
    'Ing. Ana García',
    'Ing. Carlos Rodríguez'
  ];

  const getStatusColor = (status: string) => {
    const colors = {
      draft: 'orange',
      approved: 'blue',
      applied: 'green'
    };
    return colors[status as keyof typeof colors] || 'default';
  };

  const getStatusText = (status: string) => {
    const texts = {
      draft: 'Borrador',
      approved: 'Aprobada',
      applied: 'Aplicada'
    };
    return texts[status as keyof typeof texts] || status;
  };

  const getStatusIcon = (status: string) => {
    const icons = {
      draft: <FileTextOutlined />,
      approved: <ClockCircleOutlined />,
      applied: <CheckCircleOutlined />
    };
    return icons[status as keyof typeof icons] || <FileTextOutlined />;
  };

  const getCurrentStep = (status: string) => {
    const steps = {
      draft: 0,
      approved: 1,
      applied: 2
    };
    return steps[status as keyof typeof steps] || 0;
  };

  const handleEdit = (record: TechnicalOrder) => {
    setEditingOrder(record);
    form.setFieldsValue({
      orderNumber: record.orderNumber,
      scheduledDate: dayjs(record.scheduledDate),
      farmIds: record.farmIds,
      recipeId: record.recipeId,
      responsibleAgronomist: record.responsibleAgronomist,
      observations: record.observations,
      status: record.status,
      products: record.products
    });
    setIsModalVisible(true);
  };

  const handleDelete = (id: string) => {
    deleteOrderMutation.mutate(id);
  };

  const handleRecipeChange = (recipeId: string) => {
    const selectedRecipe = mockRecipes.find(recipe => recipe.id === recipeId);
    if (selectedRecipe && selectedRecipe.products) {
      // Precargar productos de la receta seleccionada
      const preloadedProducts = selectedRecipe.products.map((recipeProduct: any) => ({
        productId: recipeProduct.productId,
        productName: recipeProduct.productName,
        brandId: recipeProduct.brandId,
        brandName: recipeProduct.brandName,
        quantity: recipeProduct.quantity,
        unit: recipeProduct.unit,
        observations: recipeProduct.observations || ''
      }));

      form.setFieldsValue({
        products: preloadedProducts
      });

      message.info(`Productos cargados desde la receta "${selectedRecipe.name}". Puedes modificar cantidades y agregar más productos.`);
    }
  };

  const handleSave = (values: any) => {
    // Prevent multiple submissions using ref (synchronous check)
    if (isSubmittingRef.current) {
      return;
    }
    isSubmittingRef.current = true;

    // Format data for backend API (snake_case)
    const orderData = {
      scheduled_date: values.scheduledDate.toISOString(),
      farm_ids: values.farmIds,
      recipe_id: values.recipeId || undefined,
      responsible_agronomist: values.responsibleAgronomist,
      observations: values.observations || undefined,
      status: values.status || 'draft',
      products: (values.products || []).map((product: any) => ({
        product_id: product.productId,
        product_name: product.productName,
        brand_id: product.brandId,
        brand_name: product.brandName,
        quantity: product.quantity,
        unit: product.unit,
        dosage: product.dosage || undefined,
        application_method: product.applicationMethod || undefined,
        observations: product.observations || undefined,
      })),
    };

    const mutationOptions = {
      onSettled: () => {
        isSubmittingRef.current = false;
      }
    };

    if (editingOrder) {
      updateOrderMutation.mutate({ id: editingOrder.id, data: orderData }, mutationOptions);
    } else {
      createOrderMutation.mutate(orderData, mutationOptions);
    }
  };

  // Backend handles filtering, so no need to filter locally
  const filteredOrders = orders;

  const mobileColumns: ColumnsType<TechnicalOrder> = [
    {
      title: 'Orden Técnica',
      key: 'order',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: '14px', marginBottom: 4 }}>
            <ExperimentOutlined style={{ marginRight: 8, color: '#4CAF50' }} />
            {record.orderNumber}
          </div>
          <div style={{ fontSize: '12px', color: '#666' }}>
            <Tag color={getStatusColor(record.status)} icon={getStatusIcon(record.status)}>
              {getStatusText(record.status)}
            </Tag>
            <span style={{ marginLeft: 8 }}>{new Date(record.scheduledDate).toLocaleDateString('es-CO')}</span>
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

  const desktopColumns: ColumnsType<TechnicalOrder> = [
    {
      title: 'Orden',
      key: 'order',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: 14, marginBottom: 4 }}>
            <ExperimentOutlined style={{ marginRight: 8, color: '#4CAF50' }} />
            {record.orderNumber}
          </div>
          <div style={{ color: '#666', fontSize: 12 }}>
            <CalendarOutlined style={{ marginRight: 4 }} />
            {new Date(record.scheduledDate).toLocaleDateString('es-CO')}
          </div>
        </div>
      ),
    },
    {
      title: 'Fincas',
      // El backend (TechnicalOrderResource) envía "farms" (objetos {id,name,...}), nunca
      // "farmNames"; ese campo era del mock. Hoy no hay órdenes técnicas en producción, así que
      // esto no se había manifestado, pero con la primera orden real `farms` puede venir ausente
      // (relación no cargada) o vacío, de ahí la guarda.
      dataIndex: 'farms',
      key: 'farms',
      render: (farms: TechnicalOrder['farms']) => {
        const farmList = farms || [];
        return (
          <div>
            {farmList.slice(0, 2).map((farm, index) => (
              <div key={farm.id ?? index} style={{ fontSize: 12, marginBottom: 2 }}>
                <EnvironmentOutlined style={{ marginRight: 4, color: '#2196F3' }} />
                {farm.name}
              </div>
            ))}
            {farmList.length > 2 && (
              <div style={{ color: '#999', fontSize: 12 }}>
                +{farmList.length - 2} más...
              </div>
            )}
            {farmList.length === 0 && (
              <div style={{ color: '#999', fontSize: 12 }}>Sin fincas asignadas</div>
            )}
          </div>
        );
      },
    },
    {
      title: 'Receta/Productos',
      key: 'recipe',
      render: (_, record) => (
        <div>
          {record.recipeName ? (
            <div style={{ fontSize: 12, fontWeight: 500, marginBottom: 4 }}>
              📋 {record.recipeName}
            </div>
          ) : (
            <div style={{ color: '#999', fontSize: 12, marginBottom: 4 }}>
              Sin receta base
            </div>
          )}
          <div style={{ fontSize: 11, color: '#666' }}>
            {record.products.length} producto(s)
          </div>
        </div>
      ),
    },
    {
      title: 'Responsable',
      dataIndex: 'responsibleAgronomist',
      key: 'responsibleAgronomist',
      render: (text: string) => (
        <span style={{ color: '#2E7D32', fontSize: 12 }}>{text}</span>
      ),
    },
    {
      title: 'Estado',
      dataIndex: 'status',
      key: 'status',
      render: (status: string) => (
        <Tag color={getStatusColor(status)} icon={getStatusIcon(status)}>
          {getStatusText(status)}
        </Tag>
      ),
      filters: [
        { text: 'Borrador', value: 'draft' },
        { text: 'Aprobada', value: 'approved' },
        { text: 'Aplicada', value: 'applied' },
      ],
      onFilter: (value, record) => record.status === value,
    },
    {
      title: 'Costo Est.',
      dataIndex: 'estimatedCost',
      key: 'estimatedCost',
      render: (cost: number) => (
        <span style={{ fontWeight: 500 }}>
          ${cost.toLocaleString('es-CO')}
        </span>
      ),
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
            title="¿Está seguro de eliminar esta orden?"
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

  const expandedRowRender = (record: TechnicalOrder) => (
    <Descriptions size="small" column={1}>
      <Descriptions.Item label="Número de orden">{record.orderNumber}</Descriptions.Item>
      <Descriptions.Item label="Fecha programada">
        {new Date(record.scheduledDate).toLocaleDateString('es-CO')}
      </Descriptions.Item>
      <Descriptions.Item label="Fincas asignadas">
        {record.farms && record.farms.length > 0 ? (
          record.farms.map((farm, index) => (
            <div key={farm.id ?? index} style={{ marginBottom: 4 }}>
              <EnvironmentOutlined style={{ marginRight: 4, color: '#2196F3' }} />
              {farm.name}
            </div>
          ))
        ) : (
          <span style={{ color: '#999' }}>Sin fincas asignadas</span>
        )}
      </Descriptions.Item>
      <Descriptions.Item label="Receta base">
        {record.recipeName || 'Sin receta base'}
      </Descriptions.Item>
      <Descriptions.Item label="Responsable agrónomo">{record.responsibleAgronomist}</Descriptions.Item>
      <Descriptions.Item label="Productos a aplicar">
        {record.products.map((product, index) => (
          <div key={index} style={{ marginBottom: 4 }}>
            <strong>{product.productName}</strong> - {product.brandName}
            <br />
            <span style={{ color: '#666', fontSize: 12 }}>
              Dosis: {(product as any).dosage} {product.unit} | Cantidad: {product.quantity} {product.unit}
              {(product as any).applicationMethod && ` | Método: ${(product as any).applicationMethod}`}
            </span>
          </div>
        ))}
      </Descriptions.Item>
      <Descriptions.Item label="Costo estimado">
        ${record.estimatedCost.toLocaleString('es-CO')}
      </Descriptions.Item>
      {record.observations && (
        <Descriptions.Item label="Observaciones">{record.observations}</Descriptions.Item>
      )}
    </Descriptions>
  );

  const renderFormContent = () => (
    <Form
      form={form}
      layout="vertical"
      onFinish={handleSave}
    >
      {editingOrder && (
        <div style={{ marginBottom: 24 }}>
          <Steps current={getCurrentStep(editingOrder.status)} size="small">
            <Step title="Borrador" icon={<FileTextOutlined />} />
            <Step title="Aprobada" icon={<ClockCircleOutlined />} />
            <Step title="Aplicada" icon={<CheckCircleOutlined />} />
          </Steps>
        </div>
      )}

      <Tabs defaultActiveKey="1">
        <TabPane tab="Información General" key="1">
          <Row gutter={isMobile ? [0, 16] : 16}>
            <Col xs={24} sm={24} md={8}>
              <Form.Item
                name="scheduledDate"
                label="Fecha Programada"
                rules={[{ required: true, message: 'La fecha es requerida' }]}
              >
                <DatePicker style={{ width: '100%' }} />
              </Form.Item>
            </Col>
            <Col xs={24} sm={24} md={8}>
              <Form.Item
                name="responsibleAgronomist"
                label="Agrónomo Responsable"
                rules={[{ required: true, message: 'El responsable es requerido' }]}
              >
                <Select placeholder="Seleccione el agrónomo">
                  {agronomists.map(agro => (
                    <Option key={agro} value={agro}>{agro}</Option>
                  ))}
                </Select>
              </Form.Item>
            </Col>
            <Col xs={24} sm={24} md={8}>
              <Form.Item
                name="status"
                label="Estado"
                rules={[{ required: true, message: 'El estado es requerido' }]}
              >
                <Select placeholder="Seleccione el estado">
                  <Option value="draft">Borrador</Option>
                  <Option value="approved">Aprobada</Option>
                  <Option value="applied">Aplicada</Option>
                </Select>
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={isMobile ? [0, 16] : 16}>
            <Col xs={24} sm={24} md={12}>
              <Form.Item
                name="farmIds"
                label="Fincas de Aplicación"
                rules={[{ required: true, message: 'Seleccione al menos una finca' }]}
              >
                <Select
                  mode="multiple"
                  placeholder="Seleccione las fincas"
                >
                  {farms.map(farm => (
                    <Option key={farm.id} value={farm.id}>{farm.name}</Option>
                  ))}
                </Select>
              </Form.Item>
            </Col>
            <Col xs={24} sm={24} md={12}>
              <Form.Item
                name="recipeId"
                label="Receta Base (Opcional)"
              >
                <Select
                  placeholder="Seleccione una receta"
                  allowClear
                  onChange={handleRecipeChange}
                >
                  {mockRecipes.map(recipe => (
                    <Option key={recipe.id} value={recipe.id}>
                      {recipe.name} ({recipe.products.length} productos)
                    </Option>
                  ))}
                </Select>
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col span={24}>
              <Form.Item
                name="observations"
                label="Observaciones"
              >
                <Input.TextArea
                  rows={3}
                  placeholder="Observaciones especiales para la aplicación"
                />
              </Form.Item>
            </Col>
          </Row>
        </TabPane>

        <TabPane tab="Productos" key="2">
          <Form.List name="products">
            {(fields, { add, remove }) => (
              <>
                <div style={{ marginBottom: 16 }}>
                  <Button
                    type="dashed"
                    onClick={() => add()}
                    icon={<PlusCircleOutlined />}
                    block
                  >
                    Agregar Producto
                  </Button>
                </div>
                {fields.map(({ key, name, ...restField }) => (
                  <Card key={key} size="small" style={{ marginBottom: 16 }}>
                    <Row gutter={isMobile ? [0, 8] : 16}>
                      <Col xs={24} sm={24} md={8}>
                        <Form.Item
                          {...restField}
                          name={[name, 'productId']}
                          label="Producto"
                          rules={[{ required: true, message: 'Seleccione un producto' }]}
                        >
                          <Select
                            placeholder="Seleccione producto"
                            onChange={(value) => {
                              const product = mockProducts.find(p => p.id === value);
                              if (product) {
                                form.setFieldValue(['products', name, 'productName'], product.name);
                                form.setFieldValue(['products', name, 'unit'], product.unit);
                              }
                            }}
                          >
                            {mockProducts.map(product => (
                              <Option key={product.id} value={product.id}>
                                {product.name}
                              </Option>
                            ))}
                          </Select>
                        </Form.Item>
                      </Col>
                      <Col xs={24} sm={12} md={6}>
                        <Form.Item
                          {...restField}
                          name={[name, 'brandId']}
                          label="Marca"
                          rules={[{ required: true, message: 'Seleccione una marca' }]}
                        >
                          <Select
                            placeholder="Marca"
                            onChange={(value) => {
                              const brand = mockBrands.find(b => b.id === value);
                              if (brand) {
                                form.setFieldValue(['products', name, 'brandName'], brand.name);
                              }
                            }}
                          >
                            {mockBrands.map(brand => (
                              <Option key={brand.id} value={brand.id}>
                                {brand.name}
                              </Option>
                            ))}
                          </Select>
                        </Form.Item>
                      </Col>
                      <Col xs={12} sm={6} md={4}>
                        <Form.Item
                          {...restField}
                          name={[name, 'quantity']}
                          label="Cantidad"
                          rules={[{ required: true, message: 'Ingrese cantidad' }]}
                        >
                          <InputNumber min={0} style={{ width: '100%' }} />
                        </Form.Item>
                      </Col>
                      <Col xs={8} sm={4} md={4}>
                        <Form.Item
                          {...restField}
                          name={[name, 'unit']}
                          label="Unidad"
                        >
                          <Input disabled />
                        </Form.Item>
                      </Col>
                      <Col xs={4} sm={2} md={2}>
                        <Form.Item label={isMobile ? "" : " "}>
                          <Button
                            type="text"
                            danger
                            icon={<MinusCircleOutlined />}
                            onClick={() => remove(name)}
                            style={{ width: '100%' }}
                          />
                        </Form.Item>
                      </Col>
                    </Row>
                    <Row>
                      <Col span={24}>
                        <Form.Item
                          {...restField}
                          name={[name, 'observations']}
                          label="Observaciones"
                        >
                          <Input placeholder="Observaciones para este producto" />
                        </Form.Item>
                      </Col>
                    </Row>
                  </Card>
                ))}
              </>
            )}
          </Form.List>
        </TabPane>
      </Tabs>

      <div style={{ textAlign: 'right', marginTop: 24 }}>
        <Space>
          <Button onClick={() => {
            setIsModalVisible(false);
            form.resetFields();
            setEditingOrder(null);
          }}>
            Cancelar
          </Button>
          <Button
            type="primary"
            htmlType="submit"
            loading={createOrderMutation.isPending || updateOrderMutation.isPending}
            disabled={createOrderMutation.isPending || updateOrderMutation.isPending}
          >
            {editingOrder ? 'Actualizar' : 'Crear'}
          </Button>
        </Space>
      </div>
    </Form>
  );

  return (
    <div>
      <div style={{ marginBottom: 16, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div>
          <h1 style={{ margin: 0, color: '#2E7D32' }}>Órdenes Técnicas</h1>
          <p style={{ color: '#666', margin: 0 }}>
            Programa y gestiona las aplicaciones agronómicas
          </p>
        </div>
        <Button
          type="primary"
          icon={<PlusOutlined />}
          onClick={() => {
            setEditingOrder(null);
            form.resetFields();
            setIsModalVisible(true);
          }}
        >
          Nueva Orden
        </Button>
      </div>

      <Card>
        <div style={{ marginBottom: 16 }}>
          <Space>
            <Search
              placeholder="Buscar órdenes..."
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
              <Option value="draft">Borrador</Option>
              <Option value="approved">Aprobada</Option>
              <Option value="applied">Aplicada</Option>
            </Select>
          </Space>
        </div>

        <ResponsiveTable
          mobileColumns={mobileColumns}
          desktopColumns={desktopColumns}
          dataSource={filteredOrders}
          rowKey="id"
          loading={ordersLoading || createOrderMutation.isPending || updateOrderMutation.isPending || deleteOrderMutation.isPending}
          expandedRowRender={expandedRowRender}
          entityName="órdenes"
          pagination={{
            total: filteredOrders.length,
            pageSize: 10,
          }}
        />
      </Card>

      {/* Responsive Form Container */}
      {isMobile ? (
        <Drawer
          title={editingOrder ? 'Editar Orden Técnica' : 'Nueva Orden Técnica'}
          placement="bottom"
          onClose={() => {
            setIsModalVisible(false);
            form.resetFields();
            setEditingOrder(null);
          }}
          open={isModalVisible}
          height="90%"
          styles={{
            body: { padding: '16px' }
          }}
        >
          {renderFormContent()}
        </Drawer>
      ) : (
        <Modal
          title={editingOrder ? 'Editar Orden Técnica' : 'Nueva Orden Técnica'}
          open={isModalVisible}
          onCancel={() => {
            setIsModalVisible(false);
            form.resetFields();
            setEditingOrder(null);
          }}
          footer={null}
          width={1000}
          centered
        >
          {renderFormContent()}
        </Modal>
      )}
    </div>
  );
};

export default Orders;