import apiService from './ApiService';

export interface PaymentCalculation {
  payment_amount: number;
  will_be_allocated: number;
  overpayment: number;
  allocations: {
    bill_id: number;
    month_year: string;
    due_date: string;
    current_balance: number;
    amount_to_allocate: number;
    remaining_balance: number;
    new_status: string;
  }[];
  contract_summary: {
    current_paid: number;
    after_payment_paid: number;
    current_remaining: number;
    after_payment_remaining: number;
    current_balance: number;
    after_payment_balance: number;
  };
}

export interface CreatePaymentDTO {
  contract_id: number;
  amount: number;
  payment_type: string;
  paid_date: string;
  note?: string;
}

class PaymentService {
  async getContractsByParent(parentId: number): Promise<any[]> {
    const response = await apiService.get<any>('/contracts', { parent_id: parentId, paginate: false });
    return response.data || [];
  }

  async getUnpaidBills(contractId: number): Promise<any[]> {
    const response = await apiService.get<any[]>(`/bills/contract/${contractId}/unpaid`);
    return response.data || [];
  }

  async calculatePayment(contractId: number, amount: number): Promise<PaymentCalculation> {
    const response = await apiService.post<PaymentCalculation>('/payments/calculate', {
      contract_id: contractId,
      amount
    });
    return response.data!;
  }

  async processPayment(data: CreatePaymentDTO): Promise<any> {
    const response = await apiService.post('/payments', data);
    return response;
  }
}

export default new PaymentService();
