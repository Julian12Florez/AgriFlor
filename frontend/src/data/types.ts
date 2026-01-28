// Interfaces compartidas para todo el sistema

export interface PackagingUnit {
  id: string;
  name: string; // Ej: "Bulto", "Galón", "Caja", "Unidad"
  baseQuantity: number; // Cuánto de la unidad base contiene
  baseUnit: string; // Unidad base (kg, L, unidades)
}

export interface Product {
  id: string;
  name: string;
  brand: string;
  brandId: string;
  category: string;
  type: 'fertilizer' | 'insecticide' | 'herbicide' | 'fungicide' | 'other';
  unit: string; // Unidad base (kg, L, unidades)
  packagingUnits: PackagingUnit[]; // Unidades de empaque disponibles
  stock: number;
  minStock: number;
  activeIngredient: string; // Principio activo del producto
  expirationDate?: Date;
  price: number;
  status: 'active' | 'inactive';
  createdAt: Date;
  createdBy?: string;
}

export interface Brand {
  id: string;
  name: string;
  status: 'active' | 'inactive';
  createdAt: Date;
}

export interface Location {
  id: string;
  name: string;
  type: 'warehouse' | 'farm';
  municipality: string;
  address: string;
  coordinates?: {
    lat: number;
    lng: number;
  };
  responsible_user_id?: string;
  responsible_user?: {
    id: string;
    name: string;
    email: string;
  };
  status: 'active' | 'inactive';
  created_at?: string;
}

export interface RecipeProduct {
  id: string;
  productId: string;
  productName: string;
  brandId: string;
  brandName: string;
  quantity: number;
  unit: string;
  applicationRate: string;
  observations?: string;
}

export interface Recipe {
  id: string;
  name: string;
  description: string;
  category: 'fertilization' | 'pest_control' | 'disease_control' | 'weed_control' | 'other';
  products: RecipeProduct[];
  applicationInstructions: string;
  safetyNotes: string;
  estimatedCost: number;
  createdBy: string;
  status: 'active' | 'inactive';
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

export interface TechnicalOrder {
  id: string;
  orderNumber: string;
  scheduledDate: Date;
  farmIds: string[];
  farmNames: string[];
  status: 'draft' | 'approved' | 'in_progress' | 'completed' | 'cancelled';
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

export interface OutputProduct {
  id: string;
  productId: string;
  productName: string;
  brandName: string;
  quantityRequested: number;
  quantityDelivered: number;
  unit: string;
  expirationDate?: Date;
  availableStock: number;
}

export interface ProductOutput {
  id: string;
  outputNumber: string;
  orderNumber: string;
  orderName: string;
  outputDate: Date;
  originLocationId: string;
  originLocationName: string;
  destinationLocationId: string;
  destinationLocationName: string;
  products: OutputProduct[];
  responsibleUser: string;
  status: 'pending' | 'partial' | 'completed';
  observations?: string;
  totalCost: number;
  createdAt: Date;
}

// Recepción Unificada - Maneja tanto compras como salidas
export interface Reception {
  id: string;
  receptionNumber: string;
  sourceId: string; // ID de compra o salida
  sourceType: 'purchase' | 'output';
  sourceNumber?: string; // Número de orden de compra o salida
  shipmentDate?: Date;
  originLocationId?: string;
  originLocationName?: string;
  destinationLocationId: string;
  destinationLocationName?: string;
  status: 'pending' | 'partial' | 'completed' | 'cancelled';
  totalExpected: number;
  totalReceived: number;
  completionPercentage: number;
  items: ReceptionItem[];
  batches: ReceptionBatch[]; // Historial de recepciones parciales
  responsibleUser?: string;
  observations?: string;
  createdAt: Date;
  updatedAt?: Date;
  // Relaciones completas opcionales
  originLocation?: Location;
  destinationLocation?: Location;
  origin_location?: Location; // Para compatibilidad con backend snake_case
  sourceDetails?: {
    purchaseNumber?: string;
    outputNumber?: string;
    [key: string]: any;
  };
}

export interface ReceptionItem {
  id: string;
  productId: string;
  productName?: string;
  brandId: string;
  brandName?: string;
  quantityExpected: number;
  quantityReceived: number;
  quantityPending: number;
  unit: string;
  expirationDate?: Date; // Solo para compras
  condition?: 'good' | 'damaged' | 'expired';
  observations?: string;
  // Relaciones completas opcionales
  product?: {
    id: string;
    name: string;
    [key: string]: any;
  };
  brand?: {
    id: string;
    name: string;
    [key: string]: any;
  };
}

export interface ReceptionBatch {
  id: string;
  batchNumber: number; // Número secuencial de recepción (1, 2, 3...)
  batch_number?: number; // Para compatibilidad con backend snake_case
  receptionDate: Date;
  reception_date?: Date; // Para compatibilidad con backend snake_case
  receivedBy: string;
  received_by?: string; // Para compatibilidad con backend snake_case
  items?: ReceptionBatchItem[];
  batch_items?: ReceptionBatchItem[]; // Para compatibilidad con backend snake_case
  observations?: string;
  attachments?: string[];
}

export interface ReceptionBatchItem {
  id: string;
  productId: string;
  quantityReceived: number;
  condition: 'good' | 'damaged' | 'expired';
  expirationDate?: Date;
  observations?: string;
}