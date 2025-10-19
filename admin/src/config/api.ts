// Centralized API configuration
const API_URL = import.meta.env.VITE_API_URL;

if (!API_URL) {
  throw new Error('VITE_API_URL environment variable is not set. Please check your .env file.');
}

export const API_CONFIG = {
  baseUrl: API_URL,

  // Get storage URL for files
  getStorageUrl: (path: string) => {
    if (!path) return '';
    if (path.startsWith('http')) return path;
    // Remove /api from the end of URL, not from domain (e.g., api.kredit.az)
    const baseUrl = API_URL.replace(/\/api$/, '');
    return `${baseUrl}${path.startsWith('/') ? '' : '/'}${path}`;
  },

  // Get image URL (handles both relative and absolute paths)
  getImageUrl: (path: string) => {
    if (!path) return '';
    if (path.startsWith('http')) return path;
    // Remove /api from the end of URL, not from domain (e.g., api.kredit.az)
    const baseUrl = API_URL.replace(/\/api$/, '');
    return `${baseUrl}${path.startsWith('/') ? '' : '/'}${path}`;
  }
};
