# /dev-agri - Agente Unificado de Desarrollo y Testing con Playwright

## Resumen
Agente todo-en-uno que analiza backend (PHP/Laravel) y frontend (React/TypeScript/Ant Design), desarrolla cambios seguros sin romper nada, y OBLIGATORIAMENTE prueba con Playwright en `http://localhost:5173`.

## Flujo
```
Input: /dev-agri "descripcion en lenguaje natural"

FASE 1: ANALISIS EXPERTO (Backend + Frontend)
  -> Lee codigo relevante, entiende relaciones, identifica problemas
  -> Conoce patrones del proyecto (UUIDs, HasUuids, Resources, Ant Form, etc.)

FASE 2: DESARROLLO SEGURO
  -> Aplica cambios respetando convenciones existentes
  -> Verifica que no rompe nada (imports, relaciones, rutas)

FASE 3: PLAYWRIGHT TESTING (OBLIGATORIO)
  -> Login en http://localhost:5173/login con el rol apropiado
  -> Navega al modulo afectado, interactua, verifica datos
  -> Screenshots en cada paso

FASE 4: AUTOCORRECCION (si falla Playwright)
  -> Corrige y re-prueba (max 2 intentos)

FASE 5: REPORTE
  -> DEV_AGRI_{ID}.md con todo documentado
```

## Credenciales Playwright
| Rol | Email | Password |
|-----|-------|----------|
| Admin | admin@agriflor.com | admin123 |
| Supervisor | supervisor@agriflor.com | supervisor123 |
| Bodeguero | bodega@agriflor.com | bodega123 |
| Finca | finca@agriflor.com | finca123 |
| Compras | compras@agriflor.com | compras123 |
| Financiero | financiero@agriflor.com | financiero123 |

## Conocimiento Experto Embebido
- Backend: UUIDs, HasUuids, DB::transaction, lockForUpdate, Resources con dual camelCase/snake_case, FormRequest, middleware role/permission, InventoryService FIFO
- Frontend: Ant Design 5 Form (NO Zod), React Query, useAuth/usePermissions, ResponsiveTable, manual snake_case mapping, ProtectedRoute con module access

## Output
Archivo `DEV_AGRI_{ID}.md` con las 5 fases documentadas.
