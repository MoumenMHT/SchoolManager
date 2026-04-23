import apiService from './ApiService';
import type { ApiResponse } from '@/types';

export interface Student {
  id: number;
  first_name: string;
  last_name: string;
  date_of_birth?: string;
  class_name?: string;
  class?: {
    id: number;
    name: string;
  };
}

export interface Parent {
  id: number;
  user_id: number | null;
  first_name: string;
  last_name: string;
  phone: string | null;
  email: string | null;
  cin: string | null;
  profession: string | null;
  created_at: string;
  updated_at: string;
  // Virtual attributes
  full_name?: string;
  contact_email?: string;
  contact_phone?: string;
  has_account?: boolean;
  students_count?: number;
  students?: Student[];
}

export interface CreateParentDTO {
  first_name: string;
  last_name: string;
  phone?: string;
  email?: string;
  cin?: string;
  profession?: string;
}

export interface UpdateParentDTO {
  first_name?: string;
  last_name?: string;
  phone?: string;
  email?: string;
  cin?: string;
  profession?: string;
}

class ParentService {
  /**
   * Get all parents
   */
  async getParents(): Promise<Parent[]> {
    const response = await apiService.get<Parent[]>('/parents');
    console.log('Fetched parents:', response.data);
    return response.data || [];
  }

  /**
   * Get a single parent by ID
   */
  async getParent(id: number): Promise<Parent> {
    const response = await apiService.get<Parent>(`/parents/${id}`);
    console.log(`Fetched parent with ID ${id}:`, response.data);
    return response.data!;
  }

  /**
   * Create a new parent
   */
  async createParent(data: CreateParentDTO): Promise<Parent> {
    const response = await apiService.post<Parent>('/parents', data);
    return response.data!;
  }

  /**
   * Update an existing parent
   */
  async updateParent(id: number, data: UpdateParentDTO): Promise<Parent> {
    const response = await apiService.put<Parent>(`/parents/${id}`, data);
    return response.data!;
  }

  /**
   * Delete a parent
   */
  async deleteParent(id: number): Promise<void> {
    await apiService.delete(`/parents/${id}`);
  }

  /**
   * Bulk delete parents
   */
  async bulkDeleteParents(ids: number[]): Promise<void> {
    await Promise.all(ids.map(id => this.deleteParent(id)));
  }

  /**
   * Create user account for parent
   */
  async createUserAccount(parentId: number, username: string, password: string): Promise<Parent> {
    const response = await apiService.post<Parent>(`/parents/${parentId}/create-account`, {
      username,
      password
    });
    return response.data!;
  }
}

export default new ParentService();
