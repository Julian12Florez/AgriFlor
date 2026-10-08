import type { ColumnsType } from 'antd/es/table';

/**
 * Quita la columna "Acciones" de una tabla cuando el usuario no tiene ninguna
 * acción que hacer en ella.
 *
 * Desde el 7-oct-2026 cada botón (Editar, Eliminar, Aprobar...) se muestra solo
 * si el perfil del usuario tiene el permiso de esa acción (Administración →
 * Perfiles). Si no tiene ninguno, la columna quedaría vacía: mejor no mostrarla.
 * Se usa igual en mobileColumns y desktopColumns.
 *
 *   const desktopColumns = conColumnaAcciones<Marca>([...], puedeEditar || puedeEliminar);
 */
export function conColumnaAcciones<T>(
  columnas: ColumnsType<T>,
  mostrar: boolean,
  key: string = 'actions',
): ColumnsType<T> {
  return mostrar ? columnas : columnas.filter((c) => c.key !== key);
}
