import apiService from '@/service/ApiService';

// ── Types ─────────────────────────────────────────────────

export interface TenantFilters {
    search?: string;
    status?: 'active' | 'inactive';
    plan?: string;
    expiring_soon?: boolean;
    sort_by?: string;
    sort_dir?: 'asc' | 'desc';
    per_page?: number;
    page?: number;
}

export interface CreateTenantPayload {
    id: string;
    name: string;
    contact_name?: string;
    contact_email?: string;
    contact_phone?: string;
    subscription_plan?: string;
    subscription_expires_at?: string;
    max_students?: number;
    notes?: string;
    address?: string;
    phone?: string;
    email?: string;
    admin_username?: string;
    admin_password?: string;
}

export interface UpdateTenantPayload {
    name?: string;
    contact_name?: string;
    contact_email?: string;
    contact_phone?: string;
    subscription_plan?: string;
    subscription_expires_at?: string;
    max_students?: number;
    notes?: string;
    address?: string;
    phone?: string;
    email?: string;
}

export interface RecordPaymentPayload {
    tenant_id: string;
    amount: number;
    currency?: string;
    status: string;
    payment_method: string;
    payment_date: string;
    period_start: string;
    period_end: string;
    reference?: string;
    notes?: string;
}

export interface PaymentFilters {
    tenant_id?: string;
    status?: string;
    from?: string;
    to?: string;
    payment_method?: string;
    sort_by?: string;
    sort_dir?: 'asc' | 'desc';
    per_page?: number;
    page?: number;
}

// ── Service ───────────────────────────────────────────────

class SuperAdminService {
    private readonly basePath = '/central';

    // ── Dashboard ─────────────────────────────────────────

    async getDashboardStats(): Promise<any> {
        const res = await apiService.get(`${this.basePath}/dashboard/stats`);
        return res.data;
    }

    async getRevenueChart(): Promise<any> {
        const res = await apiService.get(`${this.basePath}/dashboard/revenue-chart`);
        return res.data;
    }

    async getTenantGrowth(): Promise<any> {
        const res = await apiService.get(`${this.basePath}/dashboard/tenant-growth`);
        return res.data;
    }

    // ── Tenants ───────────────────────────────────────────

    async getTenants(params?: TenantFilters): Promise<any> {
        return apiService.get(`${this.basePath}/tenants`, params);
    }

    async getTenant(id: string): Promise<any> {
        return apiService.get(`${this.basePath}/tenants/${id}`);
    }

    async createTenant(data: CreateTenantPayload): Promise<any> {
        return apiService.post(`${this.basePath}/tenants`, data);
    }

    async updateTenant(id: string, data: UpdateTenantPayload): Promise<any> {
        return apiService.put(`${this.basePath}/tenants/${id}`, data);
    }

    async activateTenant(id: string): Promise<any> {
        return apiService.post(`${this.basePath}/tenants/${id}/activate`);
    }

    async deactivateTenant(id: string): Promise<any> {
        return apiService.post(`${this.basePath}/tenants/${id}/deactivate`);
    }

    async deleteTenant(id: string): Promise<any> {
        return apiService.delete(`${this.basePath}/tenants/${id}`);
    }

    // ── Platform Payments ─────────────────────────────────

    async getPayments(params?: PaymentFilters): Promise<any> {
        return apiService.get(`${this.basePath}/payments`, params);
    }

    async getPayment(id: number): Promise<any> {
        return apiService.get(`${this.basePath}/payments/${id}`);
    }

    async recordPayment(data: RecordPaymentPayload): Promise<any> {
        return apiService.post(`${this.basePath}/payments`, data);
    }

    async updatePayment(id: number, data: Partial<RecordPaymentPayload>): Promise<any> {
        return apiService.put(`${this.basePath}/payments/${id}`, data);
    }

    async deletePayment(id: number): Promise<any> {
        return apiService.delete(`${this.basePath}/payments/${id}`);
    }

    async getPaymentSummary(): Promise<any> {
        const res = await apiService.get(`${this.basePath}/payments/summary`);
        return res.data;
    }

    // ── Activity Log ──────────────────────────────────────

    async getActivityLog(params?: { tenant_id?: string; action?: string; per_page?: number; page?: number }): Promise<any> {
        return apiService.get(`${this.basePath}/activity-log`, params);
    }
}

export default new SuperAdminService();
