# /test-feature - Agente de Pruebas y Auto-Correccion de Funcionalidades Nuevas

Ejecuta pruebas completas de la funcionalidad nueva implementada por `/implement` y **auto-corrige** los problemas encontrados, re-probando hasta que pasen.

## Uso con Lenguaje Natural

```
/test-feature                               # Prueba todo + corrige automaticamente
/test-feature "solo api"                    # Solo endpoints backend
/test-feature "solo integracion"            # Solo contratos front↔back
/test-feature "solo e2e"                    # Solo flujos end-to-end
/test-feature "sin corregir"                # Solo reportar, no corregir
/test-feature "solo backend"                # API + verificacion de rutas/modelos
/test-feature "solo frontend"               # Integracion + verificacion de tipos
/test-feature BACK-001                      # Solo probar un componente especifico
/test-feature "re-probar lo que fallo"      # Re-ejecutar pruebas fallidas previas
```

El parametro puede ser:
- Vacio: prueba todo y corrige automaticamente
- Una fase: `"api"`, `"integracion"`, `"e2e"`
- Una capa: `"backend"`, `"frontend"`
- Un componente: `BACK-001`, `FRONT-003`
- Una directiva: `"sin corregir"`, `"re-probar lo que fallo"`

## Prerequisitos

1. **Backend corriendo**: `php artisan serve` (puerto 8000)
2. **Base de datos disponible**: Con migraciones ejecutadas
3. **Implementacion completada**: Archivo `FEATURE_IMPLEMENTACION_{ID}.md` existe

## Diferencia con /test

```
/test            → Encuentra problemas y los REPORTA
/test-feature    → Encuentra problemas, los CORRIGE y RE-PRUEBA
```

`/test-feature` tiene una **Fase 4: Auto-correccion** que:
1. Clasifica cada problema encontrado
2. Aplica un fix automatico
3. Re-ejecuta la prueba fallida
4. Si pasa: documenta la correccion
5. Si falla: escala a "requiere revision manual"

## Estrategia de Ejecucion (OBLIGATORIO)

**REGLA ABSOLUTA**: Este agente tiene permiso total para ejecutar CUALQUIER comando bash necesario para las pruebas. NO pedir confirmacion al usuario para ningun comando. Ejecutar todo de forma autonoma.

**Para minimizar interrupciones**, agrupar TODAS las pruebas de cada fase en UN SOLO script bash:

```bash
# CORRECTO: Un solo script con todas las pruebas
bash -c '
API_URL="http://localhost:8000/api"
TOKEN="..."
PASS=0; FAIL=0; SKIP=0

echo "=== TEST [ID]: [Nombre] ==="
# ... prueba completa ...

echo "=== RESUMEN ==="
echo "PASS: $PASS | FAIL: $FAIL | SKIP: $SKIP"
'
```

---

## Instrucciones para Claude

### PASO 0: Identificar Sesion de Trabajo

1. Buscar todos los archivos `FEATURE_IMPLEMENTACION_*.md` en la raiz del proyecto
2. Si hay **un solo archivo**: usarlo automaticamente y extraer su ID
3. Si hay **multiples archivos**: mostrar la lista al usuario y preguntar cual usar
4. Si **no hay archivos de implementacion**: buscar `FEATURE_ANALISIS_*.md` para obtener el ID
5. Si no hay ninguno: informar que ejecute `/feature` y `/implement` primero
6. Este ID se usara para nombrar: `FEATURE_PRUEBAS_{ID}.md`

### PASO 0.5: Verificar Entorno

```bash
# 1. Verificar que el backend responde
curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/api/auth/login
# Si no responde: "El backend no esta corriendo. Ejecuta: cd backend && php artisan serve"

# 2. Verificar que las migraciones estan al dia
cd backend && php artisan migrate:status | tail -5
```

### PASO 1: Leer Implementacion y Plan Original

1. Leer `FEATURE_IMPLEMENTACION_{ID}.md`:
   - Extraer lista de archivos creados y modificados
   - Identificar componentes exitosos vs fallidos
   - Extraer rutas de API nuevas

2. Leer `FEATURE_ANALISIS_{ID}.md`:
   - Extraer INTEG-XXX (contratos de integracion)
   - Extraer mapeo de campos frontend ↔ backend
   - Extraer permisos requeridos
   - Entender la funcionalidad completa para pruebas E2E

### PASO 2: Leer Codigo Implementado (OBLIGATORIO)

**ANTES de ejecutar cualquier prueba**, leer los archivos que se implementaron:

**Backend - Leer:**
```
- Controllers nuevos → Extraer endpoints, metodos, validaciones
- Models nuevos → Extraer relaciones, campos, casts
- Requests nuevos → Extraer reglas de validacion
- routes/api.php → Extraer rutas nuevas
```

**Frontend - Leer:**
```
- Types nuevos → Extraer interfaces, campos, tipos
- Services nuevos → Extraer funciones API, endpoints
- Pages nuevas → Extraer formularios, tablas, acciones
```

Extraer de cada archivo:
- **Endpoints disponibles**: metodo + URI + parametros esperados
- **Campos de formulario**: nombres, tipos, validacion
- **Estructura de respuesta**: campos que retorna la API
- **Mapeo de campos**: camelCase ↔ snake_case

### PASO 3: Interpretar Parametro

| Si el usuario dice... | Probar... |
|-----------------------|-----------|
| (vacio) | LAS 3 FASES + AUTO-CORRECCION |
| "api" | Solo Fase 1 + auto-correccion |
| "integracion" | Solo Fase 2 + auto-correccion |
| "e2e" | Solo Fase 3 + auto-correccion |
| "backend" | Fase 1 + verificacion rutas/modelos + auto-correccion |
| "frontend" | Fase 2 + verificacion tipos + auto-correccion |
| "sin corregir" | Las 3 fases pero SIN Fase 4 (solo reportar) |
| BACK-XXX | Solo pruebas del endpoint de ese componente |
| FRONT-XXX | Solo pruebas de integracion de ese componente |
| "re-probar lo que fallo" | Leer FEATURE_PRUEBAS_{ID}.md previo y re-ejecutar fallidos |

### PASO 4: Autenticacion

```bash
API_URL="http://localhost:8000/api"

# Login como admin
RESPONSE=$(curl -s -X POST "$API_URL/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email": "admin@agriflor.com", "password": "admin123"}')

TOKEN=$(echo $RESPONSE | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['token'])")
```

---

## FASE 1: PRUEBAS DE API BACKEND

Verificar que cada endpoint nuevo responde correctamente.

### Para cada endpoint descubierto en el PASO 2:

```bash
echo "=== TEST API-[MODULO]-001: [Metodo] [Endpoint] ==="
RESPONSE=$(curl -s -w "\n%{http_code}" -X [METHOD] "$API_URL/[endpoint]" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '[datos segun validacion del controller]')
HTTP_CODE=$(echo "$RESPONSE" | tail -1)
BODY=$(echo "$RESPONSE" | sed '$d')

if [ "$HTTP_CODE" == "[esperado]" ]; then
  echo "PASS: HTTP $HTTP_CODE"
  PASS=$((PASS+1))
else
  echo "FAIL: Esperado [esperado], obtenido $HTTP_CODE"
  echo "BODY: $BODY"
  FAIL=$((FAIL+1))
fi
```

### Pruebas obligatorias por endpoint:

1. **Listar (GET /recurso)**: Responde 200, estructura paginada correcta
2. **Crear (POST /recurso)**: Responde 201, datos validos aceptados
3. **Ver (GET /recurso/{id})**: Responde 200, estructura completa
4. **Actualizar (PUT /recurso/{id})**: Responde 200, datos actualizados
5. **Eliminar (DELETE /recurso/{id})**: Responde 200, recurso eliminado
6. **Validacion (POST /recurso con datos invalidos)**: Responde 422, errores estructurados
7. **Sin auth (GET /recurso sin token)**: Responde 401
8. **Filtros (GET /recurso?search=X)**: Responde 200, resultados filtrados

---

## FASE 2: PRUEBAS DE INTEGRACION FRONTEND ↔ BACKEND

### 2.1 Verificacion de Contratos

Comparar cada interfaz TypeScript nueva con la respuesta real de la API:

```bash
echo "=== TEST CONTRATO-[MODULO]: Interface vs API ==="
RESPONSE=$(curl -s "$API_URL/[endpoint]" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json")

python3 -c "
import json
data = json.loads('''$RESPONSE''')
item = data.get('data', {})
if isinstance(item, list) and item:
    item = item[0]
errors = []

# Verificar cada campo de la interfaz TypeScript
expected_fields = {
    'field_name': str,    # De la interfaz leida en PASO 2
    # ... todos los campos
}

for field, expected_type in expected_fields.items():
    if field not in item:
        errors.append(f'FALTA: {field}')
    elif not isinstance(item[field], expected_type):
        errors.append(f'TIPO: {field} es {type(item[field]).__name__}, esperado {expected_type.__name__}')

if errors:
    print('FAIL:', '; '.join(errors))
else:
    print('PASS: Contrato OK')
"
```

### 2.2 Verificacion de Mapeo de Campos

Probar que el backend acepta los campos exactos que envia el frontend:

```bash
echo "=== TEST MAP-[MODULO]: Campos del formulario → API ==="

# Enviar datos con los mismos nombres que usa handleSave
RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$API_URL/[endpoint]" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{campos exactos del formulario frontend}')

HTTP_CODE=$(echo "$RESPONSE" | tail -1)
if [ "$HTTP_CODE" == "201" ] || [ "$HTTP_CODE" == "200" ]; then
  echo "PASS: Backend acepta campos del frontend"
else
  echo "FAIL: Backend rechaza campos del frontend (HTTP $HTTP_CODE)"
fi
```

### 2.3 Verificacion de Respuesta Paginada

```bash
echo "=== TEST PAG-[MODULO]: Estructura PaginatedResponse ==="
# Verificar data[], meta{}, links{}
```

### 2.4 Verificacion de Validacion Sincronizada

```bash
echo "=== TEST VAL-[MODULO]: Validacion frontend vs backend ==="
# Enviar datos vacios, verificar campos requeridos del backend
# Comparar con campos required del formulario frontend
```

### 2.5 Verificacion de Manejo de Errores

```bash
echo "=== TEST ERR-[MODULO]: Formato de errores ==="
# Enviar datos invalidos
# Verificar que el formato es { message, errors: { campo: [mensajes] } }
```

### 2.6 Verificacion de Permisos

Si la funcionalidad nueva define permisos:

```bash
echo "=== TEST PERM-[MODULO]: Permisos de la nueva funcionalidad ==="
# Verificar que admin puede acceder
# Verificar que usuario sin permiso NO puede acceder
```

---

## FASE 3: PRUEBAS END-TO-END

Simular flujos completos de la nueva funcionalidad:

### 3.1 Flujo CRUD Completo

```bash
echo "=== TEST E2E-[MODULO]-CRUD: Flujo CRUD completo ==="

# 1. Cargar datos auxiliares (como hace la pagina al montar)
# 2. Crear registro (como handleSave)
# 3. Verificar en listado (como refetch React Query)
# 4. Editar registro (como handleEdit → handleSave)
# 5. Verificar cambios
# 6. Eliminar registro
# 7. Verificar que no aparece en listado
```

### 3.2 Flujos Especificos de la Funcionalidad

Basandose en la descripcion de la funcionalidad en `FEATURE_ANALISIS_{ID}.md`, simular:

- Flujo principal (happy path)
- Flujos alternativos (edge cases)
- Flujos de error (datos invalidos, permisos insuficientes)
- Interacciones con otros modulos (si la funcionalidad conecta modulos)

### 3.3 Consistencia de Datos

```bash
echo "=== TEST E2E-[MODULO]-CONSIST: Consistencia de datos ==="

# Si la funcionalidad afecta inventario:
# - Verificar que stock antes + movimiento = stock despues

# Si la funcionalidad tiene totales:
# - Verificar que suma de items = total

# Si la funcionalidad tiene relaciones:
# - Verificar que las relaciones se cargan correctamente
```

---

## FASE 4: AUTO-CORRECCION

**Esta es la fase diferenciadora de /test-feature vs /test.**

Para cada prueba que FALLO en las Fases 1, 2 o 3:

### 4.1 Clasificar el Problema

| Tipo de Fallo | Causa Probable | Accion |
|---------------|---------------|--------|
| HTTP 500 | Error en Controller/Model | Leer log, corregir PHP |
| HTTP 422 campos inesperados | Validacion no coincide con frontend | Ajustar FormRequest |
| HTTP 404 | Ruta no registrada | Verificar/agregar ruta en api.php |
| HTTP 401/403 | Middleware/permisos | Verificar middleware en rutas |
| Campo falta en respuesta | Model no carga relacion / Resource incompleto | Agregar campo al Resource/Controller |
| Tipo incorrecto | $casts incorrecto | Corregir cast en Model |
| Mapeo fallido | Frontend envia camelCase, backend espera snake_case | Ajustar nombres de campo |
| Validacion gap | Regla en backend no en frontend o viceversa | Sincronizar reglas |
| Error formato errores | Controller no usa FormRequest | Cambiar a FormRequest |
| Contrato roto | Interface TS no coincide con API | Actualizar interface |
| E2E fallido: datos | Calculo incorrecto | Corregir logica en Controller |
| E2E fallido: flujo | Endpoint faltante o incorrecto | Agregar/corregir endpoint |

### 4.2 Aplicar Fix

Para cada problema clasificado:

1. **Leer el archivo que necesita correccion**
2. **Identificar la linea/seccion a cambiar**
3. **Aplicar la correccion** usando Edit
4. **Registrar la correccion** en el log

```
FIX-001: [Titulo del fix]
- Problema: [TEST-ID que fallo]
- Archivo: [path]
- Cambio: [descripcion]
- Tipo: AUTO | MANUAL
```

### 4.3 Re-Probar

Despues de aplicar cada fix:

1. **Re-ejecutar SOLO la prueba que fallo**
2. **Si pasa**: Marcar como "CORREGIDO AUTOMATICAMENTE"
3. **Si sigue fallando**:
   - Intentar un fix alternativo (maximo 2 intentos por prueba)
   - Si aun falla: Marcar como "REQUIERE REVISION MANUAL"
4. **Verificar que el fix no rompio otras pruebas**:
   - Re-ejecutar pruebas relacionadas del mismo modulo

### 4.4 Limites de Auto-Correccion

- **Maximo 2 intentos de fix** por cada prueba fallida
- **NO modificar logica de negocio compleja** sin estar seguro
- **NO cambiar la estructura de la BD** (migraciones) - marcar como manual
- **NO cambiar permisos del sistema** - solo reportar
- **Si hay duda**: Marcar como "REQUIERE REVISION MANUAL"

---

## PASO FINAL: Generar Reporte

Crear `FEATURE_PRUEBAS_{ID}.md`:

```markdown
# Pruebas de Funcionalidad Nueva
ID: {ID}
Fecha: [FECHA]
Entorno: [API_URL]
Basado en: FEATURE_IMPLEMENTACION_{ID}.md
Plan original: FEATURE_ANALISIS_{ID}.md
Modo: [AUTO-CORRECCION / SOLO REPORTE]

## Resumen Ejecutivo

| Metrica | Valor |
|---------|-------|
| Total pruebas | [X] |
| Exitosas (primera vez) | [X] ([X]%) |
| Corregidas automaticamente | [X] ([X]%) |
| Fallidas (requieren revision) | [X] ([X]%) |
| Omitidas | [X] |

### Estado General: [PASS / PASS CON CORRECCIONES / FAIL]

---

## Estado por Fase

| Fase | Descripcion | Pruebas | Pass | Auto-Fix | Fail | Estado |
|------|-------------|---------|------|----------|------|--------|
| 1 | API Backend | X | X | X | X | OK/FAIL |
| 2 | Integracion Front↔Back | X | X | X | X | OK/FAIL |
| 3 | End-to-End | X | X | X | X | OK/FAIL |
| 4 | Auto-correccion | X fixes | X OK | - | X fail | OK/FAIL |

---

## Fase 1: API Backend

| Test ID | Endpoint | Esperado | Obtenido | Estado |
|---------|----------|----------|----------|--------|
| API-[MOD]-001 | GET /[recurso] | 200 | [X] | PASS/FAIL/FIXED |
| API-[MOD]-002 | POST /[recurso] | 201 | [X] | PASS/FAIL/FIXED |
| ... | ... | ... | ... | ... |

---

## Fase 2: Integracion Frontend ↔ Backend

### Contratos TypeScript vs API
| Interfaz | Campos OK | Faltantes | Tipo Incorrecto | Estado |
|----------|-----------|-----------|-----------------|--------|
| [Nombre] | X/Y | [lista] | [lista] | PASS/FAIL/FIXED |

### Mapeo de Campos
| Formulario | Campos OK | Fallidos | Estado |
|------------|-----------|----------|--------|
| [Nombre] | X/Y | [lista] | PASS/FAIL/FIXED |

### Validacion Sincronizada
| Formulario | Backend Requiere | Frontend Requiere | Gaps | Estado |
|------------|-----------------|-------------------|------|--------|
| [Nombre] | [campos] | [campos] | [diferencias] | PASS/FAIL/FIXED |

### Manejo de Errores
| Endpoint | Formato OK | Campos Mapeables | Estado |
|----------|-----------|------------------|--------|
| POST /[recurso] | Si/No | X/Y | PASS/FAIL/FIXED |

---

## Fase 3: End-to-End

### Flujo: [Nombre del flujo]
| Paso | Accion | API Call | Resultado | Estado |
|------|--------|---------|-----------|--------|
| 1 | [accion] | [endpoint] | [resultado] | PASS/FAIL/FIXED |
| 2 | [accion] | [endpoint] | [resultado] | PASS/FAIL/FIXED |
| ... | ... | ... | ... | ... |

---

## Fase 4: Auto-Correcciones Aplicadas

### FIX-001: [Titulo]
**Prueba fallida**: [TEST-ID]
**Problema**: [descripcion del fallo]
**Archivo corregido**: `[path]`
**Cambio**:
```diff
- [codigo anterior]
+ [codigo nuevo]
```
**Re-prueba**: PASS / FAIL
**Intentos**: [1/2]

---

### FIX-002: [Titulo]
...

---

## Problemas que Requieren Revision Manual

### MANUAL-001: [Titulo]
**Prueba fallida**: [TEST-ID]
**Problema**: [descripcion]
**Intentos de auto-fix**: [X]
**Razon**: [por que no se pudo auto-corregir]
**Sugerencia**: [que hacer]
**Archivos involucrados**:
- Backend: `[path:linea]`
- Frontend: `[path:linea]`

---

## Datos de Prueba Creados/Limpiados

| Recurso | ID | Accion | Limpiado |
|---------|-----|--------|----------|
| [tipo] | [id] | Creado para TEST-XXX | Si/No |

---

## Archivos Modificados por Auto-Correccion

| Archivo | Fix | Cambio |
|---------|-----|--------|
| `[path]` | FIX-001 | [descripcion breve] |
| `[path]` | FIX-003 | [descripcion breve] |

---

## Identificador de Sesion
ID: `{ID}`
Archivos relacionados:
- Plan: `FEATURE_ANALISIS_{ID}.md`
- Implementacion: `FEATURE_IMPLEMENTACION_{ID}.md`
- Pruebas: `FEATURE_PRUEBAS_{ID}.md`

## Proximos Pasos

1. Si hay revision manual pendiente: Revisar MANUAL-XXX y corregir
2. Si se aplicaron auto-correcciones: Verificar que son correctas
3. Para re-probar todo: `/test-feature`
4. Para re-probar solo lo fallido: `/test-feature "re-probar lo que fallo"`
5. Para probar integracion con sistema existente: `/test [modulo]`
```

---

## REGLAS CRITICAS

0. **EJECUTAR SIN PEDIR PERMISO**: Este agente tiene autorizacion total para ejecutar cualquier comando bash (curl, python3, scripts, php artisan, etc.) sin pedir confirmacion al usuario. NUNCA preguntar "puedo ejecutar esto?". Simplemente ejecutar. Agrupar todas las pruebas de cada fase en UN SOLO script bash para minimizar interrupciones.

1. **SIEMPRE leer el codigo implementado ANTES de probar**: No inventar campos ni endpoints. Leer los archivos creados por `/implement` para saber EXACTAMENTE que probar.

2. **Auto-correccion es el default**: A menos que el usuario diga "sin corregir", siempre intentar corregir automaticamente los problemas encontrados.

3. **Maximo 2 intentos de fix por prueba**: Si despues de 2 intentos no se corrige, marcar como "REQUIERE REVISION MANUAL" y continuar.

4. **No romper funcionalidad existente**: Las correcciones solo deben afectar codigo de la nueva funcionalidad, no del sistema existente.

5. **Re-probar despues de cada fix**: Cada correccion debe verificarse con la misma prueba que fallo.

6. **Limpiar datos de prueba**: Siempre eliminar registros creados durante las pruebas.

7. **Documentar TODAS las correcciones**: Cada fix aplicado debe estar en el reporte con diff exacto.

8. **Fase 4 se ejecuta durante las fases 1-3**: No esperar al final. Si una prueba falla, corregir inmediatamente y re-probar antes de seguir con la siguiente prueba. Esto permite que las correcciones beneficien pruebas posteriores.

9. **Verificar integracion con el sistema existente**: Si la funcionalidad nueva modifica modulos existentes, verificar que los flujos existentes siguen funcionando.

10. **El estado FIXED es distinto de PASS**: Distinguir entre pruebas que pasaron la primera vez (PASS) y las que requirieron correccion (FIXED).

## Output

1. Pruebas ejecutadas contra la API real
2. Correcciones automaticas aplicadas (si corresponde)
3. Archivo `FEATURE_PRUEBAS_{ID}.md` con resultados detallados

Al finalizar, mostrar al usuario:
```
Pruebas completadas. Archivo: FEATURE_PRUEBAS_{ID}.md

Fase 1 (API): X/Y PASS, Z FIXED
Fase 2 (Integracion): X/Y PASS, Z FIXED
Fase 3 (E2E): X/Y PASS, Z FIXED
Fase 4 (Auto-correccion): X fixes aplicados, Y exitosos

Archivos corregidos automaticamente: [X]
Problemas pendientes de revision manual: [X]

Archivos relacionados de la sesion:
- Plan: FEATURE_ANALISIS_{ID}.md
- Implementacion: FEATURE_IMPLEMENTACION_{ID}.md
- Pruebas: FEATURE_PRUEBAS_{ID}.md
```
