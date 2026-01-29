# /migrate-to-vps - Agente de Migracion a VPS

Migra el backend de AgriFlor desde Render (free tier) a un VPS de produccion, siguiendo el plan documentado en `MIGRACION_VPS.md`.

## Uso con Lenguaje Natural

```
/migrate-to-vps                                         # Pregunta proveedor y credenciales
/migrate-to-vps hetzner                                 # Migrar a Hetzner (pide API token)
/migrate-to-vps digitalocean                            # Migrar a DigitalOcean (pide API token)
/migrate-to-vps contabo                                 # Migrar a Contabo (pide credenciales)
/migrate-to-vps "hetzner con mysql local"               # Hetzner + MySQL en el VPS
/migrate-to-vps "digitalocean manteniendo tidb"         # DigitalOcean + TiDB Cloud
```

El parametro puede ser:
- Vacio: pregunta al usuario que proveedor usar
- Un proveedor: `hetzner`, `digitalocean`, `contabo`
- Una descripcion: `"hetzner con mysql local en el vps"`
- Una preferencia de DB: `"digitalocean manteniendo tidb cloud"`

## Instrucciones para Claude

### PASO 0: Leer el Plan de Migracion

1. Leer `MIGRACION_VPS.md` completo desde la raiz del proyecto
2. Este archivo contiene TODA la informacion necesaria: arquitectura actual, opciones de VPS, variables de entorno, pasos detallados
3. Si el archivo no existe, informar al usuario que no se encontro el plan de migracion

### PASO 1: Determinar Proveedor de VPS

Si el usuario NO especifico proveedor en el parametro, preguntar:

```
¿A que proveedor de VPS quieres migrar?

Opciones:
A) Hetzner Cloud (Recomendado) - CX22: 2 vCPU, 4GB RAM, 40GB NVMe - ~$4/mes
B) DigitalOcean - Basic: 1 vCPU, 1-2GB RAM, 25GB SSD - $6-12/mes
C) Contabo - VPS 10: 3 vCPU, 8GB RAM, 75GB NVMe - ~$5/mes
```

Si el usuario ya especifico (ej: `/migrate-to-vps hetzner`), usar esa opcion directamente.

### PASO 2: Obtener Credenciales

Segun el proveedor elegido, solicitar las credenciales necesarias:

**Hetzner:**
- API Token (se genera en: https://console.hetzner.cloud > Proyecto > Security > API Tokens)

**DigitalOcean:**
- API Token (se genera en: https://cloud.digitalocean.com/account/api/tokens)

**Contabo:**
- Client ID y Client Secret (Panel de Control > API)
- Email y Password de la cuenta

NO continuar sin las credenciales. Son obligatorias.

### PASO 3: Decidir sobre la Base de Datos

Si el usuario NO especifico preferencia de base de datos, preguntar:

```
¿Que hacer con la base de datos?

A) Mantener TiDB Cloud (mas simple, sin migracion de datos)
B) MySQL local en el VPS (mas rapido, todo en un solo lugar)
```

Referirse a las opciones 3A y 3B del archivo `MIGRACION_VPS.md` para los detalles de cada opcion.

### PASO 4: Instalar CLI del Proveedor

Instalar la herramienta CLI segun el proveedor elegido. Los comandos estan en `MIGRACION_VPS.md` seccion "Opciones de VPS".

**Hetzner:**
```bash
curl -sSLO https://github.com/hetznercloud/cli/releases/latest/download/hcloud-linux-amd64.tar.gz
sudo tar -C /usr/local/bin --no-same-owner -xzf hcloud-linux-amd64.tar.gz hcloud
```

**DigitalOcean:**
```bash
snap install doctl || (curl -sL https://github.com/digitalocean/doctl/releases/latest/download/doctl-linux-amd64.tar.gz | tar -xzv && sudo mv doctl /usr/local/bin/)
```

**Contabo:**
```bash
# Descargar desde https://github.com/contabo/cntb/releases
```

Autenticar la CLI con el token/credenciales proporcionados por el usuario.

### PASO 5: Crear el VPS

Ejecutar el **Paso 1** del plan en `MIGRACION_VPS.md`:

1. Generar un par de claves SSH si no existe una
2. Registrar la clave publica en el proveedor
3. Crear el servidor con:
   - **OS**: Ubuntu 24.04 LTS
   - **Region**: La mas cercana a Colombia/LATAM (ash para Hetzner, nyc1 para DO, US East para Contabo)
   - **Plan**: El especificado en `MIGRACION_VPS.md`
4. Esperar a que el servidor este listo
5. Obtener la IP publica del servidor

### PASO 6: Configurar el Servidor

Conectar via SSH y ejecutar el **Paso 2** del plan en `MIGRACION_VPS.md`:

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

# Crear usuario deploy
adduser --disabled-password --gecos "" deploy
usermod -aG docker deploy
mkdir -p /home/deploy/.ssh
cp ~/.ssh/authorized_keys /home/deploy/.ssh/
chown -R deploy:deploy /home/deploy/.ssh
```

### PASO 7: Configurar Base de Datos

Segun la decision del Paso 3:

**Si Opcion A (TiDB Cloud):** No requiere accion adicional. Usar las variables de entorno de TiDB que estan en `MIGRACION_VPS.md` Opcion 3A. Solicitar al usuario las credenciales de TiDB si no las tiene.

**Si Opcion B (MySQL local):** Ejecutar el **Paso 3, Opcion 3B** del plan:
1. Levantar contenedor MySQL 8.0
2. Generar passwords seguros
3. Solicitar credenciales de TiDB al usuario para exportar datos
4. Hacer mysqldump desde TiDB e importar en MySQL local

### PASO 8: Desplegar Backend con Docker

Ejecutar el **Paso 4** del plan en `MIGRACION_VPS.md`:

1. Clonar el repositorio en `/opt/AgriFlor`
2. Crear el archivo `.env` de produccion con las variables correctas segun la opcion de BD elegida
3. Solicitar al usuario los valores de `APP_KEY` y `JWT_SECRET` (estan en GitHub Secrets o Render Dashboard)
4. Construir la imagen Docker
5. Ejecutar el contenedor

### PASO 9: Configurar Nginx + SSL

Ejecutar el **Paso 5** del plan en `MIGRACION_VPS.md`:

1. Crear configuracion de Nginx como reverse proxy
2. Preguntar al usuario si tiene un dominio:
   - **Si tiene dominio**: Configurar SSL con Let's Encrypt (certbot)
   - **No tiene dominio**: Usar la IP directamente (sin SSL por ahora)

### PASO 10: Actualizar Frontend

Ejecutar el **Paso 6** del plan:

1. En la maquina local, recompilar el frontend con la nueva URL del backend:
   ```bash
   cd frontend
   VITE_API_URL=https://<DOMINIO-O-IP-DEL-VPS>/api npx vite build
   ```
2. Desplegar a Cloudflare Pages (solicitar credenciales de Cloudflare si no estan disponibles)

### PASO 11: Actualizar CORS

Ejecutar el **Paso 7**: Editar `backend/config/cors.php` para agregar la nueva URL/dominio del VPS si es necesario.

### PASO 12: Configurar CI/CD

Ejecutar el **Paso 8** del plan:

1. Actualizar `.github/workflows/deploy.yml` para que el job `deploy-backend` use SSH en lugar de Render API
2. Configurar GitHub Secrets nuevos:
   - `VPS_HOST`: IP publica del VPS
   - `VPS_SSH_KEY`: Clave SSH privada
3. Mantener el job `deploy-frontend` sin cambios

### PASO 13: Verificacion Post-Migracion

Ejecutar TODAS las verificaciones listadas en `MIGRACION_VPS.md` seccion "Verificacion Post-Migracion":

1. **Health check**: `curl https://<DOMINIO>/up` → debe responder 200
2. **Login API**: `POST /api/auth/login` con admin@agriflor.com / admin123
3. **CORS**: Verificar preflight desde https://agriflor.pages.dev
4. **Frontend**: Confirmar que https://agriflor.pages.dev carga datos del nuevo backend
5. **Sin cold start**: Confirmar respuesta inmediata (no hay cold start en VPS)

Si alguna verificacion falla, diagnosticar y corregir antes de continuar.

### PASO 14: Actualizar Documentacion

1. Actualizar `DEPLOY.md` con la nueva arquitectura (VPS en lugar de Render)
2. Actualizar URLs, credenciales (sin secretos en texto plano), y diagramas

### PASO 15: Limpiar Render (Opcional)

Preguntar al usuario si desea eliminar el servicio de Render:

```
¿Todo funciona correctamente en el VPS? ¿Quieres eliminar el servicio de Render?

A) Si, eliminar servicio de Render
B) No, mantenerlo como respaldo por ahora
```

Si dice si, ejecutar el **Paso 9** del plan (DELETE via API de Render).

---

## Manejo de Errores

Durante la migracion pueden ocurrir estos problemas comunes:

| Problema | Solucion |
|----------|----------|
| CLI no se instala | Probar metodo alternativo de instalacion |
| SSH rechaza conexion | Verificar firewall, clave SSH, IP correcta |
| Docker build falla | Revisar Dockerfile, verificar dependencias |
| Nginx no arranca | Verificar config con `nginx -t`, revisar puertos |
| Base de datos no conecta | Verificar credenciales, host, puerto, SSL |
| CORS bloqueado | Verificar `config/cors.php`, reiniciar contenedor |
| Frontend no carga datos | Verificar `VITE_API_URL`, rebuild y redeploy |
| Certbot falla | Verificar que el dominio apunte al VPS (DNS), puerto 80 abierto |

## Output

Al finalizar, mostrar un resumen:

```
=== Migracion Completada ===

Proveedor: [Hetzner/DigitalOcean/Contabo]
IP del VPS: [IP]
URL Backend: https://[DOMINIO-O-IP]
Base de Datos: [TiDB Cloud / MySQL local]
Frontend: https://agriflor.pages.dev (actualizado)
CI/CD: GitHub Actions → SSH al VPS

Verificaciones:
✓ Health check: OK
✓ Login API: OK
✓ CORS: OK
✓ Frontend: OK

Archivos modificados:
- backend/config/cors.php (si aplica)
- .github/workflows/deploy.yml
- DEPLOY.md

GitHub Secrets configurados:
- VPS_HOST
- VPS_SSH_KEY
```

## IMPORTANTE

- Nunca almacenar secretos en texto plano en archivos del repositorio
- Siempre verificar cada paso antes de continuar al siguiente
- Si algo falla, diagnosticar y corregir antes de avanzar
- Mantener al usuario informado del progreso en cada paso
- Usar el archivo `MIGRACION_VPS.md` como referencia principal para todos los comandos y configuraciones
