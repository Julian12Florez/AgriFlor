# /analyze - Agente de Analisis Inteligente

Analiza el proyecto detectando problemas reales con evidencia concreta. No genera hallazgos teoricos.

## PRINCIPIO: Solo reportar lo que puedes PROBAR

Un hallazgo sin evidencia es ruido. Cada problema reportado debe incluir:
- El archivo y linea exacta
- El codigo problematico real (no supuesto)
- Por que es un problema (no "podria ser un problema")
- La correccion concreta

---

## PASO 1: Interpretar la solicitud

Leer $ARGUMENTS del usuario. Si esta vacio, analizar todo. Si tiene contenido, enfocarse en eso.

Modulos del sistema y sus archivos clave:

| Modulo | Backend | Frontend |
|--------|---------|----------|
| Productos | Models/Product, Controllers/ProductController | pages/master/Products |
| Compras | Models/Purchase, Controllers/PurchaseController | pages/purchases/Purchases |
| Recepciones | Models/Reception, Controllers/ReceptionController | pages/reception/Reception |
| Salidas | Models/ProductOutput, Controllers/ProductOutputController | pages/outputs/Outputs |
| Inventario | Models/Inventory, Services/InventoryService | pages/inventory/ |
| Usuarios | Models/User, Controllers/UserController | pages/admin/Users |
| Ubicaciones | Models/Location | pages/master/Locations |
| Ordenes Tecnicas | Models/TechnicalOrder | pages/technical/Orders |

**Dependencias automaticas**: Si analizas un modulo, incluye sus dependencias directas (ej: Recepciones → Compras + Inventario + Productos).

---

## PASO 2: Leer codigo con objetivo claro

**NO leer todo el proyecto.** Leer solo lo relevante al alcance.

Para cada modulo en alcance, leer en este orden:
1. Migration → estructura real de BD
2. Model → relaciones, fillable, casts
3. Controller → logica de negocio, validaciones
4. Resource (si existe) → formato de respuesta API
5. Routes → endpoints, middleware
6. Frontend page → como se consume
7. api.ts → llamadas al backend

**Mientras lees, anotar problemas concretos. No leer pasivamente.**

---

## PASO 3: Buscar problemas reales

### Que buscar (en orden de prioridad):

**ERRORES (ERR)** - Cosas que fallan o producen datos incorrectos:
- Calculos de stock/cantidades incorrectos
- Transacciones faltantes en operaciones multi-tabla
- Queries sin lockForUpdate en inventario
- Campos que fallan con null cuando no deberian
- Respuestas API con campos faltantes que el frontend necesita

**INCONSISTENCIAS (INC)** - Backend dice X, frontend espera Y:
- Campos en Resource que no coinciden con TypeScript interface
- Endpoints en api.ts que no existen en routes/api.php
- Validaciones backend vs reglas Ant Form que no coinciden
- Mapeo snake_case/camelCase incorrecto

**LOGICA (LOG)** - Flujos que no funcionan correctamente:
- Condiciones que nunca se cumplen
- Estados inalcanzables
- Filtros que no filtran
- Paginacion rota

**INTEGRACION (INT)** - Modulos que no se comunican bien:
- Modulo A actualiza dato, modulo B no se entera
- Invalidacion de cache (React Query) faltante
- Eventos/observers que no se disparan

### Que NO reportar:
- Warnings de Ant Design deprecation (bodyStyle, destroyOnClose)
- Warnings de React Router future flags
- Posibles mejoras de rendimiento que no causan problemas
- Falta de comentarios o documentacion
- Patrones que "podrian ser mejores" pero funcionan

---

## PASO 4: Verificar hallazgos con evidencia

Antes de incluir un hallazgo en el reporte:

**Para errores de backend**: ejecutar curl y mostrar la respuesta real
**Para errores de frontend**: hacer snapshot de Playwright y mostrar el DOM real
**Para inconsistencias**: mostrar AMBOS lados (backend Y frontend) con codigo real

Si no puedes probar que el problema existe, NO lo reportes.

---

## PASO 5: Generar reporte

Crear `ANALISIS_{ID}.md` donde ID = timestamp (YYYYMMDD_HHMMSS):

```markdown
# Analisis AgriFlor
ID: {ID}
Fecha: [FECHA]
Alcance: [que se analizo]

## Resumen
| Tipo | Cantidad |
|------|----------|
| ERR | X |
| INC | X |
| LOG | X |
| INT | X |

## Hallazgos

### ERR-001: [Titulo preciso]
**Archivo**: `[path]:[linea]`
**Evidencia**: [curl output / snapshot / codigo real]
**Problema**: [descripcion concreta]
**Codigo actual**:
```[lang]
[codigo real del archivo]
```
**Correccion**:
```[lang]
[codigo corregido]
```
**Impacto**: [que modulos afecta]

---
[repetir para cada hallazgo]

## Orden de correccion
1. [ID] - [razon de prioridad]
2. ...
```

---

## REGLAS

1. **Calidad sobre cantidad**: 3 hallazgos reales > 20 hallazgos teoricos
2. **Evidencia obligatoria**: Si no puedes probar el problema, no lo reportes
3. **Codigo real**: Copiar el codigo del archivo, no inventar como crees que es
4. **Correcciones concretas**: Cada hallazgo incluye el fix exacto, listo para aplicar
5. **Patrones del proyecto**: Respetar Ant Form (no Zod), React Query, ResponsiveTable, etc.
6. **Backend es fuente de verdad**: Si hay conflicto backend/frontend, el frontend se adapta
