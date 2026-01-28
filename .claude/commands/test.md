# /test - Agente de Pruebas Funcionales Completas

Ejecuta pruebas funcionales reales contra la API, simulando exactamente las peticiones que hace el frontend.

## Uso con Lenguaje Natural

```
/test                                       # Prueba todo el sistema
/test "autenticacion"                       # Solo auth: login, logout, permisos
/test "el flujo completo de compras"        # Crear, aprobar, recibir, verificar stock
/test "transferencias entre bodegas"        # Transferencias + verificar stock
/test "que el inventario funcione bien"     # CRUD inventario + movimientos
/test "crear un producto y usarlo"          # Producto + agregarlo a inventario
/test "permisos de usuario admin"           # Verificar roles y permisos
/test "todo lo que se corrigio"             # Lee CORRECCIONES_APLICADAS.md y prueba eso
```

El parametro puede ser:
- Vacio: prueba todo el sistema
- Un modulo: `"inventario"`, `"compras"`
- Un flujo: `"el flujo de transferencias"`
- Una funcionalidad: `"login y permisos"`
- Referencia a correcciones: `"lo que se corrigio"`

## Prerequisitos

1. **Backend corriendo**: `php artisan serve` (puerto 8000)
2. **Base de datos disponible**: Con datos de prueba
3. **Usuario de prueba**: admin@test.com / password (o el configurado)

## Instrucciones para Claude

### PASO 0: Verificar Entorno

Antes de cualquier prueba:

```bash
# 1. Verificar que el backend responde
curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/api/health || \
curl -s -o /dev/null -w "%{http_code}" http://localhost:8000

# Si no responde, informar al usuario:
# "El backend no esta corriendo. Ejecuta: cd backend && php artisan serve"
```

```bash
# 2. Obtener la URL base del frontend
cat frontend/src/services/api.ts | grep -i "baseURL\|base_url\|API_URL"
```

### PASO 1: Interpretar Parametro

| Si el usuario dice... | Probar... |
|-----------------------|-----------|
| (vacio) | TODOS los modulos |
| "auth" o "login" o "permisos" | Autenticacion completa |
| "productos" | CRUD productos + relaciones |
| "inventario" o "stock" | Movimientos, entradas, salidas |
| "compras" | Flujo completo de purchase |
| "transferencias" | Crear, completar, verificar stock |
| "ubicaciones" | CRUD locations |
| "proveedores" | CRUD suppliers |
| "lo que se corrigio" | Leer CORRECCIONES_APLICADAS.md |

### PASO 2: Leer Servicios del Frontend

Para entender EXACTAMENTE que datos envia el frontend:

```bash
# Leer servicios
cat frontend/src/services/api.ts
cat frontend/src/services/*.ts

# Leer types para estructura de datos
cat frontend/src/types/*.ts
```

### PASO 3: Autenticacion

```bash
# Variables de entorno para las pruebas
API_URL="http://localhost:8000/api"
EMAIL="admin@test.com"
PASSWORD="password"

# Login
echo "=== TEST AUTH-001: Login ==="
RESPONSE=$(curl -s -X POST "$API_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d "{\"email\": \"$EMAIL\", \"password\": \"$PASSWORD\"}")

echo "$RESPONSE"

# Extraer token
TOKEN=$(echo $RESPONSE | grep -o '"token":"[^"]*"' | cut -d'"' -f4)

if [ -n "$TOKEN" ]; then
  echo "PASS: Login exitoso, token obtenido"
else
  echo "FAIL: No se obtuvo token"
  exit 1
fi
```

### PASO 4: Ejecutar Pruebas por Modulo

#### MODULO: Auth
```bash
# AUTH-001: Login valido
curl -X POST "$API_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@test.com", "password": "password"}'
# Esperado: 200 + token

# AUTH-002: Login invalido
curl -X POST "$API_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@test.com", "password": "wrongpassword"}'
# Esperado: 401

# AUTH-003: Obtener usuario actual
curl -X GET "$API_URL/auth/user" \
  -H "Authorization: Bearer $TOKEN"
# Esperado: 200 + datos usuario

# AUTH-004: Acceso sin token
curl -X GET "$API_URL/products"
# Esperado: 401

# AUTH-005: Logout
curl -X POST "$API_URL/auth/logout" \
  -H "Authorization: Bearer $TOKEN"
# Esperado: 200
```

#### MODULO: Products
```bash
# PROD-001: Listar productos
curl -X GET "$API_URL/products" \
  -H "Authorization: Bearer $TOKEN"
# Esperado: 200 + array

# PROD-002: Crear producto (datos como el frontend)
curl -X POST "$API_URL/products" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Fertilizante Test",
    "code": "FERT-TEST-001",
    "brand_id": 1,
    "base_unit": "kg",
    "iva": 19,
    "description": "Producto de prueba automatizada"
  }'
# Esperado: 201 + producto creado

# PROD-003: Obtener producto
curl -X GET "$API_URL/products/1" \
  -H "Authorization: Bearer $TOKEN"
# Esperado: 200 + producto con relaciones

# PROD-004: Actualizar producto
curl -X PUT "$API_URL/products/1" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "Fertilizante Test Actualizado"}'
# Esperado: 200

# PROD-005: Validacion - nombre vacio
curl -X POST "$API_URL/products" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "", "code": "TEST"}'
# Esperado: 422 con errores de validacion
```

#### MODULO: Inventory
```bash
# INV-001: Obtener inventario completo
curl -X GET "$API_URL/inventory" \
  -H "Authorization: Bearer $TOKEN"
# Esperado: 200 + array con stock

# INV-002: Stock por ubicacion
curl -X GET "$API_URL/inventory?location_id=1" \
  -H "Authorization: Bearer $TOKEN"
# Esperado: 200 + filtrado por ubicacion

# INV-003: Stock de un producto especifico
curl -X GET "$API_URL/inventory/product/1" \
  -H "Authorization: Bearer $TOKEN"
# Esperado: 200 + stock del producto en todas las ubicaciones
```

#### MODULO: Purchases (Flujo Completo)
```bash
# PUR-001: Listar compras
curl -X GET "$API_URL/purchases" \
  -H "Authorization: Bearer $TOKEN"

# PUR-002: Crear orden de compra
PURCHASE_RESPONSE=$(curl -s -X POST "$API_URL/purchases" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "supplier_id": 1,
    "origin_location_id": 1,
    "notes": "Compra de prueba automatizada",
    "items": [
      {
        "product_id": 1,
        "quantity": 100,
        "unit_price": 5000
      }
    ]
  }')
echo "$PURCHASE_RESPONSE"
PURCHASE_ID=$(echo $PURCHASE_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)

# PUR-003: Aprobar compra
curl -X PUT "$API_URL/purchases/$PURCHASE_ID/approve" \
  -H "Authorization: Bearer $TOKEN"
# Esperado: 200 + status: approved

# PUR-004: Recibir compra (parcial o total)
curl -X POST "$API_URL/purchases/$PURCHASE_ID/receive" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "items": [
      {"product_id": 1, "quantity_received": 100, "location_id": 1}
    ]
  }'
# Esperado: 200 + stock actualizado

# PUR-005: Verificar que el stock aumento
curl -X GET "$API_URL/inventory/product/1/location/1" \
  -H "Authorization: Bearer $TOKEN"
# Verificar: quantity aumento en 100
```

#### MODULO: Transfers (Flujo Completo)
```bash
# Primero obtener stock actual
STOCK_ORIGEN=$(curl -s "$API_URL/inventory/product/1/location/1" \
  -H "Authorization: Bearer $TOKEN" | grep -o '"quantity":[0-9]*' | cut -d':' -f2)
echo "Stock origen antes: $STOCK_ORIGEN"

# TRF-001: Crear transferencia
TRANSFER_RESPONSE=$(curl -s -X POST "$API_URL/transfers" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "from_location_id": 1,
    "to_location_id": 2,
    "notes": "Transferencia de prueba",
    "items": [
      {"product_id": 1, "quantity": 25}
    ]
  }')
TRANSFER_ID=$(echo $TRANSFER_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)

# TRF-002: Completar transferencia
curl -X PUT "$API_URL/transfers/$TRANSFER_ID/complete" \
  -H "Authorization: Bearer $TOKEN"

# TRF-003: Verificar stock origen (debe haber disminuido)
STOCK_ORIGEN_NUEVO=$(curl -s "$API_URL/inventory/product/1/location/1" \
  -H "Authorization: Bearer $TOKEN" | grep -o '"quantity":[0-9]*' | cut -d':' -f2)
echo "Stock origen despues: $STOCK_ORIGEN_NUEVO"
# Verificar: STOCK_ORIGEN_NUEVO = STOCK_ORIGEN - 25

# TRF-004: Verificar stock destino (debe haber aumentado)
curl -X GET "$API_URL/inventory/product/1/location/2" \
  -H "Authorization: Bearer $TOKEN"
# Verificar: quantity aumento en 25
```

#### MODULO: Locations
```bash
# LOC-001: Listar ubicaciones
curl -X GET "$API_URL/locations" \
  -H "Authorization: Bearer $TOKEN"

# LOC-002: Crear ubicacion
curl -X POST "$API_URL/locations" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Bodega Test",
    "type": "warehouse",
    "address": "Direccion de prueba"
  }'
```

#### MODULO: Suppliers
```bash
# SUP-001: Listar proveedores
curl -X GET "$API_URL/suppliers" \
  -H "Authorization: Bearer $TOKEN"

# SUP-002: Crear proveedor
curl -X POST "$API_URL/suppliers" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Proveedor Test",
    "nit": "900123456-7",
    "email": "proveedor@test.com",
    "phone": "3001234567"
  }'
```

### PASO 5: Pruebas de Integridad

Verificar que las operaciones mantienen consistencia:

```bash
# INTEGRIDAD-001: Stock despues de compra
echo "=== Verificando integridad de compra ==="
# 1. Obtener stock inicial
# 2. Crear y recibir compra
# 3. Verificar stock = inicial + cantidad recibida

# INTEGRIDAD-002: Stock despues de transferencia
echo "=== Verificando integridad de transferencia ==="
# 1. Stock origen inicial
# 2. Stock destino inicial
# 3. Ejecutar transferencia
# 4. Stock origen = inicial - cantidad
# 5. Stock destino = inicial + cantidad
# 6. Suma total igual (no se creo ni destruyo inventario)

# INTEGRIDAD-003: No permitir stock negativo
echo "=== Verificando que no permite stock negativo ==="
curl -X POST "$API_URL/transfers" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "from_location_id": 1,
    "to_location_id": 2,
    "items": [{"product_id": 1, "quantity": 999999}]
  }'
# Esperado: 422 o 400 con error de stock insuficiente
```

### PASO 6: Pruebas de Validacion

```bash
# VAL-001: Campos requeridos
curl -X POST "$API_URL/products" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{}'
# Esperado: 422 con lista de campos requeridos

# VAL-002: Tipos incorrectos
curl -X POST "$API_URL/products" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": 123, "quantity": "texto"}'
# Esperado: 422 con errores de tipo

# VAL-003: Foreign key invalida
curl -X POST "$API_URL/products" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "Test", "brand_id": 99999}'
# Esperado: 422 - brand_id no existe
```

### PASO 7: Probar Correcciones Especificas

Si el usuario pidio probar "lo que se corrigio":

1. Leer `CORRECCIONES_APLICADAS.md`
2. Para cada correccion aplicada:
   - Identificar que funcionalidad afecta
   - Diseñar prueba especifica
   - Ejecutar y verificar

### PASO 8: Generar Reporte

Crear `REPORTE_PRUEBAS.md`:

```markdown
# Reporte de Pruebas Funcionales
Fecha: [FECHA]
Entorno: [API_URL]
Usuario de prueba: [EMAIL]

## Resumen Ejecutivo

| Metrica | Valor |
|---------|-------|
| Total pruebas | [X] |
| Exitosas | [X] ([X]%) |
| Fallidas | [X] ([X]%) |
| Omitidas | [X] |

### Estado General: [PASS / FAIL / PARCIAL]

---

## Estado por Modulo

| Modulo | Pruebas | Pass | Fail | Estado |
|--------|---------|------|------|--------|
| Auth | X | X | X | OK/FAIL |
| Products | X | X | X | OK/FAIL |
| Inventory | X | X | X | OK/FAIL |
| Purchases | X | X | X | OK/FAIL |
| Transfers | X | X | X | OK/FAIL |
| Locations | X | X | X | OK/FAIL |
| Suppliers | X | X | X | OK/FAIL |

---

## Pruebas de Integridad

| Verificacion | Resultado | Detalle |
|--------------|-----------|---------|
| Stock post-compra | PASS/FAIL | [detalle] |
| Stock post-transferencia | PASS/FAIL | [detalle] |
| No stock negativo | PASS/FAIL | [detalle] |
| Consistencia total | PASS/FAIL | [detalle] |

---

## Detalle de Pruebas

### PASS: [TEST-ID] [Nombre]
- **Endpoint**: [METHOD] [URL]
- **Request**:
```json
{datos}
```
- **Response**: [HTTP CODE]
```json
{respuesta}
```
- **Verificacion**: [que se verifico]

---

### FAIL: [TEST-ID] [Nombre]
- **Endpoint**: [METHOD] [URL]
- **Request**:
```json
{datos}
```
- **Esperado**: [codigo y estructura]
- **Obtenido**: [codigo]
```json
{respuesta real}
```
- **Error**: [descripcion del problema]
- **Posible causa**: [analisis]
- **Accion requerida**: [que corregir]

---

## Pruebas de Flujos Completos

### Flujo: Compra hasta Stock
| Paso | Accion | Resultado | Verificacion |
|------|--------|-----------|--------------|
| 1 | Crear orden | OK | ID obtenido |
| 2 | Aprobar | OK | Status: approved |
| 3 | Recibir | OK | Items recibidos |
| 4 | Verificar stock | OK | +100 unidades |

### Flujo: Transferencia
| Paso | Accion | Resultado | Verificacion |
|------|--------|-----------|--------------|
| 1 | Stock origen inicial | 500 | - |
| 2 | Stock destino inicial | 100 | - |
| 3 | Crear transferencia | OK | ID obtenido |
| 4 | Completar | OK | Status: completed |
| 5 | Stock origen final | 475 | -25 OK |
| 6 | Stock destino final | 125 | +25 OK |

---

## Cobertura de Endpoints

| Endpoint | Metodo | Probado | Estado |
|----------|--------|---------|--------|
| /auth/login | POST | Si | PASS |
| /auth/logout | POST | Si | PASS |
| /products | GET | Si | PASS |
| /products | POST | Si | PASS |
| /products/:id | GET | Si | PASS |
| /products/:id | PUT | Si | FAIL |
| ... | ... | ... | ... |

---

## Problemas Encontrados

### [ISSUE-001] [Titulo]
- **Prueba relacionada**: [TEST-ID]
- **Severidad**: CRITICA | ALTA | MEDIA | BAJA
- **Descripcion**: [que fallo]
- **Impacto**: [que funcionalidad afecta]
- **Recomendacion**: Ejecutar `/analyze` y `/fix`

---

## Recomendaciones

1. [Accion prioritaria basada en fallos]
2. [Segunda accion]
3. [Tercera accion]

---

## Proximos Pasos

1. Si hay fallos: `/analyze [modulo con fallo]` → `/fix`
2. Re-ejecutar: `/test [modulo corregido]`
3. Si todo pasa: Sistema listo para uso
```

### FORMATO DE CADA PRUEBA

```bash
echo "=== TEST [ID]: [Nombre] ==="
START_TIME=$(date +%s%N)

RESPONSE=$(curl -s -w "\n%{http_code}" -X [METHOD] "$API_URL/[endpoint]" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '[datos]')

HTTP_CODE=$(echo "$RESPONSE" | tail -1)
BODY=$(echo "$RESPONSE" | sed '$d')

END_TIME=$(date +%s%N)
DURATION=$(( ($END_TIME - $START_TIME) / 1000000 ))ms

if [ "$HTTP_CODE" == "[codigo_esperado]" ]; then
  echo "PASS ($DURATION)"
  # Verificaciones adicionales del body si es necesario
else
  echo "FAIL: Esperado [esperado], obtenido $HTTP_CODE"
  echo "Response: $BODY"
fi
```

## Output

1. Pruebas ejecutadas contra la API real
2. Verificacion de integridad de datos
3. Archivo `REPORTE_PRUEBAS.md` con resultados completos
