import axios from 'axios';
import { getAuthHeader } from './auth';
import { API_CONFIG } from '../config/api';

const API_URL = API_CONFIG.baseUrl;

export interface Ad {
  id: number;
  iframe?: string;
  image?: string;
  url?: string;
  place: 'home_slider' | 'sidebar' | 'banner' | 'popup';
  is_active: boolean;
  order: number;
  created_at: string;
  updated_at: string;
}

export interface AdFormData {
  place: string;
  iframe?: string;
  url?: string;
  is_active: boolean;
  order: number;
}

export interface PaginatedAds {
  data: Ad[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export const adsService = {
  async getAll(params?: {
    page?: number;
    per_page?: number;
    place?: string;
    is_active?: boolean;
  }): Promise<PaginatedAds> {
    try {
      const url = `${API_URL}/admin/test-ads`; // Temporary: using test endpoint
      console.log('Fetching ads from:', url);
      console.log('With headers:', getAuthHeader());
      console.log('With params:', params);
      
      const response = await axios.get(url, {
        headers: getAuthHeader(),
        params,
      });
      
      console.log('Raw ads response:', response.data);
      
      // Check if the response has an 'ads' field (non-paginated response)
      if (response.data.ads && Array.isArray(response.data.ads)) {
        // It's the non-paginated format from the API
        const wrapped = {
          data: response.data.ads,
          current_page: 1,
          last_page: 1,
          per_page: response.data.ads.length,
          total: response.data.ads.length
        };
        console.log('Returning wrapped ads array:', wrapped);
        return wrapped;
      }
      
      // The backend returns paginated data directly in response.data.data
      const paginatedData = response.data.data;
      
      // Check if it's actually paginated or just an array
      if (paginatedData && typeof paginatedData === 'object') {
        if ('data' in paginatedData && Array.isArray(paginatedData.data)) {
          // It's already properly paginated
          console.log('Returning paginated data:', paginatedData);
          return paginatedData;
        } else if (Array.isArray(paginatedData)) {
          // It's just an array, wrap it in pagination structure
          const wrapped = {
            data: paginatedData,
            current_page: 1,
            last_page: 1,
            per_page: paginatedData.length,
            total: paginatedData.length
          };
          console.log('Returning wrapped array:', wrapped);
          return wrapped;
        }
      }
      
      // Return empty pagination if no data
      console.log('Returning empty pagination');
      return {
        data: [],
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 0
      };
    } catch (error) {
      console.error('Error fetching ads:', error);
      throw error;
    }
  },

  async getById(id: number): Promise<Ad> {
    try {
      const response = await axios.get(`${API_URL}/admin/ads/${id}`, {
        headers: getAuthHeader(),
      });
      return response.data.data;
    } catch (error) {
      console.error('Error fetching ad:', error);
      throw error;
    }
  },

  async create(data: FormData): Promise<Ad> {
    try {
      const response = await axios.post(`${API_URL}/admin/ads`, data, {
        headers: {
          ...getAuthHeader(),
          'Content-Type': 'multipart/form-data',
        },
      });
      return response.data.data;
    } catch (error) {
      console.error('Error creating ad:', error);
      throw error;
    }
  },

  async update(id: number, data: FormData): Promise<Ad> {
    try {
      // Convert FormData to regular object for PUT request
      const obj: any = {};
      data.forEach((value, key) => {
        if (key !== 'image') {
          obj[key] = value;
        }
      });

      const response = await axios.put(`${API_URL}/admin/ads/${id}`, obj, {
        headers: getAuthHeader(),
      });

      // Upload image separately if present
      if (data.has('image')) {
        const imageFormData = new FormData();
        imageFormData.append('image', data.get('image') as Blob);
        
        await axios.post(`${API_URL}/admin/ads/${id}/upload-image`, imageFormData, {
          headers: {
            ...getAuthHeader(),
            'Content-Type': 'multipart/form-data',
          },
        });
      }

      return response.data.data;
    } catch (error) {
      console.error('Error updating ad:', error);
      throw error;
    }
  },

  async delete(id: number): Promise<void> {
    try {
      await axios.delete(`${API_URL}/admin/ads/${id}`, {
        headers: getAuthHeader(),
      });
    } catch (error) {
      console.error('Error deleting ad:', error);
      throw error;
    }
  },

  async toggleStatus(id: number): Promise<Ad> {
    try {
      const response = await axios.patch(`${API_URL}/admin/ads/${id}/toggle-status`, {}, {
        headers: getAuthHeader(),
      });
      return response.data.data;
    } catch (error) {
      console.error('Error toggling ad status:', error);
      throw error;
    }
  },

  async reorder(ads: { id: number; order: number }[]): Promise<void> {
    try {
      await axios.post(`${API_URL}/admin/ads/reorder`, { ads }, {
        headers: getAuthHeader(),
      });
    } catch (error) {
      console.error('Error reordering ads:', error);
      throw error;
    }
  },

  async uploadImage(id: number, image: File): Promise<{ image: string }> {
    try {
      const formData = new FormData();
      formData.append('image', image);

      const response = await axios.post(`${API_URL}/admin/ads/${id}/upload-image`, formData, {
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