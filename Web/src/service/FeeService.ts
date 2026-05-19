import apiService from './ApiService';
import type { Level } from './LevelService';

export interface Fee {
  id: number;
  name: string;
  description: string | null;
  base_amount: number;
  academic_year: string;
  is_active: boolean;
  created_at: string;
  updated_at: string;
  levels?: Level[];
  pivot?: { level_id?: number; fee_id?: number };
}

export interface CreateFeeDTO {
  name: string;
  description?: string;
  base_amount: number;
  academic_year: string;
  is_active?: boolean;
}

class FeeService {
  async getFees(params?: { is_active?: boolean; academic_year?: string }): Promise<Fee[]> {
    const response = await apiService.get<Fee[]>('/fees', params);
    return response.data || [];
  }

  async getFee(id: number): Promise<Fee> {
    const response = await apiService.get<Fee>(`/fees/${id}`);
    return response.data!;
  }

  async createFee(data: CreateFeeDTO): Promise<Fee> {
    const response = await apiService.post<Fee>('/fees', data);
    return response.data!;
  }

  async updateFee(id: number, data: Partial<CreateFeeDTO>): Promise<Fee> {
    const response = await apiService.put<Fee>(`/fees/${id}`, data);
    return response.data!;
  }

  async deleteFee(id: number): Promise<void> {
    await apiService.delete(`/fees/${id}`);
  }

  async toggleStatus(id: number): Promise<Fee> {
    const response = await apiService.put<Fee>(`/fees/${id}/toggle-status`);
    return response.data!;
  }

  async syncLevels(id: number, levelIds: number[]): Promise<Fee> {
    const response = await apiService.post<Fee>(`/fees/${id}/sync-levels`, { level_ids: levelIds });
    return response.data!;
  }

  async getFeeLevels(id: number): Promise<Level[]> {
    const response = await apiService.get<Level[]>(`/fees/${id}/levels`);
    return response.data || [];
  }

  async getAvailableForContract(): Promise<{ data: Fee[]; total_if_all_selected: number }> {
    const response = await apiService.get<any>('/fees/available-for-contract');
    return { data: response.data || [], total_if_all_selected: (response as any).total_if_all_selected || 0 };
  }

  async copyToNewYear(fromYear: string, toYear: string, increasePercentage?: number): Promise<Fee[]> {
    const response = await apiService.post<Fee[]>('/fees/copy-to-new-year', {
      from_academic_year: fromYear,
      to_academic_year: toYear,
      increase_percentage: increasePercentage,
    });
    return response.data || [];
  }

  async getStatistics(params?: { academic_year?: string }): Promise<any> {
    const response = await apiService.get('/fees/statistics', params);
    return response.data;
  }
}

export default new FeeService();
