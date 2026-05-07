import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
  Tabs, Card, Table, Tag, DatePicker, Select, Space, Row, Col,
  Statistic, Typography, Progress, Empty, Spin, Tooltip as AntTooltip, Divider
} from 'antd';
import {
  BarChartOutlined, CheckCircleOutlined, CalendarOutlined,
  SwapOutlined, RocketOutlined, ArrowUpOutlined, ArrowDownOutlined,
  WarningOutlined, TrophyOutlined, TeamOutlined, ClockCircleOutlined
} from '@ant-design/icons';
import {
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend,
  ResponsiveContainer, PieChart, Pie, Cell, LineChart, Line,
  ReferenceLine, ComposedChart, Area
} from 'recharts';
import dayjs from 'dayjs';
import { performanceReportsApi, taskCatalogApi, locationsApi, taskScheduleApi } from '../../services/api';

const { Title, Text } = Typography;
const { RangePicker } = DatePicker;
const { Option } = Select;

const LEVEL_COLORS_HEX: Record<string, string> = {
  sobrepaso: '#f5222d', alto: '#52c41a', medio: '#faad14', bajo: '#ff4d4f',
};
const LEVEL_TAG_COLORS: Record<string, string> = {
  sobrepaso: 'red', alto: 'green', medio: 'gold', bajo: 'red',
};
const LEVEL_LABELS: Record<string, string> = {
  sobrepaso: 'Sobrepaso', alto: 'Alto', medio: 'Medio', bajo: 'Bajo',
};
const ALERT_COLORS: Record<string, string> = {
  adelantada: '#52c41a', en_tiempo: '#1890ff', riesgo: '#fa8c16', atrasada: '#f5222d',
};
const ALERT_LABELS: Record<string, string> = {
  adelantada: 'Adelantada', en_tiempo: 'En Tiempo', riesgo: 'Riesgo', atrasada: 'Atrasada',
};
const STATUS_COLORS: Record<string, string> = {
  planificada: '#1890ff', en_progreso: '#52c41a', completada: '#8c8c8c', cancelada: '#ff4d4f',
};

const PIE_COLORS = ['#52c41a', '#faad14', '#ff4d4f', '#f5222d'];

const CustomTooltipStyle = {
  backgroundColor: 'rgba(255,255,255,0.96)', border: '1px solid #e8e8e8',
  borderRadius: 8, padding: '12px 16px', boxShadow: '0 2px 8px rgba(0,0,0,0.1)',
};

const PerformanceReports = () => {
  const [dateRange, setDateRange] = useState<[dayjs.Dayjs, dayjs.Dayjs]>([
    dayjs().startOf('month'), dayjs(),
  ]);
  const [selectedFarm, setSelectedFarm] = useState<string | undefined>();
  const [selectedTask, setSelectedTask] = useState<string | undefined>();
  const [activeTab, setActiveTab] = useState('1');

  const { data: locationsData } = useQuery({
    queryKey: ['farmLocations'],
    queryFn: () => locationsApi.list({ type: 'farm', status: 'active', per_page: 100 }),
  });
  const { data: tasksData } = useQuery({
    queryKey: ['taskCatalogActive'],
    queryFn: () => taskCatalogApi.list({ active: 'true', per_page: 100 }),
  });

  const params = {
    date_from: dateRange[0].format('YYYY-MM-DD'),
    date_to: dateRange[1].format('YYYY-MM-DD'),
    location_id: selectedFarm, task_catalog_id: selectedTask,
  };

  const { data: r1Data, isLoading: r1Loading } = useQuery({
    queryKey: ['report-by-task', params],
    queryFn: () => performanceReportsApi.byTask(params),
    enabled: activeTab === '1',
  });
  const { data: r2Data, isLoading: r2Loading } = useQuery({
    queryKey: ['report-compliance', params],
    queryFn: () => performanceReportsApi.compliance(params),
    enabled: activeTab === '2',
  });
  const { data: r3Data, isLoading: r3Loading } = useQuery({
    queryKey: ['report-gantt', params],
    queryFn: () => performanceReportsApi.gantt(params),
    enabled: activeTab === '3',
  });
  const { data: r5Data, isLoading: r5Loading } = useQuery({
    queryKey: ['report-projection', selectedFarm],
    queryFn: () => performanceReportsApi.projection({ location_id: selectedFarm }),
    enabled: activeTab === '5',
  });

  const locations = (locationsData as any)?.data || [];
  const tasks = (tasksData as any)?.data || [];

  // ─── TAB 1: Rendimiento por Tarea ───
  const renderTab1 = () => {
    if (r1Loading) return <Spin size="large" style={{ display: 'block', margin: '60px auto' }} />;
    const d = (r1Data as any)?.data;
    if (!d || d.summary.totalCompleted === 0) return <Empty description="No hay tareas completadas en este periodo" />;

    const pieData = [
      { name: 'Alto', value: d.summary.distribution.alto, color: LEVEL_COLORS_HEX.alto },
      { name: 'Medio', value: d.summary.distribution.medio, color: LEVEL_COLORS_HEX.medio },
      { name: 'Bajo', value: d.summary.distribution.bajo, color: LEVEL_COLORS_HEX.bajo },
      { name: 'Sobrepaso', value: d.summary.distribution.sobrepaso, color: LEVEL_COLORS_HEX.sobrepaso },
    ].filter(x => x.value > 0);

    const barData = (d.byTask || []).map((t: any) => ({
      name: t.taskName.length > 12 ? t.taskName.substring(0, 12) + '...' : t.taskName,
      fullName: t.taskName,
      rendimiento: t.avgPerformance,
      completadas: t.count,
      alto: t.distribution.alto,
      medio: t.distribution.medio,
      bajo: t.distribution.bajo,
    }));

    return (
      <>
        <Row gutter={16} style={{ marginBottom: 24 }}>
          <Col span={5}>
            <Card size="small" style={{ textAlign: 'center' }}>
              <Statistic title="Tareas Completadas" value={d.summary.totalCompleted} prefix={<TrophyOutlined />} />
            </Card>
          </Col>
          <Col span={5}>
            <Card size="small" style={{ textAlign: 'center' }}>
              <Statistic title="Rendimiento Promedio" value={d.summary.avgPerformance || 0} suffix="%" precision={1}
                valueStyle={{ color: (d.summary.avgPerformance || 0) >= 100 ? '#52c41a' : '#faad14' }} />
            </Card>
          </Col>
          <Col span={4}>
            <Card size="small" style={{ textAlign: 'center' }}>
              <Statistic title="Alto" value={d.summary.distribution.alto} valueStyle={{ color: '#52c41a' }} prefix={<ArrowUpOutlined />} />
            </Card>
          </Col>
          <Col span={4}>
            <Card size="small" style={{ textAlign: 'center' }}>
              <Statistic title="Medio" value={d.summary.distribution.medio} valueStyle={{ color: '#faad14' }} />
            </Card>
          </Col>
          <Col span={3}>
            <Card size="small" style={{ textAlign: 'center' }}>
              <Statistic title="Bajo" value={d.summary.distribution.bajo} valueStyle={{ color: '#f5222d' }} prefix={<ArrowDownOutlined />} />
            </Card>
          </Col>
          <Col span={3}>
            <Card size="small" style={{ textAlign: 'center' }}>
              <Statistic title="Sobrepaso" value={d.summary.distribution.sobrepaso} valueStyle={{ color: '#f5222d' }} prefix={<WarningOutlined />} />
            </Card>
          </Col>
        </Row>

        <Row gutter={24} style={{ marginBottom: 24 }}>
          {/* Pie de distribución */}
          <Col span={8}>
            <Card size="small" title="Distribución por Nivel" style={{ height: 340 }}>
              <ResponsiveContainer width="100%" height={280}>
                <PieChart>
                  <Pie data={pieData} cx="50%" cy="50%" innerRadius={50} outerRadius={100} paddingAngle={3} dataKey="value"
                    label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}>
                    {pieData.map((entry, i) => <Cell key={i} fill={entry.color} />)}
                  </Pie>
                  <Tooltip contentStyle={CustomTooltipStyle}
                    formatter={(value: any, name: any) => [`${value} tareas`, name]} />
                </PieChart>
              </ResponsiveContainer>
            </Card>
          </Col>
          {/* Bar de rendimiento promedio por tarea */}
          <Col span={16}>
            <Card size="small" title="Rendimiento Promedio por Tarea (%)" style={{ height: 340 }}>
              <ResponsiveContainer width="100%" height={280}>
                <ComposedChart data={barData} margin={{ top: 10, right: 20, left: 0, bottom: 30 }}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                  <XAxis dataKey="name" angle={-25} textAnchor="end" fontSize={11} />
                  <YAxis domain={[0, 'auto']} tickFormatter={(v) => `${v}%`} />
                  <Tooltip contentStyle={CustomTooltipStyle}
                    formatter={(value: any, name: any) => {
                      if (name === 'rendimiento') return [`${value}%`, 'Rendimiento Prom.'];
                      return [value, name];
                    }}
                    labelFormatter={(label: any, payload: any) => payload?.[0]?.payload?.fullName || label} />
                  <ReferenceLine y={100} stroke="#52c41a" strokeDasharray="5 5" label={{ value: '100% Objetivo', position: 'right', fontSize: 10, fill: '#52c41a' }} />
                  <ReferenceLine y={80} stroke="#faad14" strokeDasharray="3 3" label={{ value: '80% Medio', position: 'right', fontSize: 10, fill: '#faad14' }} />
                  <Bar dataKey="rendimiento" fill="#1890ff" radius={[4, 4, 0, 0]} name="rendimiento" />
                  <Line type="monotone" dataKey="completadas" stroke="#722ed1" strokeWidth={2} dot={{ r: 4 }} name="Tareas" yAxisId={0} />
                </ComposedChart>
              </ResponsiveContainer>
            </Card>
          </Col>
        </Row>

        <Card size="small" title="Detalle de Tareas Completadas">
          <Table dataSource={d.details} rowKey="code" size="small" pagination={{ pageSize: 10, showSizeChanger: true }}
            columns={[
              { title: 'Código', dataIndex: 'code', width: 100, render: (c: string) => <Tag>{c}</Tag> },
              { title: 'Tarea', dataIndex: 'taskName' },
              { title: 'Finca', dataIndex: 'locationName' },
              { title: 'Completada', dataIndex: 'completedAt', width: 110 },
              { title: 'Jornales', width: 120, render: (_: any, r: any) => (
                <AntTooltip title={`Plan: ${r.budgetedJornales} | Real: ${r.realJornales} | Ahorro: ${r.budgetedJornales - r.realJornales}`}>
                  <Text>{r.realJornales} <Text type="secondary">/ {r.budgetedJornales}</Text></Text>
                </AntTooltip>
              )},
              { title: 'Rendimiento', dataIndex: 'performancePct', width: 120, render: (v: any) => (
                <Progress percent={Math.min(Number(v), 200)} format={() => `${v}%`} size="small"
                  strokeColor={Number(v) >= 100 ? '#52c41a' : Number(v) >= 80 ? '#faad14' : '#f5222d'} />
              )},
              { title: 'Nivel', dataIndex: 'level', width: 110, render: (l: string) => (
                <Tag color={LEVEL_TAG_COLORS[l]} icon={l === 'sobrepaso' ? <WarningOutlined /> : l === 'alto' ? <TrophyOutlined /> : undefined}>
                  {LEVEL_LABELS[l] || l}
                </Tag>
              )},
            ]} />
        </Card>
      </>
    );
  };

  // ─── TAB 2: Cumplimiento ───
  const renderTab2 = () => {
    if (r2Loading) return <Spin size="large" style={{ display: 'block', margin: '60px auto' }} />;
    const d = (r2Data as any)?.data;
    if (!d) return <Empty />;

    const barData = (d.programmed || []).map((p: any) => ({
      name: p.code,
      taskName: p.taskName,
      plan: p.budgetedJornales,
      real: p.realJornales,
      variation: p.variation,
    }));

    return (
      <>
        <Row gutter={16} style={{ marginBottom: 24 }}>
          <Col span={6}>
            <Card size="small" style={{ textAlign: 'center', borderTop: '3px solid #1890ff' }}>
              <Statistic title="Jornales Presupuestados" value={d.summary.totalBudgetedJornales}
                prefix={<ClockCircleOutlined />} valueStyle={{ color: '#1890ff' }} />
            </Card>
          </Col>
          <Col span={6}>
            <Card size="small" style={{ textAlign: 'center', borderTop: '3px solid #52c41a' }}>
              <Statistic title="Jornales Reales (Prog.)" value={d.summary.totalRealProgrammed}
                prefix={<TeamOutlined />} valueStyle={{ color: '#52c41a' }} />
            </Card>
          </Col>
          <Col span={6}>
            <Card size="small" style={{ textAlign: 'center', borderTop: '3px solid #fa8c16' }}>
              <Statistic title="Jornales Ad-Hoc" value={d.summary.totalAdHocJornales}
                prefix={<WarningOutlined />} valueStyle={{ color: '#fa8c16' }} />
            </Card>
          </Col>
          <Col span={6}>
            <Card size="small" style={{ textAlign: 'center', borderTop: `3px solid ${d.summary.variationPct > 0 ? '#f5222d' : '#52c41a'}` }}>
              <Statistic title="Variación Global" value={d.summary.variationPct} suffix="%"
                prefix={d.summary.variationPct > 0 ? <ArrowUpOutlined /> : <ArrowDownOutlined />}
                valueStyle={{ color: d.summary.variationPct > 0 ? '#f5222d' : '#52c41a' }} />
              <Text type="secondary" style={{ fontSize: 11 }}>
                {d.summary.variationPct > 0 ? 'Sobre presupuesto' : 'Ahorro vs presupuesto'}
              </Text>
            </Card>
          </Col>
        </Row>

        {barData.length > 0 && (
          <Card size="small" title="Jornales: Presupuesto vs Real" style={{ marginBottom: 24 }}>
            <ResponsiveContainer width="100%" height={300}>
              <BarChart data={barData} margin={{ top: 10, right: 30, left: 0, bottom: 30 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                <XAxis dataKey="name" angle={-25} textAnchor="end" fontSize={11} />
                <YAxis label={{ value: 'Jornales', angle: -90, position: 'insideLeft', fontSize: 11 }} />
                <Tooltip contentStyle={CustomTooltipStyle}
                  labelFormatter={(label: any, payload: any) => `${label} — ${payload?.[0]?.payload?.taskName || ''}`}
                  formatter={(value: any, name: any) => [value, name === 'plan' ? 'Presupuestado' : 'Real']} />
                <Legend formatter={(value) => value === 'plan' ? 'Presupuestado' : 'Real'} />
                <Bar dataKey="plan" fill="#1890ff" radius={[4, 4, 0, 0]} name="plan" />
                <Bar dataKey="real" fill="#52c41a" radius={[4, 4, 0, 0]} name="real" />
              </BarChart>
            </ResponsiveContainer>
          </Card>
        )}

        <Card size="small" title="Detalle de Cumplimiento">
          <Table dataSource={d.programmed} rowKey="code" size="small" pagination={false}
            columns={[
              { title: 'Código', dataIndex: 'code', width: 100, render: (c: string) => <Tag>{c}</Tag> },
              { title: 'Tarea', dataIndex: 'taskName' },
              { title: 'Finca', dataIndex: 'locationName' },
              { title: 'Avance', dataIndex: 'accumulatedPct', width: 120, render: (v: any) => <Progress percent={Number(v)} size="small" /> },
              { title: 'Plan', dataIndex: 'budgetedJornales', width: 60, render: (v: any) => <Text strong>{v}</Text> },
              { title: 'Real', dataIndex: 'realJornales', width: 60 },
              { title: 'Variación', dataIndex: 'variation', width: 100, render: (v: any) => (
                <Tag color={v > 0 ? 'red' : v < 0 ? 'green' : 'default'} icon={v > 0 ? <ArrowUpOutlined /> : v < 0 ? <ArrowDownOutlined /> : undefined}>
                  {v > 0 ? '+' : ''}{v}%
                </Tag>
              )},
              { title: 'Estado', dataIndex: 'status', width: 110, render: (s: string) => <Tag color={STATUS_COLORS[s]}>{s}</Tag> },
            ]} />
        </Card>
      </>
    );
  };

  // ─── TAB 3: Gantt Visual ───
  const renderTab3 = () => {
    if (r3Loading) return <Spin size="large" style={{ display: 'block', margin: '60px auto' }} />;
    const bars = (r3Data as any)?.data?.bars || [];
    if (bars.length === 0) return <Empty description="No hay programaciones en este rango" />;

    // Calcular posiciones de las barras para un pseudo-Gantt
    const rangeStart = dateRange[0];
    const rangeEnd = dateRange[1];
    const totalDays = rangeEnd.diff(rangeStart, 'day') + 1;
    const todayOffset = dayjs().diff(rangeStart, 'day');

    return (
      <>
        <Card size="small" title="Vista Gantt de Programaciones" style={{ marginBottom: 16 }}>
          <div style={{ overflowX: 'auto' }}>
            {/* Header con fechas */}
            <div style={{ display: 'flex', minWidth: 800, borderBottom: '1px solid #f0f0f0', paddingBottom: 8, marginBottom: 8 }}>
              <div style={{ width: 250, flexShrink: 0, fontWeight: 600, fontSize: 12, color: '#8c8c8c' }}>Tarea / Finca</div>
              <div style={{ flex: 1, position: 'relative', height: 20 }}>
                {Array.from({ length: Math.min(totalDays, 60) }, (_, i) => {
                  const d = rangeStart.add(i, 'day');
                  const showLabel = i % 3 === 0 || i === totalDays - 1;
                  return showLabel ? (
                    <span key={i} style={{
                      position: 'absolute', left: `${(i / totalDays) * 100}%`, fontSize: 9, color: '#8c8c8c',
                      transform: 'translateX(-50%)',
                    }}>{d.format('DD/MM')}</span>
                  ) : null;
                })}
              </div>
            </div>
            {/* Barras */}
            {bars.map((bar: any) => {
              const barStart = dayjs(bar.startDate).diff(rangeStart, 'day');
              const barEnd = dayjs(bar.endDate).diff(rangeStart, 'day');
              const left = Math.max(0, (barStart / totalDays) * 100);
              const width = Math.max(2, ((barEnd - barStart + 1) / totalDays) * 100);
              const barColor = bar.finalLevel ? LEVEL_COLORS_HEX[bar.finalLevel] :
                bar.status === 'en_progreso' ? '#52c41a' : bar.status === 'cancelada' ? '#ff4d4f' : '#1890ff';

              return (
                <div key={bar.id} style={{ display: 'flex', minWidth: 800, marginBottom: 10, alignItems: 'center' }}>
                  <div style={{ width: 250, flexShrink: 0, fontSize: 12 }}>
                    <AntTooltip title={`${bar.taskName} — ${bar.locationName}${bar.lotName ? ' - ' + bar.lotName : ''} | Avance: ${bar.accumulatedPct}%`}>
                      <Text ellipsis style={{ width: 240 }}>
                        {bar.isAdHoc && <WarningOutlined style={{ color: '#fa8c16', marginRight: 4 }} />}
                        <Text strong>{bar.taskName}</Text>
                        <Text type="secondary"> — {bar.locationName}</Text>
                      </Text>
                    </AntTooltip>
                  </div>
                  <div style={{ flex: 1, position: 'relative', height: 36 }}>
                    {/* Línea de hoy */}
                    {todayOffset >= 0 && todayOffset <= totalDays && (
                      <div style={{
                        position: 'absolute', left: `${(todayOffset / totalDays) * 100}%`,
                        top: 0, bottom: 0, width: 2, backgroundColor: '#f5222d', zIndex: 3, opacity: 0.5,
                      }} />
                    )}

                    {/* Barra 1: PROGRAMACIÓN (plan) — fondo gris claro punteado */}
                    <AntTooltip title={`Programado: ${bar.startDate} → ${bar.endDate}`}>
                      <div style={{
                        position: 'absolute', left: `${left}%`, width: `${width}%`,
                        height: 14, top: 2,
                        backgroundColor: '#e6f4ff',
                        border: '1px dashed #1890ff',
                        borderRadius: 3,
                        display: 'flex', alignItems: 'center', paddingLeft: 4,
                        fontSize: 9, color: '#1890ff', fontWeight: 600,
                      }}>
                        Plan
                      </div>
                    </AntTooltip>

                    {/* Barra 2: EJECUCIÓN (real) — coloreada según estado */}
                    <AntTooltip title={`${bar.taskName} | Ejecución: ${bar.accumulatedPct}% | ${bar.status}${bar.finalLevel ? ' | Nivel: ' + bar.finalLevel.toUpperCase() : ''}`}>
                      <div style={{
                        position: 'absolute', left: `${left}%`,
                        width: `${width * (bar.accumulatedPct / 100)}%`,
                        height: 14, top: 20,
                        backgroundColor: barColor, borderRadius: 3, opacity: 0.9,
                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                        color: '#fff', fontSize: 9, fontWeight: 600, cursor: 'pointer',
                        minWidth: bar.accumulatedPct > 0 ? 30 : 0,
                      }}>
                        {bar.accumulatedPct > 10 ? `${bar.accumulatedPct}%` : ''}
                      </div>
                    </AntTooltip>
                    {/* Background de ejecución vacío (gris) */}
                    {bar.accumulatedPct < 100 && (
                      <div style={{
                        position: 'absolute', left: `${left + (width * bar.accumulatedPct / 100)}%`,
                        width: `${width * (1 - bar.accumulatedPct / 100)}%`,
                        height: 14, top: 20,
                        backgroundColor: '#fafafa',
                        border: '1px solid #e8e8e8', borderRadius: 3,
                      }} />
                    )}
                  </div>
                </div>
              );
            })}
            {/* Leyenda */}
            <Divider style={{ margin: '12px 0 8px' }} />
            <Space size="large" wrap style={{ fontSize: 11, color: '#8c8c8c' }}>
              <span><span style={{ display: 'inline-block', width: 16, height: 10, backgroundColor: '#e6f4ff', border: '1px dashed #1890ff', borderRadius: 2, marginRight: 4 }} /><Text strong>Programación (plan)</Text></span>
              <span><span style={{ display: 'inline-block', width: 16, height: 10, backgroundColor: '#52c41a', borderRadius: 2, marginRight: 4 }} /><Text strong>Ejecución</Text> (color = estado/nivel)</span>
              <span><span style={{ display: 'inline-block', width: 12, height: 12, backgroundColor: '#1890ff', borderRadius: 2, marginRight: 4 }} />En curso</span>
              <span><span style={{ display: 'inline-block', width: 12, height: 12, backgroundColor: '#8c8c8c', borderRadius: 2, marginRight: 4 }} />Completada</span>
              <span><span style={{ display: 'inline-block', width: 12, height: 12, backgroundColor: '#ff4d4f', borderRadius: 2, marginRight: 4 }} />Cancelada/Bajo</span>
              <span><span style={{ display: 'inline-block', width: 2, height: 12, backgroundColor: '#f5222d', marginRight: 4 }} />Hoy</span>
            </Space>
          </div>
        </Card>

        <Card size="small" title="Detalle de Programaciones">
          <Table dataSource={bars} rowKey="id" size="small" pagination={false}
            columns={[
              { title: 'Tarea', dataIndex: 'taskName' },
              { title: 'Finca', dataIndex: 'locationName' },
              { title: 'Lote', dataIndex: 'lotName', render: (v: any) => v || '-' },
              { title: 'Periodo', width: 180, render: (_: any, r: any) => `${r.startDate} → ${r.endDate}` },
              { title: 'Avance', dataIndex: 'accumulatedPct', width: 140,
                render: (v: any, r: any) => <Progress percent={v} size="small" status={r.status === 'cancelada' ? 'exception' : undefined}
                  strokeColor={r.finalLevel ? LEVEL_COLORS_HEX[r.finalLevel] : undefined} /> },
              { title: 'Estado', dataIndex: 'status', width: 110, render: (s: string) => <Tag color={STATUS_COLORS[s]}>{s}</Tag> },
              { title: 'Nivel', dataIndex: 'finalLevel', width: 100,
                render: (l: string) => l ? <Tag color={LEVEL_TAG_COLORS[l]}>{LEVEL_LABELS[l]}</Tag> : '-' },
            ]} />
        </Card>
      </>
    );
  };

  // ─── TAB 4: Comparativa (placeholder mejorado) ───
  const renderTab4 = () => (
    <Card style={{ textAlign: 'center', padding: '60px 24px' }}>
      <SwapOutlined style={{ fontSize: 48, color: '#bfbfbf', marginBottom: 16 }} />
      <Title level={4} type="secondary">Eficiencia Comparativa entre Fincas</Title>
      <Text type="secondary" style={{ display: 'block', maxWidth: 400, margin: '0 auto' }}>
        Seleccione una tarea especifica en el filtro superior para comparar su rendimiento promedio entre diferentes fincas.
        Requiere al menos 2 fincas con tareas completadas del mismo tipo.
      </Text>
    </Card>
  );

  // ─── TAB 5: Proyección ───
  const renderTab5 = () => {
    if (r5Loading) return <Spin size="large" style={{ display: 'block', margin: '60px auto' }} />;
    const data = (r5Data as any)?.data || [];
    if (data.length === 0) return <Empty description="No hay tareas en progreso para proyectar" />;

    const chartData = data.map((d: any) => ({
      name: d.code,
      taskName: d.taskName,
      avance: d.accumulatedPct,
      ritmo: d.dailyRate,
      diasRestantes: d.daysRemaining,
    }));

    return (
      <>
        <Card size="small" title="Ritmo de Avance y Proyección" style={{ marginBottom: 24 }}>
          <ResponsiveContainer width="100%" height={300}>
            <ComposedChart data={chartData} margin={{ top: 10, right: 30, left: 0, bottom: 30 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
              <XAxis dataKey="name" fontSize={11} />
              <YAxis yAxisId="left" domain={[0, 100]} tickFormatter={(v) => `${v}%`} label={{ value: 'Avance %', angle: -90, position: 'insideLeft', fontSize: 11 }} />
              <YAxis yAxisId="right" orientation="right" label={{ value: 'Días restantes', angle: 90, position: 'insideRight', fontSize: 11 }} />
              <Tooltip contentStyle={CustomTooltipStyle}
                labelFormatter={(label: any, payload: any) => `${label} — ${payload?.[0]?.payload?.taskName || ''}`}
                formatter={(value: any, name: any) => {
                  if (name === 'avance') return [`${value}%`, 'Avance Actual'];
                  if (name === 'ritmo') return [`${value}%/dia`, 'Ritmo Diario'];
                  if (name === 'diasRestantes') return [`${value} dias`, 'Dias Restantes'];
                  return [value, name];
                }} />
              <Legend />
              <Area yAxisId="left" type="monotone" dataKey="avance" fill="#e6f7ff" stroke="#1890ff" strokeWidth={2} name="avance" />
              <Bar yAxisId="right" dataKey="diasRestantes" fill="#fa8c16" radius={[4, 4, 0, 0]} name="diasRestantes" barSize={20} />
            </ComposedChart>
          </ResponsiveContainer>
        </Card>

        <Card size="small" title="Detalle de Proyecciones">
          <Table dataSource={data} rowKey="code" size="small" pagination={false}
            columns={[
              { title: 'Código', dataIndex: 'code', width: 100, render: (c: string) => <Tag>{c}</Tag> },
              { title: 'Tarea', dataIndex: 'taskName' },
              { title: 'Finca', dataIndex: 'locationName' },
              { title: 'Avance', dataIndex: 'accumulatedPct', width: 130,
                render: (v: any) => <Progress percent={v} size="small" strokeColor={v >= 70 ? '#52c41a' : v >= 40 ? '#faad14' : '#f5222d'} /> },
              { title: 'Ritmo', dataIndex: 'dailyRate', width: 90,
                render: (v: any) => <AntTooltip title="Porcentaje de avance promedio por dia habil"><Tag color="blue">{v}%/dia</Tag></AntTooltip> },
              { title: 'Dias Rest.', dataIndex: 'daysRemaining', width: 80,
                render: (v: any) => v !== null ? <Text strong>{v} dias</Text> : '-' },
              { title: 'Fin Plan', dataIndex: 'plannedEndDate', width: 100 },
              { title: 'Fin Proy.', dataIndex: 'projectedEndDate', width: 100,
                render: (v: any) => v || <Text type="secondary">-</Text> },
              { title: 'Dif.', dataIndex: 'diffDays', width: 70,
                render: (v: any) => v !== null ? (
                  <Tag color={v >= 0 ? 'green' : v >= -3 ? 'orange' : 'red'}>{v > 0 ? '+' : ''}{v}d</Tag>
                ) : '-' },
              { title: 'Alerta', dataIndex: 'alert', width: 110,
                render: (a: string) => (
                  <Tag color={ALERT_COLORS[a]} style={{ fontWeight: 600 }}>
                    {a === 'atrasada' && <WarningOutlined />} {ALERT_LABELS[a] || a}
                  </Tag>
                )},
            ]} />
        </Card>
      </>
    );
  };

  // ─── TAB 6: Rendimiento Diario por Programación ───
  const renderTab6 = () => {
    return <DailyPerformanceReport />;
  };

  return (
    <div style={{ padding: 24 }}>
      <Title level={3} style={{ margin: 0, marginBottom: 8 }}>
        <BarChartOutlined /> Reportes de Rendimiento
      </Title>
      <Text type="secondary" style={{ display: 'block', marginBottom: 16 }}>
        Analiza el desempeno de las tareas agricolas con graficas interactivas y datos detallados
      </Text>

      <Card size="small" style={{ marginBottom: 16 }}>
        <Space wrap>
          <RangePicker value={dateRange} onChange={(v) => v && setDateRange([v[0]!, v[1]!])} format="DD/MM/YYYY" />
          <Select placeholder="Finca" allowClear style={{ width: 180 }} value={selectedFarm} onChange={setSelectedFarm}>
            {locations.map((l: any) => <Option key={l.id} value={l.id}>{l.name}</Option>)}
          </Select>
          <Select placeholder="Tarea" allowClear style={{ width: 200 }} value={selectedTask} onChange={setSelectedTask} showSearch optionFilterProp="children">
            {tasks.map((t: any) => <Option key={t.id} value={t.id}>{t.name}</Option>)}
          </Select>
        </Space>
      </Card>

      <Tabs activeKey={activeTab} onChange={setActiveTab} items={[
        { key: '1', label: <span><BarChartOutlined /> Rendimiento</span>, children: renderTab1() },
        { key: '2', label: <span><CheckCircleOutlined /> Cumplimiento</span>, children: renderTab2() },
        { key: '3', label: <span><CalendarOutlined /> Gantt</span>, children: renderTab3() },
        { key: '4', label: <span><SwapOutlined /> Comparativa</span>, children: renderTab4() },
        { key: '5', label: <span><RocketOutlined /> Proyeccion</span>, children: renderTab5() },
        { key: '6', label: <span><BarChartOutlined /> Diario</span>, children: renderTab6() },
      ]} />
    </div>
  );
};

// ─── Sub-componente: Rendimiento Diario por programación ───
const DailyPerformanceReport = () => {
  const [selectedScheduleId, setSelectedScheduleId] = useState<string | undefined>();

  const { data: schedulesData } = useQuery({
    queryKey: ['schedulesForDaily'],
    queryFn: () => taskScheduleApi.list({ status: undefined, per_page: 100 }),
  });

  const { data: dailyData, isLoading } = useQuery({
    queryKey: ['dailyPerformance', selectedScheduleId],
    queryFn: () => selectedScheduleId
      ? taskScheduleApi.dailyPerformance(selectedScheduleId)
      : Promise.resolve(null),
    enabled: !!selectedScheduleId,
  });

  const schedules = (schedulesData as any)?.data || [];
  const data = (dailyData as any)?.data;

  return (
    <div>
      <Card size="small" style={{ marginBottom: 16 }}>
        <Space wrap>
          <Text strong>Seleccione una programación:</Text>
          <Select
            placeholder="Buscar programación..."
            style={{ width: 400 }}
            showSearch
            optionFilterProp="children"
            value={selectedScheduleId}
            onChange={setSelectedScheduleId}
          >
            {schedules.map((s: any) => (
              <Option key={s.id} value={s.id}>
                <Tag color={STATUS_COLORS[s.status]} style={{ marginRight: 4 }}>{s.code}</Tag>
                {s.task?.name} — {s.location?.name}{s.lot ? ` / ${s.lot.name}` : ''}
                {s.finalLevel && <Tag color={LEVEL_TAG_COLORS[s.finalLevel]} style={{ marginLeft: 6 }}>{s.finalLevel}</Tag>}
              </Option>
            ))}
          </Select>
        </Space>
      </Card>

      {isLoading && <Spin size="large" style={{ display: 'block', margin: '60px auto' }} />}
      {!selectedScheduleId && (
        <Empty description="Seleccione una programación para ver su rendimiento diario" />
      )}
      {data && (() => {
        const s = data.schedule;
        const summary = data.summary;
        const daily = data.daily || [];

        const chartData = daily.map((d: any) => ({
          date: d.date.substring(5),
          personas: d.persons,
          esperado: summary.expectedDailyJornales,
          eficiencia: d.efficiency,
        }));

        return (
          <>
            <Card size="small" title={<Space><Tag>{s.code}</Tag><Text strong>{s.taskName}</Text> — {s.locationName}{s.lotName ? ` / ${s.lotName}` : ''}</Space>} style={{ marginBottom: 16 }}>
              <Row gutter={16}>
                <Col span={4}><Statistic title="Estado" value={s.status} valueStyle={{ fontSize: 14, color: STATUS_COLORS[s.status] }} /></Col>
                <Col span={4}><Statistic title="Días Trabajados" value={summary.totalDaysWorked} suffix={`/${s.workingDays}`} /></Col>
                <Col span={4}><Statistic title="Total Personas" value={summary.totalPersons} prefix={<TeamOutlined />} /></Col>
                <Col span={4}><Statistic title="Promedio/día" value={summary.avgPersonsPerDay} valueStyle={{ color: '#1890ff' }} /></Col>
                <Col span={4}><Statistic title="Plan/día" value={summary.expectedDailyJornales} valueStyle={{ color: '#722ed1' }} /></Col>
                <Col span={4}>
                  {s.finalLevel ? (
                    <div>
                      <Text type="secondary" style={{ fontSize: 12 }}>Rendimiento</Text>
                      <div><Tag color={LEVEL_TAG_COLORS[s.finalLevel]}>{s.finalLevel?.toUpperCase()}</Tag></div>
                      <Text strong>{s.finalPerformancePct}%</Text>
                    </div>
                  ) : (
                    <Statistic title="Avance" value={s.accumulatedPct} suffix="%" />
                  )}
                </Col>
              </Row>
            </Card>

            <Card size="small" title="Personas por Día (real vs plan)" style={{ marginBottom: 16 }}>
              <ResponsiveContainer width="100%" height={300}>
                <ComposedChart data={chartData} margin={{ top: 10, right: 30, left: 0, bottom: 30 }}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                  <XAxis dataKey="date" fontSize={11} />
                  <YAxis label={{ value: 'Personas', angle: -90, position: 'insideLeft', fontSize: 11 }} />
                  <Tooltip contentStyle={CustomTooltipStyle}
                    formatter={(value: any, name: any) => {
                      if (name === 'personas') return [`${value} personas`, 'Real'];
                      if (name === 'esperado') return [`${value} jornales/día`, 'Plan/día'];
                      if (name === 'eficiencia') return [`${value}%`, 'Eficiencia'];
                      return [value, name];
                    }} />
                  <Legend formatter={(v) => v === 'personas' ? 'Personas Real' : v === 'esperado' ? 'Plan/día' : v} />
                  <Bar dataKey="personas" fill="#1890ff" radius={[4, 4, 0, 0]} name="personas" />
                  <Line type="monotone" dataKey="esperado" stroke="#722ed1" strokeWidth={2} strokeDasharray="5 5" dot={false} name="esperado" />
                </ComposedChart>
              </ResponsiveContainer>
            </Card>

            <Card size="small" title="Detalle Diario">
              <Table dataSource={daily} rowKey="date" size="small" pagination={false}
                columns={[
                  { title: 'Fecha', dataIndex: 'date', width: 120 },
                  { title: 'Personas', dataIndex: 'persons', width: 100, render: (v: any) => <Text strong>{v}</Text> },
                  { title: '# Logs', dataIndex: 'logsCount', width: 80, render: (v: any) => <Tag>{v}</Tag> },
                  { title: '% Avance', dataIndex: 'advancePct', width: 100, render: (v: any) => v ? `${v}%` : '-' },
                  { title: 'Eficiencia', dataIndex: 'efficiency', width: 110,
                    render: (v: any) => v != null ? (
                      <Tag color={v >= 100 ? 'green' : v >= 70 ? 'gold' : 'red'}>{v}%</Tag>
                    ) : '-' },
                  { title: 'Observaciones', dataIndex: 'observations', ellipsis: true, render: (v: any) => v || '-' },
                ]} />
            </Card>
          </>
        );
      })()}
    </div>
  );
};

export default PerformanceReports;
