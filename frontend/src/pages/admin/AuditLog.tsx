import { useState } from 'react';
import { Card, Table, Tag, Select, DatePicker, Space, Typography, Tooltip } from 'antd';
import { useQuery } from '@tanstack/react-query';
import dayjs, { Dayjs } from 'dayjs';
import { auditsApi } from '../../services/api';

const { Title, Paragraph, Text } = Typography;
const { RangePicker } = DatePicker;

type Cambio = { label: string; from: string | null; to: string | null };

// Qué significan las líneas según la acción: lo que se creó, cómo quedó o lo que tenía.
const lineCaption: Record<string, string> = {
  created: 'Productos',
  updated: 'Productos después del cambio',
  deleted: 'Productos que tenía',
};

const eventColor: Record<string, string> = {
  created: 'green',
  updated: 'blue',
  deleted: 'red',
  restored: 'gold',
};

export default function AuditLog() {
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(30);
  const [model, setModel] = useState<string | undefined>();
  const [event, setEvent] = useState<string | undefined>();
  const [range, setRange] = useState<[Dayjs, Dayjs] | null>(null);

  const { data: filtersData } = useQuery({
    queryKey: ['audit-filters'],
    queryFn: () => auditsApi.filters(),
  });

  const params: Record<string, any> = { page, per_page: pageSize };
  if (model) params.model = model;
  if (event) params.event = event;
  if (range) { params.from = range[0].format('YYYY-MM-DD'); params.to = range[1].format('YYYY-MM-DD'); }

  const { data, isLoading } = useQuery({
    queryKey: ['audits', page, pageSize, model, event, range?.[0]?.format('YYYY-MM-DD'), range?.[1]?.format('YYYY-MM-DD')],
    queryFn: () => auditsApi.list(params),
  });

  const audits = data?.data || [];
  const total = (data as any)?.meta?.total || 0;

  const models = filtersData?.data?.models || [];
  const events = filtersData?.data?.events || [];

  // Renderiza la acción de forma humana con lo que envía el backend, ya en
  // palabras (nombres en vez de IDs, etiquetas en español):
  //  - document/details/lines: la foto del documento TAL COMO ERA en el momento
  //    de la acción (los registros viejos no la tienen y traen solo summary);
  //  - lineChanges: qué productos se agregaron, quitaron o cambiaron;
  //  - changes: los cambios campo a campo.
  // Un producto quitado se muestra tachado, sin "→ ∅"; un campo que se vació, sí.
  const renderCambio = (esLinea: boolean) => (c: Cambio, i: number) => (
    <span key={i} style={{ fontSize: 12 }}>
      <strong>{c.label}:</strong>{' '}
      {c.from !== null && c.from !== '' ? (
        <><Text delete type="secondary">{c.from}</Text>{esLinea && c.to === null ? null : ' → '}</>
      ) : null}
      {esLinea && c.to === null ? null : <Text type="success">{c.to ?? '∅'}</Text>}
    </span>
  );

  const renderChanges = (record: any) => {
    const changes: Cambio[] = record.changes || [];
    const lineChanges: Cambio[] = record.lineChanges || [];
    const lines: string[] = record.lines || [];
    const details: Array<{ label: string | null; value: string }> = record.details || [];
    return (
      <Space direction="vertical" size={2} style={{ width: '100%' }}>
        {record.document ? (
          <span>
            <Text strong style={{ fontSize: 13 }}>{record.document}</Text>
            {details.length > 0 && (
              <Text type="secondary" style={{ fontSize: 12 }}>
                {' · ' + details.map((d) => (d.label ? `${d.label}: ${d.value}` : d.value)).join(' · ')}
              </Text>
            )}
          </span>
        ) : (
          record.summary && <Text strong style={{ fontSize: 13 }}>{record.summary}</Text>
        )}
        {lines.length > 0 && (
          <div style={{ fontSize: 12 }}>
            <Text type="secondary" style={{ fontSize: 12 }}>{lineCaption[record.event] || 'Productos'}:</Text>
            <ul style={{ margin: 0, paddingLeft: 18 }}>
              {lines.map((l, i) => <li key={i}>{l}</li>)}
            </ul>
          </div>
        )}
        {record.event === 'deleted' && <Text type="danger">Registro eliminado</Text>}
        {lineChanges.length > 0 && (
          <Space direction="vertical" size={0}>
            {lineChanges.map(renderCambio(true))}
          </Space>
        )}
        {record.event !== 'deleted' && changes.length > 0 && (
          <Space direction="vertical" size={0}>
            {changes.map(renderCambio(false))}
          </Space>
        )}
        {!record.summary && record.event !== 'deleted' && changes.length === 0 && lineChanges.length === 0 && (
          <Text type="secondary">—</Text>
        )}
      </Space>
    );
  };

  const columns = [
    {
      title: 'Fecha',
      dataIndex: 'createdAt',
      width: 160,
      render: (v: string) => v ? dayjs(v).format('DD/MM/YYYY HH:mm') : '—',
    },
    {
      title: 'Usuario',
      dataIndex: 'userName',
      width: 200,
      render: (v: string, r: any) => (
        <Tooltip title={r.userEmail || ''}><span>{v}</span></Tooltip>
      ),
    },
    {
      title: 'Acción',
      dataIndex: 'eventLabel',
      width: 110,
      render: (v: string, r: any) => <Tag color={eventColor[r.event] || 'default'}>{v}</Tag>,
    },
    {
      title: 'Entidad',
      dataIndex: 'modelLabel',
      width: 130,
      render: (v: string) => <Tag>{v}</Tag>,
    },
    {
      title: 'Detalle',
      key: 'changes',
      render: (_: any, r: any) => renderChanges(r),
    },
    {
      title: 'IP',
      dataIndex: 'ipAddress',
      width: 130,
      render: (v: string) => <Text type="secondary" style={{ fontSize: 12 }}>{v || '—'}</Text>,
    },
  ];

  return (
    <div>
      <Title level={3} style={{ color: '#389e0d' }}>Auditoría</Title>
      <Paragraph type="secondary">
        Registro de quién creó, editó o eliminó información del sistema y cuándo: compras, salidas, recepciones, ajustes,
        datos maestros, recetas y órdenes técnicas, rendimiento, alertas y liquidación. Cada documento se muestra con sus
        productos y cantidades tal como estaban en el momento de la acción.
      </Paragraph>

      <Card style={{ marginBottom: 16 }}>
        <Space wrap>
          <Select
            allowClear showSearch optionFilterProp="label" placeholder="Filtrar por entidad" style={{ width: 240 }}
            value={model} onChange={(v) => { setModel(v); setPage(1); }}
            options={models.map((m: any) => ({ value: m.value, label: m.label }))}
          />
          <Select
            allowClear placeholder="Filtrar por acción" style={{ width: 180 }}
            value={event} onChange={(v) => { setEvent(v); setPage(1); }}
            options={events.map((e: any) => ({ value: e.value, label: e.label }))}
          />
          <RangePicker
            format="DD/MM/YYYY"
            value={range as any}
            onChange={(v) => { setRange(v as any); setPage(1); }}
          />
        </Space>
      </Card>

      <Card>
        <Table
          rowKey="id"
          loading={isLoading}
          dataSource={audits}
          columns={columns as any}
          size="small"
          scroll={{ x: 900 }}
          pagination={{
            current: page,
            pageSize,
            total,
            showSizeChanger: true,
            showTotal: (t) => `${t} registros`,
            onChange: (p, ps) => { setPage(p); setPageSize(ps); },
          }}
        />
      </Card>
    </div>
  );
}
