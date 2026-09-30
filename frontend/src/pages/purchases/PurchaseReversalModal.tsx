import React, { useEffect, useState } from 'react';
import { Alert, Button, Input, Modal, Spin, Table, Tag, Typography, message } from 'antd';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { purchasesApi, handleApiError } from '../../services/api';

const { Text } = Typography;

interface Props {
  purchase: { id: string; orderNumber: string } | null;
  open: boolean;
  onClose: () => void;
  onDeleted: () => void;
}

interface Linea {
  reception_number: string;
  product_name: string;
  brand_name: string;
  unit: string;
  movement_date: string;
  entered: number;
  still_in_lot: number;
  already_dispatched: number;
  ok: boolean;
  problems: string[];
}

const num = (n: number) => Number(n).toLocaleString('es-CO', { maximumFractionDigits: 2 });

/**
 * "Eliminar compra": también las YA RECIBIDAS, revirtiendo lo que metieron al
 * inventario. Hasta el 30-sep-2026 la app no dejaba borrar ninguna compra y hubo
 * que hacerlo con SQL a mano (PUR-2026-402555, un remanente de finca registrado
 * como compra ficticia).
 *
 * Primero muestra la vista previa —qué pasa con cada producto— y solo deja
 * confirmar si nada lo impide y hay un motivo escrito.
 */
const PurchaseReversalModal: React.FC<Props> = ({ purchase, open, onClose, onDeleted }) => {
  const queryClient = useQueryClient();
  const [motivo, setMotivo] = useState('');

  useEffect(() => {
    if (open) setMotivo('');
  }, [open]);

  const { data, isLoading, isError } = useQuery({
    queryKey: ['purchase-reversal-preview', purchase?.id],
    queryFn: () => purchasesApi.reversalPreview(purchase!.id),
    enabled: open && !!purchase,
    // La vista previa depende del stock de este instante: nunca desde caché.
    staleTime: 0,
    gcTime: 0,
  });

  const plan = data?.data;
  const lineas: Linea[] = plan?.lines ?? [];
  const bloqueos: string[] = plan?.blockers ?? [];
  const puede = !!plan?.can_reverse;
  const motivoValido = motivo.trim().length >= 10;

  const reverseMutation = useMutation({
    mutationFn: () => purchasesApi.reverse(purchase!.id, motivo.trim()),
    onSuccess: (resp: any) => {
      queryClient.invalidateQueries({ queryKey: ['purchases'] });
      queryClient.invalidateQueries({ queryKey: ['inventory'] });
      queryClient.invalidateQueries({ queryKey: ['receptions'] });
      queryClient.invalidateQueries({ queryKey: ['available-sources'] });
      message.success(resp?.message || 'Compra eliminada. Su inventario quedó revertido.');
      onDeleted();
    },
    onError: (error: any) => {
      handleApiError(error, 'No se pudo eliminar la compra');
    },
  });

  const hayDespachado = lineas.some((l) => l.already_dispatched > 0.009);

  return (
    <Modal
      title={`Eliminar compra ${purchase?.orderNumber ?? ''}`}
      open={open}
      onCancel={onClose}
      width={880}
      destroyOnClose
      footer={[
        <Button key="cancel" onClick={onClose}>
          Cancelar
        </Button>,
        <Button
          key="ok"
          danger
          type="primary"
          disabled={!puede || !motivoValido}
          loading={reverseMutation.isPending}
          onClick={() => reverseMutation.mutate()}
        >
          Eliminar y revertir inventario
        </Button>,
      ]}
    >
      {isLoading && (
        <div style={{ textAlign: 'center', padding: 32 }}>
          <Spin tip="Calculando qué pasaría con el inventario..." />
        </div>
      )}

      {isError && (
        <Alert type="error" showIcon message="No se pudo calcular la vista previa. Intente de nuevo." />
      )}

      {plan && (
        <>
          <Alert
            type="warning"
            showIcon
            style={{ marginBottom: 16 }}
            message="Esta acción no se puede deshacer"
            description={
              lineas.length === 0 ? (
                'La compra no tiene recepciones: solo se elimina el documento.'
              ) : (
                <>
                  Se eliminan la compra, su recepción ({(plan.receptions ?? []).join(', ')}) y las entradas que
                  metió al kardex. Lo que siga en el lote de la compra se retira de la bodega.
                  {hayDespachado && (
                    <>
                      {' '}Lo que <strong>ya salió</strong> de ese lote hacia fincas se descuenta de los otros lotes
                      del mismo producto (FIFO), para que kardex y físico sigan cuadrando.
                    </>
                  )}
                </>
              )
            }
          />

          {bloqueos.length > 0 && (
            <Alert
              type="error"
              showIcon
              style={{ marginBottom: 16 }}
              message="No se puede eliminar"
              description={
                <ul style={{ margin: 0, paddingLeft: 18 }}>
                  {bloqueos.map((b) => (
                    <li key={b}>{b}</li>
                  ))}
                </ul>
              }
            />
          )}

          {lineas.length > 0 && (
            <Table<Linea>
              size="small"
              pagination={false}
              rowKey={(l) => `${l.reception_number}-${l.product_name}-${l.brand_name}`}
              dataSource={lineas}
              style={{ marginBottom: 16 }}
              columns={[
                {
                  title: 'Producto',
                  render: (_, l) => (
                    <>
                      <Text strong>{l.product_name}</Text>
                      <div style={{ fontSize: 12, color: '#888' }}>{l.brand_name}</div>
                    </>
                  ),
                },
                { title: 'Entró', align: 'right', render: (_, l) => `${num(l.entered)} ${l.unit}` },
                { title: 'Sigue en el lote', align: 'right', render: (_, l) => `${num(l.still_in_lot)} ${l.unit}` },
                {
                  title: 'Ya salió (se descuenta de otros lotes)',
                  align: 'right',
                  render: (_, l) =>
                    l.already_dispatched > 0.009 ? (
                      <Text type="warning">{`${num(l.already_dispatched)} ${l.unit}`}</Text>
                    ) : (
                      '—'
                    ),
                },
                {
                  title: 'Estado',
                  render: (_, l) =>
                    l.ok ? <Tag color="green">OK</Tag> : <Tag color="red">{l.problems.join('; ')}</Tag>,
                },
              ]}
            />
          )}

          <div>
            <Text strong>Motivo</Text> <Text type="secondary">(obligatorio, queda en la auditoría)</Text>
            <Input.TextArea
              rows={2}
              maxLength={500}
              showCount
              value={motivo}
              disabled={!puede}
              onChange={(e) => setMotivo(e.target.value)}
              placeholder="Ej.: Era un remanente de finca registrado como compra; se rehace como ajuste + salida de remanente."
              style={{ marginTop: 6 }}
            />
          </div>
        </>
      )}
    </Modal>
  );
};

export default PurchaseReversalModal;
