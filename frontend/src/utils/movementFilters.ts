/**
 * Traduce el filtro "Tipo de Movimiento" (que en la UI incluye la opción
 * "Ajuste", value='adjustment') a los parámetros que entiende la API de
 * movimientos (`/inventory/movements` y `/inventory/movements/report`).
 *
 * 'adjustment' NO es un valor de `inventory_movements.type` — el enum es
 * entry/exit/transfer/application y NUNCA incluye 'adjustment' — así que
 * enviarlo como `type=adjustment` da SIEMPRE 0 resultados aunque existan
 * ajustes aprobados (medido: `GET /inventory/movements?type=adjustment` →
 * meta.total = 0 mientras `type=exit` → 804). Un ajuste es un DOCUMENTO
 * relacionado (`related_document_type`), no un tipo de movimiento, así que se
 * traduce al parámetro `related_document_type` que
 * InventoryController::resolveRelatedDocumentTypeAlias sabe interpretar.
 *
 * Compartido por los 3 informes que ofrecen este filtro: InventoryAudit,
 * InventoryMovementsReport y ConsolidatedMovementsReport.
 */
export function buildMovementTypeParams(type: string | undefined): Record<string, string> {
  if (!type) return {};

  return type === 'adjustment' ? { related_document_type: 'adjustment' } : { type };
}

/**
 * Contraparte de buildMovementTypeParams() para el filtro LOCAL (columna
 * `filters`/`onFilter` de AntD Table sobre los datos ya cargados): 'adjustment'
 * se decide por `related_document_type`, cualquier otro valor por `type`.
 */
export function matchesMovementTypeFilter(
  value: string,
  record: { type: string; related_document_type?: string | null }
): boolean {
  if (value === 'adjustment') {
    return record.related_document_type === 'App\\Models\\Adjustment';
  }

  return record.type === value;
}
