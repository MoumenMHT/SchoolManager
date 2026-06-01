import api from './ApiService';

export const ParentPortalService = {
    // ---- Dashboard ----
    async getDashboard() {
        const response = await api.get<any>('/parent/dashboard');
        return response.data;
    },

    // ---- Children ----
    async getMyChildren() {
        const response = await api.get<any>('/parent/students');
        return response.data ? response.data : response;
    },

    // ---- Child Specific Details ----
    async getChildSchedule(studentId: number) {
        const response = await api.get<any>(`/parent/students/${studentId}/schedule`);
        return response.data;
    },
    async getChildAttendances(studentId: number) {
        const response = await api.get<any>(`/parent/students/${studentId}/attendances`);
        return response.data;
    },
    async getChildGrades(studentId: number) {
        const response = await api.get<any>(`/parent/students/${studentId}/grades`);
        return response.data;
    },
    async getChildReportCard(studentId: number, semester: string, academicYear: string) {
        const response = await api.get<any>(`/parent/students/${studentId}/report-card`, {
            semester,
            academic_year: academicYear,
        });
        return response.data ? response.data : response;
    },

    // ---- Finances & Payments ----
    async getContracts() {
        const response = await api.get<any>('/parent/contracts');
        return response.data ? response.data : response;
    },
    async getContractDetails(contractId: number) {
        const response = await api.get<any>(`/parent/contracts/${contractId}`);
        return response.data ? response.data : response;
    },
    async getBills() {
        const response = await api.get<any>('/parent/bills');
        return response.data ? response.data : response;
    },
    async getPayments() {
        const response = await api.get<any>('/parent/payments');
        return response.data ? response.data : response;
    },
    async getChildPayments(studentId: number) {
        const response = await api.get<any>(`/parent/students/${studentId}/payments`);
        return response.data ? response.data : response;
    }
};
