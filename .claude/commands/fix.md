# /fix - Agente de Correccion Inteligente

Corrige problemas detectados por /analyze. Lee el analisis, verifica que los problemas siguen vigentes, y aplica correcciones precisas.

## PRINCIPIO: Verificar antes de corregir

NUNCA aplicar una correccion del analisis a ciegas. El codigo puede haber cambiado desde que se genero el analisis. Siempre:
1. Leer el archivo actual
2. Verificar que el problema sigue existiendo
3. Adaptar la correccion al estado actual del codigo
4. Aplicar el cambio minimo necesario

---

## PASO 0: Encontrar el analisis

1. Buscar archivos `ANALISIS_*.md` en la raiz del proyecto
2. Si hay uno: usarlo
3. Si hay varios: usar el mas reciente
4. Si no hay ninguno: informar al usuario que ejecute `/analyze` primero
5. Extraer el ID del nombre (ej: `ANALISIS_20260128_143052.md` → ID = `20260128_143052`)

## PASO 1: Parsear y filtrar

Leer el analisis y extraer todos los hallazgos (ERR, INC, LOG, INT).

Si $ARGUMENTS tiene filtro, aplicarlo:
- `"solo errores"` → solo ERR-*
- `"inventario"` → solo hallazgos del modulo inventario
- `"ERR-001"` → solo ese hallazgo
- vacio → todos

Ordenar por prioridad: ERR > LOG > INT > INC

## PASO 2: Para CADA hallazgo

```
2.1 Leer el archivo actual (NO asumir que sigue igual que en el analisis)
2.2 Buscar el codigo problematico en el archivo actual
2.3 Si el codigo CAMBIO:
    - Re-evaluar si el problema sigue existiendo
    - Si ya no existe: marcar como RESUELTO y pasar al siguiente
    - Si existe pero cambio: adaptar la correccion
2.4 Si el codigo es IGUAL al analisis:
    - Aplicar la correccion propuesta
2.5 Verificar que la correccion no rompe imports/exports/relaciones
2.6 php -l o tsc --noEmit segun corresponda
```

## PASO 3: Correcciones backend

**Respetar patrones del proyecto:**
- UUIDs como primary key (HasUuids trait)
- Verificar si modelo tiene timestamps (algunos no tienen updated_at)
- DB::transaction() para operaciones multi-tabla
- lockForUpdate() para inventario
- FormRequest para validacion (no inline)
- Resources con dual naming (camelCase + snake_case)
- Respuestas: `{ success: true/false, message: '...', data: ... }`
- Mensajes en espanol

## PASO 4: Correcciones frontend

**Respetar patrones del proyecto:**
- Ant Design 5 + Ant Form con rules (NO Zod, NO React Hook Form)
- React Query (useQuery, useMutation, invalidateQueries)
- ResponsiveTable con mobileColumns + desktopColumns
- ApiService con fetch nativo (NO axios)
- Mapeo manual snake_case <-> camelCase
- isSubmittingRef para anti-doble-submit
- overflow: hidden en containers mobile

## PASO 5: Verificar cambios

Despues de aplicar TODAS las correcciones:

```bash
# Backend
php -l [cada archivo PHP modificado]

# Frontend
cd frontend && npx tsc --noEmit 2>&1 | head -20

# API health
curl -s http://localhost:8000/api/auth/login -X POST \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@agriflor.com","password":"admin123"}' | head -50
```

## PASO 6: Generar reporte

Crear `CORRECCIONES_{ID}.md` (mismo ID del analisis):

```markdown
# Correcciones Aplicadas
ID: {ID}
Basado en: ANALISIS_{ID}.md

## Resumen
| Aplicadas | Ya resueltas | Fallidas |
|-----------|-------------|----------|
| X | X | X |

## Cambios realizados

### [ID]: [Titulo]
**Archivo**: `[path]`
**Cambio**:
```diff
- [antes]
+ [despues]
```

## Archivos modificados
| Archivo | Correcciones |
|---------|-------------|
| [path] | [IDs] |
```

---

## REGLAS

1. **Verificar antes de aplicar**: El analisis puede estar desactualizado
2. **Cambio minimo**: Solo lo necesario para resolver el problema
3. **No romper nada**: Si hay duda, marcar como pendiente de revision
4. **Backend primero**: Si la correccion involucra ambos, empezar por backend
5. **Preservar funcionalidad**: No eliminar codigo que otros archivos usan
