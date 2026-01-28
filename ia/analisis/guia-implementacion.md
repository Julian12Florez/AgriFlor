# 🚀 Guía de Implementación Completa - AgriFlor Frontend

## 📋 FASE 1: CONFIGURACIÓN INICIAL DEL PROYECTO

### 1.1 Creación del Proyecto Base
```bash
# Crear proyecto con Vite + React + TypeScript
npm create vite@latest agriflor-frontend -- --template react-ts

# Moverse al directorio
cd agriflor-frontend

# Instalar dependencias base
npm install
```

### 1.2 Instalación de Dependencias Principales
```bash
# UI y Estilos
npm install antd tailwindcss @tailwindcss/forms
npm install @ant-design/icons
npm install @ant-design/charts recharts

# Routing y Estado
npm install react-router-dom
npm install @tanstack/react-query
npm install zustand

# Formularios y Validación
npm install react-hook-form @hookform/resolvers zod

# Utilidades
npm install dayjs clsx
npm install react-hot-toast

# PWA
npm install vite-plugin-pwa workbox-window

# Dev Dependencies
npm install -D @types/node
npm install -D eslint-plugin-react-hooks
npm install -D prettier eslint-config-prettier
```

### 1.3 Configuración de Tailwind CSS
```bash
# Inicializar Tailwind
npx tailwindcss init -p
```

## 📁 FASE 2: ESTRUCTURA DE CARPETAS

### 2.1 Crear Estructura Completa Según Mapeo de Módulos
```bash
# Estructura de componentes
mkdir -p src/components/{ui,forms,tables,charts,layout}

# Estructura de páginas específica por módulo
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

# Estructura de servicios y utilidades
mkdir -p src/{hooks,services,store,types,utils,mock}
mkdir -p src/mock/{users,products,brands,suppliers,locations,recipes,orders,purchases,inventory,movements}
```

### 2.2 Estructura Detallada por Módulo
```
src/
├── components/
│   ├── ui/                 # Componentes básicos (Button, Card, Modal, etc.)
│   ├── forms/              # Formularios especializados reutilizables
│   ├── tables/             # Tablas con filtros avanzados
│   ├── charts/             # Gráficos y visualizaciones
│   └── layout/             # Layout principal, sidebar, header
├── pages/
│   ├── auth/               # Login, registro, recuperación
│   ├── dashboard/          # Dashboard principal con métricas
│   ├── master/             # CRUDs auxiliares
│   │   ├── products/       # CRUD productos (/master/products)
│   │   ├── brands/         # CRUD marcas (/master/brands)
│   │   ├── suppliers/      # CRUD proveedores (/master/suppliers)
│   │   └── locations/      # CRUD fincas/bodegas (/master/locations)
│   ├── technical/          # Procesos técnicos
│   │   ├── recipes/        # CRUD recetas (/technical/recipes)
│   │   └── orders/         # CRUD órdenes técnicas (/technical/orders)
│   ├── purchases/          # Módulo de compras (/purchases)
│   ├── warehouse/          # Gestión bodega
│   │   ├── entries/        # Entradas bodega (/warehouse/entries)
│   │   └── exits/          # Salidas bodega (/warehouse/exits)
│   ├── farms/              # Gestión fincas
│   │   ├── receptions/     # Recepciones (/farms/receptions)
│   │   └── transfers/      # Transferencias (/farms/transfers)
│   ├── inventory/          # Inventario y kardex (/inventory)
│   ├── alerts/             # Sistema de alertas (/alerts)
│   ├── reports/            # Reportes (/reports)
│   └── admin/              # Administración
│       ├── users/          # Gestión usuarios (/admin/users)
│       └── config/         # Configuración (/admin/config)
├── hooks/                  # Custom hooks por funcionalidad
├── services/               # Mock API y servicios
├── store/                  # Estado global con Zustand
├── types/                  # Tipos TypeScript organizados
├── utils/                  # Funciones utilitarias
└── mock/                   # Data mock organizada por módulo
    ├── users.ts            # Mock usuarios y roles
    ├── products.ts         # Mock productos y categorías
    ├── brands.ts           # Mock marcas
    ├── suppliers.ts        # Mock proveedores
    ├── locations.ts        # Mock fincas y bodegas
    ├── recipes.ts          # Mock recetas técnicas
    ├── orders.ts           # Mock órdenes técnicas
    ├── purchases.ts        # Mock compras
    ├── inventory.ts        # Mock inventario actual
    ├── movements.ts        # Mock movimientos
    └── index.ts            # Exportador central
```

## 🎨 FASE 3: CONFIGURACIÓN DE TEMA Y ESTILOS

### 3.1 Configurar Ant Design Theme
```typescript
// src/config/theme.ts
import type { ThemeConfig } from 'antd';

export const antdTheme: ThemeConfig = {
  token: {
    colorPrimary: '#4CAF50',
    colorSuccess: '#2E7D32',
    colorWarning: '#FFD54F',
    colorError: '#f5222d',
    colorBgLayout: '#F5F5F5',
    borderRadius: 12,
    fontFamily: 'Inter, -apple-system, BlinkMacSystemFont, sans-serif',
    fontSize: 14,
    controlHeight: 40,
  },
  components: {
    Button: {
      borderRadius: 12,
      controlHeight: 40,
    },
    Card: {
      borderRadius: 16,
    },
    Table: {
      borderRadius: 12,
    },
  },
};
```

### 3.2 Configurar Tailwind
```javascript
// tailwind.config.js
/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#E8F5E8',
          100: '#C8E6C8',
          500: '#4CAF50',
          600: '#43A047',
          700: '#2E7D32',
        },
        accent: {
          yellow: '#FFD54F',
          brown: '#6D4C41',
          light: '#A5D6A7',
        }
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
  ],
}
```

## 🗃 FASE 4: TIPOS TYPESCRIPT FUNDAMENTALES

### 4.1 Crear Tipos Base
```typescript
// src/types/index.ts
export interface User {
  id: string;
  email: string;
  name: string;
  role: 'admin' | 'agronomist' | 'warehouse' | 'supervisor' | 'farm';
  status: 'active' | 'inactive';
  createdAt: Date;
}

export interface Product {
  id: string;
  name: string;
  category: string;
  baseUnit: string;
  status: 'active' | 'inactive';
  brands: Brand[];
  createdAt: Date;
}

export interface Brand {
  id: string;
  name: string;
  status: 'active' | 'inactive';
}

export interface Location {
  id: string;
  name: string;
  type: 'warehouse' | 'farm';
  municipality: string;
  responsible: string;
  status: 'active' | 'inactive';
}

export interface InventoryMovement {
  id: string;
  type: 'entry' | 'exit' | 'transfer' | 'application';
  productId: string;
  productName: string;
  brandId: string;
  brandName: string;
  quantity: number;
  unit: string;
  locationId: string;
  locationName: string;
  batch: string;
  expirationDate: Date;
  unitPrice?: number;
  totalPrice?: number;
  responsibleUser: string;
  relatedDocumentId?: string;
  relatedDocumentType?: string;
  observations?: string;
  createdAt: Date;
}

export interface Purchase {
  id: string;
  supplierId: string;
  supplierName: string;
  orderNumber?: string;
  purchaseDate: Date;
  status: 'purchased' | 'in_transit' | 'received';
  items: PurchaseItem[];
  subtotal: number;
  tax: number;
  total: number;
  observations?: string;
  createdBy: string;
  createdAt: Date;
}

export interface PurchaseItem {
  id: string;
  productId: string;
  productName: string;
  brandId: string;
  brandName: string;
  quantity: number;
  unit: string;
  unitPrice: number;
  subtotal: number;
  batch: string;
  expirationDate: Date;
}

export interface TechnicalRecipe {
  id: string;
  name: string;
  description?: string;
  status: 'active' | 'inactive';
  products: RecipeProduct[];
  createdBy: string;
  createdAt: Date;
}

export interface RecipeProduct {
  id: string;
  productId: string;
  productName: string;
  brandId: string;
  brandName: string;
  quantity: number;
  unit: string;
  observations?: string;
}

export interface TechnicalOrder {
  id: string;
  orderNumber: string;
  scheduledDate: Date;
  farmIds: string[];
  farmNames: string[];
  status: 'approved' | 'applied';
  recipeId?: string;
  recipeName?: string;
  products: OrderProduct[];
  appliedAt?: Date;
  appliedBy?: string;
  responsibleAgronomist: string;
  observations?: string;
  createdAt: Date;
}

export interface OrderProduct {
  id: string;
  productId: string;
  productName: string;
  brandId: string;
  brandName: string;
  quantity: number;
  unit: string;
  observations?: string;
}
```

## 🏗 FASE 5: CONFIGURACIÓN DE SERVICIOS MOCK

### 5.1 Mock API Base
```typescript
// src/services/mockApi.ts
class MockApiService {
  private delay = (ms: number = 500) => new Promise(resolve => setTimeout(resolve, ms));

  async get<T>(endpoint: string): Promise<T> {
    await this.delay();
    // Implementar lógica de mock data por endpoint
    return {} as T;
  }

  async post<T>(endpoint: string, data: any): Promise<T> {
    await this.delay();
    // Implementar lógica de creación
    return {} as T;
  }

  async put<T>(endpoint: string, data: any): Promise<T> {
    await this.delay();
    // Implementar lógica de actualización
    return {} as T;
  }

  async delete(endpoint: string): Promise<void> {
    await this.delay();
    // Implementar lógica de eliminación
  }
}

export const mockApi = new MockApiService();
```

## 🎯 FASE 6: ORDEN DE IMPLEMENTACIÓN DETALLADO POR MÓDULOS

### **6.1 CONFIGURACIÓN BASE (Día 1-2)**
1. **Layout Principal** - Sidebar con navegación exacta según mapeo
2. **Sistema de Autenticación** - Login, Context de usuario, rutas protegidas
3. **Configuraciones Base** - Tema, tipos, mock API

### **6.2 DATOS MAESTROS - CRUDs AUXILIARES (Día 3-7)**
#### **Orden de implementación:**
1. **Productos** (`/master/products`)
   - `ProductsPage.tsx` - Lista con tabla y filtros
   - `ProductCreatePage.tsx` - Formulario crear
   - `ProductEditPage.tsx` - Formulario editar
   - `ProductDetailPage.tsx` - Vista detalle con kardex

2. **Marcas** (`/master/brands`)
   - `BrandsPage.tsx` - Lista simple
   - `BrandCreateModal.tsx` - Modal crear/editar

3. **Proveedores** (`/master/suppliers`)
   - `SuppliersPage.tsx` - Lista con contactos
   - `SupplierCreatePage.tsx` - Formulario completo
   - `SupplierDetailPage.tsx` - Vista con historial

4. **Fincas y Bodegas** (`/master/locations`)
   - `LocationsPage.tsx` - Lista filtrada por tipo
   - `LocationCreatePage.tsx` - Formulario con coordenadas
   - `LocationDetailPage.tsx` - Vista con inventario actual

### **6.3 DASHBOARD Y PROCESOS TÉCNICOS (Día 8-14)**
5. **Dashboard** (`/dashboard`)
   - Cards métricas, gráficos, alertas recientes

6. **Recetas Técnicas** (`/technical/recipes`)
   - `RecipesPage.tsx` - Lista de recetas
   - `RecipeCreatePage.tsx` - Formulario con selector productos múltiple
   - `RecipeDuplicatePage.tsx` - Duplicar receta

7. **Órdenes Técnicas** (`/technical/orders`)
   - `OrdersPage.tsx` - Lista con estados
   - `OrderCreatePage.tsx` - Integración con recetas
   - `OrderApplyPage.tsx` - Proceso aplicación

### **6.4 GESTIÓN COMPRAS (Día 15-21)**
8. **Compras** (`/purchases`)
   - `PurchasesPage.tsx` - Lista con totales
   - `PurchaseCreatePage.tsx` - Formulario con items múltiples
   - `PurchaseReceivePage.tsx` - Proceso recepción

9. **Entradas a Bodega** (`/warehouse/entries`)
   - `EntriesPage.tsx` - Lista con lotes
   - `EntryCreatePage.tsx` - Formulario con validaciones
   - `EntryFromPurchasePage.tsx` - Pre-llenado desde compra

### **6.5 GESTIÓN INVENTARIO (Día 22-28)**
10. **Salidas de Bodega** (`/warehouse/exits`)
    - `ExitsPage.tsx` - Lista con aprobaciones
    - `ExitCreatePage.tsx` - Formulario con validación 5%
    - `ExitApprovePage.tsx` - Proceso aprobación con tokens

11. **Recepción en Finca** (`/farms/receptions`)
    - `ReceptionsPage.tsx` - Lista con validaciones
    - `ReceptionFromExitPage.tsx` - Validación contra salidas

12. **Transferencias** (`/farms/transfers`)
    - `TransfersPage.tsx` - Lista origen/destino
    - `TransferCreatePage.tsx` - Formulario con justificación

13. **Inventario y Kardex** (`/inventory`)
    - `InventoryDashboard.tsx` - Dashboard stock
    - `KardexPage.tsx` - Historial movimientos
    - `ExpirationReport.tsx` - Productos próximos vencer

### **6.6 REPORTES Y PWA (Día 29-35)**
14. **Alertas** (`/alerts`)
    - Dashboard alertas, configuración umbrales

15. **Reportes** (`/reports`)
    - Catálogo reportes, exportaciones mock

16. **PWA y Optimizaciones**
    - Service worker, manifest, optimización bundle

## 🚦 FASE 7: COMANDOS DE DESARROLLO

### 7.1 Scripts de Package.json
```json
{
  "scripts": {
    "dev": "vite",
    "build": "tsc && vite build",
    "preview": "vite preview",
    "lint": "eslint src --ext ts,tsx --report-unused-disable-directives --max-warnings 0",
    "lint:fix": "eslint src --ext ts,tsx --fix",
    "format": "prettier --write src/**/*.{ts,tsx}",
    "type-check": "tsc --noEmit"
  }
}
```

### 7.2 Comandos de Desarrollo Diario
```bash
# Desarrollo
npm run dev

# Verificación de código
npm run lint
npm run type-check

# Construcción
npm run build
npm run preview
```

## ✅ CHECKLIST DE IMPLEMENTACIÓN

### Configuración Inicial
- [ ] Proyecto Vite creado
- [ ] Dependencias instaladas
- [ ] Estructura de carpetas creada
- [ ] Tailwind configurado
- [ ] Ant Design theme configurado

### Tipos y Servicios
- [ ] Tipos TypeScript definidos
- [ ] Mock API service implementado
- [ ] Zustand store configurado
- [ ] React Query configurado

### Componentes Base
- [ ] Layout principal
- [ ] Componentes UI reutilizables
- [ ] Sistema de routing
- [ ] Autenticación

### Módulos por Prioridad
- [ ] Dashboard
- [ ] CRUDs auxiliares
- [ ] Inventario
- [ ] Compras
- [ ] Entradas bodega
- [ ] Recetas técnicas
- [ ] Órdenes técnicas
- [ ] Salidas bodega
- [ ] Recepción finca
- [ ] Transferencias
- [ ] Alertas y reportes

### PWA y Optimizaciones
- [ ] Service worker
- [ ] Manifest.json
- [ ] Optimización bundle
- [ ] Testing

---

**🎯 RESULTADO:** Siguiendo esta guía tendrás un frontend completo y funcional de AgriFlor en aproximadamente 8 semanas de desarrollo.