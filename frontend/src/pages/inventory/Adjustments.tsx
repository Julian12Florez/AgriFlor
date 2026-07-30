import React, { useState } from 'react';
import { Button, Input, Space, Card, Tag, Popconfirm, message, Modal, Form, Row, Col, Select, Descriptions, Badge, Alert } from 'antd';
import {
  PlusOutlined,
  ArrowUpOutlined,
  ArrowDownOutlined,
  SwapOutlined,
  CheckCircleOutlined,
  CloseCircleOutlined,
  ClockCircleOutlined,
  StopOutlined,
} from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import ResponsiveTable from '../../components/ResponsiveTable';
import dayjs from 'dayjs';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { adjustmentsApi, handleApiError } from '../../services/api';
import usePermissions from '../../hooks/usePermissions';
import { formatQuantity, formatCurrency } from '../../utils/formatters';
import type { Adjustment } from '../../types/index';
import AdjustmentRequestModal from './AdjustmentRequestModal';
import { invalidateAdjustmentRelated } from './adjustmentQueries';

const { Search } = Input;
const { Option } = Select;
const { TextArea } = Input;

const TYPE_LABELS: Record<string, string> = {
  entry: 'Entrada',
  exit: 'Salida',
  transfer: 'Traslado',
};

const TYPE_COLORS: Record<string, string> = {
  entry: 'green',
  exit: 'red',
  transfer: 'blue',
};

const TYPE_ICONS: Record<string, React.ReactNode> = {
  entry: <ArrowUpOutlined />,
  exit: <ArrowDownOutlined />,
  transfer: <SwapOutlined />,
};

const STATUS_LABELS: Record<string, string> = {
  pending: 'Pendiente',
  approved: 'Aprobado',
  rejected: 'Rechazado',
  cancelled: 'Cancelado',
};

const STATUS_COLORS: Record<string, string> = {
  pending: 'gold',
  approved: 'green',
  rejected: 'red',
  cancelled: 'default',
};

const STATUS_ICONS: Record<string, React.ReactNode> = {
  pending: <ClockCircleOutlined />,
  approved: <CheckCircleOutlined />,
  rejected: <CloseCircleOutlined />,
  cancelled: <StopOutlined />,
};

const getLocationsText = (record: Adjustment): string => {
  if (record.type === 'entry') return `→ ${record.destination_location_name || 'Destino'}`;
  if (record.type === 'exit') return `${record.origin_location_name || 'Origen'} →`;
  return `${record.origin_location_name || 'Origen'} → ${record.destination_location_name || 'Destino'}`;
};

interface QuantityDisplay {
  /** Número principal, coloreado según el tipo (o neutro si aún no se conoce). */
  primary: string;
  /** Aclaración opcional, en letra más pequeña. */
  secondary?: string;
  color: string;
}

const NEUTRAL_QUANTITY_COLOR = '#666';
const QUANTITY_COLOR_BY_TYPE: Record<string, string> = {
  entry: '#389e0d',
  exit: '#cf1322',
  transfer: '#1890ff',
};

/**
 * Cómo presentar la columna "Cantidad" de una solicitud de ajuste.
 *
 * En modo `delta`, `record.quantity` YA es lo que se mueve (con signo según el
 * tipo): se muestra tal cual, como siempre.
 *
 * En modo `absolute`, `record.quantity` es el SALDO OBJETIVO del lote, NO lo
 * que se mueve — pintarlo con signo (como hacía getSignedQuantityText, la
 * función que esta reemplaza) hace que la pantalla donde el admin aprueba
 * muestre un número hasta 60x mayor que el movimiento real: medido con
 * AJU-20260730-0018 (exit, absolute, saldo objetivo 15.000 kg sobre un lote de
 * 15.250), donde el movimiento real fue de 250 kg pero la celda mostró
 * "−15.000,00 kg".
 *
 * El delta real solo se conoce al aprobar: `quantity_base` lo calcula
 * approve() contra el stock EN ESE MOMENTO (ver
 * AdjustmentController::resolveAbsoluteDeltaBase) y queda `null` mientras la
 * solicitud siga pending/rejected/cancelled — no se inventa un número antes de
 * que exista.
 */
const getQuantityDisplay = (record: Adjustment): QuantityDisplay => {
  const color = QUANTITY_COLOR_BY_TYPE[record.type] || NEUTRAL_QUANTITY_COLOR;

  if (record.quantity_mode === 'delta') {
    const sign = record.type === 'entry' ? '+' : record.type === 'exit' ? '-' : '';
    return { primary: `${sign}${formatQuantity(record.quantity)} ${record.unit}`, color };
  }

  const target = `Fijar saldo en ${formatQuantity(record.quantity)} ${record.unit}`;

  if (record.quantity_base === null || record.quantity_base === undefined) {
    return {
      primary: target,
      secondary: 'El movimiento se calculará al aprobar, según el stock en ese momento.',
      color: NEUTRAL_QUANTITY_COLOR,
    };
  }

  // Ya aprobado: quantity_base es el delta REAL que se aplicó (siempre
  // positivo; transfer no admite modo absoluto, así que type es entry|exit).
  const appliedSign = record.type === 'exit' ? '-' : '+';
  const baseUnit = record.product_base_unit || record.unit;

  return {
    primary: `${appliedSign}${formatQuantity(record.quantity_base)} ${baseUnit}`,
    secondary: target,
    color,
  };
};

const Adjustments: React.FC = () => {
  const queryClient = useQueryClient();
  const { user, getRoleName } = usePermissions();
  const isAdminRole = getRoleName() === 'admin';

  const [isRequestModalOpen, setIsRequestModalOpen] = useState(false);
  const [searchText, setSearchText] = useState('');
  const [statusFilter, setStatusFilter] = useState<string | undefined>();
  const [rejectingAdjustment, setRejectingAdjustment] = useState<Adjustment | null>(null);
  const [rejectForm] = Form.useForm();

  const { data: adjustmentsData, isLoading } = useQuery({
    queryKey: ['adjustments', statusFilter],
    queryFn: () => adjustmentsApi.list({ status: statusFilter, per_page: 50 }),
  });

  // Contador de pendientes independiente de la página/filtro actual: refleja el
  // total real (meta.total), no solo lo que trajo la página cargada.
  const { data: pendingCountData } = useQuery({
    queryKey: ['adjustments', 'pending-count'],
    queryFn: () => adjustmentsApi.list({ status: 'pending', per_page: 1 }),
  });

  const adjustments = adjustmentsData?.data || [];
  const pendingCount = pendingCountData?.meta?.total ?? 0;

  const approveMutation = useMutation({
    mutationFn: (id: string) => adjustmentsApi.approve(id),
    onSuccess: (response: any) => {
      invalidateAdjustmentRelated(queryClient);
      message.success(response?.message || 'Ajuste aprobado y aplicado al inventario.');
    },
    onError: (error: any) => {
      handleApiError(error, 'Error al aprobar el ajuste');
    },
  });

  const rejectMutation = useMutation({
    mutationFn: ({ id, reason }: { id: string; reason: string }) => adjustmentsApi.reject(id, reason),
    onSuccess: (response: any) => {
      invalidateAdjustmentRelated(queryClient);
      message.success(response?.message || 'Solicitud de ajuste rechazada.');
      setRejectingAdjustment(null);
      rejectForm.resetFields();
    },
    onError: (error: any) => {
      handleApiError(error, 'Error al rechazar el ajuste', rejectForm);
    },
  });

  const cancelMutation = useMutation({
    mutationFn: (id: string) => adjustmentsApi.cancel(id),
    onSuccess: (response: any) => {
      invalidateAdjustmentRelated(queryClient);
      message.success(response?.message || 'Solicitud de ajuste cancelada.');
    },
    onError: (error: any) => {
      handleApiError(error, 'Error al cancelar la solicitud');
    },
  });

  const handleReject = () => {
    rejectForm.validateFields().then((values) => {
      if (!rejectingAdjustment) return;
      rejectMutation.mutate({ id: rejectingAdjustment.id, reason: values.rejectionReason });
    });
  };

  // La búsqueda por texto se resuelve en cliente sobre la página cargada (el backend
  // solo soporta filtro por status); cubre N° de ajuste, producto, motivo y solicitante.
  const filteredAdjustments = adjustments.filter((adj: Adjustment) => {
    if (!searchText) return true;
    const haystack = [adj.adjustment_number, adj.product_name, adj.product_code, adj.reason_name, adj.requester_name]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();
    return haystack.includes(searchText.toLowerCase());
  });

  const renderActions = (record: Adjustment) => {
    const isOwnPending = record.status === 'pending' && record.responsible_user === user?.id;
    const canApproveReject = isAdminRole && record.status === 'pending';

    if (!canApproveReject && !isOwnPending) return null;

    return (
      <Space size="small" wrap>
        {canApproveReject && (
          <Popconfirm
            title="¿Aprobar esta solicitud?"
            description="El inventario se actualizará de inmediato."
            onConfirm={() => approveMutation.mutate(record.id)}
            okText="Sí, aprobar"
            cancelText="No"
          >
            <Button
              type="link"
              size="small"
              icon={<CheckCircleOutlined />}
              loading={approveMutation.isPending && approveMutation.variables === record.id}
            >
              Aprobar
            </Button>
          </Popconfirm>
        )}
        {canApproveReject && (
          <Button
            type="link"
            danger
            size="small"
            icon={<CloseCircleOutlined />}
            onClick={() => setRejectingAdjustment(record)}
          >
            Rechazar
          </Button>
        )}
        {isOwnPending && (
          <Popconfirm
            title="¿Cancelar esta solicitud?"
            onConfirm={() => cancelMutation.mutate(record.id)}
            okText="Sí"
            cancelText="No"
          >
            <Button
              type="link"
              danger
              size="small"
              icon={<StopOutlined />}
              loading={cancelMutation.isPending && cancelMutation.variables === record.id}
            >
              Cancelar
            </Button>
          </Popconfirm>
        )}
      </Space>
    );
  };

  const mobileColumns: ColumnsType<Adjustment> = [
    {
      title: 'Ajuste',
      key: 'adjustment',
      render: (_, record) => {
        const quantityDisplay = getQuantityDisplay(record);

        return (
        <div>
          <div style={{ fontWeight: 500, fontSize: '14px', marginBottom: 4 }}>
            {TYPE_ICONS[record.type]} {record.adjustment_number}
          </div>
          <div style={{ fontSize: '12px', color: '#666', marginBottom: 4 }}>
            {record.product_name} —{' '}
            <span style={{ color: quantityDisplay.color, fontWeight: 600 }}>{quantityDisplay.primary}</span>
          </div>
          {quantityDisplay.secondary && (
            <div style={{ fontSize: '11px', color: '#999', marginBottom: 4 }}>{quantityDisplay.secondary}</div>
          )}
          {/* Ubicación(es) y motivo: imprescindibles para aprobar/rechazar con criterio
              (los botones de acción están disponibles en esta misma vista mobile). */}
          <div style={{ fontSize: '12px', color: '#666', marginBottom: 4 }}>
            📍 {getLocationsText(record)}
          </div>
          <div style={{ fontSize: '12px', color: '#666', marginBottom: 4 }}>
            Motivo: {record.reason_name || 'Sin especificar'}
          </div>
          <Tag color={STATUS_COLORS[record.status]} icon={STATUS_ICONS[record.status]}>
            {STATUS_LABELS[record.status]}
          </Tag>
        </div>
        );
      },
    },
    {
      title: 'Acciones',
      key: 'actions',
      width: 90,
      fixed: 'right' as const,
      render: (_, record) => renderActions(record),
    },
  ];

  const desktopColumns: ColumnsType<Adjustment> = [
    {
      title: 'N° Ajuste',
      key: 'adjustment_number',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: 14 }}>{record.adjustment_number}</div>
          <div style={{ color: '#666', fontSize: 12 }}>{dayjs(record.movement_date).format('DD/MM/YYYY')}</div>
        </div>
      ),
    },
    {
      title: 'Tipo',
      dataIndex: 'type',
      key: 'type',
      render: (type: string) => (
        <Tag color={TYPE_COLORS[type]} icon={TYPE_ICONS[type]}>
          {TYPE_LABELS[type] || type}
        </Tag>
      ),
      filters: [
        { text: 'Entrada', value: 'entry' },
        { text: 'Salida', value: 'exit' },
        { text: 'Traslado', value: 'transfer' },
      ],
      onFilter: (value, record) => record.type === value,
    },
    {
      title: 'Producto',
      key: 'product',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: 14 }}>{record.product_name}</div>
          <div style={{ color: '#666', fontSize: 12 }}>{record.product_code} {record.brand_name ? `- ${record.brand_name}` : ''}</div>
        </div>
      ),
    },
    {
      title: 'Ubicación(es)',
      key: 'locations',
      render: (_, record) => <span>{getLocationsText(record)}</span>,
    },
    {
      title: 'Cantidad',
      key: 'quantity',
      render: (_, record) => {
        const quantityDisplay = getQuantityDisplay(record);

        return (
          <div>
            <div style={{ fontWeight: 600, color: quantityDisplay.color }}>{quantityDisplay.primary}</div>
            {quantityDisplay.secondary && (
              <div style={{ fontSize: '12px', color: '#999' }}>{quantityDisplay.secondary}</div>
            )}
          </div>
        );
      },
    },
    {
      title: 'Motivo',
      dataIndex: 'reason_name',
      key: 'reason_name',
    },
    {
      title: 'Estado',
      dataIndex: 'status',
      key: 'status',
      render: (status: string) => (
        <Tag color={STATUS_COLORS[status]} icon={STATUS_ICONS[status]}>
          {STATUS_LABELS[status] || status}
        </Tag>
      ),
      filters: [
        { text: 'Pendiente', value: 'pending' },
        { text: 'Aprobado', value: 'approved' },
        { text: 'Rechazado', value: 'rejected' },
        { text: 'Cancelado', value: 'cancelled' },
      ],
      onFilter: (value, record) => record.status === value,
    },
    {
      title: 'Solicitante',
      dataIndex: 'requester_name',
      key: 'requester_name',
      render: (text: string) => <span style={{ color: '#2E7D32' }}>{text || 'Sin asignar'}</span>,
    },
    {
      title: 'Acciones',
      key: 'actions',
      fixed: 'right' as const,
      width: 200,
      render: (_, record) => renderActions(record) || <span style={{ color: '#bbb' }}>—</span>,
    },
  ];

  const expandedRowRender = (record: Adjustment) => {
    const quantityDisplay = getQuantityDisplay(record);

    return (
    <Descriptions size="small" column={2}>
      <Descriptions.Item label="Modo de cantidad">
        {record.quantity_mode === 'absolute' ? 'Valor absoluto (fija el lote)' : 'Delta (+/-)'}
      </Descriptions.Item>
      <Descriptions.Item label="Cantidad">
        <span style={{ color: quantityDisplay.color, fontWeight: 600 }}>{quantityDisplay.primary}</span>
        {quantityDisplay.secondary && (
          <div style={{ fontSize: '12px', color: '#999' }}>{quantityDisplay.secondary}</div>
        )}
      </Descriptions.Item>
      <Descriptions.Item label="Lote">{record.batch_number || 'Automático (FIFO / nuevo)'}</Descriptions.Item>
      {record.unit_price !== null && record.unit_price !== undefined && (
        <Descriptions.Item label="Costo por unidad base">{formatCurrency(record.unit_price)}</Descriptions.Item>
      )}
      <Descriptions.Item label="Fecha de movimiento">{dayjs(record.movement_date).format('DD/MM/YYYY')}</Descriptions.Item>
      <Descriptions.Item label="Solicitante">{record.requester_name || 'Sin asignar'}</Descriptions.Item>
      {record.approver_name && <Descriptions.Item label="Resuelto por">{record.approver_name}</Descriptions.Item>}
      {record.notes && <Descriptions.Item label="Notas" span={2}>{record.notes}</Descriptions.Item>}
      {record.status === 'rejected' && record.rejection_reason && (
        <Descriptions.Item label="Motivo de rechazo" span={2}>
          <span style={{ color: '#cf1322' }}>{record.rejection_reason}</span>
        </Descriptions.Item>
      )}
    </Descriptions>
    );
  };

  return (
    <div>
      <div style={{ marginBottom: 16, display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ margin: 0, color: '#2E7D32' }}>
            Ajustes de Inventario{' '}
            {pendingCount > 0 && (
              <Badge
                count={pendingCount}
                style={{ backgroundColor: '#faad14', cursor: 'pointer' }}
                onClick={() => setStatusFilter('pending')}
                title="Solicitudes pendientes de aprobación"
              />
            )}
          </h1>
          <p style={{ color: '#666', margin: 0 }}>
            Solicitudes de entrada, salida y traslado con motivo y aprobación
          </p>
        </div>
        <Button type="primary" icon={<PlusOutlined />} onClick={() => setIsRequestModalOpen(true)}>
          Nueva Solicitud
        </Button>
      </div>

      <Alert
        type="info"
        showIcon
        message="El stock del inventario solo se actualiza cuando un administrador aprueba la solicitud."
        style={{ marginBottom: 16 }}
      />

      <Card>
        <div style={{ marginBottom: 16 }}>
          <Row gutter={[16, 16]}>
            <Col xs={24} sm={12} md={8}>
              <Search
                placeholder="Buscar por N°, producto, motivo o solicitante..."
                allowClear
                value={searchText}
                onChange={(e) => setSearchText(e.target.value)}
              />
            </Col>
            <Col xs={24} sm={12} md={8}>
              <Select
                placeholder="Filtrar por estado"
                allowClear
                style={{ width: '100%' }}
                value={statusFilter}
                onChange={setStatusFilter}
              >
                <Option value="pending">Pendiente</Option>
                <Option value="approved">Aprobado</Option>
                <Option value="rejected">Rechazado</Option>
                <Option value="cancelled">Cancelado</Option>
              </Select>
            </Col>
          </Row>
        </div>

        <ResponsiveTable
          mobileColumns={mobileColumns}
          desktopColumns={desktopColumns}
          dataSource={filteredAdjustments}
          rowKey="id"
          loading={isLoading}
          expandedRowRender={expandedRowRender}
          entityName="ajustes"
          pagination={{
            total: filteredAdjustments.length,
            pageSize: 10,
          }}
        />
      </Card>

      <AdjustmentRequestModal open={isRequestModalOpen} onClose={() => setIsRequestModalOpen(false)} />

      <Modal
        title="Rechazar Solicitud de Ajuste"
        open={!!rejectingAdjustment}
        onOk={handleReject}
        onCancel={() => {
          setRejectingAdjustment(null);
          rejectForm.resetFields();
        }}
        okText="Rechazar"
        okButtonProps={{ danger: true, loading: rejectMutation.isPending }}
        cancelText="Cancelar"
      >
        <p>
          Solicitud <strong>{rejectingAdjustment?.adjustment_number}</strong> — {rejectingAdjustment?.product_name}
        </p>
        <Form form={rejectForm} layout="vertical">
          <Form.Item
            name="rejectionReason"
            label="Motivo de rechazo"
            rules={[
              { required: true, message: 'El motivo de rechazo es requerido' },
              { max: 1000, message: 'El motivo no puede exceder 1000 caracteres' },
            ]}
          >
            <TextArea rows={4} placeholder="Explique por qué se rechaza esta solicitud..." />
          </Form.Item>
        </Form>
      </Modal>
    </div>
  );
};

export default Adjustments;
