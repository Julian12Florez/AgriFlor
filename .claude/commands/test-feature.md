# /test-feature - Agente de Pruebas y Auto-Correccion de Features

Prueba la funcionalidad implementada por /implement usando Playwright y auto-corrige problemas encontrados.

## PRINCIPIO: Probar el flujo completo como usuario, corregir lo que falle

No solo verificar que la pagina carga. Probar el CRUD completo: crear, ver en tabla, editar, eliminar. Si algo falla, corregirlo y re-probar.

---

## PREREQUISITOS

- Backend: `cd backend && php artisan serve` (puerto 8000)
- Frontend: `cd frontend && npm run dev` (puerto 5173)

## PASO 0: Identificar que probar

1. Leer `FEATURE_IMPLEMENTACION_*.md` mas reciente
2. Identificar que modulos/paginas se crearon o modificaron
3. Si $ARGUMENTS tiene filtro, aplicarlo

## PASO 1: Pruebas API (backend)

Para cada endpoint nuevo:

```bash
# Obtener token
TOKEN=$(curl -s http://localhost:8000/api/auth/login -X POST \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@agriflor.com","password":"admin123"}' | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['token'])")

# Probar LIST
curl -s http://localhost:8000/api/[recurso] \
  -H "Authorization: Bearer $TOKEN" | head -100

# Probar CREATE
curl -s http://localhost:8000/api/[recurso] -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '[datos de prueba]' | head -50

# Probar validacion (enviar datos invalidos)
curl -s http://localhost:8000/api/[recurso] -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{}' | head -50
```

**CRITERIO PASS**: Endpoint retorna datos correctos con status 200/201
**CRITERIO FAIL**: Error 500, datos incorrectos, o validacion no funciona

## PASO 2: Pruebas Playwright (frontend)

```
1. browser_resize → 1280x800
2. Login con admin@agriflor.com
3. browser_navigate → ruta de la nueva pagina
4. browser_wait_for → 2 segundos
5. browser_snapshot → verificar que la pagina carga
```

### 2.1 Verificar tabla
```
1. browser_snapshot → verificar columnas y datos
2. Verificar: no "undefined", no "NaN", no errores
```

### 2.2 Verificar formulario crear
```
1. browser_click → boton "Nuevo/Crear"
2. browser_snapshot → verificar campos del form
3. browser_fill_form → llenar con datos de prueba
4. browser_click → boton guardar
5. browser_wait_for → mensaje exito
6. browser_snapshot → verificar que aparece en tabla
```

### 2.3 Verificar editar
```
1. browser_click → boton editar de una fila
2. browser_snapshot → verificar que carga datos
3. Modificar un campo
4. browser_click → guardar
5. Verificar cambio en tabla
```

### 2.4 Verificar mobile
```
1. browser_resize → 375x667
2. browser_navigate → ruta
3. browser_snapshot → verificar responsive
4. browser_take_screenshot → evidencia
```

## PASO 3: Auto-correccion

Si algo falla:

1. **Diagnosticar**: leer console_messages, network_requests, snapshot
2. **Identificar causa raiz**: leer el codigo del archivo problematico
3. **Corregir**: aplicar fix minimo
4. **Re-probar**: repetir la prueba que fallo
5. **Maximo 2 intentos** por problema

### Problemas comunes y sus fixes:

| Problema | Causa probable | Fix |
|----------|---------------|-----|
| Pagina en blanco | Ruta no registrada en App.tsx | Agregar ruta |
| Tabla sin datos | Endpoint no retorna data.data | Verificar controller response |
| Form no guarda | Mapeo snake_case incorrecto | Corregir mapeo en onFinish |
| 404 en API call | Endpoint no registrado | Agregar en api.php |
| 500 en API | Error PHP en controller | php -l + revisar logs |
| Columnas undefined | Fields no coinciden con API | Corregir dataIndex/render |

## PASO 4: Reporte

Crear `FEATURE_PRUEBAS_{ID}.md`:

```markdown
# Pruebas Feature
ID: {ID}
Implementacion: FEATURE_IMPLEMENTACION_{ID}.md

## Resultados

| Prueba | Resultado | Notas |
|--------|-----------|-------|
| API LIST | PASS | Retorna N registros |
| API CREATE | PASS | Crea correctamente |
| API validacion | PASS | Rechaza datos vacios |
| UI carga pagina | PASS | - |
| UI crear registro | PASS | - |
| UI editar | FIXED | [que se corrigio] |
| UI mobile | PASS | - |

## Auto-correcciones aplicadas
| Archivo | Problema | Fix |
|---------|----------|-----|
| [path] | [que fallaba] | [que se cambio] |

## Pendientes (si hay)
- [ ] [lo que requiere revision manual]
```

## REGLAS

1. **Probar en navegador real**: Usar Playwright, no solo curl
2. **CRUD completo**: No solo verificar que carga
3. **Auto-corregir**: Si falla, intentar fix antes de reportar como FAIL
4. **Maximo 2 intentos**: Si no se resuelve, documentar como pendiente
5. **Mobile obligatorio**: Siempre verificar responsive
