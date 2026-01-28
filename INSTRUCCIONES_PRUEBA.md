# 🚀 INSTRUCCIONES PARA PROBAR EL SISTEMA AGRIFLOR

**Fecha:** 2025-11-17
**Estado:** Sistema Completo Funcional con Autenticación

---

## ✅ COMPONENTES IMPLEMENTADOS

### Backend (100% Funcional)
- ✅ Laravel 11 + MySQL 8.0 en Docker
- ✅ 26 tablas migradas y seeders ejecutados
- ✅ 50+ endpoints API REST con JWT Auth
- ✅ Lógica de negocio completa (Compras, Recepciones, Salidas, Inventario)
- ✅ FIFO, regla del 5%, recepciones parciales
- ✅ Autenticación JWT con rutas protegidas

### Frontend (Completamente Integrado)
- ✅ React 19 + TypeScript + Vite
- ✅ Login con JWT implementado
- ✅ Rutas protegidas configuradas
- ✅ Módulos integrados con backend:
  - **Compras**: Crear órdenes, ver detalles
  - **Recepciones**: Ver recepciones, agregar batches parciales
  - **Salidas**: Crear salidas con regla del 5%, aprobar (FIFO)
- ✅ Ant Design + React Query

---

## 🔧 PASO 1: INICIAR EL BACKEND

```bash
# Terminal 1 - Backend
cd /home/julian/Documentos/AgriFlor/backend

# Verificar que Docker está corriendo
docker-compose ps

# Si no está corriendo, iniciar:
docker-compose up -d

# Ver logs en tiempo real (opcional)
docker-compose logs -f app
```

**Verificar que está corriendo:**
- API: http://localhost:8000/api
- phpMyAdmin: http://localhost:8083
  - Usuario: `agriflor_user`
  - Contraseña: `agriflor_pass`

---

## 🎨 PASO 2: INICIAR EL FRONTEND

```bash
# Terminal 2 - Frontend
cd /home/julian/Documentos/AgriFlor/frontend

# Instalar dependencias (solo primera vez o si cambió package.json)
npm install

# Iniciar servidor de desarrollo
npm run dev
```

**Verificar que está corriendo:**
- Frontend: http://localhost:5174

---

## 🔐 PASO 3: PROBAR LOGIN

1. Abrir navegador en **http://localhost:5174**

2. Serás redirigido automáticamente a `/login`

3. **Credenciales del usuario admin:**
   ```
   Email: admin@agriflor.com
   Contraseña: admin123
   ```

4. Click en "Iniciar Sesión"

5. Deberías ver:
   - ✅ Mensaje "¡Inicio de sesión exitoso!"
   - ✅ Redirección automática al Dashboard
   - ✅ Token JWT guardado en localStorage

---

## 📦 PASO 4: PROBAR MÓDULO DE COMPRAS

### 4.1 Ver Compras Existentes
1. Navegar a **"Compras"** en el menú lateral
2. Verás la lista de compras cargadas desde el backend
3. Puedes:
   - Buscar por número de orden o proveedor
   - Filtrar por estado (Ordenado, En Tránsito, Recibido)
   - Click en "Ver" para ver detalles

### 4.2 Crear Nueva Compra
1. Click en **"Nueva Compra"**
2. Llenar el formulario:
   ```
   Proveedor: (Seleccionar de la lista cargada desde backend)
   Fecha de Compra: Hoy
   Ubicación de Destino: (Seleccionar bodega)
   Fecha de Entrega Esperada: +7 días
   ```

3. **Agregar Productos:**
   - Click en "+ Agregar Producto"
   - Seleccionar producto (se cargan desde backend)
   - Cantidad: 10
   - Unidad de Empaque: (Se cargan desde backend)
   - Precio Unitario: 50000
   - Fecha de Vencimiento: (Opcional)

4. Click en **"Crear Orden de Compra"**

5. **Verificar:**
   - ✅ Mensaje "Orden de compra creada exitosamente"
   - ✅ La compra aparece en la lista
   - ✅ En phpMyAdmin: Ver tabla `purchases` - nueva fila
   - ✅ En phpMyAdmin: Ver tabla `purchase_items` - productos

---

## 📥 PASO 5: PROBAR MÓDULO DE RECEPCIONES

### 5.1 Ver Recepciones
1. Navegar a **"Recepción"**
2. Verás lista de recepciones con:
   - Número de recepción
   - Origen (si aplica)
   - Destino
   - Progreso (% recibido)
   - Estado (Pendiente/Parcial/Completada)

### 5.2 Agregar Recepción Parcial
1. Click en **"Ver"** en una recepción pendiente
2. Verás:
   - Detalles de la recepción
   - Productos esperados vs recibidos
   - Historial de batches

3. Click en **"Nueva Recepción Parcial"**

4. Llenar formulario:
   ```
   Fecha de Recepción: Hoy
   Recibido por: (Seleccionar usuario)
   ```

5. Por cada producto:
   ```
   Cantidad a Recibir: (Máximo = cantidad pendiente)
   Estado: Bueno / Dañado / Vencido
   Fecha de Vencimiento: (Opcional)
   ```

6. Click en **"Confirmar Recepción Parcial"**

7. **Verificar:**
   - ✅ Batch agregado al historial
   - ✅ Progreso actualizado
   - ✅ Solo productos "Bueno" van a inventario
   - ✅ En phpMyAdmin:
     - Tabla `reception_batches` - nuevo batch
     - Tabla `inventory` - incremento (solo "good")

---

## 📤 PASO 6: PROBAR MÓDULO DE SALIDAS

### 6.1 Crear Nueva Salida
1. Navegar a **"Salidas"**
2. Click en **"Nueva Salida"**

3. Llenar formulario:
   ```
   Número de Salida: (Auto-generado)
   Fecha de Salida: Hoy
   Ubicación Origen: (Bodega con inventario)
   Ubicación Destino: (Finca o cultivo)
   Usuario Responsable: (Opcional)
   ```

4. **Agregar Productos:**
   - Seleccionar producto
   - Cantidad Solicitada: 80 kg
   - Cantidad Entregada: 84 kg (80 + 5%)

   **Validación de 5%:**
   - ✅ Si ingresas 85 kg, mostrará error
   - ✅ Máximo permitido: 84 kg (80 * 1.05)

5. Click en **"Crear Salida"**

6. **Verificar:**
   - ✅ Salida creada
   - ✅ En phpMyAdmin: Tabla `product_outputs` - nueva salida

### 6.2 Aprobar Salida (Reducir Inventario FIFO)
1. Click en **"Aprobar"** en una salida pendiente
2. **Verificar:**
   - ✅ Mensaje "Inventario actualizado con FIFO"
   - ✅ En phpMyAdmin:
     - Tabla `inventory` - reducción FIFO
     - Tabla `inventory_movements` - registro del movimiento

---

## 📊 PASO 7: VERIFICAR EN phpMyAdmin

1. Abrir **http://localhost:8083**
2. Login:
   ```
   Usuario: agriflor_user
   Contraseña: agriflor_pass
   ```

3. Seleccionar base de datos: `agriflor`

4. **Tablas importantes:**
   ```
   users               - Usuarios del sistema
   purchases           - Órdenes de compra
   purchase_items      - Items de compras
   receptions          - Recepciones
   reception_batches   - Batches parciales
   product_outputs     - Salidas de productos
   inventory           - Inventario actual
   inventory_movements - Historial (kardex)
   ```

---

## 🔍 FLUJO COMPLETO DE PRUEBA

### Escenario: Compra → Recepción → Salida

1. **Crear Compra**
   - Producto: NPK 10-20-20
   - Cantidad: 300 kg
   - Estado: `ordered`

2. **Recepción Parcial 1**
   - Recibir: 120 kg
   - Estado: Good
   - ✅ Inventario: +120 kg

3. **Recepción Parcial 2**
   - Recibir: 90 kg Good + 30 kg Damaged
   - ✅ Inventario: +90 kg (solo good)
   - Total recibido: 210/300 (70%)

4. **Crear Salida**
   - Solicitar: 80 kg
   - Entregar: 84 kg (80 + 5%)
   - Estado: `pending`

5. **Aprobar Salida**
   - ✅ Inventario: -84 kg (FIFO)
   - ✅ Inventario final: 126 kg

6. **Verificar Kardex**
   - Tabla `inventory_movements`
   - Tipo: `entry` (recepciones good)
   - Tipo: `output` (salida aprobada)

---

## 🔐 SEGURIDAD Y AUTENTICACIÓN

### JWT Token
- **Almacenado en:** `localStorage` con key `auth_token`
- **Duración:** Configurado en backend (por defecto 60 minutos)
- **Refresh:** Automático antes de expirar

### Rutas Protegidas
- **Sin token:** Redirección automática a `/login`
- **Con token:** Acceso a todas las rutas del dashboard
- **Logout:** Limpia token y redirige a login

### Verificar Token
```javascript
// Abrir consola del navegador (F12)
localStorage.getItem('auth_token')
// Debería mostrar: "Bearer ey..."
```

---

## 🐛 SOLUCIÓN DE PROBLEMAS

### Backend no responde
```bash
# Ver estado de contenedores
docker-compose ps

# Ver logs
docker-compose logs app

# Reiniciar si es necesario
docker-compose restart
```

### Frontend no carga
```bash
# Verificar errores en consola
npm run dev

# Si hay errores de node_modules:
rm -rf node_modules package-lock.json
npm install
npm run dev
```

### Error de autenticación
```bash
# Verificar que JWT está configurado en backend
docker-compose exec app php artisan config:clear

# Verificar secret key
docker-compose exec app php artisan jwt:secret --force
```

### Error de CORS
- El backend ya tiene CORS configurado en `config/cors.php`
- Si hay problemas, verificar que frontend usa `http://localhost:5174`

---

## 📝 DATOS DE ACCESO RÁPIDO

### Frontend
- **URL:** http://localhost:5174
- **Login:** admin@agriflor.com / admin123

### Backend API
- **Base URL:** http://localhost:8000/api
- **Documentación:** Ver `RESUMEN_COMPLETO_SISTEMA.md`

### Base de Datos
- **phpMyAdmin:** http://localhost:8083
- **Usuario:** agriflor_user
- **Contraseña:** agriflor_pass
- **Base de datos:** agriflor

---

## ✅ CHECKLIST DE VERIFICACIÓN

- [ ] Backend corriendo (http://localhost:8000/api)
- [ ] Frontend corriendo (http://localhost:5174)
- [ ] Login exitoso con admin@agriflor.com
- [ ] Token JWT en localStorage
- [ ] Crear compra desde frontend → Guardada en MySQL
- [ ] Ver recepciones con progreso
- [ ] Agregar batch parcial → Inventario actualizado
- [ ] Crear salida con validación 5%
- [ ] Aprobar salida → FIFO aplicado
- [ ] Verificar kardex en phpMyAdmin

---

## 🎯 PRÓXIMOS PASOS SUGERIDOS

1. **Implementar Módulo de Inventario Frontend**
   - Consultar por ubicación
   - Ver kardex por producto
   - Filtros avanzados

2. **Recuperación de Contraseña**
   - Implementar envío de emails
   - Token de reset password

3. **Roles y Permisos**
   - Instalar Laravel Permission (Spatie)
   - Crear roles: admin, supervisor, bodega, operador
   - Restricciones por rol

4. **Pruebas End-to-End**
   - Cypress o Playwright
   - Automatizar flujos completos

---

**Desarrollado por:** Claude Code (Anthropic)
**Stack:** Laravel 11 + React 19 + MySQL 8.0 + Docker
**Estado:** ✅ Sistema Funcional con Autenticación

