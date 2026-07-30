import React, { useState } from 'react';
import { Card, Row, Col, DatePicker, Button, Select, message, Spin, Descriptions, Alert, Statistic, Tooltip } from 'antd';
import {
  FileTextOutlined,
  FileExcelOutlined,
  FilePdfOutlined,
  ReloadOutlined,
  FilterOutlined,
  PrinterOutlined,
  InfoCircleOutlined,
} from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import ResponsiveTable from '../../components/ResponsiveTable';
import { useQuery } from '@tanstack/react-query';
import { inventoryApi, productsApi, locationsApi, brandsApi, reportExportsApi } from '../../services/api';
import dayjs, { Dayjs } from 'dayjs';

const { RangePicker } = DatePicker;
const { Option } = Select;

interface KardexEntry {
  id: string;
  date: string;
  type: string;
  brand_name: string;
  location_name: string;
  quantity_in: number;
  quantity_out: number;
  balance: number;
  balance_unit: string;
  unit: string;
  original_quantity: number;
  original_unit: string;
  unit_price: number;
  total_price: number;
  responsible_user: string;
  related_document_id: string;
  related_document_type: string;
  observations: string;
}

const getTypeLabel = (type: string): string => {
  const labels: Record<string, string> = {
    entry: 'ENTRADA',
    exit: 'SALIDA',
    transfer: 'TRANSFERENCIA',
    application: 'APLICACIÓN',
    adjustment: 'AJUSTE',
  };
  return labels[type] || type.toUpperCase();
};

const getCategoryLabel = (category: string): string => {
  const labels: Record<string, string> = {
    fertilizante: 'Fertilizante',
    pesticida: 'Pesticida',
    herbicida: 'Herbicida',
    fungicida: 'Fungicida',
  };
  return labels[category] || (category ? category.charAt(0).toUpperCase() + category.slice(1) : 'Sin categoría');
};

const InventoryKardex: React.FC = () => {
  const [dateRange, setDateRange] = useState<[Dayjs | null, Dayjs | null] | null>(null);
  const [productId, setProductId] = useState<string | undefined>(undefined);
  const [brandId, setBrandId] = useState<string | undefined>(undefined);
  const [locationId, setLocationId] = useState<string | undefined>(undefined);
  const [shouldFetch, setShouldFetch] = useState(false);

  // Fetch products for filter
  const { data: productsData } = useQuery({
    queryKey: ['products-all'],
    queryFn: () => productsApi.list({ per_page: 1000 }),
  });

  // Fetch brands for filter
  const { data: brandsData } = useQuery({
    queryKey: ['brands-all'],
    queryFn: () => brandsApi.list({ per_page: 1000 }),
  });

  // Fetch locations for filter
  const { data: locationsData } = useQuery({
    queryKey: ['locations-all'],
    queryFn: () => locationsApi.list({ per_page: 1000 }),
  });

  // Fetch kardex data
  const { data: kardexData, isLoading, refetch } = useQuery({
    queryKey: ['kardex', productId, dateRange, brandId, locationId],
    queryFn: () => {
      if (!productId) return null;

      const params: any = {};

      if (dateRange && dateRange[0] && dateRange[1]) {
        params.start_date = dateRange[0].format('YYYY-MM-DD');
        params.end_date = dateRange[1].format('YYYY-MM-DD');
      }

      if (brandId) params.brand_id = brandId;
      if (locationId) params.location_id = locationId;

      return inventoryApi.getProductKardex(productId, params);
    },
    enabled: shouldFetch && !!productId,
  });

  const kardex: KardexEntry[] = kardexData?.data?.movements || [];
  const productInfo = kardexData?.data?.product || null;
  const summary = kardexData?.data?.summary || null;

  const handleGenerateReport = () => {
    if (!productId) {
      message.warning('Por favor selecciona un producto');
      return;
    }
    setShouldFetch(true);
    refetch();
  };

  const handleClearFilters = () => {
    setDateRange(null);
    setProductId(undefined);
    setBrandId(undefined);
    setLocationId(undefined);
    setShouldFetch(false);
  };

  const handleExportExcel = async () => {
    if (!productId) {
      message.warning('Selecciona un producto primero');
      return;
    }
    try {
      const params: Record<string, string> = { product_id: productId };
      if (dateRange && dateRange[0] && dateRange[1]) {
        params.start_date = dateRange[0].format('YYYY-MM-DD');
        params.end_date = dateRange[1].format('YYYY-MM-DD');
      }
      if (brandId) params.brand_id = brandId;
      if (locationId) params.location_id = locationId;
      await reportExportsApi.exportKardexExcel(params);
      message.success('Excel descargado correctamente');
    } catch {
      message.error('Error al exportar a Excel');
    }
  };

  const handleExportPDF = async () => {
    if (!productId) {
      message.warning('Selecciona un producto primero');
      return;
    }
    try {
      const params: Record<string, string> = { product_id: productId };
      if (dateRange && dateRange[0] && dateRange[1]) {
        params.start_date = dateRange[0].format('YYYY-MM-DD');
        params.end_date = dateRange[1].format('YYYY-MM-DD');
      }
      if (brandId) params.brand_id = brandId;
      if (locationId) params.location_id = locationId;
      await reportExportsApi.exportKardexPdf(params);
      message.success('PDF descargado correctamente');
    } catch {
      message.error('Error al exportar a PDF');
    }
  };

  const handlePrint = () => {
    window.print();
  };

  const mobileColumns: ColumnsType<KardexEntry> = [
    {
      title: 'Movimiento',
      key: 'movement',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: '14px', marginBottom: 4 }}>
            {dayjs(record.date).format('DD/MM/YYYY HH:mm')}
          </div>
          <div style={{ fontSize: '12px', color: '#666', marginBottom: 4 }}>
            {getTypeLabel(record.type)} - {record.location_name} - {record.brand_name}
          </div>
          <div style={{ fontSize: '12px' }}>
            {record.quantity_in > 0 && (
              <span style={{ color: '#3f8600' }}>
                E: +{record.quantity_in} | ${(record.quantity_in * record.unit_price).toLocaleString('es-CO')}
              </span>
            )}
            {record.quantity_out > 0 && (
              <span style={{ color: '#cf1322' }}>
                S: -{record.quantity_out} | ${(record.quantity_out * record.unit_price).toLocaleString('es-CO')}
              </span>
            )}
          </div>
        </div>
      ),
    },
    {
      title: 'Saldo',
      key: 'balance',
      width: 100,
      render: (_, record) => (
        <div style={{ textAlign: 'right' }}>
          <div style={{ fontWeight: 500, fontSize: '14px', marginBottom: 4 }}>
            {record.balance.toLocaleString()}
          </div>
          <div style={{ fontSize: '12px', color: '#666' }}>
            ${(record.balance * record.unit_price).toLocaleString('es-CO')}
          </div>
        </div>
      ),
    },
  ];

  const desktopColumns: ColumnsType<KardexEntry> = [
    {
      title: 'Fecha',
      dataIndex: 'date',
      key: 'date',
      width: 150,
      render: (date: string) => dayjs(date).format('DD/MM/YYYY HH:mm'),
    },
    {
      title: 'Tipo',
      dataIndex: 'type',
      key: 'type',
      width: 100,
      render: (type: string) => getTypeLabel(type),
    },
    {
      title: 'Ubicación',
      dataIndex: 'location_name',
      key: 'location_name',
      width: 150,
    },
    {
      title: 'Marca',
      dataIndex: 'brand_name',
      key: 'brand_name',
      width: 120,
    },
    {
      title: 'Entrada',
      dataIndex: 'quantity_in',
      key: 'quantity_in',
      width: 120,
      align: 'right' as const,
      render: (qty: number, record) => qty > 0 ? (
        <div>
          <div style={{ color: '#3f8600', fontWeight: 500 }}>
            +{qty.toLocaleString()} {record.unit}
          </div>
          {record.original_unit && record.original_unit !== record.unit && (
            <div style={{ fontSize: '11px', color: '#999' }}>
              ({record.original_quantity} {record.original_unit})
            </div>
          )}
        </div>
      ) : '-',
    },
    {
      title: 'Salida',
      dataIndex: 'quantity_out',
      key: 'quantity_out',
      width: 120,
      align: 'right' as const,
      render: (qty: number, record) => qty > 0 ? (
        <div>
          <div style={{ color: '#cf1322', fontWeight: 500 }}>
            -{qty.toLocaleString()} {record.unit}
          </div>
          {record.original_unit && record.original_unit !== record.unit && (
            <div style={{ fontSize: '11px', color: '#999' }}>
              ({record.original_quantity} {record.original_unit})
            </div>
          )}
        </div>
      ) : '-',
    },
    {
      title: 'Saldo',
      dataIndex: 'balance',
      key: 'balance',
      width: 120,
      align: 'right' as const,
      render: (balance: number, record) => (
        <div>
          <div style={{ fontWeight: 500 }}>
            {balance.toLocaleString()} {record.balance_unit || record.unit}
          </div>
          <div style={{ fontSize: '11px', color: '#2E7D32', fontWeight: 500 }}>
            ${(balance * record.unit_price).toLocaleString('es-CO')}
          </div>
        </div>
      ),
    },
  ];

  return (
    <div>
      <div style={{ marginBottom: 16 }}>
        <h1 style={{ margin: 0, color: '#2E7D32' }}>
          <FileTextOutlined /> Kardex de Inventario
        </h1>
        <p style={{ color: '#666', margin: 0 }}>
          Registro detallado de entradas, salidas y saldos por producto
        </p>
      </div>

      {/* Filters Card */}
      <Card style={{ marginBottom: 16 }} title={<><FilterOutlined /> Filtros</>}>
        <Row gutter={[16, 16]}>
          <Col xs={24} sm={12} md={8} lg={6}>
            <div style={{ marginBottom: 8, fontWeight: 500 }}>Producto *</div>
            <Select
              value={productId}
              onChange={setProductId}
              style={{ width: '100%' }}
              placeholder="Selecciona un producto"
              showSearch
              optionFilterProp="children"
              filterOption={(input, option) => {
                const label = option?.children;
                return String(label || '').toLowerCase().includes(input.toLowerCase());
              }}
            >
              {productsData?.data?.map((product: any) => (
                <Option key={product.id} value={product.id}>
                  {product.name}
                </Option>
              ))}
            </Select>
          </Col>

          <Col xs={24} sm={12} md={8} lg={6}>
            <div style={{ marginBottom: 8, fontWeight: 500 }}>Marca</div>
            <Select
              value={brandId}
              onChange={setBrandId}
              style={{ width: '100%' }}
              placeholder="Todas las marcas"
              allowClear
            >
              {brandsData?.data?.map((brand: any) => (
                <Option key={brand.id} value={brand.id}>
                  {brand.name}
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
            <div style={{ marginBottom: 8, fontWeight: 500 }}>Rango de Fechas</div>
            <RangePicker
              value={dateRange}
              onChange={(dates) => setDateRange(dates)}
              style={{ width: '100%' }}
              format="DD/MM/YYYY"
              placeholder={['Fecha inicio', 'Fecha fin']}
            />
          </Col>
        </Row>

        <Row gutter={16} style={{ marginTop: 16 }}>
          <Col>
            <Button
              type="primary"
              icon={<FileTextOutlined />}
              onClick={handleGenerateReport}
              loading={isLoading}
            >
              Generar Kardex
            </Button>
          </Col>
          <Col>
            <Button icon={<ReloadOutlined />} onClick={handleClearFilters}>
              Limpiar Filtros
            </Button>
          </Col>
          {shouldFetch && kardex.length > 0 && (
            <>
              <Col>
                <Button icon={<FileExcelOutlined />} onClick={handleExportExcel}>
                  Exportar Excel
                </Button>
              </Col>
              <Col>
                <Button icon={<FilePdfOutlined />} onClick={handleExportPDF}>
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
            <div style={{ marginTop: 16, color: '#666' }}>Generando kardex...</div>
          </div>
        </Card>
      )}

      {/* No Product Selected */}
      {!isLoading && shouldFetch && !productId && (
        <Alert
          message="Producto requerido"
          description="Por favor selecciona un producto para generar el kardex"
          type="warning"
          showIcon
        />
      )}

      {/* No Data */}
      {!isLoading && shouldFetch && productId && kardex.length === 0 && (
        <Card>
          <div style={{ textAlign: 'center', padding: '40px', color: '#999' }}>
            No se encontraron movimientos para el producto seleccionado
          </div>
        </Card>
      )}

      {/* Results */}
      {!isLoading && shouldFetch && productId && kardex.length > 0 && (
        <>
          {/* Product Info */}
          {productInfo && (
            <Card style={{ marginBottom: 16 }} title="Información del Producto">
              <Descriptions column={{ xs: 1, sm: 2, md: 3 }}>
                <Descriptions.Item label="Producto">{productInfo.name}</Descriptions.Item>
                <Descriptions.Item label="Código">{productInfo.code}</Descriptions.Item>
                <Descriptions.Item label="Categoría">{getCategoryLabel(productInfo.category)}</Descriptions.Item>
                <Descriptions.Item label="Unidad Base">{productInfo.base_unit}</Descriptions.Item>
                {brandId && (
                  <Descriptions.Item label="Marca">
                    {brandsData?.data?.find((b: any) => b.id === brandId)?.name}
                  </Descriptions.Item>
                )}
                {locationId && (
                  <Descriptions.Item label="Ubicación">
                    {locationsData?.data?.find((l: any) => l.id === locationId)?.name}
                  </Descriptions.Item>
                )}
              </Descriptions>
            </Card>
          )}

          {/* Summary Cards */}
          {summary && (() => {
            const stockFisico = summary.current_stock;
            const saldoContable = summary.current_balance;
            const diferencia = Math.round((saldoContable - stockFisico) * 100) / 100;
            const hayDiferencia = Math.abs(diferencia) > 0.01;

            return (
              <>
                <Row gutter={[16, 16]} style={{ marginBottom: hayDiferencia ? 16 : 16 }}>
                  <Col xs={12} sm={6}>
                    <Card>
                      <Statistic
                        title="Total Movimientos"
                        value={summary.total_movements}
                      />
                    </Card>
                  </Col>
                  <Col xs={12} sm={6}>
                    <Card>
                      <Statistic
                        title={`Total Entradas (${productInfo?.base_unit || ''})`}
                        value={summary.total_entries}
                        valueStyle={{ color: '#3f8600' }}
                      />
                    </Card>
                  </Col>
                  <Col xs={12} sm={6}>
                    <Card>
                      <Statistic
                        title={`Total Salidas (${productInfo?.base_unit || ''})`}
                        value={summary.total_exits}
                        valueStyle={{ color: '#cf1322' }}
                      />
                    </Card>
                  </Col>
                  <Col xs={12} sm={6}>
                    <Card>
                      <Statistic
                        title={
                          <Tooltip title="Lo que hay físicamente en los lotes de esta ubicación y se puede despachar.">
                            Stock Actual (físico) <InfoCircleOutlined style={{ color: '#999' }} />
                          </Tooltip>
                        }
                        value={stockFisico}
                        valueStyle={{ color: '#1890ff' }}
                      />
                    </Card>
                  </Col>
                  <Col xs={12} sm={6}>
                    <Card>
                      <Statistic
                        title={
                          <Tooltip title="El saldo que resulta de sumar y restar los movimientos del kardex (entradas, salidas, traslados, ajustes contables).">
                            Saldo contable (libro) <InfoCircleOutlined style={{ color: '#999' }} />
                          </Tooltip>
                        }
                        value={saldoContable}
                        valueStyle={{ color: '#722ed1' }}
                      />
                    </Card>
                  </Col>
                </Row>

                {hayDiferencia && (
                  <Alert
                    type="warning"
                    showIcon
                    style={{ marginBottom: 16 }}
                    message={`El saldo contable y el stock físico no coinciden (diferencia: ${diferencia.toFixed(2)} ${productInfo?.base_unit || ''})`}
                    description="Esta diferencia corresponde a movimientos contables (por ejemplo, correcciones de cierre) que se registraron en el kardex sin crear un lote físico. No es un error de cálculo: se resuelve con un conteo físico. Mientras tanto, lo que se puede despachar es el Stock Actual (físico)."
                  />
                )}
              </>
            );
          })()}

          {/* Kardex Table */}
          <Card title={`Kardex (${kardex.length} movimientos)`}>
            <ResponsiveTable
              mobileColumns={mobileColumns}
              desktopColumns={desktopColumns}
              dataSource={kardex}
              rowKey={(record, index) => `${record.date}-${index}`}
              entityName="movimientos"
              pagination={{
                pageSize: 50,
                showSizeChanger: true,
                showTotal: (total) => `Total: ${total} movimientos`,
              }}
            />
          </Card>
        </>
      )}
    </div>
  );
};

export default InventoryKardex;
