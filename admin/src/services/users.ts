import api from './api';
import { User } from '../types';

export const usersApi = {
  getAll: async (): Promise<User[]> => {
    const response = await api.get('/admin/users');
    return response.data;
  },

  getById: async (id: number): Promise<User> => {
    const response = await api.get(`/admin/users/${id}`);
    return response.data;
  },

  create: async (data: Partial<User>): Promise<User> => {
    const response = await api.post('/admin/users', data);
    return response.data;
  },

  update: async (id: number, data: Partial<User>): Promise<User> => {
    const response = await api.put(`/admin/users/${id}`, data);
    return response.data;
  },

  delete: async (id: number): Promise<void> => {
    await api.delete(`/admin/users/${id}`);
  },
};