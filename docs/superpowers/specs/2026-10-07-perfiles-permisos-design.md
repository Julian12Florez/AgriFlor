# Perfiles y permisos configurables — diseño

Fecha: 2026-10-07 · Aprobado por el cliente en conversación (opción A: pantalla de Perfiles).

## Objetivo

Que el administrador pueda definir, desde una pantalla, qué ve y qué puede hacer
cada perfil, y que **todo el sistema obedezca esa configuración**: menú, vistas,
botones y API. Hoy no existe pantalla para administrarlo.

## Cómo está hoy (inventario medido el 2026-10-07)

- 8 perfiles en `roles` y 30 permisos en `permissions`; 33 usuarios, todos con
  `role_id` enlazado y nombre coincidente.
- **Menú y vistas**: salen de la base. `/auth/me` entrega `accessibleModules`
  (módulos donde el perfil tiene *algún* permiso) y el frontend los usa en
  `MainLayout` y en `ProtectedRoute module="..."`.
- **API (239 rutas)**: las escrituras se protegen con `role:<lista de nombres>`
  escrita en `routes/api.php` (20 listas distintas, 139 rutas). 12 rutas usan
  `permission:export_reports`. 84 rutas solo piden sesión (77 lecturas + 7).
  4 son públicas.
- **Los permisos de la base no describen lo que el API permite.** Ejemplos:
  la base dice que `farm` puede `approve_output` y el API solo deja a
  `admin,supervisor`; `warehouse` puede crear compras por API y no tiene ningún
  permiso de compras. Los permisos hoy solo sirven para el menú y para exportar.
- Reglas por nombre de perfil dentro del código:
  `User::LOCATION_SCOPED_ROLES` (supervisor, farm: solo su finca),
  `User::SECRET_ROLES` (auditor oculto), `TaskScheduleController` y
  `TaskSchedule` (farm solo en fincas donde es responsable),
  `ProductOutputController:580` (aprobar: supervisor o admin). En el frontend:
  `getRoleName() === 'admin'` (Empresas, eliminar compra, aprobar ajustes),
  `LOCATION_SCOPED_ROLES`, `allowedRoles` para auditoría.
- `users.role` es un ENUM con nombres fijos: no admite perfiles nuevos.
- Perfiles fantasma en rutas: `liquidador` y `farm_operator` no existen.

## Modelo

Un **catálogo único de permisos** (`App\Support\PermissionCatalog`) es la fuente
de verdad: nombre, etiqueta, módulo (sección del menú), grupo (fila de la
pantalla), acción (`view | create | edit | delete | special`) y la lista de
perfiles que lo tienen el día uno.

- **Ver (`view`)** de un módulo = el módulo aparece en el menú y sus vistas
  abren. `Role::hasModuleAccess` pasa a mirar SOLO el permiso `view` del módulo
  (hoy mira "cualquier permiso del módulo"). Medido: hoy todo perfil que tiene un
  módulo tiene también su `view_*`, así que el menú no cambia.
- **Crear / Editar / Eliminar / especiales** = lo que el API deja guardar. Cada
  ruta de escritura pide su permiso con `permission:<nombre>`: POST → crear,
  PUT/PATCH → editar, DELETE → eliminar, y las acciones propias (aprobar salida,
  revertir compra, finalizar programación...) como especiales.
- El Administrador (`has_full_access`) tiene todo siempre.

## Regla del día uno: nadie gana ni pierde acceso

Cada permiso nace asignado exactamente a los perfiles que hoy pasan la lista
`role:` de esa ruta. Se demuestra con una prueba de paridad:

1. Foto del "antes": `tests/Fixtures/route_access_antes_de_perfiles.json`, sacada
   de `route:list` antes del cambio (ruta → público | sesión | lista de perfiles
   | permiso).
2. Para cada ruta actual y cada uno de los 8 perfiles se ejecutan los middlewares
   reales de acceso y el resultado tiene que ser idéntico al de la foto.
3. Paridad de menú: `getAccessibleModules()` de cada perfil igual al de
   producción antes del cambio.
4. Ninguna ruta puede aparecer o desaparecer sin declararla.

## Lo que NO se vuelve configurable (y por qué)

- **Administrador**: siempre todo; no editable (nadie se queda sin acceso).
- **Auditor**: sigue por nombre (`role:auditor`), oculto. Ni el admin ve la
  auditoría: es un requisito previo del cliente.
- **Mantenimiento** (`admin/clean-data`, `import-inventory`, `run-migrations`,
  `setup-brand`): sigue `role:admin`. Son destructivas y no son del negocio.
- **Lecturas del API**: siguen abiertas a cualquier usuario con sesión, como hoy.
  Las pantallas se cruzan entre módulos (Salidas lee ubicaciones, productos,
  usuarios, empresas; Recepción lee compras...), así que cerrarlas por módulo
  rompería pantallas. Lo que el perfil controla es qué pantallas abre (menú +
  bloqueo de la URL) y qué puede guardar.
- **Solicitar/cancelar ajuste de inventario**: hoy abierto a cualquier sesión; se
  deja igual en la parte 1 (cerrarlo sí cambiaría accesos). Queda anotado.

## Entregas

1. **Permisos por dentro (esta entrega).** Catálogo, migración de datos, rutas
   con `permission:`, `hasModuleAccess` por permiso `view`, prueba de paridad.
   Sin cambios visibles.
2. **Pantalla de Perfiles.** CRUD de perfiles con la tabla Ver/Crear/Editar/
   Eliminar + especiales; `users.role` deja de ser ENUM; casilla "solo ve su
   finca" que reemplaza `LOCATION_SCOPED_ROLES`; auditoría de cambios; no se puede
   borrar un perfil con usuarios.
3. **Botones y vistas.** El frontend oculta cada botón según `hasPermission`, y
   las reglas por nombre (`getRoleName() === 'admin'`) pasan a permisos.

## Hallazgos para decidir después (no se tocan en la parte 1)

- `warehouse` puede crear/editar/eliminar compras por API sin ver el menú Compras.
- La base daba `approve_output` a 6 perfiles; el API solo dejaba a supervisor. La
  paridad sigue al API: se le quita a los otros 5 (no podían usarlo).
- Solicitar ajustes está abierto a cualquier sesión, incluido el auditor.
- Liquidación está cerrada a todos menos admin (el perfil `liquidador` no existe).
- Registrar avance de tareas: solo admin y supervisor (el perfil `farm_operator`
  de la ruta no existe; el Operario de Finca real, `farm`, no puede).
