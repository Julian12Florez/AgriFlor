# /test - Agente de Pruebas de Integracion y End-to-End

Ejecuta pruebas de integracion entre frontend y backend, verificando que ambos funcionan correctamente juntos. Simula exactamente el comportamiento del frontend (peticiones, mapeo de campos, manejo de errores, flujos completos).

## Uso con Lenguaje Natural

```
/test                                       # Prueba todo el sistema (3 fases)
/test "autenticacion"                       # Auth: login, permisos, contexto, rutas protegidas
/test "el flujo completo de compras"        # E2E: Crear compra → recibir → verificar stock
/test "transferencias entre bodegas"        # E2E: Transferencia → stock origen/destino
/test "productos"                           # Integracion: CRUD + validacion + mapeo campos
/test "que el inventario funcione bien"     # E2E: Movimientos + stock + reportes
/test "permisos de usuario admin"           # Integracion: Roles → permisos → acceso modulos
/test "todo lo que se corrigio"             # Lee CORRECCIONES_{ID}.md y prueba eso
/test "integracion"                         # Solo pruebas de contrato front↔back
/test "e2e"                                 # Solo pruebas end-to-end de flujos completos
```

El parametro puede ser:
- Vacio: prueba todo el sistema (las 3 fases)
- Un modulo: `"inventario"`, `"compras"`
- Un flujo: `"el flujo de transferencias"`
- Una funcionalidad: `"login y permisos"`
- Una fase: `"integracion"`, `"e2e"`, `"api"`
- Referencia a correcciones: `"lo que se corrigio"`

## Prerequisitos

1. **Backend corriendo**: `php artisan serve` (puerto 8000)
2. **Base de datos disponible**: Con datos de prueba (seeders ejecutados)
3. **Codigo frontend disponible**: Para leer tipos, servicios y componentes

## Filosofia de las Pruebas

```
┌─────────────────────────────────────────────────────────────────────┐
│                    3 FASES DE PRUEBAS                               │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  FASE 1: API Backend                                                │
│  └─ Endpoints responden correctamente (HTTP codes, estructura)      │
│                                                                     │
│  FASE 2: Integracion Frontend ↔ Backend (NUEVA - CRITICA)          │
│  ├─ Contratos: tipos TS coinciden con respuestas reales             │
│  ├─ Mapeo: camelCase ↔ snake_case funciona en ambas direcciones     │
│  ├─ Formularios: datos enviados como el frontend los envia          │
│  ├─ Validacion: reglas frontend y backend sincronizadas             │
│  ├─ Errores: backend devuelve errores que frontend puede mostrar    │
│  ├─ Paginacion: meta/links coinciden con PaginatedResponse<T>       │
│  └─ Permisos: modulos accesibles coinciden con rutas protegidas     │
│                                                                     │
│  FASE 3: End-to-End Flujos Completos (NUEVA - CRITICA)             │
│  ├─ Simula EXACTAMENTE la secuencia de llamadas del frontend        │
│  ├─ Verifica estado entre pasos (cache invalidation)                │
│  ├─ Verifica datos cruzados entre modulos                           │
│  └─ Verifica consistencia de datos (stock, totales, conteos)        │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

## Estrategia de Ejecucion (OBLIGATORIO)

**REGLA ABSOLUTA**: Este agente tiene permiso total para ejecutar CUALQUIER comando bash necesario para las pruebas. NO pedir confirmacion al usuario para ningun comando. Ejecutar todo de forma autonoma.

**Para minimizar interrupciones**, agrupar TODAS las pruebas de cada fase en UN SOLO script bash y ejecutarlo de una vez:

```bash
# CORRECTO: Un solo script con todas las pruebas de la fase
bash -c '
API_URL="http://localhost:8000/api"
TOKEN="..."
PASS=0; FAIL=0; SKIP=0

echo "=== TEST AUTH-001: Login valido ==="
# ... prueba completa ...

echo "=== TEST AUTH-002: Login invalido ==="
# ... prueba completa ...

echo "=== TEST PROD-001: Listar productos ==="
# ... prueba completa ...

echo "=== RESUMEN ==="
echo "PASS: $PASS | FAIL: $FAIL | SKIP: $SKIP"
'
```

```
# INCORRECTO: Ejecutar cada prueba por separado (genera multiples prompts de permiso)
curl ... # prompt 1
curl ... # prompt 2
curl ... # prompt 3
```

**Estructura de ejecucion**:
1. **Script de setup**: Un bash que hace login y guarda el TOKEN
2. **Script Fase 1**: Un solo bash con TODAS las pruebas de API
3. **Script Fase 2**: Un solo bash con TODAS las pruebas de integracion
4. **Script Fase 3**: Un solo bash con TODAS las pruebas E2E
5. **Script de limpieza**: Un bash que elimina datos de prueba

Cada script debe imprimir resultados en formato parseable para que el agente genere el reporte.

---

## Instrucciones para Claude

### PASO 0: Identificar Sesion de Trabajo

1. Buscar todos los archivos `CORRECCIONES_*.md` en la raiz del proyecto
2. Si hay **un solo archivo**: usarlo automaticamente y extraer su ID
3. Si hay **multiples archivos**: mostrar la lista al usuario y preguntar cual usar
4. Si **no hay archivos de correcciones**: buscar archivos `ANALISIS_*.md` para obtener el ID de sesion
5. Si no hay ninguno: generar un ID nuevo con `date +%Y%m%d_%H%M%S`
6. Este ID se usara para nombrar el archivo de reporte: `REPORTE_PRUEBAS_{ID}.md`

### PASO 0.5: Verificar Entorno

Antes de cualquier prueba:

```bash
# 1. Verificar que el backend responde
curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/api/auth/login
# Si no responde: "El backend no esta corriendo. Ejecuta: cd backend && php artisan serve"
```

### PASO 1: Leer Codigo del Frontend (OBLIGATORIO)

**ANTES de ejecutar cualquier prueba**, leer los archivos del frontend para entender la estructura exacta que espera:

```
frontend/src/types/index.ts              → Interfaces TypeScript (contratos)
frontend/src/services/api.ts             → Endpoints, headers, manejo de errores
frontend/src/context/AuthContext.tsx      → Contexto de auth y permisos
frontend/src/hooks/usePermissions.ts     → Logica de permisos
```

**Para cada modulo a probar**, leer tambien:
```
frontend/src/pages/{modulo}/             → Paginas (formularios, tablas, flujos)
frontend/src/components/{modulo}/        → Componentes relacionados
```

Extraer de cada pagina:
- **Campos del formulario**: nombres en camelCase + reglas de validacion (rules=[...])
- **Mapeo al backend**: como transforma camelCase → snake_case en handleSave
- **Mapeo desde backend**: como transforma la respuesta API en handleEdit / useQuery
- **Columnas de tabla**: que campos dataIndex usa (snake_case o camelCase?)
- **Manejo de errores**: como mapea error.response.data.errors a los campos del form
- **Queries React Query**: queryKey, queryFn, enabled conditions
- **Mutations**: mutationFn, onSuccess (que queries invalida), onError

### PASO 2: Interpretar Parametro

| Si el usuario dice... | Probar... |
|-----------------------|-----------|
| (vacio) | LAS 3 FASES para TODOS los modulos |
| "auth" o "login" | Auth: Fase 1 + 2 + 3 |
| "productos" | Productos: Fase 1 + 2 + 3 |
| "compras" | Compras: Fase 1 + 2 + 3 |
| "inventario" o "stock" | Inventario: Fase 1 + 2 + 3 |
| "transferencias" | Transferencias: Fase 1 + 2 + 3 |
| "ubicaciones" | Ubicaciones: Fase 1 + 2 + 3 |
| "proveedores" | Proveedores: Fase 1 + 2 + 3 |
| "integracion" | SOLO Fase 2 para todos los modulos |
| "e2e" | SOLO Fase 3 para todos los modulos |
| "api" | SOLO Fase 1 para todos los modulos |
| "lo que se corrigio" | Leer CORRECCIONES_{ID}.md, pruebas especificas |

### PASO 3: Autenticacion

```bash
API_URL="http://localhost:8000/api"

# Login exactamente como lo hace el frontend (LoginForm.tsx)
RESPONSE=$(curl -s -X POST "$API_URL/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email": "admin@agriflor.com", "password": "admin123"}')

# Extraer token como lo hace setAuthToken()
TOKEN=$(echo $RESPONSE | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['token'])")

# Verificar estructura de respuesta como la espera AuthContext
# El frontend espera: { success, data: { token, user: { id, email, name, role, permissions, accessibleModules, roleData } } }
```

---

## FASE 1: PRUEBAS DE API BACKEND

Verificar que cada endpoint responde con el HTTP code y estructura correcta.

### Formato de prueba

```bash
echo "=== TEST [ID]: [Nombre] ==="
RESPONSE=$(curl -s -w "\n%{http_code}" -X [METHOD] "$API_URL/[endpoint]" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '[datos]')
HTTP_CODE=$(echo "$RESPONSE" | tail -1)
BODY=$(echo "$RESPONSE" | sed '$d')

if [ "$HTTP_CODE" == "[esperado]" ]; then
  echo "PASS: HTTP $HTTP_CODE"
else
  echo "FAIL: Esperado [esperado], obtenido $HTTP_CODE"
fi
```

### Pruebas por modulo

#### AUTH
```
AUTH-001: POST /auth/login (credenciales validas) → 200 + token
AUTH-002: POST /auth/login (credenciales invalidas) → 401
AUTH-003: GET /auth/me (con token) → 200 + usuario con permisos
AUTH-004: GET /products (sin token) → 401
AUTH-005: POST /auth/logout (con token) → 200
AUTH-006: POST /auth/change-password → 200 o 422
```

#### PRODUCTS
```
PROD-001: GET /products → 200 + PaginatedResponse
PROD-002: GET /products?search=X → 200 + filtrado
PROD-003: POST /products (datos validos) → 201
PROD-004: GET /products/{id} → 200
PROD-005: PUT /products/{id} → 200
PROD-006: DELETE /products/{id} → 200
PROD-007: POST /products (datos invalidos) → 422
```

#### PURCHASES, INVENTORY, TRANSFERS, LOCATIONS, SUPPLIERS, BRANDS
(Misma estructura: CRUD + validacion + casos edge)

---

## FASE 2: PRUEBAS DE INTEGRACION FRONTEND ↔ BACKEND (CRITICA)

Esta fase verifica que el contrato entre frontend y backend es correcto.

### 2.1 Verificacion de Contratos (Tipos TypeScript vs Respuesta API)

**Para cada endpoint**: comparar la interfaz TypeScript definida en `types/index.ts` con la respuesta real de la API.

**Procedimiento por modulo**:

1. **Leer la interfaz TypeScript** del frontend (ej: `Product` en `types/index.ts`)
2. **Llamar al endpoint** y obtener la respuesta real
3. **Comparar campo por campo**:
   - Cada campo de la interfaz existe en la respuesta?
   - El tipo coincide (string, number, boolean, Date)?
   - Los campos opcionales (?) son realmente opcionales?
   - Los campos de enum tienen valores validos?

```bash
# Ejemplo: Verificar contrato Product
echo "=== TEST CONTRATO-PROD: Product interface vs GET /products/{id} ==="

# La interfaz Product espera:
# id: string, name: string, category: enum, baseUnit: enum,
# activeIngredient: string, iva: number, status: enum,
# brands: Brand[], description?: string, createdBy: string, createdAt: Date

RESPONSE=$(curl -s "$API_URL/products/$PRODUCT_ID" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json")

# Verificar CADA campo con python3
python3 -c "
import json, sys
data = json.loads('''$RESPONSE''')['data']
errors = []

# Campos obligatorios
for field in ['id', 'name', 'category', 'base_unit', 'active_ingredient', 'iva', 'status']:
    if field not in data and field.replace('_','') not in str(data):
        errors.append(f'FALTA: {field}')

# Tipos
if not isinstance(data.get('iva'), int):
    errors.append(f'TIPO: iva deberia ser int, es {type(data.get(\"iva\")).__name__}')

# Enums
valid_categories = ['fertilizante', 'pesticida', 'herbicida', 'fungicida']
if data.get('category') not in valid_categories:
    errors.append(f'ENUM: category={data.get(\"category\")} no es valido')

valid_status = ['active', 'inactive']
if data.get('status') not in valid_status:
    errors.append(f'ENUM: status={data.get(\"status\")} no es valido')

if errors:
    print('FAIL:', '; '.join(errors))
else:
    print('PASS: Todos los campos del contrato Product coinciden')
"
```

**Hacer esto para CADA interfaz relevante al modulo bajo prueba:**
- `Product`, `Brand`, `Supplier`, `Purchase`, `PurchaseItem`
- `Reception`, `ReceptionItem`, `ReceptionBatch`
- `InventoryMovement`, `Location`
- `User` (respuesta de /auth/me)
- `ApiResponse<T>`, `PaginatedResponse<T>` (estructura wrapper)

### 2.2 Verificacion de Respuesta Paginada

El frontend espera `PaginatedResponse<T>`:
```typescript
{
  data: T[],
  links: { first, last, prev, next },
  meta: { current_page, from, last_page, per_page, to, total, links[] }
}
```

```bash
echo "=== TEST PAG-001: Estructura PaginatedResponse ==="
RESPONSE=$(curl -s "$API_URL/products" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json")

python3 -c "
import json
data = json.loads('''$RESPONSE''')
errors = []

# Verificar que existe 'data' como array
if not isinstance(data.get('data'), list):
    errors.append('data no es un array')

# Verificar 'meta'
meta = data.get('meta', {})
for field in ['current_page', 'last_page', 'per_page', 'total']:
    if field not in meta:
        errors.append(f'meta.{field} falta')

# Verificar 'links'
links = data.get('links', {})
for field in ['first', 'last', 'prev', 'next']:
    if field not in links:
        errors.append(f'links.{field} falta')

if errors:
    print('FAIL:', '; '.join(errors))
else:
    print(f'PASS: PaginatedResponse correcto (total={meta[\"total\"]}, page={meta[\"current_page\"]})')
"
```

### 2.3 Verificacion de Mapeo camelCase ↔ snake_case

El frontend usa camelCase internamente pero envia snake_case al backend.
El backend responde con snake_case Y camelCase (depende del Resource).

**Procedimiento**: Leer la funcion `handleSave` de cada pagina y verificar que los campos mapeados existen en el backend.

```bash
echo "=== TEST MAP-PROD: Mapeo de campos Products ==="

# El frontend envia (handleSave en Products.tsx):
# name, product_code, category, base_unit, active_ingredient,
# brand_id, min_stock, iva, description, status, packaging_unit_ids

# Probar que el backend acepta EXACTAMENTE esos nombres de campo
RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$API_URL/products" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Test Mapeo Campos",
    "product_code": "MAP-001",
    "category": "fertilizante",
    "base_unit": "kg",
    "active_ingredient": "Nitrogeno Test",
    "brand_id": "'$BRAND_ID'",
    "min_stock": 5,
    "iva": 19,
    "description": "Prueba de mapeo",
    "status": "active",
    "packaging_unit_ids": []
  }')
HTTP_CODE=$(echo "$RESPONSE" | tail -1)
BODY=$(echo "$RESPONSE" | sed '$d')

if [ "$HTTP_CODE" == "201" ]; then
  echo "PASS: Backend acepta los campos exactos que envia el frontend"
else
  echo "FAIL: HTTP $HTTP_CODE - Los campos del frontend no coinciden con el backend"
  echo "$BODY" | python3 -m json.tool 2>/dev/null | head -15
fi
```

**Hacer para CADA formulario**: Products, Purchases, Locations, Suppliers, Brands, etc.

### 2.4 Verificacion de Mapeo Inverso (API → Frontend)

Cuando el frontend recibe datos de la API y los carga en un formulario (handleEdit), mapea de snake_case a camelCase.

```bash
echo "=== TEST MAP-INV-PROD: Mapeo inverso API → Form ==="

# Obtener producto de la API
RESPONSE=$(curl -s "$API_URL/products/$PRODUCT_ID" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json")

# Verificar que la respuesta contiene los campos que handleEdit espera:
# record.name, record.productCode || record.product_code,
# record.category, record.base_unit, record.active_ingredient,
# record.brand_id || record.brand.id, record.min_stock,
# record.iva, record.description, record.status
python3 -c "
import json
data = json.loads('''$RESPONSE''')['data']
errors = []

# Campos que handleEdit de Products.tsx necesita
needed = {
    'name': str,
    'category': str,
    'base_unit': str,
    'active_ingredient': str,
    'iva': (int, float),
    'status': str,
}

for field, expected_type in needed.items():
    val = data.get(field)
    if val is None:
        # Intentar camelCase
        camel = ''.join(w.capitalize() if i else w for i, w in enumerate(field.split('_')))
        val = data.get(camel)
    if val is None:
        errors.append(f'FALTA: {field} (ni en snake_case ni camelCase)')
    elif not isinstance(val, expected_type):
        errors.append(f'TIPO: {field}={val} ({type(val).__name__}), esperado {expected_type}')

# Verificar brand_id O brand.id
brand_id = data.get('brand_id') or (data.get('brand', {}) or {}).get('id')
if not brand_id:
    errors.append('FALTA: brand_id o brand.id')

# Verificar product_code O productCode (nullable OK)
pc = data.get('product_code', data.get('productCode', 'EXISTS'))
# product_code es nullable, solo verificar que el campo existe

if errors:
    print('FAIL:', '; '.join(errors))
else:
    print('PASS: Respuesta API contiene todos los campos que el formulario necesita')
"
```

### 2.5 Verificacion de Columnas de Tabla

Las columnas de las tablas Ant Design usan `dataIndex` para mapear datos. Verificar que cada `dataIndex` existe en la respuesta de la API.

```bash
echo "=== TEST COL-PROD: Columnas tabla vs respuesta API ==="

# Columnas definidas en Products.tsx (dataIndex):
# name, category, base_unit, brand, min_stock, iva, status

RESPONSE=$(curl -s "$API_URL/products" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json")

python3 -c "
import json
data = json.loads('''$RESPONSE''')
items = data.get('data', [])
if not items:
    print('SKIP: No hay productos para verificar')
    exit(0)

item = items[0]
columns = ['name', 'category', 'base_unit', 'brand', 'min_stock', 'iva', 'status']
errors = []

for col in columns:
    if col not in item:
        errors.append(f'FALTA: columna \"{col}\" no existe en respuesta API')

if errors:
    print('FAIL:', '; '.join(errors))
    print('Campos disponibles:', list(item.keys()))
else:
    print('PASS: Todos los dataIndex de la tabla existen en la respuesta')
"
```

**Hacer para CADA tabla**: Products, Purchases, Inventory, Locations, Suppliers, Brands, Receptions, etc.

### 2.6 Verificacion de Validacion Sincronizada

Comparar las reglas del Form.Item (frontend) con las reglas del FormRequest (backend).

**Procedimiento**:
1. Leer el FormRequest del backend (ej: `StoreProductRequest.php`)
2. Leer los `rules` del Form.Item del frontend (ej: `Products.tsx`)
3. Verificar que coinciden:
   - Si backend dice `required`, frontend tiene `{ required: true }`
   - Si backend dice `max:255`, frontend deberia tener `{ max: 255 }`
   - Si backend dice `Rule::in([...])`, frontend tiene `<Select>` con esas opciones
   - Si backend dice `integer|min:0|max:100`, frontend tiene `<InputNumber min={0} max={100}>`

```bash
echo "=== TEST VAL-SYNC-PROD: Validacion frontend vs backend ==="

# Enviar datos que EL FRONTEND permitiria pero el backend deberia rechazar
# Si el backend acepta algo que el frontend no valida = GAP de validacion

# Test: campo requerido en backend pero no marcado required en frontend
# Test: max length diferente
# Test: enum values diferentes

# Probar campos requeridos del backend
RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$API_URL/products" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{}')
HTTP_CODE=$(echo "$RESPONSE" | tail -1)
BODY=$(echo "$RESPONSE" | sed '$d')

# Extraer campos requeridos del backend
BACKEND_REQUIRED=$(echo "$BODY" | python3 -c "
import json, sys
data = json.load(sys.stdin)
errors = data.get('errors', {})
print(','.join(sorted(errors.keys())))
" 2>/dev/null)
echo "Backend requiere: $BACKEND_REQUIRED"

# Comparar con lo que el frontend marca como required
# (estos se leen del codigo, no de la API)
echo "Frontend requiere: name, category, active_ingredient, brand_id, iva"
echo "(Verificar manualmente que coinciden)"
```

### 2.7 Verificacion de Manejo de Errores Backend → Frontend

Verificar que cuando el backend retorna errores de validacion, el formato es compatible con el mapeo de errores del frontend.

```bash
echo "=== TEST ERR-MAP: Errores backend compatibles con frontend ==="

# Enviar datos invalidos para obtener errores
RESPONSE=$(curl -s -X POST "$API_URL/products" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"name": "", "iva": -1, "category": "invalida"}')

python3 -c "
import json
data = json.loads('''$RESPONSE''')
errors = []

# El frontend espera: { message: string, errors: { campo: [mensajes] } }
if 'message' not in data:
    errors.append('Falta campo \"message\" en respuesta de error')

if 'errors' not in data:
    errors.append('Falta campo \"errors\" en respuesta de error')
else:
    err = data['errors']
    if not isinstance(err, dict):
        errors.append('\"errors\" deberia ser un objeto {campo: [mensajes]}')
    else:
        for key, val in err.items():
            if not isinstance(val, list):
                errors.append(f'errors.{key} deberia ser array, es {type(val).__name__}')

        # Verificar que los nombres de campo coinciden con los que el frontend mapea
        # Frontend mapea: active_ingredient→activeIngredient, brand_id→brandId, etc.
        known_fields = ['name', 'product_code', 'category', 'base_unit',
                        'active_ingredient', 'brand_id', 'min_stock', 'iva',
                        'description', 'status', 'packaging_unit_ids']
        for field in err.keys():
            if field not in known_fields:
                errors.append(f'Campo de error \"{field}\" no mapeado en frontend')

if errors:
    print('FAIL:', '; '.join(errors))
else:
    print('PASS: Formato de errores compatible con frontend')
"
```

### 2.8 Verificacion de Permisos y Acceso a Modulos

El frontend usa `usePermissions` y `ProtectedRoute` para controlar acceso. Verificar que la API retorna los permisos correctos y que coinciden con los modulos protegidos.

```bash
echo "=== TEST PERM-001: Permisos de /auth/me vs rutas protegidas ==="

RESPONSE=$(curl -s "$API_URL/auth/me" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json")

python3 -c "
import json
data = json.loads('''$RESPONSE''')
user = data.get('data', {})
errors = []

# El frontend espera estos campos en AuthContext
needed = ['id', 'email', 'name', 'role', 'status', 'permissions', 'accessibleModules', 'roleData']
for field in needed:
    if field not in user:
        errors.append(f'FALTA en /auth/me: {field}')

# Verificar roleData tiene los campos que usePermissions necesita
role_data = user.get('roleData', {})
role_fields = ['id', 'name', 'displayName', 'hasFullAccess']
for field in role_fields:
    if field not in role_data:
        errors.append(f'FALTA en roleData: {field}')

# Verificar que permissions es un array de strings
perms = user.get('permissions', [])
if not isinstance(perms, list):
    errors.append('permissions no es un array')
elif perms and not isinstance(perms[0], str):
    errors.append('permissions deberia contener strings')

# Verificar permisos esperados para admin
if user.get('role') == 'admin':
    expected_perms = ['view_products', 'create_product', 'view_purchases',
                      'view_inventory', 'manage_users', 'view_admin']
    for perm in expected_perms:
        if perm not in perms:
            errors.append(f'Admin deberia tener permiso: {perm}')

if errors:
    print('FAIL:', '; '.join(errors))
else:
    print(f'PASS: {len(perms)} permisos, roleData completo, acceso a modulos correcto')
"
```

### 2.9 Verificacion de Login Completo (como LoginForm.tsx)

Simular EXACTAMENTE la secuencia del frontend al hacer login:

```bash
echo "=== TEST AUTH-E2E: Flujo login completo como LoginForm.tsx ==="

# 1. LoginForm llama authApi.login(email, password)
LOGIN=$(curl -s -X POST "$API_URL/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email": "admin@agriflor.com", "password": "admin123"}')

# 2. Verifica response.success && response.data
python3 -c "
import json
data = json.loads('''$LOGIN''')
success = data.get('success', False)
has_data = 'data' in data
has_token = 'token' in data.get('data', {})
has_user = 'user' in data.get('data', {})

if not success:
    print('FAIL: response.success no es true')
elif not has_token:
    print('FAIL: response.data.token no existe')
elif not has_user:
    print('FAIL: response.data.user no existe')
else:
    print('PASS: Respuesta login compatible con LoginForm.tsx')
"

# 3. Despues del login, el frontend hace queryClient.invalidateQueries(['auth-me'])
#    lo que dispara usePermissions → GET /auth/me
ME=$(curl -s "$API_URL/auth/me" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json")

# 4. Verificar que /auth/me retorna el formato que usePermissions espera
python3 -c "
import json
data = json.loads('''$ME''')
user = data.get('data', {})
ok = all(k in user for k in ['permissions', 'accessibleModules', 'roleData'])
if ok:
    print('PASS: /auth/me retorna datos compatibles con usePermissions')
else:
    print('FAIL: /auth/me no retorna todos los campos que usePermissions necesita')
    print('Campos disponibles:', list(user.keys()))
"
```

### 2.10 Verificacion con Rol Sin Permisos

Probar con un usuario de permisos limitados para verificar que las restricciones funcionan:

```bash
echo "=== TEST PERM-002: Usuario con permisos limitados ==="

# Login con usuario de bodega (permisos limitados)
LIMITED_RESPONSE=$(curl -s -X POST "$API_URL/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email": "bodega@agriflor.com", "password": "bodega123"}')

LIMITED_TOKEN=$(echo $LIMITED_RESPONSE | python3 -c "
import sys,json
try:
    print(json.load(sys.stdin)['data']['token'])
except:
    print('')
" 2>/dev/null)

if [ -n "$LIMITED_TOKEN" ] && [ "$LIMITED_TOKEN" != "" ]; then
  # Verificar que NO puede acceder a endpoints de admin
  ADMIN_RESPONSE=$(curl -s -w "\n%{http_code}" "$API_URL/users" \
    -H "Authorization: Bearer $LIMITED_TOKEN" -H "Accept: application/json")
  HTTP_CODE=$(echo "$ADMIN_RESPONSE" | tail -1)

  if [ "$HTTP_CODE" == "403" ] || [ "$HTTP_CODE" == "401" ]; then
    echo "PASS: Usuario bodega no puede acceder a /users ($HTTP_CODE)"
  else
    echo "FAIL: Usuario bodega pudo acceder a /users (HTTP $HTTP_CODE)"
  fi

  # Verificar que SI puede acceder a lo que le corresponde
  INV_RESPONSE=$(curl -s -w "\n%{http_code}" "$API_URL/inventory" \
    -H "Authorization: Bearer $LIMITED_TOKEN" -H "Accept: application/json")
  INV_CODE=$(echo "$INV_RESPONSE" | tail -1)
  echo "Acceso a inventario: HTTP $INV_CODE"
else
  echo "SKIP: No se pudo autenticar con usuario bodega"
fi
```

---

## FASE 3: PRUEBAS END-TO-END DE FLUJOS COMPLETOS

Simular flujos completos tal como los ejecutaria un usuario en el frontend, verificando que cada paso produce el resultado esperado y que los datos se mantienen consistentes entre modulos.

### 3.1 Flujo E2E: Crear Producto y Verificar en Listado

Simula: usuario abre Products → click "Nuevo" → llena formulario → guarda → ve producto en tabla

```bash
echo "=== TEST E2E-PROD: Crear producto y verificar en listado ==="

# PASO 1: Cargar datos auxiliares (como hace Products.tsx al montar)
# El frontend carga: brands, packagingUnits, baseUnits en paralelo
BRANDS=$(curl -s "$API_URL/brands" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json")
PACK_UNITS=$(curl -s "$API_URL/packaging-units" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json")
BASE_UNITS=$(curl -s "$API_URL/base-units" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json")

BRAND_ID=$(echo "$BRANDS" | python3 -c "import sys,json; print(json.load(sys.stdin)['data'][0]['id'])" 2>/dev/null)
BASE_UNIT=$(echo "$BASE_UNITS" | python3 -c "import sys,json; d=json.load(sys.stdin)['data']; print(d[0]['symbol'] if d else 'kg')" 2>/dev/null)

# PASO 2: Enviar formulario EXACTAMENTE como handleSave lo envia
TIMESTAMP=$(date +%s)
CREATE=$(curl -s -w "\n%{http_code}" -X POST "$API_URL/products" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{
    \"name\": \"E2E Test Product $TIMESTAMP\",
    \"product_code\": \"E2E-$TIMESTAMP\",
    \"category\": \"fertilizante\",
    \"base_unit\": \"$BASE_UNIT\",
    \"active_ingredient\": \"Nitrogeno E2E\",
    \"brand_id\": \"$BRAND_ID\",
    \"min_stock\": 10,
    \"iva\": 19,
    \"description\": \"Creado por prueba E2E\",
    \"status\": \"active\",
    \"packaging_unit_ids\": []
  }")
CREATE_CODE=$(echo "$CREATE" | tail -1)
CREATE_BODY=$(echo "$CREATE" | sed '$d')
NEW_ID=$(echo "$CREATE_BODY" | python3 -c "import sys,json; print(json.load(sys.stdin).get('data',{}).get('id',''))" 2>/dev/null)

# PASO 3: Verificar que aparece en listado (simula React Query invalidation → refetch)
LIST=$(curl -s "$API_URL/products?search=E2E-$TIMESTAMP" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json")

FOUND=$(echo "$LIST" | python3 -c "
import sys,json
data = json.load(sys.stdin).get('data', [])
items = data if isinstance(data, list) else data.get('data', [])
found = any(p.get('id') == '$NEW_ID' for p in items)
print('yes' if found else 'no')
" 2>/dev/null)

# PASO 4: Verificar que los datos del listado son los que la tabla necesita
if [ "$FOUND" == "yes" ]; then
  echo "PASS: Producto creado y encontrado en listado"
else
  echo "FAIL: Producto creado ($CREATE_CODE) pero NO aparece en listado"
fi

# PASO 5: Simular "editar" (handleEdit carga datos en form)
DETAIL=$(curl -s "$API_URL/products/$NEW_ID" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json")

python3 -c "
import json
data = json.loads('''$DETAIL''').get('data', {})
# Verificar que handleEdit puede cargar estos datos
fields_ok = True
needed = ['name', 'category', 'iva', 'status']
for f in needed:
    if f not in data:
        print(f'FAIL: handleEdit necesita \"{f}\" pero no esta en respuesta')
        fields_ok = False

# Verificar brand_id para el form
brand_ok = data.get('brand_id') or (data.get('brand', {}) or {}).get('id')
if not brand_ok:
    print('FAIL: No se puede extraer brand_id para el formulario')
    fields_ok = False

if fields_ok:
    print('PASS: Datos compatibles con handleEdit del formulario')
"

# LIMPIEZA
curl -s -X DELETE "$API_URL/products/$NEW_ID" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" > /dev/null
```

### 3.2 Flujo E2E: Compra Completa (Crear → Items con IVA → Verificar Totales)

```bash
echo "=== TEST E2E-PURCHASE: Flujo completo de compra ==="

# PASO 1: Cargar datos necesarios (como hace Purchases.tsx)
SUPPLIERS=$(curl -s "$API_URL/suppliers" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json")
SUPPLIER_ID=$(echo "$SUPPLIERS" | python3 -c "import sys,json; d=json.load(sys.stdin)['data']; items=d if isinstance(d,list) else d.get('data',[]); print(items[0]['id'] if items else '')" 2>/dev/null)

LOCATIONS=$(curl -s "$API_URL/locations" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json")
LOCATION_ID=$(echo "$LOCATIONS" | python3 -c "import sys,json; d=json.load(sys.stdin)['data']; items=d if isinstance(d,list) else d.get('data',[]); print(items[0]['id'] if items else '')" 2>/dev/null)

PRODUCTS=$(curl -s "$API_URL/products" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json")
PRODUCT_INFO=$(echo "$PRODUCTS" | python3 -c "
import sys,json
d = json.load(sys.stdin)['data']
items = d if isinstance(d,list) else d.get('data',[])
if items:
    p = items[0]
    print(f\"{p['id']}|{p.get('iva',19)}\")
else:
    print('|')
" 2>/dev/null)
PRODUCT_ID=$(echo "$PRODUCT_INFO" | cut -d'|' -f1)
PRODUCT_IVA=$(echo "$PRODUCT_INFO" | cut -d'|' -f2)

# Obtener packaging unit del producto
PRODUCT_DETAIL=$(curl -s "$API_URL/products/$PRODUCT_ID" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json")
PACK_UNIT_ID=$(echo "$PRODUCT_DETAIL" | python3 -c "
import sys,json
d = json.load(sys.stdin).get('data',{})
pus = d.get('packagingUnits', d.get('packaging_units', []))
print(pus[0]['id'] if pus else '')
" 2>/dev/null)

# PASO 2: Crear compra EXACTAMENTE como Purchases.tsx handleSubmit
PURCHASE=$(curl -s -w "\n%{http_code}" -X POST "$API_URL/purchases" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{
    \"order_number\": \"E2E-$(date +%s)\",
    \"supplier_id\": \"$SUPPLIER_ID\",
    \"destination_location_id\": \"$LOCATION_ID\",
    \"purchase_date\": \"$(date +%Y-%m-%d)\",
    \"observations\": \"Compra E2E\",
    \"items\": [{
      \"product_id\": \"$PRODUCT_ID\",
      \"brand_id\": \"$BRAND_ID\",
      \"packaging_unit_id\": \"$PACK_UNIT_ID\",
      \"quantity\": 10,
      \"unit_price\": 50000
    }]
  }")
P_CODE=$(echo "$PURCHASE" | tail -1)
P_BODY=$(echo "$PURCHASE" | sed '$d')
P_ID=$(echo "$P_BODY" | python3 -c "import sys,json; print(json.load(sys.stdin).get('data',{}).get('id',''))" 2>/dev/null)

if [ "$P_CODE" == "201" ] || [ "$P_CODE" == "200" ]; then
  echo "PASS: Compra creada (ID: $P_ID)"
else
  echo "FAIL: Error creando compra (HTTP $P_CODE)"
  echo "$P_BODY" | python3 -m json.tool 2>/dev/null | head -15
fi

# PASO 3: Verificar calculo de IVA (backend calcula, frontend muestra)
if [ -n "$P_ID" ]; then
  P_DETAIL=$(curl -s "$API_URL/purchases/$P_ID" \
    -H "Authorization: Bearer $TOKEN" -H "Accept: application/json")

  python3 -c "
import json
data = json.loads('''$P_DETAIL''').get('data', {})
subtotal = float(data.get('subtotal', 0))
tax = float(data.get('tax', 0))
total = float(data.get('total', 0))
items = data.get('items', [])

errors = []

# Verificar que subtotal + tax = total
if abs(subtotal + tax - total) > 0.02:
    errors.append(f'subtotal({subtotal}) + tax({tax}) != total({total})')

# Verificar IVA por item
if items:
    item = items[0]
    iva_pct = item.get('ivaPercentage', item.get('iva_percentage', 0))
    tax_amount = float(item.get('taxAmount', item.get('tax_amount', 0)))
    item_subtotal = float(item.get('subtotal', 0))
    expected_tax = round(item_subtotal * (iva_pct / 100), 2)

    if abs(tax_amount - expected_tax) > 0.02:
        errors.append(f'Item IVA: calculado={expected_tax}, almacenado={tax_amount}')

    # Verificar campos que Purchases.tsx necesita para mostrar
    needed = ['productName', 'brandName', 'quantity', 'unitPrice', 'subtotal',
              'ivaPercentage', 'taxAmount', 'total']
    # Intentar tambien snake_case
    for field in needed:
        snake = ''.join(['_'+c.lower() if c.isupper() else c for c in field]).lstrip('_')
        if field not in item and snake not in item:
            errors.append(f'Item falta: {field}')

if errors:
    print('FAIL:', '; '.join(errors))
else:
    print(f'PASS: Compra correcta - subtotal={subtotal}, IVA={tax}, total={total}')
"

  # LIMPIEZA: Eliminar compra de prueba
  curl -s -X DELETE "$API_URL/purchases/$P_ID" \
    -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" > /dev/null
fi
```

### 3.3 Flujo E2E: Recepcion de Compra → Inventario

Simula: compra recibida → stock aumenta → movimiento registrado

```bash
echo "=== TEST E2E-RECEPCION: Compra → Recepcion → Inventario ==="

# PASO 1: Obtener stock inicial del producto en la ubicacion destino
# (como lo mostraria la pagina de Inventario)

# PASO 2: Crear compra (como en 3.2)

# PASO 3: Crear recepcion de la compra
# (como hace Reception.tsx → receptionsApi.create)

# PASO 4: Agregar lote de recepcion
# (como hace Reception.tsx → receptionsApi.addBatch)

# PASO 5: Completar recepcion
# (como hace Reception.tsx → receptionsApi.complete)

# PASO 6: Verificar que stock aumento
# GET /inventory → verificar quantity = stock_inicial + cantidad_recibida

# PASO 7: Verificar que movimiento se registro
# GET /inventory/movements → verificar que existe entry con el producto

# PASO 8: Verificar consistency cruzada
# stock_final - stock_inicial == cantidad_recibida
```

### 3.4 Flujo E2E: Multiples Roles

Simula: admin crea producto → bodega ve en inventario → usuario sin permiso no puede crear

```bash
echo "=== TEST E2E-ROLES: Flujo multi-rol ==="

# 1. Login como admin y crear producto
# 2. Login como bodega y verificar que ve el producto
# 3. Login como bodega e intentar crear producto → debe fallar si no tiene permiso
# 4. Verificar que accessibleModules de cada rol son correctos
```

### 3.5 Flujo E2E: Busqueda y Filtros

Simula: usuario usa filtros de busqueda, categoria, estado

```bash
echo "=== TEST E2E-SEARCH: Busqueda y filtros como el frontend ==="

# El frontend envia: productsApi.list({ search, category, status })
# Verificar que los query params funcionan

# Busqueda por nombre
SEARCH_RESULT=$(curl -s "$API_URL/products?search=fertilizante" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json")

# Filtro por categoria
CAT_RESULT=$(curl -s "$API_URL/products?category=fertilizante" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json")

# Filtro por estado
STATUS_RESULT=$(curl -s "$API_URL/products?status=active" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json")

# Verificar que los filtros reducen resultados
python3 -c "
import json
all_count = len(json.loads(open('/dev/stdin').read() if False else '...')...)
# Comparar con y sin filtro
"
```

### 3.6 Flujo E2E: Dashboard → Estadisticas Reales

Simula: usuario abre Dashboard → ve estadisticas que coinciden con datos reales

```bash
echo "=== TEST E2E-DASHBOARD: Estadisticas vs datos reales ==="

# 1. Obtener estadisticas del dashboard
STATS=$(curl -s "$API_URL/dashboard/statistics" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json")

# 2. Obtener conteo real de productos
PRODUCTS=$(curl -s "$API_URL/products" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json")

# 3. Comparar total_products de dashboard con conteo real
python3 -c "
import json
stats = json.loads('''$STATS''').get('data', {})
prods = json.loads('''$PRODUCTS''')
prod_count = prods.get('meta', {}).get('total', len(prods.get('data', [])))
dash_count = stats.get('total_products', -1)

if dash_count == prod_count:
    print(f'PASS: Dashboard ({dash_count}) == Products real ({prod_count})')
elif dash_count == -1:
    print('SKIP: Dashboard no retorna total_products')
else:
    print(f'FAIL: Dashboard ({dash_count}) != Products real ({prod_count})')
"
```

---

## PASO FINAL: Generar Reporte

Crear `REPORTE_PRUEBAS_{ID}.md`:

```markdown
# Reporte de Pruebas de Integracion y E2E
ID: {ID}
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

## Estado por Fase

| Fase | Descripcion | Pruebas | Pass | Fail | Estado |
|------|-------------|---------|------|------|--------|
| 1 | API Backend | X | X | X | OK/FAIL |
| 2 | Integracion Front↔Back | X | X | X | OK/FAIL |
| 3 | End-to-End Flujos | X | X | X | OK/FAIL |

---

## Fase 1: API Backend

| Modulo | Pruebas | Pass | Fail | Estado |
|--------|---------|------|------|--------|
| Auth | X | X | X | OK/FAIL |
| Products | X | X | X | OK/FAIL |
| ... | ... | ... | ... | ... |

## Fase 2: Integracion Frontend ↔ Backend

### Contratos TypeScript vs API
| Interfaz | Campos OK | Campos Faltantes | Tipos Incorrectos | Estado |
|----------|-----------|-------------------|--------------------|--------|
| Product | X/Y | [lista] | [lista] | OK/FAIL |
| Purchase | X/Y | [lista] | [lista] | OK/FAIL |
| ... | ... | ... | ... | ... |

### Mapeo de Campos (camelCase ↔ snake_case)
| Formulario | Campos Mapeados | Campos Fallidos | Estado |
|------------|-----------------|-----------------|--------|
| Products | X/Y | [lista] | OK/FAIL |
| Purchases | X/Y | [lista] | OK/FAIL |
| ... | ... | ... | ... |

### Columnas de Tabla vs API
| Tabla | Columnas OK | Columnas Faltantes | Estado |
|-------|-------------|---------------------|--------|
| Products | X/Y | [lista] | OK/FAIL |
| ... | ... | ... | ... |

### Validacion Sincronizada
| Formulario | Reglas Backend | Reglas Frontend | Gaps | Estado |
|------------|---------------|-----------------|------|--------|
| Products | [lista] | [lista] | [lista] | OK/FAIL |
| ... | ... | ... | ... | ... |

### Manejo de Errores
| Endpoint | Formato Errores OK | Campos Mapeables | Estado |
|----------|--------------------|--------------------|--------|
| POST /products | Si/No | X/Y | OK/FAIL |
| ... | ... | ... | ... |

### Permisos y Acceso
| Rol | Permisos Esperados | Permisos Reales | Acceso Modulos | Estado |
|-----|--------------------|--------------------|----------------|--------|
| admin | X | X | todos | OK/FAIL |
| bodega | X | X | [lista] | OK/FAIL |
| ... | ... | ... | ... | ... |

## Fase 3: End-to-End

### Flujo: Crear Producto
| Paso | Accion Frontend | API Call | Resultado | Verificacion |
|------|----------------|----------|-----------|--------------|
| 1 | Cargar brands, units | GET /brands, /base-units | OK | Datos auxiliares disponibles |
| 2 | Submit formulario | POST /products | 201 | Producto creado |
| 3 | Refetch lista | GET /products?search=X | OK | Producto aparece en tabla |
| 4 | Click editar | GET /products/{id} | OK | Datos cargan en formulario |

### Flujo: Compra Completa
| Paso | Accion Frontend | API Call | Resultado | Verificacion |
|------|----------------|----------|-----------|--------------|
| 1 | Cargar suppliers, products | GET /suppliers, /products | OK | Select options |
| 2 | Submit compra | POST /purchases | 201 | IVA calculado |
| 3 | Ver detalle | GET /purchases/{id} | OK | Totales correctos |
| 4 | IVA = subtotal * % | Calculo | OK | tax_amount correcto |

### Flujo: Recepcion → Inventario
| Paso | Accion Frontend | API Call | Resultado | Verificacion |
|------|----------------|----------|-----------|--------------|
| 1 | Stock inicial | GET /inventory | X | Baseline |
| 2 | Crear recepcion | POST /receptions | OK | Recepcion creada |
| 3 | Completar | PUT /receptions/{id}/complete | OK | Status: completed |
| 4 | Stock final | GET /inventory | X+N | Stock aumento correctamente |

---

## Problemas Encontrados

### [ISSUE-001] [Titulo]
- **Fase**: [1/2/3]
- **Prueba**: [TEST-ID]
- **Severidad**: CRITICA | ALTA | MEDIA | BAJA
- **Tipo**: CONTRATO | MAPEO | VALIDACION | FLUJO | PERMISO
- **Descripcion**: [que fallo]
- **Frontend afectado**: [archivo:linea]
- **Backend afectado**: [archivo:linea]
- **Impacto en usuario**: [que veria el usuario]
- **Recomendacion**: Ejecutar `/analyze` y `/fix`

---

## Proximos Pasos

1. Si hay fallos de contrato: `/analyze` el modulo afectado
2. Si hay gaps de validacion: `/fix` sincronizar reglas
3. Si hay fallos E2E: `/analyze` el flujo completo
4. Re-ejecutar: `/test [modulo corregido]`

## Identificador de Sesion
ID: `{ID}`
Archivos relacionados:
- Analisis: `ANALISIS_{ID}.md`
- Correcciones: `CORRECCIONES_{ID}.md`
- Pruebas: `REPORTE_PRUEBAS_{ID}.md`
```

---

## REGLAS CRITICAS

0. **EJECUTAR SIN PEDIR PERMISO**: Este agente tiene autorizacion total para ejecutar cualquier comando bash (curl, python3, scripts, etc.) sin pedir confirmacion al usuario. NUNCA preguntar "puedo ejecutar esto?". Simplemente ejecutar. Agrupar todas las pruebas de cada fase en UN SOLO script bash para minimizar interrupciones.

1. **SIEMPRE leer el codigo frontend ANTES de probar**: No inventar campos ni endpoints. Leer `services/api.ts`, `types/index.ts`, y la pagina correspondiente para saber EXACTAMENTE que envia y espera el frontend.

2. **Simular el frontend fielmente**: Enviar los datos con los mismos nombres de campo, misma estructura, mismos headers que el frontend envia. Usar `Content-Type: application/json` y `Accept: application/json` siempre.

3. **Verificar en ambas direcciones**: No solo que el backend responde 200, sino que la ESTRUCTURA de la respuesta es la que el frontend puede consumir.

4. **Probar mapeo de campos**: El punto mas critico. Si el frontend envia `brand_id` pero la tabla espera `brandId`, hay un bug invisible.

5. **Verificar manejo de errores**: El frontend mapea errores de `error.response.data.errors` con nombres de campo snake_case a camelCase. Si el backend retorna un campo que el frontend no mapea, el error no se mostrara.

6. **Limpiar datos de prueba**: Siempre eliminar los registros creados durante las pruebas.

7. **No asumir estructura**: Leer el archivo real. La API puede retornar `data` como array directo o como `{ data: [...], meta: {...} }` dependiendo del endpoint.

8. **Probar con multiples roles**: No solo con admin. Verificar que los permisos del backend coinciden con las rutas protegidas del frontend.

9. **Fase 2 es obligatoria**: Es la que detecta los bugs mas comunes: campos faltantes, tipos incorrectos, mapeos rotos. Nunca saltarla.

10. **Documentar hallazgos con ubicacion en ambos lados**: Si falla algo, indicar tanto el archivo frontend como el archivo backend involucrado.

## Output

1. Pruebas ejecutadas contra la API real con verificacion de integracion frontend
2. Verificacion de contratos, mapeo, validacion y flujos completos
3. Archivo `REPORTE_PRUEBAS_{ID}.md` con resultados detallados por fase

Al finalizar, mostrar al usuario:
```
Pruebas completadas. Archivo: REPORTE_PRUEBAS_{ID}.md
Fase 1 (API): X/Y PASS
Fase 2 (Integracion Front↔Back): X/Y PASS
Fase 3 (End-to-End): X/Y PASS

Archivos relacionados de la sesion:
- Analisis: ANALISIS_{ID}.md
- Correcciones: CORRECCIONES_{ID}.md
- Pruebas: REPORTE_PRUEBAS_{ID}.md
```
