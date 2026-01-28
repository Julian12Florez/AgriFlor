# /analyze - Agente de Analisis Inteligente

Analiza el proyecto de forma inteligente, detectando automaticamente modulos relacionados y dependencias.

## Uso con Lenguaje Natural

```
/analyze                                    # Todo el proyecto
/analyze transferencias                     # Transferencias + modulos relacionados
/analyze "roles y permisos"                 # Sistema de roles, permisos, usuarios
/analyze "el flujo de compras completo"     # Compras, recepciones, inventario, proveedores
/analyze "como se manejan los productos"    # Productos, stock, unidades, marcas
/analyze "autenticacion y seguridad"        # Auth, JWT, middleware, permisos
/analyze "movimientos de inventario"        # Inventory, transfers, stock, ubicaciones
/analyze "ordenes tecnicas y aplicaciones"  # Technical orders, applications, farm lots
```

El parametro puede ser:
- Una palabra: `inventario`, `compras`, `usuarios`
- Una descripcion: `"el sistema de stock y movimientos"`
- Una pregunta: `"como funcionan las transferencias parciales"`
- Vacio: analiza todo el proyecto

## Instrucciones para Claude

### PASO 1: Interpretar el Parametro

Si el usuario proporciona un parametro, interpretarlo inteligentemente:

1. **Identificar el tema principal** que quiere analizar
2. **Detectar modulos relacionados** automaticamente
3. **Incluir dependencias** que afectan o son afectadas

#### Mapa de Relaciones entre Modulos

```
PRODUCTOS (products)
├── relacionado con → MARCAS (brands)
├── relacionado con → UNIDADES (base_units, packaging_units)
├── afecta → INVENTARIO (inventory)
├── afecta → COMPRAS (purchases)
├── afecta → TRANSFERENCIAS (transfers)
└── afecta → APLICACIONES (applications)

INVENTARIO (inventory)
├── depende de → PRODUCTOS
├── depende de → UBICACIONES (locations)
├── afectado por → COMPRAS (recepciones)
├── afectado por → TRANSFERENCIAS
├── afectado por → SALIDAS (outputs)
└── afectado por → APLICACIONES

COMPRAS (purchases)
├── depende de → PROVEEDORES (suppliers)
├── depende de → PRODUCTOS
├── afecta → INVENTARIO (cuando se recibe)
├── relacionado con → RECEPCIONES (receptions)
└── relacionado con → ADJUNTOS (attachments)

TRANSFERENCIAS (transfers)
├── depende de → PRODUCTOS
├── depende de → UBICACIONES (origen y destino)
├── afecta → INVENTARIO (disminuye origen, aumenta destino)
└── relacionado con → MOVIMIENTOS

USUARIOS (users)
├── relacionado con → ROLES (roles)
├── relacionado con → PERMISOS (permissions)
├── relacionado con → AUTH (login, JWT, tokens)
└── afecta → TODO (autorizacion)

UBICACIONES (locations)
├── relacionado con → FINCAS (farm_lots)
├── afecta → INVENTARIO
├── afecta → TRANSFERENCIAS
└── afecta → APLICACIONES

APLICACIONES (applications)
├── depende de → PRODUCTOS
├── depende de → UBICACIONES/FINCAS
├── afecta → INVENTARIO (consume productos)
└── relacionado con → ORDENES TECNICAS

ORDENES TECNICAS (technical_orders)
├── relacionado con → FINCAS
├── relacionado con → PRODUCTOS
└── genera → APLICACIONES
```

### PASO 2: Determinar Alcance Inteligente

Segun el parametro del usuario, determinar QUE analizar:

| Si el usuario dice... | Analizar estos modulos |
|-----------------------|------------------------|
| "transferencias" | transfers, products, locations, inventory |
| "compras" o "purchases" | purchases, suppliers, products, receptions, inventory |
| "inventario" o "stock" | inventory, products, locations, movements |
| "productos" | products, brands, base_units, packaging_units |
| "usuarios" o "roles" o "permisos" | users, roles, auth, middleware |
| "ubicaciones" o "fincas" | locations, farm_lots, inventory |
| "aplicaciones" | applications, products, farm_lots, inventory, technical_orders |
| "todo el flujo de X" | X + todas sus dependencias y afectaciones |
| (vacio) | TODO el proyecto |

### PASO 3: Buscar Archivos Relevantes

Para cada modulo en el alcance determinado:

**Backend - Buscar en:**
```
backend/app/Models/              → Modelos relacionados
backend/app/Http/Controllers/    → Controladores
backend/app/Http/Requests/       → Validaciones
backend/app/Http/Middleware/     → Middleware (si aplica)
backend/app/Observers/           → Observers
backend/app/Exports/             → Exportaciones
backend/database/migrations/     → Estructura de BD
backend/routes/api.php           → Endpoints
backend/app/Policies/            → Politicas de autorizacion
```

**Frontend - Buscar en:**
```
frontend/src/pages/              → Paginas del modulo
frontend/src/components/         → Componentes relacionados
frontend/src/services/           → Llamadas API
frontend/src/types/              → Tipos/Interfaces
frontend/src/hooks/              → Hooks personalizados
frontend/src/context/            → Contextos (auth, etc)
frontend/src/store/              → Estados Zustand
```

### PASO 4: Analisis Profundo

Para cada archivo encontrado:

#### 4.1 Analisis de Backend

**Modelos - Verificar:**
- [ ] $fillable tiene todos los campos necesarios
- [ ] $casts coincide con tipos de migracion
- [ ] Relaciones definidas correctamente (belongsTo, hasMany, etc.)
- [ ] Relaciones inversas existen en modelos relacionados
- [ ] Scopes utiles definidos
- [ ] Metodos de negocio tienen logica correcta

**Controladores - Verificar:**
- [ ] Todos los metodos tienen validacion de entrada
- [ ] Usa FormRequest o validate()
- [ ] Operaciones criticas usan DB::transaction
- [ ] Manejo de errores con try-catch
- [ ] Respuestas consistentes (estructura JSON)
- [ ] Autorizacion verificada (authorize, can, middleware)
- [ ] No hay SQL injection (DB::raw sin sanitizar)

**Migraciones - Verificar:**
- [ ] Campos coinciden con modelo
- [ ] Indices en campos de busqueda frecuente
- [ ] Foreign keys definidas
- [ ] Campos nullable donde corresponde
- [ ] Valores default correctos

**Rutas - Verificar:**
- [ ] Middleware auth aplicado
- [ ] Agrupacion logica
- [ ] Nombres de rutas consistentes

#### 4.2 Analisis de Frontend

**Servicios API - Verificar:**
- [ ] Endpoints coinciden con backend
- [ ] Manejo de errores
- [ ] Headers correctos (Authorization, Content-Type)

**Types - Verificar:**
- [ ] Interfaces coinciden con respuestas de API
- [ ] Campos opcionales marcados con ?
- [ ] Enums para estados/tipos

**Componentes - Verificar:**
- [ ] Formularios tienen validacion (Zod)
- [ ] Estados de carga (loading)
- [ ] Estados de error manejados
- [ ] Props tipadas correctamente

**Paginas - Verificar:**
- [ ] Permisos verificados antes de mostrar
- [ ] Datos cargados correctamente
- [ ] Navegacion funcional

#### 4.3 Analisis de Interconexiones

**Verificar flujos completos:**
```
Ejemplo: Transferencia de productos
1. Usuario selecciona productos → Products API funciona?
2. Usuario selecciona ubicaciones → Locations API funciona?
3. Se crea transferencia → Transfer API valida correctamente?
4. Se confirma transferencia → Inventory se actualiza atomicamente?
5. Stock origen disminuye → Verificar calculo
6. Stock destino aumenta → Verificar calculo
7. Si falla algo → Rollback funciona?
```

### PASO 5: Detectar Problemas

Categorizar hallazgos:

#### ERRORES CRITICOS [ERR-XXX]
- Vulnerabilidades de seguridad
- Operaciones sin transaccion que deberian tenerla
- Validaciones faltantes en endpoints publicos
- Calculos de stock incorrectos
- Race conditions en operaciones concurrentes

#### INCONSISTENCIAS [INC-XXX]
- Modelo dice X, migracion dice Y
- API retorna X, frontend espera Y
- Validacion backend != validacion frontend
- Relaciones definidas pero no usadas

#### FUNCIONALIDADES INCOMPLETAS [FUN-XXX]
- TODOs en el codigo
- Funciones vacias o con "not implemented"
- Endpoints definidos pero no implementados
- Casos edge no manejados

#### PROBLEMAS DE LOGICA [LOG-XXX]
- Condiciones imposibles
- Calculos que pueden dar resultados incorrectos
- Estados inalcanzables
- Flujos incompletos

#### PROBLEMAS DE INTEGRACION [INT-XXX]
- Modulo A espera X de modulo B, pero B retorna Y
- Eventos no propagados entre modulos
- Datos inconsistentes entre tablas relacionadas

### PASO 6: Generar Reporte Detallado

Crear `ANALISIS_ACTUAL.md`:

```markdown
# Analisis del Proyecto AgriFlor
Fecha: [FECHA]
Solicitud del usuario: "[parametro original]"
Interpretacion: [que entendio Claude]

## Alcance del Analisis

### Modulo Principal
- [Nombre del modulo principal]

### Modulos Relacionados Incluidos
- [Modulo 2] - Razon: [por que se incluyo]
- [Modulo 3] - Razon: [por que se incluyo]

### Archivos Analizados
| Capa | Archivo | Lineas |
|------|---------|--------|
| Backend Model | [path] | [X] |
| Backend Controller | [path] | [X] |
| Frontend Page | [path] | [X] |
| ... | ... | ... |

---

## Resumen de Hallazgos

| Categoria | Cantidad | Criticos |
|-----------|----------|----------|
| Errores | [X] | [X] |
| Inconsistencias | [X] | [X] |
| Incompletos | [X] | [X] |
| Logica | [X] | [X] |
| Integracion | [X] | [X] |
| **TOTAL** | **[X]** | **[X]** |

---

## HALLAZGOS DETALLADOS

### ERR-001: [Titulo descriptivo]
**Severidad**: CRITICA | ALTA | MEDIA | BAJA
**Modulo**: [modulo afectado]
**Archivo**: `[path completo]`
**Linea**: [numero]

**Descripcion**:
[Explicacion clara del problema]

**Codigo actual**:
```[lang]
[codigo problematico]
```

**Por que es un problema**:
[Explicacion del impacto]

**Codigo corregido**:
```[lang]
[codigo con la correccion]
```

**Modulos afectados**:
- [Modulo X] - [como le afecta]
- [Modulo Y] - [como le afecta]

---

### INC-001: [Titulo]
**Archivos involucrados**:
- Backend: `[path]` linea [X]
- Frontend: `[path]` linea [X]

**Inconsistencia**:
[Descripcion de la diferencia]

**Backend dice**:
```[lang]
[codigo/estructura backend]
```

**Frontend espera**:
```[lang]
[codigo/estructura frontend]
```

**Correccion**:
[Cual archivo cambiar y como]

---

### FUN-001: [Titulo]
**Archivo**: `[path]`
**Linea**: [X]
**Tipo**: TODO | STUB | PARCIAL

**Codigo actual**:
```[lang]
[codigo incompleto]
```

**Que falta**:
[Descripcion de lo que debe implementarse]

**Implementacion sugerida**:
```[lang]
[codigo completo sugerido]
```

---

### LOG-001: [Titulo]
**Archivo**: `[path]`
**Linea**: [X]

**Problema de logica**:
[Descripcion]

**Caso que falla**:
[Ejemplo concreto]

**Correccion**:
```[lang]
[codigo corregido]
```

---

### INT-001: [Titulo]
**Modulos involucrados**: [A] ↔ [B]

**Flujo problematico**:
1. [Paso 1]
2. [Paso 2] ← Aqui falla
3. [Paso 3]

**Problema**:
[Descripcion]

**Correccion**:
[Que cambiar en que modulo]

---

## Mapa de Dependencias Encontradas

```
[Modulo Principal]
    │
    ├── usa → [Modulo A]
    │         └── problema: [descripcion breve]
    │
    ├── afecta → [Modulo B]
    │            └── problema: [descripcion breve]
    │
    └── relacionado → [Modulo C]
                      └── OK
```

---

## Resumen por Archivo

| Archivo | ERR | INC | FUN | LOG | INT | Total |
|---------|-----|-----|-----|-----|-----|-------|
| [path1] | 1 | 0 | 2 | 0 | 1 | 4 |
| [path2] | 0 | 1 | 0 | 1 | 0 | 2 |

---

## Orden de Correccion Recomendado

1. **Primero**: [ERR-XXX] - [razon de prioridad]
2. **Segundo**: [ERR-XXX] - [razon]
3. **Tercero**: [INC-XXX] - [razon]
...

---

## Proximos Pasos

1. Ejecutar `/fix` para corregir automaticamente
2. Ejecutar `/fix ERR-001` para corregir uno especifico
3. Ejecutar `/test [modulo]` para verificar correcciones
```

## IMPORTANTE

- Siempre analizar modulos relacionados, no solo el solicitado
- Incluir codigo actual Y codigo corregido para cada hallazgo
- Priorizar por impacto en el sistema
- El archivo ANALISIS_ACTUAL.md debe tener suficiente detalle para que /fix funcione

## Output
Archivo `ANALISIS_ACTUAL.md` en la raiz del proyecto.
