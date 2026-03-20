# /dev-agri - Agente Unificado de Desarrollo y Testing

Agente autonomo: Analiza > Entiende > Desarrolla > Prueba con Playwright. Sin reprocesos.

## PRINCIPIO CENTRAL: ENTENDER ANTES DE ACTUAR

**La causa #1 de reprocesos es no entender el estado real antes de codificar.**

Antes de CUALQUIER cambio:
1. Lee el archivo COMPLETO que vas a modificar (no asumir estructura)
2. Lee los archivos que IMPORTAN o USAN ese archivo
3. Si es frontend: haz snapshot de Playwright para VER el estado real en el navegador
4. Si es backend: haz curl para VER la respuesta real de la API
5. Solo despues de entender el estado real, planifica el cambio

---

## FASE 1: DIAGNOSTICO PRECISO

### 1.1 Entender la solicitud

Leer $ARGUMENTS del usuario e identificar:
- **Que quiere**: bug fix, mejora visual, feature nueva, analisis
- **Donde esta el problema**: que modulo, que vista, que endpoint
- **Criterio de exito**: como se ve/funciona cuando esta correcto

### 1.2 Investigar el estado real

**REGLA: No codificar nada hasta tener evidencia del estado actual.**

Para problemas de FRONTEND:
```
1. browser_resize → al viewport relevante (375x667 mobile, 1280x800 desktop)
2. browser_navigate → a la URL del problema
3. browser_snapshot → capturar DOM real (NO adivinar estructura)
4. browser_take_screenshot → evidencia visual del problema
5. SOLO AHORA leer el codigo fuente del componente
```

Para problemas de BACKEND:
```
1. curl al endpoint para ver respuesta real
2. Leer el controller, model, y migration involucrados
3. Verificar que datos realmente retorna la API
4. SOLO AHORA diagnosticar el problema
```

Para problemas de INTEGRACION:
```
1. Hacer ambos: curl al API Y snapshot del frontend
2. Comparar: que datos manda el backend vs que espera el frontend
3. Identificar donde esta el desajuste
```

### 1.3 Leer codigo con contexto

**Orden de lectura segun el modulo afectado:**

Backend (fuente de verdad):
1. `backend/app/Models/` → relaciones, fillable, casts
2. `backend/app/Http/Controllers/Api/` → logica de negocio
3. `backend/app/Http/Resources/` → formato de respuesta API
4. `backend/routes/api.php` → endpoints y middleware

Frontend (se adapta al backend):
1. `frontend/src/pages/` → el componente de la pagina afectada
2. `frontend/src/services/api.ts` → llamadas API
3. `frontend/src/components/` → componentes reutilizables (ResponsiveTable, etc.)

**NO leer archivos que no son relevantes al problema. Ser quirurgico.**

---

## FASE 2: PLANIFICAR CAMBIOS MINIMOS

### 2.1 Definir cambios necesarios

Antes de editar, listar EXACTAMENTE:
- Archivo 1: que cambio y por que
- Archivo 2: que cambio y por que
- Ningun cambio extra. Solo lo necesario.

### 2.2 Anticipar efectos secundarios

Preguntarse:
- Este cambio rompe algun import/export?
- Este cambio afecta desktop si estoy arreglando mobile (o viceversa)?
- Este cambio de backend rompe algun frontend que consume esta API?
- Si agrego un campo a la API, algun frontend lo necesita?

### 2.3 Reglas de seguridad

**Backend:**
- NUNCA modificar migraciones existentes, crear nuevas
- Transacciones para operaciones multi-tabla
- lockForUpdate() para inventario
- Resources con dual naming (camelCase + snake_case)
- Mensajes en espanol

**Frontend:**
- Ant Design 5 + Ant Form (NO Zod, NO React Hook Form)
- ResponsiveTable con mobileColumns + desktopColumns separados
- React Query para data fetching
- Mapeo manual snake_case <-> camelCase
- overflow: hidden en containers mobile para evitar scroll horizontal

---

## FASE 3: APLICAR CAMBIOS

### 3.1 Orden de aplicacion

1. Backend primero (migraciones > modelos > controllers > resources > routes)
2. Frontend despues (types > api.ts > pages/components)

### 3.2 Verificacion post-cambio

Despues de CADA archivo editado:
```bash
# Si es PHP
php -l [archivo_modificado]

# Si es TypeScript (al final de todos los cambios frontend)
cd frontend && npx tsc --noEmit 2>&1 | head -20
```

---

## FASE 4: VERIFICAR CON PLAYWRIGHT (OBLIGATORIO)

**No se puede declarar "listo" sin verificar en el navegador.**

### 4.1 Login

```
1. browser_navigate → http://localhost:5173/login
2. browser_snapshot → verificar form visible
3. Llenar credenciales (ver tabla abajo)
4. browser_click → boton login
5. browser_wait_for → esperar dashboard
```

### Credenciales

| Modulo | Email | Password |
|--------|-------|----------|
| General/Admin | admin@agriflor.com | admin123 |
| Compras | compras@agriflor.com | compras123 |
| Bodega/Recepcion | bodega@agriflor.com | bodega123 |
| Finca | finca@agriflor.com | finca123 |
| Reportes | financiero@agriflor.com | financiero123 |

### 4.2 Verificar el cambio

```
1. browser_resize → al viewport donde estaba el problema
2. browser_navigate → a la URL del problema
3. browser_snapshot → verificar que el DOM refleja el cambio
4. browser_take_screenshot → evidencia visual
5. Si el problema era mobile: verificar TAMBIEN desktop (y viceversa)
```

### 4.3 Verificar que no se rompio nada

```
1. browser_console_messages → NO debe haber errores JS nuevos
2. Si se cambio una tabla: verificar que los datos cargan
3. Si se cambio un form: verificar que abre y tiene campos correctos
4. Si se cambio un endpoint: verificar que la UI muestra los datos
```

### Rutas del frontend

| Modulo | Ruta |
|--------|------|
| Dashboard | /dashboard |
| Productos | /master/products |
| Marcas | /master/brands |
| Categorias | /master/categories |
| Proveedores | /master/suppliers |
| Ubicaciones | /master/locations |
| Unidades Base | /master/base-units |
| Unidades Empaque | /master/packaging-units |
| Tipos de Salida | /master/output-types |
| Compras | /purchases |
| Recepciones | /reception |
| Salidas | /outputs |
| Inventario | /inventory |
| Recetas | /technical/recipes |
| Ordenes Tecnicas | /technical/orders |
| Usuarios | /admin/users |

---

## FASE 5: AUTOCORRECCION

Si Playwright muestra que algo no funciona:

1. **Diagnosticar con evidencia**: snapshot + console + network, NO adivinar
2. **Identificar causa raiz**: leer el codigo que causa el error
3. **Corregir de forma precisa**: solo lo necesario
4. **Re-verificar**: otro snapshot + screenshot
5. **Maximo 2 intentos** por problema. Si no se resuelve, documentar.

---

## ANTI-PATRONES (NO HACER)

| NO hacer | SI hacer |
|----------|----------|
| Leer 20 archivos "por si acaso" | Leer solo los archivos del problema |
| Cambiar estructura de columnas sin ver el DOM real | Hacer snapshot primero, luego decidir |
| Asumir que "1 productos" es todo lo que muestra la tabla | Verificar que datos tiene el record via API |
| Arreglar mobile sin verificar desktop | Probar AMBOS viewports |
| Generar reporte de 500 lineas | Comunicar: que se hizo, que se verifico, resultado |
| Agregar overflow:hidden sin saber que causa el scroll | Inspeccionar que elemento desborda |
| Cambiar patrones del proyecto (Zod, axios, etc.) | Usar los patrones existentes (Ant Form, fetch, React Query) |

---

## OUTPUT

Al finalizar, comunicar al usuario de forma concisa:

```
Cambios realizados:
- [archivo]: [que se cambio y por que]

Verificacion Playwright:
- [viewport] [url]: [resultado]

Screenshots: [nombres de archivos]
```

NO generar archivo de reporte MD a menos que el usuario lo pida explicitamente.
