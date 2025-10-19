/**
 * Admin Panel Configuration
 */

// Default language for admin panel display
export const DEFAULT_LANG = 'en';

// Available languages
export const AVAILABLE_LANGS = ['az', 'en', 'ru'] as const;

// Type for language codes
export type LanguageCode = typeof AVAILABLE_LANGS[number];

// Language labels for UI
export const LANG_LABELS: Record<LanguageCode, string> = {
  az: 'Azərbaycan',
  en: 'English',
  ru: 'Русский'
};

// API configuration is now centralized in ./api.ts
export { API_CONFIG } from './api';

// Pagination defaults
export const PAGINATION = {
  defaultPageSize: 20,
  pageSizeOptions: [10, 20, 50, 100],
};