import apiService from './ApiService';
import type { 
  DashboardStats, 
  Student, 
  Teacher, 
  SchoolClass,
  Payment,
  Attendance,
  Grade,
  PaginatedResponse,
  ApiResponse 
} from '@/types';

class DashboardService {
  /**
   * Get dashboard statistics
   * @param academicYear - Optional academic year filter
   */
  async getStats(academicYear?: string): Promise<DashboardStats> {
    const params = academicYear ? { academic_year: academicYear } : {};
    const response = await apiService.get<DashboardStats>('/dashboard/stats', params);
    return response.data!;
  }

  /**
   * Get all students
   */
  async getStudents(params?: {
    class_id?: number;
    is_active?: boolean;
    search?: string;
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<Student>> {
    return await apiService.get<PaginatedResponse<Student>>('/students', params) as any;
  }

  /**
   * Get all teachers
   */
  async getTeachers(params?: {
    search?: string;
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<Teacher>> {
    return await apiService.get<PaginatedResponse<Teacher>>('/teachers', params) as any;
  }

  /**
   * Get all classes
   */
  async getClasses(params?: {
    academic_year?: string;
    is_active?: boolean;
  }): Promise<ApiResponse<SchoolClass[]>> {
    return await apiService.get<SchoolClass[]>('/classes', params);
  }

  /**
   * Get recent payments
   */
  async getRecentPayments(limit: number = 10): Promise<Payment[]> {
    const response = await apiService.get<Payment[]>('/payments', { 
      per_page: limit,
      status: 'paid',
      sort: 'paid_date',
      order: 'desc'
    });
    return response.data || [];
  }

  /**
   * Get attendance statistics
   */
  async getAttendanceStats(days: number = 30): Promise<any> {
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - days);
    
    return await apiService.get('/attendance', {
      start_date: startDate.toISOString().split('T')[0],
      end_date: endDate.toISOString().split('T')[0]
    });
  }

  /**
   * Get grades statistics
   */
  async getGradesStats(academicYear?: string): Promise<any> {
    const params = academicYear ? { academic_year: academicYear } : {};
    return await apiService.get('/grades', params);
  }
}

export default new DashboardService();
