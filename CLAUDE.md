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
```

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

| Archivo | Generado por | Leido por | Proposito |
|---------|--------------|-----------|-----------|
| `ANALISIS_{ID}.md` | /analyze | /fix | Lista de problemas |
| `CORRECCIONES_{ID}.md` | /fix | /test | Log de cambios |
| `REPORTE_PRUEBAS_{ID}.md` | /test | - | Resultados de pruebas |
| `MIGRACION_VPS.md` | manual | /migrate-to-vps | Plan de migracion a VPS |

> **Nota**: `{ID}` es un timestamp unico (formato `YYYYMMDD_HHMMSS`) generado por `/analyze`.
> Los tres archivos de una sesion comparten el mismo ID, permitiendo ejecutar multiples analisis en paralelo.
> Ejemplo: `ANALISIS_20260128_143052.md` → `CORRECCIONES_20260128_143052.md` → `REPORTE_PRUEBAS_20260128_143052.md`

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
│   ├── analyze.md
│   ├── fix.md
│   ├── test.md
│   ├── deploy.md
│   └── migrate-to-vps.md
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
