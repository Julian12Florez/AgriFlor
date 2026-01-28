// Base Types
export interface User {
  id: string;
  email: string;
  name: string;
  role: 'admin' | 'agronomist' | 'warehouse' | 'supervisor' | 'farm';
  status: 'active' | 'inactive';
  createdAt: Date;
  updatedAt: Date;
}

export interface Product {
  id: string;
  name: string;
  category: 'fertilizante' | 'pesticida' | 'herbicida' | 'fungicida';
  baseUnit: 'kg' | 'litros' | 'unidades';
  activeIngredient: string; // Principio activo del producto
  iva: number; // Porcentaje de IVA (0, 5, 16, 19)
  status: 'active' | 'inactive';
  brands: Brand[];
  description?: string;
  createdBy: string;
  createdAt: Date;
  updatedAt: Date;
}

export interface Brand {
  id: string;
  name: string;
  status: 'active' | 'inactive';
  createdAt: Date;
}

export interface Supplier {
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

export interface SupplierContact {
  id: string;
  name: string;
  position: string;
  phone: string;
  email: string;
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
  responsible: string;
  status: 'active' | 'inactive';
  createdAt: Date;
}

// Inventory and Movement Types
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
  expirationDate?: Date;
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
  ivaPercentage: number;
  taxAmount: number;
  total: number;
  expirationDate?: Date;
}

// Technical Process Types
export interface TechnicalRecipe {
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

// Warehouse Types
export interface WarehouseEntry {
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

export interface EntryItem {
  id: string;
  productId: string;
  productName: string;
  brandId: string;
  brandName: string;
  quantity: number;
  unit: string;
  unitPrice: number;
  expirationDate?: Date;
  location?: string;
}

export interface WarehouseExit {
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

export interface ExitItem {
  id: string;
  productId: string;
  productName: string;
  brandId: string;
  brandName: string;
  requestedQuantity: number;
  approvedQuantity: number;
  unit: string;
  expirationDate?: Date;
  observations?: string;
}

// Reception Types - Sistema Unificado
export interface Reception {
  id: string;
  receptionNumber: string;
  sourceId: string; // ID de compra o salida
  sourceType: 'purchase' | 'output';
  sourceNumber: string; // Número de orden de compra o salida
  originLocationId?: string;
  originLocationName?: string;
  destinationLocationId: string;
  destinationLocationName: string;
  status: 'pending' | 'partial' | 'completed' | 'cancelled';
  totalExpected: number;
  totalReceived: number;
  completionPercentage: number;
  items: ReceptionItem[];
  batches: ReceptionBatch[]; // Historial de recepciones parciales
  responsibleUser: string;
  observations?: string;
  createdAt: Date;
  updatedAt: Date;
}

export interface ReceptionItem {
  id: string;
  productId: string;
  productName: string;
  brandId: string;
  brandName: string;
  quantityExpected: number;
  quantityReceived: number;
  quantityPending: number;
  unit: string;
  expirationDate?: Date; // Solo para compras
  condition?: 'good' | 'damaged' | 'expired';
  observations?: string;
}

export interface ReceptionBatch {
  id: string;
  batchNumber: number; // Número secuencial de recepción (1, 2, 3...)
  receptionDate: Date;
  receivedBy: string;
  items: ReceptionBatchItem[];
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

// Common Types
export interface ApiResponse<T> {
  data: T;
  message: string;
  success: boolean;
}

export interface PaginatedResponse<T> {
  data: T[];
  links: {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
  };
  meta: {
    current_page: number;
    from: number | null;
    last_page: number;
    links: Array<{
      url: string | null;
      label: string;
      active: boolean;
    }>;
    path: string;
    per_page: number;
    to: number | null;
    total: number;
  };
}

export interface FilterParams {
  search?: string;
  status?: string;
  dateFrom?: Date;
  dateTo?: Date;
  page?: number;
  limit?: number;
}