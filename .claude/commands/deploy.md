# /deploy - Agente de Despliegue

Gestiona el despliegue de AgriFlor a produccion. Verifica estado, dispara deploys y valida que los servicios esten funcionando.

## Uso con Lenguaje Natural

```
/deploy                          # Verificar estado + deploy si hay cambios pendientes
/deploy status                   # Solo verificar estado de servicios
/deploy frontend                 # Desplegar solo frontend (Cloudflare Pages)
/deploy backend                  # Desplegar solo backend (Render)
/deploy all                      # Desplegar frontend y backend
/deploy verify                   # Solo verificar salud de produccion
/deploy "que esta pasando"       # Diagnosticar estado actual
```

## Arquitectura de Produccion

```
┌─────────────────────────────────────────────────────────────┐
│                     GitHub Actions CI/CD                     │
│                 .github/workflows/deploy.yml                 │
│              Trigger: push main / workflow_dispatch           │
├──────────────────────┬──────────────────────────────────────┤
│                      │                                       │
│   ┌──────────────────▼───────────────┐                      │
│   │     Frontend (Cloudflare Pages)  │                      │
│   │     https://agriflor.pages.dev   │                      │
│   │     Build: npm ci + vite build   │                      │
│   │     Deploy: wrangler pages       │                      │
│   └──────────────────────────────────┘                      │
│                                                              │
│   ┌──────────────────▼───────────────┐                      │
│   │     Backend (Render.com)         │                      │
│   │     https://agriflor-api.onrender.com                   │
│   │     Docker: PHP 8.3 + Nginx     │                      │
│   │     Deploy: Render API trigger   │                      │
│   └──────────────┬───────────────────┘                      │
│                  │                                            │
│   ┌──────────────▼───────────────────┐                      │
│   │     Database (TiDB Cloud)        │                      │
│   │     MySQL 8.0 compatible         │                      │
│   │     gateway01.us-east-1.prod.aws │                      │
│   └──────────────────────────────────┘                      │
└─────────────────────────────────────────────────────────────┘
```

## URLs de Produccion

| Servicio | URL |
|----------|-----|
| Frontend | https://agriflor.pages.dev |
| Backend API | https://agriflor-api.onrender.com |
| Backend Health | https://agriflor-api.onrender.com/up |
| GitHub Actions | https://github.com/Julian12Florez/AgriFlor/actions |
| Render Dashboard | https://dashboard.render.com |
| Cloudflare Dashboard | https://dash.cloudflare.com |

## Instrucciones para Claude

### PASO 0: Interpretar Parametro

Interpretar lo que el usuario quiere hacer:

| Input | Accion |
|-------|--------|
| (vacio) | Verificar estado → deploy si hay cambios |
| `status` | Solo verificar servicios |
| `verify` | Solo health checks |
| `frontend` | Deploy solo frontend |
| `backend` | Deploy solo backend |
| `all` | Deploy todo |
| texto libre | Interpretar intencion |

### PASO 1: Verificar Prerequisitos

1. **Verificar `gh` CLI**:
   ```bash
   which gh 2>/dev/null
   ```
   - Si NO esta instalado, instalarlo:
     ```bash
     # Ubuntu/Debian
     (type -p wget >/dev/null || sudo apt-get install wget -y) && \
     sudo mkdir -p -m 755 /etc/apt/keyrings && \
     out=$(mktemp) && wget -nv -O$out https://cli.github.com/packages/githubcli-archive-keyring.gpg && \
     cat $out | sudo tee /etc/apt/keyrings/githubcli-archive-keyring.gpg > /dev/null && \
     sudo chmod go+r /etc/apt/keyrings/githubcli-archive-keyring.gpg && \
     echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/githubcli-archive-keyring.gpg] https://cli.github.com/packages stable main" | sudo tee /etc/apt/sources.list.d/github-cli.list > /dev/null && \
     sudo apt update && \
     sudo apt install gh -y
     ```
   - Verificar autenticacion: `gh auth status`
   - Si no esta autenticado, ejecutar: `gh auth login`
   - IMPORTANTE: No continuar sin `gh` autenticado.

2. **Verificar rama y cambios**:
   ```bash
   git status
   git log origin/main..HEAD --oneline
   ```

### PASO 2: Verificar Estado de Servicios

Ejecutar health checks en paralelo:

```bash
# Frontend
curl -s -o /dev/null -w "%{http_code}" https://agriflor.pages.dev

# Backend health
curl -s -o /dev/null -w "%{http_code}" https://agriflor-api.onrender.com/up

# Backend API (login endpoint existe)
curl -s -o /dev/null -w "%{http_code}" -X POST \
  -H "Content-Type: application/json" \
  -d '{"email":"test","password":"test"}' \
  https://agriflor-api.onrender.com/api/auth/login
```

Nota: El backend en Render free tier puede tener cold start (30-60s la primera request). Si responde 502/503, esperar y reintentar.

Mostrar resultado como tabla:

```
╔══════════════════╦════════╦══════════════════════════════════╗
║ Servicio         ║ Estado ║ Detalle                          ║
╠══════════════════╬════════╬══════════════════════════════════╣
║ Frontend         ║  OK    ║ 200 - https://agriflor.pages.dev ║
║ Backend Health   ║  OK    ║ 200 - /up respondio              ║
║ Backend API      ║  OK    ║ 401 - API activa                 ║
╚══════════════════╩════════╩══════════════════════════════════╝
```

Si el parametro fue solo `status` o `verify`, terminar aqui.

### PASO 3: Verificar ultimo deploy en GitHub Actions

```bash
gh run list --workflow=deploy.yml --limit 5
```

Mostrar los ultimos 5 runs con su estado (success, failure, in_progress).

Si hay un run en progreso, informar al usuario y preguntar si quiere esperar.

### PASO 4: Determinar que desplegar

Si el usuario especifico target (frontend/backend/all), usar eso.

Si no, detectar automaticamente:
```bash
# Que cambio desde el ultimo deploy exitoso?
git diff --name-only origin/main..HEAD
```

- Si hay cambios en `frontend/` → deploy frontend
- Si hay cambios en `backend/` → deploy backend
- Si no hay cambios pendientes (HEAD == origin/main), informar: "No hay cambios pendientes. El ultimo push ya debio disparar el CI/CD."

### PASO 5: Ejecutar Deploy

**Opcion A: Via GitHub Actions (Recomendado)**

Si hay cambios sin pushear:
```bash
git push origin main
```
El push a main dispara automaticamente el workflow.

Si ya se hizo push y se quiere re-disparar:
```bash
# Deploy todo
gh workflow run deploy.yml -f deploy_target=all

# Solo frontend
gh workflow run deploy.yml -f deploy_target=frontend

# Solo backend
gh workflow run deploy.yml -f deploy_target=backend
```

**Opcion B: Deploy directo (si GitHub Actions falla)**

Frontend directo a Cloudflare:
```bash
cd frontend
npm ci
VITE_API_URL=https://agriflor-api.onrender.com/api npx vite build
npx wrangler pages deploy dist --project-name agriflor --branch main
```
NOTA: Requiere `CLOUDFLARE_API_TOKEN` y `CLOUDFLARE_ACCOUNT_ID`. Preguntar al usuario.

Backend directo a Render:
```bash
# Trigger via API (requiere RENDER_API_KEY y RENDER_SERVICE_ID)
curl -X POST "https://api.render.com/v1/services/RENDER_SERVICE_ID/deploys" \
  -H "Authorization: Bearer RENDER_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{}'
```
NOTA: Preguntar al usuario por `RENDER_API_KEY`. El service ID es `srv-d5t5e14hg0os73a2e45g`.

### PASO 6: Monitorear Deploy

```bash
# Seguir el run de GitHub Actions
gh run watch

# O listar y ver un run especifico
gh run list --workflow=deploy.yml --limit 1
gh run view <RUN_ID> --log
```

Si el deploy falla:
```bash
# Ver logs del run fallido
gh run view <RUN_ID> --log-failed
```

Diagnosticar el error y sugerir solucion.

### PASO 7: Verificacion Post-Deploy

Ejecutar los mismos health checks del PASO 2.

Ademas, hacer una prueba funcional:
```bash
# Login de prueba
curl -s -X POST https://agriflor-api.onrender.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@agriflor.com","password":"admin123"}' | python3 -c "
import sys, json
data = json.load(sys.stdin)
if data.get('data', {}).get('token') or data.get('token'):
    print('Login: OK - Token obtenido')
else:
    print('Login: FALLO -', json.dumps(data)[:200])
"
```

### PASO 8: Mostrar Resumen

```
=== Deploy Completado ===

Commit: <hash> - <mensaje>
Target: frontend / backend / ambos
Metodo: GitHub Actions / directo

Servicios:
  Frontend: https://agriflor.pages.dev      → OK (200)
  Backend:  https://agriflor-api.onrender.com → OK (200)
  API:      Login funcional                   → OK

GitHub Actions: https://github.com/Julian12Florez/AgriFlor/actions/runs/<ID>
```

## Manejo de Errores Comunes

| Problema | Diagnostico | Solucion |
|----------|-------------|----------|
| Backend 502/503 | Cold start de Render | Esperar 60s y reintentar |
| Frontend 404 en rutas | `_redirects` faltante | Verificar `frontend/public/_redirects` |
| CORS bloqueado | Config desactualizada | Verificar `backend/config/cors.php` |
| Build frontend falla | Tipos TypeScript | Ejecutar `npm run build` local primero |
| Build backend falla | Docker/PHP | Verificar `Dockerfile` y dependencias |
| gh no autenticado | Sin token | Ejecutar `gh auth login` |
| Deploy triggered pero no arranca | Secrets faltantes | Verificar GitHub Secrets |

## Secrets Necesarios (GitHub)

| Secret | Descripcion | Usado por |
|--------|-------------|-----------|
| `CLOUDFLARE_API_TOKEN` | Token API de Cloudflare | Frontend deploy |
| `CLOUDFLARE_ACCOUNT_ID` | ID de cuenta Cloudflare | Frontend deploy |
| `RENDER_SERVICE_ID` | ID del servicio en Render | Backend deploy |
| `RENDER_API_KEY` | API key de Render | Backend deploy |

## IMPORTANTE

- Siempre verificar que el build local pasa antes de pushear
- El backend en Render free tier se suspende tras 15 min de inactividad
- El primer request tras suspension puede tardar 30-60s (cold start)
- Nunca pushear secretos o `.env` al repositorio
- Si GitHub Actions falla, intentar deploy directo como alternativa
