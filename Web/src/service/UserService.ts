import ApiService from './ApiService';

const UserService = {
    getAllUsers() {
        return ApiService.get('/users').then((res: any) => {
            return res.data?.data ?? res.data ?? (Array.isArray(res) ? res : []);
        });
    },

    getUsersByRole(role: string) {
        return ApiService.get(`/users?role=${role}`).then((res: any) => {
            return res.data?.data ?? res.data ?? (Array.isArray(res) ? res : []);
        });
    },

    getUsersByRoles(roles: string[]) {
        const rolesQuery = roles.join(',');
        return ApiService.get(`/users?roles=${rolesQuery}`).then((res: any) => {
            return res.data?.data ?? res.data ?? (Array.isArray(res) ? res : []);
        });
    },

    getUser(id: number) {
        return ApiService.get(`/users/${id}`).then((res: any) => res.data ?? res);
    },

    createUser(data: any) {
        return ApiService.post('/users', data).then((res: any) => res.data ?? res);
    },

    updateUser(id: number, data: any) {
        return ApiService.put(`/users/${id}`, data).then((res: any) => res.data ?? res);
    },

    deleteUser(id: number) {
        return ApiService.delete(`/users/${id}`).then((res: any) => res.data ?? res);
    }
};

export default UserService;
