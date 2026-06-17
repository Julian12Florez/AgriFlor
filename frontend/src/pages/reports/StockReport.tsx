import React, { useState } from 'react';
import { Card, Row, Col, Button, Select, message, Spin, Tag, Statistic, Alert } from 'antd';
import {
  ShoppingOutlined,
  FileExcelOutlined,
  FilePdfOutlined,
  ReloadOutlined,
  FilterOutlined,
  WarningOutlined,
  CheckCircleOutlined,
  PrinterOutlined,
} from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import ResponsiveTable from '../../components/ResponsiveTable';
import { useQuery } from '@tanstack/react-query';
import { inventoryApi, productsApi, locationsApi, reportExportsApi } from '../../services/api';
import dayjs from 'dayjs';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell } from 'recharts';
import usePermissions from '../../hooks/usePermissions';

const { Option } = Select;

interface StockItem {
  product_id: string;
  product_name: string;
  product_code: string;
  category: string;
  brand_id: string;
  brand_name: string;
  location_id: string;
  location_name: string;
  location_type: string;
  quantity: number;
  unit: string;
  base_quantity?: number;
  base_unit?: string;
  total_base_quantity?: number;
  unit_price: number;
  total_value: number;
  status: 'good' | 'low' | 'out_of_stock' | 'expired' | 'near_expiry';
  expiration_date?: string;
}

const StockReport: React.FC = () => {
  const [productId, setProductId] = useState<string | undefined>(undefined);
  const [locationId, setLocationId] = useState<string | undefined>(undefined);
  const [groupBy, setGroupBy] = useState<'product' | 'location'>('product');
  const [shouldFetch, setShouldFetch] = useState(false);
  const [isExporting, setIsExporting] = useState(false);

  const { hasPermission } = usePermissions();

  // Fetch products for filter
  const { data: productsData } = useQuery({
    queryKey: ['products-all'],
    queryFn: () => productsApi.list({ per_page: 1000 }),
  });

  // Fetch locations for filter
  const { data: locationsData } = useQuery({
    queryKey: ['locations-all'],
    queryFn: () => locationsApi.list({ per_page: 1000 }),
  });

  // Fetch inventory
  const { data: inventoryData, isLoading, refetch } = useQuery({
    queryKey: ['inventory-stock-report', productId, locationId],
    queryFn: () => {
      const params: any = { per_page: 1000 };
      if (productId) params.product_id = productId;
      if (locationId) params.location_id = locationId;
      return inventoryApi.list(params);
    },
    enabled: shouldFetch,
  });

  const stockItems: StockItem[] = inventoryData?.data || [];

  const handleGenerateReport = () => {
    setShouldFetch(true);
    refetch();
  };

  const handleClearFilters = () => {
    setProductId(undefined);
    setLocationId(undefined);
    setShouldFetch(false);
  };

  const handleExportExcel = async () => {
    try {
      setIsExporting(true);
      const params: any = { group_by: groupBy };
      if (productId) params.product_id = productId;
      if (locationId) params.location_id = locationId;

      await reportExportsApi.exportStockExcel(params);
      message.success('Reporte exportado exitosamente a Excel');
    } catch (error: any) {
      message.error(error.message || 'Error al exportar a Excel');
      console.error('Error exporting to Excel:', error);
    } finally {
      setIsExporting(false);
    }
  };

  const handleExportPDF = async () => {
    try {
      setIsExporting(true);
      const params: any = { group_by: groupBy };
      if (productId) params.product_id = productId;
      if (locationId) params.location_id = locationId;

      await reportExportsApi.exportStockPdf(params);
      message.success('Reporte exportado exitosamente a PDF');
    } catch (error: any) {
      message.error(error.message || 'Error al exportar a PDF');
      console.error('Error exporting to PDF:', error);
    } finally {
      setIsExporting(false);
    }
  };

  const handlePrint = () => {
    window.print();
  };

  const getStatusTag = (status: string, quantity: number) => {
    const statusConfig: Record<string, { color: string; label: string; icon: any }> = {
      good: { color: 'green', label: 'Disponible', icon: <CheckCircleOutlined /> },
      low: { color: 'orange', label: 'Stock Bajo', icon: <WarningOutlined /> },
      out_of_stock: { color: 'red', label: 'Agotado', icon: <WarningOutlined /> },
      expired: { color: 'red', label: 'Vencido', icon: <WarningOutlined /> },
      near_expiry: { color: 'orange', label: 'Por Vencer', icon: <WarningOutlined /> },
    };

    if (quantity === 0) {
      return <Tag color="red" icon={<WarningOutlined />}>Agotado</Tag>;
    }

    const config = statusConfig[status] || statusConfig.good;
    return (
      <Tag color={config.color} icon={config.icon}>
        {config.label}
      </Tag>
    );
  };

  // Group data by selected grouping
  const groupedData = React.useMemo(() => {
    if (groupBy === 'product') {
      const grouped = new Map<string, any>();
      stockItems.forEach((item) => {
        const key = `${item.product_id}-${item.brand_id}`;
        if (!grouped.has(key)) {
          grouped.set(key, {
            product_id: item.product_id,
            product_name: item.product_name,
            product_code: item.product_code,
            category: item.category,
            brand_name: item.brand_name,
            total_quantity: 0,
            total_value: 0,
            total_base_quantity: 0,
            unit: item.unit,
            base_unit: item.base_unit,
            base_quantity: item.base_quantity,
            locations: [],
            status: 'good',
          });
        }
        const group = grouped.get(key);
        group.total_quantity += item.quantity;
        group.total_value += item.total_value;
        group.total_base_quantity += (item.total_base_quantity || item.quantity);
        group.locations.push({
          location_name: item.location_name,
          location_type: item.location_type,
          quantity: item.quantity,
          value: item.total_value,
          expiration_date: item.expiration_date,
        });

        // Update status to worst case
        if (item.status === 'out_of_stock' || item.status === 'expired') {
          group.status = item.status;
        } else if (
          item.status === 'low' &&
          group.status !== 'out_of_stock' &&
          group.status !== 'expired'
        ) {
          group.status = 'low';
        }
      });
      return Array.from(grouped.values());
    } else {
      // Group by location
      const grouped = new Map<string, any>();
      stockItems.forEach((item) => {
        const key = item.location_id;
        if (!grouped.has(key)) {
          grouped.set(key, {
            location_id: item.location_id,
            location_name: item.location_name,
            location_type: item.location_type,
            total_items: 0,
            total_quantity: 0,
            total_value: 0,
            products: [],
          });
        }
        const group = grouped.get(key);
        group.total_items += 1;
        group.total_quantity += item.quantity;
        group.total_value += item.total_value;
        group.products.push({
          product_name: item.product_name,
          brand_name: item.brand_name,
          quantity: item.quantity,
          unit: item.unit,
          value: item.total_value,
          status: item.status,
        });
      });
      return Array.from(grouped.values());
    }
  }, [stockItems, groupBy]);

  // Helper function to get full packaging unit name
  const getFullPackagingUnitName = (unit: string, baseQuantity: number, baseUnit: string) => {
    if (baseQuantity && baseQuantity > 1) {
      return `${unit} de ${baseQuantity} ${baseUnit}`;
    }
    return unit || 'unidades';
  };

  // Columns for product grouping
  const productMobileColumns: ColumnsType<any> = [
    {
      title: 'Producto',
      key: 'product',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: '14px', marginBottom: 4 }}>
            {record.product_name || 'Sin nombre'}
          </div>
          <div style={{ fontSize: '12px', color: '#666', marginBottom: 4 }}>
            {record.brand_name || 'Sin marca'} • {record.product_code || 'N/A'}
          </div>
          <div>{getStatusTag(record.status || 'good', record.total_quantity || 0)}</div>
        </div>
      ),
    },
    {
      title: 'Stock',
      key: 'stock',
      width: 160,
      render: (_, record) => {
        const hasPackaging = record.base_quantity && record.base_quantity > 1;
        const baseUnit = record.base_unit || record.unit || 'unidades';
        const baseQty = record.total_base_quantity || record.total_quantity || 0;
        const totalValue = record.total_value || 0;
        const unitPriceBase = baseQty > 0 ? totalValue / baseQty : 0;

        return (
          <div style={{ textAlign: 'right' }}>
            <div style={{ fontWeight: 500, fontSize: '14px', color: '#2E7D32', marginBottom: 4 }}>
              {baseQty.toLocaleString()} {baseUnit}
            </div>
            {hasPackaging && (
              <div style={{ fontSize: '11px', color: '#666', marginBottom: 4 }}>
                {(record.total_quantity || 0).toLocaleString()} {record.unit} x {record.base_quantity} {baseUnit}
              </div>
            )}
            {unitPriceBase > 0 && (
              <div style={{ fontSize: '11px', color: '#1890ff', marginBottom: 2 }}>
                ${unitPriceBase.toLocaleString('es-CO', { maximumFractionDigits: 0 })} / {baseUnit}
              </div>
            )}
            <div style={{ fontSize: '12px', color: '#666', fontWeight: 500 }}>
              ${totalValue.toLocaleString('es-CO')}
            </div>
            <div style={{ fontSize: '11px', color: '#999', marginTop: 4 }}>
              {(record.locations || []).length} ubicacion(es)
            </div>
          </div>
        );
      },
    },
  ];

  const productDesktopColumns: ColumnsType<any> = [
    {
      title: 'Producto',
      dataIndex: 'product_name',
      key: 'product_name',
      render: (name: string, record) => (
        <div>
          <div style={{ fontWeight: 500 }}>{name || 'Sin nombre'}</div>
          <div style={{ fontSize: '12px', color: '#666' }}>
            {record.product_code || 'N/A'} • {record.category ? record.category.charAt(0).toUpperCase() + record.category.slice(1) : 'Sin categoría'}
          </div>
        </div>
      ),
      sorter: (a, b) => (a.product_name || '').localeCompare(b.product_name || ''),
    },
    {
      title: 'Marca',
      dataIndex: 'brand_name',
      key: 'brand_name',
      width: 150,
      render: (name: string) => name || 'Sin marca',
    },
    {
      title: 'Stock (Base)',
      dataIndex: 'total_base_quantity',
      key: 'total_base_quantity',
      width: 160,
      align: 'right',
      render: (totalBase: number, record) => {
        const baseUnit = record.base_unit || record.unit || 'unidades';
        const qty = totalBase || record.total_quantity || 0;
        return (
          <div style={{ textAlign: 'right', fontWeight: 500, color: '#2E7D32' }}>
            {qty.toLocaleString()} {baseUnit}
          </div>
        );
      },
      sorter: (a, b) => (a.total_base_quantity || 0) - (b.total_base_quantity || 0),
    },
    {
      title: 'Detalle Empaque',
      key: 'packaging_detail',
      width: 160,
      render: (_, record) => {
        const hasPackaging = record.base_quantity && record.base_quantity > 1;
        if (!hasPackaging) return '';

        return (
          <div style={{ fontSize: '12px', color: '#666' }}>
            {(record.total_quantity || 0).toLocaleString()} {record.unit} x {record.base_quantity} {record.base_unit}
          </div>
        );
      },
    },
    {
      title: 'Precio Unitario',
      key: 'unit_price_calculated',
      width: 150,
      align: 'right',
      render: (_, record) => {
        const quantity = record.total_base_quantity || record.total_quantity || 0;
        const unit = record.base_unit || record.unit || 'unidades';
        const totalValue = record.total_value || 0;

        if (quantity === 0 || totalValue === 0) {
          return '-';
        }

        const unitPrice = totalValue / quantity;

        return (
          <div style={{ textAlign: 'right' }}>
            <div style={{ fontWeight: 500, color: '#1890ff', fontSize: '13px' }}>
              ${unitPrice.toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}
            </div>
            <div style={{ fontSize: '11px', color: '#666' }}>
              por {unit}
            </div>
          </div>
        );
      },
      sorter: (a, b) => {
        const qtyA = a.total_base_quantity || a.total_quantity || 0;
        const qtyB = b.total_base_quantity || b.total_quantity || 0;
        const priceA = qtyA > 0 ? (a.total_value || 0) / qtyA : 0;
        const priceB = qtyB > 0 ? (b.total_value || 0) / qtyB : 0;
        return priceA - priceB;
      },
    },
    {
      title: 'Valor Total',
      dataIndex: 'total_value',
      key: 'total_value',
      width: 150,
      align: 'right',
      render: (value: number) => (
        <span style={{ fontWeight: 500, color: '#2E7D32' }}>
          ${(value || 0).toLocaleString('es-CO')}
        </span>
      ),
      sorter: (a, b) => (a.total_value || 0) - (b.total_value || 0),
    },
    {
      title: 'Ubicaciones',
      dataIndex: 'locations',
      key: 'locations',
      width: 120,
      align: 'center',
      render: (locations: any[]) => (locations || []).length,
    },
    {
      title: 'Estado',
      dataIndex: 'status',
      key: 'status',
      width: 130,
      render: (status: string, record) => getStatusTag(status || 'good', record.total_quantity || 0),
      filters: [
        { text: 'Disponible', value: 'good' },
        { text: 'Stock Bajo', value: 'low' },
        { text: 'Agotado', value: 'out_of_stock' },
        { text: 'Vencido', value: 'expired' },
        { text: 'Por Vencer', value: 'near_expiry' },
      ],
      onFilter: (value, record) => (record.status || 'good') === value,
    },
  ];

  const productExpandedRowRender = (record: any) => {
    const hasPackaging = record.base_quantity && record.base_quantity > 1;
    const fullUnitName = getFullPackagingUnitName(record.unit, record.base_quantity, record.base_unit);

    return (
      <div style={{ padding: '12px' }}>
        <Row gutter={16} style={{ marginBottom: 8, borderBottom: '2px solid #f0f0f0', paddingBottom: 8 }}>
          <Col xs={24} sm={5}><strong>Ubicación</strong></Col>
          <Col xs={12} sm={3} style={{ textAlign: 'right' }}><strong>Cantidad</strong></Col>
          {hasPackaging && <Col xs={12} sm={3} style={{ textAlign: 'right' }}><strong>Total Base</strong></Col>}
          <Col xs={12} sm={hasPackaging ? 3 : 4} style={{ textAlign: 'right' }}><strong>Precio/Empaque</strong></Col>
          <Col xs={12} sm={hasPackaging ? 3 : 4} style={{ textAlign: 'right' }}><strong>Precio/Base</strong></Col>
          <Col xs={12} sm={hasPackaging ? 4 : 4} style={{ textAlign: 'right' }}><strong>Valor</strong></Col>
          <Col xs={24} sm={hasPackaging ? 3 : 4} style={{ textAlign: 'right' }}><strong>Vencimiento</strong></Col>
        </Row>
        {(record.locations || []).map((loc: any, index: number) => {
          const totalBase = (loc.quantity || 0) * (record.base_quantity || 1);

          return (
            <Row key={index} gutter={16} style={{ marginBottom: 12, paddingBottom: 12, borderBottom: '1px solid #f0f0f0' }}>
              <Col xs={24} sm={5}>
                <strong>{loc.location_name || 'Sin ubicación'}</strong>
                <div style={{ fontSize: '12px', color: '#666' }}>
                  {loc.location_type === 'warehouse' ? 'Bodega' : 'Finca'}
                </div>
              </Col>
              <Col xs={12} sm={3} style={{ textAlign: 'right' }}>
                <div style={{ fontWeight: 500 }}>
                  {(loc.quantity || 0).toLocaleString()} {fullUnitName}
                </div>
              </Col>
              {hasPackaging && (
                <Col xs={12} sm={3} style={{ textAlign: 'right' }}>
                  <div style={{ fontWeight: 500, color: '#2E7D32' }}>
                    {totalBase.toLocaleString()} {record.base_unit}
                  </div>
                </Col>
              )}
              <Col xs={12} sm={hasPackaging ? 3 : 4} style={{ textAlign: 'right' }}>
                {(() => {
                  // Calculate average price per package for this location
                  const packagePrice = (loc.quantity || 0) > 0 ? (loc.value || 0) / (loc.quantity || 0) : 0;

                  return packagePrice > 0 ? (
                    <div style={{ fontSize: '11px', color: '#722ed1' }}>
                      ${packagePrice.toLocaleString('es-CO', { maximumFractionDigits: 0 })}/{record.unit}
                    </div>
                  ) : <div>-</div>;
                })()}
              </Col>
              <Col xs={12} sm={hasPackaging ? 3 : 4} style={{ textAlign: 'right' }}>
                {(() => {
                  // Calculate average price per base unit for this location
                  const qty = hasPackaging ? totalBase : (loc.quantity || 0);
                  const unit = hasPackaging ? record.base_unit : record.unit;
                  const unitPriceBase = qty > 0 ? (loc.value || 0) / qty : 0;

                  return unitPriceBase > 0 ? (
                    <div style={{ fontSize: '11px', color: '#1890ff' }}>
                      ${unitPriceBase.toLocaleString('es-CO', { maximumFractionDigits: 0 })}/{unit}
                    </div>
                  ) : <div>-</div>;
                })()}
              </Col>
              <Col xs={12} sm={hasPackaging ? 4 : 4} style={{ textAlign: 'right' }}>
                <div style={{ fontWeight: 500, color: '#2E7D32' }}>
                  ${(loc.value || 0).toLocaleString('es-CO')}
                </div>
              </Col>
              <Col xs={24} sm={hasPackaging ? 3 : 4} style={{ textAlign: 'right' }}>
                <div style={{ fontSize: '12px', color: '#666' }}>
                  {loc.expiration_date ? (
                    <>
                      <strong>Vence:</strong> {dayjs(loc.expiration_date).format('DD/MM/YYYY')}
                    </>
                  ) : (
                    <span style={{ color: '#999' }}>Sin vencimiento</span>
                  )}
                </div>
              </Col>
            </Row>
          );
        })}
      </div>
    );
  };

  // Columns for location grouping
  const locationMobileColumns: ColumnsType<any> = [
    {
      title: 'Ubicación',
      key: 'location',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: '14px', marginBottom: 4 }}>
            {record.location_name || 'Sin ubicación'}
          </div>
          <div style={{ fontSize: '12px', color: '#666', marginBottom: 4 }}>
            {record.location_type === 'warehouse' ? 'Bodega' : 'Finca'}
          </div>
          <div style={{ fontSize: '12px', color: '#999' }}>
            {record.total_items || 0} producto(s)
          </div>
        </div>
      ),
    },
    {
      title: 'Valor',
      key: 'value',
      width: 120,
      render: (_, record) => (
        <div style={{ textAlign: 'right' }}>
          <div style={{ fontWeight: 500, fontSize: '14px', marginBottom: 4 }}>
            ${(record.total_value || 0).toLocaleString('es-CO')}
          </div>
          <div style={{ fontSize: '12px', color: '#666' }}>
            {record.total_items || 0} producto(s)
          </div>
        </div>
      ),
    },
  ];

  const locationDesktopColumns: ColumnsType<any> = [
    {
      title: 'Ubicación',
      dataIndex: 'location_name',
      key: 'location_name',
      render: (name: string, record) => (
        <div>
          <div style={{ fontWeight: 500 }}>{name || 'Sin ubicación'}</div>
          <div style={{ fontSize: '12px', color: '#666' }}>
            {record.location_type === 'warehouse' ? 'Bodega' : 'Finca'}
          </div>
        </div>
      ),
    },
    {
      title: 'Total Productos',
      dataIndex: 'total_items',
      key: 'total_items',
      width: 150,
      align: 'center',
      render: (items: number) => items || 0,
      sorter: (a, b) => (a.total_items || 0) - (b.total_items || 0),
    },
    {
      title: 'Valor Total',
      dataIndex: 'total_value',
      key: 'total_value',
      width: 180,
      align: 'right',
      render: (value: number) => (
        <span style={{ fontWeight: 500, color: '#2E7D32' }}>
          ${(value || 0).toLocaleString('es-CO')}
        </span>
      ),
      sorter: (a, b) => (a.total_value || 0) - (b.total_value || 0),
    },
  ];

  const locationExpandedRowRender = (record: any) => (
    <div style={{ padding: '12px' }}>
      <h4 style={{ marginBottom: 8 }}>Productos en esta ubicación:</h4>
      {(record.products || []).map((prod: any, index: number) => (
        <Row key={index} gutter={16} style={{ marginBottom: 8 }}>
          <Col span={8}>
            <strong>{prod.product_name || 'Sin nombre'}</strong>
            <div style={{ fontSize: '12px', color: '#666' }}>{prod.brand_name || 'Sin marca'}</div>
          </Col>
          <Col span={6} style={{ textAlign: 'right' }}>
            {(prod.quantity || 0).toLocaleString()} {prod.unit || 'unidades'}
          </Col>
          <Col span={6} style={{ textAlign: 'right' }}>
            ${(prod.value || 0).toLocaleString('es-CO')}
          </Col>
          <Col span={4}>{getStatusTag(prod.status || 'good', prod.quantity || 0)}</Col>
        </Row>
      ))}
    </div>
  );

  // Calculate statistics
  const totalItems = stockItems.length;
  const uniqueProducts = new Set(stockItems.map((item) => item.product_id)).size;
  const totalValue = stockItems.reduce((sum, item) => sum + (item.total_value || 0), 0);
  const lowStockItems = stockItems.filter((item) => item.status === 'low' || item.status === 'out_of_stock').length;
  const expiredItems = stockItems.filter((item) => item.status === 'expired' || item.status === 'near_expiry').length;

  // Data for charts
  const statusData = [
    {
      name: 'Disponible',
      value: stockItems.filter((item) => item.status === 'good').length,
      fill: '#52c41a',
    },
    {
      name: 'Stock Bajo',
      value: stockItems.filter((item) => item.status === 'low').length,
      fill: '#fa8c16',
    },
    {
      name: 'Agotado',
      value: stockItems.filter((item) => item.status === 'out_of_stock').length,
      fill: '#f5222d',
    },
    {
      name: 'Por Vencer',
      value: stockItems.filter((item) => item.status === 'near_expiry').length,
      fill: '#faad14',
    },
    {
      name: 'Vencido',
      value: stockItems.filter((item) => item.status === 'expired').length,
      fill: '#cf1322',
    },
  ].filter((item) => item.value > 0);

  const topProductsByValue = groupBy === 'product'
    ? [...groupedData]
        .sort((a, b) => (b.total_value || 0) - (a.total_value || 0))
        .slice(0, 10)
        .map((item) => {
          const name = item.product_name || 'Sin nombre';
          return {
            name: name.substring(0, 20) + (name.length > 20 ? '...' : ''),
            value: item.total_value || 0,
          };
        })
    : [];

  return (
    <div>
      <div style={{ marginBottom: 16 }}>
        <h1 style={{ margin: 0, color: '#2E7D32' }}>
          <ShoppingOutlined /> Reporte de Stock Actual
        </h1>
        <p style={{ color: '#666', margin: 0 }}>
          Estado actual del inventario al día {dayjs().format('DD/MM/YYYY')}
        </p>
      </div>

      {/* Filters Card */}
      <Card style={{ marginBottom: 16 }} title={<><FilterOutlined /> Filtros</>}>
        <Row gutter={[16, 16]}>
          <Col xs={24} sm={12} md={8} lg={6}>
            <div style={{ marginBottom: 8, fontWeight: 500 }}>Producto</div>
            <Select
              value={productId}
              onChange={setProductId}
              style={{ width: '100%' }}
              placeholder="Todos los productos"
              allowClear
              showSearch
              optionFilterProp="children"
              filterOption={(input, option) => {
                const label = option?.children;
                return String(label || '').toLowerCase().includes(input.toLowerCase());
              }}
            >
              {productsData?.data?.map((product: any) => (
                <Option key={product.id} value={product.id}>
                  {product.product_code ? `${product.product_code} - ${product.name}` : product.name}
                </Option>
              ))}
            </Select>
          </Col>

          <Col xs={24} sm={12} md={8} lg={6}>
            <div style={{ marginBottom: 8, fontWeight: 500 }}>Ubicación</div>
            <Select
              value={locationId}
              onChange={setLocationId}
              style={{ width: '100%' }}
              placeholder="Todas las ubicaciones"
              allowClear
            >
              {locationsData?.data?.map((location: any) => (
                <Option key={location.id} value={location.id}>
                  {location.name}
                </Option>
              ))}
            </Select>
          </Col>

          <Col xs={24} sm={12} md={8} lg={6}>
            <div style={{ marginBottom: 8, fontWeight: 500 }}>Agrupar Por</div>
            <Select value={groupBy} onChange={setGroupBy} style={{ width: '100%' }}>
              <Option value="product">Producto</Option>
              <Option value="location">Ubicación</Option>
            </Select>
          </Col>
        </Row>

        <Row gutter={16} style={{ marginTop: 16 }}>
          <Col>
            <Button
              type="primary"
              icon={<ShoppingOutlined />}
              onClick={handleGenerateReport}
              loading={isLoading}
            >
              Generar Reporte
            </Button>
          </Col>
          <Col>
            <Button icon={<ReloadOutlined />} onClick={handleClearFilters}>
              Limpiar Filtros
            </Button>
          </Col>
          {shouldFetch && stockItems.length > 0 && hasPermission('export_reports') && (
            <>
              <Col>
                <Button
                  icon={<FileExcelOutlined />}
                  onClick={handleExportExcel}
                  loading={isExporting}
                  disabled={isExporting}
                >
                  Exportar Excel
                </Button>
              </Col>
              <Col>
                <Button
                  icon={<FilePdfOutlined />}
                  onClick={handleExportPDF}
                  loading={isExporting}
                  disabled={isExporting}
                >
                  Exportar PDF
                </Button>
              </Col>
              <Col>
                <Button icon={<PrinterOutlined />} onClick={handlePrint}>
                  Imprimir
                </Button>
              </Col>
            </>
          )}
        </Row>
      </Card>

      {/* Loading */}
      {isLoading && (
        <Card>
          <div style={{ textAlign: 'center', padding: '40px' }}>
            <Spin size="large" />
            <div style={{ marginTop: 16, color: '#666' }}>Generando reporte...</div>
          </div>
        </Card>
      )}

      {/* No Data */}
      {!isLoading && shouldFetch && stockItems.length === 0 && (
        <Alert
          message="Sin inventario"
          description="No se encontraron productos en inventario con los filtros seleccionados"
          type="warning"
          showIcon
        />
      )}

      {/* Results */}
      {!isLoading && shouldFetch && stockItems.length > 0 && (
        <>
          {/* Summary Stats */}
          <Row gutter={16} style={{ marginBottom: 16 }}>
            <Col xs={12} sm={6}>
              <Card>
                <Statistic
                  title="Total Registros"
                  value={totalItems}
                  valueStyle={{ color: '#1890ff' }}
                />
              </Card>
            </Col>
            <Col xs={12} sm={6}>
              <Card>
                <Statistic
                  title="Total Productos"
                  value={uniqueProducts}
                  valueStyle={{ color: '#2E7D32' }}
                />
              </Card>
            </Col>
            <Col xs={12} sm={6}>
              <Card>
                <Statistic
                  title="Valor Total"
                  value={totalValue}
                  prefix="$"
                  precision={0}
                  valueStyle={{ color: '#2E7D32', fontSize: '20px' }}
                />
              </Card>
            </Col>
            <Col xs={12} sm={6}>
              <Card>
                <Statistic
                  title="Alertas"
                  value={lowStockItems + expiredItems}
                  valueStyle={{ color: lowStockItems + expiredItems > 0 ? '#fa8c16' : '#52c41a' }}
                  prefix={<WarningOutlined />}
                  suffix={`/ ${totalItems}`}
                />
              </Card>
            </Col>
          </Row>

          {/* Charts */}
          {groupBy === 'product' && topProductsByValue.length > 0 && (
            <Row gutter={16} style={{ marginBottom: 16 }}>
              <Col xs={24} lg={12}>
                <Card title="Estado del Inventario">
                  <ResponsiveContainer width="100%" height={300}>
                    <PieChart>
                      <Pie
                        data={statusData}
                        cx="50%"
                        cy="50%"
                        labelLine={false}
                        label={(entry) => `${entry.name}: ${entry.value}`}
                        outerRadius={100}
                        fill="#8884d8"
                        dataKey="value"
                      >
                        {statusData.map((entry, index) => (
                          <Cell key={`cell-${index}`} fill={entry.fill} />
                        ))}
                      </Pie>
                      <Tooltip />
                    </PieChart>
                  </ResponsiveContainer>
                </Card>
              </Col>
              <Col xs={24} lg={12}>
                <Card title="Top 10 Productos por Valor">
                  <ResponsiveContainer width="100%" height={300}>
                    <BarChart data={topProductsByValue} layout="vertical" margin={{ left: 100 }}>
                      <CartesianGrid strokeDasharray="3 3" />
                      <XAxis type="number" />
                      <YAxis type="category" dataKey="name" width={100} />
                      <Tooltip formatter={(value: number) => `$${value.toLocaleString('es-CO')}`} />
                      <Bar dataKey="value" fill="#2E7D32" />
                    </BarChart>
                  </ResponsiveContainer>
                </Card>
              </Col>
            </Row>
          )}

          {/* Stock Table */}
          <Card title={`Stock ${groupBy === 'product' ? 'por Producto' : 'por Ubicación'} (${groupedData.length})`}>
            <ResponsiveTable
              mobileColumns={groupBy === 'product' ? productMobileColumns : locationMobileColumns}
              desktopColumns={groupBy === 'product' ? productDesktopColumns : locationDesktopColumns}
              dataSource={groupedData}
              rowKey={groupBy === 'product' ? 'product_id' : 'location_id'}
              expandedRowRender={groupBy === 'product' ? productExpandedRowRender : locationExpandedRowRender}
              entityName={groupBy === 'product' ? 'productos' : 'ubicaciones'}
              pagination={{
                pageSize: 50,
                showSizeChanger: true,
                showTotal: (total) => `Total: ${total} ${groupBy === 'product' ? 'productos' : 'ubicaciones'}`,
              }}
            />
          </Card>
        </>
      )}
    </div>
  );
};

export default StockReport;
