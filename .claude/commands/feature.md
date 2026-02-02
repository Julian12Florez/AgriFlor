# /feature - Agente de Planificacion de Funcionalidades Nuevas

Analiza el codebase existente y genera un plan detallado (blueprint) para implementar una funcionalidad nueva, incluyendo esqueletos de codigo para cada componente.

## Uso con Lenguaje Natural

```
/feature "sistema de notificaciones por email"
/feature "modulo de costos y presupuestos"
/feature "historial de precios de productos"
/feature "reportes PDF de inventario por ubicacion"
/feature "sistema de alertas de stock minimo"
/feature "modulo de ordenes de trabajo"
```

El parametro puede ser:
- Una descripcion corta: `"notificaciones email"`
- Una descripcion detallada: `"sistema que envie alertas cuando el stock baje del minimo"`
- Una pregunta: `"necesito que los usuarios puedan exportar datos a Excel"`
- Un modulo nuevo: `"modulo de costos"`
- Una extension: `"agregar campos personalizados a productos"`

## Instrucciones para Claude

### PASO 1: Interpretar la Funcionalidad Solicitada

1. **Leer el parametro** del usuario e identificar:
   - Que funcionalidad quiere (objetivo principal)
   - Que modulos existentes se ven afectados
   - Que modulos nuevos se necesitan crear
   - Que integraciones se requieren

2. **Clasificar la solicitud**:

| Tipo | Ejemplo | Implica |
|------|---------|---------|
| Modulo nuevo | "modulo de costos" | BD + Models + Controllers + Routes + Pages + Types + Services |
| Extension de modulo | "agregar lotes a productos" | Migracion + Modificar Model/Controller/Page existentes |
| Funcionalidad transversal | "notificaciones email" | Nuevo servicio + modificar multiples modulos |
| Integracion | "exportar a Excel" | Nuevo Export + boton en paginas existentes |
| Mejora de flujo | "aprobacion de compras" | Nuevo estado + logica + UI |

### PASO 2: Mapear Modulos Afectados

Para cada modulo, clasificarlo como:

- **NUEVO**: No existe, hay que crear todo desde cero
- **EXTENDIDO**: Existe pero necesita cambios (nuevos campos, nuevas relaciones, nueva logica)
- **DEPENDENCIA**: Existe y se usa tal cual (solo se referencia)

#### Mapa de Relaciones entre Modulos

```
PRODUCTOS (products)
├── relacionado con → MARCAS (brands)
├── relacionado con → UNIDADES (base_units, packaging_units)
├── afecta → INVENTARIO (inventory)
├── afecta → COMPRAS (purchases)
├── afecta → TRANSFERENCIAS (transfers)
└── afecta → APLICACIONES (applications)

INVENTARIO (inventory)
├── depende de → PRODUCTOS
├── depende de → UBICACIONES (locations)
├── afectado por → COMPRAS (recepciones)
├── afectado por → TRANSFERENCIAS
├── afectado por → SALIDAS (outputs)
└── afectado por → APLICACIONES

COMPRAS (purchases)
├── depende de → PROVEEDORES (suppliers)
├── depende de → PRODUCTOS
├── afecta → INVENTARIO (cuando se recibe)
├── relacionado con → RECEPCIONES (receptions)
└── relacionado con → ADJUNTOS (attachments)

TRANSFERENCIAS (transfers)
├── depende de → PRODUCTOS
├── depende de → UBICACIONES (origen y destino)
├── afecta → INVENTARIO (disminuye origen, aumenta destino)
└── relacionado con → MOVIMIENTOS

USUARIOS (users)
├── relacionado con → ROLES (roles)
├── relacionado con → PERMISOS (permissions)
├── relacionado con → AUTH (login, JWT, tokens)
└── afecta → TODO (autorizacion)

UBICACIONES (locations)
├── relacionado con → FINCAS (farm_lots)
├── afecta → INVENTARIO
├── afecta → TRANSFERENCIAS
└── afecta → APLICACIONES

APLICACIONES (applications)
├── depende de → PRODUCTOS
├── depende de → UBICACIONES/FINCAS
├── afecta → INVENTARIO (consume productos)
└── relacionado con → ORDENES TECNICAS

ORDENES TECNICAS (technical_orders)
├── relacionado con → FINCAS
├── relacionado con → PRODUCTOS
└── genera → APLICACIONES
```

### PASO 3: Explorar Codigo Existente

**OBLIGATORIO**: Antes de disenar, leer el codigo actual para entender patrones y convenciones.

**Backend - Leer:**
```
backend/app/Models/              → Modelos existentes (patrones de relaciones, casts, fillable)
backend/app/Http/Controllers/    → Controladores (patron de respuesta, validacion, transacciones)
backend/app/Http/Requests/       → FormRequests (patron de validacion)
backend/database/migrations/     → Estructura de BD (convenciones de naming, tipos)
backend/routes/api.php           → Rutas (agrupacion, middleware, prefijos)
backend/app/Observers/           → Observers (cuando se usan)
backend/app/Exports/             → Exports (si aplica)
```

**Frontend - Leer:**
```
frontend/src/types/index.ts      → Interfaces TypeScript (convenciones de tipos)
frontend/src/services/api.ts     → Configuracion API (base URL, interceptors)
frontend/src/services/           → Servicios existentes (patron de llamadas API)
frontend/src/pages/              → Paginas existentes (patron de CRUD, formularios, tablas)
frontend/src/components/         → Componentes reutilizables
frontend/src/hooks/              → Hooks personalizados
frontend/src/context/            → Contextos (auth, permisos)
frontend/src/store/              → Estados Zustand
```

### PASO 4: Extraer Patrones del Proyecto

Al leer el codigo, identificar y documentar:

#### 4.1 Patrones de Backend
- **Modelo**: Como se definen $fillable, $casts, relaciones, scopes
- **Controlador**: Patron de index/store/show/update/destroy, uso de Resources, paginacion
- **Validacion**: FormRequest vs inline validate(), reglas comunes
- **Respuestas**: Estructura JSON estandar (ApiResponse, paginacion)
- **Rutas**: Prefijos, middleware groups, route model binding
- **Migraciones**: Convenciones de nombres de tabla, campos comunes (timestamps, soft deletes)

#### 4.2 Patrones de Frontend
- **Pagina CRUD**: Como se estructura una pagina tipica (tabla + modal + formulario)
- **Formulario**: Ant Design Form, reglas de validacion, handleSave/handleEdit
- **Tabla**: Columnas, acciones, filtros, paginacion
- **Servicio API**: Como se definen las funciones de API
- **Types**: Como se nombran interfaces, enums, DTOs
- **React Query**: Patron de useQuery/useMutation, invalidacion de cache
- **Permisos**: Como se protegen rutas y acciones

### PASO 5: Disenar la Solucion

Generar un plan detallado con componentes identificados por codigo:

#### 5.1 Base de Datos (BACK-DB-XXX)

Para cada tabla nueva o modificacion:

```
BACK-DB-001: Crear tabla [nombre]
- Campos: [lista con tipos]
- Indices: [lista]
- Foreign keys: [lista]
- Relaciones: [descripcion]
```

```
BACK-DB-002: Modificar tabla [existente]
- Agregar campos: [lista]
- Modificar campos: [lista]
- Nuevos indices: [lista]
```

#### 5.2 Backend - Archivos Nuevos (BACK-XXX)

Para cada archivo nuevo:

```
BACK-001: Crear modelo [Nombre]
- Archivo: backend/app/Models/[Nombre].php
- Fillable: [campos]
- Casts: [campos]
- Relaciones: [lista]
- Referencia: Seguir patron de [ModeloExistente]
- Esqueleto de codigo: [codigo PHP completo]
```

```
BACK-002: Crear controlador [NombreController]
- Archivo: backend/app/Http/Controllers/[NombreController].php
- Metodos: index, store, show, update, destroy [+ extras]
- Validacion: [reglas por metodo]
- Transacciones: [cuales metodos las necesitan]
- Referencia: Seguir patron de [ControladorExistente]
- Esqueleto de codigo: [codigo PHP completo]
```

```
BACK-003: Crear FormRequest [StoreNombreRequest]
- Archivo: backend/app/Http/Requests/[StoreNombreRequest].php
- Reglas: [lista de reglas]
```

```
BACK-004: Agregar rutas
- Archivo: backend/routes/api.php
- Rutas: [lista con metodo, URI, controlador, middleware]
```

#### 5.3 Backend - Modificaciones (MOD-BACK-XXX)

Para cada archivo existente que necesita cambios:

```
MOD-BACK-001: Modificar [archivo existente]
- Archivo: [path]
- Cambio: [descripcion]
- Codigo actual (referencia): [lineas relevantes]
- Codigo nuevo: [codigo con el cambio]
- Razon: [por que se modifica]
```

#### 5.4 Frontend - Archivos Nuevos (FRONT-XXX)

Para cada archivo nuevo:

```
FRONT-001: Crear tipos [nombre]
- Archivo: frontend/src/types/[nombre].ts (o agregar a index.ts)
- Interfaces: [lista]
- Enums: [lista]
- Esqueleto de codigo: [codigo TypeScript completo]
```

```
FRONT-002: Crear servicio API [nombreApi]
- Archivo: frontend/src/services/[nombre]Api.ts
- Funciones: list, create, getById, update, delete [+ extras]
- Referencia: Seguir patron de [servicioExistente]
- Esqueleto de codigo: [codigo TypeScript completo]
```

```
FRONT-003: Crear pagina [NombrePage]
- Archivo: frontend/src/pages/[Nombre]/index.tsx
- Tipo: CRUD / Detalle / Dashboard / Formulario
- Componentes: Tabla + Modal + Formulario [o variante]
- Columnas de tabla: [lista]
- Campos de formulario: [lista con tipo de input]
- Acciones: [lista]
- Permisos requeridos: [lista]
- Referencia: Seguir patron de [PaginaExistente]
- Esqueleto de codigo: [codigo TSX completo]
```

#### 5.5 Frontend - Modificaciones (MOD-FRONT-XXX)

Para cada archivo existente que necesita cambios:

```
MOD-FRONT-001: Modificar [archivo existente]
- Archivo: [path]
- Cambio: [descripcion]
- Codigo actual (referencia): [lineas relevantes]
- Codigo nuevo: [codigo con el cambio]
- Razon: [por que se modifica]
```

#### 5.6 Integracion (INTEG-XXX)

Para cada punto de integracion:

```
INTEG-001: [Descripcion de la integracion]
- Frontend envia: [estructura de datos]
- Backend recibe: [estructura esperada]
- Backend responde: [estructura de respuesta]
- Frontend consume: [como usa la respuesta]
- Mapeo de campos: [camelCase ↔ snake_case]
```

### PASO 6: Determinar Orden de Implementacion

Organizar los componentes en fases con dependencias:

```
FASE 1: Base de Datos
├── BACK-DB-001 (sin dependencias)
├── BACK-DB-002 (sin dependencias)
└── ...

FASE 2: Backend - Modelos
├── BACK-001 (depende de BACK-DB-001)
├── BACK-002 (depende de BACK-DB-001)
└── MOD-BACK-001 (depende de BACK-DB-002)

FASE 3: Backend - Logica
├── BACK-003 (depende de BACK-001)
├── BACK-004 (depende de BACK-001)
├── BACK-005 (depende de BACK-003)
└── ...

FASE 4: Backend - Rutas
├── BACK-006 (depende de BACK-005)
└── ...

FASE 5: Frontend - Tipos y Servicios
├── FRONT-001 (depende de BACK-006 para conocer la API)
├── FRONT-002 (depende de FRONT-001)
└── ...

FASE 6: Frontend - Paginas
├── FRONT-003 (depende de FRONT-001, FRONT-002)
└── ...

FASE 7: Frontend - Modificaciones
├── MOD-FRONT-001 (depende de FRONT-001)
└── ...

FASE 8: Integracion y Verificacion
├── INTEG-001 (depende de todo lo anterior)
└── ...
```

### PASO 7: Identificar Riesgos y Decisiones Arquitectonicas

Documentar:

1. **Decisiones que requieren input del usuario**:
   - Opciones de implementacion (A vs B)
   - Trade-offs (simplicidad vs flexibilidad)
   - Campos opcionales vs requeridos

2. **Riesgos tecnicos**:
   - Impacto en rendimiento
   - Migraciones que afectan datos existentes
   - Cambios que rompen compatibilidad

3. **Dependencias externas**:
   - Paquetes nuevos necesarios
   - Servicios externos (email, storage, etc.)

### PASO 8: Generar Reporte

1. Generar un ID unico:
```bash
ID=$(date +%Y%m%d_%H%M%S)
```

2. Crear `FEATURE_ANALISIS_{ID}.md`:

```markdown
# Plan de Funcionalidad: [Nombre]
ID: {ID}
Fecha: [FECHA]
Solicitud del usuario: "[parametro original]"
Interpretacion: [que entendio Claude]

## Resumen de la Funcionalidad

[Descripcion clara de que se va a implementar, en 2-3 parrafos]

## Modulos Afectados

| Modulo | Tipo | Descripcion del Impacto |
|--------|------|------------------------|
| [Modulo nuevo] | NUEVO | [que se crea] |
| [Modulo existente] | EXTENDIDO | [que cambia] |
| [Modulo existente] | DEPENDENCIA | [como se usa] |

## Patrones del Proyecto Identificados

### Backend
- Patron de controlador: [descripcion breve]
- Patron de respuesta: [estructura JSON]
- Patron de validacion: [FormRequest / inline]
- Patron de rutas: [agrupacion, middleware]

### Frontend
- Patron de pagina CRUD: [descripcion breve]
- Patron de formulario: [Ant Design Form / React Hook Form]
- Patron de servicio API: [estructura de funciones]
- Patron de tipos: [convenciones]

---

## COMPONENTES A IMPLEMENTAR

### Base de Datos

#### BACK-DB-001: [Titulo]
**Tipo**: Nueva tabla / Modificacion
**Archivo**: `backend/database/migrations/YYYY_MM_DD_HHMMSS_[nombre].php`

**Estructura**:
```sql
[DDL o descripcion de campos]
```

**Esqueleto de migracion**:
```php
[codigo PHP completo de la migracion]
```

---

### Backend - Archivos Nuevos

#### BACK-001: [Titulo]
**Tipo**: Modelo / Controlador / Request / Observer / Export
**Archivo**: `[path completo]`
**Depende de**: [BACK-DB-XXX, ...]
**Referencia**: Seguir patron de `[archivo existente]`

**Esqueleto de codigo**:
```php
[codigo PHP completo]
```

---

#### BACK-002: [Titulo]
...

---

### Backend - Modificaciones

#### MOD-BACK-001: [Titulo]
**Archivo**: `[path existente]`
**Depende de**: [BACK-XXX, ...]

**Codigo actual** (lineas [X-Y]):
```php
[fragmento actual]
```

**Codigo nuevo**:
```php
[fragmento con los cambios]
```

**Razon**: [por que se modifica]

---

### Frontend - Archivos Nuevos

#### FRONT-001: [Titulo]
**Tipo**: Types / Service / Page / Component / Hook
**Archivo**: `[path completo]`
**Depende de**: [BACK-XXX (API debe existir), FRONT-XXX, ...]
**Referencia**: Seguir patron de `[archivo existente]`

**Esqueleto de codigo**:
```typescript
[codigo TypeScript/TSX completo]
```

---

#### FRONT-002: [Titulo]
...

---

### Frontend - Modificaciones

#### MOD-FRONT-001: [Titulo]
**Archivo**: `[path existente]`
**Depende de**: [FRONT-XXX, ...]

**Codigo actual** (lineas [X-Y]):
```typescript
[fragmento actual]
```

**Codigo nuevo**:
```typescript
[fragmento con los cambios]
```

**Razon**: [por que se modifica]

---

### Integracion

#### INTEG-001: [Titulo]
**Flujo**: [Frontend → Backend → Respuesta → Frontend]

**Frontend envia**:
```typescript
[estructura de datos que envia el formulario]
```

**Backend recibe**:
```php
[reglas de validacion / estructura esperada]
```

**Backend responde**:
```json
[estructura JSON de respuesta]
```

**Frontend consume**:
```typescript
[como se usa la respuesta en el componente]
```

**Mapeo de campos**:
| Frontend (camelCase) | Backend (snake_case) | Tipo |
|---------------------|---------------------|------|
| fieldName | field_name | string |
| ... | ... | ... |

---

## Orden de Implementacion

### Fase 1: Base de Datos
| Orden | Componente | Descripcion | Dependencias |
|-------|-----------|-------------|--------------|
| 1 | BACK-DB-001 | [desc] | Ninguna |
| 2 | BACK-DB-002 | [desc] | Ninguna |

### Fase 2: Backend - Modelos
| Orden | Componente | Descripcion | Dependencias |
|-------|-----------|-------------|--------------|
| 3 | BACK-001 | [desc] | BACK-DB-001 |
| 4 | MOD-BACK-001 | [desc] | BACK-DB-002 |

### Fase 3: Backend - Logica (Requests + Controllers)
| Orden | Componente | Descripcion | Dependencias |
|-------|-----------|-------------|--------------|
| 5 | BACK-002 | [desc] | BACK-001 |
| 6 | BACK-003 | [desc] | BACK-001 |

### Fase 4: Backend - Rutas
| Orden | Componente | Descripcion | Dependencias |
|-------|-----------|-------------|--------------|
| 7 | BACK-004 | [desc] | BACK-003 |

### Fase 5: Frontend - Tipos y Servicios
| Orden | Componente | Descripcion | Dependencias |
|-------|-----------|-------------|--------------|
| 8 | FRONT-001 | [desc] | BACK-004 (API disponible) |
| 9 | FRONT-002 | [desc] | FRONT-001 |

### Fase 6: Frontend - Paginas y Componentes
| Orden | Componente | Descripcion | Dependencias |
|-------|-----------|-------------|--------------|
| 10 | FRONT-003 | [desc] | FRONT-001, FRONT-002 |

### Fase 7: Modificaciones Frontend
| Orden | Componente | Descripcion | Dependencias |
|-------|-----------|-------------|--------------|
| 11 | MOD-FRONT-001 | [desc] | FRONT-001 |

### Fase 8: Integracion
| Orden | Componente | Descripcion | Dependencias |
|-------|-----------|-------------|--------------|
| 12 | INTEG-001 | [desc] | Todo lo anterior |

---

## Resumen de Componentes

| Codigo | Tipo | Archivo | Fase |
|--------|------|---------|------|
| BACK-DB-001 | Migracion | backend/database/migrations/... | 1 |
| BACK-001 | Modelo | backend/app/Models/... | 2 |
| BACK-002 | Request | backend/app/Http/Requests/... | 3 |
| BACK-003 | Controller | backend/app/Http/Controllers/... | 3 |
| BACK-004 | Rutas | backend/routes/api.php | 4 |
| MOD-BACK-001 | Mod. Modelo | backend/app/Models/... | 2 |
| FRONT-001 | Types | frontend/src/types/... | 5 |
| FRONT-002 | Service | frontend/src/services/... | 5 |
| FRONT-003 | Page | frontend/src/pages/... | 6 |
| MOD-FRONT-001 | Mod. Page | frontend/src/pages/... | 7 |
| INTEG-001 | Integracion | - | 8 |

---

## Decisiones Arquitectonicas

### Decision 1: [Titulo]
**Opciones**:
- **Opcion A**: [descripcion] — Pros: [X]. Contras: [Y]
- **Opcion B**: [descripcion] — Pros: [X]. Contras: [Y]
**Recomendacion**: [Opcion X porque...]

### Decision 2: [Titulo]
...

---

## Riesgos Identificados

| Riesgo | Impacto | Mitigacion |
|--------|---------|------------|
| [descripcion] | [alto/medio/bajo] | [como evitarlo] |
| ... | ... | ... |

---

## Dependencias Externas

| Paquete/Servicio | Uso | Instalacion |
|-----------------|-----|-------------|
| [nombre] | [para que] | [comando] |
| ... | ... | ... |

---

## Permisos Necesarios

| Permiso | Descripcion | Modulo |
|---------|-------------|--------|
| view_[modulo] | Ver listado y detalle | [Modulo] |
| create_[modulo] | Crear nuevo registro | [Modulo] |
| edit_[modulo] | Editar registro existente | [Modulo] |
| delete_[modulo] | Eliminar registro | [Modulo] |

---

## Identificador de Sesion
ID: `{ID}`
Archivos relacionados:
- Plan: `FEATURE_ANALISIS_{ID}.md`
- Implementacion (pendiente): `FEATURE_IMPLEMENTACION_{ID}.md`
- Pruebas (pendiente): `FEATURE_PRUEBAS_{ID}.md`

## Proximos Pasos

1. Revisar este plan y aprobar/ajustar decisiones arquitectonicas
2. Ejecutar `/implement` para implementar (se detectara automaticamente este archivo)
3. Ejecutar `/implement "solo backend"` para implementar solo la parte del servidor
4. Ejecutar `/implement BACK-001` para implementar un componente especifico
```

## REGLAS CRITICAS

1. **NUNCA disenar sin leer el codigo existente primero**
   - Leer al menos 2-3 modelos, controladores y paginas existentes
   - Copiar los patrones exactos del proyecto

2. **Los esqueletos de codigo deben ser COMPLETOS**
   - No escribir `// TODO: implementar`
   - Generar codigo funcional basado en los patrones del proyecto
   - Incluir imports, exports, tipos completos

3. **Respetar convenciones del proyecto**
   - Mismos nombres de campos (snake_case en BD/API, camelCase en frontend)
   - Misma estructura de respuesta JSON
   - Misma organizacion de archivos

4. **Cada componente debe tener un codigo unico**
   - BACK-DB-XXX para migraciones
   - BACK-XXX para archivos nuevos de backend
   - MOD-BACK-XXX para modificaciones de backend
   - FRONT-XXX para archivos nuevos de frontend
   - MOD-FRONT-XXX para modificaciones de frontend
   - INTEG-XXX para puntos de integracion

5. **Las dependencias deben ser explicitas**
   - Cada componente lista sus dependencias
   - El orden de implementacion respeta las dependencias
   - No se puede implementar FRONT-002 antes de FRONT-001 si depende de el

6. **Incluir integracion como componente separado**
   - Documentar el contrato frontend ↔ backend
   - Especificar mapeo de campos explicitamente
   - Definir estructura de respuesta esperada

7. **Identificar lo que se MODIFICA vs lo que se CREA**
   - MOD-* = modificar archivo existente (incluir codigo actual y nuevo)
   - BACK-*/FRONT-* sin MOD = archivo completamente nuevo

8. **Permisos deben ser considerados**
   - Definir que permisos necesita la nueva funcionalidad
   - Indicar como integrar con el sistema de roles existente

## Output

Archivo `FEATURE_ANALISIS_{ID}.md` en la raiz del proyecto.

Al finalizar, mostrar al usuario:
```
Plan de funcionalidad completado. Archivo: FEATURE_ANALISIS_{ID}.md

Resumen:
- Componentes nuevos backend: [X]
- Componentes nuevos frontend: [X]
- Modificaciones backend: [X]
- Modificaciones frontend: [X]
- Fases de implementacion: [X]
- Decisiones pendientes: [X]

Para implementar: /implement (se detectara automaticamente este archivo)
Para implementar solo backend: /implement "solo backend"
Para implementar un componente: /implement BACK-001
```
