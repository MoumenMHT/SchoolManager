import ApiService from './ApiService';

export interface SupervisorData {
  id: number;
  user_id: number;
  first_name: string;
  last_name: string;
  phone: number | string | null;
  hire_date: string | null;
  status: 'active' | 'inactive';
  user?: any;
  classes?: any[];
}

class SupervisorService {
  // ─── Supervisor Portal ─────────────────────────────────────
  async getMyClasses(): Promise<any[]> {
    const response = await ApiService.get<any>('/supervisor/classes');
    return (response.data as any)?.data ?? response.data ?? [];
  }

  async getDashboard(): Promise<any[]> {
    const response = await ApiService.get<any>('/supervisor/dashboard');
    return (response.data as any)?.data ?? response.data ?? [];
  }

  async getClassScheduleToday(classId: number): Promise<any> {
    const response = await ApiService.get<any>(`/supervisor/classes/${classId}/schedule-today`);
    return (response.data as any)?.data ?? response.data ?? {};
  }

  // ─── Admin CRUD ────────────────────────────────────────────
  async getAll(): Promise<SupervisorData[]> {
    const response = await ApiService.get<any>('/supervisors');
    return (response.data as any)?.data ?? response.data ?? [];
  }

  async getById(id: number): Promise<SupervisorData> {
    const response = await ApiService.get<any>(`/supervisors/${id}`);
    return (response.data as any)?.data ?? response.data;
  }

  async create(data: any): Promise<SupervisorData> {
    const response = await ApiService.post<any>('/supervisors', data);
    return (response.data as any)?.data ?? response.data;
  }

  async update(id: number, data: any): Promise<SupervisorData> {
    const response = await ApiService.put<any>(`/supervisors/${id}`, data);
    return (response.data as any)?.data ?? response.data;
  }

  async remove(id: number): Promise<void> {
    await ApiService.delete(`/supervisors/${id}`);
  }
}

export default new SupervisorService();
