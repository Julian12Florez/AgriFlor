# Diagnóstico: Eliminar Módulo "Aplicaciones"

**Fecha:** 2026-01-26
**Reportado por:** Usuario
**Estado:** DIAGNOSTICADO

---

## 1. Descripción del Problema

**Situación actual:**
Existe un módulo independiente de "Aplicaciones" en el sistema que no debería existir según la lógica de negocio.

**Lógica correcta del sistema:**
Las aplicaciones de productos (en fincas) se registran a través de:
1. Crear una **Salida** de tipo "consumo" desde bodega
2. Registrar la aplicación asociada a esa salida
3. No se necesita un módulo separado para gestionar aplicaciones

**Comportamiento esperado:**
- NO debe existir un módulo/página independiente de "Aplicaciones"
- Las aplicaciones se gestionan desde el módulo de Salidas (outputs)

**Comportamiento actual:**
- Existe una página `/applications` accesible desde el menú
- Existe un API independiente `/applications`
- Duplica funcionalidad que ya está en Salidas

---

## 2. Archivos a Eliminar/Modificar

### Frontend - ELIMINAR

| Archivo | Descripción |
|---------|-------------|
| `src/pages/applications/ApplicationsPage.tsx` | Página principal del módulo |
| `src/services/applicationService.ts` | Servicio del módulo |
| `src/types/application.types.ts` | Tipos TypeScript |

### Frontend - MODIFICAR

| Archivo | Línea | Cambio |
|---------|-------|--------|
| `src/App.tsx` | 37 | Eliminar import `ApplicationsPage` |
| `src/App.tsx` | 170-177 | Eliminar ruta `/applications` |
| `src/components/layout/MainLayout.tsx` | 88-90 | Eliminar item de menú "Aplicaciones" |
| `src/services/api.ts` | 554-570 | Eliminar `applicationsApi` |

### Backend - NO ELIMINAR (mantener)

Los siguientes archivos del backend **NO se eliminan** porque la funcionalidad de registrar aplicaciones desde salidas sigue siendo necesaria:

| Archivo | Razón para mantener |
|---------|---------------------|
| `ApplicationController.php` | Usado por salidas para registrar aplicaciones |
| `Application.php` (modelo) | Modelo necesario para la BD |
| `ApplicationProduct.php` | Relación muchos-a-muchos |
| Migraciones | Tablas necesarias en BD |
| Rutas en `api.php` | Endpoints usados por salidas |

**Nota:** El endpoint `product-outputs/{id}/applications` y `product-outputs/{id}/register-application` son los que se usan desde Salidas.

---

## 3. Código a Eliminar

### App.tsx - Import (línea 37)
```tsx
// ELIMINAR
import ApplicationsPage from './pages/applications/ApplicationsPage';
```

### App.tsx - Ruta (líneas 170-177)
```tsx
// ELIMINAR
{/* Applications Routes - module: 'technical' (uses products for farm applications) */}
<Route path="/applications" element={
  <ProtectedRoute module="technical" showAccessDenied>
    <MainLayout>
      <ApplicationsPage />
    </MainLayout>
  </ProtectedRoute>
} />
```

### MainLayout.tsx - Menú (líneas 86-90)
```tsx
// ELIMINAR estas líneas del array purchaseChildren
// Aplicaciones usa modulo 'technical' o similar
if (hasModuleAccess('technical') || hasModuleAccess('purchases')) {
  purchaseChildren.push({ key: '/applications', label: 'Aplicaciones' });
}
```

### api.ts - applicationsApi (líneas 554-570)
```tsx
// ELIMINAR
// Applications API
export const applicationsApi = {
  list: (params?: Record<string, any>) =>
    api.get<PaginatedResponse<any>>('/applications', params),

  get: (id: string) =>
    api.get<ApiResponse<any>>(`/applications/${id}`),

  create: (data: any) =>
    api.post<ApiResponse<any>>('/applications', data),

  approve: (id: string) =>
    api.post<ApiResponse<any>>(`/applications/${id}/approve`, {}),

  cancel: (id: string, data: { cancellation_reason: string }) =>
    api.post<ApiResponse<any>>(`/applications/${id}/cancel`, data),
};
```

---

## 4. Análisis de Impacto

### Dependencias del código a eliminar

| Archivo eliminado | Quién lo usa |
|-------------------|--------------|
| `ApplicationsPage.tsx` | Solo `App.tsx` (ruta) |
| `applicationService.ts` | Solo `ApplicationsPage.tsx` |
| `application.types.ts` | Solo `ApplicationsPage.tsx` y `applicationService.ts` |
| `applicationsApi` | Solo `ApplicationsPage.tsx` |

**Impacto:** BAJO - El código a eliminar solo se referencia a sí mismo y a la ruta en App.tsx.

### Funcionalidad que se MANTIENE

- `outputsApi.registerApplication()` - Registrar aplicación desde una salida
- `outputsApi.getApplications()` - Ver aplicaciones de una salida
- Backend: ApplicationController con sus endpoints

---

## 5. Evaluación de Riesgo

| Factor | Nivel |
|--------|-------|
| Dependientes afectados | BAJO (código aislado) |
| Complejidad del fix | BAJO (eliminar archivos y referencias) |
| Cobertura de tests | N/A (no hay tests) |
| Criticidad | BAJO (eliminar funcionalidad duplicada) |

**RIESGO TOTAL: BAJO**

---

## 6. Alcance Recomendado

**Alcance:** Eliminación completa del módulo frontend

**Justificación:**
- El módulo está aislado y no tiene dependencias externas
- La funcionalidad real (registrar aplicaciones) permanece en el módulo de Salidas
- Solo se elimina la duplicación de UI

---

## 7. Verificación Post-Fix

1. El menú NO debe mostrar "Aplicaciones"
2. La ruta `/applications` NO debe existir
3. La funcionalidad de registrar aplicaciones DEBE seguir funcionando desde Salidas
4. No debe haber errores de compilación

---

## 8. Próximos Pasos

1. Revisar y aprobar este diagnóstico
2. Ejecutar `/fix-design-2` para planificar el fix
