# Instrucciones de Prueba de Integración Frontend-Backend

## Correcciones Realizadas

### 1. ✅ Problema de Parámetros `undefined` Corregido

**Ubicación**: `frontend/src/services/api.ts:66-87`

**Problema**: Los parámetros con valor `undefined` se estaban convirtiendo en la cadena literal `"undefined"` en las URLs.

**Ejemplo del problema**:
```
http://localhost:8000/api/suppliers?search=undefined&status=undefined
```

**Solución**: Filtrado de parámetros antes de crear el query string:

```typescript
async get<T>(endpoint: string, params?: Record<string, any>): Promise<T> {
  let queryString = '';
  if (params) {
    // Filter out undefined, null, and empty string values
    const filteredParams: Record<string, string> = {};
    Object.keys(params).forEach(key => {
      const value = params[key];
      if (value !== undefined && value !== null && value !== '') {
        filteredParams[key] = String(value);
      }
    });

    // Only create query string if there are valid parameters
    if (Object.keys(filteredParams).length > 0) {
      queryString = '?' + new URLSearchParams(filteredParams).toString();
    }
  }

  return this.request<T>(`${endpoint}${queryString}`, {
    method: 'GET',
  });
}
```

**Resultado esperado**:
```
http://localhost:8000/api/suppliers
```

### 2. ✅ Menú Frontend - Funcionamiento Correcto

**Ubicación**: `frontend/src/components/layout/MainLayout.tsx`

**Estado**: El menú está correctamente implementado con Ant Design Menu. Los submenús hijos funcionan correctamente.

**Estructura**:
- Los items padre tienen keys no navegables (ej: `"datos-maestros"`)
- Los items hijos tienen keys con rutas (ej: `"/master/products"`)
- El onClick solo navega cuando el key es una ruta válida

## Pruebas de Integración Requeridas

### Configuración

1. **Backend**: http://localhost:8000 (Laravel + JWT Auth)
2. **Frontend**: http://localhost:5175 (React + Vite)

### Módulos a Validar

#### 1. Suppliers (Proveedores)
```bash
# Endpoint
GET /api/suppliers

# Parámetros opcionales
- search: string
- status: 'active' | 'inactive'
- page: number
- per_page: number

# Prueba esperada:
1. Ir a /master/suppliers
2. Verificar que se carguen los proveedores
3. Buscar por nombre
4. Filtrar por estado (activo/inactivo)
5. Verificar que no aparezca "undefined" en la URL
```

#### 2. Products (Productos)
```bash
# Endpoint
GET /api/products

# Parámetros opcionales
- search: string
- category: 'fertilizante' | 'pesticida' | 'herbicida' | 'fungicida'
- status: 'active' | 'inactive'
- page: number
- per_page: number

# Prueba esperada:
1. Ir a /master/products
2. Verificar que se carguen los productos
3. Buscar por nombre
4. Filtrar por categoría y estado
5. Verificar que no aparezca "undefined" en la URL
```

#### 3. Brands (Marcas)
```bash
# Endpoint
GET /api/brands

# Parámetros opcionales
- search: string
- status: 'active' | 'inactive'
- page: number
- per_page: number

# Prueba esperada:
1. Ir a /master/brands
2. Verificar que se carguen las marcas
3. Buscar por nombre
4. Filtrar por estado
5. Verificar que no aparezca "undefined" en la URL
```

#### 4. Locations (Ubicaciones - Fincas y Bodegas)
```bash
# Endpoint
GET /api/locations

# Parámetros opcionales
- search: string
- type: 'farm' | 'warehouse'
- status: 'active' | 'inactive'
- page: number
- per_page: number

# Prueba esperada:
1. Ir a /master/locations
2. Verificar que se carguen las ubicaciones
3. Buscar por nombre
4. Filtrar por tipo (finca/bodega) y estado
5. Verificar que no aparezca "undefined" en la URL
```

## Cómo Probar

### Usando DevTools del Navegador

1. Abrir Chrome DevTools (F12)
2. Ir a la pestaña "Network"
3. Navegar a cada módulo
4. Verificar las peticiones HTTP:
   - ✅ **CORRECTO**: `http://localhost:8000/api/suppliers`
   - ✅ **CORRECTO**: `http://localhost:8000/api/suppliers?search=test`
   - ❌ **INCORRECTO**: `http://localhost:8000/api/suppliers?search=undefined&status=undefined`

### Verificación Visual

1. **Menú de Navegación**:
   - Click en "Datos Maestros" debe expandir el submenú
   - Click en "Productos", "Marcas", "Proveedores", "Fincas y Bodegas" debe navegar correctamente

2. **Listados**:
   - Deben cargar datos del backend
   - Los filtros y búsquedas deben funcionar
   - La paginación debe funcionar

3. **Formularios**:
   - Crear nuevo registro
   - Editar registro existente
   - Eliminar registro

## Errores Comunes a Verificar

### 1. Parámetros undefined
❌ **Antes**:
```
Request URL: http://localhost:8000/api/suppliers?search=undefined&status=undefined
```

✅ **Después**:
```
Request URL: http://localhost:8000/api/suppliers
```

### 2. Errores de CORS
Si aparece error de CORS, verificar:
- Backend tiene configurado CORS correctamente
- Frontend hace peticiones a la URL correcta

### 3. Errores de Autenticación
- Verificar que el token JWT esté presente en localStorage
- Verificar que las peticiones incluyan el header `Authorization: Bearer <token>`

## Scripts de Prueba

### Iniciar Servidores

```bash
# Terminal 1: Backend
cd backend
php artisan serve

# Terminal 2: Frontend (con Node.js 22+)
export NVM_DIR="$HOME/.nvm"
[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"
nvm use 22
cd frontend
npm run dev
```

### Prueba con cURL

```bash
# 1. Login
TOKEN=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@agriflor.com","password":"password"}' \
  | jq -r '.data.token')

# 2. Prueba Suppliers (sin parámetros)
curl -s "http://localhost:8000/api/suppliers" \
  -H "Authorization: Bearer $TOKEN" | jq .

# 3. Prueba Suppliers (con búsqueda)
curl -s "http://localhost:8000/api/suppliers?search=test" \
  -H "Authorization: Bearer $TOKEN" | jq .

# 4. Prueba Products
curl -s "http://localhost:8000/api/products" \
  -H "Authorization: Bearer $TOKEN" | jq .

# 5. Prueba Brands
curl -s "http://localhost:8000/api/brands" \
  -H "Authorization: Bearer $TOKEN" | jq .

# 6. Prueba Locations
curl -s "http://localhost:8000/api/locations" \
  -H "Authorization: Bearer $TOKEN" | jq .
```

## Checklist de Validación

- [ ] Backend corriendo en puerto 8000
- [ ] Frontend corriendo en puerto 5175 (o similar)
- [ ] Login funciona correctamente
- [ ] Menú de navegación funciona
- [ ] Módulo Suppliers carga y funciona
- [ ] Módulo Products carga y funciona
- [ ] Módulo Brands carga y funciona
- [ ] Módulo Locations carga y funciona
- [ ] NO aparece "undefined" en las URLs
- [ ] Filtros y búsquedas funcionan
- [ ] Paginación funciona
- [ ] Crear/Editar/Eliminar funcionan

## Información Adicional

### Estructura de Respuesta del Backend

```json
{
  "success": true,
  "message": "Suppliers retrieved successfully",
  "data": [
    {
      "id": "uuid",
      "name": "Proveedor 1",
      "status": "active",
      ...
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 75
  }
}
```

### Archivos Modificados

1. `frontend/src/services/api.ts` - Corregido método `get()` para filtrar parámetros undefined
2. Todos los componentes de listado ya están configurados correctamente para enviar parámetros

### Notas Importantes

- ⚠️ El frontend requiere Node.js 20.19+ o 22.12+
- ⚠️ El backend debe estar corriendo en el puerto 8000 (configurado en `.env` del frontend)
- ⚠️ Se requiere autenticación JWT para acceder a los endpoints
