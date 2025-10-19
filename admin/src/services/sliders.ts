import api from './api';

export type SliderItem = {
  id: number;
  news_id: number;
  order: number;
  news?: {
    id: number;
    title: string;
    language: string;
    status: boolean;
    publish_date: string;
    thumbnail_image: string | null;
  };
  created_at: string;
  updated_at: string;
};

export type AvailableNews = {
  id: number;
  title: string;
  publish_date: string;
  thumbnail_image: string | null;
};

export const slidersService = {
  async getAll(): Promise<SliderItem[]> {
    const response = await api.get<SliderItem[]>('/admin/sliders');
    return response.data;
  },

  async getAvailableNews(language: string = 'az', search: string = ''): Promise<AvailableNews[]> {
    const response = await api.get<AvailableNews[]>('/admin/sliders/available-news', {
      params: { language, search }
    });
    return response.data;
  },

  async create(news_id: number, order?: number): Promise<{ message: string; data: SliderItem }> {
    const response = await api.post<{ message: string; data: SliderItem }>('/admin/sliders', {
      news_id,
      order
    });
    return response.data;
  },

  async update(id: number, order: number): Promise<{ message: string; data: SliderItem }> {
    const response = await api.put<{ message: string; data: SliderItem }>(`/admin/sliders/${id}`, {
      order
    });
    return response.data;
  },

  async delete(id: number): Promise<{ message: string }> {
    const response = await api.delete<{ message: string }>(`/admin/sliders/${id}`);
    return response.data;
  },

  async reorder(items: Array<{ id: number; order: number }>): Promise<{ message: string }> {
    const response = await api.post<{ message: string }>('/admin/sliders/reorder', {
      items
    });
    return response.data;
  },
};