import React, { useState } from 'react';
import { Card, Row, Col, DatePicker, Button, Tag, Select, message, Spin } from 'antd';
import { BarChartOutlined, FileExcelOutlined, FilePdfOutlined, ReloadOutlined, FilterOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import ResponsiveTable from '../../components/ResponsiveTable';
import { useQuery } from '@tanstack/react-query';
import { inventoryApi, productsApi, locationsApi, reportExportsApi } from '../../services/api';
import usePermissions from '../../hooks/usePermissions';
import dayjs, { Dayjs } from 'dayjs';

const { RangePicker } = DatePicker;
const { Option } = Select;

interface Movement {
  id: string;
  type: 'entry' | 'exit' | 'transfer' | 'application' | 'adjustment';
  product_name: string;
  product_code: string;
  brand_name: string;
  location_name: string;
  location_type: string;
  origin_location_id: string;
  origin_location_name: string;
  destination_location_id: string;
  destination_location_name: string;
  quantity: number;
  unit: string;
  packaging_unit_name: string;
  base_quantity: number;
  base_unit: string;
  total_quantity_in_base_unit: number;
  unit_price: number;
  total_price: number;
  responsible_user_name: string;
  observations: string;
  movement_date: string;
  created_at: string;
}

const InventoryMovementsReport: React.FC = () => {
  const [dateRange, setDateRange] = useState<[Dayjs | null, Dayjs | null] | null>(null);
  const [locationId, setLocationId] = useState<string | undefined>(undefined);
  const [productId, setProductId] = useState<string | undefined>(undefined);
  const [type, setType] = useState<string | undefined>(undefined);
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

  // Fetch movements
  const { data: movementsData, isLoading, refetch } = useQuery({
    queryKey: ['inventory-movements', dateRange, locationId, productId, type],
    queryFn: () => {
      const params: any = {};

      if (dateRange && dateRange[0] && dateRange[1]) {
        params.start_date = dateRange[0].format('YYYY-MM-DD');
        params.end_date = dateRange[1].format('YYYY-MM-DD');
      }

      if (locationId) params.location_id = locationId;
      if (productId) params.product_id = productId;
      if (type) params.type = type;

      return inventoryApi.getMovements(params);
    },
    enabled: shouldFetch,
  });

  const movements: Movement[] = movementsData?.data || [];

  const handleGenerateReport = () => {
    if (!dateRange || !dateRange[0] || !dateRange[1]) {
      message.warning('Por favor selecciona un rango de fechas');
      return;
    }
    setShouldFetch(true);
    refetch();
  };

  const handleClearFilters = () => {
    setDateRange(null);
    setLocationId(undefined);
    setProductId(undefined);
    setType(undefined);
    setShouldFetch(false);
  };

  const handleExportExcel = async () => {
    try {
      setIsExporting(true);
      const params: any = {};

      if (dateRange && dateRange[0] && dateRange[1]) {
        params.start_date = dateRange[0].format('YYYY-MM-DD');
        params.end_date = dateRange[1].format('YYYY-MM-DD');
      }

      if (productId) params.product_id = productId;
      if (locationId) params.location_id = locationId;
      if (type) params.type = type;

      await reportExportsApi.exportMovementsExcel(params);
      message.success('Reporte exportado exitosamente a Excel');
    } catch (error) {
      message.error('Error al exportar el reporte a Excel');
    } finally {
      setIsExporting(false);
    }
  };

  const handleExportPDF = async () => {
    try {
      setIsExporting(true);
      const params: any = {};

      if (dateRange && dateRange[0] && dateRange[1]) {
        params.start_date = dateRange[0].format('YYYY-MM-DD');
        params.end_date = dateRange[1].format('YYYY-MM-DD');
      }

      if (productId) params.product_id = productId;
      if (locationId) params.location_id = locationId;
      if (type) params.type = type;

      await reportExportsApi.exportMovementsPdf(params);
      message.success('Reporte exportado exitosamente a PDF');
    } catch (error) {
      message.error('Error al exportar el reporte a PDF');
    } finally {
      setIsExporting(false);
    }
  };

  const getTypeTag = (type: string) => {
    const typeConfig: Record<string, { color: string; label: string }> = {
      entry: { color: 'green', label: 'Entrada' },
      exit: { color: 'red', label: 'Salida' },
      transfer: { color: 'blue', label: 'Transferencia' },
      application: { color: 'orange', label: 'Aplicación' },
      adjustment: { color: 'purple', label: 'Ajuste' },
    };

    const config = typeConfig[type] || { color: 'default', label: type };
    return <Tag color={config.color}>{config.label}</Tag>;
  };

  const mobileColumns: ColumnsType<Movement> = [
    {
      title: 'Movimiento',
      key: 'movement',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: '14px', marginBottom: 4 }}>
            {record.product_name}
          </div>
          <div style={{ fontSize: '12px', color: '#666', marginBottom: 4 }}>
            {record.brand_name}
          </div>
          <div style={{ fontSize: '11px', color: '#999', marginBottom: 4 }}>
            {record.origin_location_name && `Origen: ${record.origin_location_name}`}
            {record.origin_location_name && record.destination_location_name && ' → '}
            {record.destination_location_name && `Destino: ${record.destination_location_name}`}
          </div>
          <div style={{ marginBottom: 4 }}>
            {getTypeTag(record.type)}
          </div>
          <div style={{ fontSize: '12px', color: '#999' }}>
            {dayjs(record.movement_date || record.created_at).format('DD/MM/YYYY')}
          </div>
        </div>
      ),
    },
    {
      title: 'Cantidad',
      key: 'quantity',
      width: 140,
      render: (_, record) => {
        const sign = record.type === 'exit' ? '-' : '+';
        const color = record.type === 'exit' ? '#cf1322' : '#3f8600';
        const hasPackaging = record.base_quantity && record.base_quantity > 1;
        const displayQty = hasPackaging
          ? (record.total_quantity_in_base_unit || 0)
          : (record.quantity || 0);
        const displayUnit = record.base_unit || record.unit;

        return (
          <div style={{ textAlign: 'right' }}>
            <div style={{ fontWeight: 600, fontSize: '13px', color, marginBottom: 2 }}>
              {sign}{displayQty.toLocaleString()} {displayUnit}
            </div>
            {hasPackaging && (
              <div style={{ fontSize: '11px', color: '#999', marginBottom: 4 }}>
                ({record.quantity} {record.unit})
              </div>
            )}
            <div style={{ fontSize: '11px', color: '#999' }}>
              ${(record.total_price || 0).toLocaleString('es-CO')}
            </div>
          </div>
        );
      },
    },
  ];

  const desktopColumns: ColumnsType<Movement> = [
    {
      title: 'Fecha',
      dataIndex: 'movement_date',
      key: 'movement_date',
      width: 150,
      render: (_: string, record) => dayjs(record.movement_date || record.created_at).format('DD/MM/YYYY'),
      sorter: (a, b) => dayjs(a.movement_date || a.created_at).unix() - dayjs(b.movement_date || b.created_at).unix(),
    },
    {
      title: 'Tipo',
      dataIndex: 'type',
      key: 'type',
      width: 120,
      render: (type: string) => getTypeTag(type),
      filters: [
        { text: 'Entrada', value: 'entry' },
        { text: 'Salida', value: 'exit' },
        { text: 'Transferencia', value: 'transfer' },
        { text: 'Aplicación', value: 'application' },
        { text: 'Ajuste', value: 'adjustment' },
      ],
      onFilter: (value, record) => record.type === value,
    },
    {
      title: 'Producto',
      dataIndex: 'product_name',
      key: 'product_name',
      render: (name: string, record) => (
        <div>
          <div style={{ fontWeight: 500 }}>{name}</div>
          <div style={{ fontSize: '12px', color: '#666' }}>{record.product_code}</div>
        </div>
      ),
    },
    {
      title: 'Marca',
      dataIndex: 'brand_name',
      key: 'brand_name',
      width: 150,
    },
    {
      title: 'Origen',
      dataIndex: 'origin_location_name',
      key: 'origin_location_name',
      width: 150,
      render: (name: string) => name || '-',
    },
    {
      title: 'Destino',
      dataIndex: 'destination_location_name',
      key: 'destination_location_name',
      width: 150,
      render: (name: string) => name || '-',
    },
    {
      title: 'Cantidad',
      key: 'quantity_standardized',
      width: 160,
      align: 'right',
      render: (_, record) => {
        const sign = record.type === 'exit' ? '-' : '+';
        const color = record.type === 'exit' ? '#cf1322' : '#3f8600';
        const hasPackaging = record.base_quantity && record.base_quantity > 1;
        const displayQty = hasPackaging
          ? (record.total_quantity_in_base_unit || 0)
          : (record.quantity || 0);
        const displayUnit = record.base_unit || record.unit;

        return (
          <div style={{ textAlign: 'right' }}>
            <div style={{ color, fontWeight: 600, fontSize: '14px' }}>
              {sign}{displayQty.toLocaleString()} {displayUnit}
            </div>
            {hasPackaging && (
              <div style={{ fontSize: 11, color: '#999' }}>
                ({record.quantity} {record.unit})
              </div>
            )}
          </div>
        );
      },
    },
    {
      title: 'Precio Unit.',
      dataIndex: 'unit_price',
      key: 'unit_price',
      width: 120,
      align: 'right',
      render: (price: number) => `$${(price || 0).toLocaleString('es-CO')}`,
    },
    {
      title: 'Total',
      dataIndex: 'total_price',
      key: 'total_price',
      width: 130,
      align: 'right',
      render: (price: number) => (
        <span style={{ fontWeight: 500 }}>
          ${(price || 0).toLocaleString('es-CO')}
        </span>
      ),
    },
    {
      title: 'Usuario',
      dataIndex: 'responsible_user_name',
      key: 'responsible_user_name',
      width: 150,
    },
  ];

  const expandedRowRender = (record: Movement) => (
    <div style={{ padding: '12px' }}>
      <Row gutter={[16, 8]}>
        <Col span={24}>
          <strong>Observaciones:</strong>
          <div style={{ marginTop: 4, color: '#666' }}>
            {record.observations || 'Sin observaciones'}
          </div>
        </Col>
      </Row>
    </div>
  );

  const totalEntries = movements.filter(m => m?.type === 'entry').length;
  const totalExits = movements.filter(m => m?.type === 'exit').length;
  const totalValue = movements.reduce((sum, m) => sum + (m?.total_price || 0), 0);

  return (
    <div>
      <div style={{ marginBottom: 16 }}>
        <h1 style={{ margin: 0, color: '#2E7D32' }}>
          <BarChartOutlined /> Reporte de Movimientos de Inventario
        </h1>
        <p style={{ color: '#666', margin: 0 }}>
          Consulta detallada de todos los movimientos de inventario
        </p>
      </div>

      {/* Filters Card */}
      <Card style={{ marginBottom: 16 }} title={<><FilterOutlined /> Filtros</>}>
        <Row gutter={[16, 16]}>
          <Col xs={24} sm={12} md={8} lg={6}>
            <div style={{ marginBottom: 8, fontWeight: 500 }}>Rango de Fechas *</div>
            <RangePicker
              value={dateRange}
              onChange={(dates) => setDateRange(dates)}
              style={{ width: '100%' }}
              format="DD/MM/YYYY"
              placeholder={['Fecha inicio', 'Fecha fin']}
            />
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
                  {product.name}
                </Option>
              ))}
            </Select>
          </Col>

          <Col xs={24} sm={12} md={8} lg={6}>
            <div style={{ marginBottom: 8, fontWeight: 500 }}>Tipo de Movimiento</div>
            <Select
              value={type}
              onChange={setType}
              style={{ width: '100%' }}
              placeholder="Todos los tipos"
              allowClear
            >
              <Option value="entry">Entrada</Option>
              <Option value="exit">Salida</Option>
              <Option value="transfer">Transferencia</Option>
              <Option value="application">Aplicación</Option>
              <Option value="adjustment">Ajuste</Option>
            </Select>
          </Col>
        </Row>

        <Row gutter={16} style={{ marginTop: 16 }}>
          <Col>
            <Button
              type="primary"
              icon={<BarChartOutlined />}
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
          {shouldFetch && movements.length > 0 && hasPermission('export_reports') && (
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
            </>
          )}
        </Row>
      </Card>

      {/* Results */}
      {isLoading && (
        <Card>
          <div style={{ textAlign: 'center', padding: '40px' }}>
            <Spin size="large" />
            <div style={{ marginTop: 16, color: '#666' }}>Generando reporte...</div>
          </div>
        </Card>
      )}

      {!isLoading && shouldFetch && movements.length === 0 && (
        <Card>
          <div style={{ textAlign: 'center', padding: '40px', color: '#999' }}>
            No se encontraron movimientos con los filtros seleccionados
          </div>
        </Card>
      )}

      {!isLoading && shouldFetch && movements.length > 0 && (
        <>
          {/* Summary Stats */}
          <Row gutter={16} style={{ marginBottom: 16 }}>
            <Col xs={24} sm={8}>
              <Card>
                <div style={{ fontSize: '14px', color: '#666' }}>Total Movimientos</div>
                <div style={{ fontSize: '24px', fontWeight: 'bold', color: '#1890ff' }}>
                  {movements.length}
                </div>
              </Card>
            </Col>
            <Col xs={24} sm={8}>
              <Card>
                <div style={{ fontSize: '14px', color: '#666' }}>Entradas / Salidas</div>
                <div style={{ fontSize: '24px', fontWeight: 'bold' }}>
                  <span style={{ color: '#3f8600' }}>{totalEntries}</span>
                  {' / '}
                  <span style={{ color: '#cf1322' }}>{totalExits}</span>
                </div>
              </Card>
            </Col>
            <Col xs={24} sm={8}>
              <Card>
                <div style={{ fontSize: '14px', color: '#666' }}>Valor Total</div>
                <div style={{ fontSize: '24px', fontWeight: 'bold', color: '#2E7D32' }}>
                  ${totalValue.toLocaleString('es-CO')}
                </div>
              </Card>
            </Col>
          </Row>

          {/* Movements Table */}
          <Card title={`Movimientos (${movements.length})`}>
            <ResponsiveTable
              mobileColumns={mobileColumns}
              desktopColumns={desktopColumns}
              dataSource={movements}
              rowKey="id"
              expandedRowRender={expandedRowRender}
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

export default InventoryMovementsReport;
