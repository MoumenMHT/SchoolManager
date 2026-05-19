import apiService from './ApiService';
import type { Fee } from './FeeService';

export interface StudentFeeEntry {
  student_id: number;
  fee_ids: number[];
}

export interface CreateContractDTO {
  parent_id: number;
  student_fees: StudentFeeEntry[];
  academic_year: string;
  start_date: string;
  end_date: string;
  discount_type?: string | null;
  discount_value?: number;
  discount_reason?: string;
  notes?: string;
  is_active?: boolean;
}

export interface ContractStudent {
  id: number;
  first_name: string;
  last_name: string;
  class?: { id: number; name: string };
  fees?: Fee[];
}

export interface Contract {
  id: number;
  parent_id: number;
  contract_number: string;
  academic_year: string;
  total_fees: number;
  discount_type: string | null;
  discount_value: number;
  discount_reason: string | null;
  monthly_amount: number;
  paid_amount: number;
  remaining_amount: number;
  balance: number;
  start_date: string;
  end_date: string;
  notes: string | null;
  status: string;
  is_active: boolean;
  created_at: string;
  updated_at: string;
  parent?: {
    id: number;
    first_name: string;
    last_name: string;
    student_fees?: any[];
  };
  bills?: any[];
  payments?: any[];
}

class ContractService {
  async getContracts(params?: { parent_id?: number; academic_year?: string; status?: string }): Promise<Contract[]> {
    const response = await apiService.get<Contract[]>('/contracts', params);
    return response.data || [];
  }

  async getContract(id: number): Promise<Contract> {
    const response = await apiService.get<Contract>(`/contracts/${id}`);
    return response.data!;
  }

  async createContract(data: CreateContractDTO): Promise<Contract> {
    const response = await apiService.post<Contract>('/contracts', data);
    return response.data!;
  }

  async updateContract(id: number, data: CreateContractDTO): Promise<Contract> {
    const response = await apiService.put<Contract>(`/contracts/${id}`, data);
    return response.data!;
  }

  async getParentStudentsWithFees(parentId: number): Promise<ContractStudent[]> {
    const response = await apiService.get<ContractStudent[]>(`/parents/${parentId}/students-with-fees`);
    return response.data || [];
  }

  async addService(contractId: number, feeIds: number[]): Promise<Contract> {
    const response = await apiService.post<Contract>(`/contracts/${contractId}/add-service`, { fee_ids: feeIds });
    return response.data!;
  }

  async withdraw(contractId: number, data: any): Promise<any> {
    const response = await apiService.post(`/contracts/${contractId}/withdraw`, data);
    return response.data;
  }
}

export default new ContractService();
