import React, { useState, useMemo } from 'react';
import { Card, Row, Col, DatePicker, Button, Select, Spin, Table, Statistic, Tag, message } from 'antd';
import {
  CalendarOutlined,
  FileExcelOutlined,
  ReloadOutlined,
  DatabaseOutlined,
  CheckCircleOutlined,
  SwapOutlined,
} from '@ant-design/icons';
import { useQuery } from '@tanstack/react-query';
import { inventoryApi, locationsApi, reportExportsApi } from '../../services/api';
import dayjs, { Dayjs } from 'dayjs';
import {
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer,
} from 'recharts';

const { Option } = Select;

const fmt = (v: number) => v ? v.toLocaleString('es-CO', { maximumFractionDigits: 2 }) : '-';

interface ProductListingRow {
  location: string;
  location_type: string;
  product_code: string;
  category: string;
  product_name: string;
  unit: string;
  stock_at_date: number;
  differential: number;
  current_stock: number;
  status: string;
}

const ProductListingReport: React.FC = () => {
  const [selectedDate, setSelectedDate] = useState<Dayjs>(dayjs());
  const [locationId, setLocationId] = useState<string | undefined>(undefined);
  const [searchText, setSearchText] = useState('');
  const [exporting, setExporting] = useState(false);

  const dateStr = selectedDate.format('YYYY-MM-DD');

  const { data: locationsData } = useQuery({
    queryKey: ['locations-all'],
    queryFn: () => locationsApi.list({ per_page: 100 }),
  });

  const locations = locationsData?.data ?? [];

  const { data: reportResponse, isLoading, refetch } = useQuery({
    queryKey: ['product-listing', dateStr, locationId],
    queryFn: () => inventoryApi.getProductListing({ date: dateStr, location_id: locationId }),
  });

  const reportData = reportResponse?.data ?? null;

  const filteredProducts = useMemo(() => {
    if (!reportData?.products) return [];
    if (!searchText) return reportData.products;
    const lower = searchText.toLowerCase();
    return reportData.products.filter((p: ProductListingRow) =>
      p.product_name.toLowerCase().includes(lower) ||
      (p.product_code && p.product_code.toLowerCase().includes(lower)) ||
      p.category.toLowerCase().includes(lower)
    );
  }, [reportData, searchText]);

  // Chart: Top 10 by differential (biggest changes)
  const diffChartData = useMemo(() => {
    if (!reportData?.products) return [];
    return [...reportData.products]
      .filter((p: ProductListingRow) => p.differential !== 0)
      .sort((a: ProductListingRow, b: ProductListingRow) => Math.abs(b.differential) - Math.abs(a.differential))
      .slice(0, 10)
      .map((p: ProductListingRow) => ({
        name: p.product_name.length > 18 ? p.product_name.substring(0, 18) + '...' : p.product_name,
        diferencial: p.differential,
      }));
  }, [reportData]);

  const handleExport = async () => {
    setExporting(true);
    try {
      await reportExportsApi.exportProductListingExcel({
        date: dateStr,
        ...(locationId ? { location_id: locationId } : {}),
      });
      message.success('Excel exportado correctamente');
    } catch {
      message.error('Error al exportar Excel');
    } finally {
      setExporting(false);
    }
  };

  const columns = [
    { title: 'Finca', dataIndex: 'location', key: 'loc', width: 120, fixed: 'left' as const,
      render: (v: string, r: ProductListingRow) => <Tag color={r.location_type === 'warehouse' ? 'blue' : 'green'}>{v}</Tag> },
    { title: 'Código', dataIndex: 'product_code', key: 'code', width: 80 },
    { title: 'Grupo Insumo', dataIndex: 'category', key: 'cat', width: 140,
      render: (v: string) => <Tag color="orange">{v}</Tag> },
    { title: 'Producto', dataIndex: 'product_name', key: 'name', width: 250, ellipsis: true },
    { title: 'Unidad', dataIndex: 'unit', key: 'unit', width: 70, align: 'center' as const },
    { title: 'Stock Fecha', dataIndex: 'stock_at_date', key: 'sf', width: 110, align: 'right' as const,
      render: (v: number) => <span style={{ fontWeight: 500 }}>{fmt(v)}</span> },
    { title: 'Diferencial', dataIndex: 'differential', key: 'diff', width: 100, align: 'right' as const,
      render: (v: number) => {
        if (!v) return '-';
        return <span style={{ color: v > 0 ? '#52c41a' : '#ff4d4f', fontWeight: 600 }}>{v > 0 ? '+' : ''}{fmt(v)}</span>;
      }
    },
    { title: 'Stock Actual', dataIndex: 'current_stock', key: 'sa', width: 110, align: 'right' as const,
      render: (v: number) => <span style={{ fontWeight: 600, color: v > 0 ? '#1890ff' : '#999' }}>{fmt(v)}</span> },
    { title: 'Estado', dataIndex: 'status', key: 'st', width: 90, align: 'center' as const,
      render: (v: string) => <Tag color={v === 'ACTIVO' ? 'green' : 'red'}>{v}</Tag> },
  ];

  return (
    <div>
      <div style={{ marginBottom: 24 }}>
        <h1 style={{ fontSize: 24, margin: 0 }}>Listado de Productos por Fecha</h1>
        <p style={{ color: '#666', margin: 0 }}>
          Stock a una fecha determinada vs stock actual con diferencial
        </p>
      </div>

      <Card style={{ marginBottom: 16 }}>
        <Row gutter={[16, 16]} align="middle">
          <Col xs={24} sm={6} md={5}>
            <DatePicker
              value={selectedDate}
              onChange={(date) => date && setSelectedDate(date)}
              format="YYYY-MM-DD"
              style={{ width: '100%' }}
              placeholder="Fecha de corte"
              suffixIcon={<CalendarOutlined />}
            />
          </Col>
          <Col xs={24} sm={6} md={5}>
            <Select allowClear placeholder="Filtrar por ubicación" value={locationId} onChange={setLocationId} style={{ width: '100%' }}>
              {locations.map((loc: any) => <Option key={loc.id} value={loc.id}>{loc.name}</Option>)}
            </Select>
          </Col>
          <Col xs={24} sm={6} md={5}>
            <input
              type="text" placeholder="Buscar producto..." value={searchText}
              onChange={(e) => setSearchText(e.target.value)}
              style={{ width: '100%', padding: '4px 11px', border: '1px solid #d9d9d9', borderRadius: 6, height: 32, fontSize: 14 }}
            />
          </Col>
          <Col xs={24} sm={6} md={9}>
            <Button icon={<ReloadOutlined />} onClick={() => refetch()} style={{ marginRight: 8 }}>Actualizar</Button>
            <Button type="primary" icon={<FileExcelOutlined />} onClick={handleExport} loading={exporting} style={{ background: '#2E7D32' }}>
              Exportar Excel
            </Button>
          </Col>
        </Row>
      </Card>

      {reportData && (
        <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
          <Col xs={8}>
            <Card><Statistic title="Total Productos" value={reportData.summary.total} prefix={<DatabaseOutlined />} /></Card>
          </Col>
          <Col xs={8}>
            <Card><Statistic title="Con Stock Actual" value={reportData.summary.with_stock} prefix={<CheckCircleOutlined />} valueStyle={{ color: '#52c41a' }} /></Card>
          </Col>
          <Col xs={8}>
            <Card><Statistic title="Con Diferencial" value={reportData.summary.with_differential} prefix={<SwapOutlined />} valueStyle={{ color: '#fa8c16' }} /></Card>
          </Col>
        </Row>
      )}

      {diffChartData.length > 0 && (
        <Card title="Top 10 Mayores Cambios (Diferencial)" size="small" style={{ marginBottom: 16 }}>
          <ResponsiveContainer width="100%" height={280}>
            <BarChart data={diffChartData} layout="vertical" margin={{ left: 20, right: 20 }}>
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis type="number" />
              <YAxis type="category" dataKey="name" width={140} tick={{ fontSize: 11 }} />
              <Tooltip formatter={(v: number) => fmt(v)} />
              <Legend />
              <Bar dataKey="diferencial" name="Diferencial" fill="#F57C00" />
            </BarChart>
          </ResponsiveContainer>
        </Card>
      )}

      <Card
        title={`Listado al ${selectedDate.format('DD/MM/YYYY')} — ${reportData?.location ?? 'Todas'}`}
        extra={<Tag color="blue">{filteredProducts.length} productos</Tag>}
      >
        <Spin spinning={isLoading}>
          <Table
            dataSource={filteredProducts}
            columns={columns}
            rowKey={(r: ProductListingRow) => `${r.location}-${r.product_code}`}
            scroll={{ x: 'max-content' }}
            pagination={{ pageSize: 50, showSizeChanger: true, pageSizeOptions: ['25', '50', '100', '200'],
              showTotal: (total, range) => `${range[0]}-${range[1]} de ${total}` }}
            size="small"
            bordered
            sticky
          />
        </Spin>
      </Card>
    </div>
  );
};

export default ProductListingReport;
