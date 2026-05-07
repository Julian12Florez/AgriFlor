import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Table, Button, Modal, Form, Input, Select, InputNumber, Tag, Space,
  Card, DatePicker, Progress, Typography, Tooltip, message, Row, Col,
  Descriptions, Divider, Alert
} from 'antd';
import {
  PlusOutlined, EyeOutlined, StopOutlined,
  ThunderboltOutlined, CalendarOutlined
} from '@ant-design/icons';
import dayjs from 'dayjs';
import { taskScheduleApi, taskCatalogApi, locationsApi, farmLotsApi, workerAvailabilityApi, handleApiError } from '../../services/api';

const { Option } = Select;
const { Title, Text } = Typography;
const { RangePicker } = DatePicker;

const UNIT_LABELS: Record<string, string> = {
  arbol: 'Árbol', hectarea: 'Hectárea', m2: 'm²', metro: 'Metro',
};
const UNIT_COLORS: Record<string, string> = {
  arbol: 'green', hectarea: 'blue', m2: 'purple', metro: 'orange',
};

const STATUS_COLORS: Record<string, string> = {
  planificada: 'blue',
  en_progreso: 'processing',
  completada: 'success',
  cancelada: 'default',
};

const STATUS_LABELS: Record<string, string> = {
  planificada: 'Planificada',
  en_progreso: 'En Progreso',
  completada: 'Completada',
  cancelada: 'Cancelada',
};

const LEVEL_COLORS: Record<string, string> = {
  sobrepaso: 'red',
  alto: 'green',
  medio: 'gold',
  bajo: 'red',
};

const ScheduleList = () => {
  const [form] = Form.useForm();
  const [isCreateModal, setIsCreateModal] = useState(false);
  const [isDetailModal, setIsDetailModal] = useState(false);
  const [selectedSchedule, setSelectedSchedule] = useState<any>(null);
  const [cancelModal, setCancelModal] = useState<string | null>(null);
  const [cancelReason, setCancelReason] = useState('');
  const [selectedFarm, setSelectedFarm] = useState<string | undefined>();
  const [lots, setLots] = useState<any[]>([]);
  const [filters, setFilters] = useState<Record<string, any>>({ per_page: 20 });
  const queryClient = useQueryClient();

  const { data, isLoading } = useQuery({
    queryKey: ['schedules', filters],
    queryFn: () => taskScheduleApi.list(filters),
  });

  const { data: tasksData } = useQuery({
    queryKey: ['taskCatalogActive'],
    queryFn: () => taskCatalogApi.list({ active: 'true', per_page: 100 }),
  });

  const { data: locationsData } = useQuery({
    queryKey: ['farmLocations'],
    queryFn: () => locationsApi.list({ type: 'farm', status: 'active', per_page: 100 }),
  });

  const [availability, setAvailability] = useState<any>(null);
  const [selectedTaskData, setSelectedTaskData] = useState<any>(null);
  const [selectedLotData, setSelectedLotData] = useState<any>(null);
  const [finalizeModal, setFinalizeModal] = useState<any>(null);
  const [finalizeResult, setFinalizeResult] = useState<any>(null);

  const createMutation = useMutation({
    mutationFn: (values: any) => taskScheduleApi.create(values),
    onSuccess: (res: any) => {
      message.success(res.message || 'Programación creada');
      queryClient.invalidateQueries({ queryKey: ['schedules'] });
      setIsCreateModal(false);
      form.resetFields();
    },
    onError: (err: any) => handleApiError(err),
  });

  const finalizeMutation = useMutation({
    mutationFn: (scheduleId: string) => taskScheduleApi.finalize(scheduleId),
    onSuccess: (res: any) => {
      message.success(res.message || 'Tarea finalizada');
      setFinalizeResult(res?.data || null);
      setFinalizeModal(null);
      queryClient.invalidateQueries({ queryKey: ['schedules'] });
    },
    onError: (err: any) => handleApiError(err),
  });

  const cancelMutation = useMutation({
    mutationFn: ({ id, reason }: { id: string; reason: string }) =>
      taskScheduleApi.cancel(id, reason),
    onSuccess: (res: any) => {
      message.success(res.message || 'Programación cancelada');
      queryClient.invalidateQueries({ queryKey: ['schedules'] });
      setCancelModal(null);
      setCancelReason('');
    },
    onError: (err: any) => handleApiError(err),
  });

  const handleFarmChange = async (locationId: string) => {
    setSelectedFarm(locationId);
    form.setFieldValue('lot_id', undefined);
    try {
      const [lotsRes, availRes] = await Promise.all([
        farmLotsApi.getByLocation(locationId),
        workerAvailabilityApi.get({ location_id: locationId }),
      ]);
      setLots((lotsRes as any)?.data || []);
      setAvailability((availRes as any)?.data || null);
    } catch {
      setLots([]);
      setAvailability(null);
    }
  };

  const handleCreate = (values: any) => {
    const data: any = {
      task_catalog_id: values.task_catalog_id,
      location_id: values.location_id,
      lot_id: values.lot_id || null,
      total_quantity: values.total_quantity,
      start_date: values.start_date.format('YYYY-MM-DD'),
      planned_persons: values.planned_persons || 0,
      external_farm_workers: values.external_farm_workers || 0,
      third_party_workers: values.third_party_workers || 0,
      observations: values.observations || null,
    };
    // Si el usuario ingresó end_date, lo enviamos (modo inverso: backend calcula personas)
    if (values.end_date) {
      data.end_date = values.end_date.format('YYYY-MM-DD');
    }
    createMutation.mutate(data);
  };

  const openDetail = async (record: any) => {
    try {
      const res = await taskScheduleApi.get(record.id);
      setSelectedSchedule((res as any)?.data || record);
      setIsDetailModal(true);
    } catch {
      setSelectedSchedule(record);
      setIsDetailModal(true);
    }
  };

  const tasks = (tasksData as any)?.data || [];
  const locations = (locationsData as any)?.data || [];
  const schedules = data?.data || [];

  const columns = [
    {
      title: 'Código',
      dataIndex: 'code',
      key: 'code',
      width: 110,
      render: (code: string, record: any) => (
        <Space>
          <Tag>{code}</Tag>
          {record.isAdHoc && <Tooltip title="Tarea ad-hoc"><ThunderboltOutlined style={{ color: '#fa8c16' }} /></Tooltip>}
        </Space>
      ),
    },
    {
      title: 'Tarea',
      key: 'task',
      render: (_: any, record: any) => record.task?.name || '-',
    },
    {
      title: 'Finca / Lote',
      key: 'location',
      render: (_: any, record: any) => (
        <span>{record.location?.name}{record.lot ? ` - ${record.lot.name}` : ''}</span>
      ),
    },
    {
      title: 'Avance',
      key: 'advance',
      width: 140,
      render: (_: any, record: any) => (
        <Progress
          percent={Number(record.accumulatedPct || 0)}
          size="small"
          status={record.status === 'cancelada' ? 'exception' : undefined}
        />
      ),
    },
    {
      title: 'Jornales',
      key: 'jornales',
      width: 100,
      render: (_: any, record: any) => (
        <Text>{record.realJornales || 0} / {record.budgetedJornales}</Text>
      ),
    },
    {
      title: 'Estado',
      dataIndex: 'status',
      key: 'status',
      width: 120,
      render: (status: string) => (
        <Tag color={STATUS_COLORS[status]}>{STATUS_LABELS[status] || status}</Tag>
      ),
    },
    {
      title: 'Nivel',
      key: 'level',
      width: 90,
      render: (_: any, record: any) =>
        record.finalLevel ? (
          <Tag color={LEVEL_COLORS[record.finalLevel]}>
            {record.finalLevel.toUpperCase()} ({record.finalPerformancePct}%)
          </Tag>
        ) : '-',
    },
    {
      title: 'Acciones',
      key: 'actions',
      width: 230,
      render: (_: any, record: any) => (
        <Space size="small">
          <Button size="small" icon={<EyeOutlined />} onClick={() => openDetail(record)}>
            Ver
          </Button>
          {record.status === 'en_progreso' && (
            <Button
              size="small"
              type="primary"
              onClick={() => setFinalizeModal(record)}
            >
              Finalizar
            </Button>
          )}
          {(record.status === 'planificada' || record.status === 'en_progreso') && (
            <Button
              size="small"
              danger
              icon={<StopOutlined />}
              onClick={() => setCancelModal(record.id)}
            >
              Cancelar
            </Button>
          )}
        </Space>
      ),
    },
  ];

  return (
    <div style={{ padding: 24 }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
        <div>
          <Title level={3} style={{ margin: 0 }}>Programación de Tareas</Title>
          <Text type="secondary">Planifica y da seguimiento a las labores agrícolas</Text>
        </div>
        <Button type="primary" icon={<PlusOutlined />} onClick={() => setIsCreateModal(true)}>
          Nueva Programación
        </Button>
      </div>

      <Card size="small" style={{ marginBottom: 16 }}>
        <Space wrap>
          <Select
            placeholder="Finca"
            allowClear
            style={{ width: 160 }}
            onChange={(v) => setFilters({ ...filters, location_id: v || undefined })}
          >
            {locations.map((loc: any) => (
              <Option key={loc.id} value={loc.id}>{loc.name}</Option>
            ))}
          </Select>
          <Select
            placeholder="Estado"
            allowClear
            style={{ width: 140 }}
            onChange={(v) => setFilters({ ...filters, status: v || undefined })}
          >
            {Object.entries(STATUS_LABELS).map(([k, v]) => (
              <Option key={k} value={k}>{v}</Option>
            ))}
          </Select>
        </Space>
      </Card>

      <Table
        columns={columns}
        dataSource={schedules}
        rowKey="id"
        loading={isLoading}
        pagination={{
          total: (data as any)?.meta?.total,
          pageSize: filters.per_page,
          showTotal: (total: number) => `${total} programaciones`,
        }}
        size="middle"
        scroll={{ x: 950 }}
      />

      {/* Create Modal */}
      <Modal
        title="Nueva Programación"
        open={isCreateModal}
        onCancel={() => { setIsCreateModal(false); form.resetFields(); setAvailability(null); setSelectedTaskData(null); }}
        footer={null}
        width={800}
        destroyOnClose
      >
        <Form form={form} layout="vertical" onFinish={handleCreate}>
          <Row gutter={16}>
            <Col span={14}>
              {/* ---- Lado izquierdo: campos del formulario ---- */}
              <Row gutter={16}>
                <Col span={24}>
                  <Form.Item name="task_catalog_id" label="Tarea" rules={[{ required: true, message: 'Seleccione una tarea' }]}>
                    <Select
                      placeholder="Seleccionar tarea"
                      showSearch
                      optionFilterProp="children"
                      onChange={(id) => {
                        const t = tasks.find((t: any) => t.id === id);
                        setSelectedTaskData(t || null);
                        // Limpiar cantidad al cambiar de tarea (la unidad cambia)
                        form.setFieldValue('total_quantity', undefined);
                        setSelectedLotData(null);
                        form.setFieldValue('lot_id', undefined);
                      }}
                    >
                      {tasks.map((t: any) => (
                        <Option key={t.id} value={t.id}>
                          <Tag style={{ marginRight: 4 }}>{t.code}</Tag>
                          {t.name}
                          <Tag color={UNIT_COLORS[t.unit] || 'default'} style={{ marginLeft: 8 }}>
                            {UNIT_LABELS[t.unit] || t.unit}
                          </Tag>
                        </Option>
                      ))}
                    </Select>
                  </Form.Item>
                </Col>
              </Row>
              <Row gutter={16}>
                <Col span={12}>
                  <Form.Item name="location_id" label="Finca" rules={[{ required: true, message: 'Seleccione una finca' }]}>
                    <Select placeholder="Seleccionar finca" onChange={handleFarmChange}>
                      {locations.map((loc: any) => (
                        <Option key={loc.id} value={loc.id}>
                          {loc.name}
                          {loc.total_workers ? ` (${loc.total_workers} trab.)` : ''}
                        </Option>
                      ))}
                    </Select>
                  </Form.Item>
                </Col>
                <Col span={12}>
                  <Form.Item name="lot_id" label="Lote">
                    <Select
                      placeholder="Toda la finca"
                      allowClear
                      disabled={!selectedFarm}
                      onChange={(lotId) => {
                        const lot = lots.find((l: any) => l.id === lotId);
                        setSelectedLotData(lot || null);
                        // No auto-fill: la cantidad es opcional. Si el usuario no la ingresa,
                        // el backend usará la capacidad total del lote automáticamente.
                      }}
                    >
                      {lots.map((lot: any) => {
                        const unit = selectedTaskData?.unit;
                        const hasCap = unit === 'arbol' ? lot.total_trees :
                          unit === 'hectarea' ? lot.area_hectares :
                          unit === 'metro' ? lot.total_linear_meters : true;
                        return (
                          <Option key={lot.id} value={lot.id} disabled={unit && !hasCap}>
                            {lot.name}
                            {lot.total_trees ? ` | ${lot.total_trees} árb` : ''}
                            {lot.area_hectares ? ` | ${lot.area_hectares} ha` : ''}
                            {lot.total_linear_meters ? ` | ${lot.total_linear_meters} m` : ''}
                            {unit && !hasCap ? ' (sin datos)' : ''}
                          </Option>
                        );
                      })}
                    </Select>
                  </Form.Item>
                </Col>
              </Row>
              <Row gutter={16}>
                <Col span={12}>
                  {(() => {
                    const unit = selectedTaskData?.unit;
                    const maxCap = selectedLotData ? (
                      unit === 'arbol' ? selectedLotData.total_trees :
                      unit === 'hectarea' ? selectedLotData.area_hectares :
                      unit === 'metro' ? selectedLotData.total_linear_meters : null
                    ) : null;
                    const maxCapNum = maxCap ? Number(maxCap) : undefined;
                    const unitPlural = unit === 'arbol' ? 'Árboles' : unit === 'hectarea' ? 'Hectáreas' : unit === 'metro' ? 'Metros' : unit || '';
                    return (
                      <Form.Item
                        name="total_quantity"
                        label={`Cantidad Total${unitPlural ? ` (${unitPlural})` : ''} - opcional`}
                        rules={[
                          ...(maxCapNum ? [{
                            validator: (_: any, value: number) =>
                              value && value > maxCapNum
                                ? Promise.reject(`Máximo ${maxCapNum} ${unitPlural.toLowerCase()} en este lote`)
                                : Promise.resolve()
                          }] : []),
                        ]}
                        extra={maxCapNum
                          ? <Text type="secondary" style={{ fontSize: 11 }}>Si vacío, se usa toda la capacidad: {maxCapNum} {unitPlural.toLowerCase()}</Text>
                          : <Text type="secondary" style={{ fontSize: 11 }}>Si vacío, se usará la capacidad del lote</Text>}
                      >
                        <InputNumber
                          min={0.01}
                          max={maxCapNum}
                          precision={2}
                          style={{ width: '100%' }}
                          placeholder={maxCapNum ? `Máx: ${maxCapNum}` : 'Ej: 5000'}
                        />
                      </Form.Item>
                    );
                  })()}
                </Col>
                <Col span={12}>
                  <Form.Item
                    name="start_date"
                    label="Fecha de Inicio"
                    rules={[{ required: true, message: 'Requerido' }]}
                  >
                    <DatePicker
                      style={{ width: '100%' }}
                      format="DD/MM/YYYY"
                      disabledDate={(current) => current && current.isBefore(dayjs().startOf('day'))}
                    />
                  </Form.Item>
                </Col>
              </Row>
              <Row gutter={16}>
                <Col span={24}>
                  <Form.Item
                    name="end_date"
                    label="Fecha Fin (opcional)"
                    extra={<Text type="secondary" style={{ fontSize: 11 }}>Si la dejas vacía, el sistema la calcula con los trabajadores. Si la llenas, calculará los trabajadores necesarios.</Text>}
                  >
                    <DatePicker
                      style={{ width: '100%' }}
                      format="DD/MM/YYYY"
                      disabledDate={(current) => {
                        const start = form.getFieldValue('start_date');
                        return current && start && current.isBefore(start);
                      }}
                    />
                  </Form.Item>
                </Col>
              </Row>

              <Divider plain style={{ marginTop: 0 }}><Text type="secondary" style={{ fontSize: 12 }}>Trabajadores (al menos 1 de cualquier tipo)</Text></Divider>
              <Row gutter={8}>
                <Col span={8}>
                  <Form.Item
                    name="planned_persons"
                    label={<span style={{ fontSize: 12 }}>De la Finca</span>}
                    rules={[
                      ...(availability?.totalWorkers > 0 ? [{
                        validator: (_: any, value: number) =>
                          value && value > availability.availableToday
                            ? Promise.reject(`Máx ${availability.availableToday} disponibles`)
                            : Promise.resolve()
                      }] : []),
                    ]}
                    extra={availability?.totalWorkers > 0
                      ? <Text type="secondary" style={{ fontSize: 10 }}>Disp: {availability.availableToday}/{availability.totalWorkers}</Text>
                      : null}
                  >
                    <InputNumber
                      min={0}
                      max={availability?.totalWorkers > 0 ? availability.availableToday : undefined}
                      style={{ width: '100%' }}
                      placeholder="0"
                    />
                  </Form.Item>
                </Col>
                <Col span={8}>
                  <Form.Item name="external_farm_workers" label={<span style={{ fontSize: 12 }}>De Otra Finca</span>}>
                    <InputNumber min={0} style={{ width: '100%' }} placeholder="0" />
                  </Form.Item>
                </Col>
                <Col span={8}>
                  <Form.Item name="third_party_workers" label={<span style={{ fontSize: 12 }}>Terceros</span>}>
                    <InputNumber min={0} style={{ width: '100%' }} placeholder="0" />
                  </Form.Item>
                </Col>
              </Row>
              <Form.Item name="observations" label="Observaciones">
                <Input.TextArea rows={2} />
              </Form.Item>
            </Col>

            {/* ---- Lado derecho: cálculo en vivo + disponibilidad ---- */}
            <Col span={10}>
              <Form.Item noStyle shouldUpdate>
                {({ getFieldValue }) => {
                  const ownPersons = getFieldValue('planned_persons') || 0;
                  const externalWorkers = getFieldValue('external_farm_workers') || 0;
                  const thirdPartyWorkers = getFieldValue('third_party_workers') || 0;
                  let totalPersons = ownPersons + externalWorkers + thirdPartyWorkers;
                  const quantity = getFieldValue('total_quantity');
                  const startDate = getFieldValue('start_date');
                  const endDateField = getFieldValue('end_date');
                  const refYield = selectedTaskData?.referenceYield ? Number(selectedTaskData.referenceYield) : null;

                  // Determinar modo de operación
                  const hasWorkers = totalPersons > 0;
                  const hasEndDate = !!endDateField;
                  let mode: 'auto-end' | 'auto-persons' | 'both' | 'none' = 'none';
                  if (hasWorkers && !hasEndDate) mode = 'auto-end';
                  else if (!hasWorkers && hasEndDate) mode = 'auto-persons';
                  else if (hasWorkers && hasEndDate) mode = 'both';

                  // Cálculos
                  let workingDays = 0;
                  let autoEndDate: string | null = null;
                  let autoPersons: number | null = null;

                  if (mode === 'auto-end' && refYield && quantity && startDate) {
                    workingDays = Math.ceil(quantity / (refYield * totalPersons));
                    let endD = startDate.clone();
                    let count = 0;
                    while (count < workingDays) {
                      endD = endD.add(1, 'day');
                      if (endD.day() !== 0) count++;
                    }
                    autoEndDate = endD.format('DD/MM/YYYY');
                  } else if (mode === 'auto-persons' && refYield && quantity && startDate && endDateField) {
                    // Calcular días hábiles entre fechas
                    let d = startDate.clone();
                    while (d.isBefore(endDateField) || d.isSame(endDateField, 'day')) {
                      if (d.day() !== 0) workingDays++;
                      d = d.add(1, 'day');
                    }
                    if (workingDays > 0) {
                      autoPersons = Math.ceil(quantity / (refYield * workingDays));
                      totalPersons = autoPersons; // para mostrar en jornales
                    }
                    autoEndDate = endDateField.format('DD/MM/YYYY');
                  } else if (mode === 'both' && startDate && endDateField) {
                    // Usuario puso ambos: usa lo que puso
                    let d = startDate.clone();
                    while (d.isBefore(endDateField) || d.isSame(endDateField, 'day')) {
                      if (d.day() !== 0) workingDays++;
                      d = d.add(1, 'day');
                    }
                    autoEndDate = endDateField.format('DD/MM/YYYY');
                  }

                  const budgetedJornales = totalPersons && workingDays ? totalPersons * workingDays : null;
                  const suggestedPersons = refYield && quantity && workingDays
                    ? Math.ceil(quantity / (refYield * workingDays)) : null;
                  const suggestedWorkingDays = workingDays;
                  const suggestedEndDate = autoEndDate;
                  const dates = startDate ? [startDate] : null;

                  const unitLabel = selectedTaskData ? (UNIT_LABELS[selectedTaskData.unit] || selectedTaskData.unit) : '';
                  const unitLabelPlural = selectedTaskData ? (selectedTaskData.unit === 'arbol' ? 'árboles' : selectedTaskData.unit === 'hectarea' ? 'hectáreas' : selectedTaskData.unit === 'metro' ? 'metros' : selectedTaskData.unit === 'm2' ? 'm²' : '') : '';
                  const selectedFarmName = selectedFarm ? locations.find((l: any) => l.id === selectedFarm)?.name : null;
                  const selectedLotId = getFieldValue('lot_id');
                  const selectedLotName = selectedLotId ? lots.find((l: any) => l.id === selectedLotId)?.name : null;

                  return (
                    <Card size="small" style={{ backgroundColor: '#f0f7ff', height: '100%' }}>
                      <Text strong style={{ display: 'block', marginBottom: 8, color: '#1890ff' }}>
                        Estimación Automática
                      </Text>

                      {/* Alert visible del cálculo según modo */}
                      {mode === 'auto-end' && autoEndDate && (
                        <Alert
                          type="info"
                          showIcon
                          style={{ marginBottom: 12 }}
                          message={<Text strong>Fecha fin calculada: <span style={{ color: '#722ed1' }}>{autoEndDate}</span></Text>}
                          description={<Text type="secondary" style={{ fontSize: 11 }}>Con {totalPersons} trabajadores en {workingDays} días hábiles.</Text>}
                        />
                      )}
                      {mode === 'auto-persons' && autoPersons && (
                        <Alert
                          type="warning"
                          showIcon
                          style={{ marginBottom: 12 }}
                          message={<Text strong>Trabajadores necesarios: <span style={{ color: '#fa8c16' }}>{autoPersons}</span></Text>}
                          description={<Text type="secondary" style={{ fontSize: 11 }}>Se asignarán como trabajadores de la finca para terminar en {workingDays} días hábiles.</Text>}
                        />
                      )}
                      {mode === 'both' && (
                        <Alert
                          type="success"
                          showIcon
                          style={{ marginBottom: 12 }}
                          message={<Text strong>Programación con datos completos</Text>}
                          description={<Text type="secondary" style={{ fontSize: 11 }}>Trabajadores y fechas definidos manualmente.</Text>}
                        />
                      )}
                      {mode === 'none' && quantity && (
                        <Alert
                          type="warning"
                          showIcon
                          style={{ marginBottom: 12 }}
                          message="Faltan datos"
                          description={<Text type="secondary" style={{ fontSize: 11 }}>Ingresa trabajadores O fecha fin para calcular automáticamente.</Text>}
                        />
                      )}

                      {/* Descripción en lenguaje natural */}
                      {selectedTaskData && (
                        <Card size="small" style={{ marginBottom: 12, backgroundColor: '#e6f7ff', border: '1px solid #91d5ff' }}>
                          <Text style={{ fontSize: 13 }}>
                            Se trabajará <Text strong>{selectedTaskData.name}</Text>
                            {selectedFarmName && <> en la finca <Text strong>{selectedFarmName}</Text></>}
                            {selectedLotName && <>, lote <Text strong>{selectedLotName}</Text></>}
                            {quantity && <>. El total a trabajar será <Text strong style={{ color: '#1890ff', fontSize: 15 }}>{quantity}</Text> <Tag color={UNIT_COLORS[selectedTaskData.unit]}>{unitLabelPlural}</Tag></>}
                            {totalPersons > 0 && <> con <Text strong>{totalPersons}</Text> trabajadores ({ownPersons} finca{externalWorkers ? `, ${externalWorkers} otra finca` : ''}{thirdPartyWorkers ? `, ${thirdPartyWorkers} terceros` : ''})</>}
                            {workingDays > 0 && <> durante <Text strong>{workingDays}</Text> días hábiles</>}
                            {autoEndDate && <>. Fecha fin: <Text strong style={{ color: '#722ed1' }}>{autoEndDate}</Text></>}
                            .
                          </Text>
                        </Card>
                      )}

                      {selectedTaskData && (
                        <div style={{ marginBottom: 8 }}>
                          <Text type="secondary">Unidad de trabajo: </Text>
                          <Tag color={UNIT_COLORS[selectedTaskData.unit]}>{unitLabel}</Tag>
                        </div>
                      )}

                      {workingDays > 0 && (
                        <div style={{ marginBottom: 8 }}>
                          <Text type="secondary">Días hábiles: </Text>
                          <Text strong>{workingDays}</Text>
                        </div>
                      )}

                      {refYield && (
                        <div style={{ marginBottom: 8 }}>
                          <Text type="secondary">Rend. referencia: </Text>
                          <Text strong>{refYield} {unitLabelPlural}/jornal</Text>
                        </div>
                      )}

                      {suggestedPersons && (
                        <div style={{ marginBottom: 8 }}>
                          <Text type="secondary">Personas sugeridas: </Text>
                          <Text strong style={{ color: '#52c41a', fontSize: 18 }}>{suggestedPersons}</Text>
                        </div>
                      )}

                      {budgetedJornales && (
                        <div style={{ marginBottom: 8 }}>
                          <Text type="secondary">Jornales presupuestados: </Text>
                          <Text strong style={{ fontSize: 18 }}>{budgetedJornales}</Text>
                        </div>
                      )}

                      {suggestedWorkingDays && (
                        <div style={{ marginBottom: 8 }}>
                          <Text type="secondary">Días hábiles sugeridos: </Text>
                          <Text strong style={{ color: '#722ed1', fontSize: 16 }}>{suggestedWorkingDays}</Text>
                          {workingDays > 0 && suggestedWorkingDays !== workingDays && (
                            <Tag color={workingDays >= suggestedWorkingDays ? 'green' : 'orange'} style={{ marginLeft: 8 }}>
                              {workingDays >= suggestedWorkingDays ? 'Holgura' : 'Ajustado'}
                            </Tag>
                          )}
                        </div>
                      )}

                      {suggestedEndDate && (
                        <div style={{ marginBottom: 16 }}>
                          <Text type="secondary">Fecha fin sugerida: </Text>
                          <Text strong style={{ color: '#722ed1' }}>{suggestedEndDate}</Text>
                        </div>
                      )}

                      {/* Disponibilidad de trabajadores */}
                      {availability && (
                        <>
                          <Divider style={{ margin: '8px 0' }} />
                          <Text strong style={{ display: 'block', marginBottom: 8, color: '#fa8c16' }}>
                            Trabajadores — {availability.locationName}
                          </Text>
                          <div style={{ marginBottom: 4 }}>
                            <Text type="secondary">Total en finca: </Text>
                            <Text strong>{availability.totalWorkers || 'Sin configurar'}</Text>
                          </div>
                          <div style={{ marginBottom: 4 }}>
                            <Text type="secondary">Ocupados hoy: </Text>
                            <Text strong style={{ color: '#f5222d' }}>{availability.busyToday}</Text>
                          </div>
                          <div style={{ marginBottom: 4 }}>
                            <Text type="secondary">Disponibles hoy: </Text>
                            <Text strong style={{ color: '#52c41a' }}>{availability.availableToday}</Text>
                          </div>
                          {availability.soonAvailable?.length > 0 && (
                            <div style={{ marginTop: 8 }}>
                              <Text type="secondary" style={{ display: 'block', marginBottom: 4 }}>
                                Próximos a liberar:
                              </Text>
                              {availability.soonAvailable.map((s: any) => (
                                <Tag key={s.code} color="orange" style={{ marginBottom: 4 }}>
                                  {s.taskName}: {s.persons} pers. ({s.daysRemaining}d)
                                </Tag>
                              ))}
                            </div>
                          )}
                          {ownPersons > 0 && availability.totalWorkers > 0 && ownPersons > availability.availableToday && (
                            <Tag color="red" style={{ marginTop: 8 }}>
                              ⚠ De la finca pides {ownPersons} pero solo hay {availability.availableToday} disponibles
                            </Tag>
                          )}
                        </>
                      )}
                    </Card>
                  );
                }}
              </Form.Item>
            </Col>
          </Row>

          <div style={{ textAlign: 'right', marginTop: 16 }}>
            <Space>
              <Button onClick={() => { setIsCreateModal(false); form.resetFields(); setAvailability(null); setSelectedTaskData(null); setSelectedLotData(null); }}>Cancelar</Button>
              <Button type="primary" htmlType="submit" loading={createMutation.isPending}>Crear Programación</Button>
            </Space>
          </div>
        </Form>
      </Modal>

      {/* Detail Modal */}
      <Modal
        title={`Detalle: ${selectedSchedule?.code || ''}`}
        open={isDetailModal}
        onCancel={() => { setIsDetailModal(false); setSelectedSchedule(null); }}
        footer={null}
        width={700}
      >
        {selectedSchedule && (
          <Descriptions bordered size="small" column={2}>
            <Descriptions.Item label="Tarea">{selectedSchedule.task?.name}</Descriptions.Item>
            <Descriptions.Item label="Finca">{selectedSchedule.location?.name}</Descriptions.Item>
            <Descriptions.Item label="Lote">{selectedSchedule.lot?.name || 'Toda la finca'}</Descriptions.Item>
            <Descriptions.Item label="Cantidad">{selectedSchedule.totalQuantity}</Descriptions.Item>
            <Descriptions.Item label="Periodo">{selectedSchedule.startDate} → {selectedSchedule.endDate}</Descriptions.Item>
            <Descriptions.Item label="Días hábiles">{selectedSchedule.workingDays}</Descriptions.Item>
            <Descriptions.Item label="Personas">{selectedSchedule.plannedPersons}{selectedSchedule.suggestedPersons ? ` (sugeridas: ${selectedSchedule.suggestedPersons})` : ''}</Descriptions.Item>
            <Descriptions.Item label="Jornales presup.">{selectedSchedule.budgetedJornales}</Descriptions.Item>
            {selectedSchedule.suggestedWorkingDays && (
              <Descriptions.Item label="Días sugeridos">{selectedSchedule.suggestedWorkingDays}</Descriptions.Item>
            )}
            {selectedSchedule.suggestedEndDate && (
              <Descriptions.Item label="Fin sugerido">{selectedSchedule.suggestedEndDate}</Descriptions.Item>
            )}
            {selectedSchedule.referenceYieldUsed && (
              <Descriptions.Item label="Rend. usado">{selectedSchedule.referenceYieldUsed}/jornal</Descriptions.Item>
            )}
            <Descriptions.Item label="Avance" span={2}>
              <Progress percent={Number(selectedSchedule.accumulatedPct || 0)} />
            </Descriptions.Item>
            <Descriptions.Item label="Jornales reales">{selectedSchedule.realJornales || 0}</Descriptions.Item>
            <Descriptions.Item label="Estado">
              <Tag color={STATUS_COLORS[selectedSchedule.status]}>
                {STATUS_LABELS[selectedSchedule.status]}
              </Tag>
            </Descriptions.Item>
            {selectedSchedule.finalLevel && (
              <>
                <Descriptions.Item label="Rendimiento Final">
                  {selectedSchedule.finalPerformancePct}%
                </Descriptions.Item>
                <Descriptions.Item label="Nivel">
                  <Tag color={LEVEL_COLORS[selectedSchedule.finalLevel]}>
                    {selectedSchedule.finalLevel.toUpperCase()}
                  </Tag>
                </Descriptions.Item>
              </>
            )}
            {selectedSchedule.isAdHoc && (
              <Descriptions.Item label="Motivo ad-hoc" span={2}>
                {selectedSchedule.adHocMotive}
              </Descriptions.Item>
            )}
            {selectedSchedule.cancellationReason && (
              <Descriptions.Item label="Motivo cancelación" span={2}>
                {selectedSchedule.cancellationReason}
              </Descriptions.Item>
            )}
          </Descriptions>
        )}
      </Modal>

      {/* Finalize Confirmation Modal */}
      <Modal
        title="Finalizar Tarea"
        open={!!finalizeModal}
        onCancel={() => setFinalizeModal(null)}
        onOk={() => finalizeModal && finalizeMutation.mutate(finalizeModal.id)}
        okText="Finalizar y Calcular Rendimiento"
        confirmLoading={finalizeMutation.isPending}
        okButtonProps={{ type: 'primary' }}
      >
        {finalizeModal && (
          <>
            <Alert
              type="info"
              showIcon
              message="Al finalizar se calcularán los rendimientos finales"
              description={`Se compararán los jornales presupuestados (${finalizeModal.budgetedJornales}) vs los reales (${finalizeModal.realJornales || 0}) y se asignará el nivel SOBREPASO/ALTO/MEDIO/BAJO.`}
              style={{ marginBottom: 16 }}
            />
            <Text>
              Tarea: <Text strong>{finalizeModal.task?.name}</Text><br />
              Finca: <Text strong>{finalizeModal.location?.name}</Text>{finalizeModal.lot ? ` - ${finalizeModal.lot.name}` : ''}<br />
              Avance actual: <Text strong>{finalizeModal.accumulatedPct}%</Text>
            </Text>
          </>
        )}
      </Modal>

      {/* Finalize Result Modal */}
      <Modal
        title="✓ Tarea Finalizada"
        open={!!finalizeResult}
        onCancel={() => setFinalizeResult(null)}
        footer={<Button type="primary" onClick={() => setFinalizeResult(null)}>Entendido</Button>}
      >
        {finalizeResult && (
          <div style={{ textAlign: 'center', padding: 16 }}>
            <Tag
              color={LEVEL_COLORS[finalizeResult.finalLevel]}
              style={{ fontSize: 22, padding: '6px 18px', marginBottom: 12 }}
            >
              {finalizeResult.finalLevel?.toUpperCase()}
            </Tag>
            <Title level={2} style={{ margin: 0 }}>{finalizeResult.finalPerformancePct}%</Title>
            <Text type="secondary">Rendimiento Final</Text>
            <Row gutter={16} style={{ marginTop: 16 }}>
              <Col span={12}><Text type="secondary">Jornales Plan: </Text><Text strong>{finalizeResult.budgetedJornales}</Text></Col>
              <Col span={12}><Text type="secondary">Jornales Real: </Text><Text strong>{finalizeResult.realJornales}</Text></Col>
              <Col span={24} style={{ marginTop: 8 }}><Text type="secondary">Total registros: </Text><Text strong>{finalizeResult.totalLogs}</Text></Col>
            </Row>
          </div>
        )}
      </Modal>

      {/* Cancel Modal */}
      <Modal
        title="Cancelar Programación"
        open={!!cancelModal}
        onCancel={() => { setCancelModal(null); setCancelReason(''); }}
        onOk={() => cancelModal && cancelMutation.mutate({ id: cancelModal, reason: cancelReason })}
        okText="Confirmar Cancelación"
        okButtonProps={{ danger: true, disabled: cancelReason.length < 5, loading: cancelMutation.isPending }}
      >
        <Text>Ingrese el motivo de la cancelación (mínimo 5 caracteres):</Text>
        <Input.TextArea
          rows={3}
          value={cancelReason}
          onChange={(e) => setCancelReason(e.target.value)}
          placeholder="Ej: Lluvias prolongadas, cambio de plan..."
          style={{ marginTop: 12 }}
        />
      </Modal>
    </div>
  );
};

export default ScheduleList;
