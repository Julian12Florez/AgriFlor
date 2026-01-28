# 🌿 AgriFlor - Frontend Development Specification

**Sistema de Gestión Agrícola con Trazabilidad Total**

Desarrollar un frontend React completo y funcional con data mock para un sistema de gestión agrícola empresarial enfocado en trazabilidad total, control de inventarios y cumplimiento normativo.

## 🛠 STACK TECNOLÓGICO OBLIGATORIO

### Core Technologies
- **React 18.x** + **Vite 4.x** (desarrollo rápido, HMR)
- **React Router 6.x** (navegación SPA)
- **Ant Design 5.x** (componentes UI base)
- **Tailwind CSS 3.x** (utilidades, responsive)
- **TypeScript** (tipado fuerte)

### Librerías Complementarias
- **@tanstack/react-query** (gestión estado servidor/mock)
- **React Hook Form** + **Zod** (formularios y validación)
- **Recharts** o **Ant Design Charts** (visualizaciones)
- **Day.js** (manejo fechas)
- **React Hot Toast** (notificaciones)

### PWA y Funcionalidades Especiales
- **Vite PWA Plugin** (PWA completa)
- **Workbox** (service worker, caché offline)
- Mock de **OneSignal** (notificaciones push)

## 🎨 DISEÑO Y TEMA VISUAL

### Paleta de Colores Agro
```css
:root {
  --primary-green: #4CAF50;     /* Verde aguacate */
  --primary-dark: #2E7D32;      /* Verde oscuro */
  --accent-light: #A5D6A7;      /* Verde claro */
  --secondary-brown: #6D4C41;   /* Café tierra */
  --accent-yellow: #FFD54F;     /* Amarillo semilla */
  --neutral-light: #F5F5F5;     /* Background */
  --neutral-dark: #212121;      /* Texto */
}
```

### Configuración Ant Design Theme
```typescript
const theme = {
  token: {
    colorPrimary: '#4CAF50',
    colorSuccess: '#2E7D32',
    colorWarning: '#FFD54F',
    colorBgLayout: '#F5F5F5',
    borderRadius: 12,
    fontFamily: 'Inter, -apple-system, sans-serif'
  }
}
```

## 📁 ARQUITECTURA DE CARPETAS REQUERIDA

```
src/
├── components/           # Componentes reutilizables
│   ├── ui/              # Componentes básicos (Button, Card, etc.)
│   ├── forms/           # Formularios especializados
│   ├── tables/          # Tablas con filtros avanzados
│   └── charts/          # Gráficos y visualizaciones
├── pages/               # Páginas principales por módulo
│   ├── auth/           # Login, registro
│   ├── dashboard/      # Dashboard principal
│   ├── inventory/      # Inventario y movimientos
│   ├── purchases/      # Compras
│   ├── warehouse/      # Entradas/salidas bodega
│   ├── farms/          # Recepciones y transferencias
│   ├── technical/      # Recetas y órdenes técnicas
│   ├── reports/        # Alertas y reportes
│   └── master/         # CRUDs auxiliares
├── hooks/              # Custom hooks
├── services/           # Mock API y servicios
├── store/              # Estado global (Zustand/Context)
├── types/              # Tipos TypeScript
├── utils/              # Utilidades
└── mock/               # Data mock organizada por módulo
```

## 🗃 ESTRUCTURA DE MOCK DATA CRÍTICA

### Entidades Principales
```typescript
interface Product {
  id: string;
  name: string;
  category: string;
  baseUnit: string;
  status: 'active' | 'inactive';
  brands: Brand[];
}

interface InventoryMovement {
  id: string;
  type: 'entry' | 'exit' | 'transfer' | 'application';
  productId: string;
  quantity: number;
  location: Location;
  batch: string;
  expirationDate: Date;
  responsibleUser: string;
  relatedDocumentId?: string;
  createdAt: Date;
}

interface TechnicalOrder {
  id: string;
  scheduledDate: Date;
  farmId: string;
  status: 'approved' | 'applied';
  products: OrderProduct[];
  recipeId?: string;
  appliedAt?: Date;
  responsibleUser: string;
}
```

## 🔄 FLUJO DE TRAZABILIDAD MOCK

### Estados de Stock por Módulo
1. **Compras** → Genera registro en "stock pendiente"
2. **Entradas Bodega** → **SUMA** al stock de bodega
3. **Salidas Bodega** → **RESTA** del stock de bodega, crea "en tránsito"
4. **Recepción Finca** → **SUMA** al stock de finca específica
5. **Aplicación Técnica** → **RESTA** del stock de finca
6. **Transferencias** → **RESTA** origen, **SUMA** destino

### Mock de Validaciones Críticas
- **Salidas Bodega:** Máximo 105% de lo solicitado en orden técnica
- **Stock Negativo:** Prevención en todas las operaciones
- **Vencimientos:** Alertas automáticas 30/15/7 días antes
- **Aprobaciones:** Simulación de enlaces únicos/tokens

## 📱 ESTRUCTURA DE NAVEGACIÓN Y MÓDULOS

### **Sidebar Navigation Structure**
```
📊 Dashboard
📁 Datos Maestros
  ├── 📦 Productos (/master/products)
  ├── 🏷️ Marcas (/master/brands)
  ├── 🏪 Proveedores (/master/suppliers)
  └── 📍 Fincas y Bodegas (/master/locations)
🧪 Procesos Técnicos
  ├── 📋 Recetas Técnicas (/technical/recipes)
  └── 📝 Órdenes Técnicas (/technical/orders)
🛒 Gestión de Compras
  ├── 💰 Compras (/purchases)
  └── 📥 Entradas a Bodega (/warehouse/entries)
🏛️ Gestión de Inventario
  ├── 📤 Salidas de Bodega (/warehouse/exits)
  ├── 📨 Recepción en Finca (/farms/receptions)
  ├── 🔄 Transferencias (/farms/transfers)
  └── 📊 Inventario y Kardex (/inventory)
📈 Reportes y Alertas
  ├── 🚨 Alertas (/alerts)
  └── 📊 Reportes (/reports)
👥 Administración
  ├── 👤 Usuarios (/admin/users)
  └── ⚙️ Configuración (/admin/config)
```

### **MÓDULOS CRÍTICOS CON RUTAS ESPECÍFICAS**

#### **1. Datos Maestros (CRUD Completos)**
- **Productos:** Lista, Crear, Editar, Detalle con kardex
- **Marcas:** Modal CRUD rápido
- **Proveedores:** CRUD completo con contactos múltiples
- **Fincas/Bodegas:** CRUD con coordenadas y stock actual

#### **2. Procesos Técnicos**
- **Recetas:** CRUD con selector productos múltiple, duplicar, activar/desactivar
- **Órdenes Técnicas:** CRUD con integración recetas, multi-finca, aplicación en campo

#### **3. Gestión Compras e Inventario**
- **Compras:** CRUD con items múltiples, proceso recepción
- **Entradas Bodega:** CRUD con lotes/vencimientos, actualización stock automática
- **Salidas Bodega:** CRUD con aprobación jerárquica, validación 5%, tokens
- **Recepción Finca:** Validación contra salidas, control calidad
- **Transferencias:** Movimientos entre fincas con justificación
- **Inventario:** Dashboard, kardex, reportes vencimientos

## 🔧 FUNCIONALIDADES TÉCNICAS OBLIGATORIAS

### PWA Completa
- **Manifest.json** con íconos agro-temáticos
- **Service Worker** con caché estratégico
- **Offline Fallback** para operaciones críticas
- **Install Prompt** personalizado

### Responsive Design
- **Mobile-First:** Optimización touch para campo
- **Breakpoints:** 320px, 768px, 1024px, 1440px
- **Navigation:** Drawer/sidebar colapsable
- **Tables:** Scroll horizontal en móvil

### Performance
- **Code Splitting:** Por módulo/ruta
- **Lazy Loading:** Componentes pesados
- **Virtualization:** Para tablas grandes
- **Bundle Analysis:** Optimización tamaño

## 📊 REPORTES Y VISUALIZACIONES

### Gráficos Requeridos
- **Consumo por Finca:** Bar chart mensual
- **Stock por Categoría:** Donut chart
- **Vencimientos:** Timeline visual
- **Eficiencia Aplicaciones:** Gauge chart

### Exportaciones Mock
- **PDF:** Reportes formateados con logos
- **Excel:** Datos tabulares complejos
- **CSV:** Datos básicos para análisis

## ⚡ CRITERIOS DE ACEPTACIÓN

### Funcionalidad
✅ **100% Mock Funcional:** Todas las operaciones simuladas
✅ **Flujo Completo:** De compra a aplicación sin interrupciones
✅ **Validaciones:** Todas las reglas de negocio implementadas
✅ **Responsive:** Funcional en todos los dispositivos

### Calidad de Código
✅ **TypeScript Strict:** Sin errores de tipos
✅ **ESLint + Prettier:** Código consistente
✅ **Componentes Reutilizables:** DRY principles
✅ **Testing:** Al menos componentes críticos

### UX/UI
✅ **Identidad Agro:** Paleta de colores coherente
✅ **Navegación Intuitiva:** Máximo 3 clics para cualquier función
✅ **Loading States:** Feedback visual en todas las operaciones
✅ **Error Handling:** Mensajes claros y accionables

## 🚀 ENTREGABLES FINALES

1. **Aplicación PWA Completa** instalable y funcional
2. **Mock Data Realista** con relaciones complejas
3. **Documentación README** con setup y arquitectura
4. **Demo Navegable** con datos de ejemplo
5. **Build de Producción** optimizado y testeado

## 💡 CONSIDERACIONES ESPECIALES

### Preparación para Backend
- **API Layer Abstraído:** Fácil migración a Laravel/Inertia
- **Tipos Compartidos:** Preparados para sincronización
- **Estado Normalizado:** Compatible con bases de datos relacionales

### Escalabilidad
- **Arquitectura Modular:** Fácil adición de nuevos módulos
- **Lazy Loading:** Carga bajo demanda
- **Caching Strategy:** Preparado para datos en tiempo real

---

**🎯 RESULTADO ESPERADO:** Un frontend completo, profesional y funcional que demuestre todas las capacidades del sistema AgriFlor, listo para integración con backend y despliegue en producción.