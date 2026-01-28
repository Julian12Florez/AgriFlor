# 📋 Lista de Tareas Futuras - Sistema AgriFlor

Este documento contiene las tareas pendientes para mejorar y completar el sistema AgriFlor una vez finalizada la implementación gradual básica.

## 🔐 Tareas Críticas de Seguridad

### 1.1 Implementar Sistema de Autenticación y Login
- **Descripción**: **CRÍTICO** - Crear sistema completo de autenticación para controlar acceso al sistema
- **Prioridad**: 🔴 **ALTA - CRÍTICO**
- **Ubicación**:
  - `/src/pages/auth/Login.tsx` (CREAR NUEVO)
  - `/src/contexts/AuthContext.tsx` (CREAR NUEVO)
  - `/src/hooks/useAuth.ts` (CREAR NUEVO)
  - `/src/middleware/ProtectedRoute.tsx` (CREAR NUEVO)
- **Funcionalidades principales**:
  - Formulario de login con email/usuario y contraseña
  - Validación de credenciales
  - Manejo de sesiones con tokens JWT
  - Redirección automática a login si no está autenticado
  - Logout seguro
  - Recuperación de contraseña
  - Recordar sesión (optional)
- **Componentes necesarios**:
  - Form de login responsive (mobile-first)
  - Loading states y manejo de errores
  - Protected routes para todas las páginas
  - Header con opción de logout
  - Context provider para estado de autenticación
- **Validaciones de seguridad**:
  - Bloqueo temporal después de intentos fallidos
  - Validación de token en cada request
  - Logout automático por inactividad
  - Encriptación de datos sensibles

### 1.2 Roles y Permisos de Usuario
- **Descripción**: Sistema de roles para controlar acceso a diferentes módulos
- **Prioridad**: 🟡 **MEDIA**
- **Roles sugeridos**:
  - **Administrador**: Acceso completo
  - **Agrónomo**: Órdenes técnicas, recetas, reportes
  - **Bodeguero**: Inventario, compras, salidas, recepción
  - **Supervisor**: Solo lectura y reportes
- **Permisos por módulo**:
  - Crear, leer, actualizar, eliminar por rol
  - Validación en frontend y backend
  - UI condicional según permisos

## 🎯 Tareas Prioritarias de Funcionalidad

### 2.1 Implementar Carga de Productos por Archivo
- **Descripción**: Crear funcionalidad para cargar productos masivamente desde un archivo Excel/CSV
- **Ubicación**: `/src/pages/master/Products.tsx`
- **Componentes necesarios**:
  - Modal de importación con drag & drop
  - Validación de formato de archivo
  - Preview de datos antes de importar
  - Manejo de errores y duplicados
  - Progress bar para carga masiva
- **Formatos soportados**: Excel (.xlsx), CSV (.csv)
- **Campos mínimos requeridos**: Nombre, Marca, Categoría, Tipo de aplicación

### 2.2 Precarga de Productos desde Recetas en Órdenes Técnicas
- **Descripción**: Cuando se selecciona una receta en una orden técnica, los productos deben precargarse automáticamente
- **Ubicación**: `/src/pages/technical/Orders.tsx`
- **Funcionalidades**:
  - Al seleccionar una receta, cargar automáticamente sus productos
  - Permitir editar cantidades de productos precargados
  - Permitir remover productos de la lista
  - Permitir agregar productos adicionales no incluidos en la receta
  - Mantener trazabilidad entre receta y orden
- **Estados**: Productos pueden estar marcados como "De receta" o "Adicional"

### 2.3 Selección de Orden Técnica en Compras
- **Descripción**: En el módulo de compras, permitir seleccionar una orden técnica para generar automáticamente la lista de productos a comprar
- **Ubicación**: `/src/pages/purchases/Purchases.tsx`
- **Funcionalidades**:
  - Dropdown/selector de órdenes técnicas aprobadas
  - Cargar automáticamente productos de la orden seleccionada
  - Calcular cantidades necesarias basadas en la orden
  - Permitir ajustar cantidades y precios
  - Mantener referencia a la orden técnica original
  - Validar que no se compre más de lo necesario sin justificación

## 🔄 Tareas de Integración y Mejoras

## 🔄 Tareas de Mejora de UX

### 4.1 Validaciones Avanzadas
- Validación de coordenadas geográficas en tiempo real
- Validación de NIT/RUT para proveedores
- Validación de fechas (no permitir fechas pasadas en órdenes técnicas)

### 4.2 Mejoras de Interfaz
- Implementar modo oscuro
- Añadir filtros avanzados en todas las tablas
- Implementar búsqueda global en el header
- Añadir tooltips informativos

### 4.3 Funcionalidades de Reportes
- Generar PDF de órdenes técnicas
- Reportes de costos por período
- Dashboard con métricas en tiempo real
- Exportar datos a Excel

## 🔧 Tareas Técnicas

### 5.1 Integración con Backend
- Conectar con API REST real
- Implementar autenticación JWT
- Manejo de estados de carga y error
- Implementar caché inteligente

### 5.2 Optimizaciones
- Lazy loading de componentes
- Virtualización de tablas largas
- Optimización de imágenes
- Service Worker para funcionalidad offline

### 5.3 Testing
- Tests unitarios para componentes
- Tests de integración
- Tests E2E con Cypress
- Coverage mínimo del 80%

## 📊 Trazabilidad Completa

### 6.1 Flujo de Trazabilidad Objetivo
1. **Receta Técnica** → Define qué productos usar
2. **Orden Técnica** → Planifica cuándo y dónde aplicar
3. **Compras** → Adquiere productos necesarios (basado en orden)
4. **Entrada a Inventario** → Registra productos comprados
5. **🚨 SALIDAS** → Entrega productos para aplicación (FALTANTE)
6. **Aplicación en Campo** → Uso real de productos
7. **Reportes de Trazabilidad** → Seguimiento completo

### 6.2 Validaciones de Trazabilidad
- Cada salida debe tener orden técnica asociada
- No se puede aplicar sin registro de salida
- Inventario debe reflejar movimientos reales
- Reportes deben mostrar cadena completa

## 📱 Funcionalidades Móviles

### 7.1 Responsive Design
- Optimizar tablas para dispositivos móviles
- Menú hamburguesa para navegación
- Cards en lugar de tablas en pantallas pequeñas

### 7.2 PWA (Progressive Web App)
- Manifest.json
- Service Worker
- Funcionalidad offline básica
- Notificaciones push

## 📊 Analytics y Monitoreo

### 8.1 Métricas de Uso
- Tracking de acciones de usuario
- Métricas de performance
- Reportes de errores automáticos

### 8.2 Logs y Auditoría
- Log de cambios en datos críticos
- Auditoría de accesos
- Backup automático de datos

## 🎨 Personalización

### 9.1 Temas Personalizables
- Selector de temas de color
- Logo personalizable
- Configuración por empresa/finca

### 9.2 Configuración de Usuario
- Preferencias de interfaz
- Configuración de notificaciones
- Idioma/localización

---

## ⚠️ NOTAS CRÍTICAS

### Sistema de Login - URGENTE
El sistema de **LOGIN Y AUTENTICACIÓN** es crítico y debe implementarse como prioridad inmediata porque:

1. **Sin autenticación el sistema no es seguro ni productivo**
2. **Controla el acceso y protege la información sensible**
3. **Base necesaria para implementar roles y permisos**
4. **Requisito fundamental para cualquier sistema empresarial**

### Orden de Implementación Sugerido
1. 🔴 **Sistema de Login y Autenticación** (CRÍTICO - SEGURIDAD)
2. 🟢 **Carga masiva de productos por archivo** (PRODUCTIVIDAD)
3. 🟡 Roles y permisos de usuario
4. 🟡 Selección de orden técnica en compras
5. 🟡 Precarga de productos desde recetas
6. 🟢 Mejoras de UX y funcionalidades adicionales

---

## 📝 Notas de Implementación

- Cada tarea debe implementarse como feature branch
- Usar conventional commits para el historial
- Documentar cambios en CHANGELOG.md
- Actualizar tests antes de hacer merge

## 🏷️ Etiquetas de Prioridad

- 🔴 **Alta**: Tareas críticas para seguridad y funcionalidad básica (Login, Salidas)
- 🟡 **Media**: Mejoras importantes de UX, trazabilidad y roles
- 🟢 **Baja**: Funcionalidades adicionales o mejoras menores

## 📊 Estado de Tareas Completadas

### ✅ Tareas Implementadas Recientemente
- **Tablas Responsivas**: Implementadas en todos los módulos con patrón unificado
- **Módulo de Recepción**: Responsive table implementada
- **Módulo de Órdenes Técnicas**: Responsive table con expandedRowRender
- **Módulo de Marcas**: Responsive table implementada
- **Módulo de Proveedores**: Responsive table implementada
- **Módulo de Compras**: Responsive table implementada
- **Módulo de Inventario**: Responsive table implementada
- **Módulo de Salidas**: ✅ **YA IMPLEMENTADO** - Responsive table con trazabilidad completa
- **Componente ResponsiveTable**: Unificado para eliminar scroll horizontal
- **Paginación Uniforme**: Implementada en todos los módulos
- **Expandable Rows**: Para mostrar detalles adicionales sin scroll

### 🎯 Próximas Prioridades Inmediatas
1. 🔴 **Login y Autenticación** (Sin esto, el sistema no es productivo)
2. 🟢 **Carga masiva de productos por archivo** (Mejora de productividad)
3. 🟡 **Roles y permisos de usuario** (Control de acceso granular)

---

*Documento creado: 2025-09-22*
*Última actualización: 2025-09-23 - Agregado sistema de Login como prioridad crítica y estado de tareas completadas*