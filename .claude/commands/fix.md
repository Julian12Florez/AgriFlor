# /fix - Agente de Correccion Inteligente

Corrige automaticamente los problemas detectados por `/analyze`, entendiendo las relaciones entre modulos.

## Uso con Lenguaje Natural

```
/fix                                        # Corrige todo del analisis
/fix "solo errores criticos"                # Solo ERR-* con severidad CRITICA
/fix "problemas de inventario"              # Solo hallazgos del modulo inventario
/fix "las inconsistencias"                  # Solo INC-*
/fix "lo que afecta transferencias"         # Problemas que impactan transferencias
/fix ERR-001                                # Un problema especifico por ID
/fix "primero los de seguridad"             # Prioriza seguridad
/fix "todo menos las funcionalidades"       # Excluye FUN-*
```

El parametro puede ser:
- Vacio: corrige todo
- Un ID especifico: `ERR-001`, `INC-003`
- Una categoria: `"errores"`, `"inconsistencias"`, `"incompletos"`
- Un modulo: `"inventario"`, `"compras"`, `"transferencias"`
- Una descripcion: `"los problemas criticos de stock"`
- Una prioridad: `"primero seguridad, luego logica"`

## Prerequisito

DEBE existir al menos un archivo `ANALISIS_{ID}.md` generado por `/analyze`.
Si no existe ninguno, informar al usuario que ejecute `/analyze` primero.

## Instrucciones para Claude

### PASO 0: Identificar Archivo de Analisis

1. Buscar todos los archivos `ANALISIS_*.md` en la raiz del proyecto
2. Si hay **un solo archivo**: usarlo automaticamente
3. Si hay **multiples archivos**: mostrar la lista al usuario y preguntar cual usar
4. Si **no hay archivos**: informar que ejecute `/analyze` primero
5. Extraer el `{ID}` del nombre del archivo seleccionado (ej: `ANALISIS_20260128_143052.md` → ID = `20260128_143052`)
6. Este ID se usara para nombrar el archivo de correcciones: `CORRECCIONES_{ID}.md`

### PASO 1: Leer y Parsear Analisis

1. Leer el archivo `ANALISIS_{ID}.md` seleccionado en el Paso 0
2. Extraer todos los hallazgos con sus IDs:
   - ERR-XXX: Errores
   - INC-XXX: Inconsistencias
   - FUN-XXX: Funcionalidades incompletas
   - LOG-XXX: Problemas de logica
   - INT-XXX: Problemas de integracion
3. Para cada hallazgo extraer:
   - ID
   - Severidad
   - Modulo(s) afectado(s)
   - Archivo(s) a modificar
   - Codigo actual
   - Codigo corregido sugerido

### PASO 2: Interpretar Parametro del Usuario

Si el usuario proporciona un parametro, filtrar hallazgos:

| Si el usuario dice... | Filtrar por... |
|-----------------------|----------------|
| "errores" o "criticos" | Solo ERR-* |
| "inconsistencias" | Solo INC-* |
| "incompletos" o "funcionalidades" | Solo FUN-* |
| "logica" | Solo LOG-* |
| "integracion" | Solo INT-* |
| "[nombre modulo]" | Hallazgos de ese modulo |
| "seguridad" | ERR-* relacionados con auth/validacion |
| "stock" o "inventario" | Hallazgos que afectan inventory |
| ID especifico | Solo ese hallazgo |
| (vacio) | Todos los hallazgos |

### PASO 3: Ordenar por Prioridad

Ordenar hallazgos filtrados por:

1. **Severidad**: CRITICA > ALTA > MEDIA > BAJA
2. **Tipo**: ERR > LOG > INT > INC > FUN
3. **Dependencias**: Si A depende de B, corregir B primero
4. **Impacto**: Mas modulos afectados = mayor prioridad

### PASO 4: Verificar Antes de Corregir

Para cada hallazgo, ANTES de aplicar la correccion:

1. **Leer el archivo actual**
2. **Verificar que el codigo problematico existe** en la linea indicada
3. **Si el codigo cambio** desde el analisis:
   - Re-analizar ese fragmento
   - Ajustar la correccion si es necesario
   - O marcar como "requiere revision manual"
4. **Verificar que la correccion no rompe nada**:
   - Sintaxis valida
   - Imports necesarios presentes
   - No elimina funcionalidad usada en otro lugar

### PASO 5: Aplicar Correcciones

#### 5.1 Correcciones en Backend (PHP/Laravel)

**Para Modelos:**
```php
// Agregar campos faltantes a $fillable
protected $fillable = [
    'campo_existente',
    'campo_nuevo',  // Agregado por /fix
];

// Corregir $casts
protected $casts = [
    'campo' => 'tipo_correcto',
];

// Agregar relacion faltante
public function relacionFaltante()
{
    return $this->belongsTo(OtroModelo::class);
}
```

**Para Controladores:**
```php
// Agregar validacion faltante
public function store(Request $request)
{
    $validated = $request->validate([
        'campo_requerido' => 'required|string|max:255',
        'campo_opcional' => 'nullable|integer',
    ]);

    // ... resto del codigo
}

// Envolver en transaccion
public function transfer(Request $request)
{
    return DB::transaction(function () use ($request) {
        // Operaciones atomicas
        $origen = Inventory::where(...)->lockForUpdate()->first();
        $origen->quantity -= $request->quantity;
        $origen->save();

        $destino = Inventory::where(...)->lockForUpdate()->first();
        $destino->quantity += $request->quantity;
        $destino->save();

        return response()->json(['success' => true]);
    });
}

// Agregar manejo de errores
try {
    // operacion riesgosa
} catch (\Exception $e) {
    Log::error('Error en operacion: ' . $e->getMessage());
    return response()->json([
        'error' => 'No se pudo completar la operacion'
    ], 500);
}
```

**Para Migraciones (solo si es seguro):**
- NO modificar migraciones ya ejecutadas
- Crear nueva migracion para cambios

#### 5.2 Correcciones en Frontend (TypeScript/React)

**Para Types:**
```typescript
// Corregir interface para que coincida con API
interface Product {
    id: number;
    name: string;
    code: string;
    brand_id: number;
    brand?: Brand;  // Agregado - relacion opcional
    created_at: string;
    updated_at: string;
}
```

**Para Servicios:**
```typescript
// Corregir endpoint
export const getProducts = async (): Promise<Product[]> => {
    const response = await api.get('/products');  // Corregido
    return response.data;
};

// Agregar manejo de errores
export const createProduct = async (data: CreateProductDto): Promise<Product> => {
    try {
        const response = await api.post('/products', data);
        return response.data;
    } catch (error) {
        console.error('Error creating product:', error);
        throw error;
    }
};
```

**Para Componentes:**
```typescript
// Agregar validacion Zod
const schema = z.object({
    name: z.string().min(1, 'El nombre es requerido'),
    quantity: z.number().min(0, 'La cantidad no puede ser negativa'),
});

// Agregar estado de loading
const [loading, setLoading] = useState(false);

// Agregar manejo de errores
const handleSubmit = async (data: FormData) => {
    setLoading(true);
    try {
        await createProduct(data);
        toast.success('Producto creado');
    } catch (error) {
        toast.error('Error al crear producto');
    } finally {
        setLoading(false);
    }
};
```

#### 5.3 Correcciones de Integracion

Cuando el problema involucra multiples archivos:

1. Identificar el archivo "fuente de verdad" (generalmente backend)
2. Corregir archivos dependientes para que coincidan
3. Verificar que el flujo completo funciona

```
Ejemplo: INC-001 - Types no coinciden con API

1. Backend retorna: { id, name, created_at }
2. Frontend espera: { id, nombre, createdAt }

Correccion:
- Opcion A: Cambiar frontend para usar { id, name, created_at }
- Opcion B: Agregar transformer en el servicio

// Preferir Opcion A (menos codigo)
```

### PASO 6: Registrar Cada Correccion

Despues de aplicar cada correccion:

1. Verificar que el archivo se guardo correctamente
2. Registrar en el log de correcciones
3. Si fallo, registrar el error y continuar con la siguiente

### PASO 7: Generar Reporte de Correcciones

Crear `CORRECCIONES_{ID}.md` (usando el mismo ID del archivo de analisis):

```markdown
# Correcciones Aplicadas
ID: {ID}
Fecha: [FECHA]
Basado en: ANALISIS_{ID}.md
Parametro del usuario: "[parametro]"
Interpretacion: [que filtro se aplico]

## Resumen

| Metrica | Valor |
|---------|-------|
| Total en analisis | [X] |
| Filtrados para corregir | [X] |
| Correcciones exitosas | [X] |
| Correcciones fallidas | [X] |
| Requieren revision manual | [X] |

---

## Correcciones Aplicadas Exitosamente

### [ID]: [Titulo]
**Estado**: APLICADO
**Archivo**: `[path]`
**Tipo de cambio**: Edicion | Nuevo codigo | Eliminacion

**Cambio realizado**:
```diff
- [codigo anterior]
+ [codigo nuevo]
```

**Verificacion**: El archivo compila/es valido sintacticamente

---

### [ID]: [Titulo]
...

---

## Correcciones Fallidas

### [ID]: [Titulo]
**Estado**: FALLIDO
**Archivo**: `[path]`
**Razon del fallo**: [explicacion]

**Intento de correccion**:
```[lang]
[lo que se intento]
```

**Error encontrado**:
[descripcion del error]

**Accion requerida**:
[que debe hacer el desarrollador manualmente]

---

## Requieren Revision Manual

### [ID]: [Titulo]
**Estado**: PENDIENTE_REVISION
**Archivo**: `[path]`
**Razon**: [por que no se pudo automatizar]

**Sugerencia**:
[que deberia revisar el desarrollador]

---

## Archivos Modificados

| Archivo | Correcciones | IDs |
|---------|--------------|-----|
| `backend/app/Models/Product.php` | 2 | ERR-001, INC-003 |
| `frontend/src/types/product.ts` | 1 | INC-003 |
| ... | ... | ... |

---

## Modulos Afectados

| Modulo | Archivos Modificados | Estado |
|--------|---------------------|--------|
| Products | 3 | Corregido |
| Inventory | 2 | Corregido |
| Transfers | 1 | Parcial |

---

## Dependencias Entre Correcciones

```
ERR-001 (corregido)
    └── permite → INC-003 (corregido)
                      └── permite → FUN-005 (corregido)

LOG-002 (corregido)
    └── independiente
```

---

## Identificador de Sesion
ID: `{ID}`
Archivos relacionados:
- Analisis: `ANALISIS_{ID}.md`
- Correcciones: `CORRECCIONES_{ID}.md`
- Pruebas (pendiente): `REPORTE_PRUEBAS_{ID}.md`

## Proximos Pasos

1. Revisar correcciones marcadas como "PENDIENTE_REVISION"
2. Ejecutar `/test` para verificar que todo funciona (se detectara automaticamente `CORRECCIONES_{ID}.md`)
3. Si hay fallos, ejecutar `/analyze` nuevamente
4. Hacer commit de los cambios

---

## Hallazgos NO Corregidos (fuera del filtro)

| ID | Titulo | Razon |
|----|--------|-------|
| FUN-002 | Exportacion PDF | No incluido en filtro del usuario |
| ... | ... | ... |
```

### REGLAS CRITICAS

1. **NUNCA romper funcionalidad existente**
   - Si hay duda, marcar como "requiere revision manual"

2. **Respetar el estilo del proyecto**
   - Usar misma indentacion
   - Usar mismos patrones de nombres
   - Usar mismas convenciones

3. **Backend es fuente de verdad**
   - Si hay conflicto, el backend define la estructura
   - Frontend se adapta al backend

4. **Transacciones para operaciones criticas**
   - Stock, transferencias, pagos = siempre DB::transaction

5. **No crear nuevos archivos innecesarios**
   - Preferir editar archivos existentes

6. **Validar sintaxis**
   - PHP: Verificar que no hay errores de sintaxis
   - TypeScript: Verificar tipos correctos

7. **Preservar funcionalidad**
   - No eliminar codigo que se usa en otro lugar
   - Verificar imports/exports

### MANEJO DE CONFLICTOS

Si durante la correccion se detecta que:
- El archivo cambio desde el analisis
- La correccion afectaria otro codigo
- Hay ambiguedad en como corregir

Entonces:
1. NO aplicar la correccion automaticamente
2. Marcar como "PENDIENTE_REVISION"
3. Explicar claramente el conflicto
4. Sugerir opciones de solucion

## Output

1. Archivos del proyecto corregidos
2. Archivo `CORRECCIONES_{ID}.md` con el detalle completo (mismo ID del analisis)

Al finalizar, mostrar al usuario:
```
Correcciones completadas. Archivo: CORRECCIONES_{ID}.md
Basado en: ANALISIS_{ID}.md
Para probar: /test (se detectara automaticamente este archivo)
```
