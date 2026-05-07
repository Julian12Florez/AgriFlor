import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Card, Form, InputNumber, Button, Typography, Space, Divider, Tag, message, Spin, Row, Col
} from 'antd';
import { SettingOutlined, SaveOutlined } from '@ant-design/icons';
import { performanceSettingsApi, handleApiError } from '../../services/api';

const { Title, Text, Paragraph } = Typography;

const PerformanceSettings = () => {
  const [form] = Form.useForm();
  const queryClient = useQueryClient();

  const { data, isLoading } = useQuery({
    queryKey: ['performanceSettings'],
    queryFn: () => performanceSettingsApi.get(),
  });

  const updateMutation = useMutation({
    mutationFn: (values: any) => performanceSettingsApi.update(values),
    onSuccess: (res: any) => {
      message.success(res.message || 'Configuración actualizada');
      queryClient.invalidateQueries({ queryKey: ['performanceSettings'] });
    },
    onError: (err: any) => handleApiError(err),
  });

  const settings = (data as any)?.data;

  if (isLoading) return <Spin size="large" style={{ display: 'block', margin: '100px auto' }} />;

  return (
    <div style={{ padding: 24, maxWidth: 700 }}>
      <Title level={3} style={{ margin: 0, marginBottom: 8 }}>
        <SettingOutlined /> Configuración de Rendimiento
      </Title>
      <Paragraph type="secondary">
        Define los umbrales globales de los 4 niveles de rendimiento y el factor de detección de avance sospechoso.
        Cada tarea del catálogo puede sobrescribir estos valores si necesita umbrales diferentes.
      </Paragraph>

      <Card style={{ marginBottom: 24 }}>
        <Form
          form={form}
          layout="vertical"
          initialValues={{
            global_sobrepaso_pct: settings?.globalSobrepasoPct ? Number(settings.globalSobrepasoPct) : 130,
            global_alto_pct: settings?.globalAltoPct ? Number(settings.globalAltoPct) : 100,
            global_medio_pct: settings?.globalMedioPct ? Number(settings.globalMedioPct) : 80,
            global_k_factor: settings?.globalKFactor ? Number(settings.globalKFactor) : 3,
          }}
          onFinish={(values) => updateMutation.mutate(values)}
        >
          <Divider orientation="left">Niveles de Rendimiento Final (%)</Divider>

          <Row gutter={16}>
            <Col span={6}>
              <Form.Item
                name="global_sobrepaso_pct"
                label={<><Tag color="red">SOBREPASO</Tag> &ge;</>}
                rules={[{ required: true, message: 'Requerido' }]}
                tooltip="Rendimiento sospechosamente alto. Puede indicar un error de medición."
              >
                <InputNumber min={100} max={300} style={{ width: '100%' }} addonAfter="%" />
              </Form.Item>
            </Col>
            <Col span={6}>
              <Form.Item
                name="global_alto_pct"
                label={<><Tag color="green">ALTO</Tag> &ge;</>}
                rules={[{ required: true, message: 'Requerido' }]}
                tooltip="Buen desempeño: el equipo rindió más de lo planeado."
              >
                <InputNumber min={1} style={{ width: '100%' }} addonAfter="%" />
              </Form.Item>
            </Col>
            <Col span={6}>
              <Form.Item
                name="global_medio_pct"
                label={<><Tag color="gold">MEDIO</Tag> &ge;</>}
                rules={[{ required: true, message: 'Requerido' }]}
                tooltip="Rendimiento aceptable, dentro de lo esperado."
              >
                <InputNumber min={1} style={{ width: '100%' }} addonAfter="%" />
              </Form.Item>
            </Col>
            <Col span={6}>
              <div style={{ paddingTop: 30 }}>
                <Tag color="red">BAJO</Tag> = Todo por debajo de MEDIO
              </div>
            </Col>
          </Row>

          <Divider orientation="left">Detección de Avance Sospechoso</Divider>

          <Row gutter={16}>
            <Col span={8}>
              <Form.Item
                name="global_k_factor"
                label="Factor K"
                rules={[{ required: true, message: 'Requerido' }]}
                tooltip="Si un registro diario supera K × ritmo esperado, se marca como sospechoso. Ejemplo: K=3 y tarea de 10 días → alerta si reportan más de 30% en un día."
              >
                <InputNumber min={1} max={10} precision={1} style={{ width: '100%' }} addonAfter="×" />
              </Form.Item>
            </Col>
            <Col span={16}>
              <Card size="small" style={{ marginTop: 30, backgroundColor: '#f6f6f6' }}>
                <Text type="secondary">
                  Ejemplo: una tarea de 10 días hábiles tiene ritmo esperado de 10%/día.
                  Con K=3, el sistema alerta si alguien reporta más de 30% en un solo día.
                </Text>
              </Card>
            </Col>
          </Row>

          <div style={{ textAlign: 'right', marginTop: 24 }}>
            <Button
              type="primary"
              htmlType="submit"
              icon={<SaveOutlined />}
              loading={updateMutation.isPending}
              size="large"
            >
              Guardar Configuración
            </Button>
          </div>
        </Form>
      </Card>
    </div>
  );
};

export default PerformanceSettings;
