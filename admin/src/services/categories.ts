import api from './api';

export interface Category {
  id: number;
  title: string | { az: string; en: string; ru: string };
  slug: string;
  order: number;
  status: number;
}

export const categoriesService = {
  async getAll(): Promise<Category[]> {
    const response = await api.get<Category[]>('/admin/categories');
    return response.data;
  },
};