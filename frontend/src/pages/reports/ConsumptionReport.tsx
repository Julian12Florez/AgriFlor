import React, { useState } from 'react';
import { Card, Row, Col, Button, Select, message, Spin, Tag, Statistic, Alert, DatePicker } from 'antd';
import {
  ExperimentOutlined,
  FileExcelOutlined,
  FilePdfOutlined,
  ReloadOutlined,
  FilterOutlined,
  PrinterOutlined,
  CheckCircleOutlined,
} from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import ResponsiveTable from '../../components/ResponsiveTable';
import { useQuery } from '@tanstack/react-query';
import { productsApi, locationsApi, inventoryApi, reportExportsApi } from '../../services/api';
import dayjs from 'dayjs';
import usePermissions from '../../hooks/usePermissions';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell } from 'recharts';

const { Option } = Select;
const { RangePicker } = DatePicker;

interface ConsumptionItem {
  output_id: string;
  output_number: string;
  output_date: string;
  product_id: string;
  product_name: string;
  product_code: string;
  category: string;
  brand_name: string;
  quantity: number;
  unit: string;
  unit_full_name: string;
  quantity_with_unit: string;
  base_quantity?: number;
  base_unit?: string;
  total_base_quantity?: number;
  origin_location_id: string;
  origin_location_name: string;
  destination_location_id: string;
  destination_location_name: string;
  farm_lots: Array<{
    lot_id: string;
    lot_name: string;
    area: number;
    area_unit: string;
  }>;
  lots_count: number;
  lots_names: string;
  observations?: string;
}

interface ConsumptionReportData {
  success: boolean;
  filters: {
    start_date?: string;
    end_date?: string;
    location_id?: string;
    product_id?: string;
  };
  consumptions: ConsumptionItem[];
  summary: {
    total_consumptions: number;
    total_quantity_consumed: number;
    outputs_count: number;
  };
  by_product: Array<{
    product_id: string;
    product_name: string;
    product_code: string;
    category: string;
    total_quantity: number;
    consumption_count: number;
    unit: string;
    unit_full_name: string;
  }>;
  by_location: Array<{
    location_id: string;
    location_name: string;
    location_type: string;
    total_quantity: number;
    consumption_count: number;
    products_count: number;
  }>;
}

const ConsumptionReport: React.FC = () => {
  const [productId, setProductId] = useState<string | undefined>(undefined);
  const [locationId, setLocationId] = useState<string | undefined>(undefined);
  const [dateRange, setDateRange] = useState<[dayjs.Dayjs | null, dayjs.Dayjs | null] | null>(null);
  const [shouldFetch, setShouldFetch] = useState(false);
  const [reportData, setReportData] = useState<ConsumptionReportData | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [isExporting, setIsExporting] = useState(false);

  const { hasPermission } = usePermissions();

  // Fetch products for filter
  const { data: productsData } = useQuery({
    queryKey: ['products-all'],
    queryFn: () => productsApi.list({ per_page: 1000 }),
  });

  // Fetch locations for filter (only farms for consumption)
  const { data: locationsData } = useQuery({
    queryKey: ['locations-farms'],
    queryFn: () => locationsApi.list({ per_page: 1000, type: 'farm' }),
  });

  const handleGenerateReport = async () => {
    setShouldFetch(true);
    setIsLoading(true);

    try {
      const params: any = {};
      if (productId) params.product_id = productId;
      if (locationId) params.location_id = locationId;
      if (dateRange && dateRange[0]) params.start_date = dateRange[0].format('YYYY-MM-DD');
      if (dateRange && dateRange[1]) params.end_date = dateRange[1].format('YYYY-MM-DD');

      const response = await inventoryApi.getConsumptionReport(params);
      setReportData(response as any);
      message.success('Reporte generado exitosamente');
    } catch (error: any) {
      message.error(error.message || 'Error al generar el reporte');
      console.error('Error generating consumption report:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const handleClearFilters = () => {
    setProductId(undefined);
    setLocationId(undefined);
    setDateRange(null);
    setShouldFetch(false);
    setReportData(null);
  };

  const handleExportExcel = async () => {
    try {
      setIsExporting(true);
      const params: any = {};
      if (productId) params.product_id = productId;
      if (locationId) params.location_id = locationId;
      if (dateRange && dateRange[0]) params.start_date = dateRange[0].format('YYYY-MM-DD');
      if (dateRange && dateRange[1]) params.end_date = dateRange[1].format('YYYY-MM-DD');

      await reportExportsApi.exportConsumptionExcel(params);
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
      const params: any = {};
      if (productId) params.product_id = productId;
      if (locationId) params.location_id = locationId;
      if (dateRange && dateRange[0]) params.start_date = dateRange[0].format('YYYY-MM-DD');
      if (dateRange && dateRange[1]) params.end_date = dateRange[1].format('YYYY-MM-DD');

      await reportExportsApi.exportConsumptionPdf(params);
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

  const consumptions = reportData?.consumptions || [];
  const summary = reportData?.summary || {
    total_consumptions: 0,
    total_quantity_consumed: 0,
    outputs_count: 0,
  };
  const byProduct = reportData?.by_product || [];
  const byLocation = reportData?.by_location || [];

  // Helper function to get full packaging unit name
  const getFullPackagingUnitName = (unit: string, baseQuantity?: number, baseUnit?: string) => {
    if (baseQuantity && baseQuantity > 1 && baseUnit) {
      return `${unit} de ${baseQuantity} ${baseUnit}`;
    }
    return unit || 'unidades';
  };

  // Columns for consumption table
  const consumptionMobileColumns: ColumnsType<ConsumptionItem> = [
    {
      title: 'Producto',
      key: 'product',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: '14px', marginBottom: 4 }}>
            {record.product_name}
          </div>
          <div style={{ fontSize: '12px', color: '#666', marginBottom: 4 }}>
            {record.brand_name} • {record.product_code}
          </div>
          <div style={{ fontSize: '11px', color: '#999' }}>
            {dayjs(record.output_date).format('DD/MM/YYYY')}
          </div>
        </div>
      ),
    },
    {
      title: 'Consumo',
      key: 'consumption',
      width: 120,
      render: (_, record) => {
        const fullUnitName = getFullPackagingUnitName(record.unit, record.base_quantity, record.base_unit);
        const hasPackaging = record.base_quantity && record.base_quantity > 1;

        return (
          <div style={{ textAlign: 'right' }}>
            <div style={{ fontWeight: 500, fontSize: '14px', marginBottom: 4 }}>
              {record.quantity.toLocaleString()} {fullUnitName}
            </div>
            {hasPackaging && (
              <div style={{ fontSize: '12px', color: '#2E7D32', fontWeight: 500, marginBottom: 4 }}>
                Total: {(record.total_base_quantity || 0).toLocaleString()} {record.base_unit}
              </div>
            )}
            <div style={{ fontSize: '11px', color: '#999', marginTop: 4 }}>
              {record.lots_count} lote(s)
            </div>
          </div>
        );
      },
    },
  ];

  const consumptionDesktopColumns: ColumnsType<ConsumptionItem> = [
    {
      title: 'Fecha',
      dataIndex: 'output_date',
      key: 'output_date',
      width: 120,
      render: (date: string) => dayjs(date).format('DD/MM/YYYY'),
      sorter: (a, b) => dayjs(a.output_date).unix() - dayjs(b.output_date).unix(),
    },
    {
      title: 'Salida',
      dataIndex: 'output_number',
      key: 'output_number',
      width: 120,
    },
    {
      title: 'Producto',
      dataIndex: 'product_name',
      key: 'product_name',
      render: (name: string, record) => (
        <div>
          <div style={{ fontWeight: 500 }}>{name}</div>
          <div style={{ fontSize: '12px', color: '#666' }}>
            {record.product_code} • {record.category}
          </div>
        </div>
      ),
      sorter: (a, b) => a.product_name.localeCompare(b.product_name),
    },
    {
      title: 'Marca',
      dataIndex: 'brand_name',
      key: 'brand_name',
      width: 120,
    },
    {
      title: 'Cantidad',
      dataIndex: 'quantity',
      key: 'quantity',
      width: 140,
      align: 'right',
      render: (qty: number, record) => {
        const fullUnitName = getFullPackagingUnitName(record.unit, record.base_quantity, record.base_unit);
        return (
          <div style={{ textAlign: 'right', fontWeight: 500 }}>
            {qty.toLocaleString()} {fullUnitName}
          </div>
        );
      },
      sorter: (a, b) => a.quantity - b.quantity,
    },
    {
      title: 'Cantidad Total',
      dataIndex: 'total_base_quantity',
      key: 'total_base_quantity',
      width: 140,
      align: 'right',
      render: (totalBase: number | undefined, record) => {
        const hasPackaging = record.base_quantity && record.base_quantity > 1;
        if (!hasPackaging) return '-';

        return (
          <div style={{ textAlign: 'right', fontWeight: 500, color: '#2E7D32' }}>
            {(totalBase || 0).toLocaleString()} {record.base_unit}
          </div>
        );
      },
      sorter: (a, b) => (a.total_base_quantity || 0) - (b.total_base_quantity || 0),
    },
    {
      title: 'Finca',
      dataIndex: 'destination_location_name',
      key: 'destination_location_name',
      width: 150,
    },
    {
      title: 'Lotes',
      dataIndex: 'lots_names',
      key: 'lots_names',
      width: 200,
      render: (lotsNames: string, record) => (
        <div>
          <div>{lotsNames || 'N/A'}</div>
          <div style={{ fontSize: '11px', color: '#999' }}>
            ({record.lots_count} lote{record.lots_count !== 1 ? 's' : ''})
          </div>
        </div>
      ),
    },
  ];

  const consumptionExpandedRowRender = (record: ConsumptionItem) => (
    <div style={{ padding: '12px' }}>
      <Row gutter={16}>
        <Col span={12}>
          <h4 style={{ marginBottom: 8 }}>Origen:</h4>
          <div>{record.origin_location_name}</div>
        </Col>
        <Col span={12}>
          <h4 style={{ marginBottom: 8 }}>Lotes donde se aplicó:</h4>
          {record.farm_lots && record.farm_lots.length > 0 ? (
            record.farm_lots.map((lot, index) => (
              <div key={index} style={{ marginBottom: 4 }}>
                <Tag color="green">{lot.lot_name}</Tag>
                {lot.area > 0 && (
                  <span style={{ fontSize: '12px', color: '#666', marginLeft: 8 }}>
                    {lot.area} {lot.area_unit}
                  </span>
                )}
              </div>
            ))
          ) : (
            <div style={{ color: '#999' }}>Sin lotes asociados</div>
          )}
        </Col>
      </Row>
      {record.observations && (
        <Row style={{ marginTop: 12 }}>
          <Col span={24}>
            <h4 style={{ marginBottom: 8 }}>Observaciones:</h4>
            <div style={{ color: '#666' }}>{record.observations}</div>
          </Col>
        </Row>
      )}
    </div>
  );

  // Data for charts
  const topProductsByQuantity =
    byProduct.length > 0
      ? [...byProduct]
          .sort((a, b) => b.total_quantity - a.total_quantity)
          .slice(0, 10)
          .map((item) => ({
            name: item.product_name.substring(0, 20) + (item.product_name.length > 20 ? '...' : ''),
            value: item.total_quantity,
            unit: item.unit,
          }))
      : [];

  const locationData =
    byLocation.length > 0
      ? byLocation.map((item) => ({
          name: item.location_name,
          value: item.consumption_count,
          consumptions: item.consumption_count,
        }))
      : [];

  const COLORS = ['#2E7D32', '#4CAF50', '#66BB6A', '#81C784', '#A5D6A7', '#C8E6C9', '#E8F5E9'];

  return (
    <div>
      <div style={{ marginBottom: 16 }}>
        <h1 style={{ margin: 0, color: '#2E7D32' }}>
          <ExperimentOutlined /> Reporte de Consumo de Productos
        </h1>
        <p style={{ color: '#666', margin: 0 }}>
          Informe de productos consumidos/aplicados en fincas y lotes
        </p>
      </div>

      {/* Filters Card */}
      <Card style={{ marginBottom: 16 }} title={<><FilterOutlined /> Filtros</>}>
        <Row gutter={[16, 16]}>
          <Col xs={24} sm={12} md={8} lg={6}>
            <div style={{ marginBottom: 8, fontWeight: 500 }}>Rango de Fechas</div>
            <RangePicker
              value={dateRange}
              onChange={setDateRange}
              style={{ width: '100%' }}
              format="DD/MM/YYYY"
            />
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
            <div style={{ marginBottom: 8, fontWeight: 500 }}>Finca</div>
            <Select
              value={locationId}
              onChange={setLocationId}
              style={{ width: '100%' }}
              placeholder="Todas las fincas"
              allowClear
            >
              {locationsData?.data?.map((location: any) => (
                <Option key={location.id} value={location.id}>
                  {location.name}
                </Option>
              ))}
            </Select>
          </Col>
        </Row>

        <Row gutter={16} style={{ marginTop: 16 }}>
          <Col>
            <Button
              type="primary"
              icon={<ExperimentOutlined />}
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
          {shouldFetch && consumptions.length > 0 && hasPermission('export_reports') && (
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
      {!isLoading && shouldFetch && consumptions.length === 0 && (
        <Alert
          message="Sin consumos registrados"
          description="No se encontraron consumos de productos con los filtros seleccionados"
          type="warning"
          showIcon
        />
      )}

      {/* Results */}
      {!isLoading && shouldFetch && consumptions.length > 0 && (
        <>
          {/* Summary Stats */}
          <Row gutter={16} style={{ marginBottom: 16 }}>
            <Col xs={24} sm={8}>
              <Card>
                <Statistic
                  title="Total Salidas"
                  value={summary.outputs_count}
                  valueStyle={{ color: '#1890ff' }}
                  prefix={<CheckCircleOutlined />}
                />
              </Card>
            </Col>
            <Col xs={24} sm={8}>
              <Card>
                <Statistic
                  title="Total Aplicaciones"
                  value={summary.total_consumptions}
                  valueStyle={{ color: '#2E7D32' }}
                />
              </Card>
            </Col>
            <Col xs={24} sm={8}>
              <Card>
                <Statistic
                  title="Cantidad Consumida"
                  value={summary.total_quantity_consumed.toFixed(2)}
                  valueStyle={{ color: '#722ed1' }}
                />
              </Card>
            </Col>
          </Row>

          {/* Charts */}
          {topProductsByQuantity.length > 0 && (
            <Row gutter={16} style={{ marginBottom: 16 }}>
              <Col xs={24} lg={12}>
                <Card title="Top 10 Productos Consumidos">
                  <ResponsiveContainer width="100%" height={300}>
                    <BarChart data={topProductsByQuantity} layout="vertical" margin={{ left: 100 }}>
                      <CartesianGrid strokeDasharray="3 3" />
                      <XAxis type="number" />
                      <YAxis type="category" dataKey="name" width={100} />
                      <Tooltip
                        formatter={(value: number, name: string, props: any) =>
                          `${value.toLocaleString()} ${props.payload.unit || 'unidades'}`
                        }
                      />
                      <Bar dataKey="value" fill="#2E7D32" />
                    </BarChart>
                  </ResponsiveContainer>
                </Card>
              </Col>
              <Col xs={24} lg={12}>
                <Card title="Consumos por Finca">
                  <ResponsiveContainer width="100%" height={300}>
                    <PieChart>
                      <Pie
                        data={locationData}
                        cx="50%"
                        cy="50%"
                        labelLine={false}
                        label={(entry) => `${entry.name}: ${entry.value} consumos`}
                        outerRadius={100}
                        fill="#8884d8"
                        dataKey="value"
                      >
                        {locationData.map((entry, index) => (
                          <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                        ))}
                      </Pie>
                      <Tooltip formatter={(value: number) => `${value} consumos`} />
                    </PieChart>
                  </ResponsiveContainer>
                </Card>
              </Col>
            </Row>
          )}

          {/* Consumption Table */}
          <Card title={`Consumos Detallados (${consumptions.length})`}>
            <ResponsiveTable
              mobileColumns={consumptionMobileColumns}
              desktopColumns={consumptionDesktopColumns}
              dataSource={consumptions}
              rowKey={(record) => `${record.output_id}-${record.product_id}`}
              expandedRowRender={consumptionExpandedRowRender}
              entityName="consumos"
              pagination={{
                pageSize: 50,
                showSizeChanger: true,
                showTotal: (total) => `Total: ${total} consumos`,
              }}
            />
          </Card>
        </>
      )}
    </div>
  );
};

export default ConsumptionReport;
