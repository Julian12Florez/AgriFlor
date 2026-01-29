import React, { useState, useEffect } from 'react';
import { Button, Input, Space, Card, Tag, Popconfirm, message, Modal, Form, Row, Col, Select, Tabs, InputNumber, Drawer, Descriptions } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined, ExperimentOutlined, MinusCircleOutlined, PlusCircleOutlined } from '@ant-design/icons';
import ResponsiveTable from '../../components/ResponsiveTable';
import type { ColumnsType } from 'antd/es/table';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { recipesApi, productsApi, brandsApi } from '../../services/api';
import type { Recipe, RecipeProduct } from '../../data/types';


const { Search } = Input;
const { Option } = Select;
const { TabPane } = Tabs;

const Recipes: React.FC = () => {
  const queryClient = useQueryClient();
  const [isMobile, setIsMobile] = useState(false);
  const [isModalVisible, setIsModalVisible] = useState(false);
  const [editingRecipe, setEditingRecipe] = useState<Recipe | null>(null);
  const [searchText, setSearchText] = useState('');
  const [categoryFilter, setCategoryFilter] = useState<string | undefined>();
  const [statusFilter, setStatusFilter] = useState<string | undefined>();
  const [form] = Form.useForm();

  // Fetch recipes from API
  const { data: recipesData, isLoading: recipesLoading } = useQuery({
    queryKey: ['recipes', searchText, categoryFilter, statusFilter],
    queryFn: () => recipesApi.list({
      search: searchText || undefined,
      category: categoryFilter || undefined,
      status: statusFilter || undefined
    }),
  });

  // Fetch products
  const { data: productsData } = useQuery({
    queryKey: ['products'],
    queryFn: () => productsApi.list(),
  });

  // Fetch brands
  const { data: brandsData } = useQuery({
    queryKey: ['brands'],
    queryFn: () => brandsApi.list(),
  });

  const recipes = recipesData?.data || [];
  const mockProducts = productsData?.data || [];
  const mockBrands = brandsData?.data || [];

  // Create recipe mutation
  const createRecipeMutation = useMutation({
    mutationFn: (data: any) => recipesApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['recipes'] });
      setIsModalVisible(false);
      form.resetFields();
      message.success('Receta creada correctamente');
    },
    onError: (error: any) => {
      message.error(`Error al crear receta: ${error.message}`);
    },
  });

  // Update recipe mutation
  const updateRecipeMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: any }) => recipesApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['recipes'] });
      setIsModalVisible(false);
      form.resetFields();
      setEditingRecipe(null);
      message.success('Receta actualizada correctamente');
    },
    onError: (error: any) => {
      message.error(`Error al actualizar receta: ${error.message}`);
    },
  });

  // Delete recipe mutation
  const deleteRecipeMutation = useMutation({
    mutationFn: (id: string) => recipesApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['recipes'] });
      message.success('Receta eliminada correctamente');
    },
    onError: (error: any) => {
      message.error(`Error al eliminar receta: ${error.message}`);
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

  // Mock data for dropdowns
  const categories = ['fertilization', 'pest_control', 'disease_control', 'weed_control', 'other'];

  const handleEdit = (record: Recipe) => {
    setEditingRecipe(record);
    form.setFieldsValue({
      name: record.name,
      description: record.description,
      category: record.category,
      status: record.status,
      products: record.products
    });
    setIsModalVisible(true);
  };

  const handleDelete = (id: string) => {
    deleteRecipeMutation.mutate(id);
  };

  const handleSave = (values: any) => {
    // Format data for backend API (snake_case)
    const recipeData = {
      name: values.name,
      description: values.description,
      category: values.category,
      status: values.status || 'active',
      products: (values.products || []).map((product: any) => ({
        product_id: product.productId,
        product_name: product.productName,
        brand_id: product.brandId,
        brand_name: product.brandName,
        quantity: product.quantity,
        unit: product.unit,
        observations: product.observations || undefined,
      })),
      application_instructions: values.applicationInstructions || undefined,
      safety_notes: values.safetyNotes || undefined,
    };

    if (editingRecipe) {
      updateRecipeMutation.mutate({ id: editingRecipe.id, data: recipeData });
    } else {
      createRecipeMutation.mutate(recipeData);
    }
  };

  // Backend handles filtering, so no need to filter locally
  const filteredRecipes = recipes;

  const mobileColumns: ColumnsType<Recipe> = [
    {
      title: 'Receta',
      key: 'recipe',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: '14px', marginBottom: 4 }}>
            <ExperimentOutlined style={{ marginRight: 8, color: '#4CAF50' }} />
            {record.name}
          </div>
          <div style={{ fontSize: '12px', color: '#666' }}>
            <Tag color="blue">{record.category ? record.category.charAt(0).toUpperCase() + record.category.slice(1) : 'Sin categoría'}</Tag>
            <span style={{ marginLeft: 8 }}>{record.products.length} producto{record.products.length !== 1 ? 's' : ''}</span>
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
        <Tag color={status === 'active' ? 'green' : 'red'}>
          {status === 'active' ? 'Activa' : 'Inactiva'}
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

  const desktopColumns: ColumnsType<Recipe> = [
    {
      title: 'Receta',
      key: 'recipe',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: 14, marginBottom: 4 }}>
            <ExperimentOutlined style={{ marginRight: 8, color: '#4CAF50' }} />
            {record.name}
          </div>
          <div style={{ color: '#666', fontSize: 12 }}>
            {record.description}
          </div>
        </div>
      ),
    },
    {
      title: 'Categoría',
      dataIndex: 'category',
      key: 'category',
      render: (category: string) => (
        <Tag color="blue">{category ? category.charAt(0).toUpperCase() + category.slice(1) : 'Sin categoría'}</Tag>
      ),
      filters: categories.map(cat => ({ text: cat ? cat.charAt(0).toUpperCase() + cat.slice(1) : cat, value: cat })),
      onFilter: (value, record) => record.category === value,
    },
    {
      title: 'Productos',
      dataIndex: 'products',
      key: 'products',
      render: (products: RecipeProduct[]) => (
        <div>
          {products.slice(0, 2).map(product => (
            <div key={product.id} style={{ fontSize: 12, marginBottom: 2 }}>
              <span style={{ fontWeight: 500 }}>{product.productName}</span>
              <span style={{ color: '#666' }}> - {product.quantity} {product.unit}</span>
            </div>
          ))}
          {products.length > 2 && (
            <span style={{ color: '#999', fontSize: 11 }}>
              +{products.length - 2} más
            </span>
          )}
        </div>
      ),
    },
    {
      title: 'Uso',
      key: 'usage',
      render: (_) => (
        <div>
          <div style={{ fontSize: 12 }}>
            <strong>0</strong> veces
          </div>
          <div style={{ color: '#666', fontSize: 11 }}>
            Disponible para usar
          </div>
        </div>
      ),
    },
    {
      title: 'Estado',
      dataIndex: 'status',
      key: 'status',
      render: (status: string) => (
        <Tag color={status === 'active' ? 'green' : 'red'}>
          {status === 'active' ? 'Activa' : 'Inactiva'}
        </Tag>
      ),
      filters: [
        { text: 'Activa', value: 'active' },
        { text: 'Inactiva', value: 'inactive' },
      ],
      onFilter: (value, record) => record.status === value,
    },
    {
      title: 'Acciones',
      key: 'actions',
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
            title="¿Está seguro de eliminar esta receta?"
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

  const renderFormContent = () => (
    <Form
      form={form}
      layout="vertical"
      onFinish={handleSave}
    >
      <Tabs defaultActiveKey="1" type={isMobile ? 'card' : 'line'}>
        <TabPane tab="Información General" key="1">
          <Row gutter={isMobile ? [0, 16] : 16}>
            <Col xs={24} sm={24} md={12}>
              <Form.Item
                name="name"
                label="Nombre de la Receta"
                rules={[{ required: true, message: 'El nombre es requerido' }]}
              >
                <Input placeholder="Ingrese el nombre de la receta" />
              </Form.Item>
            </Col>
            <Col xs={24} sm={24} md={12}>
              <Form.Item
                name="category"
                label="Categoría"
                rules={[{ required: true, message: 'La categoría es requerida' }]}
              >
                <Select placeholder="Seleccione una categoría">
                  {categories.map(cat => (
                    <Option key={cat} value={cat}>{cat}</Option>
                  ))}
                </Select>
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={isMobile ? [0, 16] : 16}>
            <Col xs={24} sm={18} md={18}>
              <Form.Item
                name="description"
                label="Descripción"
                rules={[{ required: true, message: 'La descripción es requerida' }]}
              >
                <Input.TextArea
                  rows={isMobile ? 2 : 3}
                  placeholder="Descripción detallada de la receta"
                />
              </Form.Item>
            </Col>
            <Col xs={24} sm={6} md={6}>
              <Form.Item
                name="status"
                label="Estado"
                rules={[{ required: true, message: 'El estado es requerido' }]}
              >
                <Select placeholder="Seleccione el estado">
                  <Option value="active">Activa</Option>
                  <Option value="inactive">Inactiva</Option>
                </Select>
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
                    {isMobile ? (
                      <div>
                        <Row gutter={[0, 12]}>
                          <Col span={24}>
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
                          <Col span={24}>
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
                          <Col span={12}>
                            <Form.Item
                              {...restField}
                              name={[name, 'quantity']}
                              label="Cantidad"
                              rules={[{ required: true, message: 'Ingrese cantidad' }]}
                            >
                              <InputNumber min={0} style={{ width: '100%' }} />
                            </Form.Item>
                          </Col>
                          <Col span={8}>
                            <Form.Item
                              {...restField}
                              name={[name, 'unit']}
                              label="Unidad"
                            >
                              <Input disabled />
                            </Form.Item>
                          </Col>
                          <Col span={4}>
                            <Form.Item label=" ">
                              <Button
                                type="text"
                                danger
                                icon={<MinusCircleOutlined />}
                                onClick={() => remove(name)}
                                size="small"
                              />
                            </Form.Item>
                          </Col>
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
                      </div>
                    ) : (
                      <div>
                        <Row gutter={16}>
                          <Col span={8}>
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
                          <Col span={6}>
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
                          <Col span={4}>
                            <Form.Item
                              {...restField}
                              name={[name, 'quantity']}
                              label="Cantidad"
                              rules={[{ required: true, message: 'Ingrese cantidad' }]}
                            >
                              <InputNumber min={0} style={{ width: '100%' }} />
                            </Form.Item>
                          </Col>
                          <Col span={4}>
                            <Form.Item
                              {...restField}
                              name={[name, 'unit']}
                              label="Unidad"
                            >
                              <Input disabled />
                            </Form.Item>
                          </Col>
                          <Col span={2}>
                            <Form.Item label=" ">
                              <Button
                                type="text"
                                danger
                                icon={<MinusCircleOutlined />}
                                onClick={() => remove(name)}
                              />
                            </Form.Item>
                          </Col>
                        </Row>
                        <Row>
                          <Col span={22}>
                            <Form.Item
                              {...restField}
                              name={[name, 'observations']}
                              label="Observaciones"
                            >
                              <Input placeholder="Observaciones para este producto" />
                            </Form.Item>
                          </Col>
                        </Row>
                      </div>
                    )}
                  </Card>
                ))}
              </>
            )}
          </Form.List>
        </TabPane>
      </Tabs>

      <div style={{ textAlign: 'right', marginTop: 24 }}>
        <Space direction={isMobile ? 'vertical' : 'horizontal'} style={{ width: isMobile ? '100%' : 'auto' }}>
          <Button
            onClick={() => {
              setIsModalVisible(false);
              form.resetFields();
              setEditingRecipe(null);
            }}
            style={{ width: isMobile ? '100%' : 'auto' }}
          >
            Cancelar
          </Button>
          <Button
            type="primary"
            htmlType="submit"
            style={{ width: isMobile ? '100%' : 'auto' }}
          >
            {editingRecipe ? 'Actualizar' : 'Crear'}
          </Button>
        </Space>
      </div>
    </Form>
  );


  return (
    <div>
      <div style={{ marginBottom: 16, display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ margin: 0, color: '#2E7D32' }}>Recetas Técnicas</h1>
          <p style={{ color: '#666', margin: 0 }}>
            Administra las fórmulas predefinidas para aplicaciones agrícolas
          </p>
        </div>
        <Button
          type="primary"
          icon={<PlusOutlined />}
          onClick={() => {
            setEditingRecipe(null);
            form.resetFields();
            setIsModalVisible(true);
          }}
        >
          Nueva Receta
        </Button>
      </div>

      <Card>
        <div style={{ marginBottom: 16 }}>
          <Row gutter={[16, 16]}>
            <Col xs={24} sm={12} md={8}>
              <Search
                placeholder="Buscar recetas..."
                allowClear
                value={searchText}
                onChange={(e) => setSearchText(e.target.value)}
              />
            </Col>
            <Col xs={24} sm={12} md={8}>
              <Select
                placeholder="Filtrar por categoría"
                allowClear
                style={{ width: '100%' }}
                value={categoryFilter}
                onChange={setCategoryFilter}
              >
                {categories.map(cat => (
                  <Option key={cat} value={cat}>{cat}</Option>
                ))}
              </Select>
            </Col>
            <Col xs={24} sm={12} md={8}>
              <Select
                placeholder="Filtrar por estado"
                allowClear
                style={{ width: '100%' }}
                value={statusFilter}
                onChange={setStatusFilter}
              >
                <Option value="active">Activa</Option>
                <Option value="inactive">Inactiva</Option>
              </Select>
            </Col>
          </Row>
        </div>

        <ResponsiveTable
          mobileColumns={mobileColumns}
          desktopColumns={desktopColumns}
          dataSource={filteredRecipes}
          rowKey="id"
          loading={recipesLoading || createRecipeMutation.isPending || updateRecipeMutation.isPending || deleteRecipeMutation.isPending}
          expandedRowRender={(record: Recipe) => (
            <Descriptions size="small" column={1}>
              <Descriptions.Item label="Descripción">{record.description}</Descriptions.Item>
              <Descriptions.Item label="Productos">
                <div style={{ marginTop: 4 }}>
                  {record.products.map((product, index) => (
                    <div key={index} style={{ fontSize: '12px', color: '#666', marginBottom: 2 }}>
                      • {product.productName} - {product.quantity} {product.unit}
                      {product.observations && (
                        <div style={{ marginLeft: 12, fontSize: '11px', color: '#999' }}>
                          {product.observations}
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              </Descriptions.Item>
              <Descriptions.Item label="Instrucciones de aplicación">
                {record.applicationInstructions || 'Seguir indicaciones del producto'}
              </Descriptions.Item>
              <Descriptions.Item label="Notas de seguridad">
                {record.safetyNotes || 'Usar equipos de protección personal'}
              </Descriptions.Item>
              <Descriptions.Item label="Creado por">{record.createdBy}</Descriptions.Item>
              <Descriptions.Item label="Fecha de creación">
                {new Date(record.createdAt).toLocaleDateString('es-CO')}
              </Descriptions.Item>
            </Descriptions>
          )}
          entityName="recetas"
          pagination={{
            total: filteredRecipes.length,
            pageSize: 10,
          }}
        />
      </Card>

      {isMobile ? (
        <Drawer
          title={editingRecipe ? 'Editar Receta' : 'Nueva Receta'}
          placement="bottom"
          height="90%"
          open={isModalVisible}
          onClose={() => {
            setIsModalVisible(false);
            form.resetFields();
            setEditingRecipe(null);
          }}
          styles={{
            body: { padding: 16 }
          }}
        >
          {renderFormContent()}
        </Drawer>
      ) : (
        <Modal
          title={editingRecipe ? 'Editar Receta' : 'Nueva Receta'}
          open={isModalVisible}
          onCancel={() => {
            setIsModalVisible(false);
            form.resetFields();
            setEditingRecipe(null);
          }}
          footer={null}
          width={900}
        >
          {renderFormContent()}
        </Modal>
      )}
    </div>
  );
};

export default Recipes;