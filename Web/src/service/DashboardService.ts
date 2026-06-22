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
  ApiResponse,
  FinancialReport,
  BillRecord,
  PaymentRecord
} from '@/types';

class DashboardService {
  /**
   * Get dashboard statistics
   */
  async getStats(academicYear?: string): Promise<DashboardStats> {
    const params = academicYear ? { academic_year: academicYear } : {};
    const response = await apiService.get<DashboardStats>('/dashboard/stats', params);
    return response.data!;
  }

  async getStudents(params?: {
    class_id?: number; is_active?: boolean; search?: string; page?: number; per_page?: number;
  }): Promise<PaginatedResponse<Student>> {
    return await apiService.get<PaginatedResponse<Student>>('/students', params) as any;
  }

  async getTeachers(params?: {
    search?: string; page?: number; per_page?: number;
  }): Promise<PaginatedResponse<Teacher>> {
    return await apiService.get<PaginatedResponse<Teacher>>('/teachers', params) as any;
  }

  async getClasses(params?: {
    academic_year?: string; is_active?: boolean;
  }): Promise<ApiResponse<SchoolClass[]>> {
    return await apiService.get<SchoolClass[]>('/classes', params);
  }

  async getRecentPayments(limit: number = 10): Promise<Payment[]> {
    const response = await apiService.get<Payment[]>('/payments', {
      per_page: limit, status: 'paid', sort: 'paid_date', order: 'desc'
    });
    return response.data || [];
  }

  async getAttendanceStats(days: number = 30): Promise<any> {
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - days);
    return await apiService.get('/attendance', {
      start_date: startDate.toISOString().split('T')[0],
      end_date: endDate.toISOString().split('T')[0]
    });
  }

  async getGradesStats(academicYear?: string): Promise<any> {
    const params = academicYear ? { academic_year: academicYear } : {};
    return await apiService.get('/grades', params);
  }

  /**
   * Get admin financial reports for payment dashboard
   */
  async getFinancialReports(params?: {
    academic_year?: string;
    start_date?: string;
    end_date?: string;
  }): Promise<FinancialReport> {
    const response = await apiService.get<FinancialReport>('/payments/financial-reports', params);
    return response.data!;
  }

  /**
   * Get all payments (with contract.parent eager-loaded)
   */
  async getAllPayments(params: any = {}): Promise<PaymentRecord[]> {
    params.per_page = 200;
    const response = await apiService.get<any>('/payments', params);
    return response.data?.data || response.data || [];
  }

  /**
   * Get all bills (with contract.parent eager-loaded)
   */
  async getAllBills(params: any = {}): Promise<BillRecord[]> {
    params.per_page = 200;
    const response = await apiService.get<any>('/bills', params);
    return response.data?.data || response.data || [];
  }
}

export default new DashboardService();
