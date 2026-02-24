import apiService from './ApiService';
import type { ApiResponse } from '@/types';

export interface SchoolClass {
  
  id: number;
  name: string;
  level: string;
  academic_year: string | null;
  capacity: number | null;
  main_teacher_id: number | null;
  is_active: boolean | null;
  updated_at: string;
  subjects?: {
    name: string;
    discription: string;
  }[];
  teachers?: {
    name: string;
  }[];
  sudents?: {
    id: number;
    first_name: string;
    last_name: string;
    code: string;
    birth_date: Date | null;
    mdical_info: string | null;
  }[];
  teachers_count?: number;
  students_count?: number;
}

export interface CreateTeacherDTO {
  name: string;
  level: string;
  academic_year?: Date;
  capacity?: string;
  main_teacher_id?: string | null;
}

export interface UpdateTeacherDTO {
  name: string;
  level: string;
  academic_year?: Date;
  capacity?: string;
  main_teacher_id?: string | null;
}

class SchoolClassService {
  /**
   * Get all classes
   */
  async getClasses(): Promise<SchoolClass[]> {
    const response = await apiService.get<SchoolClass[]>('/classes');
    console.log('Fetched classes:', response.data);    
    return response.data || [];
  }

  /**
   * Get a single class by ID
   */
  async getClass(id: number): Promise<SchoolClass> {
    const response = await apiService.get<SchoolClass>(`/classes/${id}`);
    console.log('Fetched class:', response.data);
    return response.data!;
  }

  /**
   * Create a new class
   */
  async createClass(data: CreateTeacherDTO): Promise<SchoolClass> {
    console.log('Creating class with data:', data);
    const response = await apiService.post<SchoolClass>('/classes', data);
    return response.data!;
  }

  /**
   * Update an existing class
   */
  async updateClass(id: number, data: UpdateTeacherDTO): Promise<SchoolClass> {
    const response = await apiService.put<SchoolClass>(`/classes/${id}`, data);
    return response.data!;
  }

  /**
   * Delete a class
   */
  async deleteClass(id: number): Promise<void> {
    await apiService.delete(`/classes/${id}`);
  }

  /**F
   * Bulk delete classes
   */
  async bulkDeleteClasses(ids: number[]): Promise<void> {
    await Promise.all(ids.map(id => this.deleteClass(id)));
  }

  /**
   * Get class assignments (teacher-subject assignments for a class)
   */
  async getClassAssignments(classId: number): Promise<any> {
    const response = await apiService.get<any>(`/classes/${classId}/assignments`);
    return response.data!;
  }


}

export default new SchoolClassService();
