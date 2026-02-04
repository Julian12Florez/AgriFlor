import React, { useState, useEffect, useRef } from 'react';
import { Button, Input, Select, Space, Card, Tag, Popconfirm, message, Modal, Form, Row, Col, Typography, Descriptions, InputNumber } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { productsApi, brandsApi, packagingUnitsApi, baseUnitsApi } from '../../services/api';
import type { Product, Brand } from '../../data/types';
import ResponsiveTable from '../../components/ResponsiveTable';

const { Text, Title } = Typography;

const { Search } = Input;
const { Option } = Select;

const Products: React.FC = () => {
  const queryClient = useQueryClient();
  const [isMobile, setIsMobile] = useState(false);
  const [isModalVisible, setIsModalVisible] = useState(false);
  const [editingProduct, setEditingProduct] = useState<any | null>(null);
  const [form] = Form.useForm();
  const [searchText, setSearchText] = useState('');
  const [categoryFilter, setCategoryFilter] = useState<string | undefined>();
  const [statusFilter, setStatusFilter] = useState<string | undefined>();

  // Ref to prevent double submissions (synchronous check)
  const isSubmittingRef = useRef(false);

  // Fetch products from API
  const { data: productsData, isLoading: productsLoading } = useQuery({
    queryKey: ['products', searchText, categoryFilter, statusFilter],
    queryFn: () => productsApi.list({
      search: searchText || undefined,
      category: categoryFilter || undefined,
      status: statusFilter || undefined
    }),
  });

  // Fetch brands
  const { data: brandsData } = useQuery({
    queryKey: ['brands'],
    queryFn: () => brandsApi.list(),
  });

  // Fetch packaging units
  const { data: packagingUnitsData } = useQuery({
    queryKey: ['packagingUnits'],
    queryFn: () => packagingUnitsApi.list(),
  });

  // Fetch base units
  const { data: baseUnitsData } = useQuery({
    queryKey: ['baseUnits'],
    queryFn: () => baseUnitsApi.list(),
  });

  const products = productsData?.data || [];
  const brands = brandsData?.data || [];
  const packagingUnits = packagingUnitsData?.data || [];
  const baseUnits = baseUnitsData?.data || [];

  // Create product mutation
  const createProductMutation = useMutation({
    mutationFn: (data: any) => productsApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['products'] });
      setIsModalVisible(false);
      form.resetFields();
      message.success('Producto creado exitosamente');
    },
    onError: (error: any) => {
      // Handle backend validation errors
      if (error.response?.data?.errors) {
        const backendErrors = error.response.data.errors;
        // Show first error message
        const firstError = Object.values(backendErrors)[0];
        message.error(Array.isArray(firstError) ? firstError[0] : firstError);

        // Set form field errors
        const formErrors = Object.keys(backendErrors).map(key => ({
          name: key === 'active_ingredient' ? 'activeIngredient' :
                key === 'brand_id' ? 'brandId' :
                key === 'base_unit' ? 'baseUnit' :
                key === 'min_stock' ? 'minStock' :
                key === 'product_code' ? 'productCode' : key,
          errors: backendErrors[key],
        }));
        form.setFields(formErrors);
      } else {
        message.error(error.response?.data?.message || 'Error al crear producto');
      }
    },
  });

  // Update product mutation
  const updateProductMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: any }) => productsApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['products'] });
      setIsModalVisible(false);
      form.resetFields();
      setEditingProduct(null);
      message.success('Producto actualizado exitosamente');
    },
    onError: (error: any) => {
      // Handle backend validation errors
      if (error.response?.data?.errors) {
        const backendErrors = error.response.data.errors;
        // Show first error message
        const firstError = Object.values(backendErrors)[0];
        message.error(Array.isArray(firstError) ? firstError[0] : firstError);

        // Set form field errors
        const formErrors = Object.keys(backendErrors).map(key => ({
          name: key === 'active_ingredient' ? 'activeIngredient' :
                key === 'brand_id' ? 'brandId' :
                key === 'base_unit' ? 'baseUnit' :
                key === 'min_stock' ? 'minStock' :
                key === 'product_code' ? 'productCode' : key,
          errors: backendErrors[key],
        }));
        form.setFields(formErrors);
      } else {
        message.error(error.response?.data?.message || 'Error al actualizar producto');
      }
    },
  });

  // Delete product mutation
  const deleteProductMutation = useMutation({
    mutationFn: (id: string) => productsApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['products'] });
      message.success('Producto eliminado exitosamente');
    },
    onError: (error: any) => {
      message.error(`Error al eliminar producto: ${error.message}`);
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

  const getCategoryColor = (category: string) => {
    const colors = {
      fertilizante: 'green',
      pesticida: 'orange',
      herbicida: 'blue',
      fungicida: 'purple'
    };
    return colors[category as keyof typeof colors] || 'default';
  };

  const handleEdit = (record: any) => {
    setEditingProduct(record);
    form.setFieldsValue({
      name: record.name,
      productCode: record.productCode || record.product_code,
      category: record.category,
      baseUnit: record.base_unit,
      activeIngredient: record.active_ingredient,
      brandId: record.brand_id || (typeof record.brand === 'object' ? record.brand.id : null),
      minStock: record.min_stock,
      iva: record.iva ?? 19,
      description: record.description,
      status: record.status,
      packagingUnitIds: record.packagingUnits ? record.packagingUnits.map((pu: any) => pu.id) : []
    });
    setIsModalVisible(true);
  };

  const handleDelete = (id: string) => {
    deleteProductMutation.mutate(id);
  };

  const handleSave = (values: any) => {
    // Prevent multiple submissions using ref (synchronous check)
    if (isSubmittingRef.current) {
      return;
    }
    isSubmittingRef.current = true;

    // Format data for backend API (snake_case)
    const productData = {
      name: values.name,
      product_code: values.productCode || undefined,
      category: values.category,
      base_unit: values.baseUnit,
      active_ingredient: values.activeIngredient,
      brand_id: values.brandId,
      min_stock: values.minStock || undefined,
      iva: values.iva ?? 19,
      description: values.description || undefined,
      status: values.status || 'active',
      packaging_unit_ids: values.packagingUnitIds || [],
    };

    const mutationOptions = {
      onSettled: () => {
        isSubmittingRef.current = false;
      }
    };

    if (editingProduct) {
      updateProductMutation.mutate({ id: editingProduct.id, data: productData }, mutationOptions);
    } else {
      createProductMutation.mutate(productData, mutationOptions);
    }
  };

  // Backend handles filtering, so no need to filter locally
  const filteredProducts = products;

  const mobileColumns: ColumnsType<Product> = [
    {
      title: 'Producto',
      key: 'product',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: '14px', marginBottom: 4 }}>
            {record.name}
          </div>
          <div style={{ fontSize: '12px', color: '#666' }}>
            {record.category && (
              <Tag color={getCategoryColor(record.category)} size="small">
                {record.category.toUpperCase()}
              </Tag>
            )}
            {record.brand && <Tag color="blue" size="small">{record.brand}</Tag>}
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

  const desktopColumns: ColumnsType<Product> = [
    {
      title: 'Nombre',
      dataIndex: 'name',
      key: 'name',
      sorter: (a, b) => a.name.localeCompare(b.name),
    },
    {
      title: 'Categoría',
      dataIndex: 'category',
      key: 'category',
      render: (category: string) => (
        category ? (
          <Tag color={getCategoryColor(category)}>
            {category.toUpperCase()}
          </Tag>
        ) : (
          <Tag>Sin categoría</Tag>
        )
      ),
      filters: [
        { text: 'Fertilizante', value: 'fertilizante' },
        { text: 'Pesticida', value: 'pesticida' },
        { text: 'Herbicida', value: 'herbicida' },
        { text: 'Fungicida', value: 'fungicida' },
      ],
      onFilter: (value, record) => record.category === value,
    },
    {
      title: 'Unidad Base',
      dataIndex: 'base_unit',
      key: 'base_unit',
      render: (base_unit: string) => base_unit || 'N/A',
    },
    {
      title: 'Marca',
      dataIndex: 'brand',
      key: 'brand',
      render: (brand: any) => (
        brand ? (
          <Tag color="blue">{typeof brand === 'string' ? brand : brand.name}</Tag>
        ) : (
          <Tag>Sin marca</Tag>
        )
      ),
    },
    {
      title: 'Stock Mínimo',
      dataIndex: 'min_stock',
      key: 'min_stock',
      render: (min_stock: number) => min_stock || 'N/A',
      sorter: (a, b) => (a.min_stock || 0) - (b.min_stock || 0),
    },
    {
      title: 'IVA',
      dataIndex: 'iva',
      key: 'iva',
      render: (iva: number) => `${iva ?? 19}%`,
      sorter: (a, b) => (a.iva || 0) - (b.iva || 0),
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
            title="¿Está seguro de eliminar este producto?"
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

  const expandedRowRender = (record: any) => (
    <Descriptions size="small" column={1}>
      <Descriptions.Item label="Unidad Base">{record.base_unit || 'N/A'}</Descriptions.Item>
      <Descriptions.Item label="Categoría">
        {record.category ? (
          <Tag color={getCategoryColor(record.category)}>
            {record.category.toUpperCase()}
          </Tag>
        ) : 'N/A'}
      </Descriptions.Item>
      <Descriptions.Item label="Marca">
        {record.brand ? (typeof record.brand === 'string' ? record.brand : record.brand.name) : 'N/A'}
      </Descriptions.Item>
      <Descriptions.Item label="Principio Activo">
        {record.active_ingredient || 'N/A'}
      </Descriptions.Item>
      <Descriptions.Item label="Stock Mínimo">
        {record.min_stock || 'N/A'}
      </Descriptions.Item>
      <Descriptions.Item label="IVA">
        {record.iva ?? 19}%
      </Descriptions.Item>
      {record.description && (
        <Descriptions.Item label="Descripción">{record.description}</Descriptions.Item>
      )}
      <Descriptions.Item label="Fecha de creación">
        {record.created_at ? new Date(record.created_at).toLocaleDateString('es-CO') : 'N/A'}
      </Descriptions.Item>
    </Descriptions>
  );


  return (
    <div>
      <div style={{ marginBottom: 16, display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ margin: 0, color: '#2E7D32' }}>Gestión de Productos</h1>
          <p style={{ color: '#666', margin: 0 }}>
            Administra los productos químicos agrícolas
          </p>
        </div>
        <Button
          type="primary"
          icon={<PlusOutlined />}
          onClick={() => {
            setEditingProduct(null);
            form.resetFields();
            setIsModalVisible(true);
          }}
        >
          Nuevo Producto
        </Button>
      </div>

      <Card>
        <div style={{ marginBottom: 16 }}>
          <Row gutter={[16, 16]}>
            <Col xs={24} sm={12} md={8}>
              <Search
                placeholder="Buscar productos..."
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
                <Option value="fertilizer">Fertilizer</Option>
                <Option value="insecticide">Insecticide</Option>
                <Option value="herbicide">Herbicide</Option>
                <Option value="fungicide">Fungicide</Option>
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
                <Option value="active">Activo</Option>
                <Option value="inactive">Inactivo</Option>
              </Select>
            </Col>
          </Row>
        </div>

        <ResponsiveTable
          mobileColumns={mobileColumns}
          desktopColumns={desktopColumns}
          dataSource={filteredProducts}
          rowKey="id"
          loading={productsLoading || createProductMutation.isPending || updateProductMutation.isPending || deleteProductMutation.isPending}
          expandedRowRender={expandedRowRender}
          entityName="productos"
          pagination={{
            total: filteredProducts.length,
            pageSize: 10,
          }}
        />
      </Card>

      <Modal
        title={editingProduct ? 'Editar Producto' : 'Nuevo Producto'}
        open={isModalVisible}
        onCancel={() => {
          setIsModalVisible(false);
          form.resetFields();
          setEditingProduct(null);
        }}
        footer={null}
        width={600}
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
                label="Nombre del Producto"
                rules={[{ required: true, message: 'El nombre es requerido' }]}
              >
                <Input placeholder="Ingrese el nombre del producto" />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item
                name="productCode"
                label="Código de Producto"
              >
                <Input placeholder="Código del producto (opcional)" />
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col span={12}>
              <Form.Item
                name="category"
                label="Categoría"
                rules={[{ required: true, message: 'La categoría es requerida' }]}
              >
                <Select placeholder="Seleccione una categoría">
                  <Option value="fertilizante">Fertilizante</Option>
                  <Option value="pesticida">Pesticida</Option>
                  <Option value="herbicida">Herbicida</Option>
                  <Option value="fungicida">Fungicida</Option>
                </Select>
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item
                name="baseUnit"
                label="Unidad Base"
                rules={[{ required: true, message: 'La unidad base es requerida' }]}
              >
                <Select
                  placeholder="Seleccione la unidad"
                  loading={!baseUnitsData}
                  showSearch
                  optionFilterProp="children"
                >
                  {baseUnits
                    .filter((unit: any) => unit.status === 'active')
                    .map((unit: any) => (
                      <Option key={unit.id} value={unit.symbol}>
                        {unit.name} ({unit.symbol})
                      </Option>
                    ))}
                </Select>
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col span={24}>
              <Form.Item
                name="activeIngredient"
                label="Principio Activo"
                rules={[{ required: true, message: 'El principio activo es requerido' }]}
              >
                <Input placeholder="Ej: Nitrógeno 15%, Deltametrina 2.5%" />
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col span={12}>
              <Form.Item
                name="brandId"
                label="Marca"
                rules={[{ required: true, message: 'La marca es requerida' }]}
              >
                <Select
                  placeholder="Seleccione una marca"
                  loading={!brandsData}
                  showSearch
                  optionFilterProp="children"
                >
                  {brands.map((brand: any) => (
                    <Option key={brand.id} value={brand.id}>
                      {brand.name}
                    </Option>
                  ))}
                </Select>
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item
                name="minStock"
                label="Stock Mínimo"
              >
                <InputNumber min={0} placeholder="0" style={{ width: '100%' }} />
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col span={12}>
              <Form.Item
                name="iva"
                label="IVA (%)"
                rules={[{ required: true, message: 'El IVA es requerido' }]}
                initialValue={19}
              >
                <InputNumber
                  min={0}
                  max={100}
                  precision={0}
                  style={{ width: '100%' }}
                  placeholder="Ingrese el porcentaje de IVA"
                  addonAfter="%"
                />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item
                name="status"
                label="Estado"
                initialValue="active"
              >
                <Select placeholder="Seleccione el estado">
                  <Option value="active">Activo</Option>
                  <Option value="inactive">Inactivo</Option>
                </Select>
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col span={24}>
              <Form.Item
                name="packagingUnitIds"
                label="Unidades de Empaque"
              >
                <Select
                  mode="multiple"
                  placeholder="Seleccione las unidades de empaque disponibles"
                  loading={!packagingUnitsData}
                  showSearch
                  optionFilterProp="children"
                >
                  {packagingUnits.map((unit: any) => (
                    <Option key={unit.id} value={unit.id}>
                      {unit.name} ({unit.baseQuantity} {unit.baseUnit})
                    </Option>
                  ))}
                </Select>
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col span={24}>
              <Form.Item
                name="description"
                label="Descripción"
              >
                <Input.TextArea
                  rows={3}
                  placeholder="Descripción del producto (opcional)"
                />
              </Form.Item>
            </Col>
          </Row>

          <div style={{ textAlign: 'right' }}>
            <Space>
              <Button onClick={() => {
                setIsModalVisible(false);
                form.resetFields();
                setEditingProduct(null);
              }}>
                Cancelar
              </Button>
              <Button
                type="primary"
                htmlType="submit"
                loading={createProductMutation.isPending || updateProductMutation.isPending}
                disabled={createProductMutation.isPending || updateProductMutation.isPending}
              >
                {editingProduct ? 'Actualizar' : 'Crear'}
              </Button>
            </Space>
          </div>
        </Form>
      </Modal>
    </div>
  );
};

export default Products;