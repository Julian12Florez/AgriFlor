# Despliegue AgriFlor - Documentacion

## Arquitectura de Despliegue

```
┌──────────────────────┐     ┌──────────────────────────┐     ┌────────────────────────┐
│   Cloudflare Pages   │     │        Render.com        │     │     TiDB Cloud         │
│                      │     │                          │     │                        │
│   Frontend React     │────▶│   Backend Laravel API    │────▶│   MySQL Compatible     │
│   (SPA estatica)     │     │   (Docker: PHP+Nginx)    │     │   (Serverless)         │
│                      │     │                          │     │                        │
│ agriflor.pages.dev   │     │ agriflor-api.onrender.com│     │ gateway01.us-east-1    │
│                      │     │                          │     │ .prod.aws.tidbcloud.com│
└──────────────────────┘     └──────────────────────────┘     └────────────────────────┘
```

---

## 1. Frontend - Cloudflare Pages

### Informacion General

| Campo | Valor |
|-------|-------|
| **Plataforma** | Cloudflare Pages |
| **URL Produccion** | https://agriflor.pages.dev |
| **Tipo** | Sitio estatico (SPA) |
| **Framework** | React 19 + Vite 7 |
| **Despliegue** | CLI (wrangler) o GitHub Actions |
| **Costo** | Gratuito |
| **Limite Bandwidth** | Ilimitado |

### Como se despliega

El frontend se compila localmente (o en CI/CD) y se sube la carpeta `dist/` a Cloudflare Pages.

```bash
# 1. Compilar con la URL del backend
cd frontend
VITE_API_URL=https://agriflor-api.onrender.com/api npx vite build

# 2. Desplegar a Cloudflare
CLOUDFLARE_API_TOKEN=<tu-token> \
CLOUDFLARE_ACCOUNT_ID=<tu-account-id> \
wrangler pages deploy dist --project-name agriflor --branch main
```

### Configuracion Importante

- **Variable de build**: `VITE_API_URL` - URL del backend API (se inyecta en build time)
- **`_redirects`**: Archivo en `public/_redirects` que redirige todas las rutas a `index.html` (necesario para React Router)
- **CORS**: El backend debe permitir `https://agriflor.pages.dev` como origen

### Credenciales necesarias

| Credencial | Donde obtenerla |
|------------|-----------------|
| `CLOUDFLARE_API_TOKEN` | dash.cloudflare.com > My Profile > API Tokens |
| `CLOUDFLARE_ACCOUNT_ID` | dash.cloudflare.com > sidebar izquierdo (codigo hex) |

---

## 2. Backend - Render.com

### Informacion General

| Campo | Valor |
|-------|-------|
| **Plataforma** | Render.com |
| **URL Produccion** | https://agriflor-api.onrender.com |
| **Tipo** | Web Service (Docker) |
| **Framework** | Laravel 11 + PHP 8.3 |
| **Plan** | Free |
| **Region** | Oregon (US West) |
| **Service ID** | srv-d5t5e14hg0os73a2e45g |
| **Auto Deploy** | Si (desde GitHub main branch) |

### Arquitectura del contenedor Docker

```
┌─────────────────────────────────┐
│        Docker Container         │
│                                 │
│  ┌──────────┐   ┌────────────┐ │
│  │  Nginx   │──▶│  PHP-FPM   │ │
│  │ :$PORT   │   │  :9000     │ │
│  └──────────┘   └────────────┘ │
│         │                       │
│  ┌──────────────────────────┐  │
│  │     Supervisord          │  │
│  │  (gestiona ambos)        │  │
│  └──────────────────────────┘  │
│                                 │
│  start.sh: envsubst, migrate,  │
│  permisos, supervisord          │
└─────────────────────────────────┘
```

### Archivos clave del despliegue

| Archivo | Proposito |
|---------|-----------|
| `backend/Dockerfile` | Imagen de produccion (PHP 8.3 + Nginx + Supervisor) |
| `backend/docker/start.sh` | Script de inicio: configura nginx, migra BD, inicia servicios |
| `backend/docker/nginx-render.conf` | Configuracion Nginx (template con $PORT) |
| `backend/docker/supervisord.conf` | Configuracion Supervisor (nginx + php-fpm) |
| `backend/config/cors.php` | Origenes permitidos para CORS |

### Variables de Entorno (configuradas en Render)

| Variable | Valor | Descripcion |
|----------|-------|-------------|
| `APP_NAME` | AgriFlor | Nombre de la app |
| `APP_ENV` | production | Entorno |
| `APP_DEBUG` | true/false | Debug mode |
| `APP_KEY` | base64:... | Clave de encriptacion Laravel |
| `APP_URL` | https://agriflor-api.onrender.com | URL publica |
| `APP_TIMEZONE` | America/Bogota | Zona horaria |
| `DB_CONNECTION` | mysql | Driver de BD |
| `DB_HOST` | gateway01.us-east-1.prod.aws.tidbcloud.com | Host TiDB |
| `DB_PORT` | 4000 | Puerto TiDB |
| `DB_DATABASE` | agriflor | Nombre de la BD |
| `DB_USERNAME` | nZosCocbwoAoWqv.root | Usuario TiDB |
| `DB_PASSWORD` | *** | Password TiDB |
| `JWT_SECRET` | *** | Secreto para tokens JWT |
| `JWT_ALGO` | HS256 | Algoritmo JWT |
| `JWT_TTL` | 240 | Tiempo de vida del token (minutos) |
| `LOG_CHANNEL` | stderr | Logs a stderr (visible en Render) |
| `SESSION_DRIVER` | cookie | Sesiones en cookies |
| `CACHE_STORE` | file | Cache en archivos |
| `QUEUE_CONNECTION` | sync | Colas sincronas |
| `MYSQL_ATTR_SSL_CA` | /etc/ssl/certs/ca-certificates.crt | Certificado SSL para TiDB |

### Credenciales necesarias

| Credencial | Donde obtenerla |
|------------|-----------------|
| `RENDER_API_KEY` | render.com > Account Settings > API Keys |

### Comportamiento Free Tier

- El servicio **se suspende despues de 15 minutos** sin trafico
- La primera peticion tras suspension tarda **~30-60 segundos** (cold start)
- 750 horas gratis/mes (suficiente para un servicio 24/7)

---

## 3. Base de Datos - TiDB Cloud

### Informacion General

| Campo | Valor |
|-------|-------|
| **Plataforma** | TiDB Cloud (Serverless) |
| **Motor** | Compatible MySQL 8.0 |
| **Region** | US East (AWS) |
| **Host** | gateway01.us-east-1.prod.aws.tidbcloud.com |
| **Puerto** | 4000 |
| **Base de datos** | agriflor |
| **SSL** | Requerido |
| **Plan** | Free Tier |

### Limites Free Tier

| Recurso | Limite |
|---------|--------|
| Almacenamiento | 5 GB |
| Request Units | 50 millones/mes |
| Bandwidth | 50 GB/mes |

### Acceso

- **Dashboard**: https://tidbcloud.com
- **Conexion directa**: Requiere SSL (`MYSQL_ATTR_SSL_CA`)

---

## 4. Repositorio GitHub

| Campo | Valor |
|-------|-------|
| **URL** | https://github.com/Julian12Florez/AgriFlor |
| **Visibilidad** | Publica (cambiar a privada si se desea) |
| **Branch principal** | main |
| **Auto-deploy Render** | Si (cada push a main) |

### Estructura relevante para deploy

```
AgriFlor/
├── backend/
│   ├── Dockerfile            # Imagen de produccion
│   ├── docker/
│   │   ├── start.sh          # Script de inicio
│   │   ├── nginx-render.conf # Config Nginx
│   │   └── supervisord.conf  # Config Supervisor
│   ├── config/cors.php       # CORS
│   └── ...
├── frontend/
│   ├── public/_redirects     # SPA redirects
│   ├── src/services/api.ts   # URL del backend
│   └── ...
└── .github/workflows/
    └── deploy.yml            # CI/CD (GitHub Actions)
```

---

## 5. Credenciales de la Aplicacion

### Usuario Admin

| Campo | Valor |
|-------|-------|
| Email | admin@agriflor.com |
| Password | admin123 |
| Rol | Administrador (acceso completo) |

### Datos precargados

- 8 productos agricolas
- 6 marcas (Yara, Bayer, BASF, Syngenta, Corteva, FMC)
- 5 unidades base (kg, L, unidades, g, mL)
- 11 unidades de empaque
- 3 proveedores
- 24 permisos, 5 roles

---

## 6. Comandos Utiles

### Despliegue manual del frontend

```bash
cd frontend
VITE_API_URL=https://agriflor-api.onrender.com/api npx vite build
CLOUDFLARE_API_TOKEN=<token> CLOUDFLARE_ACCOUNT_ID=<id> \
  wrangler pages deploy dist --project-name agriflor --branch main
```

### Forzar redeploy del backend

```bash
curl -X POST "https://api.render.com/v1/services/srv-d5t5e14hg0os73a2e45g/deploys" \
  -H "Authorization: Bearer <RENDER_API_KEY>" \
  -H "Content-Type: application/json" \
  -d '{}'
```

### Ejecutar migraciones manualmente

```bash
# Desde Docker local conectado a TiDB
docker run --rm \
  -e APP_KEY="base64:..." \
  -e DB_CONNECTION=mysql \
  -e DB_HOST=gateway01.us-east-1.prod.aws.tidbcloud.com \
  -e DB_PORT=4000 \
  -e DB_DATABASE=agriflor \
  -e DB_USERNAME='nZosCocbwoAoWqv.root' \
  -e DB_PASSWORD='...' \
  -e MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt \
  agriflor-api-test php artisan migrate --force
```

### Ver logs del backend

```bash
# Via Render Dashboard
# https://dashboard.render.com/web/srv-d5t5e14hg0os73a2e45g/logs
```

---

## 7. Troubleshooting

### El frontend no carga datos

1. Verificar que el backend este despierto: `curl https://agriflor-api.onrender.com/up`
2. Si tarda, es cold start del free tier (~30s)
3. Verificar CORS en `backend/config/cors.php`

### El backend no arranca

1. Verificar variables de entorno en Render Dashboard
2. Verificar conexion a TiDB (puede estar en mantenimiento)
3. Revisar logs en Render Dashboard

### Error de base de datos

1. Verificar credenciales TiDB en Render env vars
2. TiDB requiere SSL: `MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt`
3. Verificar que no se excedio el free tier (5GB storage)
