/**
 * El valor que YA tiene un registro, como opción de un selector, aunque la lista
 * de opciones no lo traiga.
 *
 * Varios selectores cargan sus opciones FILTRADAS (solo usuarios activos, solo
 * categorías activas, solo proveedores activos...). Si el registro que se abre
 * apunta a algo que quedó fuera del filtro —un responsable que se inactivó, una
 * categoría dada de baja—, Ant Design no encuentra la opción y muestra el ID
 * interno ("a1d15395-bc21-...") en vez del nombre. Pasó en Salidas el
 * 2-oct-2026; aquí se cierra para el resto de selectores.
 *
 * La opción agregada lleva el nombre real y una marca para que se note que ya
 * no está disponible para elegir de nuevo.
 */
export interface OpcionSelect {
  value: string;
  label: string;
}

export function conOpcionActual(
  opciones: OpcionSelect[] | undefined,
  actual: { value?: string | null; label?: string | null } | null | undefined,
  marca = ' (inactivo)',
): OpcionSelect[] {
  const lista = opciones ?? [];
  if (!actual?.value || lista.some((o) => o.value === actual.value)) {
    return lista;
  }
  return [...lista, { value: actual.value, label: `${actual.label || 'Sin nombre'}${marca}` }];
}
