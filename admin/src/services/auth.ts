import api from './api';

export type LoginCredentials = {
  email: string;
  password: string;
};

export type User = {
  id: number;
  name: string;
  email: string;
  is_admin: boolean;
};

export type AuthResponse = {
  user: User;
  token: string;
};

export const getAuthHeader = () => {
  const token = localStorage.getItem('admin_token');
  return token ? { Authorization: `Bearer ${token}` } : {};
};

export const authService = {
  async login(credentials: LoginCredentials): Promise<AuthResponse> {
    const response = await api.post<AuthResponse>('/admin/login', credentials);
    localStorage.setItem('admin_token', response.data.token);
    return response.data;
  },

  async logout(): Promise<void> {
    await api.post('/admin/logout');
    localStorage.removeItem('admin_token');
  },

  async getMe(): Promise<User> {
    const response = await api.get<User>('/admin/me');
    return response.data;
  },

  isAuthenticated(): boolean {
    return !!localStorage.getItem('admin_token');
  },
};