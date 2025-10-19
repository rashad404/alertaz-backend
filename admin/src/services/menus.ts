import axios from 'axios';
import { API_CONFIG } from '../config/api';

const API_URL = API_CONFIG.baseUrl;

export interface Menu {
  id: number;
  title: {
    az: string;
    en: string;
    ru: string;
  };
  slug: string;
  url?: string;
  parent_id?: number;
  parent?: Menu;
  children?: Menu[];
  position: number;
  target?: '_self' | '_blank';
  has_dropdown?: boolean;
  is_active: boolean;
  menu_location: 'header' | 'footer' | 'both';
  icon?: string;
  meta?: any;
  created_at?: string;
  updated_at?: string;
}

export interface MenuFormData {
  title: {
    az: string;
    en: string;
    ru: string;
  };
  slug: string;
  url?: string;
  parent_id?: number | null;
  position?: number;
  target?: '_self' | '_blank';
  has_dropdown?: boolean;
  is_active?: boolean;
  menu_location: 'header' | 'footer' | 'both';
  icon?: string;
  meta?: any;
}

class MenuService {
  private getAuthToken() {
    return localStorage.getItem('admin_token');
  }

  async getMenus(page = 1, search = '', location = '') {
    const token = this.getAuthToken();
    const params = new URLSearchParams();
    params.append('page', page.toString());
    if (search) params.append('search', search);
    if (location) params.append('location', location);

    // Use the admin API endpoint to get menus with proper data
    const response = await axios.get(`${API_URL}/admin/menus?${params.toString()}`, {
      headers: {
        Authorization: `Bearer ${token}`
      }
    });
    
    // Return the response data directly
    return response.data;
  }

  async getMenu(id: number) {
    const token = this.getAuthToken();
    const response = await axios.get(`${API_URL}/admin/menus/${id}`, {
      headers: {
        Authorization: `Bearer ${token}`
      }
    });
    return response.data;
  }

  async createMenu(data: MenuFormData) {
    const token = this.getAuthToken();
    const response = await axios.post(`${API_URL}/admin/menus`, data, {
      headers: {
        Authorization: `Bearer ${token}`,
        'Content-Type': 'application/json'
      }
    });
    return response.data;
  }

  async updateMenu(id: number, data: MenuFormData) {
    const token = this.getAuthToken();
    const response = await axios.put(`${API_URL}/admin/menus/${id}`, data, {
      headers: {
        Authorization: `Bearer ${token}`,
        'Content-Type': 'application/json'
      }
    });
    return response.data;
  }

  async deleteMenu(id: number) {
    const token = this.getAuthToken();
    const response = await axios.delete(`${API_URL}/admin/menus/${id}`, {
      headers: {
        Authorization: `Bearer ${token}`
      }
    });
    return response.data;
  }

  async reorderMenus(items: { id: number; position: number }[]) {
    const token = this.getAuthToken();
    const response = await axios.post(`${API_URL}/admin/menus/reorder`, { items }, {
      headers: {
        Authorization: `Bearer ${token}`,
        'Content-Type': 'application/json'
      }
    });
    return response.data;
  }

  async toggleMenuStatus(id: number) {
    const token = this.getAuthToken();
    const response = await axios.patch(`${API_URL}/admin/menus/${id}/toggle-status`, {}, {
      headers: {
        Authorization: `Bearer ${token}`
      }
    });
    return response.data;
  }
}

export default new MenuService();