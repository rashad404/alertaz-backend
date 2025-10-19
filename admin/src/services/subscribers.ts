import api from './api';

export interface Subscriber {
  id: number;
  email: string;
  language: 'az' | 'en' | 'ru';
  status: 'active' | 'unsubscribed';
  token: string;
  subscribed_at: string | null;
  ip_address: string | null;
  created_at: string;
  updated_at: string;
}

export interface SubscriberStats {
  total: number;
  active: number;
  unsubscribed: number;
  by_language: {
    az: number;
    en: number;
    ru: number;
  };
  recent_30_days: number;
}

export const subscribersService = {
  // Get all subscribers with filters
  getAll: async (params?: {
    page?: number;
    per_page?: number;
    status?: string;
    language?: string;
    search?: string;
  }) => {
    const response = await api.get('/admin/subscribers', { params });
    return response.data;
  },

  // Get single subscriber
  getOne: async (id: number) => {
    const response = await api.get(`/admin/subscribers/${id}`);
    return response.data;
  },

  // Create subscriber
  create: async (data: {
    email: string;
    language: 'az' | 'en' | 'ru';
    status: 'active' | 'unsubscribed';
  }) => {
    const response = await api.post('/admin/subscribers', data);
    return response.data;
  },

  // Update subscriber
  update: async (id: number, data: {
    email: string;
    language: 'az' | 'en' | 'ru';
    status: 'active' | 'unsubscribed';
  }) => {
    const response = await api.put(`/admin/subscribers/${id}`, data);
    return response.data;
  },

  // Delete subscriber
  delete: async (id: number) => {
    const response = await api.delete(`/admin/subscribers/${id}`);
    return response.data;
  },

  // Get statistics
  getStats: async () => {
    const response = await api.get('/admin/subscribers/stats');
    return response.data;
  },

  // Export to CSV
  exportToCsv: async (params?: {
    status?: string;
    language?: string;
  }) => {
    const response = await api.get('/admin/subscribers/export', {
      params,
      responseType: 'blob'
    });
    
    // Create download link
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `subscribers_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  }
};