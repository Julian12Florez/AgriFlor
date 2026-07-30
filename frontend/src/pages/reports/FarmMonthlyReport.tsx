import React, { useState } from 'react';
import { Card, Row, Col, DatePicker, Select, Spin, Table, Tag, Alert, Typography } from 'antd';
import { CalendarOutlined, EnvironmentOutlined } from '@ant-design/icons';
import { useQuery } from '@tanstack/react-query';
import { inventoryApi, locationsApi } from '../../services/api';
import dayjs, { Dayjs } from 'dayjs';

const { Title, Paragraph } = Typography;

interface FarmProductRow {
  product_id: string;
  product_code: string;
  product_name: string;
  category: string;
  unit: string;
  initial_stock: number;
  entries: number;
  consumption: number;
  remanente_to_warehouse: number;
  other_exits: number;
  final_stock: number;
}

const fmt = (v: number) => (v ? v.toLocaleString('es-CO', { maximumFractionDigits: 2 }) : '-');

const FarmMonthlyReport: React.FC = () => {
  const [selectedMonth, setSelectedMonth] = useState<Dayjs>(dayjs());
  const [fincaId, setFincaId] = useState<string | undefined>(undefined);

  const month = selectedMonth.month() + 1;
  const year = selectedMonth.year();

  const { data: farmsData } = useQuery({
    queryKey: ['farms'],
    queryFn: () => locationsApi.getFarms(),
  });
  const farms = farmsData?.data || [];

  const { data: reportResponse, isLoading } = useQuery({
    queryKey: ['farm-monthly', fincaId, month, year],
    queryFn: () => inventoryApi.getFarmMonthlyReport({ location_id: fincaId, month, year }),
    enabled: !!fincaId,
  });
  const reportData = reportResponse?.data ?? null;
  const products: FarmProductRow[] = reportData?.products ?? [];

  const columns = [
    { title: 'Cód.', dataIndex: 'product_code', key: 'code', width: 80, fixed: 'left' as const },
    { title: 'Producto', dataIndex: 'product_name', key: 'name', width: 200, fixed: 'left' as const, ellipsis: true },
    { title: 'Categoría', dataIndex: 'category', key: 'cat', width: 120, render: (c: string) => <Tag color="green">{c}</Tag> },
    { title: 'Unidad', dataIndex: 'unit', key: 'unit', width: 70, align: 'center' as const },
    { title: 'Inv. Inicial', dataIndex: 'initial_stock', key: 'init', width: 100, align: 'right' as const, render: fmt },
    { title: 'Entradas (a finca)', dataIndex: 'entries', key: 'entries', width: 130, align: 'right' as const,
      render: (v: number) => <span style={{ color: '#1565c0', fontWeight: 600 }}>{fmt(v)}</span> },
    { title: 'Consumo', dataIndex: 'consumption', key: 'cons', width: 100, align: 'right' as const,
      render: (v: number) => <span style={{ color: '#c62828' }}>{fmt(v)}</span> },
    { title: 'Remanente a bodega', dataIndex: 'remanente_to_warehouse', key: 'rem', width: 150, align: 'right' as const,
      render: (v: number) => <span style={{ color: '#e65100', fontWeight: 600 }}>{fmt(v)}</span> },
    { title: 'Salidas por Ajuste', dataIndex: 'other_exits', key: 'other_exits', width: 140, align: 'right' as const,
      render: (v: number) => <span style={{ color: '#c62828' }}>{fmt(v)}</span> },
    { title: 'Inv. Final (finca)', dataIndex: 'final_stock', key: 'final', width: 130, align: 'right' as const,
      render: (v: number) => <span style={{ fontWeight: 700, color: '#2E7D32' }}>{fmt(v)}</span> },
  ];

  return (
    <div>
      <Title level={3} style={{ color: '#389e0d' }}>Inventario Mensual por Finca</Title>
      <Paragraph type="secondary">
        Cierre de mes por finca: inventario inicial, entradas (envíos a la finca), consumo, remanente devuelto a bodega e inventario final.
      </Paragraph>

      <Card style={{ marginBottom: 16 }}>
        <Row gutter={[16, 16]} align="middle">
          <Col xs={24} sm={10} md={8}>
            <Select
              showSearch
              allowClear
              placeholder="Seleccionar finca"
              style={{ width: '100%' }}
              value={fincaId}
              onChange={setFincaId}
              suffixIcon={<EnvironmentOutlined />}
              optionFilterProp="children"
            >
              {farms.map((f: any) => <Select.Option key={f.id} value={f.id}>{f.name}</Select.Option>)}
            </Select>
          </Col>
          <Col xs={24} sm={10} md={6}>
            <DatePicker
              picker="month"
              value={selectedMonth}
              onChange={(d) => d && setSelectedMonth(d)}
              format="MMMM YYYY"
              style={{ width: '100%' }}
              suffixIcon={<CalendarOutlined />}
              allowClear={false}
            />
          </Col>
        </Row>
      </Card>

      {!fincaId ? (
        <Alert type="info" showIcon message="Selecciona una finca para ver su inventario mensual." />
      ) : (
        <>
          <Alert
            type="warning"
            showIcon
            style={{ marginBottom: 16 }}
            message="Consumo, Remanente a bodega y Salidas por Ajuste"
            description="Consumo y Remanente se llenan cuando se registran en el sistema las salidas de esos tipos (finca → bodega / consumo en finca). 'Salidas por Ajuste' agrupa lo demás que redujo el stock de la finca sin pasar por esas salidas (ajustes de salida, traslados salientes, aplicaciones sueltas). El inventario final es: inicial + entradas − consumo − remanente − salidas por ajuste."
          />
          <Card title={`Inventario ${selectedMonth.format('MMMM YYYY')} — ${reportData?.farm ?? ''}`}
            extra={<Tag color="blue">{products.length} productos</Tag>}>
            <Spin spinning={isLoading}>
              <Table
                dataSource={products}
                columns={columns}
                rowKey="product_id"
                scroll={{ x: 'max-content' }}
                pagination={{ pageSize: 50, showSizeChanger: true, showTotal: (t) => `${t} productos` }}
                size="small"
                bordered
                sticky
              />
            </Spin>
          </Card>
        </>
      )}
    </div>
  );
};

export default FarmMonthlyReport;
