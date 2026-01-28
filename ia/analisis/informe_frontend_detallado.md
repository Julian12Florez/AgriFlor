# Informe Detallado del Frontend - Sistema AgriFlor

## Resumen Ejecutivo

El sistema AgriFlor es una aplicación web de gestión agrícola desarrollada en React con TypeScript, utilizando Ant Design como librería de componentes UI. El sistema está diseñado para gestionar toda la cadena de valor de productos agrícolas, desde la administración de datos maestros hasta el control de inventarios y reportes.

## Arquitectura General

### Stack Tecnológico
- **Framework**: React 18+ con TypeScript
- **Librería de UI**: Ant Design (antd)
- **Enrutamiento**: React Router DOM
- **Estado**: Estado local con useState/useEffect
- **Consultas**: React Query (TanStack Query)
- **Build Tool**: Vite
- **Estilos**: CSS-in-JS integrado con Ant Design

### Estructura del Proyecto
```
frontend/src/
├── components/        # Componentes reutilizables
├── pages/            # Páginas organizadas por módulo
├── data/             # Tipos e interfaces de datos
├── services/         # Servicios y APIs
├── config/           # Configuración de temas
├── mock/             # Datos mock para desarrollo
└── types/            # Definiciones de tipos TypeScript
```

## Módulos del Sistema

### 1. MÓDULO DE AUTENTICACIÓN Y NAVEGACIÓN

#### App Component (`App.tsx`)
**Campos y Configuración:**
- `COMPONENT_FLAGS`: Objeto de configuración para habilitar/deshabilitar módulos
  - `dashboard`: boolean
  - `products`: boolean
  - `brands`: boolean
  - `suppliers`: boolean
  - `locations`: boolean
  - `recipes`: boolean
  - `orders`: boolean
  - `purchases`: boolean
  - `outputs`: boolean
  - `reception`: boolean
  - `inventory`: boolean
  - `alerts`: boolean
  - `reports`: boolean
  - `users`: boolean

**Layout Components:**
- `SimpleLayout`: Componente de layout responsivo
  - `isMobile`: boolean - Estado para determinar si es móvil
  - `drawerVisible`: boolean - Control del drawer en móvil
  - `collapsed`: boolean - Estado del sidebar colapsado

**Menú de Navegación:**
```typescript
interface MenuItem {
  key: string;           // Ruta de navegación
  label: string;         // Texto mostrado
  type: 'item' | 'group' // Tipo de elemento
  children?: MenuItem[]; // Subelementos
}
```

### 2. MÓDULO DE TIPOS DE DATOS

#### Interfaces Principales (`types/index.ts`)

##### User Interface
```typescript
interface User {
  id: string;
  email: string;
  name: string;
  role: 'admin' | 'agronomist' | 'warehouse' | 'supervisor' | 'farm';
  status: 'active' | 'inactive';
  createdAt: Date;
  updatedAt: Date;
}
```

##### Product Interface
```typescript
interface Product {
  id: string;
  name: string;
  category: 'fertilizante' | 'pesticida' | 'herbicida' | 'fungicida';
  baseUnit: 'kg' | 'litros' | 'unidades';
  status: 'active' | 'inactive';
  brands: Brand[];
  description?: string;
  createdBy: string;
  createdAt: Date;
  updatedAt: Date;
}
```

##### Brand Interface
```typescript
interface Brand {
  id: string;
  name: string;
  status: 'active' | 'inactive';
  createdAt: Date;
}
```

##### Supplier Interface
```typescript
interface Supplier {
  id: string;
  name: string;
  nit: string;
  address: string;
  city: string;
  phone: string;
  email: string;
  contacts: SupplierContact[];
  status: 'active' | 'inactive';
  paymentTerms: string;
  createdAt: Date;
}
```

##### Location Interface
```typescript
interface Location {
  id: string;
  name: string;
  type: 'warehouse' | 'farm';
  municipality: string;
  address: string;
  coordinates?: {
    lat: number;
    lng: number;
  };
  responsible: string;
  status: 'active' | 'inactive';
  createdAt: Date;
}
```

### 3. MÓDULO DE INVENTARIO Y MOVIMIENTOS

##### InventoryMovement Interface
```typescript
interface InventoryMovement {
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
```

##### Purchase Interface
```typescript
interface Purchase {
  id: string;
  orderNumber: string;
  supplierId: string;
  supplierName: string;
  purchaseDate: Date;
  expectedDelivery?: Date;
  status: 'ordered' | 'in_transit' | 'received' | 'cancelled';
  items: PurchaseItem[];
  subtotal: number;
  tax: number;
  total: number;
  observations?: string;
  attachments: string[];
  createdBy: string;
  receivedBy?: string;
  receivedAt?: Date;
  createdAt: Date;
}
```

### 4. MÓDULO DE PROCESOS TÉCNICOS

##### TechnicalRecipe Interface
```typescript
interface TechnicalRecipe {
  id: string;
  name: string;
  description: string;
  category: string;
  status: 'active' | 'inactive';
  products: RecipeProduct[];
  usageCount: number;
  createdBy: string;
  createdAt: Date;
  lastUsed?: Date;
}
```

##### TechnicalOrder Interface
```typescript
interface TechnicalOrder {
  id: string;
  orderNumber: string;
  scheduledDate: Date;
  farmIds: string[];
  farmNames: string[];
  status: 'draft' | 'approved' | 'applied';
  recipeId?: string;
  recipeName?: string;
  products: OrderProduct[];
  appliedAt?: Date;
  appliedBy?: string;
  responsibleAgronomist: string;
  observations?: string;
  estimatedCost: number;
  createdAt: Date;
}
```

### 5. MÓDULO DE ALMACÉN

##### WarehouseEntry Interface
```typescript
interface WarehouseEntry {
  id: string;
  entryNumber: string;
  type: 'from_purchase' | 'manual' | 'transfer' | 'adjustment';
  purchaseId?: string;
  warehouseId: string;
  entryDate: Date;
  items: EntryItem[];
  responsibleUser: string;
  observations?: string;
  attachments: string[];
  status: 'draft' | 'confirmed';
  confirmedAt?: Date;
  confirmedBy?: string;
}
```

##### WarehouseExit Interface
```typescript
interface WarehouseExit {
  id: string;
  exitNumber: string;
  type: 'technical_order' | 'free_request' | 'transfer';
  technicalOrderId?: string;
  destinationFarmId: string;
  destinationFarmName: string;
  requestedBy: string;
  exitDate: Date;
  items: ExitItem[];
  status: 'pending' | 'approved' | 'in_transit' | 'received' | 'rejected';
  approvalToken?: string;
  approvedBy?: string;
  approvedAt?: Date;
  rejectionReason?: string;
  observations?: string;
}
```

## Componentes de Página

### 1. Dashboard (`pages/Dashboard.tsx`)

**Estados Locales:**
- Navegación: `useNavigate()` para redirección

**Métricas Mostradas:**
- `productos_totales`: 1453 (hardcoded)
- `ordenes_activas`: 28 (hardcoded)
- `compras_mes`: 245000 COP (hardcoded)
- `alertas_activas`: 7 (hardcoded)

**Categorías de Inventario:**
- Fertilizantes: 75%
- Pesticidas: 60%
- Herbicidas: 45%
- Fungicidas: 30%

### 2. Gestión de Usuarios (`pages/admin/Users.tsx`)

**Estados del Componente:**
```typescript
const [users, setUsers] = useState<User[]>([]);
const [loading, setLoading] = useState(false);
const [isModalVisible, setIsModalVisible] = useState(false);
const [editingUser, setEditingUser] = useState<User | null>(null);
const [searchText, setSearchText] = useState('');
const [roleFilter, setRoleFilter] = useState<string | undefined>();
const [statusFilter, setStatusFilter] = useState<string | undefined>();
const [form] = Form.useForm();
```

**Funcionalidades:**
- CRUD completo de usuarios
- Filtros por rol y estado
- Búsqueda por nombre y email
- Validación de formularios

**Roles Disponibles:**
- `admin`: Administrador
- `agronomist`: Agrónomo
- `warehouse`: Bodeguero
- `supervisor`: Supervisor
- `farm`: Operario de Finca

### 3. Gestión de Productos (`pages/master/Products.tsx`)

**Estados del Componente:**
```typescript
const [products, setProducts] = useState<Product[]>(mockProducts);
const [isMobile, setIsMobile] = useState(false);
const [loading, setLoading] = useState(false);
const [isModalVisible, setIsModalVisible] = useState(false);
const [editingProduct, setEditingProduct] = useState<Product | null>(null);
const [form] = Form.useForm();
const [searchText, setSearchText] = useState('');
const [categoryFilter, setCategoryFilter] = useState<string | undefined>();
const [statusFilter, setStatusFilter] = useState<string | undefined>();
```

**Categorías de Productos:**
- `fertilizer`: Fertilizantes
- `insecticide`: Insecticidas
- `herbicide`: Herbicidas
- `fungicide`: Fungicidas

**Campos del Formulario:**
- `name`: Nombre del producto (requerido)
- `category`: Categoría (requerido)
- `baseUnit`: Unidad base (kg/litros/unidades)
- `description`: Descripción (opcional)
- `status`: Estado (activo/inactivo)

### 4. Gestión de Marcas (`pages/master/Brands.tsx`)

**Estados del Componente:**
```typescript
const [brands, setBrands] = useState<Brand[]>(mockBrands);
const [loading, setLoading] = useState(false);
const [isModalVisible, setIsModalVisible] = useState(false);
const [editingBrand, setEditingBrand] = useState<Brand | null>(null);
const [searchText, setSearchText] = useState('');
const [statusFilter, setStatusFilter] = useState<string | undefined>();
const [form] = Form.useForm();
```

**Validaciones del Formulario:**
- `name`: 2-50 caracteres, requerido
- `status`: Requerido (active/inactive)

### 5. Recetas Técnicas (`pages/technical/Recipes.tsx`)

**Estados del Componente:**
```typescript
const [isMobile, setIsMobile] = useState(false);
const [recipes, setRecipes] = useState<Recipe[]>(mockRecipes);
const [loading, setLoading] = useState(false);
const [isModalVisible, setIsModalVisible] = useState(false);
const [editingRecipe, setEditingRecipe] = useState<Recipe | null>(null);
const [searchText, setSearchText] = useState('');
const [categoryFilter, setCategoryFilter] = useState<string | undefined>();
const [statusFilter, setStatusFilter] = useState<string | undefined>();
const [form] = Form.useForm();
```

**Categorías de Recetas:**
- `fertilization`: Fertilización
- `pest_control`: Control de plagas
- `disease_control`: Control de enfermedades
- `weed_control`: Control de malezas
- `other`: Otros

**Estructura de Receta:**
- Información general (nombre, categoría, descripción)
- Lista de productos con cantidades
- Instrucciones de aplicación
- Notas de seguridad

## Componentes Reutilizables

### ResponsiveTable (`components/ResponsiveTable.tsx`)

**Props Interface:**
```typescript
interface ResponsiveTableProps<T> extends Omit<TableProps<T>, 'columns'> {
  mobileColumns: ColumnsType<T>;      // Columnas para móvil
  desktopColumns: ColumnsType<T>;     // Columnas para escritorio
  expandedRowRender?: (record: T) => React.ReactNode; // Render expandido
  entityName?: string;                // Nombre de entidad para paginación
}
```

**Funcionalidades:**
- Detección automática de dispositivo móvil
- Columnas adaptativas
- Paginación responsiva
- Expansión de filas opcional

## Servicios y APIs

### MockApiService (`services/mockApi.ts`)

**Métodos Disponibles:**
```typescript
class MockApiService {
  async get<T>(endpoint: string, params?: Record<string, any>): Promise<T>
  async post<T>(endpoint: string, data: any): Promise<T>
  async put<T>(endpoint: string, data: any): Promise<T>
  async delete(endpoint: string): Promise<ApiResponse<null>>
}
```

**Endpoints Simulados:**
- `/products` - Gestión de productos
- `/users` - Gestión de usuarios
- `/brands` - Gestión de marcas
- `/suppliers` - Gestión de proveedores
- `/locations` - Gestión de ubicaciones
- `/recipes` - Gestión de recetas técnicas
- `/orders` - Gestión de órdenes técnicas
- `/purchases` - Gestión de compras
- `/inventory` - Gestión de inventario

## Datos Mock

### Productos Mock (`data/mockData.ts`)
- 6 productos predefinidos con diferentes categorías
- Incluye fertilizantes, insecticidas, herbicidas y fungicidas
- Datos completos con lotes, fechas de vencimiento y precios

### Marcas Mock
- 4 marcas principales: Yara, Bayer, Monsanto, BASF
- Todas activas por defecto

### Ubicaciones Mock
- 3 fincas y 2 bodegas
- Coordenadas geográficas incluidas
- Responsables asignados

### Recetas Técnicas Mock
- 5 recetas predefinidas
- Cubren las 4 categorías principales
- Productos asociados con cantidades y observaciones

### Órdenes Técnicas Mock
- 5 órdenes con diferentes estados
- Vinculadas a recetas y ubicaciones
- Incluyen responsables y costos estimados

## Funcionalidades Principales

### 1. Gestión de Usuarios
- Creación, edición y eliminación de usuarios
- Roles diferenciados con permisos implícitos
- Filtros por rol y estado
- Búsqueda por nombre y email

### 2. Gestión de Productos
- CRUD completo de productos químicos
- Categorización por tipo de producto
- Gestión de marcas asociadas
- Control de unidades de medida

### 3. Gestión de Marcas
- CRUD simple de marcas
- Control de estado activo/inactivo
- Validaciones de nombres únicos

### 4. Recetas Técnicas
- Creación de fórmulas predefinidas
- Asociación de múltiples productos
- Categorización por tipo de aplicación
- Instrucciones de aplicación y seguridad

### 5. Interfaz Responsiva
- Adaptación automática a dispositivos móviles
- Columnas específicas para cada tamaño de pantalla
- Navegación drawer para móviles
- Formularios optimizados para touch

## Patrones de Diseño Utilizados

### 1. Component Composition
- Composición de componentes reutilizables
- Props interfaces bien definidas
- Separación de lógica de presentación

### 2. Custom Hooks Pattern
- Hook personalizado para detección de móvil
- Reutilización de lógica de estado

### 3. Render Props Pattern
- ResponsiveTable con render functions
- Flexibilidad en la presentación de datos

### 4. Provider Pattern
- ConfigProvider de Ant Design para temas
- QueryClientProvider para gestión de estado

## Características Técnicas

### Responsividad
- Breakpoint móvil: < 768px
- Breakpoint tablet: < 1024px
- Layout adaptativo con collapse automático

### Validaciones
- Validaciones en tiempo real con Ant Design Form
- Mensajes de error personalizados
- Validaciones de longitud y formato

### Estados de Carga
- Loading states en todas las operaciones
- Feedback visual consistente
- Manejo de errores con mensajes

### Navegación
- React Router DOM para SPA
- Rutas protegidas con ProtectedRoute
- Navegación programática con useNavigate

## Configuración y Temas

### Theme Configuration (`config/theme.ts`)
- Configuración centralizada de colores
- Paleta verde para agricultura
- Consistencia visual en toda la aplicación

### Ant Design Theme
- Theme tokens personalizados
- Componentes estilizados
- Variables CSS-in-JS

## Áreas de Mejora Identificadas

### 1. Estado Global
- Implementar Redux o Zustand para estado global
- Evitar prop drilling
- Mejor gestión de datos compartidos

### 2. Validaciones
- Esquemas de validación con Yup o Zod
- Validaciones del lado del servidor
- Mensajes de error más descriptivos

### 3. Performance
- Implementar React.memo para componentes pesados
- Lazy loading de componentes
- Optimización de re-renders

### 4. Testing
- Tests unitarios con Jest/RTL
- Tests de integración
- Coverage de código

### 5. Accesibilidad
- ARIA labels y roles
- Navegación por teclado
- Contraste de colores

## Conclusiones

El frontend de AgriFlor presenta una arquitectura sólida y bien estructurada, con:

**Fortalezas:**
- Tipado fuerte con TypeScript
- Componentes reutilizables bien diseñados
- Interfaz responsiva completa
- Datos mock completos para desarrollo
- Estructura modular clara

**Oportunidades de Mejora:**
- Implementación de estado global
- Testing automatizado
- Optimizaciones de performance
- Validaciones más robustas
- Documentación de componentes

El sistema está preparado para escalabilidad y mantenimiento, con una base sólida para el desarrollo de funcionalidades adicionales.