import React, { useState, useEffect } from 'react';
import { Button, Input, Space, Card, Tag, message, Modal, Row, Col, Select, Descriptions, Divider, Alert, Typography, Drawer, Table, Form, DatePicker, InputNumber } from 'antd';
import { EyeOutlined, DownloadOutlined, PrinterOutlined, ShoppingCartOutlined, ClockCircleOutlined, CheckCircleOutlined, TruckOutlined, ExclamationCircleOutlined, PlusOutlined, InboxOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import ResponsiveTable from '../../components/ResponsiveTable';
import dayjs from 'dayjs';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { purchasesApi, suppliersApi, productsApi, locationsApi } from '../../services/api';
import { downloadPurchaseOrderPDF, openPurchaseOrderPDF } from '../../utils/pdfGenerator';
import { formatCurrency, formatQuantity } from '../../utils/formatters';

const { Text, Title } = Typography;
const { Search } = Input;
const { Option } = Select;

interface Supplier {
  id: string;
  name: string;
  nit: string;
  address: string;
  city: string;
  department: string;
  phone: string;
  email: string;
  contactPerson: string;
  contactPhone: string;
  paymentTerms: string;
  status: string;
}

interface Purchase {
  id: string;
  orderNumber: string;
  supplierId: string;
  supplierName: string;
  supplier?: Supplier;
  destinationLocationId: string;
  destinationLocationName: string;
  purchaseDate: Date;
  expectedDelivery?: Date;
  status: 'pending' | 'received' | 'cancelled';
  items: PurchaseItem[];
  subtotal: number;
  tax: number;
  total: number;
  observations?: string;
  attachments: string[];
  createdBy: string;
  receivedBy?: string;
  receivedAt?: Date;
  createdAt: Date;
}

interface PurchaseItem {
  id: string;
  productId: string;
  productName: string;
  brandId: string;
  brandName: string;
  quantity: number; // Cantidad en unidades de empaque
  quantityInBaseUnits: number; // Cantidad en unidad base
  unit: string; // Unidad base del producto
  packagingUnitId: string;
  packagingUnitName: string;
  baseQuantityPerUnit: number;
  unitPrice: number; // Precio por unidad de empaque
  subtotal: number;
  ivaPercentage?: number; // IVA del producto
  taxAmount?: number; // Monto de IVA del item
  total?: number; // Total del item con IVA
  expirationDate?: Date;
}

const Purchases: React.FC = () => {
  const queryClient = useQueryClient();
  const [isMobile, setIsMobile] = useState(false);
  const [loading, setLoading] = useState(false);
  const [isModalVisible, setIsModalVisible] = useState(false);
  const [selectedPurchase, setSelectedPurchase] = useState<Purchase | null>(null);
  const [searchText, setSearchText] = useState('');
  const [statusFilter, setStatusFilter] = useState<string | undefined>();
  const [isNewPurchaseModalVisible, setIsNewPurchaseModalVisible] = useState(false);
  const [form] = Form.useForm();
  const [selectedOriginLocationId, setSelectedOriginLocationId] = useState<string | undefined>();

  // Fetch purchases from API
  const { data: purchasesData, isLoading: purchasesLoading } = useQuery({
    queryKey: ['purchases', searchText, statusFilter],
    queryFn: () => purchasesApi.list({
      search: searchText || undefined,
      status: statusFilter || undefined
    }),
  });

  // Fetch suppliers
  const { data: suppliersData } = useQuery({
    queryKey: ['suppliers'],
    queryFn: () => suppliersApi.list(),
  });

  // Fetch products
  const { data: productsData } = useQuery({
    queryKey: ['products'],
    queryFn: () => productsApi.list(),
  });

  // Fetch locations
  const { data: locationsData } = useQuery({
    queryKey: ['locations'],
    queryFn: () => locationsApi.list(),
  });

  // Create purchase mutation
  const createPurchaseMutation = useMutation({
    mutationFn: (data: any) => purchasesApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['purchases'] });
      setIsNewPurchaseModalVisible(false);
      form.resetFields();
      message.success('Orden de compra creada exitosamente');
    },
    onError: (error: any) => {
      message.error(`Error al crear la compra: ${error.message}`);
    },
  });

  const purchases = purchasesData?.data || [];
  const suppliers = suppliersData?.data || [];
  const products = productsData?.data || [];
  const locations = locationsData?.data || [];

  useEffect(() => {
    const checkScreenSize = () => {
      setIsMobile(window.innerWidth < 768);
    };

    checkScreenSize();
    window.addEventListener('resize', checkScreenSize);
    return () => window.removeEventListener('resize', checkScreenSize);
  }, []);

  const getStatusColor = (status: string) => {
    const colors = {
      ordered: 'blue',
      pending: 'blue',
      in_transit: 'orange',
      received: 'green',
      cancelled: 'red'
    };
    return colors[status as keyof typeof colors] || 'default';
  };

  const getStatusText = (status: string) => {
    const texts = {
      ordered: 'Disponible para Recepción',
      pending: 'Disponible para Recepción',
      in_transit: 'Recepción Iniciada',
      received: 'Recibido',
      cancelled: 'Cancelado'
    };
    return texts[status as keyof typeof texts] || status;
  };

  const getStatusIcon = (status: string) => {
    const icons = {
      ordered: <ClockCircleOutlined />,
      pending: <ClockCircleOutlined />,
      in_transit: <InboxOutlined />,
      received: <CheckCircleOutlined />,
      cancelled: <ExclamationCircleOutlined />
    };
    return icons[status as keyof typeof icons] || <ClockCircleOutlined />;
  };

  const handleViewPurchase = (record: Purchase) => {
    setSelectedPurchase(record);
    setIsModalVisible(true);
  };

  const handleDownloadPDF = async (purchase: Purchase) => {
    try {
      setLoading(true);
      await downloadPurchaseOrderPDF(purchase);
      message.success('PDF descargado correctamente');
    } catch (error) {
      message.error('Error al descargar PDF');
      console.error(error);
    } finally {
      setLoading(false);
    }
  };

  const handleOpenPDF = async (purchase: Purchase) => {
    try {
      setLoading(true);
      await openPurchaseOrderPDF(purchase);
      message.success('PDF abierto en nueva ventana');
    } catch (error) {
      message.error('Error al abrir PDF');
      console.error(error);
    } finally {
      setLoading(false);
    }
  };

  const handleCreatePurchase = (values: any) => {
    // Format data for backend API
    const purchaseData = {
      order_number: `PUR-${new Date().getFullYear()}-${String(Date.now()).slice(-6)}`,
      supplier_id: values.supplierId,
      origin_location_id: values.originLocationId || undefined,
      destination_location_id: values.destinationLocationId,
      purchase_date: values.purchaseDate.format('YYYY-MM-DD'),
      expected_delivery: values.expectedDelivery ? values.expectedDelivery.format('YYYY-MM-DD') : undefined,
      observations: values.observations || undefined,
      items: values.items.map((item: any) => ({
        product_id: item.productId,
        brand_id: item.brandId || products.find((p: any) => p.id === item.productId)?.brand_id,
        packaging_unit_id: item.packagingUnitId,
        quantity: item.quantity,
        unit_price: item.unitPrice,
        expiration_date: item.expirationDate ? item.expirationDate.format('YYYY-MM-DD') : undefined,
      })),
    };

    createPurchaseMutation.mutate(purchaseData);
  };



  // No need to filter locally, backend handles filtering
  const filteredPurchases = purchases;

  const mobileColumns: ColumnsType<Purchase> = [
    {
      title: 'Compra',
      key: 'purchase',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: '14px', marginBottom: 4 }}>
            <ShoppingCartOutlined style={{ marginRight: 8, color: '#1890ff' }} />
            {record.orderNumber}
          </div>
          <div style={{ fontSize: '12px', color: '#666', marginBottom: 4 }}>
            {record.supplierName}
          </div>
          <div style={{ fontSize: '12px', color: '#666' }}>
            <Tag color={getStatusColor(record.status)} size="small" icon={getStatusIcon(record.status)}>
              {getStatusText(record.status)}
            </Tag>
            <span style={{ marginLeft: 8 }}>{formatCurrency(record.total)}</span>
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
        <Space direction="vertical" size="small">
          <Button
            type="link"
            icon={<EyeOutlined />}
            onClick={() => handleViewPurchase(record)}
            size="small"
          >
            Ver
          </Button>
          <Button
            type="link"
            icon={<PrinterOutlined />}
            onClick={() => handleOpenPDF(record)}
            size="small"
          >
            PDF
          </Button>
        </Space>
      ),
    },
  ];

  const desktopColumns: ColumnsType<Purchase> = [
    {
      title: 'Orden de Compra',
      key: 'order',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: 14, marginBottom: 4 }}>
            <ShoppingCartOutlined style={{ marginRight: 8, color: '#1890ff' }} />
            {record.orderNumber}
          </div>
          <div style={{ color: '#666', fontSize: 12 }}>
            {dayjs(record.purchaseDate).format('DD/MM/YYYY')}
          </div>
        </div>
      ),
    },
    {
      title: 'Proveedor',
      dataIndex: 'supplierName',
      key: 'supplierName',
      render: (text: string) => (
        <div style={{ fontSize: 12 }}>
          {text}
        </div>
      ),
    },
    {
      title: 'Productos',
      key: 'items',
      render: (_, record) => (
        <div>
          <div style={{ fontSize: 12, marginBottom: 4 }}>
            {record.items.length} producto{record.items.length !== 1 ? 's' : ''}
          </div>
          {record.items.slice(0, 2).map((item, index) => (
            <div key={index} style={{ color: '#666', fontSize: 11 }}>
              • {item.productName} ({formatQuantity(item.quantity)} {item.unit})
            </div>
          ))}
          {record.items.length > 2 && (
            <div style={{ color: '#999', fontSize: 11 }}>
              +{record.items.length - 2} más...
            </div>
          )}
        </div>
      ),
    },
    {
      title: 'Total',
      dataIndex: 'total',
      key: 'total',
      render: (total: number) => (
        <div style={{ textAlign: 'right', fontWeight: 500 }}>
          {formatCurrency(total)}
        </div>
      ),
    },
    {
      title: 'Entrega',
      key: 'delivery',
      render: (_, record) => (
        <div style={{ fontSize: 12 }}>
          {record.expectedDelivery ? (
            <div>
              <div style={{ color: '#666' }}>Esperada:</div>
              <div>{dayjs(record.expectedDelivery).format('DD/MM/YYYY')}</div>
            </div>
          ) : (
            <span style={{ color: '#999' }}>No definida</span>
          )}
        </div>
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
        { text: 'Disponible para Recepción', value: 'ordered' },
        { text: 'Recepción Iniciada', value: 'in_transit' },
        { text: 'Recibido', value: 'received' },
        { text: 'Cancelado', value: 'cancelled' },
      ],
      onFilter: (value, record) => record.status === value,
    },
    {
      title: 'Acciones',
      key: 'actions',
      fixed: 'right' as const,
      width: 200,
      render: (_, record) => (
        <Space size="small">
          <Button
            type="link"
            icon={<EyeOutlined />}
            onClick={() => handleViewPurchase(record)}
            size="small"
          >
            Ver
          </Button>
          <Button
            type="link"
            icon={<PrinterOutlined />}
            onClick={() => handleOpenPDF(record)}
            size="small"
            loading={loading}
          >
            PDF
          </Button>
          <Button
            type="link"
            icon={<DownloadOutlined />}
            onClick={() => handleDownloadPDF(record)}
            size="small"
            loading={loading}
          >
            Descargar
          </Button>
        </Space>
      ),
    },
  ];

  const expandedRowRender = (record: Purchase) => (
    <Descriptions size="small" column={1}>
      <Descriptions.Item label="Proveedor">{record.supplierName}</Descriptions.Item>
      <Descriptions.Item label="Ubicación de Entrega">{record.destinationLocationName}</Descriptions.Item>
      <Descriptions.Item label="Fecha de compra">
        {dayjs(record.purchaseDate).format('DD/MM/YYYY')}
      </Descriptions.Item>
      {record.expectedDelivery && (
        <Descriptions.Item label="Entrega esperada">
          {dayjs(record.expectedDelivery).format('DD/MM/YYYY')}
        </Descriptions.Item>
      )}
      <Descriptions.Item label="Subtotal">{formatCurrency(record.subtotal)}</Descriptions.Item>
      <Descriptions.Item label="IVA">{formatCurrency(record.tax)}</Descriptions.Item>
      <Descriptions.Item label="Total">{formatCurrency(record.total)}</Descriptions.Item>
      <Descriptions.Item label="Creado por">{record.createdBy}</Descriptions.Item>
      {record.observations && (
        <Descriptions.Item label="Observaciones">{record.observations}</Descriptions.Item>
      )}
    </Descriptions>
  );

  const renderPurchaseDetails = () => {
    if (!selectedPurchase) return null;

    return (
      <div>
        <Alert
          message="Información de la Orden de Compra"
          description={
            <Descriptions size="small" column={isMobile ? 1 : 2}>
              <Descriptions.Item label="Número">{selectedPurchase.orderNumber}</Descriptions.Item>
              <Descriptions.Item label="Estado">
                <Tag color={getStatusColor(selectedPurchase.status)} icon={getStatusIcon(selectedPurchase.status)}>
                  {getStatusText(selectedPurchase.status)}
                </Tag>
              </Descriptions.Item>
              <Descriptions.Item label="Proveedor">{selectedPurchase.supplierName}</Descriptions.Item>
              <Descriptions.Item label="Ubicación de Entrega">{selectedPurchase.destinationLocationName}</Descriptions.Item>
              <Descriptions.Item label="Fecha de Compra">
                {dayjs(selectedPurchase.purchaseDate).format('DD/MM/YYYY')}
              </Descriptions.Item>
              {selectedPurchase.expectedDelivery && (
                <Descriptions.Item label="Entrega Esperada">
                  {dayjs(selectedPurchase.expectedDelivery).format('DD/MM/YYYY')}
                </Descriptions.Item>
              )}
              <Descriptions.Item label="Creado por">{selectedPurchase.createdBy}</Descriptions.Item>
            </Descriptions>
          }
          type="info"
          style={{ marginBottom: 24 }}
        />

        <Divider>Productos</Divider>
        <Table
          dataSource={selectedPurchase.items}
          rowKey="id"
          pagination={false}
          size="small"
          scroll={{ x: 800 }}
          columns={[
            {
              title: 'Producto',
              dataIndex: 'productName',
              key: 'productName',
            },
            {
              title: 'Marca',
              dataIndex: 'brandName',
              key: 'brandName',
            },
            {
              title: 'Cantidad',
              dataIndex: 'quantity',
              key: 'quantity',
              render: (quantity: number, record: PurchaseItem) =>
                `${formatQuantity(quantity)} ${record.packagingUnitName || record.unit}`
            },
            {
              title: 'Equivalencia',
              key: 'equivalence',
              render: (_, record: PurchaseItem) =>
                record.packagingUnitName
                  ? `${formatQuantity(record.quantityInBaseUnits)} ${record.unit}`
                  : '-'
            },
            {
              title: 'Precio Unit.',
              dataIndex: 'unitPrice',
              key: 'unitPrice',
              render: (price: number) => formatCurrency(price)
            },
            {
              title: 'Subtotal',
              dataIndex: 'subtotal',
              key: 'subtotal',
              render: (subtotal: number) => formatCurrency(subtotal)
            },
            {
              title: 'Vencimiento',
              dataIndex: 'expirationDate',
              key: 'expirationDate',
              render: (date: Date) => date ? dayjs(date).format('DD/MM/YYYY') : 'N/A'
            }
          ]}
        />

        <div style={{ marginTop: 24, textAlign: 'right' }}>
          <Space direction="vertical" size="small">
            <div>
              <strong>Subtotal: </strong>
              <span>{formatCurrency(selectedPurchase.subtotal)}</span>
            </div>
            <div>
              <strong>IVA{(() => {
                const ivaPercentages = [...new Set(selectedPurchase.items.map(item => item.ivaPercentage ?? 19))];
                return ivaPercentages.length === 1 ? ` (${ivaPercentages[0]}%)` : ' (Mixto)';
              })()}: </strong>
              <span>{formatCurrency(selectedPurchase.tax)}</span>
            </div>
            <div style={{ fontSize: 16, color: '#2E7D32' }}>
              <strong>Total: </strong>
              <span>{formatCurrency(selectedPurchase.total)}</span>
            </div>
          </Space>
        </div>

        {selectedPurchase.observations && (
          <div style={{ marginTop: 24 }}>
            <Divider>Observaciones</Divider>
            <div style={{ background: '#f9f9f9', padding: 16, borderRadius: 4 }}>
              {selectedPurchase.observations}
            </div>
          </div>
        )}

        <div style={{ marginTop: 24, textAlign: 'center' }}>
          <Space>
            <Button
              type="primary"
              icon={<PrinterOutlined />}
              onClick={() => handleOpenPDF(selectedPurchase)}
              loading={loading}
            >
              Ver PDF
            </Button>
            <Button
              icon={<DownloadOutlined />}
              onClick={() => handleDownloadPDF(selectedPurchase)}
              loading={loading}
            >
              Descargar PDF
            </Button>
          </Space>
        </div>
      </div>
    );
  };

  return (
    <div>
      <div style={{ marginBottom: 16, display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ margin: 0, color: '#2E7D32' }}>Órdenes de Compra</h1>
          <p style={{ color: '#666', margin: 0 }}>
            Gestión de órdenes de compra con generación de PDF
          </p>
        </div>
        <Button
          type="primary"
          icon={<PlusOutlined />}
          onClick={() => setIsNewPurchaseModalVisible(true)}
        >
          Nueva Compra
        </Button>
      </div>

      <Card>
        <div style={{ marginBottom: 16 }}>
          <Row gutter={[16, 16]}>
            <Col xs={24} sm={12} md={8}>
              <Search
                placeholder="Buscar órdenes de compra..."
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
                <Option value="pending">Disponible para Recepción</Option>
                <Option value="received">Recibido</Option>
                <Option value="cancelled">Cancelado</Option>
              </Select>
            </Col>
          </Row>
        </div>

        <ResponsiveTable
          mobileColumns={mobileColumns}
          desktopColumns={desktopColumns}
          dataSource={filteredPurchases}
          rowKey="id"
          loading={purchasesLoading || createPurchaseMutation.isPending}
          expandedRowRender={expandedRowRender}
          entityName="órdenes de compra"
          pagination={{
            total: filteredPurchases.length,
            pageSize: 10,
          }}
        />
      </Card>

      {/* Modal de detalles */}
      {isMobile ? (
        <Drawer
          title={`Orden de Compra ${selectedPurchase?.orderNumber}`}
          placement="bottom"
          height="90%"
          open={isModalVisible}
          onClose={() => {
            setIsModalVisible(false);
            setSelectedPurchase(null);
          }}
          styles={{
            body: { padding: 16 }
          }}
        >
          {renderPurchaseDetails()}
        </Drawer>
      ) : (
        <Modal
          title={`Orden de Compra ${selectedPurchase?.orderNumber}`}
          open={isModalVisible}
          onCancel={() => {
            setIsModalVisible(false);
            setSelectedPurchase(null);
          }}
          footer={null}
          width={1000}
        >
          {renderPurchaseDetails()}
        </Modal>
      )}

      {/* Modal de nueva compra */}
      <Modal
        title="Nueva Orden de Compra"
        open={isNewPurchaseModalVisible}
        onCancel={() => {
          setIsNewPurchaseModalVisible(false);
          form.resetFields();
          setSelectedOriginLocationId(undefined);
        }}
        footer={null}
        width={1600}
      >
        <Form
          form={form}
          layout="vertical"
          onFinish={handleCreatePurchase}
        >
          <Row gutter={16}>
            <Col xs={24} sm={12}>
              <Form.Item
                name="supplierId"
                label="Proveedor"
                rules={[{ required: true, message: 'Selecciona un proveedor' }]}
              >
                <Select placeholder="Selecciona un proveedor" loading={!suppliersData}>
                  {suppliers.map((supplier: any) => (
                    <Select.Option key={supplier.id} value={supplier.id}>
                      {supplier.name}
                    </Select.Option>
                  ))}
                </Select>
              </Form.Item>
            </Col>
            <Col xs={24} sm={12}>
              <Form.Item
                name="purchaseDate"
                label="Fecha de Compra"
                rules={[{ required: true, message: 'Selecciona la fecha' }]}
              >
                <DatePicker style={{ width: '100%' }} format="DD/MM/YYYY" />
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col xs={24} sm={12}>
              <Form.Item
                name="originLocationId"
                label="Ubicación de Origen (Opcional)"
              >
                <Select
                  placeholder="Selecciona la ubicación de origen (opcional)"
                  loading={!locationsData}
                  allowClear
                  onChange={(value) => setSelectedOriginLocationId(value)}
                >
                  {locations
                    .filter((location: any) => location.status === 'active')
                    .map((location: any) => (
                      <Select.Option key={location.id} value={location.id}>
                        {location.name} - {location.municipality}
                      </Select.Option>
                    ))}
                </Select>
              </Form.Item>
            </Col>
            <Col xs={24} sm={12}>
              <Form.Item
                name="destinationLocationId"
                label="Ubicación de Destino"
                rules={[
                  { required: true, message: 'Selecciona la ubicación de entrega' },
                  ({ getFieldValue }) => ({
                    validator(_, value) {
                      const originId = getFieldValue('originLocationId');
                      if (originId && value && originId === value) {
                        return Promise.reject(new Error('La ubicación de destino debe ser diferente a la de origen'));
                      }
                      return Promise.resolve();
                    },
                  }),
                ]}
              >
                <Select placeholder="Selecciona la ubicación de entrega" loading={!locationsData}>
                  {locations
                    .filter((location: any) =>
                      location.status === 'active' &&
                      location.id !== selectedOriginLocationId
                    )
                    .map((location: any) => (
                      <Select.Option key={location.id} value={location.id}>
                        {location.name} - {location.municipality}
                      </Select.Option>
                    ))}
                </Select>
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col xs={24} sm={24}>
              <Form.Item
                name="expectedDelivery"
                label="Fecha de Entrega Esperada"
              >
                <DatePicker style={{ width: '100%' }} format="DD/MM/YYYY" />
              </Form.Item>
            </Col>
          </Row>

          <Form.Item label="Productos">
            <Form.List name="items">
              {(fields, { add, remove }) => (
                <>
                  {fields.map(({ key, name, ...restField }) => (
                    <Card key={key} size="small" style={{ marginBottom: 16 }}>
                      <Row gutter={16}>
                        <Col xs={24} sm={9}>
                          <Form.Item
                            {...restField}
                            name={[name, 'productId']}
                            label="Producto"
                            rules={[{ required: true, message: 'Selecciona un producto' }]}
                          >
                            <Select
                              placeholder="Selecciona producto"
                              loading={!productsData}
                              onChange={(productId) => {
                                const product = products.find((p: any) => p.id === productId);
                                if (product) {
                                  form.setFieldsValue({
                                    items: form.getFieldValue('items').map((item: any, index: number) =>
                                      index === name ? {
                                        ...item,
                                        unit: product.base_unit || product.unit,
                                        baseUnit: product.base_unit || product.unit,
                                        brandId: product.brand_id,
                                        packagingUnitId: undefined,
                                        packagingUnitName: undefined,
                                        baseQuantityPerUnit: undefined,
                                        totalEquivalence: ''
                                      } : item
                                    )
                                  });
                                }
                              }}
                            >
                              {products.map((product: any) => (
                                <Select.Option key={product.id} value={product.id}>
                                  {product.name} {product.brand?.name ? `- ${product.brand.name}` : ''}
                                </Select.Option>
                              ))}
                            </Select>
                          </Form.Item>
                        </Col>
                        <Col xs={24} sm={3}>
                          <Form.Item
                            {...restField}
                            name={[name, 'quantity']}
                            label="Cantidad"
                            rules={[{ required: true, message: 'Ingresa cantidad' }]}
                          >
                            <InputNumber
                              min={1}
                              style={{ width: '100%' }}
                              formatter={(value) => `${value}`.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}
                              parser={(value) => value!.replace(/\$\s?|(,*)/g, '')}
                              onChange={(quantity) => {
                                const currentItems = form.getFieldValue('items');
                                const currentItem = currentItems[name];
                                if (quantity && currentItem?.baseQuantityPerUnit) {
                                  const totalEquivalence = quantity * currentItem.baseQuantityPerUnit;
                                  form.setFieldsValue({
                                    items: form.getFieldValue('items').map((item: any, index: number) =>
                                      index === name ? {
                                        ...item,
                                        totalEquivalence: `${totalEquivalence} ${currentItem.baseUnit}`
                                      } : item
                                    )
                                  });
                                }
                              }}
                            />
                          </Form.Item>
                        </Col>
                        <Col xs={24} sm={6}>
                          <Form.Item
                            {...restField}
                            name={[name, 'packagingUnitId']}
                            label="Unidad de Empaque"
                            rules={[{ required: true, message: 'Selecciona unidad' }]}
                          >
                            <Select
                              placeholder="Selecciona unidad de empaque"
                              style={{ width: '100%' }}
                              onChange={(packagingUnitId) => {
                                const currentItems = form.getFieldValue('items');
                                const currentItem = currentItems[name];
                                const product = products.find((p: any) => p.id === currentItem?.productId);
                                if (product) {
                                  const packagingUnit = product.packaging_units?.find((pu: any) => pu.id === packagingUnitId);
                                  if (packagingUnit) {
                                    const quantity = currentItem?.quantity || 0;
                                    const totalEquivalence = quantity > 0 ? quantity * packagingUnit.base_quantity : 0;

                                    form.setFieldsValue({
                                      items: form.getFieldValue('items').map((item: any, index: number) =>
                                        index === name ? {
                                          ...item,
                                          packagingUnitId,
                                          packagingUnitName: packagingUnit.name,
                                          baseQuantityPerUnit: packagingUnit.base_quantity,
                                          baseUnit: product.base_unit || product.unit,
                                          totalEquivalence: totalEquivalence > 0 ? `${totalEquivalence} ${product.base_unit || product.unit}` : ''
                                        } : item
                                      )
                                    });
                                  }
                                }
                              }}
                            >
                              {form.getFieldValue('items')?.[name]?.productId &&
                                products.find((p: any) => p.id === form.getFieldValue('items')[name].productId)?.packaging_units?.map((unit: any) => (
                                  <Select.Option key={unit.id} value={unit.id}>
                                    {unit.name} ({unit.base_quantity} {unit.base_unit})
                                  </Select.Option>
                                ))
                              }
                            </Select>
                          </Form.Item>
                        </Col>
                        <Col xs={24} sm={2}>
                          <Form.Item
                            {...restField}
                            name={[name, 'baseUnit']}
                            label="U. Base"
                          >
                            <Input
                              disabled
                              style={{ width: '100%', backgroundColor: '#f5f5f5', color: '#666' }}
                              placeholder="Auto"
                            />
                          </Form.Item>
                        </Col>
                        <Col xs={24} sm={3}>
                          <Form.Item
                            {...restField}
                            name={[name, 'totalEquivalence']}
                            label="Equivalencia Total"
                          >
                            <Input
                              disabled
                              style={{ width: '100%', backgroundColor: '#e6f7ff', color: '#1890ff', fontWeight: 'bold' }}
                              placeholder="Auto"
                            />
                          </Form.Item>
                        </Col>
                        <Col xs={24} sm={2}>
                          <Form.Item
                            {...restField}
                            name={[name, 'unitPrice']}
                            label="Precio Unit."
                            rules={[{ required: true, message: 'Ingresa precio' }]}
                          >
                            <InputNumber
                              min={0}
                              style={{ width: '100%' }}
                              formatter={(value) => `$ ${value}`.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}
                              parser={(value) => value!.replace(/\$\s?|(,*)/g, '')}
                            />
                          </Form.Item>
                        </Col>
                        <Col xs={24} sm={1}>
                          <Form.Item label=" ">
                            <Button danger onClick={() => remove(name)}>
                              ×
                            </Button>
                          </Form.Item>
                        </Col>
                      </Row>
                    </Card>
                  ))}
                  <Button type="dashed" onClick={() => add()} block>
                    + Agregar Producto
                  </Button>
                </>
              )}
            </Form.List>
          </Form.Item>

          <Form.Item
            name="observations"
            label="Observaciones"
          >
            <Input.TextArea rows={3} placeholder="Observaciones adicionales..." />
          </Form.Item>

          <div style={{ textAlign: 'right' }}>
            <Space>
              <Button onClick={() => {
                setIsNewPurchaseModalVisible(false);
                form.resetFields();
                setSelectedOriginLocationId(undefined);
              }}>
                Cancelar
              </Button>
              <Button type="primary" htmlType="submit">
                Crear Orden de Compra
              </Button>
            </Space>
          </div>
        </Form>
      </Modal>
    </div>
  );
};

export default Purchases;