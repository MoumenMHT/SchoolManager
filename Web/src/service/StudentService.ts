import apiService from './ApiService';
import type { ApiResponse } from '@/types';
import type { SchoolClass } from './ClassesService';
import type { Parent } from './ParentService';

export interface StudentHistory {
  id: number;
  student_id: number;
  class_id: number | null;
  academic_year: string;
  enrolled_at: string;
  left_at: string | null;
  school_class?: Pick<SchoolClass, 'id' | 'name' | 'level' | 'academic_year'>;
}

export interface Student {
  id: number;
  first_name: string;
  last_name: string;
  code: string;
  birth_date: Date | null;
  gender: string | null;
  class_id: number | null;
  parent_id: number | null;
  enrollment_date: Date | null;
  medical_info: string | null;
  is_active: boolean;
  created_at: string;
  updated_at: string;
  // Relationships
  class?: SchoolClass;
  parent?: Parent;
}

export interface CreateStudentDTO {
  first_name: string;
  last_name: string;
  code: string;
  birth_date?: string;
  gender?: string;
  class_id?: number;
  parent_id?: number;
  enrollment_date?: string;
  medical_info?: string;
  is_active?: boolean;
}

export interface UpdateStudentDTO {
  first_name?: string;
  last_name?: string;
  code?: string;
  birth_date?: string;
  gender?: string;
  class_id?: number;
  parent_id?: number;
  enrollment_date?: string;
  medical_info?: string;
  is_active?: boolean;
}

class StudentService {
  /**
   * Get all students
   */
  async getStudents(params?: any): Promise<any> {
    const response = await apiService.get<any>('/students', params);
    return response.data;
  }

  /**
   * Get a single student by ID
   */
  async getStudent(id: number): Promise<Student> {
    const response = await apiService.get<Student>(`/students/${id}`);
    console.log('student data', response.data)
    return response.data!;
  }

  /**
   * Create a new student
   */
  async createStudent(data: CreateStudentDTO): Promise<Student> {
    console.log('Creating student with data:', data);
    const response = await apiService.post<Student>('/students', data);
    console.log('Created student:', response.data);
    return response.data!;
  }

  /**
   * Update an existing student
   */
  async updateStudent(id: number, data: UpdateStudentDTO): Promise<Student> {
    const response = await apiService.put<Student>(`/students/${id}`, data);
    return response.data!;
  }

  /**
   * Delete a student
   */
  async deleteStudent(id: number): Promise<void> {
    await apiService.delete(`/students/${id}`);
  }

  /**
   * Bulk delete students
   */
  async bulkDeleteStudents(ids: number[]): Promise<void> {
    await Promise.all(ids.map(id => this.deleteStudent(id)));
  }

  /**
   * Assign student to a class
   */
  async assignStudentToClass(studentId: number, classId: number): Promise<Student> {
    const response = await apiService.put<Student>(`/students/${studentId}`, {
      class_id: classId
    });
    return response.data!;
  }

  /**
   * Remove student from class
   */
  async removeStudentFromClass(studentId: number): Promise<Student> {
    console.log(`Removing student ${studentId} from class`);
    const response = await apiService.put<Student>(`/classes/remove-from-class/${studentId}`, {
      class_id: null
    });
    return response.data!;
  }

  /**
   * Search students without a class
   */
  async searchStudentsWithoutClass(): Promise<Student[]> {
    const response = await apiService.get<Student[]>('/students/without-class');
    return response.data || [];
  }

  /**
   * Get class history for a student
   */
  async getStudentHistory(studentId: number): Promise<StudentHistory[]> {
    const response = await apiService.get<StudentHistory[]>(`/students/${studentId}/history`);
    return response.data || [];
  }
}

export default new StudentService();
