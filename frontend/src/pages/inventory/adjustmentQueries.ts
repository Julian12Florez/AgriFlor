import type { QueryClient } from '@tanstack/react-query';

/**
 * Invalida las queries que dependen del estado de los ajustes de inventario.
 *
 * Compartido por `Adjustments.tsx` (aprobar/rechazar/cancelar) y
 * `AdjustmentRequestModal.tsx` (crear) para que el criterio de invalidación viva en
 * un solo lugar: crear una solicitud no muta stock, pero se invalida igual por
 * consistencia con las otras tres mutaciones (aprobar es la que sí lo cambia).
 */
export function invalidateAdjustmentRelated(queryClient: QueryClient): void {
  queryClient.invalidateQueries({ queryKey: ['adjustments'] });
  queryClient.invalidateQueries({ queryKey: ['inventory'] });
  queryClient.invalidateQueries({ queryKey: ['movements'] });
}
