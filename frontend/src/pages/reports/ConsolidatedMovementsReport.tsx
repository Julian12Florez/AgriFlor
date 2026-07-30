import React, { useState } from 'react';
import { Card, Row, Col, DatePicker, Button, Select, message, Spin, Statistic, Tabs, Table } from 'antd';
import {
  LineChartOutlined,
  FileExcelOutlined,
  FilePdfOutlined,
  ReloadOutlined,
  FilterOutlined,
  RiseOutlined,
  FallOutlined,
  SwapOutlined,
} from '@ant-design/icons';
import { useQuery } from '@tanstack/react-query';
import { inventoryApi, productsApi, locationsApi } from '../../services/api';
import dayjs, { Dayjs } from 'dayjs';
import { buildMovementTypeParams } from '../../utils/movementFilters';
import {
  LineChart,
  Line,
  BarChart,
  Bar,
  PieChart,
  Pie,
  Cell,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer,
} from 'recharts';

const { RangePicker } = DatePicker;
const { Option } = Select;

interface ReportData {
  success: boolean;
  filters: any;
  movements: any[];
  summary: {
    total_movements: number;
    total_entries: number;
    total_exits: number;
    total_value_entries: number;
    total_value_exits: number;
    net_change: number;
    net_value_change: number;
  };
  by_product: Array<{
    product_id: string;
    product_name: string;
    product_code: string;
    category: string;
    total_entries: number;
    total_exits: number;
    total_value_entries: number;
    total_value_exits: number;
    movements_count: number;
  }>;
  by_location: Array<{
    location_id: string;
    location_name: string;
    location_type: string;
    total_entries: number;
    total_exits: number;
    total_value_entries: number;
    total_value_exits: number;
    movements_count: number;
  }>;
  by_type: Record<string, {
    count: number;
    total_quantity: number;
    total_value: number;
  }>;
  by_day: Array<{
    date: string;
    total_entries: number;
    total_exits: number;
    total_value_entries: number;
    total_value_exits: number;
    movements_count: number;
  }>;
}

const ConsolidatedMovementsReport: React.FC = () => {
  const [dateRange, setDateRange] = useState<[Dayjs | null, Dayjs | null] | null>(null);
  const [locationId, setLocationId] = useState<string | undefined>(undefined);
  const [productId, setProductId] = useState<string | undefined>(undefined);
  const [type, setType] = useState<string | undefined>(undefined);
  const [shouldFetch, setShouldFetch] = useState(false);

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

  // Fetch consolidated report
  const { data: reportData, isLoading, refetch } = useQuery({
    queryKey: ['inventory-movements-report', dateRange, locationId, productId, type],
    queryFn: () => {
      const params: any = {};

      if (dateRange && dateRange[0] && dateRange[1]) {
        params.start_date = dateRange[0].format('YYYY-MM-DD');
        params.end_date = dateRange[1].format('YYYY-MM-DD');
      }

      if (locationId) params.location_id = locationId;
      if (productId) params.product_id = productId;
      Object.assign(params, buildMovementTypeParams(type));

      return inventoryApi.getMovementsReport(params);
    },
    enabled: shouldFetch,
  });

  const report: ReportData | null = (reportData as unknown as ReportData) || null;

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

  const handleExportExcel = () => {
    message.info('Funcionalidad de exportación a Excel en desarrollo');
  };

  const handleExportPDF = () => {
    message.info('Funcionalidad de exportación a PDF en desarrollo');
  };

  // Chart colors
  const COLORS = {
    entry: '#52c41a',
    exit: '#f5222d',
    transfer: '#1890ff',
    application: '#fa8c16',
    adjustment: '#722ed1',
  };

  const PIE_COLORS = ['#52c41a', '#f5222d', '#1890ff', '#fa8c16', '#722ed1', '#13c2c2'];

  // Type translations
  const typeLabels: Record<string, string> = {
    entry: 'Entrada',
    exit: 'Salida',
    transfer: 'Transferencia',
    application: 'Aplicación',
    adjustment: 'Ajuste',
  };

  return (
    <div>
      <div style={{ marginBottom: 16 }}>
        <h1 style={{ margin: 0, color: '#2E7D32' }}>
          <LineChartOutlined /> Reporte Consolidado con Análisis
        </h1>
        <p style={{ color: '#666', margin: 0 }}>
          Análisis estadístico y gráficas de movimientos de inventario
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
              icon={<LineChartOutlined />}
              onClick={handleGenerateReport}
              loading={isLoading}
            >
              Generar Análisis
            </Button>
          </Col>
          <Col>
            <Button icon={<ReloadOutlined />} onClick={handleClearFilters}>
              Limpiar Filtros
            </Button>
          </Col>
          {shouldFetch && report && (
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
            </>
          )}
        </Row>
      </Card>

      {/* Loading */}
      {isLoading && (
        <Card>
          <div style={{ textAlign: 'center', padding: '40px' }}>
            <Spin size="large" />
            <div style={{ marginTop: 16, color: '#666' }}>Generando análisis...</div>
          </div>
        </Card>
      )}

      {/* No Data */}
      {!isLoading && shouldFetch && !report && (
        <Card>
          <div style={{ textAlign: 'center', padding: '40px', color: '#999' }}>
            No se encontraron datos con los filtros seleccionados
          </div>
        </Card>
      )}

      {/* Results */}
      {!isLoading && shouldFetch && report && (
        <>
          {/* Summary Statistics */}
          <Row gutter={16} style={{ marginBottom: 16 }}>
            <Col xs={12} sm={8} md={6}>
              <Card>
                <Statistic
                  title="Total Movimientos"
                  value={report.summary.total_movements}
                  valueStyle={{ color: '#1890ff' }}
                  prefix={<SwapOutlined />}
                />
              </Card>
            </Col>
            <Col xs={12} sm={8} md={6}>
              <Card>
                <Statistic
                  title="Total Entradas"
                  value={report.summary.total_entries}
                  valueStyle={{ color: '#3f8600' }}
                  prefix={<RiseOutlined />}
                />
              </Card>
            </Col>
            <Col xs={12} sm={8} md={6}>
              <Card>
                <Statistic
                  title="Total Salidas"
                  value={report.summary.total_exits}
                  valueStyle={{ color: '#cf1322' }}
                  prefix={<FallOutlined />}
                />
              </Card>
            </Col>
            <Col xs={12} sm={8} md={6}>
              <Card>
                <Statistic
                  title="Cambio Neto"
                  value={report.summary.net_change}
                  valueStyle={{
                    color: report.summary.net_change >= 0 ? '#3f8600' : '#cf1322',
                  }}
                  prefix={report.summary.net_change >= 0 ? <RiseOutlined /> : <FallOutlined />}
                />
              </Card>
            </Col>
          </Row>

          <Row gutter={16} style={{ marginBottom: 16 }}>
            <Col xs={12} sm={12} md={8}>
              <Card>
                <Statistic
                  title="Valor Total Entradas"
                  value={report.summary.total_value_entries}
                  precision={0}
                  valueStyle={{ color: '#3f8600', fontSize: '20px' }}
                  prefix="$"
                />
              </Card>
            </Col>
            <Col xs={12} sm={12} md={8}>
              <Card>
                <Statistic
                  title="Valor Total Salidas"
                  value={report.summary.total_value_exits}
                  precision={0}
                  valueStyle={{ color: '#cf1322', fontSize: '20px' }}
                  prefix="$"
                />
              </Card>
            </Col>
            <Col xs={24} sm={24} md={8}>
              <Card>
                <Statistic
                  title="Cambio Neto en Valor"
                  value={Math.abs(report.summary.net_value_change)}
                  precision={0}
                  valueStyle={{
                    color: report.summary.net_value_change >= 0 ? '#3f8600' : '#cf1322',
                    fontSize: '20px',
                  }}
                  prefix={report.summary.net_value_change >= 0 ? '+$' : '-$'}
                />
              </Card>
            </Col>
          </Row>

          {/* Charts and Tables */}
          <Tabs
            defaultActiveKey="timeline"
            items={[
              {
                key: 'timeline',
                label: 'Línea de Tiempo',
                children: (
                  <Card>
                    <h3>Movimientos por Día</h3>
                    <ResponsiveContainer width="100%" height={400}>
                      <LineChart data={report.by_day}>
                        <CartesianGrid strokeDasharray="3 3" />
                        <XAxis
                          dataKey="date"
                          tickFormatter={(value) => dayjs(value).format('DD/MM')}
                        />
                        <YAxis />
                        <Tooltip
                          labelFormatter={(value) => dayjs(value).format('DD/MM/YYYY')}
                        />
                        <Legend />
                        <Line
                          type="monotone"
                          dataKey="total_entries"
                          stroke={COLORS.entry}
                          name="Entradas"
                          strokeWidth={2}
                        />
                        <Line
                          type="monotone"
                          dataKey="total_exits"
                          stroke={COLORS.exit}
                          name="Salidas"
                          strokeWidth={2}
                        />
                        <Line
                          type="monotone"
                          dataKey={(item) => item.total_entries - item.total_exits}
                          stroke="#1890ff"
                          name="Cambio Neto"
                          strokeWidth={2}
                        />
                      </LineChart>
                    </ResponsiveContainer>
                  </Card>
                ),
              },
              {
                key: 'by_type',
                label: 'Por Tipo',
                children: (
                  <Row gutter={16}>
                    <Col xs={24} lg={12}>
                      <Card title="Distribución por Tipo">
                        <ResponsiveContainer width="100%" height={300}>
                          <PieChart>
                            <Pie
                              data={Object.entries(report.by_type).map(([type, data]) => ({
                                name: typeLabels[type] || type,
                                value: data.count,
                              }))}
                              cx="50%"
                              cy="50%"
                              labelLine={false}
                              label={(entry) => `${entry.name}: ${entry.value}`}
                              outerRadius={100}
                              fill="#8884d8"
                              dataKey="value"
                            >
                              {Object.keys(report.by_type).map((_, index) => (
                                <Cell
                                  key={`cell-${index}`}
                                  fill={PIE_COLORS[index % PIE_COLORS.length]}
                                />
                              ))}
                            </Pie>
                            <Tooltip />
                          </PieChart>
                        </ResponsiveContainer>
                      </Card>
                    </Col>
                    <Col xs={24} lg={12}>
                      <Card title="Detalles por Tipo">
                        <Table
                          size="small"
                          dataSource={Object.entries(report.by_type).map(([type, data]) => ({
                            type,
                            ...data
                          }))}
                          rowKey="type"
                          pagination={false}
                          scroll={{ x: 'max-content' }}
                          columns={[
                            {
                              title: 'Tipo',
                              dataIndex: 'type',
                              key: 'type',
                              render: (val) => typeLabels[val] || val,
                            },
                            {
                              title: 'Cantidad',
                              dataIndex: 'count',
                              key: 'count',
                              align: 'right',
                            },
                            {
                              title: 'Valor Total',
                              dataIndex: 'total_value',
                              key: 'total_value',
                              align: 'right',
                              render: (val: number) => `$${val.toLocaleString('es-CO')}`,
                            },
                          ]}
                        />
                      </Card>
                    </Col>
                  </Row>
                ),
              },
              {
                key: 'by_product',
                label: 'Por Producto',
                children: (
                  <Card>
                    <h3>Top 10 Productos por Movimientos</h3>
                    <ResponsiveContainer width="100%" height={400}>
                      <BarChart
                        data={report.by_product.slice(0, 10)}
                        layout="vertical"
                        margin={{ left: 100 }}
                      >
                        <CartesianGrid strokeDasharray="3 3" />
                        <XAxis type="number" />
                        <YAxis type="category" dataKey="product_name" width={100} />
                        <Tooltip />
                        <Legend />
                        <Bar dataKey="total_entries" fill={COLORS.entry} name="Entradas" />
                        <Bar dataKey="total_exits" fill={COLORS.exit} name="Salidas" />
                      </BarChart>
                    </ResponsiveContainer>

                    <div style={{ marginTop: 24 }}>
                      <h3>Detalles por Producto</h3>
                      <Table
                        size="small"
                        dataSource={report.by_product}
                        rowKey="product_id"
                        pagination={{ pageSize: 10 }}
                        scroll={{ x: 'max-content' }}
                        columns={[
                          { title: 'Producto', dataIndex: 'product_name', key: 'product_name' },
                          {
                            title: 'Movimientos',
                            dataIndex: 'movements_count',
                            key: 'movements_count',
                            align: 'right',
                          },
                          {
                            title: 'Entradas',
                            dataIndex: 'total_entries',
                            key: 'total_entries',
                            align: 'right',
                          },
                          {
                            title: 'Salidas',
                            dataIndex: 'total_exits',
                            key: 'total_exits',
                            align: 'right',
                          },
                          {
                            title: 'Cambio Neto',
                            key: 'net_change',
                            align: 'right',
                            render: (_, record) => {
                              const netChange = record.total_entries - record.total_exits;
                              return (
                                <span style={{ color: netChange >= 0 ? '#3f8600' : '#cf1322' }}>
                                  {netChange >= 0 ? '+' : ''}
                                  {netChange}
                                </span>
                              );
                            },
                          },
                          {
                            title: 'Valor Total',
                            key: 'total_value',
                            align: 'right',
                            render: (_, record) => {
                              const total = record.total_value_entries + record.total_value_exits;
                              return `$${total.toLocaleString('es-CO')}`;
                            },
                          },
                        ]}
                      />
                    </div>
                  </Card>
                ),
              },
              {
                key: 'by_location',
                label: 'Por Ubicación',
                children: (
                  <Card>
                    <h3>Movimientos por Ubicación</h3>
                    <ResponsiveContainer width="100%" height={400}>
                      <BarChart data={report.by_location}>
                        <CartesianGrid strokeDasharray="3 3" />
                        <XAxis dataKey="location_name" />
                        <YAxis />
                        <Tooltip />
                        <Legend />
                        <Bar dataKey="total_entries" fill={COLORS.entry} name="Entradas" />
                        <Bar dataKey="total_exits" fill={COLORS.exit} name="Salidas" />
                      </BarChart>
                    </ResponsiveContainer>

                    <div style={{ marginTop: 24 }}>
                      <h3>Detalles por Ubicación</h3>
                      <Table
                        size="small"
                        dataSource={report.by_location}
                        rowKey="location_id"
                        pagination={false}
                        scroll={{ x: 'max-content' }}
                        columns={[
                          { title: 'Ubicación', dataIndex: 'location_name', key: 'location_name' },
                          {
                            title: 'Movimientos',
                            dataIndex: 'movements_count',
                            key: 'movements_count',
                            align: 'right',
                          },
                          {
                            title: 'Entradas',
                            dataIndex: 'total_entries',
                            key: 'total_entries',
                            align: 'right',
                          },
                          {
                            title: 'Salidas',
                            dataIndex: 'total_exits',
                            key: 'total_exits',
                            align: 'right',
                          },
                          {
                            title: 'Cambio Neto',
                            key: 'net_change',
                            align: 'right',
                            render: (_, record) => {
                              const netChange = record.total_entries - record.total_exits;
                              return (
                                <span style={{ color: netChange >= 0 ? '#3f8600' : '#cf1322' }}>
                                  {netChange >= 0 ? '+' : ''}
                                  {netChange}
                                </span>
                              );
                            },
                          },
                          {
                            title: 'Valor Total',
                            key: 'total_value',
                            align: 'right',
                            render: (_, record) => {
                              const total = record.total_value_entries + record.total_value_exits;
                              return `$${total.toLocaleString('es-CO')}`;
                            },
                          },
                        ]}
                      />
                    </div>
                  </Card>
                ),
              },
            ]}
          />
        </>
      )}
    </div>
  );
};

export default ConsolidatedMovementsReport;
