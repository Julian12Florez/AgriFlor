# 🚀 Comandos para Iniciar el Desarrollo de AgriFlor

## 📍 PASO 1: CONFIGURACIÓN INICIAL

### Crear el proyecto en el directorio raíz de AgriFlor
```bash
# Navegar al directorio principal
cd /home/julian/Documentos/AgriFlor

# Crear proyecto React con Vite y TypeScript
npm create vite@latest frontend -- --template react-ts

# Navegar al directorio del frontend
cd frontend

# Instalar dependencias base
npm install
```

## 📦 PASO 2: INSTALACIÓN DE DEPENDENCIAS

### Dependencias de UI y Estilos
```bash
npm install antd @ant-design/icons @ant-design/charts
npm install tailwindcss @tailwindcss/forms
npm install clsx
```

### Dependencias de Routing y Estado
```bash
npm install react-router-dom
npm install @tanstack/react-query
npm install zustand
```

### Dependencias de Formularios
```bash
npm install react-hook-form @hookform/resolvers zod
```

### Dependencias de Utilidades
```bash
npm install dayjs
npm install react-hot-toast
npm install recharts
```

### Dependencias PWA
```bash
npm install vite-plugin-pwa workbox-window
```

### Dependencias de Desarrollo
```bash
npm install -D @types/node
npm install -D eslint-plugin-react-hooks
npm install -D prettier eslint-config-prettier
npm install -D autoprefixer postcss
```

## 🎨 PASO 3: CONFIGURACIÓN DE TAILWIND

```bash
# Inicializar Tailwind CSS
npx tailwindcss init -p
```

## 📁 PASO 4: CREAR ESTRUCTURA DE CARPETAS

```bash
# Crear estructura completa de carpetas según mapeo de módulos
mkdir -p src/components/{ui,forms,tables,charts,layout}
mkdir -p src/pages/auth
mkdir -p src/pages/dashboard
mkdir -p src/pages/master/{products,brands,suppliers,locations}
mkdir -p src/pages/technical/{recipes,orders}
mkdir -p src/pages/purchases
mkdir -p src/pages/warehouse/{entries,exits}
mkdir -p src/pages/farms/{receptions,transfers}
mkdir -p src/pages/inventory
mkdir -p src/pages/reports
mkdir -p src/pages/alerts
mkdir -p src/pages/admin/{users,config}
mkdir -p src/{hooks,services,store,types,utils,mock}
mkdir -p src/mock/{users,products,brands,suppliers,locations,recipes,orders,purchases,inventory,movements}
mkdir -p public/{icons,manifest}
```

## ⚙️ PASO 5: CONFIGURACIONES INICIALES

### Crear archivo de tipos base
```bash
touch src/types/index.ts
```

### Crear archivo de configuración de tema
```bash
touch src/config/theme.ts
```

### Crear archivo de mock API
```bash
touch src/services/mockApi.ts
```

### Crear archivos de mock data según módulos
```bash
# Datos maestros
touch src/mock/users.ts
touch src/mock/products.ts
touch src/mock/brands.ts
touch src/mock/suppliers.ts
touch src/mock/locations.ts

# Procesos técnicos
touch src/mock/recipes.ts
touch src/mock/orders.ts

# Compras e inventario
touch src/mock/purchases.ts
touch src/mock/inventory.ts
touch src/mock/movements.ts

# Utilidades
touch src/mock/index.ts # Exportador central
```

## 🔧 PASO 6: ARCHIVOS DE CONFIGURACIÓN

### Actualizar vite.config.ts
```bash
# El archivo ya existe, se debe editar para incluir PWA plugin
```

### Configurar package.json scripts
```bash
# Agregar scripts útiles para desarrollo
```

## 🚀 PASO 7: COMANDOS DE DESARROLLO

### Verificar que todo funciona
```bash
# Limpiar caché si es necesario
npm run dev
```

### Comandos útiles durante desarrollo
```bash
# Desarrollo con HMR
npm run dev

# Build de producción
npm run build

# Preview del build
npm run preview

# Linting
npm run lint

# Formateo de código
npm run format
```

## 📋 PASO 8: VERIFICACIÓN DE LA INSTALACIÓN

### Verificar que las dependencias están instaladas
```bash
npm list --depth=0
```

### Verificar que el servidor de desarrollo funciona
```bash
npm run dev
# Debería abrir en http://localhost:5173
```

## 🎯 PRÓXIMOS PASOS DESPUÉS DE LA CONFIGURACIÓN

### **ORDEN DE IMPLEMENTACIÓN OBLIGATORIO:**

#### **Fase 1: Configuración Base (Día 1-2)**
1. **Configurar tema de Ant Design** en `src/config/theme.ts`
2. **Crear tipos TypeScript** en `src/types/index.ts`
3. **Implementar mock API** en `src/services/mockApi.ts`
4. **Crear layout principal** con sidebar navigation en `src/components/layout/`

#### **Fase 2: Autenticación y Datos Maestros (Día 3-7)**
5. **Implementar autenticación** en `src/pages/auth/`
6. **CRUDs Auxiliares:**
   - Productos (`/master/products`)
   - Marcas (`/master/brands`)
   - Proveedores (`/master/suppliers`)
   - Fincas y Bodegas (`/master/locations`)

#### **Fase 3: Dashboard y Procesos Técnicos (Día 8-14)**
7. **Desarrollar dashboard** en `src/pages/dashboard/`
8. **Recetas Técnicas** (`/technical/recipes`)
9. **Órdenes Técnicas** (`/technical/orders`)

#### **Fase 4: Gestión Compras e Inventario (Día 15-28)**
10. **Compras** (`/purchases`)
11. **Entradas a Bodega** (`/warehouse/entries`)
12. **Salidas de Bodega** (`/warehouse/exits`)
13. **Recepción en Finca** (`/farms/receptions`)
14. **Transferencias** (`/farms/transfers`)
15. **Inventario y Kardex** (`/inventory`)

#### **Fase 5: Reportes y PWA (Día 29-35)**
16. **Alertas** (`/alerts`)
17. **Reportes** (`/reports`)
18. **PWA y optimizaciones**

## ⚠️ NOTAS IMPORTANTES

- Asegúrate de tener **Node.js 18+** instalado
- Usa **npm** como package manager para consistencia
- El proyecto se creará en `/home/julian/Documentos/AgriFlor/frontend`
- Todos los comandos deben ejecutarse desde el directorio `frontend`

## 🔍 VERIFICACIÓN FINAL

Después de ejecutar todos los comandos, deberías tener:
- ✅ Proyecto Vite funcionando
- ✅ Todas las dependencias instaladas
- ✅ Estructura de carpetas creada
- ✅ Servidor de desarrollo corriendo
- ✅ Listo para comenzar el desarrollo

---

**🎯 ¡Listo para comenzar el desarrollo de AgriFlor!**