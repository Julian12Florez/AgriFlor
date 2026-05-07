# /test - Agente de Pruebas con Playwright

Prueba el sistema usando Playwright MCP en el navegador real. Verifica que la UI funciona, los datos cargan, los formularios guardan, y los flujos completos operan correctamente.

## PRINCIPIO: Probar como un usuario real

No probar endpoints con curl. Probar en el navegador como lo haria el usuario:
- Navegar a la pagina
- Ver que los datos cargan
- Llenar formularios
- Hacer click en botones
- Verificar que los resultados aparecen

---

## PREREQUISITOS

- Backend: `cd backend && php artisan serve` (puerto 8000)
- Frontend: `cd frontend && npm run dev` (puerto 5173)

## CREDENCIALES

| Rol | Email | Password |
|-----|-------|----------|
| Admin | admin@agriflor.com | admin123 |
| Bodeguero | bodega@agriflor.com | bodega123 |
| Compras | compras@agriflor.com | compras123 |
| Finca | finca@agriflor.com | finca123 |
| Financiero | financiero@agriflor.com | financiero123 |

---

## PASO 1: Determinar alcance

Leer $ARGUMENTS:
- Vacio → probar modulos principales (dashboard, productos, compras, recepciones, salidas, inventario)
- Un modulo → probar solo ese modulo en profundidad
- "lo que se corrigio" → leer CORRECCIONES_*.md mas reciente y probar los modulos afectados
- "mobile" → probar en viewport 375x667
- "desktop" → probar en viewport 1280x800
- "todo" → probar todo en ambos viewports

## PASO 2: Login

```
1. browser_resize → viewport apropiado
2. browser_navigate → http://localhost:5173/login
3. browser_snapshot → verificar form
4. Llenar email y password del usuario apropiado para el modulo
5. browser_click → boton login
6. browser_wait_for → esperar dashboard o 3 segundos
7. browser_snapshot → confirmar login exitoso
```

## PASO 3: Probar cada modulo

### Para cada modulo en alcance:

#### 3.1 Verificar carga

```
1. browser_navigate → ruta del modulo
2. browser_wait_for → 2 segundos (datos cargando)
3. browser_snapshot → verificar que la tabla/contenido aparece
4. browser_console_messages → verificar NO errores JS criticos
```

**CRITERIO PASS**: La pagina carga, muestra datos (o mensaje "sin datos"), no hay errores JS.
**CRITERIO FAIL**: Pantalla en blanco, error JS, o datos no cargan.

#### 3.2 Verificar tabla de datos (si aplica)

```
1. browser_snapshot → verificar columnas y filas
2. Verificar que los datos son coherentes (no "undefined", no "NaN", no "[object Object]")
3. Si hay paginacion: verificar que muestra conteo
```

#### 3.3 Verificar formulario (si aplica)

```
1. browser_click → boton "Nuevo" o "Crear"
2. browser_snapshot → verificar que el modal/drawer abre con campos correctos
3. Llenar campos con datos de prueba
4. browser_click → boton guardar
5. browser_wait_for → mensaje de exito o error de validacion
6. browser_snapshot → verificar resultado
```

#### 3.4 Verificar responsive (si se solicito)

```
1. browser_resize → 375x667 (mobile)
2. browser_navigate → ruta del modulo
3. browser_snapshot → verificar que se ve correcto
4. browser_take_screenshot → evidencia mobile
5. Verificar: NO scroll horizontal, columnas adaptadas, botones accesibles

6. browser_resize → 1280x800 (desktop)
7. browser_navigate → ruta del modulo
8. browser_snapshot → verificar que se ve correcto
9. browser_take_screenshot → evidencia desktop
```

### Rutas de modulos

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

## PASO 4: Reportar resultados

Formato conciso, directo al punto:

```
## Resultados

| Modulo | Viewport | Carga | Datos | Forms | Resultado |
|--------|----------|-------|-------|-------|-----------|
| Productos | desktop | OK | OK | OK | PASS |
| Recepciones | mobile | OK | OK | - | PASS |
| Compras | desktop | OK | NaN en total | - | FAIL |

## Problemas encontrados

### FAIL: Compras - NaN en total
**URL**: /purchases
**Evidencia**: [screenshot nombre]
**Descripcion**: La columna "Total" muestra NaN para compras sin items
**Causa probable**: Calculo de suma sobre array vacio sin valor default
```

Si el usuario pidio reporte en archivo, crear `REPORTE_PRUEBAS_{ID}.md`.
Si no, reportar directamente en la conversacion.

---

## REGLAS

1. **Probar en el navegador real**: Usar Playwright MCP, no curl
2. **Snapshot antes de actuar**: Siempre snapshot para ver el DOM antes de click/fill
3. **Screenshot como evidencia**: Tomar screenshot de resultados importantes
4. **No inventar datos**: Reportar exactamente lo que se ve en el snapshot
5. **Eficiente**: No probar 50 modulos si el usuario pidio probar 1
6. **Reportar rapido**: Tabla de resultados + detalle de FAILs, nada mas
