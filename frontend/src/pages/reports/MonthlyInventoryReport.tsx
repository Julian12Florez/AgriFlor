import React, { useState, useMemo } from 'react';
import { Card, Row, Col, DatePicker, Button, Select, Spin, Table, Statistic, Tag, message } from 'antd';
import {
  CalendarOutlined,
  FileExcelOutlined,
  ReloadOutlined,
  DatabaseOutlined,
  ShoppingCartOutlined,
  SendOutlined,
  InboxOutlined,
  RiseOutlined,
  FallOutlined,
} from '@ant-design/icons';
import { useQuery } from '@tanstack/react-query';
import { inventoryApi, reportExportsApi, locationsApi } from '../../services/api';
import dayjs, { Dayjs } from 'dayjs';
import {
  BarChart, Bar, PieChart, Pie, Cell,
  XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer,
} from 'recharts';

const { Option } = Select;

const COLORS = ['#2E7D32', '#1976D2', '#F57C00', '#D32F2F', '#7B1FA2', '#00838F', '#558B2F', '#E64A19', '#5D4037', '#455A64'];

interface FarmColumn {
  id: string;
  name: string;
}

interface ProductRow {
  product_id: string;
  product_code: string;
  product_name: string;
  category: string;
  unit: string;
  initial_stock: number;
  purchases: number;
  farm_shipments: Record<string, number>;
  total_shipped: number;
  transfers_out: number;
  returns: number;
  shipments_in: number;
  increases: number;
  decreases: number;
  total_movements: number;
  variation: number;
  final_stock: number;
  current_stock: number;
}

interface MonthlyReportData {
  month: number;
  year: number;
  warehouse: string;
  farm_columns: FarmColumn[];
  products: ProductRow[];
  summary: {
    total_products: number;
    total_with_stock: number;
    total_purchases: number;
    total_shipped: number;
  };
}

const fmt = (v: number) => v ? v.toLocaleString('es-CO', { maximumFractionDigits: 2 }) : '-';

const MonthlyInventoryReport: React.FC = () => {
  const [selectedMonth, setSelectedMonth] = useState<Dayjs>(dayjs());
  const [categoryFilter, setCategoryFilter] = useState<string | undefined>(undefined);
  const [searchText, setSearchText] = useState('');
  const [exporting, setExporting] = useState(false);
  // Ubicación del reporte: undefined = bodega principal; o una finca/bodega específica.
  const [locationId, setLocationId] = useState<string | undefined>(undefined);

  const month = selectedMonth.month() + 1;
  const year = selectedMonth.year();

  // Ubicaciones para el selector (bodegas primero, luego fincas).
  const { data: locationsResp } = useQuery({
    queryKey: ['locations-for-monthly-report'],
    queryFn: () => locationsApi.list({ per_page: 200, status: 'active' }),
  });
  const locations = useMemo(() => {
    const raw: any = (locationsResp as any)?.data ?? [];
    const arr: any[] = Array.isArray(raw) ? raw : (raw?.data ?? []);
    return [...arr].sort((a, b) =>
      a.type === b.type ? String(a.name).localeCompare(String(b.name)) : a.type === 'warehouse' ? -1 : 1
    );
  }, [locationsResp]);

  const { data: reportResponse, isLoading, refetch } = useQuery({
    queryKey: ['monthly-inventory', month, year, locationId],
    queryFn: () => inventoryApi.getMonthlyReport({ month, year, ...(locationId ? { location_id: locationId } : {}) }),
  });

  const reportData: MonthlyReportData | null = reportResponse?.data ?? null;

  const categories = useMemo(() => {
    if (!reportData?.products) return [];
    return [...new Set(reportData.products.map(p => p.category))].sort();
  }, [reportData]);

  const filteredProducts = useMemo(() => {
    if (!reportData?.products) return [];
    let filtered = reportData.products;
    if (categoryFilter) filtered = filtered.filter(p => p.category === categoryFilter);
    if (searchText) {
      const lower = searchText.toLowerCase();
      filtered = filtered.filter(p =>
        p.product_name.toLowerCase().includes(lower) ||
        (p.product_code && p.product_code.toLowerCase().includes(lower))
      );
    }
    return filtered;
  }, [reportData, categoryFilter, searchText]);

  // Chart data: Top 10 products by total shipped
  const topShippedData = useMemo(() => {
    if (!reportData?.products) return [];
    return [...reportData.products]
      .filter(p => p.total_shipped > 0)
      .sort((a, b) => b.total_shipped - a.total_shipped)
      .slice(0, 10)
      .map(p => ({
        name: p.product_name.length > 20 ? p.product_name.substring(0, 20) + '...' : p.product_name,
        enviado: p.total_shipped,
        compras: p.purchases,
      }));
  }, [reportData]);

  // Chart data: Distribution by category
  const categoryData = useMemo(() => {
    if (!reportData?.products) return [];
    const catMap: Record<string, number> = {};
    reportData.products.forEach(p => {
      if (p.final_stock > 0) {
        catMap[p.category] = (catMap[p.category] || 0) + 1;
      }
    });
    return Object.entries(catMap)
      .map(([name, value]) => ({ name, value }))
      .sort((a, b) => b.value - a.value);
  }, [reportData]);

  // Export handler
  const handleExport = async () => {
    setExporting(true);
    try {
      await reportExportsApi.exportMonthlyExcel({
        month: String(month),
        year: String(year),
        ...(locationId ? { location_id: locationId } : {}),
      });
      message.success('Excel exportado correctamente');
    } catch {
      message.error('Error al exportar Excel');
    } finally {
      setExporting(false);
    }
  };

  // Build dynamic columns
  const columns = useMemo(() => {
    const farmCols = reportData?.farm_columns ?? [];
    const cols: any[] = [
      { title: 'Cod.', dataIndex: 'product_code', key: 'code', width: 70, fixed: 'left' as const },
      { title: 'Producto', dataIndex: 'product_name', key: 'name', width: 200, fixed: 'left' as const, ellipsis: true },
      { title: 'Categoría', dataIndex: 'category', key: 'cat', width: 120, render: (c: string) => <Tag color="green">{c}</Tag> },
      { title: 'Unidad', dataIndex: 'unit', key: 'unit', width: 60, align: 'center' as const },
      { title: 'Inv. Inicial', dataIndex: 'initial_stock', key: 'init', width: 100, align: 'right' as const, render: fmt },
      { title: 'Inv. Final (cierre mes)', dataIndex: 'final_stock', key: 'final', width: 130, align: 'right' as const,
        render: (v: number) => <span style={{ fontWeight: 600, color: v > 0 ? '#1890ff' : v < 0 ? '#ff4d4f' : '#999' }}>{fmt(v)}</span>
      },
      { title: 'Stock actual (bodega)', dataIndex: 'current_stock', key: 'current', width: 130, align: 'right' as const,
        render: (v: number) => <span style={{ fontWeight: 700, color: '#2E7D32' }}>{fmt(v)}</span>
      },
      { title: 'Diferencia', key: 'diff', width: 90, align: 'right' as const,
        render: (_: any, r: ProductRow) => {
          const d = r.initial_stock - r.final_stock;
          return d ? <span style={{ color: d > 0 ? '#fa8c16' : '#52c41a' }}>{fmt(d)}</span> : '-';
        }
      },
    ];

    // Dynamic farm columns
    farmCols.forEach((farm: FarmColumn) => {
      cols.push({
        title: farm.name,
        key: `farm_${farm.id}`,
        width: 85,
        align: 'right' as const,
        render: (_: any, record: ProductRow) => {
          const val = record.farm_shipments?.[farm.id];
          return val ? <span style={{ color: '#e65100' }}>{fmt(val)}</span> : '-';
        },
      });
    });

    cols.push(
      { title: 'Traslados Salientes', dataIndex: 'transfers_out', key: 'transfers_out', width: 130, align: 'right' as const,
        render: (v: number) => v ? <span style={{ color: '#e65100' }}>{fmt(v)}</span> : '-' },
      { title: 'Compras', dataIndex: 'purchases', key: 'purch', width: 100, align: 'right' as const,
        render: (v: number) => v ? <span style={{ color: '#52c41a', fontWeight: 500 }}>{fmt(v)}</span> : '-' },
      { title: 'Remanente', dataIndex: 'returns', key: 'rem', width: 100, align: 'right' as const,
        render: (v: number) => v ? <span style={{ color: '#e65100', fontWeight: 500 }}>{fmt(v)}</span> : '-' },
      { title: 'Envíos Recibidos', dataIndex: 'shipments_in', key: 'ship_in', width: 120, align: 'right' as const,
        render: (v: number) => v ? <span style={{ color: '#1976D2', fontWeight: 500 }}>{fmt(v)}</span> : '-' },
      { title: 'Aumentos', dataIndex: 'increases', key: 'inc', width: 90, align: 'right' as const,
        render: (v: number) => v ? <span style={{ color: '#1976D2' }}>{fmt(v)}</span> : '-' },
      { title: 'Disminuc.', dataIndex: 'decreases', key: 'dec', width: 90, align: 'right' as const,
        render: (v: number) => v ? <span style={{ color: '#D32F2F' }}>{fmt(v)}</span> : '-' },
      { title: 'Total Mov.', dataIndex: 'total_movements', key: 'tmov', width: 100, align: 'right' as const,
        render: (v: number) => v ? <span style={{ fontWeight: 500 }}>{fmt(v)}</span> : '-' },
      { title: 'Variación', dataIndex: 'variation', key: 'var', width: 90, align: 'right' as const,
        render: (v: number) => v ? <span style={{ color: v !== 0 ? '#ff4d4f' : '#52c41a', fontWeight: v !== 0 ? 600 : 400 }}>{fmt(v)}</span> : '-' },
    );

    return cols;
  }, [reportData?.farm_columns]);

  const monthName = selectedMonth.format('MMMM YYYY');

  return (
    <div>
      <div style={{ marginBottom: 24 }}>
        <h1 style={{ fontSize: 24, margin: 0 }}>Inventario Mensual</h1>
        <p style={{ color: '#666', margin: 0 }}>
          Control de existencias mensuales: inicio, movimientos por finca y cierre.
          Las columnas de fincas muestran lo enviado <strong>desde la ubicación seleccionada</strong> hacia cada finca.
          "Traslados Salientes" suma lo enviado a otras ubicaciones que no son fincas (p. ej. bodega → bodega).
        </p>
      </div>

      {/* Filters */}
      <Card style={{ marginBottom: 16 }}>
        <Row gutter={[16, 16]} align="middle">
          <Col xs={24} sm={8} md={5}>
            <DatePicker
              picker="month"
              value={selectedMonth}
              onChange={(date) => date && setSelectedMonth(date)}
              format="MMMM YYYY"
              style={{ width: '100%' }}
              placeholder="Seleccionar mes"
              suffixIcon={<CalendarOutlined />}
            />
          </Col>
          <Col xs={24} sm={8} md={5}>
            <Select
              allowClear
              showSearch
              optionFilterProp="children"
              placeholder="Bodega principal"
              value={locationId}
              onChange={setLocationId}
              style={{ width: '100%' }}
            >
              {locations.map((loc: any) => (
                <Option key={loc.id} value={loc.id}>
                  {loc.type === 'farm' ? '🌱 ' : '🏬 '}{loc.name}
                </Option>
              ))}
            </Select>
          </Col>
          <Col xs={24} sm={8} md={4}>
            <Select allowClear placeholder="Filtrar por categoría" value={categoryFilter} onChange={setCategoryFilter} style={{ width: '100%' }}>
              {categories.map(cat => <Option key={cat} value={cat}>{cat}</Option>)}
            </Select>
          </Col>
          <Col xs={24} sm={12} md={4}>
            <input
              type="text"
              placeholder="Buscar producto..."
              value={searchText}
              onChange={(e) => setSearchText(e.target.value)}
              style={{ width: '100%', padding: '4px 11px', border: '1px solid #d9d9d9', borderRadius: 6, height: 32, fontSize: 14 }}
            />
          </Col>
          <Col xs={24} sm={12} md={6}>
            <Button icon={<ReloadOutlined />} onClick={() => refetch()} style={{ marginRight: 8 }}>Actualizar</Button>
            <Button type="primary" icon={<FileExcelOutlined />} onClick={handleExport} loading={exporting} style={{ background: '#2E7D32' }}>
              Exportar Excel
            </Button>
          </Col>
        </Row>
      </Card>

      {/* Summary Cards */}
      {reportData && (
        <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
          <Col xs={12} sm={6}>
            <Card><Statistic title="Productos con Movimiento" value={reportData.summary.total_products} prefix={<DatabaseOutlined />} /></Card>
          </Col>
          <Col xs={12} sm={6}>
            <Card><Statistic title="Con Stock Final" value={reportData.summary.total_with_stock} prefix={<InboxOutlined />} valueStyle={{ color: '#52c41a' }} /></Card>
          </Col>
          <Col xs={12} sm={6}>
            <Card><Statistic title="Total Compras (qty)" value={reportData.summary.total_purchases} prefix={<ShoppingCartOutlined />} valueStyle={{ color: '#1890ff' }} precision={2} /></Card>
          </Col>
          <Col xs={12} sm={6}>
            <Card><Statistic title="Total Enviado / Trasladado" value={reportData.summary.total_shipped} prefix={<SendOutlined />} valueStyle={{ color: '#fa8c16' }} precision={2} /></Card>
          </Col>
        </Row>
      )}

      {/* Charts */}
      {reportData && (topShippedData.length > 0 || categoryData.length > 0) && (
        <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
          {topShippedData.length > 0 && (
            <Col xs={24} lg={14}>
              <Card title="Top 10 Productos Más Enviados / Trasladados" size="small">
                <ResponsiveContainer width="100%" height={300}>
                  <BarChart data={topShippedData} layout="vertical" margin={{ left: 20, right: 20 }}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis type="number" />
                    <YAxis type="category" dataKey="name" width={140} tick={{ fontSize: 11 }} />
                    <Tooltip formatter={(v: number) => fmt(v)} />
                    <Legend />
                    <Bar dataKey="enviado" name="Enviado / Trasladado" fill="#F57C00" />
                    <Bar dataKey="compras" name="Compras" fill="#2E7D32" />
                  </BarChart>
                </ResponsiveContainer>
              </Card>
            </Col>
          )}
          {categoryData.length > 0 && (
            <Col xs={24} lg={10}>
              <Card title="Productos con Stock por Categoría" size="small">
                <ResponsiveContainer width="100%" height={300}>
                  <PieChart>
                    <Pie data={categoryData} dataKey="value" nameKey="name" cx="50%" cy="50%" outerRadius={100} label={({ name, value }) => `${name}: ${value}`}>
                      {categoryData.map((_, i) => <Cell key={i} fill={COLORS[i % COLORS.length]} />)}
                    </Pie>
                    <Tooltip />
                  </PieChart>
                </ResponsiveContainer>
              </Card>
            </Col>
          )}
        </Row>
      )}

      {/* Table */}
      <Card
        title={`Inventario ${monthName} — ${reportData?.warehouse ?? ''}`}
        extra={<Tag color="blue">{filteredProducts.length} productos</Tag>}
      >
        <Spin spinning={isLoading}>
          <Table
            dataSource={filteredProducts}
            columns={columns}
            rowKey="product_id"
            scroll={{ x: 'max-content' }}
            pagination={{ pageSize: 50, showSizeChanger: true, pageSizeOptions: ['25', '50', '100', '200'],
              showTotal: (total, range) => `${range[0]}-${range[1]} de ${total} productos` }}
            size="small"
            bordered
            sticky
          />
        </Spin>
      </Card>
    </div>
  );
};

export default MonthlyInventoryReport;
