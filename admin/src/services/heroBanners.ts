import axios from 'axios';
import { getAuthHeader } from './auth';
import { API_CONFIG } from '../config/api';

const API_URL = API_CONFIG.baseUrl;

export interface HeroBanner {
  id: number;
  title: {
    az: string;
    en?: string;
    ru?: string;
  };
  description?: {
    az: string;
    en?: string;
    ru?: string;
  };
  image?: string;
  link?: string;
  link_text?: {
    az: string;
    en?: string;
    ru?: string;
  };
  order: number;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface HeroBannerFormData {
  title: {
    az: string;
    en?: string;
    ru?: string;
  };
  description?: {
    az: string;
    en?: string;
    ru?: string;
  };
  link?: string;
  link_text?: {
    az: string;
    en?: string;
    ru?: string;
  };
  order: number;
  is_active: boolean;
}

export interface PaginatedHeroBanners {
  data: HeroBanner[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export const heroBannersService = {
  async getAll(params?: {
    page?: number;
    per_page?: number;
    is_active?: boolean;
  }): Promise<PaginatedHeroBanners> {
    try {
      const response = await axios.get(`${API_URL}/admin/hero-banners`, {
        headers: getAuthHeader(),
        params,
      });
      return response.data.data;
    } catch (error) {
      console.error('Error fetching hero banners:', error);
      throw error;
    }
  },

  async getById(id: number): Promise<HeroBanner> {
    try {
      const response = await axios.get(`${API_URL}/admin/hero-banners/${id}`, {
        headers: getAuthHeader(),
      });
      return response.data.data;
    } catch (error) {
      console.error('Error fetching hero banner:', error);
      throw error;
    }
  },

  async create(data: FormData): Promise<HeroBanner> {
    try {
      const response = await axios.post(`${API_URL}/admin/hero-banners`, data, {
        headers: {
          ...getAuthHeader(),
          'Content-Type': 'multipart/form-data',
        },
      });
      return response.data.data;
    } catch (error) {
      console.error('Error creating hero banner:', error);
      throw error;
    }
  },

  async update(id: number, data: FormData): Promise<HeroBanner> {
    try {
      // Convert FormData to regular object for PUT request
      const obj: any = {};
      data.forEach((value, key) => {
        if (key !== 'image') {
          if (key.includes('[') && key.includes(']')) {
            // Handle nested objects like title[az], title[en]
            const matches = key.match(/(\w+)\[(\w+)\]/);
            if (matches) {
              const [, field, lang] = matches;
              if (!obj[field]) obj[field] = {};
              obj[field][lang] = value;
            }
          } else {
            obj[key] = value;
          }
        }
      });

      const response = await axios.put(`${API_URL}/admin/hero-banners/${id}`, obj, {
        headers: getAuthHeader(),
      });

      // Upload image separately if present
      if (data.has('image')) {
        const imageFormData = new FormData();
        imageFormData.append('image', data.get('image') as Blob);
        
        await axios.post(`${API_URL}/admin/hero-banners/${id}/upload-image`, imageFormData, {
          headers: {
            ...getAuthHeader(),
            'Content-Type': 'multipart/form-data',
          },
        });
      }

      return response.data.data;
    } catch (error) {
      console.error('Error updating hero banner:', error);
      throw error;
    }
  },

  async delete(id: number): Promise<void> {
    try {
      await axios.delete(`${API_URL}/admin/hero-banners/${id}`, {
        headers: getAuthHeader(),
      });
    } catch (error) {
      console.error('Error deleting hero banner:', error);
      throw error;
    }
  },

  async toggleStatus(id: number): Promise<HeroBanner> {
    try {
      const response = await axios.patch(`${API_URL}/admin/hero-banners/${id}/toggle-status`, {}, {
        headers: getAuthHeader(),
      });
      return response.data.data;
    } catch (error) {
      console.error('Error toggling hero banner status:', error);
      throw error;
    }
  },

  async reorder(banners: { id: number; order: number }[]): Promise<void> {
    try {
      await axios.post(`${API_URL}/admin/hero-banners/reorder`, { banners }, {
        headers: getAuthHeader(),
      });
    } catch (error) {
      console.error('Error reordering hero banners:', error);
      throw error;
    }
  },

  async uploadImage(id: number, image: File): Promise<{ image: string }> {
    try {
      const formData = new FormData();
      formData.append('image', image);

      const response = await axios.post(`${API_URL}/admin/hero-banners/${id}/upload-image`, formData, {
        headers: {
          ...getAuthHeader(),
          'Content-Type': 'multipart/form-data',
        },
      });
      return response.data.data;
    } catch (error) {
      console.error('Error uploading image:', error);
      throw error;
    }
  },
};