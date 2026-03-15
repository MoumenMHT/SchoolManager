import axios, { AxiosInstance, AxiosError } from 'axios';
import type { ApiResponse, AuthResponse } from '@/types';
import config from '@/config';
import { Message } from 'primevue';

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
          // Token expired or invalid
          this.removeToken();
          window.location.href = '/auth/login';
        }
        return Promise.reject(error);
      }
    );
  }

  // Token management
  private getToken(): string | null {
    return localStorage.getItem('auth_token');
  }

  public setToken(token: string): void {
    localStorage.setItem('auth_token', token);
  }

  public removeToken(): void {
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user');
  }

  // User management
  public setUser(user: any): void {
    localStorage.setItem('user', JSON.stringify(user));
  }

  public getUser(): any | null {
    const user = localStorage.getItem('user');
    return user ? JSON.parse(user) : null;
  }

  // Authentication methods
  async login(email: string, password: string): Promise<AuthResponse> {
    const response = await this.api.post<AuthResponse>('/login', { email, password });
    if (response.data.success && response.data.token) {
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
