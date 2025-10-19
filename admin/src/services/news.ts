import api from './api';
import type { News, PaginatedResponse } from '../types';

export type NewsFilters = {
  search?: string;
  language?: string;
  category_id?: number;
  status?: boolean;
  author?: string;
  sort_by?: string;
  sort_order?: 'asc' | 'desc';
  per_page?: number;
  page?: number;
};

export type NewsFormData = {
  language: 'az' | 'en' | 'ru';
  title: string;
  sub_title?: string;
  slug?: string;
  body: string;
  category_id: number;
  category_ids?: number[];
  company_id?: number;
  news_type?: 'private' | 'official' | 'press' | 'interview' | 'analysis' | 'translation' | 'other';
  status: boolean;
  show_in_slider?: boolean;
  slider_order?: number;
  publish_date?: string;
  author?: string;
  author_id?: number;
  hashtags?: string[];
  seo_title?: string;
  seo_keywords?: string;
  seo_description?: string;
};

export const newsService = {
  async getAll(filters: NewsFilters = {}): Promise<PaginatedResponse<News>> {
    const response = await api.get<PaginatedResponse<News>>('/admin/news-items', { params: filters });
    return response.data;
  },

  async getById(id: number): Promise<News> {
    const response = await api.get<any>(`/admin/news-items/${id}`);
    // Handle both wrapped and unwrapped responses
    const data = response.data.data || response.data;
    return data;
  },

  async create(data: NewsFormData): Promise<{ message: string; data: News }> {
    const response = await api.post<{ message: string; data: News }>('/admin/news-items', data);
    return response.data;
  },

  async update(id: number, data: Partial<NewsFormData>): Promise<{ message: string; data: News }> {
    const response = await api.put<{ message: string; data: News }>(`/admin/news-items/${id}`, data);
    return response.data;
  },

  async delete(id: number): Promise<{ message: string }> {
    const response = await api.delete<{ message: string }>(`/admin/news-items/${id}`);
    return response.data;
  },

  async uploadImage(id: number, image: File): Promise<{ message: string; path: string; url: string }> {
    const formData = new FormData();
    formData.append('image', image);
    
    const response = await api.post<{ message: string; path: string; url: string }>(
      `/admin/news-items/${id}/upload-image`,
      formData,
      {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      }
    );
    return response.data;
  },

  async uploadContentImage(image: File): Promise<{ success: boolean; url: string }> {
    const formData = new FormData();
    formData.append('image', image);
    
    const response = await api.post<{ success: boolean; url: string }>(
      '/admin/upload-content-image',
      formData,
      {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      }
    );
    return response.data;
  },
};