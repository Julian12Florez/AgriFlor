import type {
  Product,
  Brand,
  Location,
  Recipe,
  TechnicalOrder,
  ProductOutput,
  Reception
} from './types';

// ==================== MARCAS ====================
export const mockBrands: Brand[] = [
  {
    id: '1',
    name: 'Yara',
    status: 'active',
    createdAt: new Date('2024-01-01')
  },
  {
    id: '2',
    name: 'Bayer',
    status: 'active',
    createdAt: new Date('2024-01-01')
  },
  {
    id: '3',
    name: 'Monsanto',
    status: 'active',
    createdAt: new Date('2024-01-01')
  },
  {
    id: '4',
    name: 'BASF',
    status: 'active',
    createdAt: new Date('2024-01-01')
  }
];

// ==================== UBICACIONES ====================
export const mockLocations: Location[] = [
  {
    id: '1',
    name: 'Finca La Esperanza',
    type: 'farm',
    municipality: 'Fusagasugá',
    address: 'Vereda San José, Km 15',
    coordinates: { lat: 4.3411, lng: -74.3644 },
    responsible_user_id: 'user-1',
    responsible_user: { id: 'user-1', name: 'Juan Pérez', email: 'juan@example.com' },
    status: 'active',
    created_at: '2024-01-10T00:00:00Z'
  },
  {
    id: '2',
    name: 'Bodega Central',
    type: 'warehouse',
    municipality: 'Bogotá',
    address: 'Zona Industrial Puente Aranda',
    coordinates: { lat: 4.6261, lng: -74.1151 },
    responsible_user_id: 'user-2',
    responsible_user: { id: 'user-2', name: 'María González', email: 'maria@example.com' },
    status: 'active',
    created_at: '2024-01-05T00:00:00Z'
  },
  {
    id: '3',
    name: 'Finca El Porvenir',
    type: 'farm',
    municipality: 'La Mesa',
    address: 'Vereda El Carmen, Sector 2',
    coordinates: { lat: 4.6332, lng: -74.4644 },
    responsible_user_id: 'user-3',
    responsible_user: { id: 'user-3', name: 'Carlos Rodríguez', email: 'carlos@example.com' },
    status: 'active',
    created_at: '2024-02-01T00:00:00Z'
  },
  {
    id: '4',
    name: 'Finca San Miguel',
    type: 'farm',
    municipality: 'Silvania',
    address: 'Vereda La Palma, Finca 25',
    coordinates: { lat: 4.4033, lng: -74.3856 },
    responsible_user_id: 'user-4',
    responsible_user: { id: 'user-4', name: 'Ana García', email: 'ana@example.com' },
    status: 'active',
    created_at: '2024-01-15T00:00:00Z'
  },
  {
    id: '5',
    name: 'Bodega Norte',
    type: 'warehouse',
    municipality: 'Chía',
    address: 'Parque Industrial Chía',
    responsible_user_id: 'user-5',
    responsible_user: { id: 'user-5', name: 'Ana Martínez', email: 'anam@example.com' },
    status: 'inactive',
    created_at: '2024-01-20T00:00:00Z'
  }
];

// ==================== PROVEEDORES ====================
export const mockSuppliers = [
  {
    id: 'SUP-001',
    name: 'Distribuidora Agrícola El Campo S.A.S.',
    nit: '900.123.456-1',
    address: 'Calle 45 #67-89, Zona Industrial',
    city: 'Bogotá',
    department: 'Cundinamarca',
    phone: '+57 (1) 345-6789',
    email: 'ventas@elcampo.com.co',
    contactPerson: 'Ing. Roberto Martínez',
    contactPhone: '+57 300-123-4567',
    paymentTerms: '30 días calendario',
    status: 'active'
  },
  {
    id: 'SUP-002',
    name: 'Agroquímicos del Norte Ltda.',
    nit: '800.987.654-2',
    address: 'Carrera 15 #23-45, Sector Industrial',
    city: 'Medellín',
    department: 'Antioquia',
    phone: '+57 (4) 234-5678',
    email: 'comercial@agronorte.com.co',
    contactPerson: 'Dr. Patricia González',
    contactPhone: '+57 310-987-6543',
    paymentTerms: '45 días calendario',
    status: 'active'
  },
  {
    id: 'SUP-003',
    name: 'Fertilizantes y Químicos S.A.',
    nit: '700.456.789-3',
    address: 'Zona Franca Km 12, Bodega 15',
    city: 'Cali',
    department: 'Valle del Cauca',
    phone: '+57 (2) 567-8901',
    email: 'info@fertiquimicos.com.co',
    contactPerson: 'Químico Juan Carlos Ruiz',
    contactPhone: '+57 320-456-7890',
    paymentTerms: '60 días calendario',
    status: 'active'
  }
];

// ==================== PRODUCTOS ====================
export const mockProducts: Product[] = [
  {
    id: '1',
    name: 'Fertilizante NPK 15-15-15',
    brand: 'Yara',
    brandId: '1',
    category: 'Fertilizantes',
    type: 'fertilizer',
    unit: 'kg',
    packagingUnits: [
      { id: '1', name: 'Bulto', baseQuantity: 50, baseUnit: 'kg' },
      { id: '2', name: 'Media Unidad', baseQuantity: 25, baseUnit: 'kg' },
      { id: '3', name: 'kg', baseQuantity: 1, baseUnit: 'kg' }
    ],
    stock: 200,
    minStock: 50,
    activeIngredient: 'Nitrógeno 15%, Fósforo 15%, Potasio 15%',
    expirationDate: new Date('2025-03-15'),
    price: 2500,
    status: 'active',
    createdAt: new Date('2024-01-10'),
    createdBy: 'admin'
  },
  {
    id: '2',
    name: 'Insecticida Deltametrina 2.5%',
    brand: 'Bayer',
    brandId: '2',
    category: 'Insecticidas',
    type: 'insecticide',
    unit: 'L',
    packagingUnits: [
      { id: '1', name: 'Galón', baseQuantity: 4, baseUnit: 'L' },
      { id: '2', name: 'Litro', baseQuantity: 1, baseUnit: 'L' },
      { id: '3', name: 'Media Unidad', baseQuantity: 0.5, baseUnit: 'L' }
    ],
    stock: 15,
    minStock: 5,
    activeIngredient: 'Deltametrina 2.5%',
    expirationDate: new Date('2026-03-20'),
    price: 30000,
    status: 'active',
    createdAt: new Date('2024-01-15'),
    createdBy: 'admin'
  },
  {
    id: '3',
    name: 'Herbicida Glifosato 48%',
    brand: 'Monsanto',
    brandId: '3',
    category: 'Herbicidas',
    type: 'herbicide',
    unit: 'L',
    packagingUnits: [
      { id: '1', name: 'Galón', baseQuantity: 4, baseUnit: 'L' },
      { id: '2', name: 'Litro', baseQuantity: 1, baseUnit: 'L' }
    ],
    stock: 8,
    minStock: 3,
    activeIngredient: 'Glifosato 48%',
    expirationDate: new Date('2025-12-20'),
    price: 36000,
    status: 'active',
    createdAt: new Date('2024-01-12'),
    createdBy: 'admin'
  },
  {
    id: '4',
    name: 'Fungicida Mancozeb 80%',
    brand: 'BASF',
    brandId: '4',
    category: 'Fungicidas',
    type: 'fungicide',
    unit: 'kg',
    packagingUnits: [
      { id: '1', name: 'Saco', baseQuantity: 25, baseUnit: 'kg' },
      { id: '2', name: 'kg', baseQuantity: 1, baseUnit: 'kg' }
    ],
    stock: 25,
    minStock: 10,
    activeIngredient: 'Mancozeb 80%',
    expirationDate: new Date('2025-09-15'),
    price: 18000,
    status: 'active',
    createdAt: new Date('2024-01-20'),
    createdBy: 'admin'
  },
  {
    id: '5',
    name: 'Fertilizante Urea 46%',
    brand: 'Yara',
    brandId: '1',
    category: 'Fertilizantes',
    type: 'fertilizer',
    unit: 'kg',
    packagingUnits: [
      { id: '1', name: 'Bulto', baseQuantity: 50, baseUnit: 'kg' },
      { id: '2', name: 'kg', baseQuantity: 1, baseUnit: 'kg' }
    ],
    stock: 150,
    minStock: 40,
    activeIngredient: 'Nitrógeno 46%',
    expirationDate: new Date('2026-01-30'),
    price: 1800,
    status: 'active',
    createdAt: new Date('2024-01-25'),
    createdBy: 'admin'
  },
  {
    id: '6',
    name: 'Insecticida Imidacloprid 35%',
    brand: 'Bayer',
    brandId: '2',
    category: 'Insecticidas',
    type: 'insecticide',
    unit: 'L',
    stock: 12,
    minStock: 4,
    activeIngredient: 'Imidacloprid 35%',
    expirationDate: new Date('2026-04-05'),
    price: 45000,
    status: 'active',
    createdAt: new Date('2024-02-01'),
    createdBy: 'admin'
  },
  {
    id: '7',
    name: 'Fertilizante Superfosfato Simple',
    brand: 'Yara',
    brandId: '1',
    category: 'Fertilizantes',
    type: 'fertilizer',
    unit: 'kg',
    stock: 80,
    minStock: 20,
    activeIngredient: 'Fósforo (P2O5) 18%',
    expirationDate: new Date('2025-08-30'),
    price: 2200,
    status: 'active',
    createdAt: new Date('2024-02-05'),
    createdBy: 'admin'
  },
  {
    id: '8',
    name: 'Herbicida 2,4-D Amina',
    brand: 'BASF',
    brandId: '4',
    category: 'Herbicidas',
    type: 'herbicide',
    unit: 'L',
    stock: 18,
    minStock: 6,
    activeIngredient: '2,4-D Amina 72%',
    expirationDate: new Date('2025-11-15'),
    price: 28000,
    status: 'active',
    createdAt: new Date('2024-02-10'),
    createdBy: 'admin'
  },
  {
    id: '9',
    name: 'Fungicida Propiconazol 25%',
    brand: 'Bayer',
    brandId: '2',
    category: 'Fungicidas',
    type: 'fungicide',
    unit: 'L',
    stock: 22,
    minStock: 8,
    activeIngredient: 'Propiconazol 25%',
    expirationDate: new Date('2026-02-28'),
    price: 52000,
    status: 'active',
    createdAt: new Date('2024-02-15'),
    createdBy: 'admin'
  },
  {
    id: '10',
    name: 'Insecticida Clorpirifos 48%',
    brand: 'Monsanto',
    brandId: '3',
    category: 'Insecticidas',
    type: 'insecticide',
    unit: 'L',
    stock: 30,
    minStock: 10,
    activeIngredient: 'Clorpirifos 48%',
    expirationDate: new Date('2025-10-20'),
    price: 24000,
    status: 'active',
    createdAt: new Date('2024-02-20'),
    createdBy: 'admin'
  }
];

// ==================== RECETAS TÉCNICAS ====================
export const mockRecipes: Recipe[] = [
  {
    id: '1',
    name: 'Fertilización Base NPK',
    description: 'Fertilización básica con NPK 15-15-15 para cultivos de ciclo corto',
    category: 'fertilization',
    products: [
      {
        id: '1',
        productId: '1',
        productName: 'Fertilizante NPK 15-15-15',
        brandId: '1',
        brandName: 'Yara',
        quantity: 100,
        unit: 'kg',
        applicationRate: '50 kg/hectárea',
        observations: 'Aplicar al momento de la siembra'
      }
    ],
    applicationInstructions: 'Aplicar 50 kg por hectárea al momento de la siembra, incorporando al suelo a 5 cm de profundidad.',
    safetyNotes: 'Usar equipo de protección personal. Evitar contacto con la piel y ojos.',
    estimatedCost: 250000,
    createdBy: 'Dr. Luis Martínez',
    status: 'active',
    createdAt: new Date('2024-02-01')
  },
  {
    id: '2',
    name: 'Control Integral de Plagas',
    description: 'Control preventivo y curativo de plagas con Deltametrina e Imidacloprid',
    category: 'pest_control',
    products: [
      {
        id: '1',
        productId: '2',
        productName: 'Insecticida Deltametrina 2.5%',
        brandId: '2',
        brandName: 'Bayer',
        quantity: 10,
        unit: 'L',
        applicationRate: '0.5 L/hectárea',
        observations: 'Para control de lepidópteros'
      },
      {
        id: '2',
        productId: '6',
        productName: 'Insecticida Imidacloprid 35%',
        brandId: '2',
        brandName: 'Bayer',
        quantity: 5,
        unit: 'L',
        applicationRate: '0.25 L/hectárea',
        observations: 'Para control de áfidos y mosca blanca'
      }
    ],
    applicationInstructions: 'Aplicar en horas de la tarde, con humedad relativa alta. Alternar productos para evitar resistencia.',
    safetyNotes: 'Usar equipo de protección completo. No aplicar con vientos fuertes. Respetar periodo de carencia.',
    estimatedCost: 525000,
    createdBy: 'Dr. Luis Martínez',
    status: 'active',
    createdAt: new Date('2024-02-05')
  },
  {
    id: '3',
    name: 'Control de Malezas Perimetrales',
    description: 'Eliminación de malezas en bordes y caminos con glifosato',
    category: 'weed_control',
    products: [
      {
        id: '1',
        productId: '3',
        productName: 'Herbicida Glifosato 48%',
        brandId: '3',
        brandName: 'Monsanto',
        quantity: 5,
        unit: 'L',
        applicationRate: '2.5 L/hectárea',
        observations: 'Solo para áreas no cultivadas'
      }
    ],
    applicationInstructions: 'Aplicar en días soleados, sin viento. Dirigir solo a malezas, evitar cultivos.',
    safetyNotes: 'Producto altamente tóxico. Usar protección respiratoria y visual.',
    estimatedCost: 180000,
    createdBy: 'Ing. Ana García',
    status: 'active',
    createdAt: new Date('2024-02-10')
  },
  {
    id: '4',
    name: 'Prevención de Enfermedades Fúngicas',
    description: 'Aplicación preventiva de fungicida para control de hongos',
    category: 'disease_control',
    products: [
      {
        id: '1',
        productId: '4',
        productName: 'Fungicida Mancozeb 80%',
        brandId: '4',
        brandName: 'BASF',
        quantity: 15,
        unit: 'kg',
        applicationRate: '3 kg/hectárea',
        observations: 'Aplicar preventivamente cada 15 días'
      }
    ],
    applicationInstructions: 'Aplicar cada 15 días en aspersión foliar. Iniciar aplicaciones antes de lluvias.',
    safetyNotes: 'Evitar inhalación del polvo. Usar mascarilla y guantes.',
    estimatedCost: 270000,
    createdBy: 'Dr. Luis Martínez',
    status: 'active',
    createdAt: new Date('2024-02-15')
  },
  {
    id: '5',
    name: 'Fertilización Complementaria Nitrogenada',
    description: 'Aplicación de urea para refuerzo nutricional en estado vegetativo',
    category: 'fertilization',
    products: [
      {
        id: '1',
        productId: '5',
        productName: 'Fertilizante Urea 46%',
        brandId: '1',
        brandName: 'Yara',
        quantity: 75,
        unit: 'kg',
        applicationRate: '25 kg/hectárea',
        observations: 'Aplicar 30 días después de germinación'
      }
    ],
    applicationInstructions: 'Aplicar al voleo 30 días después de germinación, seguido de riego.',
    safetyNotes: 'Evitar aplicación en horas de sol fuerte para evitar quemaduras.',
    estimatedCost: 135000,
    createdBy: 'Dr. Luis Martínez',
    status: 'active',
    createdAt: new Date('2024-02-20')
  }
];

// ==================== ÓRDENES TÉCNICAS ====================
export const mockTechnicalOrders: TechnicalOrder[] = [
  {
    id: '1',
    orderNumber: 'OT-2024-001',
    scheduledDate: new Date('2024-03-15'),
    farmIds: ['1', '3'],
    farmNames: ['Finca La Esperanza', 'Finca El Porvenir'],
    status: 'approved',
    recipeId: '1',
    recipeName: 'Fertilización Base NPK',
    products: [
      {
        id: '1',
        productId: '1',
        productName: 'Fertilizante NPK 15-15-15',
        brandId: '1',
        brandName: 'Yara',
        quantity: 100,
        unit: 'kg',
        observations: 'Para 2 hectáreas'
      }
    ],
    responsibleAgronomist: 'Dr. Luis Martínez',
    observations: 'Aplicar en horas de la mañana',
    estimatedCost: 250000,
    createdAt: new Date('2024-03-10')
  },
  {
    id: '2',
    orderNumber: 'OT-2024-002',
    scheduledDate: new Date('2024-03-20'),
    farmIds: ['4'],
    farmNames: ['Finca San Miguel'],
    status: 'draft',
    recipeId: '3',
    recipeName: 'Control de Malezas Perimetrales',
    products: [
      {
        id: '1',
        productId: '3',
        productName: 'Herbicida Glifosato 48%',
        brandId: '3',
        brandName: 'Monsanto',
        quantity: 5,
        unit: 'L',
        observations: 'Control de malezas perimetrales'
      }
    ],
    responsibleAgronomist: 'Ing. Ana García',
    observations: 'Revisar condiciones climáticas',
    estimatedCost: 180000,
    createdAt: new Date('2024-03-12')
  },
  {
    id: '3',
    orderNumber: 'OT-2024-003',
    scheduledDate: new Date('2024-03-25'),
    farmIds: ['3'],
    farmNames: ['Finca El Porvenir'],
    status: 'approved',
    recipeId: '2',
    recipeName: 'Control Integral de Plagas',
    products: [
      {
        id: '1',
        productId: '2',
        productName: 'Insecticida Deltametrina 2.5%',
        brandId: '2',
        brandName: 'Bayer',
        quantity: 10,
        unit: 'L',
        observations: 'Control de lepidópteros'
      },
      {
        id: '2',
        productId: '6',
        productName: 'Insecticida Imidacloprid 35%',
        brandId: '2',
        brandName: 'Bayer',
        quantity: 5,
        unit: 'L',
        observations: 'Control de áfidos'
      }
    ],
    responsibleAgronomist: 'Dr. Luis Martínez',
    observations: 'Aplicar en horas de la tarde',
    estimatedCost: 525000,
    createdAt: new Date('2024-03-15')
  },
  {
    id: '4',
    orderNumber: 'OT-2024-004',
    scheduledDate: new Date('2024-04-05'),
    farmIds: ['1'],
    farmNames: ['Finca La Esperanza'],
    status: 'approved',
    recipeId: '5',
    recipeName: 'Fertilización Complementaria Nitrogenada',
    products: [
      {
        id: '1',
        productId: '5',
        productName: 'Fertilizante Urea 46%',
        brandId: '1',
        brandName: 'Yara',
        quantity: 75,
        unit: 'kg',
        observations: 'Refuerzo nutricional'
      }
    ],
    responsibleAgronomist: 'Dr. Luis Martínez',
    observations: 'Aplicar seguido de riego',
    estimatedCost: 135000,
    createdAt: new Date('2024-03-20')
  },
  {
    id: '5',
    orderNumber: 'OT-2024-005',
    scheduledDate: new Date('2024-04-10'),
    farmIds: ['4'],
    farmNames: ['Finca San Miguel'],
    status: 'in_progress',
    recipeId: '4',
    recipeName: 'Prevención de Enfermedades Fúngicas',
    products: [
      {
        id: '1',
        productId: '4',
        productName: 'Fungicida Mancozeb 80%',
        brandId: '4',
        brandName: 'BASF',
        quantity: 15,
        unit: 'kg',
        observations: 'Aplicación preventiva'
      }
    ],
    responsibleAgronomist: 'Dr. Luis Martínez',
    observations: 'Antes de la temporada de lluvias',
    estimatedCost: 270000,
    createdAt: new Date('2024-03-25')
  }
];

// ==================== SALIDAS ====================
export const mockOutputs: ProductOutput[] = [
  {
    id: '1',
    outputNumber: 'SAL-2024-001',
    orderNumber: 'OT-2024-001',
    orderName: 'Fertilización Base NPK',
    outputDate: new Date('2024-03-20'),
    originLocationId: '2',
    originLocationName: 'Bodega Central',
    destinationLocationId: '1',
    destinationLocationName: 'Finca La Esperanza',
    products: [
      {
        id: '1',
        productId: '1',
        productName: 'Fertilizante NPK 15-15-15',
        brandName: 'Yara',
        quantityRequested: 50,
        quantityDelivered: 50,
        unit: 'kg',
        expirationDate: new Date('2025-03-15'),
        availableStock: 200
      }
    ],
    responsibleUser: 'Dr. Luis Martínez',
    status: 'completed',
    observations: 'Entrega completa según orden técnica',
    totalCost: 125000,
    createdAt: new Date('2024-03-20')
  },
  {
    id: '2',
    outputNumber: 'SAL-2024-002',
    orderNumber: 'OT-2024-003',
    orderName: 'Control Integral de Plagas',
    outputDate: new Date('2024-03-22'),
    originLocationId: '2',
    originLocationName: 'Bodega Central',
    destinationLocationId: '3',
    destinationLocationName: 'Finca El Porvenir',
    products: [
      {
        id: '1',
        productId: '2',
        productName: 'Insecticida Deltametrina 2.5%',
        brandName: 'Bayer',
        quantityRequested: 10,
        quantityDelivered: 8,
        unit: 'L',
        availableStock: 15
      }
    ],
    responsibleUser: 'Carlos Rodríguez',
    status: 'partial',
    observations: 'Entrega parcial - stock insuficiente',
    totalCost: 240000,
    createdAt: new Date('2024-03-22')
  }
];

// ==================== RECEPCIONES UNIFICADAS ====================
export const mockReceptions: Reception[] = [
  {
    id: '1',
    receptionNumber: 'REC-2024-001',
    sourceId: '1', // ID de la salida SAL-2024-001
    sourceType: 'output',
    sourceNumber: 'SAL-2024-001',
    shipmentDate: new Date('2024-03-20'),
    originLocationId: '2',
    originLocationName: 'Bodega Central',
    destinationLocationId: '1',
    destinationLocationName: 'Finca La Esperanza',
    status: 'completed',
    totalExpected: 50,
    totalReceived: 50,
    completionPercentage: 100,
    items: [
      {
        id: '1',
        productId: '1',
        productName: 'Fertilizante NPK 15-15-15',
        brandId: '1',
        brandName: 'Yara',
        quantityExpected: 50,
        quantityReceived: 50,
        quantityPending: 0,
        unit: 'kg',
        condition: 'good',
        observations: 'Producto en perfecto estado'
      }
    ],
    batches: [
      {
        id: '1',
        batchNumber: 1,
        receptionDate: new Date('2024-03-20'),
        receivedBy: 'Juan Pérez',
        items: [
          {
            id: '1',
            productId: '1',
            quantityReceived: 50,
            condition: 'good'
          }
        ],
        observations: 'Recepción completa sin novedades'
      }
    ],
    responsibleUser: 'Juan Pérez',
    observations: 'Recepción completa de salida entre ubicaciones',
    createdAt: new Date('2024-03-20'),
    updatedAt: new Date('2024-03-20')
  },
  {
    id: '2',
    receptionNumber: 'REC-2024-002',
    sourceId: '2', // ID de la salida SAL-2024-002
    sourceType: 'output',
    sourceNumber: 'SAL-2024-002',
    shipmentDate: new Date('2024-03-22'),
    originLocationId: '2',
    originLocationName: 'Bodega Central',
    destinationLocationId: '3',
    destinationLocationName: 'Finca El Porvenir',
    status: 'partial',
    totalExpected: 8,
    totalReceived: 5,
    completionPercentage: 62.5,
    items: [
      {
        id: '1',
        productId: '2',
        productName: 'Insecticida Deltametrina 2.5%',
        brandId: '2',
        brandName: 'Bayer',
        quantityExpected: 8,
        quantityReceived: 5,
        quantityPending: 3,
        unit: 'L',
        condition: 'good'
      }
    ],
    batches: [
      {
        id: '1',
        batchNumber: 1,
        receptionDate: new Date('2024-03-22'),
        receivedBy: 'Carlos Rodríguez',
        items: [
          {
            id: '1',
            productId: '2',
            quantityReceived: 5,
            condition: 'good'
          }
        ],
        observations: 'Recepción parcial - faltan 3L'
      }
    ],
    responsibleUser: 'Carlos Rodríguez',
    observations: 'Pendiente recepción de 3L restantes',
    createdAt: new Date('2024-03-22'),
    updatedAt: new Date('2024-03-22')
  },
  {
    id: '3',
    receptionNumber: 'REC-2024-003',
    sourceId: 'PUR-2024-001', // ID de una compra completa
    sourceType: 'purchase',
    sourceNumber: 'PUR-2024-001',
    destinationLocationId: '2',
    destinationLocationName: 'Bodega Central',
    status: 'completed',
    totalExpected: 237,
    totalReceived: 237,
    completionPercentage: 100,
    items: [
      {
        id: '1',
        productId: '5',
        productName: 'Fertilizante Urea 46%',
        brandId: '1',
        brandName: 'Yara',
        quantityExpected: 100,
        quantityReceived: 100,
        quantityPending: 0,
        unit: 'kg',
        expirationDate: new Date('2026-01-30'),
        condition: 'good'
      },
      {
        id: '2',
        productId: '1',
        productName: 'Fertilizante NPK 15-15-15',
        brandId: '1',
        brandName: 'Yara',
        quantityExpected: 50,
        quantityReceived: 50,
        quantityPending: 0,
        unit: 'kg',
        expirationDate: new Date('2025-03-15'),
        condition: 'good'
      },
      {
        id: '3',
        productId: '7',
        productName: 'Fertilizante Superfosfato Simple',
        brandId: '1',
        brandName: 'Yara',
        quantityExpected: 75,
        quantityReceived: 75,
        quantityPending: 0,
        unit: 'kg',
        expirationDate: new Date('2025-08-30'),
        condition: 'good'
      },
      {
        id: '4',
        productId: '9',
        productName: 'Fungicida Propiconazol 25%',
        brandId: '2',
        brandName: 'Bayer',
        quantityExpected: 12,
        quantityReceived: 12,
        quantityPending: 0,
        unit: 'L',
        expirationDate: new Date('2026-02-28'),
        condition: 'good'
      }
    ],
    batches: [
      {
        id: '1',
        batchNumber: 1,
        receptionDate: new Date('2024-04-08'),
        receivedBy: 'María González',
        items: [
          {
            id: '1',
            productId: '5',
            quantityReceived: 100,
            condition: 'good',
            expirationDate: new Date('2026-01-30')
          },
          {
            id: '2',
            productId: '1',
            quantityReceived: 50,
            condition: 'good',
            expirationDate: new Date('2025-03-15')
          },
          {
            id: '3',
            productId: '7',
            quantityReceived: 75,
            condition: 'good',
            expirationDate: new Date('2025-08-30')
          },
          {
            id: '4',
            productId: '9',
            quantityReceived: 12,
            condition: 'good',
            expirationDate: new Date('2026-02-28')
          }
        ],
        observations: 'Recepción completa de compra - todos los productos en excelente estado'
      }
    ],
    responsibleUser: 'María González',
    observations: 'Recepción completa de compra PUR-2024-001',
    createdAt: new Date('2024-04-08'),
    updatedAt: new Date('2024-04-08')
  },
  {
    id: '4',
    receptionNumber: 'REC-2024-004',
    sourceId: 'PUR-2024-002', // ID de una compra INCOMPLETA
    sourceType: 'purchase',
    sourceNumber: 'PUR-2024-002',
    destinationLocationId: '2',
    destinationLocationName: 'Bodega Central',
    status: 'partial',
    totalExpected: 90,
    totalReceived: 55,
    completionPercentage: 61.1,
    items: [
      {
        id: '1',
        productId: '2',
        productName: 'Insecticida Deltametrina 2.5%',
        brandId: '2',
        brandName: 'Bayer',
        quantityExpected: 20,
        quantityReceived: 20,
        quantityPending: 0,
        unit: 'L',
        expirationDate: new Date('2026-03-20'),
        condition: 'good'
      },
      {
        id: '2',
        productId: '4',
        productName: 'Fungicida Mancozeb 80%',
        brandId: '4',
        brandName: 'BASF',
        quantityExpected: 30,
        quantityReceived: 20,
        quantityPending: 10,
        unit: 'kg',
        expirationDate: new Date('2025-09-15'),
        condition: 'good',
        observations: 'Entrega parcial - faltan 10 kg'
      },
      {
        id: '3',
        productId: '8',
        productName: 'Herbicida 2,4-D Amina',
        brandId: '4',
        brandName: 'BASF',
        quantityExpected: 15,
        quantityReceived: 15,
        quantityPending: 0,
        unit: 'L',
        expirationDate: new Date('2025-11-15'),
        condition: 'good'
      },
      {
        id: '4',
        productId: '10',
        productName: 'Insecticida Clorpirifos 48%',
        brandId: '3',
        brandName: 'Monsanto',
        quantityExpected: 25,
        quantityReceived: 0,
        quantityPending: 25,
        unit: 'L',
        expirationDate: new Date('2025-10-20'),
        condition: 'pending',
        observations: 'Producto no entregado - pendiente segundo envío'
      }
    ],
    batches: [
      {
        id: '1',
        batchNumber: 1,
        receptionDate: new Date('2024-04-12'),
        receivedBy: 'Carlos Rodríguez',
        items: [
          {
            id: '1',
            productId: '2',
            quantityReceived: 20,
            condition: 'good',
            expirationDate: new Date('2026-03-20')
          },
          {
            id: '2',
            productId: '4',
            quantityReceived: 20,
            condition: 'good',
            expirationDate: new Date('2025-09-15')
          },
          {
            id: '3',
            productId: '8',
            quantityReceived: 15,
            condition: 'good',
            expirationDate: new Date('2025-11-15')
          }
        ],
        observations: 'Primera entrega parcial - falta Mancozeb (10 kg) y Clorpirifos completo (25 L)'
      }
    ],
    responsibleUser: 'Carlos Rodríguez',
    observations: 'Recepción parcial de compra PUR-2024-002. Pendiente: 10 kg Mancozeb y 25 L Clorpirifos',
    createdAt: new Date('2024-04-12'),
    updatedAt: new Date('2024-04-12')
  },
  {
    id: '5',
    receptionNumber: 'REC-2024-005',
    sourceId: '3', // ID de otra salida
    sourceType: 'output',
    sourceNumber: 'SAL-2024-003',
    shipmentDate: new Date('2024-04-10'),
    originLocationId: '2',
    originLocationName: 'Bodega Central',
    destinationLocationId: '4',
    destinationLocationName: 'Finca San Miguel',
    status: 'pending',
    totalExpected: 90,
    totalReceived: 0,
    completionPercentage: 0,
    items: [
      {
        id: '1',
        productId: '3',
        productName: 'Herbicida Glifosato 48%',
        brandId: '3',
        brandName: 'Monsanto',
        quantityExpected: 10,
        quantityReceived: 0,
        quantityPending: 10,
        unit: 'L',
        condition: 'pending'
      },
      {
        id: '2',
        productId: '6',
        productName: 'Insecticida Imidacloprid 35%',
        brandId: '2',
        brandName: 'Bayer',
        quantityExpected: 8,
        quantityReceived: 0,
        quantityPending: 8,
        unit: 'L',
        condition: 'pending'
      },
      {
        id: '3',
        productId: '1',
        productName: 'Fertilizante NPK 15-15-15',
        brandId: '1',
        brandName: 'Yara',
        quantityExpected: 50,
        quantityReceived: 0,
        quantityPending: 50,
        unit: 'kg',
        condition: 'pending'
      },
      {
        id: '4',
        productId: '5',
        productName: 'Fertilizante Urea 46%',
        brandId: '1',
        brandName: 'Yara',
        quantityExpected: 22,
        quantityReceived: 0,
        quantityPending: 22,
        unit: 'kg',
        condition: 'pending'
      }
    ],
    batches: [],
    responsibleUser: 'Ana García',
    observations: 'Envío programado para recepción - productos en tránsito',
    createdAt: new Date('2024-04-10'),
    updatedAt: new Date('2024-04-10')
  }
];

// ==================== COMPRAS ====================
export const mockPurchases: any[] = [
  {
    id: 'PUR-2024-001',
    orderNumber: 'PUR-2024-001',
    supplierId: 'SUP-001',
    supplierName: 'Distribuidora Agrícola El Campo S.A.S.',
    supplier: mockSuppliers[0],
    destinationLocationId: '2',
    destinationLocationName: 'Bodega Central',
    purchaseDate: new Date('2024-04-01'),
    expectedDelivery: new Date('2024-04-08'),
    status: 'received',
    items: [
      {
        id: '1',
        productId: '5',
        productName: 'Fertilizante Urea 46%',
        brandId: '1',
        brandName: 'Yara',
        quantity: 100,
        unit: 'kg',
        unitPrice: 1800,
        subtotal: 180000,
        expirationDate: new Date('2026-01-30')
      },
      {
        id: '2',
        productId: '1',
        productName: 'Fertilizante NPK 15-15-15',
        brandId: '1',
        brandName: 'Yara',
        quantity: 50,
        unit: 'kg',
        unitPrice: 2500,
        subtotal: 125000,
        expirationDate: new Date('2025-03-15')
      },
      {
        id: '3',
        productId: '7',
        productName: 'Fertilizante Superfosfato Simple',
        brandId: '1',
        brandName: 'Yara',
        quantity: 75,
        unit: 'kg',
        unitPrice: 2200,
        subtotal: 165000,
        expirationDate: new Date('2025-08-30')
      },
      {
        id: '4',
        productId: '9',
        productName: 'Fungicida Propiconazol 25%',
        brandId: '2',
        brandName: 'Bayer',
        quantity: 12,
        unit: 'L',
        unitPrice: 52000,
        subtotal: 624000,
        expirationDate: new Date('2026-02-28')
      }
    ],
    subtotal: 1094000,
    tax: 207860,
    total: 1301860,
    observations: 'Entrega en Bodega Central. Verificar fechas de vencimiento.',
    attachments: [],
    createdBy: 'María González',
    receivedBy: 'María González',
    receivedAt: new Date('2024-04-08'),
    createdAt: new Date('2024-04-01')
  },
  {
    id: 'PUR-2024-002',
    orderNumber: 'PUR-2024-002',
    supplierId: 'SUP-002',
    supplierName: 'Agroquímicos del Norte Ltda.',
    supplier: mockSuppliers[1],
    destinationLocationId: '2',
    destinationLocationName: 'Bodega Central',
    purchaseDate: new Date('2024-04-05'),
    expectedDelivery: new Date('2024-04-12'),
    status: 'in_transit',
    items: [
      {
        id: '1',
        productId: '2',
        productName: 'Insecticida Deltametrina 2.5%',
        brandId: '2',
        brandName: 'Bayer',
        quantity: 20,
        unit: 'L',
        unitPrice: 30000,
        subtotal: 600000,
        expirationDate: new Date('2026-03-20')
      },
      {
        id: '2',
        productId: '4',
        productName: 'Fungicida Mancozeb 80%',
        brandId: '4',
        brandName: 'BASF',
        quantity: 30,
        unit: 'kg',
        unitPrice: 18000,
        subtotal: 540000,
        expirationDate: new Date('2025-09-15')
      },
      {
        id: '3',
        productId: '8',
        productName: 'Herbicida 2,4-D Amina',
        brandId: '4',
        brandName: 'BASF',
        quantity: 15,
        unit: 'L',
        unitPrice: 28000,
        subtotal: 420000,
        expirationDate: new Date('2025-11-15')
      },
      {
        id: '4',
        productId: '10',
        productName: 'Insecticida Clorpirifos 48%',
        brandId: '3',
        brandName: 'Monsanto',
        quantity: 25,
        unit: 'L',
        unitPrice: 24000,
        subtotal: 600000,
        expirationDate: new Date('2025-10-20')
      }
    ],
    subtotal: 2160000,
    tax: 410400,
    total: 2570400,
    observations: 'Productos para campaña de control integrado de plagas. Entrega parcial esperada.',
    attachments: [],
    createdBy: 'Carlos Rodríguez',
    createdAt: new Date('2024-04-05')
  },
  {
    id: 'PUR-2024-003',
    orderNumber: 'PUR-2024-003',
    supplierId: 'SUP-003',
    supplierName: 'Fertilizantes y Químicos S.A.',
    supplier: mockSuppliers[2],
    destinationLocationId: '1',
    destinationLocationName: 'Finca La Esperanza',
    purchaseDate: new Date('2024-04-08'),
    expectedDelivery: new Date('2024-04-15'),
    status: 'ordered',
    items: [
      {
        id: '1',
        productId: '3',
        productName: 'Herbicida Glifosato 48%',
        brandId: '3',
        brandName: 'Monsanto',
        quantity: 30,
        unit: 'L',
        unitPrice: 36000,
        subtotal: 1080000,
        expirationDate: new Date('2025-12-20')
      },
      {
        id: '2',
        productId: '6',
        productName: 'Insecticida Imidacloprid 35%',
        brandId: '2',
        brandName: 'Bayer',
        quantity: 18,
        unit: 'L',
        unitPrice: 45000,
        subtotal: 810000,
        expirationDate: new Date('2026-04-05')
      },
      {
        id: '3',
        productId: '1',
        productName: 'Fertilizante NPK 15-15-15',
        brandId: '1',
        brandName: 'Yara',
        quantity: 120,
        unit: 'kg',
        unitPrice: 2500,
        subtotal: 300000,
        expirationDate: new Date('2025-03-15')
      },
      {
        id: '4',
        productId: '5',
        productName: 'Fertilizante Urea 46%',
        brandId: '1',
        brandName: 'Yara',
        quantity: 80,
        unit: 'kg',
        unitPrice: 1800,
        subtotal: 144000,
        expirationDate: new Date('2026-01-30')
      }
    ],
    subtotal: 2334000,
    tax: 443460,
    total: 2777460,
    observations: 'Pedido para resurtir inventario general. Prioridad media.',
    attachments: [],
    createdBy: 'María González',
    createdAt: new Date('2024-04-08')
  }
];

// ==================== HELPER FUNCTIONS ====================
export const getProductById = (productId: string): Product | undefined => {
  return mockProducts.find(p => p.id === productId);
};

export const getBrandById = (brandId: string): Brand | undefined => {
  return mockBrands.find(b => b.id === brandId);
};

export const getLocationById = (locationId: string): Location | undefined => {
  return mockLocations.find(l => l.id === locationId);
};

export const getRecipeById = (recipeId: string): Recipe | undefined => {
  return mockRecipes.find(r => r.id === recipeId);
};

export const getTechnicalOrderById = (orderId: string): TechnicalOrder | undefined => {
  return mockTechnicalOrders.find(o => o.id === orderId);
};

export const getActiveProducts = (): Product[] => {
  return mockProducts.filter(p => p.status === 'active');
};

export const getActiveLocations = (): Location[] => {
  return mockLocations.filter(l => l.status === 'active');
};

export const getApprovedOrders = (): TechnicalOrder[] => {
  return mockTechnicalOrders.filter(o => o.status === 'approved');
};