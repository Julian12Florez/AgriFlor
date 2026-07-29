import React, { useEffect, useMemo, useState } from 'react';
import {
  Modal,
  Drawer,
  Form,
  Row,
  Col,
  Select,
  Radio,
  InputNumber,
  DatePicker,
  Input,
  AutoComplete,
  Button,
  Space,
  Alert,
  message,
} from 'antd';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import dayjs from 'dayjs';
import {
  adjustmentsApi,
  adjustmentReasonsApi,
  productsApi,
  locationsApi,
  inventoryApi,
  handleApiError,
} from '../../services/api';
import usePermissions from '../../hooks/usePermissions';
import { formatQuantity } from '../../utils/formatters';

// Roles restringidos a su(s) ubicación(es): supervisor (encargado de finca) y farm
// (operario de finca). Mismo criterio que Outputs.tsx — fail-safe: un rol no
// contemplado ve/elige todas las ubicaciones.
const LOCATION_SCOPED_ROLES = ['supervisor', 'farm'];

type AdjustmentType = 'entry' | 'exit' | 'transfer';
type QuantityMode = 'delta' | 'absolute';

const { Option } = Select;
const { TextArea } = Input;

interface AdjustmentRequestModalProps {
  open: boolean;
  onClose: () => void;
}

const AdjustmentRequestModal: React.FC<AdjustmentRequestModalProps> = ({ open, onClose }) => {
  const [form] = Form.useForm();
  const queryClient = useQueryClient();
  const { user, getRoleName, isAdmin } = usePermissions();

  const [isMobile, setIsMobile] = useState(false);
  const [selectedType, setSelectedType] = useState<AdjustmentType | undefined>();
  const [quantityMode, setQuantityMode] = useState<QuantityMode>('delta');
  const [selectedProductId, setSelectedProductId] = useState<string | undefined>();
  const [selectedOriginLocationId, setSelectedOriginLocationId] = useState<string | undefined>();
  const [selectedDestinationLocationId, setSelectedDestinationLocationId] = useState<string | undefined>();

  useEffect(() => {
    const checkScreenSize = () => setIsMobile(window.innerWidth < 768);
    checkScreenSize();
    window.addEventListener('resize', checkScreenSize);
    return () => window.removeEventListener('resize', checkScreenSize);
  }, []);

  const canViewAllLocations = isAdmin() || !LOCATION_SCOPED_ROLES.includes(getRoleName());

  const { data: productsData } = useQuery({
    queryKey: ['products', 'all-for-adjustments'],
    queryFn: () => productsApi.list({ per_page: 9999, status: 'active' }),
    enabled: open,
  });

  const { data: locationsData } = useQuery({
    queryKey: ['locations'],
    queryFn: () => locationsApi.list({ per_page: 999 }),
    enabled: open,
  });

  const { data: reasonsData } = useQuery({
    queryKey: ['adjustment-reasons'],
    queryFn: () => adjustmentReasonsApi.list(),
    enabled: open,
  });

  const availableProducts = productsData?.data || [];
  const availableLocations = locationsData?.data || [];
  const availableReasons = reasonsData?.data || [];

  const selectedProduct = useMemo(
    () => availableProducts.find((p: any) => p.id === selectedProductId),
    [availableProducts, selectedProductId]
  );

  // Ubicación cuyo stock se ve afectado (destino en entrada, origen en salida/traslado) —
  // mismo criterio que AdjustmentController::stockLocationId. Se usa para sugerir lotes
  // existentes en el selector de número de lote.
  const stockLocationId = selectedType === 'entry' ? selectedDestinationLocationId : selectedOriginLocationId;

  const { data: batchesData } = useQuery({
    queryKey: ['inventory', 'batches-for-adjustment', selectedProductId, stockLocationId],
    queryFn: () => inventoryApi.list({ product_id: selectedProductId, location_id: stockLocationId, per_page: 999 }),
    enabled: open && !!selectedProductId && !!stockLocationId,
  });

  const availableBatches = batchesData?.data || [];

  // Motivos válidos para el tipo elegido: direction='any' o direction===type — el mismo
  // criterio que StoreAdjustmentRequest::validateReasonDirection exige en el backend.
  // No se filtra por API (?direction=) porque el backend solo hace match exacto y
  // ocultaría los motivos 'any', que también son válidos para cualquier tipo.
  const filteredReasons = useMemo(() => {
    if (!selectedType) return [];
    return availableReasons.filter((r: any) => r.direction === 'any' || r.direction === selectedType);
  }, [availableReasons, selectedType]);

  const unitOptions = useMemo(() => {
    if (!selectedProduct) return [];
    const baseUnit = selectedProduct.base_unit || 'unidades';
    const options = [{ value: baseUnit, label: `${baseUnit} (unidad base)` }];
    (selectedProduct.packaging_units || []).forEach((pu: any) => {
      options.push({ value: pu.name, label: `${pu.name} (equivale a ${pu.base_quantity} ${pu.base_unit})` });
    });
    return options;
  }, [selectedProduct]);

  // En traslado NO se restringe a "administradas": basta con que UNA de las dos
  // ubicaciones (origen o destino) sea del usuario — igual que
  // AdjustmentController::deniedTransferLocationMessage. Restringir ambas bloquearía
  // casos legítimos como "devolver de mi finca a la bodega central". Por eso origen y
  // destino comparten exactamente el mismo criterio de filtrado.
  const managedOnly = selectedType !== 'transfer';
  const selectableLocations = availableLocations.filter((loc: any) => {
    if (loc.status !== 'active') return false;
    if (managedOnly && !canViewAllLocations && loc.responsible_user_id !== user?.id) return false;
    return true;
  });

  const resetLocalState = () => {
    setSelectedType(undefined);
    setQuantityMode('delta');
    setSelectedProductId(undefined);
    setSelectedOriginLocationId(undefined);
    setSelectedDestinationLocationId(undefined);
  };

  const handleClose = () => {
    form.resetFields();
    resetLocalState();
    onClose();
  };

  const createMutation = useMutation({
    mutationFn: (data: any) => adjustmentsApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['adjustments'] });
      queryClient.invalidateQueries({ queryKey: ['inventory'] });
      queryClient.invalidateQueries({ queryKey: ['movements'] });
      message.success('Solicitud de ajuste creada. Quedará pendiente de aprobación.');
      handleClose();
    },
    onError: (error: any) => {
      handleApiError(error, 'Error al crear la solicitud de ajuste', form);
    },
  });

  const handleTypeChange = (type: AdjustmentType) => {
    setSelectedType(type);
    setSelectedOriginLocationId(undefined);
    setSelectedDestinationLocationId(undefined);
    form.setFieldsValue({
      reasonId: undefined,
      originLocationId: undefined,
      destinationLocationId: undefined,
      batchNumber: undefined,
    });
    if (type === 'transfer' && quantityMode === 'absolute') {
      setQuantityMode('delta');
      form.setFieldsValue({ quantityMode: 'delta' });
    }
  };

  const handleProductChange = (productId: string) => {
    setSelectedProductId(productId);
    const product = availableProducts.find((p: any) => p.id === productId);
    form.setFieldsValue({ unit: product?.base_unit, batchNumber: undefined });
  };

  const handleQuantityModeChange = (mode: QuantityMode) => {
    setQuantityMode(mode);
  };

  const handleSave = (values: any) => {
    const data: Record<string, any> = {
      type: values.type,
      reason_id: values.reasonId,
      notes: values.notes || undefined,
      product_id: values.productId,
      brand_id: selectedProduct?.brand_id,
      unit: values.unit,
      quantity_mode: values.quantityMode,
      quantity: values.quantity,
      batch_number: values.batchNumber || undefined,
      movement_date: values.movementDate.format('YYYY-MM-DD'),
    };

    if (values.type === 'entry' || values.type === 'transfer') {
      data.destination_location_id = values.destinationLocationId;
    }
    if (values.type === 'exit' || values.type === 'transfer') {
      data.origin_location_id = values.originLocationId;
    }
    if (values.type === 'entry') {
      data.unit_price = values.unitPrice;
    }

    createMutation.mutate(data);
  };

  const quantityLabel = quantityMode === 'absolute' ? 'Cantidad final del lote (saldo)' : 'Cantidad a ajustar';
  const batchTooltip =
    quantityMode === 'absolute'
      ? 'Requerido: el lote cuyo saldo final se está fijando.'
      : selectedType === 'entry'
        ? 'Opcional: déjelo vacío para crear un lote nuevo (AJU-...).'
        : 'Opcional: déjelo vacío para consumir por FIFO automáticamente.';

  const renderFormContent = () => (
    <Form form={form} layout="vertical" onFinish={handleSave} initialValues={{ quantityMode: 'delta', movementDate: dayjs() }}>
      <Alert
        type="info"
        showIcon
        message="Esta solicitud queda pendiente. El inventario solo se actualiza cuando un administrador la aprueba."
        style={{ marginBottom: 16 }}
      />

      <Row gutter={isMobile ? [0, 16] : 16}>
        <Col xs={24} sm={12}>
          <Form.Item name="type" label="Tipo de Ajuste" rules={[{ required: true, message: 'El tipo de ajuste es requerido' }]}>
            <Select placeholder="Seleccione el tipo" onChange={handleTypeChange}>
              <Option value="entry">⬆️ Entrada (sube stock)</Option>
              <Option value="exit">⬇️ Salida (baja stock)</Option>
              <Option value="transfer">🔁 Traslado (entre ubicaciones)</Option>
            </Select>
          </Form.Item>
        </Col>
        <Col xs={24} sm={12}>
          <Form.Item name="reasonId" label="Motivo" rules={[{ required: true, message: 'El motivo es requerido' }]}>
            <Select
              placeholder={selectedType ? 'Seleccione el motivo' : 'Seleccione primero el tipo'}
              disabled={!selectedType}
              showSearch
              optionFilterProp="children"
            >
              {filteredReasons.map((r: any) => (
                <Option key={r.id} value={r.id}>{r.name}</Option>
              ))}
            </Select>
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={isMobile ? [0, 16] : 16}>
        <Col xs={24} sm={12}>
          <Form.Item name="productId" label="Producto" rules={[{ required: true, message: 'Seleccione un producto' }]}>
            <Select
              placeholder="Seleccione el producto"
              showSearch
              optionFilterProp="children"
              filterOption={(input, option) =>
                (option?.children?.toString() ?? '').toLowerCase().includes(input.toLowerCase())
              }
              onChange={handleProductChange}
            >
              {availableProducts.map((p: any) => (
                <Option key={p.id} value={p.id}>
                  {p.product_code ? `${p.product_code} - ` : ''}{p.name}
                </Option>
              ))}
            </Select>
          </Form.Item>
        </Col>
        <Col xs={24} sm={12}>
          <Form.Item name="unit" label="Unidad" rules={[{ required: true, message: 'La unidad es requerida' }]}>
            <Select placeholder={selectedProductId ? 'Seleccione la unidad' : 'Seleccione primero el producto'} disabled={!selectedProductId}>
              {unitOptions.map((opt) => (
                <Option key={opt.value} value={opt.value}>{opt.label}</Option>
              ))}
            </Select>
          </Form.Item>
        </Col>
      </Row>

      {(selectedType === 'exit' || selectedType === 'transfer') && (
        <Row gutter={isMobile ? [0, 16] : 16}>
          <Col xs={24} sm={selectedType === 'transfer' ? 12 : 24}>
            <Form.Item name="originLocationId" label="Ubicación Origen" rules={[{ required: true, message: 'La ubicación de origen es requerida' }]}>
              <Select
                placeholder="Seleccione ubicación origen"
                onChange={(v: string) => {
                  setSelectedOriginLocationId(v);
                  form.setFieldsValue({ batchNumber: undefined });
                }}
              >
                {selectableLocations.map((loc: any) => (
                  <Option key={loc.id} value={loc.id} disabled={loc.id === selectedDestinationLocationId}>
                    {loc.type === 'farm' ? '🏡' : '🏢'} {loc.name} ({loc.municipality})
                  </Option>
                ))}
              </Select>
            </Form.Item>
          </Col>
          {selectedType === 'transfer' && (
            <Col xs={24} sm={12}>
              <Form.Item name="destinationLocationId" label="Ubicación Destino" rules={[{ required: true, message: 'La ubicación de destino es requerida' }]}>
                <Select
                  placeholder="Seleccione ubicación destino"
                  onChange={(v: string) => {
                    setSelectedDestinationLocationId(v);
                  }}
                >
                  {selectableLocations.map((loc: any) => (
                    <Option key={loc.id} value={loc.id} disabled={loc.id === selectedOriginLocationId}>
                      {loc.type === 'farm' ? '🏡' : '🏢'} {loc.name} ({loc.municipality})
                    </Option>
                  ))}
                </Select>
              </Form.Item>
            </Col>
          )}
        </Row>
      )}

      {selectedType === 'entry' && (
        <Row gutter={isMobile ? [0, 16] : 16}>
          <Col xs={24}>
            <Form.Item name="destinationLocationId" label="Ubicación Destino" rules={[{ required: true, message: 'La ubicación de destino es requerida' }]}>
              <Select
                placeholder="Seleccione ubicación destino"
                onChange={(v: string) => {
                  setSelectedDestinationLocationId(v);
                  form.setFieldsValue({ batchNumber: undefined });
                }}
              >
                {selectableLocations.map((loc: any) => (
                  <Option key={loc.id} value={loc.id}>
                    {loc.type === 'farm' ? '🏡' : '🏢'} {loc.name} ({loc.municipality})
                  </Option>
                ))}
              </Select>
            </Form.Item>
          </Col>
        </Row>
      )}

      <Row gutter={isMobile ? [0, 16] : 16}>
        <Col xs={24} sm={12}>
          <Form.Item name="quantityMode" label="Modo de Cantidad">
            <Radio.Group onChange={(e) => handleQuantityModeChange(e.target.value)}>
              <Radio.Button value="delta">Delta (+/-)</Radio.Button>
              <Radio.Button value="absolute" disabled={selectedType === 'transfer'}>Fijar saldo del lote</Radio.Button>
            </Radio.Group>
          </Form.Item>
        </Col>
        <Col xs={24} sm={12}>
          <Form.Item
            name="quantity"
            label={quantityLabel}
            rules={[
              { required: true, message: 'La cantidad es requerida' },
              {
                validator: (_rule, value) => {
                  if (value === undefined || value === null) return Promise.resolve();
                  if (quantityMode === 'absolute') {
                    return value < 0 ? Promise.reject(new Error('La cantidad no puede ser negativa')) : Promise.resolve();
                  }
                  return value < 0.01 ? Promise.reject(new Error('La cantidad debe ser mayor a 0')) : Promise.resolve();
                },
              },
            ]}
          >
            <InputNumber min={0} precision={2} style={{ width: '100%' }} placeholder="0.00" />
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={isMobile ? [0, 16] : 16}>
        <Col xs={24} sm={12}>
          <Form.Item
            name="batchNumber"
            label="Lote"
            tooltip={batchTooltip}
            rules={quantityMode === 'absolute' ? [{ required: true, message: 'El número de lote es requerido en modo absoluto' }] : []}
          >
            <AutoComplete
              options={availableBatches.map((b: any) => ({
                value: b.batch_number,
                label: `${b.batch_number} — ${formatQuantity(b.quantity)} ${b.unit}`,
              }))}
              placeholder={quantityMode === 'absolute' ? 'Seleccione el lote' : 'Vacío = automático'}
              disabled={!selectedProductId || !stockLocationId}
              allowClear
            />
          </Form.Item>
        </Col>
        <Col xs={24} sm={12}>
          <Form.Item name="movementDate" label="Fecha del Movimiento" rules={[{ required: true, message: 'La fecha del movimiento es requerida' }]}>
            <DatePicker style={{ width: '100%' }} format="DD/MM/YYYY" />
          </Form.Item>
        </Col>
      </Row>

      {selectedType === 'entry' && (
        <Row gutter={isMobile ? [0, 16] : 16}>
          <Col xs={24}>
            <Form.Item
              name="unitPrice"
              label={`Costo por ${selectedProduct?.base_unit || 'unidad base'}`}
              tooltip="El costo se aplica por unidad BASE del producto, sin importar la unidad de captura elegida arriba."
              rules={[{ required: true, message: 'El costo es requerido para una entrada' }]}
            >
              <InputNumber min={0} precision={2} style={{ width: '100%' }} placeholder="0.00" addonBefore="$" />
            </Form.Item>
          </Col>
        </Row>
      )}

      <Row gutter={isMobile ? [0, 16] : 16}>
        <Col xs={24}>
          <Form.Item name="notes" label="Notas (opcional)">
            <TextArea rows={isMobile ? 2 : 3} placeholder="Detalle adicional sobre el ajuste..." />
          </Form.Item>
        </Col>
      </Row>

      <div style={{ textAlign: 'right', marginTop: 24 }}>
        <Space direction={isMobile ? 'vertical' : 'horizontal'} style={{ width: isMobile ? '100%' : 'auto' }}>
          <Button onClick={handleClose} style={{ width: isMobile ? '100%' : 'auto' }}>
            Cancelar
          </Button>
          <Button
            type="primary"
            htmlType="submit"
            style={{ width: isMobile ? '100%' : 'auto' }}
            loading={createMutation.isPending}
            disabled={createMutation.isPending}
          >
            Crear Solicitud
          </Button>
        </Space>
      </div>
    </Form>
  );

  return isMobile ? (
    <Drawer
      title="Nueva Solicitud de Ajuste"
      placement="bottom"
      height="90%"
      open={open}
      onClose={handleClose}
      styles={{ body: { padding: 16 } }}
    >
      {renderFormContent()}
    </Drawer>
  ) : (
    <Modal title="Nueva Solicitud de Ajuste" open={open} onCancel={handleClose} footer={null} width={900}>
      {renderFormContent()}
    </Modal>
  );
};

export default AdjustmentRequestModal;
