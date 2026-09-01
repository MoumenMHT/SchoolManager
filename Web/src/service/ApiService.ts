import axios, { AxiosInstance, AxiosError } from 'axios';
import type { ApiResponse, AuthResponse } from '@/types';
import config from '@/config';

class ApiService {
  private api: AxiosInstance;
  private baseURL: string;

  constructor() {
    this.baseURL = config.apiBaseUrl;
    this.api = axios.create({
      baseURL: this.baseURL,
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
    });

    // Request interceptor to add token
    this.api.interceptors.request.use(
      (config) => {
        const token = this.getToken();
        if (token) {
          config.headers.Authorization = `Bearer ${token}`;
        }
        const locale = localStorage.getItem('locale') || 'en';
        config.headers['Accept-Language'] = locale;

        // Add tenant path prefix if applicable
        const tenantId = this.getTenantId();
        const isCentralRoute = config.url?.startsWith('/central') || config.url?.startsWith('central');
        
        // Failsafe for users who have old sessions without a tenant_id
        if (!tenantId && !isCentralRoute && token && config.url !== '/central/login') {
            this.removeToken();
            window.location.href = '/auth/login';
            return Promise.reject(new Error('Missing tenant ID. Please log in again.'));
        }

        if (tenantId && !isCentralRoute && config.url && !config.url.startsWith(`/t/`)) {
            const normalizedUrl = config.url.startsWith('/') ? config.url : `/${config.url}`;
            config.url = `/t/${tenantId}${normalizedUrl}`;
        }

        return config;
      },
      (error) => {
        return Promise.reject(error);
      }
    );

    // Response interceptor for error handling
    this.api.interceptors.response.use(
      (response) => response,
      (error: AxiosError) => {
        if (error.response?.status === 401) {
          const requestUrl = error.config?.url || '';
          const isLoginRequest = /\/login\/?$/.test(requestUrl);
          const hasToken = !!this.getToken();

          // Keep login form errors on the same page; only force redirect for expired authenticated sessions.
          if (!isLoginRequest && hasToken) {
            this.removeToken();
            if (window.location.pathname !== '/auth/login') {
              window.location.href = '/auth/login';
            }
          }
        }
        return Promise.reject(error);
      }
    );
  }

  // Tenant management
  public getTenantId(): string | null {
    return localStorage.getItem('current_tenant_id');
  }

  public setTenantId(tenantId: string): void {
    localStorage.setItem('current_tenant_id', tenantId);
  }

  private getNamespacedKey(key: string): string {
    const tenantId = this.getTenantId();
    return tenantId ? `${tenantId}_${key}` : key;
  }

  // Token management
  private getToken(): string | null {
    return localStorage.getItem(this.getNamespacedKey('auth_token'));
  }

  public setToken(token: string): void {
    localStorage.setItem(this.getNamespacedKey('auth_token'), token);
  }

  public removeToken(): void {
    localStorage.removeItem(this.getNamespacedKey('auth_token'));
    localStorage.removeItem(this.getNamespacedKey('user'));
  }

  // User management
  public setUser(user: any): void {
    localStorage.setItem(this.getNamespacedKey('user'), JSON.stringify(user));
  }

  public getUser(): any | null {
    const user = localStorage.getItem(this.getNamespacedKey('user'));
    return user ? JSON.parse(user) : null;
  }

  // Authentication methods
  async login(username: string, password: string): Promise<AuthResponse> {
    const response = await this.api.post<AuthResponse>('/central/login', { username, password });
    if (response.data.success && response.data.token) {
      if ((response.data as any).tenant_id) {
        this.setTenantId((response.data as any).tenant_id);
      }
      this.setToken(response.data.token);
      this.setUser(response.data.user);
    }
    return response.data;
  }

  async logout(): Promise<void> {
    try {
      await this.api.post('/logout');
    } finally {
      this.removeToken();
    }
  }

  async getCurrentUser(): Promise<ApiResponse<any>> {
    const response = await this.api.get('/me');
    return response.data;
  }

  // Generic HTTP methods
  async get<T>(url: string, params?: any): Promise<ApiResponse<T>> {
    const response = await this.api.get<ApiResponse<T>>(url, { params });
    return response.data;
  }

  async post<T>(url: string, data?: any): Promise<ApiResponse<T>> {
    const response = await this.api.post<ApiResponse<T>>(url, data);
    return response.data;
  }

  async put<T>(url: string, data?: any): Promise<ApiResponse<T>> {
    const response = await this.api.put<ApiResponse<T>>(url, data);
    return response.data;
  }

  async delete<T>(url: string, data?: any): Promise<ApiResponse<T>> {
    const response = await this.api.delete<ApiResponse<T>>(url, { data });
    return response.data;
  }

  async download(url: string, params?: any): Promise<Blob> {
    const response = await this.api.get(url, {
      params,
      responseType: 'blob'
    });

    return response.data as Blob;
  }

  // Check if user is authenticated
  isAuthenticated(): boolean {
    return !!this.getToken();
  }
}

// Export singleton instance
export default new ApiService();
