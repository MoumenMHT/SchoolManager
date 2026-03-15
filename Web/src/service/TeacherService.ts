import apiService from './ApiService';
import type { ApiResponse } from '@/types';

export interface Teacher {
  email: string;
  id: number;
  user_id: number | null;
  first_name: string;
  last_name: string;
  cin: string | null;
  birth_date: Date | string | null;
  created_at: string;
  hire_date: Date | string | null;
  specialization: string | null;
  salary: number | null;
  contract_type?: 'permanent' | 'part_time';
  weekly_hours?: number | null;
  availabilities?: TeacherAvailability[];
  updated_at: string;
  subjects?: {
    id: number;
    name: string;
    discription: string;
    created_at: string;
    updated_at: string;
  }[];
  classes?: {
    name: string;
  }[];
  classes_count?: number;
  phone?: string;
  // Virtual attributes
  full_name?: string;
  contact_email?: string;
  has_account?: boolean;
  students_count?: number;
}

export interface TeacherAvailability {
  id?: number;
  day: 'Monday' | 'Tuesday' | 'Wednesday' | 'Thursday' | 'Friday' | 'Saturday' | 'Sunday';
  start_time: string;
  end_time: string;
}

export interface CreateTeacherDTO {
  first_name: string;
  last_name: string;
  cin?: string;
  birth_date?: string;
  hire_date?: string;
  specialization?: string;
  salary?: number;
  contract_type?: 'permanent' | 'part_time';
  weekly_hours?: number;
  availabilities?: TeacherAvailability[];
}

export interface UpdateTeacherDTO {
  first_name: string;
  last_name: string;
  cin?: string;
  birth_date?: string;
  hire_date?: string;
  specialization?: string;
  salary?: number;
  contract_type?: 'permanent' | 'part_time';
  weekly_hours?: number;
  availabilities?: TeacherAvailability[];
}

class TeacherService {
  /**
   * Get all teachers
   */
  async getTeachers(): Promise<Teacher[]> {
    const response = await apiService.get<Teacher[]>('/teachers');
    console.log('Fetched teachers:', response.data);    
    return response.data || [];
  }

  /**
   * Get a single teacher by ID
   */
  async getTeacher(id: number): Promise<Teacher> {
    const response = await apiService.get<Teacher>(`/teachers/${id}`);
    return response.data!;
  }

  /**
   * Create a new teacher
   */
  async createTeacher(data: CreateTeacherDTO): Promise<Teacher> {
    console.log('Creating teacher with data:', data);
    const response = await apiService.post<Teacher>('/teachers', data);
    return response.data!;
  }

  /**
   * Update an existing teacher
   */
  async updateTeacher(id: number, data: UpdateTeacherDTO): Promise<Teacher> {
    const response = await apiService.put<Teacher>(`/teachers/${id}`, data);
    return response.data!;
  }

  /**
   * Delete a teacher
   */
  async deleteTeacher(id: number): Promise<void> {
    await apiService.delete(`/teachers/${id}`);
  }

  /**
   * Bulk delete teachers
   */
  async bulkDeleteTeachers(ids: number[]): Promise<void> {
    await Promise.all(ids.map(id => this.deleteTeacher(id)));
  }

  /**
   * Create user account for teacher
   */
  async createUserAccount(teacher_id: number, email: string, password: string, username: string, role: string): Promise<Teacher> {
    console.log('Creating user account for teacher:', { teacher_id, email, username, role });
    const response = await apiService.post<Teacher>(`/register`, {
      email,
      password,
      username,
      role,
      teacher_id
    });
    return response.data!;
  }

  /**
   * Get subjects for a specific teacher
   */
  async getTeacherSubjects(teacherId: number): Promise<any[]> {
    const response = await apiService.get<any[]>(`/teachers/${teacherId}/subjects`);
    return response.data || [];
  }

  /**
   * Assign a single subject to a teacher
   */
  async assignSubject(teacherId: number, subjectId: number): Promise<void> {
    await apiService.post('/teacher-subjects', {
      teacher_id: teacherId,
      subject_id: subjectId
    });
  }

  /**
   * Assign multiple subjects to a teacher
   */
  async assignMultipleSubjects(teacherId: number, subjectIds: number[]): Promise<any> {
    console.warn('Assigning subjects:', { teacherId, subjectIds });
    const response = await apiService.post('/teacher-subjects/assign-multiple', {
      teacher_id: teacherId,
      subject_ids: subjectIds
    });
    return response.data;
  }

  /**
   * Remove a subject from a teacher
   */
  async removeSubject(teacherId: number, subjectId: number): Promise<void> {
    await apiService.post('/teacher-subjects/remove', {
      teacher_id: teacherId,
      subject_id: subjectId
    });
  }
}

export default new TeacherService();
