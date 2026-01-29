import type { ApiResponse } from '../types/index';

class MockApiService {
  private delay = (ms: number = 500) => new Promise(resolve => setTimeout(resolve, ms));

  async get<T>(endpoint: string, params?: Record<string, any>): Promise<T> {
    await this.delay();
    console.log(`Mock API GET: ${endpoint}`, params);

    // Simulate different responses based on endpoint
    const mockData = this.getMockData(endpoint);
    return mockData as T;
  }

  async post<T>(endpoint: string, data: any): Promise<T> {
    await this.delay();
    console.log(`Mock API POST: ${endpoint}`, data);

    // Simulate creation with new ID
    const newItem = {
      id: `mock-${Date.now()}`,
      ...data,
      createdAt: new Date(),
      updatedAt: new Date(),
    };

    return { data: newItem, message: 'Created successfully', success: true } as T;
  }

  async put<T>(endpoint: string, data: any): Promise<T> {
    await this.delay();
    console.log(`Mock API PUT: ${endpoint}`, data);

    const updatedItem = {
      ...data,
      updatedAt: new Date(),
    };

    return { data: updatedItem, message: 'Updated successfully', success: true } as T;
  }

  async delete(endpoint: string): Promise<ApiResponse<null>> {
    await this.delay();
    console.log(`Mock API DELETE: ${endpoint}`);

    return { data: null, message: 'Deleted successfully', success: true };
  }

  private getMockData(endpoint: string): any {
    // Simple endpoint routing for mock data
    if (endpoint.includes('/products')) {
      return { data: [], total: 0, page: 1, limit: 10 };
    }

    if (endpoint.includes('/users')) {
      return { data: [], total: 0, page: 1, limit: 10 };
    }

    if (endpoint.includes('/brands')) {
      return { data: [], total: 0, page: 1, limit: 10 };
    }

    if (endpoint.includes('/suppliers')) {
      return { data: [], total: 0, page: 1, limit: 10 };
    }

    if (endpoint.includes('/locations')) {
      return { data: [], total: 0, page: 1, limit: 10 };
    }

    if (endpoint.includes('/recipes')) {
      return { data: [], total: 0, page: 1, limit: 10 };
    }

    if (endpoint.includes('/orders')) {
      return { data: [], total: 0, page: 1, limit: 10 };
    }

    if (endpoint.includes('/purchases')) {
      return { data: [], total: 0, page: 1, limit: 10 };
    }

    if (endpoint.includes('/inventory')) {
      return { data: [], total: 0, page: 1, limit: 10 };
    }

    // Default response
    return { data: null, message: 'Not found', success: false };
  }
}

export const mockApi = new MockApiService();