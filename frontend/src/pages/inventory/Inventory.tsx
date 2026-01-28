import React, { useState } from 'react';
import { Card, Row, Col, Statistic, Tag, Input, Select, Space, Alert, Descriptions, Modal, Table, Spin } from 'antd';
import { DatabaseOutlined, WarningOutlined, CheckCircleOutlined, ExclamationCircleOutlined, InboxOutlined, ArrowUpOutlined, ArrowDownOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import { useQuery } from '@tanstack/react-query';
import ResponsiveTable from '../../components/ResponsiveTable';
import { inventoryApi, locationsApi } from '../../services/api';
import { formatCurrency, formatQuantity } from '../../utils/formatters';

const { Search } = Input;
const { Option } = Select;

interface InventoryBatch {
  id: string;
  brand_id: string;
  brand_name: string;
  batch_number: string;
  quantity: number;
  unit: string;
  expiration_date: string | null;
  unit_price: number;
  total_value: number;
  status: string;
}

interface InventoryLocation {
  location_id: string;
  location_name: string;
  location_type: string;
  total_quantity: number;
  total_value: number;
  batches: InventoryBatch[];
}

interface KardexItem {
  product_id: string;
  product_name: string;
  product_code: string;
  category: string;
  base_unit: string;
  min_stock: number;
  total_quantity: number;
  total_value: number;
  locations_count: number;
  status: 'good' | 'low' | 'out_of_stock' | 'near_expiry' | 'expired';
  inventory_by_location: InventoryLocation[];
}

interface KardexMovement {
  id: string;
  date: string;
  type: 'entry' | 'exit' | 'transfer' | 'application';
  brand_name: string;
  location_name: string;
  quantity_in: number;
  quantity_out: number;
  balance: number;
  unit: string;
  unit_price: number;
  total_price: number;
  responsible_user: string;
  related_document_id: string | null;
  related_document_type: string | null;
  observations: string | null;
}

interface ProductKardexData {
  product: {
    id: string;
    name: string;
    product_code: string;
    category: string;
    base_unit: string;
    min_stock: number;
  };
  movements: KardexMovement[];
  current_inventory: any[];
  summary: {
    total_movements: number;
    total_entries: number;
    total_exits: number;
    current_balance: number;
    current_stock: number;
  };
}

const Inventory: React.FC = () => {
  const [searchText, setSearchText] = useState('');
  const [locationFilter, setLocationFilter] = useState<string | undefined>();
  const [statusFilter, setStatusFilter] = useState<string | undefined>();
  const [selectedProduct, setSelectedProduct] = useState<KardexItem | null>(null);
  const [isKardexModalVisible, setIsKardexModalVisible] = useState(false);

  // Fetch locations for filter
  const { data: locationsData } = useQuery({
    queryKey: ['locations'],
    queryFn: () => locationsApi.list({ per_page: 100 })
  });

  // Fetch kardex data
  const { data: kardexResponse, isLoading } = useQuery({
    queryKey: ['inventory-kardex', locationFilter, searchText, statusFilter],
    queryFn: () => inventoryApi.getKardex({
      location_id: locationFilter,
      search: searchText || undefined,
      status: statusFilter
    })
  });

  // Fetch product kardex movements when modal is opened
  const { data: productKardexData, isLoading: isLoadingKardex } = useQuery<{ data: ProductKardexData }>({
    queryKey: ['product-kardex', selectedProduct?.product_id, locationFilter],
    queryFn: () => inventoryApi.getProductKardex(selectedProduct!.product_id, {
      location_id: locationFilter
    }),
    enabled: !!selectedProduct
  });

  const kardexData = kardexResponse?.data || [];
  const summary = kardexResponse?.summary || {
    total_products: 0,
    total_value: 0,
    low_stock_count: 0,
    out_of_stock_count: 0,
    near_expiry_count: 0,
    expired_count: 0
  };

  const getStatusInfo = (status: string) => {
    const statusMap = {
      good: { color: '#52c41a', text: 'Normal', badge: 'success' },
      low: { color: '#fa541c', text: 'Stock Bajo', badge: 'warning' },
      out_of_stock: { color: '#f5222d', text: 'Sin Stock', badge: 'error' },
      near_expiry: { color: '#fa8c16', text: 'Próximo a Vencer', badge: 'warning' },
      expired: { color: '#f5222d', text: 'Expirado', badge: 'error' }
    };
    return statusMap[status as keyof typeof statusMap] || statusMap.good;
  };

  const getMovementTypeInfo = (type: string) => {
    const typeMap = {
      entry: { color: 'green', text: 'Entrada', icon: <ArrowDownOutlined /> },
      exit: { color: 'red', text: 'Salida', icon: <ArrowUpOutlined /> },
      transfer: { color: 'blue', text: 'Transferencia', icon: <InboxOutlined /> },
      application: { color: 'orange', text: 'Aplicación', icon: <CheckCircleOutlined /> }
    };
    return typeMap[type as keyof typeof typeMap] || typeMap.entry;
  };

  const showKardexModal = (record: KardexItem) => {
    setSelectedProduct(record);
    setIsKardexModalVisible(true);
  };

  const handleKardexModalClose = () => {
    setIsKardexModalVisible(false);
    setSelectedProduct(null);
  };

  const mobileColumns: ColumnsType<KardexItem> = [
    {
      title: 'Producto',
      key: 'product',
      render: (_, record) => {
        const statusInfo = getStatusInfo(record.status);
        return (
          <div onClick={() => showKardexModal(record)} style={{ cursor: 'pointer' }}>
            <div style={{ fontWeight: 500, fontSize: '14px', marginBottom: 4 }}>
              {record.product_name}
            </div>
            <div style={{ fontSize: '12px', color: '#666', marginBottom: 4 }}>
              <Tag color={statusInfo.color} size="small">{statusInfo.text}</Tag>
              <span style={{ marginLeft: 8 }}>{formatQuantity(record.total_quantity)} {record.base_unit}</span>
            </div>
            {record.locations_count > 0 && (
              <div style={{ fontSize: '11px', color: '#999' }}>
                {record.locations_count} ubicación(es)
              </div>
            )}
          </div>
        );
      },
    },
    {
      title: 'Valor',
      key: 'value',
      width: 100,
      render: (_, record) => (
        <span style={{ fontWeight: 500, fontSize: '14px' }}>
          {formatCurrency(record.total_value)}
        </span>
      ),
    },
  ];

  const desktopColumns: ColumnsType<KardexItem> = [
    {
      title: 'Producto',
      key: 'product',
      render: (_, record) => (
        <div onClick={() => showKardexModal(record)} style={{ cursor: 'pointer' }}>
          <div style={{ fontWeight: 500, fontSize: 14 }}>{record.product_name}</div>
          <div style={{ color: '#666', fontSize: 12 }}>
            Código: {record.product_code} | Categoría: {record.category}
          </div>
        </div>
      ),
    },
    {
      title: 'Stock Total',
      key: 'stock',
      render: (_, record) => {
        const statusInfo = getStatusInfo(record.status);
        return (
          <div>
            <div style={{ fontWeight: 500, fontSize: 14 }}>
              {formatQuantity(record.total_quantity)} {record.base_unit}
            </div>
            {record.min_stock > 0 && (
              <div style={{ fontSize: 11, color: '#666' }}>
                Min: {formatQuantity(record.min_stock)} {record.base_unit}
              </div>
            )}
          </div>
        );
      },
    },
    {
      title: 'Ubicaciones',
      key: 'locations',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500 }}>{record.locations_count}</div>
          {record.inventory_by_location.map((loc, idx) => (
            <div key={idx} style={{ fontSize: 11, color: '#666' }}>
              {loc.location_name}: {formatQuantity(loc.total_quantity)} {record.base_unit}
            </div>
          ))}
        </div>
      ),
    },
    {
      title: 'Valor Total',
      key: 'value',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500 }}>
            {formatCurrency(record.total_value)}
          </div>
        </div>
      ),
    },
    {
      title: 'Estado',
      key: 'status',
      render: (_, record) => {
        const statusInfo = getStatusInfo(record.status);
        return <Tag color={statusInfo.color}>{statusInfo.text}</Tag>;
      },
      filters: [
        { text: 'Normal', value: 'good' },
        { text: 'Stock Bajo', value: 'low' },
        { text: 'Sin Stock', value: 'out_of_stock' },
        { text: 'Próximo a Vencer', value: 'near_expiry' },
        { text: 'Expirado', value: 'expired' },
      ],
      onFilter: (value, record) => record.status === value,
    },
  ];

  const kardexMovementsColumns: ColumnsType<KardexMovement> = [
    {
      title: 'Fecha',
      dataIndex: 'date',
      key: 'date',
      render: (date: string) => new Date(date).toLocaleString('es-CO', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
      }),
    },
    {
      title: 'Tipo',
      dataIndex: 'type',
      key: 'type',
      render: (type: string) => {
        const typeInfo = getMovementTypeInfo(type);
        return (
          <Tag color={typeInfo.color} icon={typeInfo.icon}>
            {typeInfo.text}
          </Tag>
        );
      },
    },
    {
      title: 'Marca',
      dataIndex: 'brand_name',
      key: 'brand_name',
    },
    {
      title: 'Ubicación',
      dataIndex: 'location_name',
      key: 'location_name',
    },
    {
      title: 'Entrada',
      dataIndex: 'quantity_in',
      key: 'quantity_in',
      render: (qty: number, record) => qty > 0 ? (
        <span style={{ color: '#52c41a', fontWeight: 500 }}>
          +{formatQuantity(qty)} {record.unit}
        </span>
      ) : '-',
    },
    {
      title: 'Salida',
      dataIndex: 'quantity_out',
      key: 'quantity_out',
      render: (qty: number, record) => qty > 0 ? (
        <span style={{ color: '#f5222d', fontWeight: 500 }}>
          -{formatQuantity(qty)} {record.unit}
        </span>
      ) : '-',
    },
    {
      title: 'Saldo',
      dataIndex: 'balance',
      key: 'balance',
      render: (balance: number, record) => (
        <span style={{ fontWeight: 500 }}>
          {formatQuantity(balance)} {record.unit}
        </span>
      ),
    },
    {
      title: 'Precio Unit.',
      dataIndex: 'unit_price',
      key: 'unit_price',
      render: (price: number) => formatCurrency(price),
    },
    {
      title: 'Valor',
      dataIndex: 'total_price',
      key: 'total_price',
      render: (price: number) => formatCurrency(price),
    },
    {
      title: 'Responsable',
      dataIndex: 'responsible_user',
      key: 'responsible_user',
    },
  ];

  const expandedRowRender = (record: KardexItem) => {
    return (
      <div>
        <h4 style={{ marginBottom: 16 }}>Inventario por Ubicación</h4>
        {record.inventory_by_location.length === 0 ? (
          <Alert message="No hay inventario registrado para este producto" type="info" />
        ) : (
          record.inventory_by_location.map((location) => (
            <Card key={location.location_id} size="small" style={{ marginBottom: 12 }}>
              <div style={{ marginBottom: 8 }}>
                <strong>{location.location_name}</strong>
                <Tag color="blue" style={{ marginLeft: 8 }}>{location.location_type}</Tag>
              </div>
              <div style={{ marginBottom: 8 }}>
                <span style={{ marginRight: 16 }}>
                  <strong>Cantidad Total:</strong> {formatQuantity(location.total_quantity)} {record.base_unit}
                </span>
                <span>
                  <strong>Valor Total:</strong> {formatCurrency(location.total_value)}
                </span>
              </div>

              {location.batches.length > 0 && (
                <div>
                  <div style={{ fontWeight: 500, marginBottom: 8 }}>Lotes:</div>
                  <div style={{ paddingLeft: 16 }}>
                    {location.batches.map((batch, idx) => (
                      <div key={idx} style={{ marginBottom: 8, fontSize: 12 }}>
                        <div>
                          <strong>Marca:</strong> {batch.brand_name} |
                          <strong> Lote:</strong> {batch.batch_number || 'N/A'} |
                          <strong> Cantidad:</strong> {formatQuantity(batch.quantity)} {batch.unit}
                        </div>
                        {batch.expiration_date && (
                          <div style={{ color: '#666' }}>
                            Vencimiento: {new Date(batch.expiration_date).toLocaleDateString('es-CO')}
                          </div>
                        )}
                        <div style={{ color: '#666' }}>
                          Precio Unit.: {formatCurrency(batch.unit_price)} |
                          Valor Total: {formatCurrency(batch.total_value)}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </Card>
          ))
        )}
      </div>
    );
  };

  return (
    <div>
      <div style={{ marginBottom: 16 }}>
        <h1 style={{ margin: 0, color: '#2E7D32' }}>Inventario y Kardex</h1>
        <p style={{ color: '#666', margin: 0 }}>
          Control de existencias y trazabilidad de productos
        </p>
      </div>

      {/* Alertas */}
      {(summary.low_stock_count > 0 || summary.near_expiry_count > 0 || summary.expired_count > 0 || summary.out_of_stock_count > 0) && (
        <Alert
          message="Alertas de Inventario"
          description={
            <div>
              {summary.expired_count > 0 && <div>• {summary.expired_count} producto(s) expirado(s)</div>}
              {summary.near_expiry_count > 0 && <div>• {summary.near_expiry_count} producto(s) próximo(s) a vencer</div>}
              {summary.low_stock_count > 0 && <div>• {summary.low_stock_count} producto(s) con stock bajo</div>}
              {summary.out_of_stock_count > 0 && <div>• {summary.out_of_stock_count} producto(s) sin stock</div>}
            </div>
          }
          type="warning"
          showIcon
          style={{ marginBottom: 16 }}
        />
      )}

      {/* KPIs */}
      <Row gutter={[16, 16]} style={{ marginBottom: 24 }}>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="Total Productos"
              value={summary.total_products}
              prefix={<DatabaseOutlined style={{ color: '#4CAF50' }} />}
              valueStyle={{ color: '#2E7D32' }}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="Sin Stock / Stock Bajo"
              value={`${summary.out_of_stock_count} / ${summary.low_stock_count}`}
              prefix={<WarningOutlined style={{ color: '#FF9800' }} />}
              valueStyle={{ color: (summary.out_of_stock_count + summary.low_stock_count) > 0 ? '#F57C00' : '#666' }}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="Próximos a Vencer"
              value={summary.near_expiry_count}
              prefix={<ExclamationCircleOutlined style={{ color: '#FF5722' }} />}
              valueStyle={{ color: summary.near_expiry_count > 0 ? '#D84315' : '#666' }}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="Valor Total"
              value={summary.total_value}
              prefix="$"
              precision={2}
              valueStyle={{ color: '#2E7D32' }}
              formatter={(value) => formatCurrency(Number(value)).replace('$', '')}
            />
          </Card>
        </Col>
      </Row>

      {/* Filtros y Tabla */}
      <Card>
        <div style={{ marginBottom: 16 }}>
          <Space wrap>
            <Search
              placeholder="Buscar productos..."
              allowClear
              style={{ width: 300 }}
              value={searchText}
              onChange={(e) => setSearchText(e.target.value)}
            />
            <Select
              placeholder="Filtrar por ubicación"
              allowClear
              style={{ width: 200 }}
              value={locationFilter}
              onChange={setLocationFilter}
            >
              {locationsData?.data?.map((location: any) => (
                <Option key={location.id} value={location.id}>
                  {location.name}
                </Option>
              ))}
            </Select>
            <Select
              placeholder="Filtrar por estado"
              allowClear
              style={{ width: 180 }}
              value={statusFilter}
              onChange={setStatusFilter}
            >
              <Option value="good">Normal</Option>
              <Option value="low">Stock Bajo</Option>
              <Option value="out_of_stock">Sin Stock</Option>
              <Option value="near_expiry">Próximo a Vencer</Option>
              <Option value="expired">Expirado</Option>
            </Select>
          </Space>
        </div>

        <ResponsiveTable
          mobileColumns={mobileColumns}
          desktopColumns={desktopColumns}
          dataSource={kardexData}
          rowKey="product_id"
          expandedRowRender={expandedRowRender}
          entityName="productos"
          loading={isLoading}
          pagination={{
            total: kardexData.length,
            pageSize: 10,
          }}
          rowClassName={(record) => {
            if (record.status === 'expired') return 'row-expired';
            if (record.status === 'near_expiry') return 'row-warning';
            if (record.status === 'low' || record.status === 'out_of_stock') return 'row-low-stock';
            return '';
          }}
        />
      </Card>

      {/* Kardex Modal */}
      <Modal
        title={
          <div>
            <div style={{ fontSize: 18, fontWeight: 600 }}>
              Kardex - {selectedProduct?.product_name}
            </div>
            {selectedProduct && (
              <div style={{ fontSize: 12, color: '#666', fontWeight: 'normal' }}>
                Código: {selectedProduct.product_code} | Categoría: {selectedProduct.category}
              </div>
            )}
          </div>
        }
        open={isKardexModalVisible}
        onCancel={handleKardexModalClose}
        width="90%"
        style={{ maxWidth: 1200 }}
        footer={null}
      >
        {isLoadingKardex ? (
          <div style={{ textAlign: 'center', padding: '40px 0' }}>
            <Spin size="large" />
          </div>
        ) : productKardexData ? (
          <div>
            {/* Summary */}
            <Card size="small" style={{ marginBottom: 16, backgroundColor: '#f5f5f5' }}>
              <Row gutter={16}>
                <Col span={6}>
                  <Statistic
                    title="Total Entradas"
                    value={productKardexData.data.summary.total_entries}
                    valueStyle={{ color: '#52c41a', fontSize: 16 }}
                    suffix={selectedProduct?.base_unit}
                  />
                </Col>
                <Col span={6}>
                  <Statistic
                    title="Total Salidas"
                    value={productKardexData.data.summary.total_exits}
                    valueStyle={{ color: '#f5222d', fontSize: 16 }}
                    suffix={selectedProduct?.base_unit}
                  />
                </Col>
                <Col span={6}>
                  <Statistic
                    title="Saldo Actual"
                    value={productKardexData.data.summary.current_balance}
                    valueStyle={{ color: '#1890ff', fontSize: 16 }}
                    suffix={selectedProduct?.base_unit}
                  />
                </Col>
                <Col span={6}>
                  <Statistic
                    title="Movimientos"
                    value={productKardexData.data.summary.total_movements}
                    valueStyle={{ fontSize: 16 }}
                  />
                </Col>
              </Row>
            </Card>

            {/* Movements Table */}
            <Table
              columns={kardexMovementsColumns}
              dataSource={productKardexData.data.movements}
              rowKey="id"
              pagination={{ pageSize: 10 }}
              scroll={{ x: 1200 }}
              size="small"
            />
          </div>
        ) : null}
      </Modal>

      <style jsx>{`
        .row-expired {
          background-color: #fff2f0;
        }
        .row-warning {
          background-color: #fff7e6;
        }
        .row-low-stock {
          background-color: #fff1f0;
        }
      `}</style>
    </div>
  );
};

export default Inventory;
