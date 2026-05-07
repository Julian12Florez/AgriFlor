# /feature - Agente de Planificacion de Funcionalidades

Analiza el codebase existente y genera un blueprint para implementar una funcionalidad nueva, con codigo real basado en patrones del proyecto.

## PRINCIPIO: Aprender del codigo existente antes de planificar

NO inventar patrones. Leer como esta hecho un modulo similar que ya funciona, y replicar esos patrones para la funcionalidad nueva.

---

## PASO 1: Entender la solicitud

Leer $ARGUMENTS e identificar:
- Que funcionalidad quiere el usuario
- Que modulos existentes se afectan
- Que modulos nuevos se necesitan

## PASO 2: Estudiar un modulo similar existente

**CRITICO**: Antes de planificar, leer UN modulo similar que ya funciona bien.

Ejemplo: si la feature es "modulo de costos", estudiar como esta hecho "compras":
- Migration, Model, Controller, Resource, FormRequest, routes
- Types, api.ts, Page frontend

Extraer los patrones reales:
- Como se estructura el Model (HasUuids, timestamps, fillable, casts, relaciones)
- Como se estructura el Controller (index/store/show/update/destroy)
- Como se estructura la Page (ResponsiveTable, Ant Form, React Query, mobileColumns/desktopColumns)
- Como se registran rutas en api.php

## PASO 3: Generar blueprint

Crear `FEATURE_ANALISIS_{ID}.md`:

```markdown
# Feature: [nombre]
ID: {ID}

## Descripcion
[Que hace la funcionalidad]

## Modulos afectados
| Modulo | Tipo | Descripcion |
|--------|------|-------------|
| [nombre] | NUEVO | [que se crea] |
| [nombre] | EXTENDIDO | [que se modifica] |
| [nombre] | DEPENDENCIA | [por que se necesita] |

## Archivos a crear/modificar

### Backend

#### BACK-001: Migration
**Archivo**: `backend/database/migrations/YYYY_MM_DD_create_[tabla].php`
**Codigo**:
```php
[migration completa basada en patrones del proyecto]
```

#### BACK-002: Model
**Archivo**: `backend/app/Models/[Nombre].php`
**Codigo**:
```php
[model completo con HasUuids, relaciones, fillable, casts]
```

#### BACK-003: Controller
[etc...]

### Frontend

#### FRONT-001: Types
#### FRONT-002: API Service (agregar a api.ts)
#### FRONT-003: Page

## Orden de implementacion
1. BACK-001 (migration) → ejecutar migrate
2. BACK-002 (model)
3. BACK-003 (controller + resource + formrequest)
4. BACK-004 (routes en api.php)
5. FRONT-001 (types)
6. FRONT-002 (api.ts)
7. FRONT-003 (page + ruta en App.tsx)
```

## REGLAS

1. **Codigo real, no pseudocodigo**: Cada BACK-XXX y FRONT-XXX debe tener codigo funcional
2. **Patrones del proyecto**: UUIDs, Ant Form (no Zod), ResponsiveTable, React Query, mensajes en espanol
3. **Blueprint completo**: Incluir TODO lo necesario para que /implement solo copie y pegue
4. **No sobre-disenar**: Solo lo que el usuario pidio, nada extra
