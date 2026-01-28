# Plan de Fix

**Fecha:** 2026-01-26
**Basado en:** .fix/diagnostico.md
**Error:** Espaciado excesivo en nombre y rol del header

---

## 1. Resumen del Error

**Causa raíz:** Falta de `line-height` en los spans del nombre y rol, heredando valores excesivos que causan desbordamiento vertical.

**Ubicación:** `frontend/src/components/layout/MainLayout.tsx:279-286`

**Riesgo:** BAJO

---

## 2. Alternativas de Fix

### Opcion A: Agregar line-height a spans individuales

**Descripcion:**
Agregar `lineHeight: 1` o `lineHeight: 1.2` directamente a cada span.

**Pros:**
- Control granular por elemento
- Cambio minimo

**Contras:**
- Repeticion de estilos

**Riesgo:** Bajo

---

### Opcion B: Usar line-height en contenedor + gap (Recomendada)

**Descripcion:**
Agregar `lineHeight: 1` al contenedor div y usar `gap` para controlar el espaciado entre elementos.

**Pros:**
- Codigo mas limpio
- Control centralizado del espaciado
- Mejor mantenibilidad

**Contras:**
- Ninguno significativo

**Riesgo:** Bajo

---

## 3. Solucion Elegida

**Opcion:** B - line-height en contenedor + gap

**Justificacion:**
- Es la solucion mas limpia y mantenible
- Controla el espaciado de forma centralizada
- `gap: 0` permite espaciado compacto entre nombre y rol

---

## 4. Cambios a Realizar

### Archivo: frontend/src/components/layout/MainLayout.tsx

**Lineas:** 279-286

**Codigo actual (con bug):**
```tsx
<div style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-start' }}>
  <span style={{ fontSize: '14px', fontWeight: 500 }}>
    {user?.name || 'Usuario'}
  </span>
  <span style={{ fontSize: '12px', opacity: 0.8 }}>
    {getRoleDisplayName()}
  </span>
</div>
```

**Codigo nuevo (fix):**
```tsx
<div style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-start', lineHeight: 1.3, gap: 0 }}>
  <span style={{ fontSize: '14px', fontWeight: 500 }}>
    {user?.name || 'Usuario'}
  </span>
  <span style={{ fontSize: '12px', opacity: 0.8 }}>
    {getRoleDisplayName()}
  </span>
</div>
```

**Explicacion del cambio:**
- `lineHeight: 1.3` - Reduce el espaciado vertical heredado, manteniendo legibilidad
- `gap: 0` - Elimina espacio adicional entre los spans (el line-height ya provee suficiente separacion)

---

## 5. Test del Error

### Nota sobre Testing Visual

Este es un problema de CSS/estilo visual. Las opciones de testing son:

1. **Test visual manual** - Verificar en el navegador que el nombre y rol se ven compactos
2. **Snapshot test** - Requiere configuracion adicional de Jest + testing-library
3. **Test E2E con screenshot** - Requiere Cypress o Playwright configurado

**Recomendacion:** Verificacion visual manual dado que:
- El proyecto no tiene tests visuales configurados
- Es un cambio de bajo riesgo
- El fix es facilmente verificable visualmente

### Verificacion Manual

**Antes del fix:**
1. Abrir la aplicacion en el navegador
2. Iniciar sesion
3. Observar: nombre y rol tienen mucho espacio vertical, posiblemente desbordando

**Despues del fix:**
1. Nombre y rol compactos dentro del header
2. Espaciado uniforme y contenido
3. No hay desbordamiento vertical

---

## 6. Verificacion Post-Fix

### Verificacion visual:
- [ ] Nombre del usuario visible y compacto
- [ ] Rol visible debajo del nombre
- [ ] Ambos contenidos dentro del header (altura 64px)
- [ ] Espaciado apropiado (no excesivo)
- [ ] Alineacion correcta con el avatar

### Verificacion funcional:
- [ ] Dropdown del usuario sigue funcionando
- [ ] Hover muestra el menu correctamente
- [ ] Logout funciona

---

## 7. Plan de Rollback

Si el fix causa problemas:

```bash
git checkout -- frontend/src/components/layout/MainLayout.tsx
```

O revertir manualmente a:
```tsx
<div style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-start' }}>
```

---

## 8. Checklist de Implementacion

- [ ] Aplicar cambio en `MainLayout.tsx` linea 279
- [ ] Verificar visualmente en el navegador
- [ ] Probar dropdown de usuario
- [ ] Probar logout

---

## 9. Proximos Pasos

1. Revisar y aprobar este plan
2. Ejecutar `/fix-implement-3` para aplicar el fix
