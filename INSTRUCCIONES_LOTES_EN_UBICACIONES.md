# Instrucciones para Agregar Gestión de Lotes en Ubicaciones

## Ubicación del archivo
`/home/julian/Documentos/AgriFlor/frontend/src/pages/master/Locations.tsx`

## Cambios necesarios

### 1. Importar componentes adicionales
Agregar en la línea 2:
```tsx
import { Button, Input, Space, Card, Tag, Popconfirm, message, Modal, Form, Row, Col, Select, Badge, Descriptions, Divider, InputNumber } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined, EnvironmentOutlined, HomeOutlined, BankOutlined, MinusCircleOutlined, PlusCircleOutlined } from '@ant-design/icons';
```

### 2. Importar API de lotes
Agregar en la línea 7:
```tsx
import { locationsApi, farmLotsApi } from '../../services/api';
```

### 3. Agregar estado para tipo de ubicación seleccionado
Después de la línea 21, agregar:
```tsx
const [selectedLocationType, setSelectedLocationType] = useState<string | undefined>();
```

### 4. Modificar el formulario para incluir lotes

Buscar donde está el formulario (alrededor de la línea 403-500) y agregar ANTES del cierre del Form:

```tsx
{/* Sección de Lotes - Solo visible si tipo es 'farm' */}
<Form.Item
  noStyle
  shouldUpdate={(prevValues, currentValues) => prevValues.type !== currentValues.type}
>
  {({ getFieldValue }) =>
    getFieldValue('type') === 'farm' ? (
      <>
        <Divider>Lotes de Finca</Divider>
        <Form.List name="lots">
          {(fields, { add, remove }) => (
            <>
              {fields.map(({ key, name, ...restField }) => (
                <Card key={key} size="small" style={{ marginBottom: 16, backgroundColor: '#f9f9f9' }}>
                  <Row gutter={16}>
                    <Col span={12}>
                      <Form.Item
                        {...restField}
                        name={[name, 'name']}
                        label="Nombre del Lote"
                        rules={[{ required: true, message: 'Nombre requerido' }]}
                      >
                        <Input placeholder="Ej: Lote A" />
                      </Form.Item>
                    </Col>
                    <Col span={6}>
                      <Form.Item
                        {...restField}
                        name={[name, 'area']}
                        label="Área"
                      >
                        <InputNumber
                          min={0}
                          precision={2}
                          style={{ width: '100%' }}
                          placeholder="0.00"
                        />
                      </Form.Item>
                    </Col>
                    <Col span={6}>
                      <Form.Item
                        {...restField}
                        name={[name, 'area_unit']}
                        label="Unidad"
                        initialValue="hectares"
                      >
                        <Select>
                          <Option value="hectares">Hectáreas</Option>
                          <Option value="m2">m²</Option>
                          <Option value="acres">Acres</Option>
                        </Select>
                      </Form.Item>
                    </Col>
                  </Row>
                  <Row gutter={16}>
                    <Col span={20}>
                      <Form.Item
                        {...restField}
                        name={[name, 'description']}
                        label="Descripción"
                      >
                        <Input.TextArea rows={2} placeholder="Descripción del lote..." />
                      </Form.Item>
                    </Col>
                    <Col span={4} style={{ display: 'flex', alignItems: 'flex-end' }}>
                      <Form.Item>
                        <Button
                          danger
                          icon={<MinusCircleOutlined />}
                          onClick={() => remove(name)}
                        >
                          Eliminar
                        </Button>
                      </Form.Item>
                    </Col>
                  </Row>
                  <Form.Item
                    {...restField}
                    name={[name, 'status']}
                    hidden
                    initialValue="active"
                  >
                    <Input />
                  </Form.Item>
                </Card>
              ))}
              <Form.Item>
                <Button
                  type="dashed"
                  onClick={() => add()}
                  block
                  icon={<PlusCircleOutlined />}
                >
                  Agregar Lote
                </Button>
              </Form.Item>
            </>
          )}
        </Form.List>
      </>
    ) : null
  }
</Form.Item>
```

### 5. Modificar función handleSave

Buscar la función `handleSave` (alrededor de línea 115-140) y modificarla:

```tsx
const handleSave = async (values: any) => {
  try {
    const locationData = {
      name: values.name,
      type: values.type,
      municipality: values.municipality,
      address: values.address,
      coordinates_lat: values.coordinates_lat,
      coordinates_lng: values.coordinates_lng,
      responsible: values.responsible,
      status: values.status || 'active',
    };

    let locationId: string;

    if (editingLocation) {
      // Actualizar ubicación
      await updateLocationMutation.mutateAsync({
        id: editingLocation.id,
        data: locationData
      });
      locationId = editingLocation.id;
    } else {
      // Crear ubicación
      const result = await createLocationMutation.mutateAsync(locationData);
      locationId = result.data.id;
    }

    // Si es finca y tiene lotes, crear/actualizar lotes
    if (values.type === 'farm' && values.lots && values.lots.length > 0) {
      for (const lot of values.lots) {
        const lotData = {
          location_id: locationId,
          name: lot.name,
          area: lot.area || null,
          area_unit: lot.area_unit || 'hectares',
          description: lot.description || null,
          status: lot.status || 'active',
        };

        if (lot.id) {
          // Actualizar lote existente
          await farmLotsApi.update(lot.id, lotData);
        } else {
          // Crear nuevo lote
          await farmLotsApi.create(lotData);
        }
      }
    }

    message.success(editingLocation ? 'Ubicación actualizada exitosamente' : 'Ubicación creada exitosamente');
  } catch (error: any) {
    message.error(`Error: ${error.message}`);
  }
};
```

### 6. Modificar handleEdit para cargar lotes

Buscar la función `handleEdit` y agregar carga de lotes:

```tsx
const handleEdit = async (record: Location) => {
  setEditingLocation(record);

  // Cargar lotes si es una finca
  let lots = [];
  if (record.type === 'farm') {
    try {
      const response = await farmLotsApi.getByLocation(record.id);
      lots = response.data || [];
    } catch (error) {
      console.error('Error cargando lotes:', error);
    }
  }

  form.setFieldsValue({
    name: record.name,
    type: record.type,
    municipality: record.municipality,
    address: record.address,
    coordinates_lat: record.coordinates_lat,
    coordinates_lng: record.coordinates_lng,
    responsible: record.responsible,
    status: record.status,
    lots: lots // Agregar los lotes al formulario
  });
  setIsModalVisible(true);
};
```

## Resultado Final

Con estos cambios:
- ✅ Al seleccionar "Finca" como tipo, aparecerá una sección de lotes
- ✅ Se podrán agregar múltiples lotes con nombre, área, unidad y descripción
- ✅ Los lotes se guardarán automáticamente al crear/editar la ubicación
- ✅ Al editar una finca, se cargarán los lotes existentes

