import axios from 'axios';
import { API_CONFIG } from '../config/api';

const API_URL = API_CONFIG.baseUrl;

export interface CompanyType {
  id: number;
  slug: string;
  name: string | { [key: string]: string };
  description?: string | { [key: string]: string };
  icon?: string;
  attributes?: CompanyAttribute[];
  created_at?: string;
  updated_at?: string;
}

export interface CompanyAttribute {
  id: number;
  key: string;
  label: string | { [key: string]: string };
  type: 'text' | 'number' | 'boolean' | 'select' | 'multiselect' | 'date' | 'json';
  required: boolean;
  options?: any;
  validation?: any;
  group?: string;
  order?: number;
}

export interface Company {
  id: number;
  type_id: number;
  company_type_id?: number; // Alias for type_id for backward compatibility
  type?: CompanyType;
  slug: string;
  name: string | { [key: string]: string };
  short_name?: string | { [key: string]: string };
  logo?: string;
  cover_image?: string;
  website?: string;
  email?: string;
  phone?: string;
  addresses?: any;
  about?: string | { [key: string]: string };
  attributes?: { [key: string]: any };
  entities?: CompanyEntity[];
  is_active: boolean;
  is_featured: boolean;
  display_order?: number;
  seo_title?: string | { [key: string]: string };
  seo_description?: string | { [key: string]: string };
  seo_keywords?: string | { [key: string]: string };
  created_at?: string;
  updated_at?: string;
}

export interface CompanyEntity {
  id: number;
  company_id: number;
  entity_type: string;
  name: string | { [key: string]: string };
  attributes?: { [key: string]: any };
  is_active: boolean;
  order?: number;
}

export interface EntityType {
  id: number;
  key: string;
  name: string | { [key: string]: string };
  attributes?: CompanyAttribute[];
}

const getAuthToken = () => {
  return localStorage.getItem('admin_token');
};

const apiClient = axios.create({
  baseURL: API_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

apiClient.interceptors.request.use((config) => {
  const token = getAuthToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export const companiesEavApi = {
  // Company Types
  getTypes: async () => {
    const response = await apiClient.get('/admin/companies-eav/types');
    return response.data;
  },

  // Attribute Definitions
  getAttributeDefinitions: async (companyTypeId: number) => {
    const response = await apiClient.get(`/admin/companies-eav/attribute-definitions/${companyTypeId}`);
    return response.data;
  },

  getType: async (id: number) => {
    const response = await apiClient.get(`/admin/company-types/${id}`);
    return response.data;
  },

  createType: async (data: Partial<CompanyType>) => {
    const response = await apiClient.post('/admin/company-types', data);
    return response.data;
  },

  updateType: async (id: number, data: Partial<CompanyType>) => {
    const response = await apiClient.put(`/admin/company-types/${id}`, data);
    return response.data;
  },

  deleteType: async (id: number) => {
    const response = await apiClient.delete(`/admin/company-types/${id}`);
    return response.data;
  },

  // Companies
  getCompanies: async (params?: {
    page?: number;
    per_page?: number;
    search?: string;
    type_id?: number;
    is_active?: boolean;
    is_featured?: boolean;
    sort_by?: string;
    sort_order?: 'asc' | 'desc';
  }) => {
    const response = await apiClient.get('/admin/companies-eav', { params });
    return response.data;
  },

  getCompany: async (id: number) => {
    const response = await apiClient.get(`/admin/companies-eav/${id}`);
    return response.data;
  },

  createCompany: async (data: FormData | Partial<Company>) => {
    const response = await apiClient.post('/admin/companies-eav', data, {
      headers: data instanceof FormData ? { 'Content-Type': 'multipart/form-data' } : {},
    });
    return response.data;
  },

  updateCompany: async (id: number, data: FormData | Partial<Company>) => {
    // Use POST for FormData with _method field, PUT for JSON
    const method = data instanceof FormData ? 'post' : 'put';
    const response = await apiClient[method](`/admin/companies-eav/${id}`, data, {
      headers: data instanceof FormData ? { 'Content-Type': 'multipart/form-data' } : {},
    });
    return response.data;
  },

  deleteCompany: async (id: number) => {
    const response = await apiClient.delete(`/admin/companies-eav/${id}`);
    return response.data;
  },

  toggleStatus: async (id: number) => {
    const response = await apiClient.post(`/admin/companies-eav/${id}/toggle-status`);
    return response.data;
  },

  toggleFeatured: async (id: number) => {
    const response = await apiClient.post(`/admin/companies-eav/${id}/toggle-featured`);
    return response.data;
  },

  // Attributes
  getAttributes: async (typeId?: number) => {
    const response = await apiClient.get('/admin/company-attributes', { 
      params: { type_id: typeId } 
    });
    return response.data;
  },

  getAttribute: async (id: number) => {
    const response = await apiClient.get(`/admin/company-attributes/${id}`);
    return response.data;
  },

  createAttribute: async (data: Partial<CompanyAttribute>) => {
    const response = await apiClient.post('/admin/company-attributes', data);
    return response.data;
  },

  updateAttribute: async (id: number, data: Partial<CompanyAttribute>) => {
    const response = await apiClient.put(`/admin/company-attributes/${id}`, data);
    return response.data;
  },

  deleteAttribute: async (id: number) => {
    const response = await apiClient.delete(`/admin/company-attributes/${id}`);
    return response.data;
  },

  // Entities
  getEntities: async (companyId: number, entityType?: string) => {
    const response = await apiClient.get(`/admin/companies-eav/${companyId}/entities`, {
      params: { entity_type: entityType }
    });
    return response.data;
  },

  getEntity: async (companyId: number, entityId: number) => {
    const response = await apiClient.get(`/admin/companies-eav/${companyId}/entities/${entityId}`);
    return response.data;
  },

  createEntity: async (companyId: number, data: Partial<CompanyEntity>) => {
    const response = await apiClient.post(`/admin/companies-eav/${companyId}/entities`, data);
    return response.data;
  },

  updateEntity: async (companyId: number, entityId: number, data: Partial<CompanyEntity>) => {
    const response = await apiClient.put(`/admin/companies-eav/${companyId}/entities/${entityId}`, data);
    return response.data;
  },

  deleteEntity: async (companyId: number, entityId: number) => {
    const response = await apiClient.delete(`/admin/companies-eav/${companyId}/entities/${entityId}`);
    return response.data;
  },

  // Entity Types
  getEntityTypes: async () => {
    const response = await apiClient.get('/admin/entity-types');
    return response.data;
  },

  getEntityType: async (id: number) => {
    const response = await apiClient.get(`/admin/entity-types/${id}`);
    return response.data;
  },

  createEntityType: async (data: Partial<EntityType>) => {
    const response = await apiClient.post('/admin/entity-types', data);
    return response.data;
  },

  updateEntityType: async (id: number, data: Partial<EntityType>) => {
    const response = await apiClient.put(`/admin/entity-types/${id}`, data);
    return response.data;
  },

  deleteEntityType: async (id: number) => {
    const response = await apiClient.delete(`/admin/entity-types/${id}`);
    return response.data;
  },

  // Entity Attributes
  getEntityAttributes: async (entityType: string) => {
    const response = await apiClient.get('/admin/entity-attributes', {
      params: { entity_type: entityType }
    });
    return response.data;
  },

  createEntityAttribute: async (data: Partial<CompanyAttribute> & { entity_type: string }) => {
    const response = await apiClient.post('/admin/entity-attributes', data);
    return response.data;
  },

  updateEntityAttribute: async (id: number, data: Partial<CompanyAttribute>) => {
    const response = await apiClient.put(`/admin/entity-attributes/${id}`, data);
    return response.data;
  },

  deleteEntityAttribute: async (id: number) => {
    const response = await apiClient.delete(`/admin/entity-attributes/${id}`);
    return response.data;
  },

  // Bulk operations
  bulkDelete: async (ids: number[]) => {
    const response = await apiClient.post('/admin/companies-eav/bulk-delete', { ids });
    return response.data;
  },

  bulkUpdateStatus: async (ids: number[], status: boolean) => {
    const response = await apiClient.post('/admin/companies-eav/bulk-status', { ids, status });
    return response.data;
  },

  // Import/Export
  exportCompanies: async (type_id?: number) => {
    const response = await apiClient.get('/admin/companies-eav/export', {
      params: { type_id },
      responseType: 'blob'
    });
    return response.data;
  },

  importCompanies: async (file: File) => {
    const formData = new FormData();
    formData.append('file', file);
    const response = await apiClient.post('/admin/companies-eav/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });
    return response.data;
  },
};