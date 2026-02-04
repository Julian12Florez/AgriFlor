import type { ApiResponse, PaginatedResponse } from '../types/index';

// API Configuration
const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';

// Token management
let authToken: string | null = localStorage.getItem('auth_token');

export const setAuthToken = (token: string | null) => {
  authToken = token;
  if (token) {
    localStorage.setItem('auth_token', token);
  } else {
    localStorage.removeItem('auth_token');
  }
};

export const getAuthToken = () => authToken;

// Main API Class
class ApiService {
  private baseURL: string;

  constructor(baseURL: string) {
    this.baseURL = baseURL;
  }

  private static readonly PUBLIC_ENDPOINTS = [
    '/auth/login',
    '/auth/forgot-password',
    '/auth/reset-password',
  ];

  private async request<T>(
    endpoint: string,
    options: RequestInit = {}
  ): Promise<T> {
    const url = `${this.baseURL}${endpoint}`;
    const isPublic = ApiService.PUBLIC_ENDPOINTS.includes(endpoint);
    const optHeaders = (options.headers || {}) as Record<string, string>;
    const headers: HeadersInit = {
      ...(!optHeaders['Content-Type'] && !((options as any).body instanceof FormData)
        ? { 'Content-Type': 'application/json' } : {}),
      'Accept': 'application/json',
      ...(authToken && !isPublic ? { 'Authorization': `Bearer ${authToken}` } : {}),
      ...optHeaders,
    };

    try {
      const response = await fetch(url, {
        ...options,
        headers,
      });

      // Handle 401 Unauthorized - don't redirect if already on public pages
      if (response.status === 401 && !isPublic) {
        setAuthToken(null);
        const path = window.location.pathname;
        if (path !== '/login' && !path.startsWith('/reset-password')) {
          window.location.href = '/login';
        }
        throw new Error('No autenticado');
      }

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || `HTTP error! status: ${response.status}`);
      }

      return data;
    } catch (error) {
      console.error(`API Error [${endpoint}]:`, error);
      throw error;
    }
  }

  async get<T>(endpoint: string, params?: Record<string, any>): Promise<T> {
    let queryString = '';
    if (params) {
      // Filter out undefined, null, and empty string values
      const filteredParams: Record<string, string> = {};
      Object.keys(params).forEach(key => {
        const value = params[key];
        if (value !== undefined && value !== null && value !== '') {
          filteredParams[key] = String(value);
        }
      });

      // Only create query string if there are valid parameters
      if (Object.keys(filteredParams).length > 0) {
        queryString = '?' + new URLSearchParams(filteredParams).toString();
      }
    }

    return this.request<T>(`${endpoint}${queryString}`, {
      method: 'GET',
    });
  }

  async post<T>(endpoint: string, data: any, isFormData = false): Promise<T> {
    const headers: HeadersInit = {};
    let body: any = data;

    if (!isFormData) {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify(data);
    }

    return this.request<T>(endpoint, {
      method: 'POST',
      headers,
      body,
    });
  }

  async put<T>(endpoint: string, data: any): Promise<T> {
    return this.request<T>(endpoint, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async delete<T>(endpoint: string): Promise<T> {
    return this.request<T>(endpoint, {
      method: 'DELETE',
    });
  }
}

// Create singleton instance
export const api = new ApiService(API_BASE_URL);

// Authentication API
export const authApi = {
  login: (email: string, password: string) =>
    api.post<ApiResponse<{ token: string; user: any }>>('/auth/login', { email, password }),

  logout: () =>
    api.post<ApiResponse<null>>('/auth/logout', {}),

  me: () =>
    api.get<ApiResponse<any>>('/auth/me'),

  refresh: () =>
    api.post<ApiResponse<{ token: string }>>('/auth/refresh', {}),

  changePassword: (data: { current_password: string; password: string; password_confirmation: string }) =>
    api.post<ApiResponse<null>>('/auth/change-password', data),

  forgotPassword: (email: string) =>
    api.post<ApiResponse<null>>('/auth/forgot-password', { email }),

  resetPassword: (data: { token: string; email: string; password: string; password_confirmation: string }) =>
    api.post<ApiResponse<null>>('/auth/reset-password', data),
};

// Dashboard API
export const dashboardApi = {
  getStatistics: () =>
    api.get<ApiResponse<{
      total_products: number;
      active_orders: number;
      monthly_purchases: number;
      active_alerts: number;
    }>>('/dashboard/statistics'),

  getInventoryByCategory: () =>
    api.get<ApiResponse<{
      fertilizante: number;
      pesticida: number;
      herbicida: number;
      fungicida: number;
    }>>('/dashboard/inventory-by-category'),

  getRecentActivity: (params?: { limit?: number }) =>
    api.get<ApiResponse<Array<{
      type: string;
      title: string;
      description: string;
      timestamp: string;
      color: string;
    }>>>('/dashboard/recent-activity', params),
};

// Purchases API
export const purchasesApi = {
  list: (params?: Record<string, any>) =>
    api.get<PaginatedResponse<any>>('/purchases', params),

  get: (id: string) =>
    api.get<ApiResponse<any>>(`/purchases/${id}`),

  create: (data: any) =>
    api.post<ApiResponse<any>>('/purchases', data),

  update: (id: string, data: any) =>
    api.put<ApiResponse<any>>(`/purchases/${id}`, data),

  delete: (id: string) =>
    api.delete<ApiResponse<null>>(`/purchases/${id}`),

  addAttachment: (id: string, file: FormData) =>
    api.post<ApiResponse<any>>(`/purchases/${id}/attachments`, file, true),

  deleteAttachment: (id: string, attachmentId: string) =>
    api.delete<ApiResponse<null>>(`/purchases/${id}/attachments/${attachmentId}`),

  cancel: (id: string) =>
    api.put<ApiResponse<any>>(`/purchases/${id}/cancel`, {}),

  exportPdf: (id: string, orderNumber?: string) => {
    const url = `${API_BASE_URL}/purchases/${id}/export-pdf`;
    const filename = orderNumber ? `orden_compra_${orderNumber}.pdf` : `orden_compra_${id}.pdf`;
    return downloadFile(url, filename);
  },
};

// Receptions API
export const receptionsApi = {
  list: (params?: Record<string, any>) =>
    api.get<PaginatedResponse<any>>('/receptions', params),

  availableSources: () =>
    api.get<ApiResponse<any>>('/receptions/available-sources'),

  // FUN-001: Get available sources filtered by current user as responsible of destination
  availableSourcesForResponsible: () =>
    api.get<ApiResponse<any>>('/receptions/available-sources-for-responsible'),

  get: (id: string) =>
    api.get<ApiResponse<any>>(`/receptions/${id}`),

  create: (data: any) =>
    api.post<ApiResponse<any>>('/receptions', data),

  createDirectReception: (data: any) =>
    api.post<ApiResponse<any>>('/receptions/direct-reception', data),

  getBatches: (id: string) =>
    api.get<ApiResponse<any[]>>(`/receptions/${id}/batches`),

  getPendingProducts: (id: string) =>
    api.get<ApiResponse<any[]>>(`/receptions/${id}/pending-products`),

  addBatch: (id: string, data: any) =>
    api.post<ApiResponse<any>>(`/receptions/${id}/batches`, data),

  complete: (id: string) =>
    api.put<ApiResponse<any>>(`/receptions/${id}/complete`, {}),

  cancel: (id: string) =>
    api.put<ApiResponse<any>>(`/receptions/${id}/cancel`, {}),
};

// Product Outputs API
export const outputsApi = {
  list: (params?: Record<string, any>) =>
    api.get<PaginatedResponse<any>>('/product-outputs', params),

  get: (id: string) =>
    api.get<ApiResponse<any>>(`/product-outputs/${id}`),

  create: (data: any) =>
    api.post<ApiResponse<any>>('/product-outputs', data),

  update: (id: string, data: any) =>
    api.put<ApiResponse<any>>(`/product-outputs/${id}`, data),

  delete: (id: string) =>
    api.delete<ApiResponse<null>>(`/product-outputs/${id}`),

  validateInventory: (data: any) =>
    api.post<ApiResponse<any>>('/product-outputs/validate-inventory', data),

  approve: (id: string) =>
    api.post<ApiResponse<any>>(`/product-outputs/${id}/approve`, {}),

  markInTransit: (id: string) =>
    api.post<ApiResponse<any>>(`/product-outputs/${id}/mark-in-transit`, {}),

  complete: (id: string) =>
    api.post<ApiResponse<any>>(`/product-outputs/${id}/complete`, {}),

  // Registro de aplicaciones desde salidas tipo consumo
  registerApplication: (id: string, data: {
    farm_lot_id: string;
    application_date: string;
    applied_by: string;
    application_type?: string;
    products: Array<{
      product_id: string;
      brand_id: string;
      quantity: number;
      unit: string;
      applied_area?: number;
      area_unit?: string;
      dosage?: number;
      dosage_unit?: string;
      observations?: string;
    }>;
    observations?: string;
  }) =>
    api.post<ApiResponse<any>>(`/product-outputs/${id}/register-application`, data),

  getApplications: (id: string) =>
    api.get<ApiResponse<any[]>>(`/product-outputs/${id}/applications`),
};

// Products API
export const productsApi = {
  list: (params?: Record<string, any>) =>
    api.get<PaginatedResponse<any>>('/products', params),

  get: (id: string) =>
    api.get<ApiResponse<any>>(`/products/${id}`),

  create: (data: any) =>
    api.post<ApiResponse<any>>('/products', data),

  update: (id: string, data: any) =>
    api.put<ApiResponse<any>>(`/products/${id}`, data),

  delete: (id: string) =>
    api.delete<ApiResponse<null>>(`/products/${id}`),

  searchWithInventory: (data: any) =>
    api.post<ApiResponse<any[]>>('/products/search-with-inventory', data),

  getForOutputs: (params: { location_id: string; search?: string }) =>
    api.get<ApiResponse<any[]>>('/products-for-outputs', params),
};

// Packaging Units API
export const packagingUnitsApi = {
  list: (params?: Record<string, any>) =>
    api.get<PaginatedResponse<any>>('/packaging-units', params),

  get: (id: string) =>
    api.get<ApiResponse<any>>(`/packaging-units/${id}`),

  create: (data: any) =>
    api.post<ApiResponse<any>>('/packaging-units', data),

  update: (id: string, data: any) =>
    api.put<ApiResponse<any>>(`/packaging-units/${id}`, data),

  delete: (id: string) =>
    api.delete<ApiResponse<null>>(`/packaging-units/${id}`),
};

// Base Units API
export const baseUnitsApi = {
  list: (params?: Record<string, any>) =>
    api.get<PaginatedResponse<any>>('/base-units', params),

  get: (id: string) =>
    api.get<ApiResponse<any>>(`/base-units/${id}`),

  create: (data: any) =>
    api.post<ApiResponse<any>>('/base-units', data),

  update: (id: string, data: any) =>
    api.put<ApiResponse<any>>(`/base-units/${id}`, data),

  delete: (id: string) =>
    api.delete<ApiResponse<null>>(`/base-units/${id}`),
};

// Inventory API
export const inventoryApi = {
  list: (params?: Record<string, any>) =>
    api.get<PaginatedResponse<any>>('/inventory', params),

  get: (productId: string) =>
    api.get<ApiResponse<any>>(`/inventory/${productId}`),

  getByLocation: (locationId: string) =>
    api.get<ApiResponse<any[]>>(`/inventory/location/${locationId}`),

  getDetails: (productId: string) =>
    api.get<ApiResponse<any>>(`/inventory/product/${productId}/details`),

  getMovements: (params?: Record<string, any>) =>
    api.get<PaginatedResponse<any>>('/inventory/movements', params),

  getMovementsByProduct: (productId: string) =>
    api.get<PaginatedResponse<any>>(`/inventory/movements/product/${productId}`),

  getMovementsReport: (params?: Record<string, any>) =>
    api.get<ApiResponse<any>>('/inventory/movements/report', params),

  getConsumptionReport: (params?: Record<string, any>) =>
    api.get<ApiResponse<any>>('/inventory/consumption/report', params),

  adjust: (data: any) =>
    api.post<ApiResponse<any>>('/inventory/adjustments', data),

  // Kardex endpoints
  getKardex: (params?: Record<string, any>) =>
    api.get<ApiResponse<any>>('/inventory/kardex', params),

  getProductKardex: (productId: string, params?: Record<string, any>) =>
    api.get<ApiResponse<any>>(`/inventory/kardex/product/${productId}`, params),
};

// Locations API
export const locationsApi = {
  list: (params?: Record<string, any>) =>
    api.get<PaginatedResponse<any>>('/locations', params),

  get: (id: string) =>
    api.get<ApiResponse<any>>(`/locations/${id}`),

  create: (data: any) =>
    api.post<ApiResponse<any>>('/locations', data),

  update: (id: string, data: any) =>
    api.put<ApiResponse<any>>(`/locations/${id}`, data),

  delete: (id: string) =>
    api.delete<ApiResponse<null>>(`/locations/${id}`),

  getWarehouses: () =>
    api.get<ApiResponse<any[]>>('/locations/type/warehouses'),

  getFarms: () =>
    api.get<ApiResponse<any[]>>('/locations/type/farms'),
};

// Suppliers API
export const suppliersApi = {
  list: (params?: Record<string, any>) =>
    api.get<PaginatedResponse<any>>('/suppliers', params),

  get: (id: string) =>
    api.get<ApiResponse<any>>(`/suppliers/${id}`),

  create: (data: any) =>
    api.post<ApiResponse<any>>('/suppliers', data),

  update: (id: string, data: any) =>
    api.put<ApiResponse<any>>(`/suppliers/${id}`, data),

  delete: (id: string) =>
    api.delete<ApiResponse<null>>(`/suppliers/${id}`),
};

// Brands API
export const brandsApi = {
  list: (params?: Record<string, any>) =>
    api.get<PaginatedResponse<any>>('/brands', params),

  get: (id: string) =>
    api.get<ApiResponse<any>>(`/brands/${id}`),

  create: (data: any) =>
    api.post<ApiResponse<any>>('/brands', data),

  update: (id: string, data: any) =>
    api.put<ApiResponse<any>>(`/brands/${id}`, data),

  delete: (id: string) =>
    api.delete<ApiResponse<null>>(`/brands/${id}`),
};

// Categories API
export const categoriesApi = {
  list: (params?: Record<string, any>) =>
    api.get<PaginatedResponse<any>>('/categories', params),

  get: (id: string) =>
    api.get<ApiResponse<any>>(`/categories/${id}`),

  create: (data: Record<string, any>) =>
    api.post<ApiResponse<any>>('/categories', data),

  update: (id: string, data: Record<string, any>) =>
    api.put<ApiResponse<any>>(`/categories/${id}`, data),

  delete: (id: string) =>
    api.delete<ApiResponse<null>>(`/categories/${id}`),
};

// Alerts API
export const alertsApi = {
  list: (params?: Record<string, any>) =>
    api.get<PaginatedResponse<any>>('/alerts', params),

  get: (id: string) =>
    api.get<ApiResponse<any>>(`/alerts/${id}`),

  create: (data: any) =>
    api.post<ApiResponse<any>>('/alerts', data),

  update: (id: string, data: any) =>
    api.put<ApiResponse<any>>(`/alerts/${id}`, data),

  delete: (id: string) =>
    api.delete<ApiResponse<null>>(`/alerts/${id}`),

  resolve: (id: string) =>
    api.put<ApiResponse<any>>(`/alerts/${id}/resolve`, {}),

  dismiss: (id: string) =>
    api.put<ApiResponse<any>>(`/alerts/${id}/dismiss`, {}),
};

// Recipes API (Technical Recipes)
export const recipesApi = {
  list: (params?: Record<string, any>) =>
    api.get<PaginatedResponse<any>>('/technical-recipes', params),

  get: (id: string) =>
    api.get<ApiResponse<any>>(`/technical-recipes/${id}`),

  create: (data: any) =>
    api.post<ApiResponse<any>>('/technical-recipes', data),

  update: (id: string, data: any) =>
    api.put<ApiResponse<any>>(`/technical-recipes/${id}`, data),

  delete: (id: string) =>
    api.delete<ApiResponse<null>>(`/technical-recipes/${id}`),
};

// Orders API (Technical Orders)
export const ordersApi = {
  list: (params?: Record<string, any>) =>
    api.get<PaginatedResponse<any>>('/technical-orders', params),

  get: (id: string) =>
    api.get<ApiResponse<any>>(`/technical-orders/${id}`),

  create: (data: any) =>
    api.post<ApiResponse<any>>('/technical-orders', data),

  update: (id: string, data: any) =>
    api.put<ApiResponse<any>>(`/technical-orders/${id}`, data),

  delete: (id: string) =>
    api.delete<ApiResponse<null>>(`/technical-orders/${id}`),
};

// Users API
export const usersApi = {
  list: (params?: Record<string, any>) =>
    api.get<PaginatedResponse<any>>('/users', params),

  listSimple: (params?: Record<string, any>) =>
    api.get<ApiResponse<any>>('/users/simple', params),

  get: (id: string) =>
    api.get<ApiResponse<any>>(`/users/${id}`),

  create: (data: any) =>
    api.post<ApiResponse<any>>('/users', data),

  update: (id: string, data: any) =>
    api.put<ApiResponse<any>>(`/users/${id}`, data),

  delete: (id: string) =>
    api.delete<ApiResponse<null>>(`/users/${id}`),
};

// Output Types API
export const outputTypesApi = {
  list: (params?: Record<string, any>) =>
    api.get<PaginatedResponse<any>>('/output-types', params),

  get: (id: string) =>
    api.get<ApiResponse<any>>(`/output-types/${id}`),

  create: (data: any) =>
    api.post<ApiResponse<any>>('/output-types', data),

  update: (id: string, data: any) =>
    api.put<ApiResponse<any>>(`/output-types/${id}`, data),

  delete: (id: string) =>
    api.delete<ApiResponse<null>>(`/output-types/${id}`),
};

// Farm Lots API
export const farmLotsApi = {
  list: (params?: Record<string, any>) =>
    api.get<PaginatedResponse<any>>('/farm-lots', params),

  get: (id: string) =>
    api.get<ApiResponse<any>>(`/farm-lots/${id}`),

  create: (data: any) =>
    api.post<ApiResponse<any>>('/farm-lots', data),

  update: (id: string, data: any) =>
    api.put<ApiResponse<any>>(`/farm-lots/${id}`, data),

  delete: (id: string) =>
    api.delete<ApiResponse<null>>(`/farm-lots/${id}`),

  getByLocation: (locationId: string) =>
    api.get<ApiResponse<any[]>>(`/locations/${locationId}/farm-lots`),
};

// Report Exports API
export const reportExportsApi = {
  // Stock Report Exports
  exportStockExcel: (params?: Record<string, any>) => {
    const queryString = params ? '?' + new URLSearchParams(params as Record<string, string>).toString() : '';
    const url = `${API_BASE_URL}/reports/stock/export-excel${queryString}`;
    return downloadFile(url, 'stock-report.xlsx');
  },

  exportStockPdf: (params?: Record<string, any>) => {
    const queryString = params ? '?' + new URLSearchParams(params as Record<string, string>).toString() : '';
    const url = `${API_BASE_URL}/reports/stock/export-pdf${queryString}`;
    return downloadFile(url, 'stock-report.pdf');
  },

  // Consumption Report Exports
  exportConsumptionExcel: (params?: Record<string, any>) => {
    const queryString = params ? '?' + new URLSearchParams(params as Record<string, string>).toString() : '';
    const url = `${API_BASE_URL}/reports/consumption/export-excel${queryString}`;
    return downloadFile(url, 'consumption-report.xlsx');
  },

  exportConsumptionPdf: (params?: Record<string, any>) => {
    const queryString = params ? '?' + new URLSearchParams(params as Record<string, string>).toString() : '';
    const url = `${API_BASE_URL}/reports/consumption/export-pdf${queryString}`;
    return downloadFile(url, 'consumption-report.pdf');
  },

  // Inventory Movements Report Exports
  exportMovementsExcel: (params?: Record<string, any>) => {
    const queryString = params ? '?' + new URLSearchParams(params as Record<string, string>).toString() : '';
    const url = `${API_BASE_URL}/reports/movements/export-excel${queryString}`;
    return downloadFile(url, 'movements-report.xlsx');
  },

  exportMovementsPdf: (params?: Record<string, any>) => {
    const queryString = params ? '?' + new URLSearchParams(params as Record<string, string>).toString() : '';
    const url = `${API_BASE_URL}/reports/movements/export-pdf${queryString}`;
    return downloadFile(url, 'movements-report.pdf');
  },

  // Kardex Product Report Exports
  exportKardexExcel: (params?: Record<string, any>) => {
    const queryString = params ? '?' + new URLSearchParams(params as Record<string, string>).toString() : '';
    const url = `${API_BASE_URL}/reports/kardex/export-excel${queryString}`;
    return downloadFile(url, 'kardex-report.xlsx');
  },

  exportKardexPdf: (params?: Record<string, any>) => {
    const queryString = params ? '?' + new URLSearchParams(params as Record<string, string>).toString() : '';
    const url = `${API_BASE_URL}/reports/kardex/export-pdf${queryString}`;
    return downloadFile(url, 'kardex-report.pdf');
  },

  // Kardex List (Inventory General) Exports
  exportKardexListExcel: (params?: Record<string, any>) => {
    const queryString = params ? '?' + new URLSearchParams(params as Record<string, string>).toString() : '';
    const url = `${API_BASE_URL}/reports/kardex-list/export-excel${queryString}`;
    return downloadFile(url, 'inventario-actual.xlsx');
  },

  exportKardexListPdf: (params?: Record<string, any>) => {
    const queryString = params ? '?' + new URLSearchParams(params as Record<string, string>).toString() : '';
    const url = `${API_BASE_URL}/reports/kardex-list/export-pdf${queryString}`;
    return downloadFile(url, 'inventario-actual.pdf');
  },
};

// Workers API
export const workersApi = {
  list: (params?: Record<string, any>) =>
    api.get<any>('/workers', params),

  listSimple: (params?: Record<string, any>) =>
    api.get<any>('/workers/simple', params),

  get: (id: string) =>
    api.get<any>(`/workers/${id}`),

  create: (data: any) =>
    api.post<any>('/workers', data),

  update: (id: string, data: any) =>
    api.put<any>(`/workers/${id}`, data),

  delete: (id: string) =>
    api.delete<any>(`/workers/${id}`),

  preview: (file: FormData) =>
    api.post<any>('/workers/preview', file, true),

  processImport: (data: any) =>
    api.post<any>('/workers/import', data),

  downloadTemplate: () => {
    const url = `${API_BASE_URL}/workers/template`;
    return downloadFile(url, 'plantilla_trabajadores.xlsx');
  },
};

// Tasks API
export const tasksApi = {
  list: (params?: Record<string, any>) =>
    api.get<any>('/tasks', params),

  listSimple: (params?: Record<string, any>) =>
    api.get<any>('/tasks/simple', params),

  get: (id: string) =>
    api.get<any>(`/tasks/${id}`),

  create: (data: any) =>
    api.post<any>('/tasks', data),

  update: (id: string, data: any) =>
    api.put<any>(`/tasks/${id}`, data),

  delete: (id: string) =>
    api.delete<any>(`/tasks/${id}`),

  getNetAmount: (id: string) =>
    api.get<any>(`/tasks/${id}/net-amount`),

  addDeduction: (taskId: string, data: any) =>
    api.post<any>(`/tasks/${taskId}/deductions`, data),

  updateDeduction: (taskId: string, deductionId: string, data: any) =>
    api.put<any>(`/tasks/${taskId}/deductions/${deductionId}`, data),

  deleteDeduction: (taskId: string, deductionId: string) =>
    api.delete<any>(`/tasks/${taskId}/deductions/${deductionId}`),
};

// Daily Assignments API
export const dailyAssignmentsApi = {
  list: (params?: Record<string, any>) =>
    api.get<any>('/daily-assignments', params),

  create: (data: { date: string; worker_id: string; task_id: string }) =>
    api.post<any>('/daily-assignments', data),

  preview: (file: FormData) =>
    api.post<any>('/daily-assignments/preview', file, true),

  process: (data: any) =>
    api.post<any>('/daily-assignments/process', data),

  downloadTemplate: () => {
    const url = `${API_BASE_URL}/daily-assignments/template`;
    return downloadFile(url, 'plantilla_asignaciones.xlsx');
  },
};

// Liquidation Reports API
export const liquidationReportsApi = {
  generate: (data: any) =>
    api.post<any>('/reports/liquidation', data),

  exportExcel: (params?: Record<string, any>) => {
    const queryString = params ? '?' + new URLSearchParams(params as Record<string, string>).toString() : '';
    const url = `${API_BASE_URL}/reports/liquidation/export-excel${queryString}`;
    return downloadFile(url, 'liquidacion.xlsx');
  },

  exportPdf: (params?: Record<string, any>) => {
    const queryString = params ? '?' + new URLSearchParams(params as Record<string, string>).toString() : '';
    const url = `${API_BASE_URL}/reports/liquidation/export-pdf${queryString}`;
    return downloadFile(url, 'liquidacion.pdf');
  },
};

// Liquidation Analytics API
export const liquidationAnalyticsApi = {
  laborCosts: (data: any) =>
    api.post<any>('/reports/analytics/labor-costs', data),

  workerProductivity: (data: any) =>
    api.post<any>('/reports/analytics/worker-productivity', data),

  taskAnalysis: (data: any) =>
    api.post<any>('/reports/analytics/task-analysis', data),

  deductionsBreakdown: (data: any) =>
    api.post<any>('/reports/analytics/deductions-breakdown', data),

  periodComparison: (data: any) =>
    api.post<any>('/reports/analytics/period-comparison', data),

  exportExcel: (type: string, params?: Record<string, any>) => {
    const queryString = params ? '?' + new URLSearchParams(params as Record<string, string>).toString() : '';
    const url = `${API_BASE_URL}/reports/analytics/${type}/export-excel${queryString}`;
    return downloadFile(url, `${type}-report.xlsx`);
  },

  exportPdf: (type: string, params?: Record<string, any>) => {
    const queryString = params ? '?' + new URLSearchParams(params as Record<string, string>).toString() : '';
    const url = `${API_BASE_URL}/reports/analytics/${type}/export-pdf${queryString}`;
    return downloadFile(url, `${type}-report.pdf`);
  },
};

/**
 * Helper function to download files from API
 */
async function downloadFile(url: string, filename: string): Promise<void> {
  try {
    const response = await fetch(url, {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${authToken}`,
      },
    });

    if (!response.ok) {
      // Try to parse error message
      const data = await response.json().catch(() => ({}));
      throw new Error(data.message || `Error al descargar archivo: ${response.status}`);
    }

    // Get blob from response
    const blob = await response.blob();

    // Create download link
    const downloadUrl = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.download = filename;
    document.body.appendChild(link);
    link.click();

    // Cleanup
    document.body.removeChild(link);
    window.URL.revokeObjectURL(downloadUrl);
  } catch (error) {
    console.error('Error downloading file:', error);
    throw error;
  }
}
