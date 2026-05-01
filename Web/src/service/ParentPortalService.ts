import api from './ApiService';

export const ParentPortalService = {
    // ---- Dashboard ----
    async getDashboard() {
        const response = await api.get('/parent/dashboard');
        return response.data;
    },

    // ---- Children ----
    async getMyChildren() {
        const response = await api.get('/parent/students');
        return response.data.data ? response.data.data : response.data;
    },

    // ---- Child Specific Details ----
    async getChildSchedule(studentId: number) {
        const response = await api.get(`/parent/students/${studentId}/schedule`);
        return response.data;
    },
    async getChildAttendances(studentId: number) {
        const response = await api.get(`/parent/students/${studentId}/attendances`);
        return response.data;
    },
    async getChildGrades(studentId: number) {
        const response = await api.get(`/parent/students/${studentId}/grades`);
        return response.data;
    },
    async getChildReportCard(studentId: number) {
        // This might return a file download or a URL depending on API implementation
        const response = await api.get(`/parent/students/${studentId}/report-card`, { responseType: 'blob' });
        return response.data;
    },

    // ---- Finances & Payments ----
    async getContracts() {
        const response = await api.get('/parent/contracts');
        return response.data.data ? response.data.data : response.data;
    },
    async getContractDetails(contractId: number) {
        const response = await api.get(`/parent/contracts/${contractId}`);
        return response.data.data ? response.data.data : response.data;
    },
    async getBills() {
        const response = await api.get('/parent/bills');
        return response.data.data ? response.data.data : response.data;
    },
    async getPayments() {
        const response = await api.get('/parent/payments');
        return response.data.data ? response.data.data : response.data;
    },
    async getChildPayments(studentId: number) {
        const response = await api.get(`/parent/students/${studentId}/payments`);
        return response.data.data ? response.data.data : response.data;
    }
};
