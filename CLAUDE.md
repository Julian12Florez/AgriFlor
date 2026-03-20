# AgriFlor - Sistema de Gestion Agricola

## Descripcion
Sistema completo de gestion agricola con control de inventario, compras, transferencias, productos y usuarios.

## Stack Tecnologico

### Backend
- **Framework**: Laravel 11.x (PHP 8.2+)
- **Auth**: JWT (php-open-source-saver/jwt-auth)
- **DB**: MySQL/MariaDB
- **Exports**: DomPDF, Maatwebsite/Excel

### Frontend
- **Framework**: React 19 + TypeScript
- **Build**: Vite 7
- **UI**: Ant Design 5.x
- **State**: Zustand
- **Forms**: React Hook Form + Zod
- **API**: TanStack React Query

---

# SISTEMA DE AGENTES INTELIGENTES

## Flujo de Trabajo

```
Pipeline de Bugs/Correcciones:

┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│    /analyze     │ ──▶ │      /fix       │ ──▶ │     /test       │
│                 │     │                 │     │                 │
│ Detecta         │     │ Corrige         │     │ Fase 1: API     │
│ problemas       │     │ errores         │     │ Fase 2: Front↔  │
│                 │     │                 │     │   Back integrac. │
│ Entiende        │     │ Backend +       │     │ Fase 3: E2E     │
│ relaciones      │     │ Frontend        │     │   flujos completos│
└─────────────────┘     └─────────────────┘     └─────────────────┘
        │                       │                       │
        ▼                       ▼                       ▼
 ANALISIS_{ID}.md     CORRECCIONES_{ID}.md  REPORTE_PRUEBAS_{ID}.md


Pipeline de Funcionalidades Nuevas:

┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│    /feature     │ ──▶ │   /implement    │ ──▶ │  /test-feature  │
│                 │     │                 │     │                 │
│ Planifica       │     │ Crea codigo     │     │ Fase 1: API     │
│ funcionalidad   │     │ completo        │     │ Fase 2: Front↔  │
│                 │     │                 │     │   Back integrac. │
│ Analiza patrones│     │ Backend +       │     │ Fase 3: E2E     │
│ del proyecto    │     │ Frontend        │     │ Fase 4: Auto-fix│
└─────────────────┘     └─────────────────┘     └─────────────────┘
        │                       │                       │
        ▼                       ▼                       ▼
FEATURE_ANALISIS_    FEATURE_IMPLEMENTA-  FEATURE_PRUEBAS_
    {ID}.md              CION_{ID}.md         {ID}.md

 (ID = timestamp unico compartido entre los 3 archivos de una sesion)

┌─────────────────────────────────────────────────────────────────┐
│                        /deploy                                   │
│                                                                 │
│  Despliega a produccion (Cloudflare Pages + Render)             │
│  Verifica estado, dispara CI/CD, valida servicios               │
│  Soporta: status, frontend, backend, all, verify                │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                     /migrate-to-vps                             │
│                                                                 │
│  Migra backend de Render → VPS (Hetzner/DigitalOcean/Contabo)  │
│  Configura Docker, Nginx, SSL, CI/CD automaticamente           │
│  Lee plan de: MIGRACION_VPS.md                                 │
└─────────────────────────────────────────────────────────────────┘

Pipeline Unificado (TODO EN UNO con Playwright):

┌─────────────────────────────────────────────────────────────────┐
│                       /dev-agri                                  │
│                                                                 │
│  Agente unificado: Analisis + Desarrollo + Playwright Testing   │
│                                                                 │
│  FASE 1: Analisis experto (backend PHP/Laravel + frontend React)│
│  FASE 2: Desarrollo seguro (cambios sin romper nada)            │
│  FASE 3: Playwright Testing OBLIGATORIO (localhost:5173)        │
│  FASE 4: Autocorreccion (si falla, corrige y re-prueba x2)     │
│  FASE 5: Reporte completo (DEV_AGRI_{ID}.md)                   │
│                                                                 │
│  Usa: MCP Playwright, 6 usuarios con roles distintos            │
│  Conoce: Patrones Laravel, Ant Design, React Query, FIFO inv.  │
└─────────────────────────────────────────────────────────────────┘
```

---

## /dev-agri - Agente Unificado de Desarrollo y Testing con Playwright

### Caracteristicas
- **Todo en uno**: Analisis + Desarrollo + Testing en un solo comando
- Analisis **experto** en PHP/Laravel y React/TypeScript/Ant Design
- Conoce **todos los patrones** del proyecto (UUIDs, Resources, FIFO, etc.)
- **Desarrollo seguro**: Verifica contexto completo antes de cada cambio
- **Playwright OBLIGATORIO**: Prueba en navegador real en `http://localhost:5173`
- **Autocorreccion**: Si falla Playwright, corrige y re-prueba (max 2 intentos)
- **6 usuarios de prueba** con roles distintos para testing de permisos
- Acepta **lenguaje natural** como parametro

### Prerequisitos
```bash
cd backend && php artisan serve    # Backend en puerto 8000
cd frontend && npm run dev         # Frontend en puerto 5173
```

### Ejemplos de Uso

```bash
# Analizar todo + probar con Playwright
/dev-agri

# Bug fixes
/dev-agri "arregla el login"
/dev-agri "el inventario no descuenta bien"
/dev-agri "la tabla de compras no carga"

# Features
/dev-agri "agrega filtro por fecha en compras"
/dev-agri "mejora la tabla de productos"

# Analisis + verificacion
/dev-agri "revisa permisos de bodeguero"
/dev-agri "solo analiza recepciones"
/dev-agri "verifica que el dashboard carga bien"
```

### Fases
1. **ANALISIS EXPERTO**: Lee backend + frontend, entiende relaciones, detecta problemas
2. **DESARROLLO SEGURO**: Aplica cambios respetando convenciones, verifica que no rompe nada
3. **PLAYWRIGHT TESTING**: Login, navegacion, interaccion, verificacion de datos, screenshots
4. **AUTOCORRECCION**: Si falla, diagnostica, corrige y re-prueba
5. **REPORTE**: `DEV_AGRI_{ID}.md` con todo documentado

### Credenciales de Testing
| Rol | Email | Password |
|-----|-------|----------|
| Administrador | admin@agriflor.com | admin123 |
| Supervisor | supervisor@agriflor.com | supervisor123 |
| Bodeguero | bodega@agriflor.com | bodega123 |
| Operario Finca | finca@agriflor.com | finca123 |
| Encargado Compras | compras@agriflor.com | compras123 |
| Financiero | financiero@agriflor.com | financiero123 |

### Output
Archivo `DEV_AGRI_{ID}.md` con las 5 fases documentadas, screenshots y resultados de Playwright.

---

## /analyze - Agente de Analisis Inteligente

### Caracteristicas
- Acepta **lenguaje natural** como parametro
- Detecta **modulos relacionados automaticamente**
- Analiza **interconexiones** entre modulos

### Ejemplos de Uso

```bash
# Analizar todo el proyecto
/analyze

# Analizar un modulo (detecta relacionados)
/analyze transferencias          # → transfers + products + locations + inventory
/analyze "roles y permisos"      # → users + roles + auth + middleware

# Analizar flujos completos
/analyze "el flujo completo de compras"
/analyze "como se manejan los productos"
/analyze "movimientos de inventario"

# Preguntas
/analyze "como funcionan las transferencias parciales"
```

### Output
Archivo `ANALISIS_{ID}.md` con:
- Modulos analizados y sus relaciones
- ERR-XXX: Errores criticos
- INC-XXX: Inconsistencias backend/frontend
- FUN-XXX: Funcionalidades incompletas
- LOG-XXX: Problemas de logica
- INT-XXX: Problemas de integracion
- Codigo actual + codigo corregido para cada hallazgo

---

## /fix - Agente de Correccion Inteligente

### Caracteristicas
- Lee `ANALISIS_{ID}.md` generado por /analyze
- Acepta **filtros en lenguaje natural**
- Corrige **backend y frontend**
- Respeta **dependencias entre correcciones**

### Ejemplos de Uso

```bash
# Corregir todo
/fix

# Filtrar por tipo
/fix "solo errores criticos"
/fix "las inconsistencias"
/fix "funcionalidades incompletas"

# Filtrar por modulo
/fix "problemas de inventario"
/fix "lo que afecta transferencias"

# Corregir uno especifico
/fix ERR-001

# Priorizar
/fix "primero los de seguridad"
```

### Output
- Archivos corregidos en el proyecto
- Archivo `CORRECCIONES_{ID}.md` con detalle de cambios

---

## /test - Agente de Pruebas de Integracion y End-to-End

### Caracteristicas
- **3 fases de pruebas**: API Backend, Integracion Front↔Back, End-to-End
- **Lee el codigo frontend** antes de probar (tipos, servicios, componentes)
- Verifica **contratos**: interfaces TypeScript vs respuestas reales de la API
- Verifica **mapeo de campos**: camelCase ↔ snake_case en ambas direcciones
- Verifica **validacion sincronizada**: reglas frontend vs reglas backend
- Verifica **manejo de errores**: formato backend compatible con frontend
- Verifica **permisos**: roles y acceso a modulos con multiples usuarios
- Simula **flujos E2E completos** como los ejecutaria un usuario real
- Verifica **consistencia de datos** entre modulos (stock, totales, conteos)
- Acepta **lenguaje natural**

### Prerequisitos
```bash
cd backend && php artisan serve  # Backend en puerto 8000
```

### Ejemplos de Uso

```bash
# Probar todo (3 fases)
/test

# Probar modulo completo (API + integracion + E2E)
/test "productos"
/test "el flujo completo de compras"
/test "transferencias entre bodegas"

# Probar solo una fase
/test "integracion"              # Solo contratos, mapeo, validacion
/test "e2e"                      # Solo flujos end-to-end
/test "api"                      # Solo endpoints backend

# Probar correcciones
/test "lo que se corrigio"

# Probar flujos especificos
/test "crear un producto y usarlo en inventario"
/test "permisos de usuario admin"
```

### Output
Archivo `REPORTE_PRUEBAS_{ID}.md` con:
- Estado por fase (API, Integracion, E2E)
- Contratos TypeScript vs API (campos faltantes, tipos incorrectos)
- Mapeo camelCase ↔ snake_case (formularios y tablas)
- Validacion sincronizada (gaps entre frontend y backend)
- Flujos E2E completos paso a paso
- Problemas encontrados con ubicacion en frontend Y backend

---

## /deploy - Agente de Despliegue

### Caracteristicas
- Despliega a **Cloudflare Pages** (frontend) y **Render** (backend)
- Verifica **estado de servicios** en produccion
- Dispara **GitHub Actions CI/CD** manual o automaticamente
- Valida **salud post-deploy** (health checks + login funcional)
- Instala y configura `gh` CLI si no esta disponible

### Ejemplos de Uso

```bash
# Verificar estado + deploy si hay cambios
/deploy

# Solo verificar servicios
/deploy status
/deploy verify

# Desplegar componente especifico
/deploy frontend
/deploy backend
/deploy all

# Diagnosticar
/deploy "que esta pasando"
```

### Output
- Estado de servicios (Frontend, Backend, API)
- Estado de GitHub Actions (ultimos runs)
- Resultado del deploy con verificacion post-deploy

---

## /migrate-to-vps - Agente de Migracion a VPS

### Caracteristicas
- Migra el backend desde **Render free tier** a un **VPS de produccion**
- Soporta **Hetzner**, **DigitalOcean** y **Contabo**
- Configura **Docker, Nginx, SSL y CI/CD** automaticamente
- Lee el plan completo desde `MIGRACION_VPS.md`
- Permite elegir entre **TiDB Cloud** o **MySQL local**

### Ejemplos de Uso

```bash
# Migrar (pregunta proveedor)
/migrate-to-vps

# Especificar proveedor
/migrate-to-vps hetzner
/migrate-to-vps digitalocean
/migrate-to-vps contabo

# Especificar proveedor + base de datos
/migrate-to-vps "hetzner con mysql local"
/migrate-to-vps "digitalocean manteniendo tidb"
```

### Output
- Backend desplegado en VPS con Docker + Nginx + SSL
- CI/CD actualizado en `.github/workflows/deploy.yml`
- Frontend recompilado apuntando al nuevo backend
- `DEPLOY.md` actualizado con nueva arquitectura

---

## /feature - Agente de Planificacion de Funcionalidades Nuevas

### Caracteristicas
- Analiza el **codebase existente** y extrae patrones del proyecto
- Genera un **blueprint completo** con esqueletos de codigo funcional
- Mapea modulos **NUEVO / EXTENDIDO / DEPENDENCIA**
- Organiza la implementacion en **fases con dependencias**
- Identifica **riesgos y decisiones arquitectonicas**
- Acepta **lenguaje natural** como parametro

### Ejemplos de Uso

```bash
# Planificar una funcionalidad nueva
/feature "sistema de notificaciones por email"
/feature "modulo de costos y presupuestos"
/feature "historial de precios de productos"
/feature "reportes PDF de inventario por ubicacion"
/feature "sistema de alertas de stock minimo"
/feature "agregar campos personalizados a productos"
```

### Output
Archivo `FEATURE_ANALISIS_{ID}.md` con:
- Modulos afectados (nuevo, extendido, dependencia)
- Patrones del proyecto identificados
- BACK-DB-XXX: Migraciones de base de datos
- BACK-XXX: Archivos nuevos de backend (modelos, controllers, requests)
- MOD-BACK-XXX: Modificaciones a backend existente
- FRONT-XXX: Archivos nuevos de frontend (types, services, pages)
- MOD-FRONT-XXX: Modificaciones a frontend existente
- INTEG-XXX: Contratos de integracion frontend ↔ backend
- Orden de implementacion por fases
- Decisiones arquitectonicas y riesgos

---

## /implement - Agente de Implementacion de Funcionalidades

### Caracteristicas
- Lee `FEATURE_ANALISIS_{ID}.md` generado por /feature
- Crea **archivos nuevos con codigo completo** (no stubs ni TODOs)
- Aplica **modificaciones quirurgicas** a archivos existentes
- Ejecuta **migraciones** automaticamente
- Verifica **sintaxis y rutas** despues de implementar
- Acepta **filtros en lenguaje natural**

### Ejemplos de Uso

```bash
# Implementar todo
/implement

# Filtrar por capa
/implement "solo backend"
/implement "solo frontend"

# Filtrar por fase
/implement "fase 1"
/implement "fases 1 a 3"

# Implementar componente especifico
/implement BACK-001
/implement FRONT-003

# Implementar lo pendiente
/implement "lo que falta"
```

### Output
- Archivos creados y modificados en el proyecto
- Migraciones ejecutadas
- Archivo `FEATURE_IMPLEMENTACION_{ID}.md` con detalle de cambios

---

## /test-feature - Agente de Pruebas y Auto-Correccion de Funcionalidades Nuevas

### Caracteristicas
- **4 fases de pruebas**: API Backend, Integracion Front↔Back, End-to-End, Auto-correccion
- **Auto-corrige** problemas encontrados y re-prueba automaticamente
- Lee el **codigo implementado** antes de probar
- Verifica **contratos**, **mapeo de campos**, **validacion sincronizada**
- Distingue entre **PASS** (primera vez) y **FIXED** (corregido automaticamente)
- Maximo **2 intentos de fix** por prueba antes de escalar a revision manual
- Acepta **lenguaje natural**

### Prerequisitos
```bash
cd backend && php artisan serve  # Backend en puerto 8000
```

### Ejemplos de Uso

```bash
# Probar todo + corregir automaticamente
/test-feature

# Probar solo una fase
/test-feature "solo api"
/test-feature "solo integracion"
/test-feature "solo e2e"

# Solo reportar sin corregir
/test-feature "sin corregir"

# Probar componente especifico
/test-feature BACK-001

# Re-probar lo que fallo antes
/test-feature "re-probar lo que fallo"
```

### Output
Archivo `FEATURE_PRUEBAS_{ID}.md` con:
- Estado por fase (API, Integracion, E2E, Auto-correccion)
- Pruebas PASS, FIXED y FAIL diferenciadas
- Correcciones aplicadas automaticamente con diff
- Problemas pendientes de revision manual
- Archivos modificados por auto-correccion

---

## Mapa de Relaciones entre Modulos

```
PRODUCTOS ─────────┬─────────────────────────────────────────┐
    │              │                                         │
    ├─► MARCAS     ├─► INVENTARIO ◄── UBICACIONES           │
    │              │       │              │                  │
    └─► UNIDADES   │       │              └─► FINCAS         │
                   │       │                    │            │
                   │       ▼                    │            │
                   └─► TRANSFERENCIAS ◄────────┘            │
                           │                                 │
                           ▼                                 │
PROVEEDORES ──► COMPRAS ──► RECEPCIONES ──► INVENTARIO      │
                                                             │
USUARIOS ──► ROLES ──► PERMISOS ──► AFECTA TODO             │
                                                             │
ORDENES TECNICAS ──► APLICACIONES ──► INVENTARIO ◄──────────┘
```

Cuando analizas un modulo, automaticamente se incluyen sus dependencias.

---

## Archivos Generados

### Pipeline de Bugs/Correcciones

| Archivo | Generado por | Leido por | Proposito |
|---------|--------------|-----------|-----------|
| `ANALISIS_{ID}.md` | /analyze | /fix | Lista de problemas |
| `CORRECCIONES_{ID}.md` | /fix | /test | Log de cambios |
| `REPORTE_PRUEBAS_{ID}.md` | /test | - | Resultados de pruebas |

### Pipeline de Funcionalidades Nuevas

| Archivo | Generado por | Leido por | Proposito |
|---------|--------------|-----------|-----------|
| `FEATURE_ANALISIS_{ID}.md` | /feature | /implement | Plan de implementacion |
| `FEATURE_IMPLEMENTACION_{ID}.md` | /implement | /test-feature | Log de archivos creados/modificados |
| `FEATURE_PRUEBAS_{ID}.md` | /test-feature | - | Resultados + correcciones aplicadas |

### Pipeline Unificado (dev-agri)

| Archivo | Generado por | Leido por | Proposito |
|---------|--------------|-----------|-----------|
| `DEV_AGRI_{ID}.md` | /dev-agri | - | Reporte completo (analisis + cambios + Playwright) |

### Otros

| Archivo | Generado por | Leido por | Proposito |
|---------|--------------|-----------|-----------|
| `MIGRACION_VPS.md` | manual | /migrate-to-vps | Plan de migracion a VPS |

> **Nota**: `{ID}` es un timestamp unico (formato `YYYYMMDD_HHMMSS`) generado por `/analyze` o `/feature`.
> Los tres archivos de una sesion comparten el mismo ID, permitiendo ejecutar multiples sesiones en paralelo.
> Ejemplo bugs: `ANALISIS_20260128_143052.md` → `CORRECCIONES_20260128_143052.md` → `REPORTE_PRUEBAS_20260128_143052.md`
> Ejemplo features: `FEATURE_ANALISIS_20260202_143052.md` → `FEATURE_IMPLEMENTACION_20260202_143052.md` → `FEATURE_PRUEBAS_20260202_143052.md`

---

## Estructura del Proyecto

```
AgriFlor/
├── backend/                     # Laravel API
│   ├── app/
│   │   ├── Http/Controllers/    # Endpoints
│   │   ├── Models/              # Eloquent models
│   │   ├── Exports/             # Excel/PDF
│   │   └── Observers/           # Model observers
│   ├── database/migrations/     # DB schema
│   ├── routes/api.php           # API routes
│   └── tests/
├── frontend/                    # React SPA
│   └── src/
│       ├── pages/               # Vistas
│       ├── components/          # Componentes
│       ├── services/            # API calls
│       ├── types/               # TypeScript
│       ├── hooks/               # Custom hooks
│       ├── context/             # React context
│       └── store/               # Zustand
├── .claude/commands/            # Agentes
│   ├── dev-agri.md             # TODO EN UNO: Analisis + Desarrollo + Playwright
│   ├── analyze.md              # Pipeline bugs: analisis
│   ├── fix.md                  # Pipeline bugs: correccion
│   ├── test.md                 # Pipeline bugs: pruebas
│   ├── feature.md              # Pipeline features: planificacion
│   ├── implement.md            # Pipeline features: implementacion
│   ├── test-feature.md         # Pipeline features: pruebas + auto-fix
│   ├── deploy.md               # Despliegue
│   └── migrate-to-vps.md       # Migracion a VPS
├── MIGRACION_VPS.md             # Plan de migracion a VPS
└── CLAUDE.md                    # Este archivo
```

---

## Comandos Utiles

### Backend
```bash
cd backend
php artisan serve              # Servidor dev
php artisan migrate            # Migraciones
php artisan db:seed            # Datos prueba
php artisan test               # PHPUnit
php artisan route:list         # Ver rutas
```

### Frontend
```bash
cd frontend
npm run dev     # Desarrollo
npm run build   # Produccion
npm run lint    # Linter
```

---

## Notas Importantes

1. **API URL**: `frontend/src/services/api.ts`
2. **Auth Context**: `frontend/src/context/AuthContext.tsx`
3. **Bug conocido**: `BUG_REPORT_TRANSFERENCIA_PARCIAL.md`
4. **Documentacion previa**: Archivos .md en raiz
