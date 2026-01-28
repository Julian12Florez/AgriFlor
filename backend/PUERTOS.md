# Mapeo de Puertos - AgriFlor Backend

## Puertos Configurados

| Servicio | Puerto Host | Puerto Contenedor | URL de Acceso | Estado |
|----------|-------------|-------------------|---------------|--------|
| **Nginx (Laravel API)** | 8000 | 80 | http://localhost:8000 | ✅ Libre |
| **MySQL** | 3307 | 3306 | localhost:3307 | ✅ Libre |
| **Redis** | 6380 | 6379 | localhost:6380 | ✅ Libre |
| **phpMyAdmin** | 8083 | 80 | http://localhost:8083 | ✅ Libre |

## Puertos Evitados (Ocupados por otros proyectos)

| Puerto | Servicio | Proyecto | Motivo |
|--------|----------|----------|--------|
| 80 | HTTP | Invima | Puerto HTTP estándar usado por Invima |
| 3306 | MariaDB | Invima | Base de datos del proyecto Invima |
| 6379 | Redis | Invima | Cache del proyecto Invima |
| 8080 | Adminer | Invima | Gestor de BD del proyecto Invima ❌ |
| 8081 | Desconocido | - | Puerto ocupado |
| 8082 | Desconocido | - | Puerto ocupado |
| 9000 | SonarQube | - | Análisis de código (detenido) |

## Cambios Realizados

### ✅ Puerto phpMyAdmin: 8080 → 8083

**Razón:** El puerto 8080 está ocupado por Adminer del proyecto Invima.

**Archivos Actualizados:**
- ✅ `docker-compose.yml` (línea 84)
- ✅ `README.md` (tabla de servicios y credenciales)
- ✅ `install.sh` (mensaje de instalación completada)

## Validación de Puertos

### Comando para verificar puertos en uso:

```bash
# Ver todos los contenedores Docker activos
docker ps

# Ver puertos específicos
ss -tuln | grep -E ':(8000|8083|3307|6380)'

# Ver qué está usando un puerto específico
sudo lsof -i :8083
```

## Acceso a Servicios

### 1. API Laravel
```bash
curl http://localhost:8000
```

### 2. MySQL
```bash
# Desde el host
mysql -h 127.0.0.1 -P 3307 -u agriflor -psecret agriflor

# Desde otro contenedor
mysql -h agriflor-mysql -P 3306 -u agriflor -psecret agriflor
```

### 3. phpMyAdmin
```
URL: http://localhost:8083
Usuario: agriflor
Password: secret
```

### 4. Redis
```bash
# Desde el host
redis-cli -h 127.0.0.1 -p 6380

# Desde otro contenedor
redis-cli -h agriflor-redis -p 6379
```

## Configuración de Firewall (Opcional)

Si tienes firewall activo y necesitas acceso externo:

```bash
# UFW (Ubuntu/Debian)
sudo ufw allow 8000/tcp comment 'AgriFlor API'
sudo ufw allow 8083/tcp comment 'AgriFlor phpMyAdmin'

# Firewalld (CentOS/RHEL)
sudo firewall-cmd --permanent --add-port=8000/tcp
sudo firewall-cmd --permanent --add-port=8083/tcp
sudo firewall-cmd --reload
```

## Notas de Seguridad

⚠️ **IMPORTANTE:**

1. **phpMyAdmin (8083):** Solo para desarrollo. NO exponer en producción.
2. **MySQL (3307):** Cambiar contraseña por defecto en producción.
3. **Redis (6380):** Configurar autenticación en producción.

## Red Docker

**Nombre de red:** `agriflor`
**Driver:** bridge

Todos los contenedores están en la misma red y pueden comunicarse entre sí usando sus nombres:
- `agriflor-app` (PHP-FPM)
- `agriflor-nginx` (Nginx)
- `agriflor-mysql` (MySQL)
- `agriflor-redis` (Redis)
- `agriflor-phpmyadmin` (phpMyAdmin)

## Troubleshooting

### Error: "Port already in use"

```bash
# Identificar qué está usando el puerto
sudo lsof -i :PUERTO

# Detener el servicio conflictivo o cambiar el puerto en docker-compose.yml
```

### Cambiar un puerto

1. Editar `docker-compose.yml`
2. Cambiar la línea `ports: - "PUERTO_HOST:PUERTO_CONTENEDOR"`
3. Reiniciar contenedores:
```bash
docker-compose down
docker-compose up -d
```

---

**Última actualización:** 2025-11-14
**Verificado por:** Claude Code
