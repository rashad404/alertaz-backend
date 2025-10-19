export type Category = {
  id: number;
  title: string | { az: string; en: string; ru: string };
  slug: string;
  created_at: string;
  updated_at: string;
};

export type News = {
  id: number;
  language: 'az' | 'en' | 'ru';
  title: string;
  sub_title?: string;
  slug: string;
  body: string;
  category_id: number;
  category?: Category;
  categories?: Array<Category & { is_primary: boolean }>;
  category_ids?: number[];
  company_id?: number;
  company?: any;
  news_type?: 'private' | 'official' | 'press' | 'interview' | 'analysis' | 'translation' | 'other';
  thumbnail_image: string | null;
  publish_date: string;
  is_scheduled?: boolean;
  status: boolean;
  show_in_slider: boolean;
  slider_order: number | null;
  author: string | null;
  author_id: number | null;
  hashtags: string[];
  views: number;
  seo_title: string | null;
  seo_keywords: string | null;
  seo_description: string | null;
  is_ai_generated: boolean;
  source_url: string | null;
  created_at: string;
  updated_at: string;
};

export type User = {
  id: number;
  name: string;
  email: string;
  password?: string;
  password_confirmation?: string;
  role: 'admin' | 'editor' | 'correspondent' | 'user';
  is_admin: boolean;
  email_verified_at?: string | null;
  created_at: string;
  updated_at: string;
};

export type PaginatedResponse<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
};