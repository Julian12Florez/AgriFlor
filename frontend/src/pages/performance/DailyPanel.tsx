import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Card, Button, Modal, Form, InputNumber, Input, Select, DatePicker, Slider,
  Tag, Space, Progress, Typography, Statistic, Row, Col, Alert, Divider,
  message, Spin, Empty
} from 'antd';
import {
  PlusOutlined, CheckCircleOutlined, ThunderboltOutlined,
  WarningOutlined, CalendarOutlined, TeamOutlined,
  TrophyOutlined
} from '@ant-design/icons';
import dayjs from 'dayjs';
import { taskScheduleApi, taskCatalogApi, locationsApi, farmLotsApi, handleApiError } from '../../services/api';

const { Title, Text } = Typography;
const { Option } = Select;

const LEVEL_COLORS: Record<string, string> = {
  sobrepaso: '#f5222d',
  alto: '#52c41a',
  medio: '#faad14',
  bajo: '#f5222d',
};

const DailyPanel = () => {
  const [selectedDate, setSelectedDate] = useState(dayjs());
  const [selectedFarm, setSelectedFarm] = useState<string | undefined>();
  const [logModal, setLogModal] = useState<any>(null); // The schedule to log against
  const [completedModal, setCompletedModal] = useState<any>(null);
  const [suspiciousData, setSuspiciousData] = useState<any>(null);
  const [adHocModal, setAdHocModal] = useState(false);
  const [adHocSelectedTask, setAdHocSelectedTask] = useState<any>(null);
  const [finalizeModal, setFinalizeModal] = useState<any>(null);
  const [finalizeResult, setFinalizeResult] = useState<any>(null);
  const [logForm] = Form.useForm();
  const [adHocForm] = Form.useForm();
  const [adHocLots, setAdHocLots] = useState<any[]>([]);
  const queryClient = useQueryClient();

  const { data: locationsData } = useQuery({
    queryKey: ['farmLocations'],
    queryFn: () => locationsApi.list({ type: 'farm', status: 'active', per_page: 100 }),
  });

  const { data: panelData, isLoading } = useQuery({
    queryKey: ['dailyPanel', selectedDate.format('YYYY-MM-DD'), selectedFarm],
    queryFn: () => taskScheduleApi.panel({
      date: selectedDate.format('YYYY-MM-DD'),
      location_id: selectedFarm,
      // Si la fecha es hoy, mostrar tareas futuras (más útil para registro);
      // si es pasada, mostrar solo las que cubren ese día (vista retroactiva precisa).
      include_future: selectedDate.isSame(dayjs(), 'day'),
    }),
  });

  const { data: tasksData } = useQuery({
    queryKey: ['taskCatalogActive'],
    queryFn: () => taskCatalogApi.list({ active: 'true', per_page: 100 }),
  });

  const finalizeMutation = useMutation({
    mutationFn: (scheduleId: string) => taskScheduleApi.finalize(scheduleId),
    onSuccess: (res: any) => {
      message.success(res.message || 'Tarea finalizada');
      setFinalizeResult(res?.data || null);
      setFinalizeModal(null);
      queryClient.invalidateQueries({ queryKey: ['dailyPanel'] });
      queryClient.invalidateQueries({ queryKey: ['schedules'] });
    },
    onError: (err: any) => handleApiError(err),
  });

  const logMutation = useMutation({
    mutationFn: ({ scheduleId, data }: { scheduleId: string; data: any }) =>
      taskScheduleApi.createLog(scheduleId, data),
    onSuccess: (res: any) => {
      if ((res as any).taskCompleted) {
        setCompletedModal((res as any).finalResult);
      }
      if ((res as any).suspiciousConfirmed) {
        message.warning('Registro guardado con marca de avance sospechoso');
      } else {
        message.success(res.message || 'Avance registrado');
      }
      queryClient.invalidateQueries({ queryKey: ['dailyPanel'] });
      queryClient.invalidateQueries({ queryKey: ['schedules'] });
      setLogModal(null);
      setSuspiciousData(null);
      logForm.resetFields();
    },
    onError: (err: any) => {
      const errData = (err as any)?.data;
      if (errData?.suspicious) {
        setSuspiciousData(errData.data);
        Modal.confirm({
          title: 'Avance sospechoso detectado',
          icon: <WarningOutlined style={{ color: '#faad14' }} />,
          content: errData.message || 'El avance supera el ritmo esperado. ¿Confirma?',
          okText: 'Confirmar y guardar',
          cancelText: 'Revisar datos',
          onOk: () => {
            const values = logForm.getFieldsValue();
            logMutation.mutate({
              scheduleId: logModal.id || logModal.schedule?.id,
              data: {
                log_date: selectedDate.format('YYYY-MM-DD'),
                advance_pct_today: values.advance_pct_today,
                persons_today: values.persons_today,
                observations: values.observations || null,
                suspicious_confirmed: true,
              },
            });
          },
        });
      } else {
        handleApiError(err);
      }
    },
  });

  const handleLogSubmit = (values: any) => {
    const scheduleId = logModal.id || logModal.schedule?.id;
    const logDate = values.log_date?.format ? values.log_date.format('YYYY-MM-DD') : selectedDate.format('YYYY-MM-DD');
    logMutation.mutate({
      scheduleId,
      data: {
        log_date: logDate,
        persons_today: values.persons_today,
        observations: values.observations || null,
      },
    });
  };

  const panel = (panelData as any)?.data;
  const summary = panel?.summary;
  const activeSchedules = panel?.activeSchedules || [];
  const completedToday = panel?.completedToday || [];
  const locations = (locationsData as any)?.data || [];
  const isRetroactive = !selectedDate.isSame(dayjs(), 'day');

  return (
    <div style={{ padding: 24 }}>
      <Title level={3} style={{ margin: 0, marginBottom: 8 }}>
        <CalendarOutlined /> Panel del Día — Registro de Avance
      </Title>

      {/* Selector de fecha y finca */}
      <Card size="small" style={{ marginBottom: 16 }}>
        <Space wrap>
          <DatePicker
            value={selectedDate}
            onChange={(d) => d && setSelectedDate(d)}
            format="DD/MM/YYYY"
            allowClear={false}
          />
          <Button type="link" onClick={() => setSelectedDate(dayjs())}>Hoy</Button>
          <Select
            placeholder="Todas las fincas"
            allowClear
            style={{ width: 200 }}
            value={selectedFarm}
            onChange={setSelectedFarm}
          >
            {locations.map((loc: any) => (
              <Option key={loc.id} value={loc.id}>{loc.name}</Option>
            ))}
          </Select>
          <Button
            type="dashed"
            icon={<ThunderboltOutlined />}
            style={{ color: '#fa8c16' }}
            onClick={() => setAdHocModal(true)}
          >
            Tarea No Programada
          </Button>
        </Space>
      </Card>

      {isRetroactive && (
        <Alert
          type={selectedDate.isBefore(dayjs().subtract(30, 'day')) ? 'warning' : 'info'}
          message={`Registrando avance para ${selectedDate.format('dddd DD/MM/YYYY')} (retroactivo)`}
          style={{ marginBottom: 16 }}
          showIcon
        />
      )}

      {isLoading ? (
        <Spin size="large" style={{ display: 'block', margin: '100px auto' }} />
      ) : (
        <>
          {/* Tarjetas de resumen */}
          {summary && (
            <Row gutter={16} style={{ marginBottom: 24 }}>
              <Col span={6}>
                <Card size="small"><Statistic title="Tareas del Día" value={summary.totalTasks} /></Card>
              </Col>
              <Col span={6}>
                <Card size="small"><Statistic title="Total Personas" value={summary.totalPersons} prefix={<TeamOutlined />} /></Card>
              </Col>
              <Col span={6}>
                <Card size="small"><Statistic title="Programadas" value={summary.programmedCount} /></Card>
              </Col>
              <Col span={6}>
                <Card size="small"><Statistic title="Ad-Hoc" value={summary.adHocCount} prefix={<ThunderboltOutlined />} /></Card>
              </Col>
            </Row>
          )}

          {/* Tareas activas */}
          {activeSchedules.length > 0 ? (
            <Row gutter={[16, 16]}>
              {activeSchedules.map((item: any) => {
                const s = item.schedule;
                const hasLog = item.hasLogToday;
                return (
                  <Col span={12} key={s.id}>
                    <Card
                      size="small"
                      style={{ borderLeft: `4px solid ${hasLog ? '#52c41a' : '#1890ff'}` }}
                      actions={[
                        hasLog ? (
                          <Text type="success" key="done"><CheckCircleOutlined /> Registrado hoy</Text>
                        ) : (
                          <Button key="log" type="link" onClick={() => setLogModal(item)}>
                            Registrar Avance
                          </Button>
                        ),
                        ...(s.status === 'en_progreso' ? [(
                          <Button key="finalize" type="link" style={{ color: '#52c41a' }} onClick={() => setFinalizeModal(s)}>
                            <TrophyOutlined /> Finalizar
                          </Button>
                        )] : []),
                      ]}
                    >
                      <Space direction="vertical" style={{ width: '100%' }}>
                        <Space>
                          <Tag>{s.code}</Tag>
                          <Text strong>{s.task?.name}</Text>
                          {s.isAdHoc && <Tag color="orange" icon={<ThunderboltOutlined />}>Ad-hoc</Tag>}
                        </Space>
                        <Text type="secondary">
                          {s.location?.name}{s.lot ? ` - ${s.lot.name}` : ''}
                        </Text>
                        <Progress percent={Number(s.accumulatedPct || 0)} size="small" />
                        <Text type="secondary" style={{ fontSize: 12 }}>
                          Jornales: {s.realJornales || 0} / {s.budgetedJornales} | Personas: {s.plannedPersons}
                        </Text>
                      </Space>
                    </Card>
                  </Col>
                );
              })}
            </Row>
          ) : (
            <Empty description="No hay tareas activas para esta fecha y finca" />
          )}

          {/* Tareas completadas hoy */}
          {completedToday.length > 0 && (
            <>
              <Divider orientation="left">Completadas Hoy</Divider>
              <Row gutter={[16, 16]}>
                {completedToday.map((s: any) => (
                  <Col span={12} key={s.id}>
                    <Card size="small" style={{ borderLeft: `4px solid ${LEVEL_COLORS[s.finalLevel] || '#ccc'}` }}>
                      <Space direction="vertical" style={{ width: '100%' }}>
                        <Space>
                          <Tag>{s.code}</Tag>
                          <Text strong>{s.task?.name}</Text>
                          <Tag color={LEVEL_COLORS[s.finalLevel]} icon={<TrophyOutlined />}>
                            {s.finalLevel?.toUpperCase()} ({s.finalPerformancePct}%)
                          </Tag>
                        </Space>
                        <Text type="secondary">
                          {s.location?.name} | {s.budgetedJornales} jornales plan → {s.realJornales} reales
                        </Text>
                      </Space>
                    </Card>
                  </Col>
                ))}
              </Row>
            </>
          )}
        </>
      )}

      {/* Modal de Registro de Avance */}
      <Modal
        title={`Registrar Avance — ${logModal?.schedule?.task?.name || ''}`}
        open={!!logModal}
        onCancel={() => { setLogModal(null); logForm.resetFields(); setSuspiciousData(null); }}
        footer={null}
        width={500}
        destroyOnClose
      >
        {logModal && (
          <Form
            form={logForm}
            layout="vertical"
            onFinish={handleLogSubmit}
            initialValues={{ log_date: selectedDate }}
          >
            <Card size="small" style={{ marginBottom: 16, backgroundColor: '#f6ffed' }}>
              <Text strong>{logModal.schedule?.task?.name}</Text>
              <br />
              <Text type="secondary">
                {logModal.schedule?.location?.name}{logModal.schedule?.lot ? ` - ${logModal.schedule.lot.name}` : ''}
              </Text>
              <br />
              <Text type="secondary">
                Periodo: {logModal.schedule?.startDate} → {logModal.schedule?.endDate} | Personas planeadas: <Text strong>{logModal.schedule?.plannedPersons || 0}</Text>
              </Text>
            </Card>

            <Row gutter={16}>
              <Col span={12}>
                <Form.Item
                  name="log_date"
                  label="Fecha del Avance"
                  rules={[{ required: true, message: 'Requerido' }]}
                >
                  <DatePicker
                    style={{ width: '100%' }}
                    format="DD/MM/YYYY"
                    disabledDate={(current) => current && current.isAfter(dayjs().endOf('day'))}
                  />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item
                  name="persons_today"
                  label="Personas que trabajaron"
                  rules={[{ required: true, message: 'Requerido' }]}
                  tooltip="Sin restricción de cantidad"
                >
                  <InputNumber min={1} style={{ width: '100%' }} placeholder="Ej: 5" />
                </Form.Item>
              </Col>
            </Row>

            <Form.Item name="observations" label="Observaciones (opcional)">
              <Input.TextArea rows={2} placeholder="Notas del día (clima, incidencias, etc.)" />
            </Form.Item>

            <Alert
              type="info"
              showIcon
              message="Solo se registran personas por día"
              description="No tienes que ingresar % de avance. Cuando termines la tarea, usa el botón 'Finalizar' en la lista de programaciones para calcular el rendimiento."
              style={{ marginBottom: 12 }}
            />

            <div style={{ textAlign: 'right' }}>
              <Space>
                <Button onClick={() => { setLogModal(null); logForm.resetFields(); }}>Cancelar</Button>
                <Button type="primary" htmlType="submit" loading={logMutation.isPending}>
                  Guardar Registro
                </Button>
              </Space>
            </div>
          </Form>
        )}
      </Modal>

      {/* Modal de Tarea Completada */}
      <Modal
        title={<><TrophyOutlined /> Tarea Completada</>}
        open={!!completedModal}
        onCancel={() => setCompletedModal(null)}
        footer={<Button type="primary" onClick={() => setCompletedModal(null)}>Entendido</Button>}
      >
        {completedModal && (
          <div style={{ textAlign: 'center', padding: 24 }}>
            <Tag
              color={LEVEL_COLORS[completedModal.level]}
              style={{ fontSize: 24, padding: '8px 24px', marginBottom: 16 }}
            >
              {completedModal.level?.toUpperCase()}
            </Tag>
            <Statistic
              title="Rendimiento Final"
              value={completedModal.performancePct}
              suffix="%"
              style={{ marginBottom: 16 }}
            />
            <Row gutter={16}>
              <Col span={12}>
                <Statistic title="Jornales Presupuestados" value={completedModal.budgetedJornales} />
              </Col>
              <Col span={12}>
                <Statistic title="Jornales Reales" value={completedModal.realJornales} />
              </Col>
            </Row>
          </div>
        )}
      </Modal>

      {/* Modal Confirmación Finalizar */}
      <Modal
        title={<><TrophyOutlined style={{ color: '#52c41a' }} /> Finalizar Tarea</>}
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
              description={`Plan: ${finalizeModal.budgetedJornales} jornales · Real: ${finalizeModal.realJornales || 0} jornales. El sistema asignará el nivel SOBREPASO/ALTO/MEDIO/BAJO.`}
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

      {/* Modal Resultado Finalize */}
      <Modal
        title={<><TrophyOutlined /> Tarea Finalizada</>}
        open={!!finalizeResult}
        onCancel={() => setFinalizeResult(null)}
        footer={<Button type="primary" onClick={() => setFinalizeResult(null)}>Entendido</Button>}
      >
        {finalizeResult && (
          <div style={{ textAlign: 'center', padding: 16 }}>
            <Tag
              color={LEVEL_COLORS[finalizeResult.finalLevel]?.replace('#','').slice(0,1) ? undefined : undefined}
              style={{ fontSize: 22, padding: '6px 18px', marginBottom: 12, color: '#fff', backgroundColor: LEVEL_COLORS[finalizeResult.finalLevel] || '#1890ff' }}
            >
              {finalizeResult.finalLevel?.toUpperCase()}
            </Tag>
            <Statistic title="Rendimiento Final" value={finalizeResult.finalPerformancePct} suffix="%" style={{ marginBottom: 16 }} />
            <Row gutter={16}>
              <Col span={12}><Statistic title="Jornales Plan" value={finalizeResult.budgetedJornales} /></Col>
              <Col span={12}><Statistic title="Jornales Real" value={finalizeResult.realJornales} /></Col>
            </Row>
            <Text type="secondary" style={{ display: 'block', marginTop: 12 }}>Total registros: {finalizeResult.totalLogs}</Text>
          </div>
        )}
      </Modal>

      {/* Modal Ad-Hoc con Estimación Automática */}
      <Modal
        title={<><ThunderboltOutlined style={{ color: '#fa8c16' }} /> Registrar Tarea No Programada</>}
        open={adHocModal}
        onCancel={() => { setAdHocModal(false); adHocForm.resetFields(); setAdHocSelectedTask(null); }}
        footer={null}
        width={800}
        destroyOnClose
      >
        <Alert
          message="Registre labores no programadas (emergencias, mantenimientos imprevistos). El sistema estimará automáticamente los jornales necesarios."
          type="info"
          showIcon
          style={{ marginBottom: 16 }}
        />
        <Form
          form={adHocForm}
          layout="vertical"
          onFinish={async (values) => {
            try {
              const scheduleData = {
                task_catalog_id: values.task_catalog_id,
                location_id: values.location_id,
                lot_id: values.lot_id || null,
                total_quantity: values.total_quantity || 1,
                start_date: selectedDate.format('YYYY-MM-DD'),
                end_date: selectedDate.format('YYYY-MM-DD'),
                planned_persons: values.planned_persons,
                is_ad_hoc: true,
                ad_hoc_motive: values.ad_hoc_motive,
              };
              await taskScheduleApi.create(scheduleData);
              message.success('Tarea ad-hoc creada. Puede registrar el avance ahora.');
              queryClient.invalidateQueries({ queryKey: ['dailyPanel'] });
              queryClient.invalidateQueries({ queryKey: ['schedules'] });
              setAdHocModal(false);
              adHocForm.resetFields();
              setAdHocSelectedTask(null);
            } catch (err: any) {
              handleApiError(err);
            }
          }}
        >
          <Row gutter={16}>
            <Col span={14}>
              <Row gutter={16}>
                <Col span={12}>
                  <Form.Item name="location_id" label="Finca" rules={[{ required: true }]}>
                    <Select
                      placeholder="Seleccionar"
                      onChange={async (v) => {
                        adHocForm.setFieldValue('lot_id', undefined);
                        try {
                          const res = await farmLotsApi.getByLocation(v);
                          setAdHocLots((res as any)?.data || []);
                        } catch { setAdHocLots([]); }
                      }}
                    >
                      {locations.map((loc: any) => (
                        <Option key={loc.id} value={loc.id}>{loc.name}</Option>
                      ))}
                    </Select>
                  </Form.Item>
                </Col>
                <Col span={12}>
                  <Form.Item name="lot_id" label="Lote">
                    <Select placeholder="Toda la finca" allowClear>
                      {adHocLots.map((l: any) => (
                        <Option key={l.id} value={l.id}>
                          {l.name}
                          {l.total_trees ? ` | ${l.total_trees} árb` : ''}
                          {l.area_hectares ? ` | ${l.area_hectares} ha` : ''}
                          {l.total_linear_meters ? ` | ${l.total_linear_meters} m` : ''}
                        </Option>
                      ))}
                    </Select>
                  </Form.Item>
                </Col>
              </Row>
              <Form.Item name="task_catalog_id" label="Tarea" rules={[{ required: true }]}>
                <Select
                  placeholder="Seleccionar"
                  showSearch
                  optionFilterProp="children"
                  onChange={(id) => {
                    const allTasks = (tasksData as any)?.data || [];
                    setAdHocSelectedTask(allTasks.find((t: any) => t.id === id) || null);
                  }}
                >
                  {((tasksData as any)?.data || []).map((t: any) => (
                    <Option key={t.id} value={t.id}>
                      <Tag style={{ marginRight: 4 }}>{t.code}</Tag>{t.name}
                      <Tag color={t.unit === 'arbol' ? 'green' : t.unit === 'hectarea' ? 'blue' : 'orange'} style={{ marginLeft: 8 }}>
                        {t.unit === 'arbol' ? 'Árbol' : t.unit === 'hectarea' ? 'Hectárea' : t.unit === 'metro' ? 'Metro' : t.unit}
                      </Tag>
                    </Option>
                  ))}
                </Select>
              </Form.Item>
              <Row gutter={16}>
                <Col span={12}>
                  <Form.Item name="planned_persons" label="Personas" rules={[{ required: true }]}>
                    <InputNumber min={1} style={{ width: '100%' }} />
                  </Form.Item>
                </Col>
                <Col span={12}>
                  <Form.Item name="total_quantity" label={`Cantidad${adHocSelectedTask ? ` (${adHocSelectedTask.unit === 'arbol' ? 'Árboles' : adHocSelectedTask.unit === 'hectarea' ? 'Hectáreas' : adHocSelectedTask.unit === 'metro' ? 'Metros' : adHocSelectedTask.unit})` : ''}`}>
                    <InputNumber min={1} style={{ width: '100%' }} placeholder="100" />
                  </Form.Item>
                </Col>
              </Row>
              <Form.Item
                name="ad_hoc_motive"
                label="Motivo (obligatorio)"
                rules={[{ required: true, message: 'El motivo es obligatorio para tareas no programadas' }]}
              >
                <Input.TextArea rows={2} placeholder="Ej: Brote de Phytophthora detectado en recorrido matutino" />
              </Form.Item>
            </Col>

            {/* Card de Estimación Automática */}
            <Col span={10}>
              <Form.Item noStyle shouldUpdate>
                {({ getFieldValue }) => {
                  const taskId = getFieldValue('task_catalog_id');
                  const task = adHocSelectedTask;
                  const qty = getFieldValue('total_quantity');
                  const persons = getFieldValue('planned_persons');
                  const locationId = getFieldValue('location_id');
                  const lotId = getFieldValue('lot_id');
                  const farmName = locationId ? locations.find((l: any) => l.id === locationId)?.name : null;
                  const lotName = lotId ? adHocLots.find((l: any) => l.id === lotId)?.name : null;
                  const refYield = task?.referenceYield ? Number(task.referenceYield) : null;
                  const unitPlural = task ? (task.unit === 'arbol' ? 'árboles' : task.unit === 'hectarea' ? 'hectáreas' : task.unit === 'metro' ? 'metros' : task.unit) : '';
                  const budgeted = persons ? persons * 1 : null; // 1 día para ad-hoc

                  return (
                    <Card size="small" style={{ backgroundColor: '#fff7e6', height: '100%', border: '1px solid #ffd591' }}>
                      <Text strong style={{ display: 'block', marginBottom: 8, color: '#fa8c16' }}>
                        <ThunderboltOutlined /> Estimación Automática
                      </Text>

                      {task && (
                        <Card size="small" style={{ marginBottom: 12, backgroundColor: '#fffbe6', border: '1px solid #ffe58f' }}>
                          <Text style={{ fontSize: 13 }}>
                            Se trabajará <Text strong>{task.name}</Text>
                            {farmName && <> en <Text strong>{farmName}</Text></>}
                            {lotName && <>, lote <Text strong>{lotName}</Text></>}
                            {qty && <> — total: <Text strong style={{ color: '#fa8c16', fontSize: 15 }}>{qty}</Text> <Tag color={task.unit === 'arbol' ? 'green' : task.unit === 'hectarea' ? 'blue' : 'orange'}>{unitPlural}</Tag></>}
                            . Registro ad-hoc para {selectedDate.format('DD/MM/YYYY')}.
                          </Text>
                        </Card>
                      )}

                      {task && (
                        <div style={{ marginBottom: 8 }}>
                          <Text type="secondary">Unidad de trabajo: </Text>
                          <Tag color={task.unit === 'arbol' ? 'green' : task.unit === 'hectarea' ? 'blue' : 'orange'}>
                            {task.unit === 'arbol' ? 'Árbol' : task.unit === 'hectarea' ? 'Hectárea' : task.unit === 'metro' ? 'Metro' : task.unit}
                          </Tag>
                        </div>
                      )}

                      {refYield && (
                        <div style={{ marginBottom: 8 }}>
                          <Text type="secondary">Rend. referencia: </Text>
                          <Text strong>{refYield} {unitPlural}/jornal</Text>
                        </div>
                      )}

                      {refYield && qty && (
                        <div style={{ marginBottom: 8 }}>
                          <Text type="secondary">Jornales estimados: </Text>
                          <Text strong style={{ fontSize: 16, color: '#fa8c16' }}>{Math.ceil(qty / refYield)}</Text>
                        </div>
                      )}

                      {persons && (
                        <div style={{ marginBottom: 8 }}>
                          <Text type="secondary">Jornales hoy: </Text>
                          <Text strong>{persons} (1 día)</Text>
                        </div>
                      )}

                      {!task && (
                        <Text type="secondary" style={{ display: 'block', textAlign: 'center', padding: '20px 0' }}>
                          Seleccione una tarea para ver la estimación
                        </Text>
                      )}
                    </Card>
                  );
                }}
              </Form.Item>
            </Col>
          </Row>

          <div style={{ textAlign: 'right', marginTop: 16 }}>
            <Space>
              <Button onClick={() => { setAdHocModal(false); adHocForm.resetFields(); setAdHocSelectedTask(null); }}>Cancelar</Button>
              <Button type="primary" htmlType="submit">Crear y continuar</Button>
            </Space>
          </div>
        </Form>
      </Modal>
    </div>
  );
};

export default DailyPanel;
