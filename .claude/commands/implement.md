# /implement - Agente de Implementacion de Funcionalidades

Lee el plan generado por `/feature` e implementa todo el codigo: archivos nuevos completos, modificaciones a archivos existentes, migraciones, rutas, tipos, servicios y paginas.

## Uso con Lenguaje Natural

```
/implement                                  # Implementa todo el plan
/implement "solo backend"                   # Solo BD + modelos + controllers + rutas
/implement "solo frontend"                  # Solo types + services + pages
/implement "fase 1"                         # Solo la fase 1 (base de datos)
/implement "fase 1 y 2"                     # Fases 1 y 2
/implement BACK-001                         # Un componente especifico
/implement "lo que falta"                   # Componentes no implementados aun
/implement "solo migraciones"               # Solo BACK-DB-*
/implement "solo modelos y controllers"     # Solo BACK-* (no DB, no rutas)
/implement "solo paginas"                   # Solo FRONT-* de tipo Page
```

El parametro puede ser:
- Vacio: implementa todo
- Una fase: `"fase 1"`, `"fases 1-3"`
- Una capa: `"solo backend"`, `"solo frontend"`
- Un tipo: `"solo migraciones"`, `"solo modelos"`, `"solo paginas"`
- Un ID especifico: `BACK-001`, `FRONT-003`, `MOD-BACK-002`
- Una descripcion: `"lo que falta"`, `"todo menos las paginas"`

## Prerequisito

DEBE existir al menos un archivo `FEATURE_ANALISIS_{ID}.md` generado por `/feature`.
Si no existe ninguno, informar al usuario que ejecute `/feature` primero.

## Instrucciones para Claude

### PASO 0: Identificar Archivo de Plan

1. Buscar todos los archivos `FEATURE_ANALISIS_*.md` en la raiz del proyecto
2. Si hay **un solo archivo**: usarlo automaticamente
3. Si hay **multiples archivos**: mostrar la lista al usuario y preguntar cual usar
4. Si **no hay archivos**: informar que ejecute `/feature` primero
5. Extraer el `{ID}` del nombre del archivo seleccionado (ej: `FEATURE_ANALISIS_20260202_143052.md` → ID = `20260202_143052`)
6. Este ID se usara para nombrar el archivo de implementacion: `FEATURE_IMPLEMENTACION_{ID}.md`

### PASO 1: Leer y Parsear Plan

1. Leer el archivo `FEATURE_ANALISIS_{ID}.md` completo
2. Extraer todos los componentes con sus codigos:
   - BACK-DB-XXX: Migraciones de base de datos
   - BACK-XXX: Archivos nuevos de backend (modelos, controllers, requests, etc.)
   - MOD-BACK-XXX: Modificaciones a archivos existentes de backend
   - FRONT-XXX: Archivos nuevos de frontend (types, services, pages, etc.)
   - MOD-FRONT-XXX: Modificaciones a archivos existentes de frontend
   - INTEG-XXX: Puntos de integracion (para verificar, no implementar directamente)
3. Para cada componente extraer:
   - Codigo identificador
   - Tipo de componente
   - Archivo destino (path completo)
   - Dependencias
   - Fase de implementacion
   - Esqueleto/codigo proporcionado
   - Si es archivo nuevo o modificacion

### PASO 2: Interpretar Filtro del Usuario

Si el usuario proporciona un parametro, filtrar componentes:

| Si el usuario dice... | Filtrar por... |
|-----------------------|----------------|
| (vacio) | Todos los componentes |
| "backend" | BACK-DB-* + BACK-* + MOD-BACK-* |
| "frontend" | FRONT-* + MOD-FRONT-* |
| "fase 1" | Componentes de fase 1 |
| "fases 1-3" o "fases 1 a 3" | Componentes de fases 1, 2 y 3 |
| "migraciones" o "base de datos" | Solo BACK-DB-* |
| "modelos" | Solo BACK-* de tipo Modelo |
| "controllers" | Solo BACK-* de tipo Controller |
| "rutas" | Solo BACK-* de tipo Rutas |
| "types" o "tipos" | Solo FRONT-* de tipo Types |
| "services" o "servicios" | Solo FRONT-* de tipo Service |
| "pages" o "paginas" | Solo FRONT-* de tipo Page |
| "lo que falta" | Componentes no implementados (verificar existencia de archivos) |
| ID especifico (ej: BACK-001) | Solo ese componente + sus dependencias no satisfechas |
| "todo menos [X]" | Todos excepto los que matchean [X] |

### PASO 3: Verificar Estado Actual

Para cada componente filtrado, verificar:

1. **Si es archivo nuevo**: Verificar que NO existe ya
   - Si existe: Marcar como "YA EXISTE - SALTAR" (no sobreescribir)
   - Si no existe: Proceder con creacion

2. **Si es modificacion**: Verificar que el archivo existe y que el codigo actual coincide
   - Si el archivo no existe: Error - marcar como "ARCHIVO NO ENCONTRADO"
   - Si el codigo actual cambio: Adaptar la modificacion al estado actual
   - Si coincide: Proceder con la modificacion

3. **Verificar dependencias**: Para cada componente, verificar que sus dependencias estan satisfechas
   - Si las dependencias ya estan implementadas: OK
   - Si las dependencias estan en la lista de componentes a implementar: implementarlas primero
   - Si las dependencias no estan satisfechas ni planeadas: Advertir

### PASO 4: Ordenar por Dependencias

Ordenar los componentes filtrados respetando el orden de implementacion:

```
1. BACK-DB-*    → Migraciones (base de datos primero)
2. BACK-* (Modelos) → Modelos Eloquent
3. BACK-* (Requests) → FormRequests de validacion
4. BACK-* (Controllers) → Controladores
5. BACK-* (Observers) → Observers
6. BACK-* (Exports) → Exports
7. BACK-* (Rutas) → Agregar a api.php
8. MOD-BACK-*   → Modificaciones a backend existente
9. FRONT-* (Types) → Interfaces TypeScript
10. FRONT-* (Services) → Servicios API
11. FRONT-* (Hooks) → Custom hooks
12. FRONT-* (Components) → Componentes reutilizables
13. FRONT-* (Pages) → Paginas completas
14. MOD-FRONT-*  → Modificaciones a frontend existente
```

Si un componente depende de otro que no esta en el filtro, ADVERTIR pero no bloquearse.

### PASO 5: Implementar Archivos Nuevos

Para cada archivo nuevo (BACK-* y FRONT-* sin MOD):

#### 5.1 Leer Archivo de Referencia

Antes de crear el archivo, leer el archivo de referencia indicado en el plan:
```
Si BACK-001 dice "Referencia: Seguir patron de Product.php"
→ Leer backend/app/Models/Product.php
→ Copiar estructura exacta (namespace, imports, traits, formato)
→ Adaptar al nuevo modelo
```

#### 5.2 Generar Codigo Completo

Basandose en:
- El esqueleto del plan (`FEATURE_ANALISIS_{ID}.md`)
- El patron del archivo de referencia
- Las convenciones del proyecto

Generar codigo **COMPLETO Y FUNCIONAL**:
- NO usar `// TODO`
- NO usar `// Implementar aqui`
- NO dejar funciones vacias
- Incluir todos los imports
- Incluir todos los tipos
- Incluir toda la logica de negocio

#### 5.3 Crear el Archivo

Usar la herramienta Write para crear el archivo en la ruta indicada.

#### 5.4 Para Migraciones (BACK-DB-*)

Despues de crear la migracion, el nombre del archivo debe seguir la convencion de Laravel:
```bash
# Nombre del archivo: YYYY_MM_DD_HHMMSS_create_[tabla]_table.php
# Usar fecha actual
```

### PASO 6: Aplicar Modificaciones a Archivos Existentes

Para cada modificacion (MOD-BACK-* y MOD-FRONT-*):

1. **Leer el archivo actual completo**
2. **Localizar el codigo que debe cambiar** (buscar el "codigo actual" del plan)
3. **Si el codigo actual coincide**: Aplicar el cambio usando Edit
4. **Si el codigo actual NO coincide**:
   - Analizar las diferencias
   - Adaptar la modificacion al estado actual
   - Si no es posible adaptar: Marcar como "REQUIERE REVISION MANUAL"
5. **Verificar que no se rompio nada**:
   - Los imports siguen siendo validos
   - Las funciones referenciadas existen
   - Los tipos son compatibles

### PASO 7: Ejecutar Migraciones (si corresponde)

Si se crearon migraciones (BACK-DB-*):

```bash
cd backend && php artisan migrate
```

Si la migracion falla:
1. Leer el error
2. Corregir la migracion
3. Reintentar
4. Si sigue fallando: Documentar el error en el reporte

### PASO 8: Verificacion Rapida

#### 8.1 Backend
```bash
# Verificar sintaxis PHP de archivos creados/modificados
cd backend && php -l app/Models/NuevoModelo.php
cd backend && php -l app/Http/Controllers/NuevoController.php

# Verificar que las rutas se registraron
cd backend && php artisan route:list --path=[prefijo]
```

#### 8.2 Frontend
```bash
# Verificar que no hay errores de TypeScript evidentes
# (lectura visual del codigo, verificar imports)
```

#### 8.3 Verificar Integracion
Para cada INTEG-XXX del plan:
- Verificar que los endpoints existen (route:list)
- Verificar que los tipos del frontend coinciden con la respuesta esperada
- Verificar que los servicios API usan los endpoints correctos

### PASO 9: Generar Reporte de Implementacion

Crear `FEATURE_IMPLEMENTACION_{ID}.md` (usando el mismo ID del archivo de plan):

```markdown
# Implementacion de Funcionalidad
ID: {ID}
Fecha: [FECHA]
Basado en: FEATURE_ANALISIS_{ID}.md
Parametro del usuario: "[parametro]"
Interpretacion: [que filtro se aplico]

## Resumen

| Metrica | Valor |
|---------|-------|
| Total en plan | [X] |
| Filtrados para implementar | [X] |
| Implementados exitosamente | [X] |
| Implementacion fallida | [X] |
| Ya existian (saltados) | [X] |
| Requieren revision manual | [X] |

---

## Archivos Creados

### [BACK-DB-001]: [Titulo]
**Estado**: CREADO
**Archivo**: `[path]`
**Tipo**: Migracion
**Migracion ejecutada**: Si / No / Error

---

### [BACK-001]: [Titulo]
**Estado**: CREADO
**Archivo**: `[path]`
**Tipo**: Modelo
**Verificacion sintaxis**: OK / ERROR

---

### [FRONT-001]: [Titulo]
**Estado**: CREADO
**Archivo**: `[path]`
**Tipo**: Types

---

## Archivos Modificados

### [MOD-BACK-001]: [Titulo]
**Estado**: MODIFICADO
**Archivo**: `[path]`

**Cambio realizado**:
```diff
- [codigo anterior]
+ [codigo nuevo]
```

---

### [MOD-FRONT-001]: [Titulo]
...

---

## Implementaciones Fallidas

### [BACK-XXX]: [Titulo]
**Estado**: FALLIDO
**Archivo**: `[path]`
**Razon**: [explicacion]
**Error**: [mensaje de error]
**Accion requerida**: [que hacer manualmente]

---

## Componentes Saltados

| Componente | Razon |
|-----------|-------|
| BACK-002 | Ya existe: backend/app/Models/... |
| FRONT-003 | Fuera del filtro del usuario |

---

## Estado de Migraciones

| Migracion | Tabla | Estado |
|-----------|-------|--------|
| BACK-DB-001 | [tabla] | Ejecutada / Pendiente / Error |
| BACK-DB-002 | [tabla] | Ejecutada / Pendiente / Error |

---

## Verificacion de Rutas

```
[Salida de php artisan route:list --path=...]
```

---

## Archivos Creados/Modificados (resumen)

| Archivo | Accion | Componente |
|---------|--------|-----------|
| `backend/database/migrations/...` | CREADO | BACK-DB-001 |
| `backend/app/Models/...` | CREADO | BACK-001 |
| `backend/app/Http/Controllers/...` | CREADO | BACK-002 |
| `backend/routes/api.php` | MODIFICADO | BACK-003 |
| `frontend/src/types/...` | CREADO | FRONT-001 |
| `frontend/src/services/...` | CREADO | FRONT-002 |
| `frontend/src/pages/...` | CREADO | FRONT-003 |

---

## Componentes Pendientes (no implementados)

| Componente | Titulo | Fase | Razon |
|-----------|--------|------|-------|
| FRONT-003 | Pagina CRUD | 6 | Fuera del filtro "solo backend" |
| ... | ... | ... | ... |

---

## Identificador de Sesion
ID: `{ID}`
Archivos relacionados:
- Plan: `FEATURE_ANALISIS_{ID}.md`
- Implementacion: `FEATURE_IMPLEMENTACION_{ID}.md`
- Pruebas (pendiente): `FEATURE_PRUEBAS_{ID}.md`

## Proximos Pasos

1. Si hay componentes pendientes: `/implement "lo que falta"`
2. Si hay fallidos: Revisar errores y reintentar `/implement [COMPONENTE]`
3. Para probar: `/test-feature` (se detectara automaticamente este archivo)
4. Para probar solo API: `/test-feature "solo api"`
```

## REGLAS CRITICAS

1. **NUNCA sobreescribir archivos existentes sin verificar**
   - Si el archivo ya existe, marcar como "YA EXISTE" y saltar
   - Si es una modificacion (MOD-*), verificar que el codigo actual coincide

2. **El codigo debe ser COMPLETO y FUNCIONAL**
   - No dejar TODOs ni stubs
   - Cada archivo nuevo debe poder usarse inmediatamente
   - Incluir todos los imports, exports, tipos

3. **Respetar el estilo del proyecto**
   - SIEMPRE leer el archivo de referencia antes de crear uno nuevo
   - Copiar indentacion, nomenclatura, estructura
   - Usar las mismas dependencias (Ant Design, React Query, etc.)

4. **Respetar dependencias**
   - No crear un controlador sin que el modelo exista
   - No crear una pagina sin que el servicio API exista
   - No modificar rutas sin que el controlador exista

5. **Migraciones con cuidado**
   - Generar nombre de archivo con timestamp actual
   - Ejecutar `php artisan migrate` despues de crear migraciones
   - Si falla, corregir y reintentar

6. **Verificar despues de implementar**
   - `php -l` para verificar sintaxis PHP
   - `php artisan route:list` para verificar rutas
   - Verificar visualmente que los imports TypeScript son correctos

7. **Backend es fuente de verdad para la API**
   - Los tipos del frontend deben coincidir con la respuesta del backend
   - Los nombres de campo del servicio API deben coincidir con los endpoints

8. **NO inventar codigo fuera del plan**
   - Solo implementar lo que esta en `FEATURE_ANALISIS_{ID}.md`
   - Si algo falta en el plan, documentarlo como "REQUIERE ACTUALIZACION DEL PLAN"
   - No agregar funcionalidades extra no planificadas

9. **Modificaciones deben ser quirurgicas**
   - MOD-* solo cambia las lineas indicadas
   - No reformatear ni refactorizar codigo no relacionado
   - Preservar todo el codigo existente que no necesita cambiar

10. **Documentar todo en el reporte**
    - Cada archivo creado o modificado
    - Cada error encontrado
    - Cada decision tomada durante la implementacion
    - Estado de migraciones

## Output

1. Archivos del proyecto creados y modificados
2. Migraciones ejecutadas (si aplica)
3. Archivo `FEATURE_IMPLEMENTACION_{ID}.md` con el detalle completo (mismo ID del plan)

Al finalizar, mostrar al usuario:
```
Implementacion completada. Archivo: FEATURE_IMPLEMENTACION_{ID}.md
Basado en: FEATURE_ANALISIS_{ID}.md

Resumen:
- Archivos creados: [X]
- Archivos modificados: [X]
- Migraciones ejecutadas: [X]
- Errores: [X]
- Pendientes: [X]

Para probar: /test-feature (se detectara automaticamente este archivo)
Para implementar lo pendiente: /implement "lo que falta"
```
