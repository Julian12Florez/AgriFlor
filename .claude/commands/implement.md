# /implement - Agente de Implementacion

Lee el blueprint de /feature y crea/modifica los archivos con codigo funcional completo.

## PRINCIPIO: Implementar exactamente lo que dice el blueprint

No improvisar. El blueprint tiene el codigo, los archivos, y el orden. Seguirlo al pie de la letra. Si algo del blueprint no tiene sentido al implementar, ajustar y documentar el ajuste.

---

## PASO 0: Encontrar el blueprint

1. Buscar archivos `FEATURE_ANALISIS_*.md` en la raiz
2. Si hay uno: usarlo. Si hay varios: usar el mas reciente
3. Extraer el ID
4. Si $ARGUMENTS tiene filtro ("solo backend", "fase 1", "BACK-001"), aplicarlo

## PASO 1: Leer el blueprint completo

Parsear todos los BACK-XXX y FRONT-XXX. Verificar que:
- Los archivos que se van a crear no existen ya
- Los archivos que se van a modificar existen y tienen la estructura esperada
- Las dependencias estan en el orden correcto

## PASO 2: Implementar en orden

Seguir el orden del blueprint:

### Backend primero:
1. **Migrations** → crear y ejecutar `php artisan migrate`
2. **Models** → crear con HasUuids, relaciones, fillable, casts
3. **FormRequests** → crear con rules
4. **Controllers** → crear con logica de negocio
5. **Resources** → crear con dual naming (camelCase + snake_case)
6. **Routes** → agregar en api.php con middleware correcto

### Frontend despues:
7. **Types** → agregar interfaces
8. **API Service** → agregar endpoints en api.ts
9. **Pages** → crear con ResponsiveTable, Ant Form, React Query
10. **Rutas** → agregar en App.tsx con ProtectedRoute

### Para archivos NUEVOS: usar Write
### Para archivos EXISTENTES: leer primero, luego usar Edit

## PASO 3: Verificar

Despues de implementar TODO:

```bash
# Migraciones
cd backend && php artisan migrate

# PHP syntax (cada archivo nuevo/modificado)
php -l [archivo]

# TypeScript
cd frontend && npx tsc --noEmit 2>&1 | head -20

# API responde
curl -s http://localhost:8000/api/auth/login -X POST \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@agriflor.com","password":"admin123"}' | head -20
```

## PASO 4: Reporte

Crear `FEATURE_IMPLEMENTACION_{ID}.md`:

```markdown
# Implementacion
ID: {ID}
Blueprint: FEATURE_ANALISIS_{ID}.md

## Archivos creados/modificados
| Archivo | Tipo | Estado |
|---------|------|--------|
| [path] | Nuevo | OK |
| [path] | Modificado | OK |

## Verificaciones
- Migracion: OK/FAIL
- PHP syntax: OK/FAIL
- TypeScript: OK/FAIL

## Ajustes al blueprint
[cambios respecto al plan original, si los hubo]
```

## REGLAS

1. **Codigo completo**: No TODOs, no stubs, no "implementar despues"
2. **Patrones del proyecto**: UUIDs, Ant Form, ResponsiveTable, React Query, dual naming, espanol
3. **No modificar funcionalidad existente** a menos que el blueprint lo indique
4. **Ejecutar migraciones**: No solo crearlas
5. **Leer antes de editar**: Siempre leer archivo existente antes de modificarlo
