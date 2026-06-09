import React, { useState } from 'react';
import { Card, Row, Col, DatePicker, Select, Spin, Table, Tag, Alert, Statistic, Typography } from 'antd';
import { EnvironmentOutlined, InboxOutlined } from '@ant-design/icons';
import { useQuery } from '@tanstack/react-query';
import { inventoryApi, locationsApi } from '../../services/api';
import { Dayjs } from 'dayjs';

const { Title, Paragraph } = Typography;
const { RangePicker } = DatePicker;

interface EntryRow {
  product_id: string;
  product_code: string;
  product_name: string;
  brand_name: string;
  unit: string;
  total_quantity: number;
  movements: number;
}

const fmt = (v: number) => (v ? v.toLocaleString('es-CO', { maximumFractionDigits: 2 }) : '-');

const FarmEntriesReport: React.FC = () => {
  const [fincaId, setFincaId] = useState<string | undefined>(undefined);
  const [range, setRange] = useState<[Dayjs, Dayjs] | null>(null);

  const { data: farmsData } = useQuery({
    queryKey: ['farms'],
    queryFn: () => locationsApi.getFarms(),
  });
  const farms = farmsData?.data || [];

  const { data: reportResponse, isLoading } = useQuery({
    queryKey: ['farm-entries', fincaId, range?.[0]?.format('YYYY-MM-DD'), range?.[1]?.format('YYYY-MM-DD')],
    queryFn: () => inventoryApi.getFarmEntriesReport({
      location_id: fincaId,
      date_from: range?.[0]?.format('YYYY-MM-DD'),
      date_to: range?.[1]?.format('YYYY-MM-DD'),
    }),
    enabled: !!fincaId,
  });
  const reportData = reportResponse?.data ?? null;
  const products: EntryRow[] = reportData?.products ?? [];
  const summary = reportData?.summary ?? { total_products: 0, total_movements: 0 };

  const columns = [
    { title: 'Cód.', dataIndex: 'product_code', key: 'code', width: 80 },
    { title: 'Producto', dataIndex: 'product_name', key: 'name', width: 240, ellipsis: true },
    { title: 'Marca', dataIndex: 'brand_name', key: 'brand', width: 140, render: (b: string) => <Tag>{b}</Tag> },
    { title: 'Cantidad total', dataIndex: 'total_quantity', key: 'qty', width: 130, align: 'right' as const,
      render: (v: number) => <span style={{ fontWeight: 700, color: '#1565c0' }}>{fmt(v)}</span> },
    { title: 'Unidad', dataIndex: 'unit', key: 'unit', width: 80, align: 'center' as const },
    { title: 'N° envíos', dataIndex: 'movements', key: 'movs', width: 100, align: 'right' as const },
  ];

  return (
    <div>
      <Title level={3} style={{ color: '#389e0d' }}>Entradas Consolidadas a Finca</Title>
      <Paragraph type="secondary">
        Informe consolidado de todo lo enviado/recibido en una finca, por producto, en el rango de fechas seleccionado.
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
          <Col xs={24} sm={12} md={8}>
            <RangePicker
              style={{ width: '100%' }}
              format="DD/MM/YYYY"
              placeholder={['Fecha desde', 'Fecha hasta']}
              value={range as any}
              onChange={(v) => setRange(v as any)}
            />
          </Col>
        </Row>
      </Card>

      {!fincaId ? (
        <Alert type="info" showIcon message="Selecciona una finca para ver sus entradas consolidadas." />
      ) : (
        <>
          <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
            <Col xs={12} sm={8}>
              <Card><Statistic title="Productos recibidos" value={summary.total_products} prefix={<InboxOutlined />} valueStyle={{ color: '#2E7D32' }} /></Card>
            </Col>
            <Col xs={12} sm={8}>
              <Card><Statistic title="Total de envíos" value={summary.total_movements} valueStyle={{ color: '#1890ff' }} /></Card>
            </Col>
          </Row>
          <Card title={`Entradas — ${reportData?.farm ?? ''}`} extra={<Tag color="blue">{products.length} productos</Tag>}>
            <Spin spinning={isLoading}>
              <Table
                dataSource={products}
                columns={columns}
                rowKey={(r) => r.product_id + r.unit}
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

export default FarmEntriesReport;
