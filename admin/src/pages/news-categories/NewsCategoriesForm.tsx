import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate, useParams } from 'react-router-dom';
import { useQuery, useMutation } from '@tanstack/react-query';
import { ArrowLeft } from 'lucide-react';
import { API_CONFIG } from '../../config/api';

interface CategoryFormData {
  title_az: string;
  title_en: string;
  title_ru: string;
  slug: string;
  order: number;
  status: number;
  seo_title_az?: string;
  seo_title_en?: string;
  seo_title_ru?: string;
  seo_keywords_az?: string;
  seo_keywords_en?: string;
  seo_keywords_ru?: string;
  seo_description_az?: string;
  seo_description_en?: string;
  seo_description_ru?: string;
}

export default function NewsCategoriesForm() {
  const navigate = useNavigate();
  const { id } = useParams();
  const isEdit = !!id;

  const { register, handleSubmit, reset, formState: { errors } } = useForm<CategoryFormData>({
    defaultValues: {
      order: 0,
      status: 1,
    }
  });

  // Fetch category data if editing
  const { data: category, isLoading } = useQuery({
    queryKey: ['category', id],
    queryFn: async () => {
      if (!id) return null;
      const response = await fetch(`${API_CONFIG.baseUrl}/admin/categories/${id}`, {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('admin_token')}`,
        },
      });
      if (!response.ok) throw new Error('Failed to fetch category');
      return response.json();
    },
    enabled: isEdit,
  });

  // Update form when category data is loaded
  useEffect(() => {
    if (category) {
      console.log('Category data loaded:', category);
      const formData: CategoryFormData = {
        title_az: typeof category.title === 'object' ? category.title.az : category.title,
        title_en: typeof category.title === 'object' ? category.title.en : '',
        title_ru: typeof category.title === 'object' ? category.title.ru : '',
        slug: category.slug || '',
        order: category.order || 0,
        status: category.status || 1,
        seo_title_az: category.seo_title && typeof category.seo_title === 'object' ? category.seo_title.az : '',
        seo_title_en: category.seo_title && typeof category.seo_title === 'object' ? category.seo_title.en : '',
        seo_title_ru: category.seo_title && typeof category.seo_title === 'object' ? category.seo_title.ru : '',
        seo_keywords_az: category.seo_keywords && typeof category.seo_keywords === 'object' ? category.seo_keywords.az : '',
        seo_keywords_en: category.seo_keywords && typeof category.seo_keywords === 'object' ? category.seo_keywords.en : '',
        seo_keywords_ru: category.seo_keywords && typeof category.seo_keywords === 'object' ? category.seo_keywords.ru : '',
        seo_description_az: category.seo_description && typeof category.seo_description === 'object' ? category.seo_description.az : '',
        seo_description_en: category.seo_description && typeof category.seo_description === 'object' ? category.seo_description.en : '',
        seo_description_ru: category.seo_description && typeof category.seo_description === 'object' ? category.seo_description.ru : '',
      };
      console.log('Resetting form with data:', formData);
      reset(formData);
    }
  }, [category, reset]);

  const saveMutation = useMutation({
    mutationFn: async (data: CategoryFormData) => {
      const payload = {
        title: {
          az: data.title_az,
          en: data.title_en,
          ru: data.title_ru,
        },
        slug: data.slug,
        order: data.order,
        status: data.status,
        seo_title: {
          az: data.seo_title_az || '',
          en: data.seo_title_en || '',
          ru: data.seo_title_ru || '',
        },
        seo_keywords: {
          az: data.seo_keywords_az || '',
          en: data.seo_keywords_en || '',
          ru: data.seo_keywords_ru || '',
        },
        seo_description: {
          az: data.seo_description_az || '',
          en: data.seo_description_en || '',
          ru: data.seo_description_ru || '',
        },
      };

      const url = isEdit
        ? `${API_CONFIG.baseUrl}/admin/categories/${id}`
        : `${API_CONFIG.baseUrl}/admin/categories`;

      const response = await fetch(url, {
        method: isEdit ? 'PUT' : 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('admin_token')}`,
        },
        body: JSON.stringify(payload),
      });

      if (!response.ok) throw new Error('Failed to save category');
      return response.json();
    },
    onSuccess: () => {
      navigate('/news-categories');
    },
  });

  const onSubmit = (data: CategoryFormData) => {
    saveMutation.mutate(data);
  };

  if (isEdit && isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  return (
    <div>
      <div className="flex items-center mb-6">
        <button
          onClick={() => navigate('/news-categories')}
          className="mr-4 p-2 hover:bg-gray-100 rounded"
        >
          <ArrowLeft className="h-5 w-5" />
        </button>
        <h1 className="text-2xl font-bold text-gray-900">
          {isEdit ? 'Edit News Category' : 'Create News Category'}
        </h1>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
        {/* Basic Information */}
        <div className="bg-white rounded-lg shadow p-6">
          <h2 className="text-lg font-medium text-gray-900 mb-4">Basic Information</h2>
          
          {/* Title Fields */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Title (Azerbaijani) <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                {...register('title_az', { required: 'Azerbaijani title is required' })}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
              {errors.title_az && (
                <p className="mt-1 text-sm text-red-600">{errors.title_az.message}</p>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Title (English)
              </label>
              <input
                type="text"
                {...register('title_en')}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Title (Russian)
              </label>
              <input
                type="text"
                {...register('title_ru')}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
          </div>

          {/* Slug and Order */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Slug
              </label>
              <input
                type="text"
                {...register('slug')}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Order
              </label>
              <input
                type="number"
                {...register('order', { valueAsNumber: true })}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Status
              </label>
              <select
                {...register('status', { valueAsNumber: true })}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value={1}>Active</option>
                <option value={0}>Inactive</option>
              </select>
            </div>
          </div>
        </div>

        {/* SEO Information */}
        <div className="bg-white rounded-lg shadow p-6">
          <h2 className="text-lg font-medium text-gray-900 mb-4">SEO Information</h2>
          
          {/* SEO Title */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                SEO Title (Azerbaijani)
              </label>
              <input
                type="text"
                {...register('seo_title_az')}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                SEO Title (English)
              </label>
              <input
                type="text"
                {...register('seo_title_en')}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                SEO Title (Russian)
              </label>
              <input
                type="text"
                {...register('seo_title_ru')}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
          </div>

          {/* SEO Keywords */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                SEO Keywords (Azerbaijani)
              </label>
              <input
                type="text"
                {...register('seo_keywords_az')}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                SEO Keywords (English)
              </label>
              <input
                type="text"
                {...register('seo_keywords_en')}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                SEO Keywords (Russian)
              </label>
              <input
                type="text"
                {...register('seo_keywords_ru')}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
          </div>

          {/* SEO Description */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                SEO Description (Azerbaijani)
              </label>
              <textarea
                {...register('seo_description_az')}
                rows={3}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                SEO Description (English)
              </label>
              <textarea
                {...register('seo_description_en')}
                rows={3}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                SEO Description (Russian)
              </label>
              <textarea
                {...register('seo_description_ru')}
                rows={3}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
          </div>
        </div>

        {/* Submit Buttons */}
        <div className="flex justify-end space-x-4">
          <button
            type="button"
            onClick={() => navigate('/news-categories')}
            className="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={saveMutation.isPending}
            className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
          >
            {saveMutation.isPending ? 'Saving...' : (isEdit ? 'Update' : 'Create')}
          </button>
        </div>
      </form>
    </div>
  );
}