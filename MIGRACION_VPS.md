# Migracion del Backend a VPS

## Proposito

Este documento contiene toda la informacion necesaria para que Claude migre el backend de AgriFlor desde Render (free tier) a un VPS de produccion. Solo necesitas decirle:

```
Migra el backend a [Hetzner/DigitalOcean/Contabo] usando el archivo MIGRACION_VPS.md
```

---

## Estado Actual del Despliegue

### Arquitectura Actual

```
Frontend (Cloudflare Pages)     →  NO CAMBIA
  URL: https://agriflor.pages.dev
  Deploy: wrangler pages deploy

Backend (Render Free Tier)      →  SE MIGRA A VPS
  URL: https://agriflor-api.onrender.com
  Service ID: srv-d5t5e14hg0os73a2e45g
  Problema: cold start de 30-60s tras 15 min inactividad

Base de Datos (TiDB Cloud)      →  SE PUEDE MIGRAR AL VPS (opcional)
  Host: gateway01.us-east-1.prod.aws.tidbcloud.com
  Port: 4000
  DB: agriflor
  User: (ver GitHub Secrets o solicitar al usuario)
  Pass: (ver GitHub Secrets o solicitar al usuario)
```

### Repositorio

```
GitHub: https://github.com/Julian12Florez/AgriFlor
Branch: main
GitHub User: Julian12Florez
```

> Nota: Los tokens y API keys se gestionan como GitHub Secrets o se solicitan al usuario en el momento de la migracion. No se almacenan en texto plano en el repositorio.

### Variables de Entorno del Backend

```env
APP_NAME=AgriFlor
APP_ENV=production
APP_DEBUG=false
APP_KEY=<ver GitHub Secrets o solicitar al usuario>
APP_TIMEZONE=America/Bogota
DB_CONNECTION=mysql
DB_DATABASE=agriflor
JWT_SECRET=<ver GitHub Secrets o solicitar al usuario>
JWT_ALGO=HS256
JWT_TTL=240
LOG_CHANNEL=stderr
SESSION_DRIVER=cookie
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

> Nota: DB_HOST, DB_PORT, DB_USERNAME, DB_PASSWORD cambian segun si usas TiDB Cloud o MySQL local en el VPS.

---

## Opciones de VPS

### Opcion A: Hetzner Cloud (Recomendado)

```
Plan: CX22
Precio: ~$4 USD/mes (€3.79)
vCPU: 2
RAM: 4 GB
Disco: 40 GB NVMe
Trafico: 20 TB
Region sugerida: ash (Ashburn, Virginia - mas cerca de LATAM)
CLI: hcloud
Instalar CLI: curl -sSLO https://github.com/hetznercloud/cli/releases/latest/download/hcloud-linux-amd64.tar.gz && sudo tar -C /usr/local/bin --no-same-owner -xzf hcloud-linux-amd64.tar.gz hcloud
```

**Credenciales necesarias del usuario:**
- API Token de Hetzner Cloud (se genera en: https://console.hetzner.cloud > Proyecto > Security > API Tokens > Generate API Token con Read & Write)

### Opcion B: DigitalOcean

```
Plan: Basic $6/mes (1GB RAM) o Basic $12/mes (2GB RAM)
vCPU: 1
RAM: 1 GB (plan $6) o 2 GB (plan $12)
Disco: 25 GB SSD
Trafico: 1 TB
Region sugerida: nyc1 (New York - mas cerca de LATAM)
CLI: doctl
Instalar CLI: snap install doctl || (curl -sL https://github.com/digitalocean/doctl/releases/latest/download/doctl-linux-amd64.tar.gz | tar -xzv && sudo mv doctl /usr/local/bin/)
```

**Credenciales necesarias del usuario:**
- API Token de DigitalOcean (se genera en: https://cloud.digitalocean.com/account/api/tokens > Generate New Token con Read & Write)

### Opcion C: Contabo

```
Plan: VPS 10
Precio: ~$5 USD/mes (€4.50)
vCPU: 3
RAM: 8 GB
Disco: 75 GB NVMe
Trafico: Ilimitado
Region sugerida: US East
CLI: cntb
Instalar CLI: descargar desde https://github.com/contabo/cntb/releases
```

**Credenciales necesarias del usuario:**
- Client ID, Client Secret (Panel de Control > API)
- Email y Password de la cuenta Contabo

---

## Plan de Migracion (instrucciones para Claude)

### Paso 1: Crear el VPS

Usar la CLI del proveedor elegido para crear un servidor con:
- **OS**: Ubuntu 24.04 LTS
- **Plan**: El mas barato que cumpla (min 1GB RAM, idealmente 2GB+)
- **Region**: La mas cercana a Colombia/LATAM
- **SSH Key**: Generar una keypair y agregar la publica al servidor

Comandos segun proveedor:

```bash
# Hetzner
hcloud server create --name agriflor --type cx22 --image ubuntu-24.04 --location ash --ssh-key <key-name>

# DigitalOcean
doctl compute droplet create agriflor --region nyc1 --image ubuntu-24-04-x64 --size s-1vcpu-1gb --ssh-keys <fingerprint> --wait

# Contabo
cntb create instance --productId V45 --region US --imageId afecbb85-e2fc-46f0-9684-b46b1faf00bb --displayName agriflor
```

### Paso 2: Configurar el servidor via SSH

Ejecutar estos comandos en el VPS:

```bash
# Actualizar sistema
apt update && apt upgrade -y

# Instalar dependencias
apt install -y docker.io docker-compose-plugin nginx certbot python3-certbot-nginx ufw git

# Habilitar Docker
systemctl enable docker && systemctl start docker

# Configurar firewall
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

# Crear usuario deploy (opcional pero recomendado)
adduser --disabled-password --gecos "" deploy
usermod -aG docker deploy
mkdir -p /home/deploy/.ssh
cp ~/.ssh/authorized_keys /home/deploy/.ssh/
chown -R deploy:deploy /home/deploy/.ssh
```

### Paso 3: Decidir sobre la base de datos

**Opcion 3A: Mantener TiDB Cloud (mas simple, sin cambios en la app)**

```env
DB_HOST=gateway01.us-east-1.prod.aws.tidbcloud.com
DB_PORT=4000
DB_USERNAME=<solicitar al usuario o ver GitHub Secrets>
DB_PASSWORD=<solicitar al usuario o ver GitHub Secrets>
MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt
```

**Opcion 3B: MySQL local en el VPS (mas rapido, todo en un lugar)**

```bash
# Instalar MySQL en el VPS
docker run -d --name agriflor-mysql \
  --restart unless-stopped \
  -e MYSQL_ROOT_PASSWORD=<generar-password-seguro> \
  -e MYSQL_DATABASE=agriflor \
  -e MYSQL_USER=agriflor \
  -e MYSQL_PASSWORD=<generar-password-seguro> \
  -p 127.0.0.1:3306:3306 \
  -v mysql_data:/var/lib/mysql \
  mysql:8.0

# Env vars para MySQL local
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USERNAME=agriflor
DB_PASSWORD=<el-password-generado>
# No necesita MYSQL_ATTR_SSL_CA (es conexion local)
```

Si se usa MySQL local, hay que migrar los datos desde TiDB:
```bash
# Exportar desde TiDB
mysqldump -h gateway01.us-east-1.prod.aws.tidbcloud.com -P 4000 \
  -u '<TIDB_USERNAME>' -p'<TIDB_PASSWORD>' \
  --ssl --set-gtid-purged=OFF agriflor > agriflor_backup.sql

# Importar en MySQL local
docker exec -i agriflor-mysql mysql -u agriflor -p'<password>' agriflor < agriflor_backup.sql
```

### Paso 4: Desplegar el backend con Docker

```bash
# En el VPS, clonar el repo
cd /opt
git clone https://github.com/Julian12Florez/AgriFlor.git
cd AgriFlor/backend

# Crear archivo .env de produccion
cat > .env << 'EOF'
APP_NAME=AgriFlor
APP_ENV=production
APP_DEBUG=false
APP_KEY=<ver GitHub Secrets o solicitar al usuario>
APP_URL=https://<DOMINIO-O-IP-DEL-VPS>
APP_TIMEZONE=America/Bogota
DB_CONNECTION=mysql
DB_HOST=<segun-opcion-3A-o-3B>
DB_PORT=<segun-opcion>
DB_DATABASE=agriflor
DB_USERNAME=<segun-opcion>
DB_PASSWORD=<segun-opcion>
JWT_SECRET=<ver GitHub Secrets o solicitar al usuario>
JWT_ALGO=HS256
JWT_TTL=240
LOG_CHANNEL=stack
SESSION_DRIVER=cookie
CACHE_STORE=file
QUEUE_CONNECTION=sync
EOF

# Construir y levantar
docker build -t agriflor-api .
docker run -d --name agriflor-api \
  --restart unless-stopped \
  --env-file .env \
  -e PORT=8080 \
  -p 127.0.0.1:8080:8080 \
  --add-host=host.docker.internal:host-gateway \
  agriflor-api
```

### Paso 5: Configurar Nginx como reverse proxy + SSL

```bash
# Crear config de Nginx en el VPS
cat > /etc/nginx/sites-available/agriflor-api << 'EOF'
server {
    listen 80;
    server_name <DOMINIO-O-IP>;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 300;
        client_max_body_size 100M;
    }
}
EOF

ln -sf /etc/nginx/sites-available/agriflor-api /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

# Si tienes dominio, agregar SSL con Let's Encrypt
certbot --nginx -d <TU-DOMINIO> --non-interactive --agree-tos -m <TU-EMAIL>
```

### Paso 6: Actualizar el frontend

El frontend necesita apuntar a la nueva URL del backend:

```bash
# En la maquina local
cd frontend
VITE_API_URL=https://<DOMINIO-O-IP-DEL-VPS>/api npx vite build

# Desplegar a Cloudflare Pages
CLOUDFLARE_API_TOKEN=<ver GitHub Secrets> \
CLOUDFLARE_ACCOUNT_ID=<ver GitHub Secrets> \
wrangler pages deploy dist --project-name agriflor --branch main
```

### Paso 7: Actualizar CORS en el backend

Editar `backend/config/cors.php` para agregar el nuevo dominio si es diferente.

### Paso 8: Configurar CI/CD para el VPS

Actualizar `.github/workflows/deploy.yml` para el job de backend:

```yaml
deploy-backend:
  needs: detect-changes
  if: needs.detect-changes.outputs.backend == 'true'
  runs-on: ubuntu-latest
  steps:
    - uses: actions/checkout@v4

    - name: Deploy to VPS via SSH
      uses: appleboy/ssh-action@v1
      with:
        host: ${{ secrets.VPS_HOST }}
        username: deploy
        key: ${{ secrets.VPS_SSH_KEY }}
        script: |
          cd /opt/AgriFlor
          git pull origin main
          cd backend
          docker build -t agriflor-api .
          docker stop agriflor-api || true
          docker rm agriflor-api || true
          docker run -d --name agriflor-api \
            --restart unless-stopped \
            --env-file .env \
            -e PORT=8080 \
            -p 127.0.0.1:8080:8080 \
            --add-host=host.docker.internal:host-gateway \
            agriflor-api
          docker image prune -f
```

**Secrets adicionales a configurar en GitHub:**
- `VPS_HOST`: IP publica del VPS
- `VPS_SSH_KEY`: Clave SSH privada para conectar al VPS

### Paso 9: Limpiar Render (opcional)

Una vez verificado que todo funciona en el VPS:

```bash
# Eliminar servicio de Render via API
curl -X DELETE "https://api.render.com/v1/services/srv-d5t5e14hg0os73a2e45g" \
  -H "Authorization: Bearer <RENDER_API_KEY>"
```

---

## Verificacion Post-Migracion

Claude debe verificar estos puntos despues de migrar:

1. **Health check**: `curl https://<NUEVO-DOMINIO>/up` debe responder 200
2. **Login API**: `POST https://<NUEVO-DOMINIO>/api/auth/login` con admin@agriflor.com / admin123
3. **CORS**: Hacer preflight desde https://agriflor.pages.dev
4. **Frontend**: Abrir https://agriflor.pages.dev y verificar que cargue datos
5. **Sin cold start**: Esperar 20 minutos y hacer otra peticion (debe responder instantaneo)

---

## Arquitectura Final (Post-Migracion)

### Con TiDB Cloud (Opcion 3A)

```
Cloudflare Pages          VPS ($4-6/mes)              TiDB Cloud (gratis)
┌──────────────┐     ┌──────────────────┐     ┌──────────────────────┐
│   Frontend   │────▶│  Nginx (SSL)     │     │  MySQL Compatible    │
│   React SPA  │     │       │          │────▶│  5GB gratis          │
│              │     │  Docker:         │     │                      │
│              │     │  Laravel+PHP-FPM │     │                      │
└──────────────┘     └──────────────────┘     └──────────────────────┘
Costo: $0              Costo: $4-6/mes          Costo: $0
                                                Total: $4-6/mes
```

### Con MySQL Local (Opcion 3B)

```
Cloudflare Pages          VPS ($4-6/mes)
┌──────────────┐     ┌──────────────────────────┐
│   Frontend   │────▶│  Nginx (SSL)             │
│   React SPA  │     │       │                  │
│              │     │  Docker: Laravel+PHP-FPM  │
│              │     │       │                  │
│              │     │  Docker: MySQL 8.0        │
│              │     │  (datos persistentes)     │
└──────────────┘     └──────────────────────────┘
Costo: $0              Costo: $4-6/mes
                       Total: $4-6/mes (todo incluido)
```

---

## Instruccion para Claude

Para ejecutar la migracion, el usuario debe decir algo como:

```
Migra el backend a Hetzner usando MIGRACION_VPS.md.
Mi API token de Hetzner es: <token>
Quiero MySQL local en el VPS.
```

O:

```
Migra el backend a DigitalOcean usando MIGRACION_VPS.md.
Mi API token es: <token>
Mantener TiDB Cloud como base de datos.
```

Claude debe:
1. Leer este archivo completo
2. Instalar la CLI del proveedor elegido
3. Pedir el API token si no lo proporcionaron
4. Ejecutar los pasos 1-9 del plan de migracion
5. Verificar que todo funcione
6. Actualizar DEPLOY.md con la nueva configuracion
