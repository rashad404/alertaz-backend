import api from './api';

export interface CompanyType {
  id: number;
  title: { az: string; en: string; ru: string };
  slug: string;
  icon?: string;
  icon_alt_text?: { az: string; en: string; ru: string };
  seo_title?: { az: string; en: string; ru: string };
  seo_keywords?: { az: string; en: string; ru: string };
  seo_description?: { az: string; en: string; ru: string };
  order: number;
  status: number;
  created_at?: string;
  updated_at?: string;
}

export interface CompanyTypeFormData {
  title: { az: string; en: string; ru: string };
  slug: string;
  icon?: File | string;
  icon_alt_text?: { az: string; en: string; ru: string };
  seo_title?: { az: string; en: string; ru: string };
  seo_keywords?: { az: string; en: string; ru: string };
  seo_description?: { az: string; en: string; ru: string };
  order?: number;
  status: number;
}

export const companyTypesService = {
  async getAll(): Promise<CompanyType[]> {
    const response = await api.get('/admin/company-types');
    // Handle both wrapped and unwrapped responses
    const data = response.data;
    return Array.isArray(data) ? data : (data.data || []);
  },

  async getById(id: number): Promise<CompanyType> {
    const response = await api.get<CompanyType>(`/admin/company-types/${id}`);
    return response.data;
  },

  async create(data: CompanyTypeFormData): Promise<CompanyType> {
    const formData = new FormData();
    
    // Handle translatable fields
    formData.append('title[az]', data.title.az);
    formData.append('title[en]', data.title.en);
    formData.append('title[ru]', data.title.ru);
    
    formData.append('slug', data.slug);
    formData.append('status', data.status.toString());
    formData.append('order', (data.order || 0).toString());
    
    // Handle icon upload
    if (data.icon && data.icon instanceof File) {
      formData.append('icon', data.icon);
    }
    
    // Handle optional translatable fields
    if (data.icon_alt_text) {
      formData.append('icon_alt_text[az]', data.icon_alt_text.az || '');
      formData.append('icon_alt_text[en]', data.icon_alt_text.en || '');
      formData.append('icon_alt_text[ru]', data.icon_alt_text.ru || '');
    }
    
    if (data.seo_title) {
      formData.append('seo_title[az]', data.seo_title.az || '');
      formData.append('seo_title[en]', data.seo_title.en || '');
      formData.append('seo_title[ru]', data.seo_title.ru || '');
    }
    
    if (data.seo_keywords) {
      formData.append('seo_keywords[az]', data.seo_keywords.az || '');
      formData.append('seo_keywords[en]', data.seo_keywords.en || '');
      formData.append('seo_keywords[ru]', data.seo_keywords.ru || '');
    }
    
    if (data.seo_description) {
      formData.append('seo_description[az]', data.seo_description.az || '');
      formData.append('seo_description[en]', data.seo_description.en || '');
      formData.append('seo_description[ru]', data.seo_description.ru || '');
    }
    
    const response = await api.post<CompanyType>('/admin/company-types', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data;
  },

  async update(id: number, data: CompanyTypeFormData): Promise<CompanyType> {
    const formData = new FormData();
    
    // Laravel requires _method for PUT requests with FormData
    formData.append('_method', 'PUT');
    
    // Handle translatable fields
    formData.append('title[az]', data.title.az);
    formData.append('title[en]', data.title.en);
    formData.append('title[ru]', data.title.ru);
    
    formData.append('slug', data.slug);
    formData.append('status', data.status.toString());
    formData.append('order', (data.order || 0).toString());
    
    // Handle icon upload
    if (data.icon && data.icon instanceof File) {
      formData.append('icon', data.icon);
    }
    
    // Handle optional translatable fields
    if (data.icon_alt_text) {
      formData.append('icon_alt_text[az]', data.icon_alt_text.az || '');
      formData.append('icon_alt_text[en]', data.icon_alt_text.en || '');
      formData.append('icon_alt_text[ru]', data.icon_alt_text.ru || '');
    }
    
    if (data.seo_title) {
      formData.append('seo_title[az]', data.seo_title.az || '');
      formData.append('seo_title[en]', data.seo_title.en || '');
      formData.append('seo_title[ru]', data.seo_title.ru || '');
    }
    
    if (data.seo_keywords) {
      formData.append('seo_keywords[az]', data.seo_keywords.az || '');
      formData.append('seo_keywords[en]', data.seo_keywords.en || '');
      formData.append('seo_keywords[ru]', data.seo_keywords.ru || '');
    }
    
    if (data.seo_description) {
      formData.append('seo_description[az]', data.seo_description.az || '');
      formData.append('seo_description[en]', data.seo_description.en || '');
      formData.append('seo_description[ru]', data.seo_description.ru || '');
    }
    
    const response = await api.post<CompanyType>(`/admin/company-types/${id}`, formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data;
  },

  async delete(id: number): Promise<void> {
    await api.delete(`/admin/company-types/${id}`);
  },
};