import React, { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useForm, Controller } from 'react-hook-form';
import { useQuery, useMutation } from '@tanstack/react-query';
import { ArrowLeft, Upload, X, ChevronDown } from 'lucide-react';
import AsyncSelect from 'react-select/async';
// Type assertion for React 18 compatibility
const AsyncSelectComponent = AsyncSelect as any;
import { newsService } from '../../services/news';
import { categoriesService } from '../../services/categories';
import { companiesEavApi } from '../../services/companies-eav';
import { usersApi } from '../../services/users';
import type { NewsFormData } from '../../services/news';
import RichTextEditor from '../../components/RichTextEditor';
import DateTimeInput from '../../components/DateTimeInput';
import { API_CONFIG } from '../../config/api';

// Tag Input Component
const TagInput = ({ value = [], onChange }: { value: string[]; onChange: (tags: string[]) => void }) => {
  const [inputValue, setInputValue] = useState('');

  const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter' && inputValue.trim()) {
      e.preventDefault();
      const newTag = inputValue.trim();
      if (!value.includes(newTag)) {
        onChange([...value, newTag]);
      }
      setInputValue('');
    } else if (e.key === 'Backspace' && !inputValue && value.length > 0) {
      onChange(value.slice(0, -1));
    }
  };

  const removeTag = (indexToRemove: number) => {
    onChange(value.filter((_, index) => index !== indexToRemove));
  };

  return (
    <div className="w-full">
      <div className="flex flex-wrap gap-2 p-2 border border-gray-300 rounded-md focus-within:ring-2 focus-within:ring-blue-500 focus-within:border-blue-500">
        {value.map((tag, index) => (
          <span
            key={index}
            className="inline-flex items-center gap-1 px-3 py-1 text-sm bg-blue-100 text-blue-700 rounded-full"
          >
            {tag}
            <button
              type="button"
              onClick={() => removeTag(index)}
              className="hover:bg-blue-200 rounded-full p-0.5"
            >
              <X className="h-3 w-3" />
            </button>
          </span>
        ))}
        <input
          type="text"
          value={inputValue}
          onChange={(e) => setInputValue(e.target.value)}
          onKeyDown={handleKeyDown}
          placeholder={value.length === 0 ? "Type and press Enter" : ""}
          className="flex-1 min-w-[100px] outline-none py-1"
        />
      </div>
    </div>
  );
};

export default function NewsForm() {
  const navigate = useNavigate();
  const { id } = useParams();
  const isEdit = !!id;
  const [imageFile, setImageFile] = useState<File | null>(null);
  const [imagePreview, setImagePreview] = useState<string | null>(null);
  const [uploadingImage, setUploadingImage] = useState(false);
  const [showSEO, setShowSEO] = useState(false);
  const [selectedCompany, setSelectedCompany] = useState<{ value: number; label: string } | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors },
    reset,
    control,
  } = useForm<NewsFormData>({
    defaultValues: {
      language: 'az',
      status: true,
      publish_date: '',  // Will be set from server time
      hashtags: [],
      category_ids: [],
      news_type: 'other',
    },
  });

  // Fetch news data if editing
  const { data: newsData, isLoading: isLoadingNews } = useQuery({
    queryKey: ['news', id],
    queryFn: () => newsService.getById(Number(id)),
    enabled: isEdit,
  });

  // Fetch categories
  const { data: categories = [] } = useQuery({
    queryKey: ['categories'],
    queryFn: categoriesService.getAll,
  });

  // Fetch users for author dropdown
  const { data: users = [] } = useQuery({
    queryKey: ['users'],
    queryFn: usersApi.getAll,
  });

  // Fetch server time for default publish date
  useEffect(() => {
    if (!isEdit) {
      // Only fetch server time for new news, not when editing
      fetch(`${API_CONFIG.baseUrl}/admin/server-time`)
        .then(res => res.json())
        .then(data => {
          // Set the form field value with server time
          reset((formValues) => ({
            ...formValues,
            publish_date: data.datetime
          }));
        })
        .catch(err => {
          console.error('Failed to fetch server time:', err);
          // Fallback to browser time if server time fails
          const now = new Date();
          const fallbackTime = now.toISOString().slice(0, 16);
          reset((formValues) => ({
            ...formValues,
            publish_date: fallbackTime
          }));
        });
    }
  }, [isEdit, reset]);

  useEffect(() => {
    if (newsData && categories.length > 0) {
      reset({
        language: newsData.language,
        title: newsData.title,
        sub_title: newsData.sub_title || '',
        slug: newsData.slug || '',
        body: newsData.body,
        category_id: newsData.category_id,
        category_ids: newsData.category_ids || [],
        company_id: newsData.company_id || undefined,
        news_type: newsData.news_type || 'other',
        status: newsData.status,
        // The date from backend is in Azerbaijan time, just use it directly for the input
        publish_date: newsData.publish_date ? newsData.publish_date.slice(0, 16) : '',
        author: newsData.author || '',
        author_id: newsData.author_id || undefined,
        hashtags: (() => {
          if (Array.isArray(newsData.hashtags)) {
            // If it's already an array, check if it's a single string that needs splitting
            if (newsData.hashtags.length === 1 && typeof newsData.hashtags[0] === 'string') {
              // Split by spaces and filter out empty strings
              return newsData.hashtags[0].split(/\s+/).filter(tag => tag.trim() !== '');
            }
            return newsData.hashtags;
          }
          return [];
        })(),
        seo_title: newsData.seo_title || '',
        seo_keywords: newsData.seo_keywords || '',
        seo_description: newsData.seo_description || '',
      });
      
      // Set selected company if exists
      if (newsData.company_id && newsData.company) {
        setSelectedCompany({
          value: newsData.company_id,
          label: newsData.company.name
        });
      }
      
      if (newsData.thumbnail_image) {
        // Handle both full URLs and relative paths
        if (newsData.thumbnail_image.startsWith('http')) {
          setImagePreview(newsData.thumbnail_image);
        } else {
          setImagePreview(API_CONFIG.getImageUrl(`/storage/${newsData.thumbnail_image}`));
        }
      }
    }
  }, [newsData, categories, reset]);

  const createMutation = useMutation({
    mutationFn: newsService.create,
    onSuccess: async (response) => {
      if (imageFile) {
        await uploadImage(response.data.id);
      }
      navigate('/news');
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<NewsFormData> }) =>
      newsService.update(id, data),
    onSuccess: async () => {
      if (imageFile && id) {
        await uploadImage(Number(id));
      }
      navigate('/news');
    },
  });

  const uploadImage = async (newsId: number) => {
    if (!imageFile) return;
    
    setUploadingImage(true);
    try {
      await newsService.uploadImage(newsId, imageFile);
    } catch (error) {
      console.error('Image upload failed:', error);
    } finally {
      setUploadingImage(false);
    }
  };

  const onSubmit = (data: NewsFormData) => {
    // Set category_id from first selected category for backwards compatibility
    if (data.category_ids && data.category_ids.length > 0) {
      data.category_id = data.category_ids[0];
    }
    
    if (isEdit) {
      updateMutation.mutate({ id: Number(id), data });
    } else {
      createMutation.mutate(data);
    }
  };

  const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      setImageFile(file);
      const reader = new FileReader();
      reader.onloadend = () => {
        setImagePreview(reader.result as string);
      };
      reader.readAsDataURL(file);
    }
  };

  const removeImage = () => {
    setImageFile(null);
    setImagePreview(null);
  };

  const isSubmitting = createMutation.isPending || updateMutation.isPending || uploadingImage;

  // Load initial companies and search
  const loadCompanyOptions = async (inputValue: string) => {
    const response = await companiesEavApi.getCompanies({ search: inputValue });
    const companies = response.data?.data || [];
    return companies.map((company: any) => ({
      value: company.id,
      label: typeof company.name === 'string' ? company.name : (company.name?.en || company.name?.az || company.name?.ru || 'Unnamed')
    }));
  };


  if (isEdit && isLoadingNews) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="text-lg text-gray-600">Loading...</div>
      </div>
    );
  }

  return (
    <div>
      <div className="mb-6">
        <button
          onClick={() => navigate('/news')}
          className="inline-flex items-center text-gray-600 hover:text-gray-900"
        >
          <ArrowLeft className="h-4 w-4 mr-2" />
          Back to News
        </button>
      </div>

      <div className="bg-white rounded-lg shadow">
        <div className="p-6">
          <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-6 space-y-4 sm:space-y-0">
            <h1 className="text-xl sm:text-2xl font-bold text-gray-900">
              {isEdit ? 'Edit News' : 'Create News'}
            </h1>
            <div className="flex space-x-2 sm:space-x-4">
              <button
                type="button"
                onClick={() => navigate('/news')}
                className="px-3 sm:px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 text-sm sm:text-base"
              >
                Cancel
              </button>
              <button
                type="submit"
                form="news-form"
                disabled={isSubmitting}
                className="px-3 sm:px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 text-sm sm:text-base"
              >
                {isSubmitting ? 'Saving...' : isEdit ? 'Update' : 'Create'}
              </button>
            </div>
          </div>

          <form id="news-form" onSubmit={handleSubmit(onSubmit)} className="space-y-6">
            {/* Title - Most Important */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Title <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                {...register('title', { required: 'Title is required' })}
                className="w-full px-4 py-3 text-lg border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Enter news title..."
              />
              {errors.title && (
                <p className="mt-1 text-sm text-red-600">{errors.title.message}</p>
              )}
            </div>

            {/* Sub Title (Red Title) */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Sub Title (Optional)
              </label>
              <input
                type="text"
                {...register('sub_title')}
                className="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Enter additional subtitle (will be displayed in red)..."
              />
              <p className="mt-1 text-sm text-gray-500">
                This will be displayed after the main title with a dash (e.g., "Title - Sub Title")
              </p>
            </div>

            {/* News Categories (Multiple Selection) */}
            <div className="mb-6">
              <label className="block text-sm font-medium text-gray-700 mb-2">
                News Categories <span className="text-red-500">*</span>
              </label>
              <Controller
                name="category_ids"
                control={control}
                rules={{ 
                  validate: (value) => {
                    if (!value || value.length === 0) {
                      return 'At least one category is required';
                    }
                    return true;
                  }
                }}
                render={({ field }) => (
                  <div className="space-y-2">
                    <div className="border border-gray-300 rounded-md max-h-48 overflow-y-auto p-3">
                      {categories.map((category) => {
                        const title = typeof category.title === 'object' 
                          ? (category.title.az || category.title.en || category.title.ru || 'News Category ' + category.id)
                          : (category.title || 'Uncategorized');
                        const isChecked = field.value?.includes(category.id);
                        const isPrimary = field.value?.[0] === category.id;
                        
                        return (
                          <label 
                            key={category.id} 
                            className="flex items-center space-x-2 py-1 hover:bg-gray-50 cursor-pointer"
                          >
                            <input
                              type="checkbox"
                              checked={isChecked}
                              onChange={(e) => {
                                const currentValues = field.value || [];
                                if (e.target.checked) {
                                  field.onChange([...currentValues, category.id]);
                                } else {
                                  field.onChange(currentValues.filter((id: number) => id !== category.id));
                                }
                              }}
                              className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span className="flex-1">{title}</span>
                            {isPrimary && (
                              <span className="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded">Primary</span>
                            )}
                          </label>
                        );
                      })}
                    </div>
                    {field.value && field.value.length > 0 && (
                      <div className="text-sm text-gray-600">
                        Selected: {field.value.length} categor{field.value.length === 1 ? 'y' : 'ies'}
                        {field.value.length > 1 && ' (First selected is primary)'}
                      </div>
                    )}
                  </div>
                )}
              />
              {errors.category_ids && (
                <p className="mt-1 text-sm text-red-600">{errors.category_ids.message}</p>
              )}
              
              {/* Hidden field for backwards compatibility */}
            </div>

            {/* Status and Publish Date */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Publish Date
                </label>
                <Controller
                  name="publish_date"
                  control={control}
                  render={({ field }) => (
                    <DateTimeInput
                      value={field.value}
                      onChange={field.onChange}
                    />
                  )}
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Status
                </label>
                <Controller
                  name="status"
                  control={control}
                  render={({ field: { value, onChange } }) => (
                    <label className="relative inline-flex items-center cursor-pointer mt-2">
                      <input
                        type="checkbox"
                        className="sr-only peer"
                        checked={value}
                        onChange={(e) => onChange(e.target.checked)}
                      />
                      <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                      <span className="ms-3 text-sm font-medium text-gray-900">
                        {value ? 'Active' : 'Inactive'}
                      </span>
                    </label>
                  )}
                />
              </div>
            </div>

            {/* AI Generated Info - Only show if editing and is AI generated */}
            {isEdit && newsData?.is_ai_generated && (
              <div className="border-l-4 border-purple-500 bg-purple-50 p-4 rounded">
                <div className="flex items-center mb-2">
                  <span className="px-3 py-1 text-sm font-semibold rounded-full bg-purple-100 text-purple-800">
                    AI Generated Content
                  </span>
                </div>
                {newsData?.source_url && (
                  <div className="mt-3">
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Data Source URL
                    </label>
                    <a
                      href={newsData.source_url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-sm text-blue-600 hover:text-blue-800 underline break-all"
                    >
                      {newsData.source_url}
                    </a>
                  </div>
                )}
              </div>
            )}

            {/* Content - Full Width */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Content <span className="text-red-500">*</span>
              </label>
              <Controller
                name="body"
                control={control}
                rules={{ required: 'Content is required' }}
                render={({ field }) => (
                  <RichTextEditor
                    content={field.value || ''}
                    onChange={field.onChange}
                    placeholder="Write your news content here..."
                  />
                )}
              />
              {errors.body && (
                <p className="mt-1 text-sm text-red-600">{errors.body.message}</p>
              )}
            </div>

            {/* Featured Image - Full Width */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Featured Image
              </label>
              <label className="mt-1 flex justify-center px-4 pt-4 pb-4 border-2 border-gray-300 border-dashed rounded-md cursor-pointer hover:border-gray-400 transition-colors">
                {imagePreview ? (
                  <div className="relative w-full">
                    <img
                      src={imagePreview}
                      alt="Preview"
                      className="w-full h-48 md:h-64 object-cover rounded"
                      onError={(e) => {
                        e.currentTarget.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="300" height="200" viewBox="0 0 300 200"%3E%3Crect width="300" height="200" fill="%23f3f4f6"/%3E%3Cg fill="%239ca3af"%3E%3Cpath d="M150 80c-8.284 0-15 6.716-15 15s6.716 15 15 15 15-6.716 15-15-6.716-15-15-15zm0 20c-2.761 0-5-2.239-5-5s2.239-5 5-5 5 2.239 5 5-2.239 5-5 5z"/%3E%3Cpath d="M125 70h50c2.761 0 5 2.239 5 5v50c0 2.761-2.239 5-5 5h-50c-2.761 0-5-2.239-5-5V75c0-2.761 2.239-5 5-5zm45 50v-15l-7.5-7.5c-1.95-1.95-5.1-1.95-7.05 0L145 108l-5-5c-1.95-1.95-5.1-1.95-7.05 0L125 111v9h45z"/%3E%3C/g%3E%3C/svg%3E';
                      }}
                    />
                    <button
                      type="button"
                      onClick={(e) => {
                        e.preventDefault();
                        removeImage();
                      }}
                      className="absolute top-2 right-2 p-1 bg-red-500 text-white rounded-full hover:bg-red-600"
                    >
                      <X className="h-4 w-4" />
                    </button>
                  </div>
                ) : (
                  <div className="space-y-1 text-center">
                    <Upload className="mx-auto h-10 w-10 text-gray-400" />
                    <div className="text-sm text-gray-600">
                      <span className="font-medium text-blue-600">Click to upload</span>
                    </div>
                    <p className="text-xs text-gray-500">
                      PNG, JPG up to 10MB
                    </p>
                  </div>
                )}
                <input
                  type="file"
                  className="sr-only"
                  accept="image/*"
                  onChange={handleImageChange}
                />
              </label>
            </div>


            {/* News Type and Language */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  News Type
                </label>
                <select
                  {...register('news_type')}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="other">Other</option>
                  <option value="private">Private</option>
                  <option value="official">Official</option>
                  <option value="press">Press Release</option>
                  <option value="interview">Interview</option>
                  <option value="analysis">Analysis</option>
                  <option value="translation">Translation</option>
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Language
                </label>
                <select
                  {...register('language', { required: 'Language is required' })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="az">Azerbaijani</option>
                  <option value="en">English</option>
                  <option value="ru">Russian</option>
                </select>
              </div>
            </div>

            {/* Author and Hashtags */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Author
                </label>
                <select
                  {...register('author_id')}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="">Select author</option>
                  {users.map((user: any) => (
                    <option key={user.id} value={user.id}>
                      {user.name} ({user.role})
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Hashtags
                </label>
                <Controller
                  name="hashtags"
                  control={control}
                  render={({ field }) => (
                    <TagInput
                      value={field.value || []}
                      onChange={field.onChange}
                    />
                  )}
                />
              </div>
            </div>

            {/* Related Company (Optional) */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Related Company (Optional)
              </label>
              <Controller
                name="company_id"
                control={control}
                render={({ field }) => (
                  <AsyncSelectComponent
                    cacheOptions
                    defaultOptions
                    loadOptions={loadCompanyOptions}
                    value={selectedCompany}
                    onChange={(option: any) => {
                      setSelectedCompany(option);
                      field.onChange(option?.value || null);
                    }}
                    placeholder="Search for a company..."
                    isClearable
                    isSearchable
                    noOptionsMessage={() => "No companies found"}
                    styles={{
                      control: (base: any) => ({
                        ...base,
                        borderColor: '#d1d5db',
                        '&:hover': {
                          borderColor: '#9ca3af'
                        },
                        '&:focus-within': {
                          borderColor: '#3b82f6',
                          boxShadow: '0 0 0 3px rgba(59, 130, 246, 0.1)',
                        }
                      }),
                      menu: (base: any) => ({
                        ...base,
                        zIndex: 9999
                      })
                    }}
                    className="react-select-container"
                    classNamePrefix="react-select"
                  />
                )}
              />
              <p className="mt-1 text-sm text-gray-500">
                Search and select a company if this news is related to a specific bank or financial institution
              </p>
            </div>

            {/* SEO Fields - Collapsible */}
            <div className="border-t pt-6">
              <button
                type="button"
                onClick={() => setShowSEO(!showSEO)}
                className="flex items-center justify-between w-full text-left"
              >
                <h3 className="text-lg font-medium text-gray-900">SEO Settings (Optional)</h3>
                <ChevronDown className={`h-5 w-5 text-gray-500 transition-transform ${showSEO ? 'rotate-180' : ''}`} />
              </button>
              
              {showSEO && (
                <div className="space-y-4 mt-4">
                  {/* Slug - Only show on Edit */}
                  {isEdit && (
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Slug
                      </label>
                      <input
                        type="text"
                        {...register('slug')}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Leave empty to use auto-generated slug"
                      />
                      <p className="mt-1 text-sm text-gray-500">
                        URL-friendly version of the title (e.g., "my-news-title")
                      </p>
                      {errors.slug && (
                        <p className="mt-1 text-sm text-red-600">{errors.slug.message}</p>
                      )}
                    </div>
                  )}

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      SEO Title
                    </label>
                    <input
                      type="text"
                      {...register('seo_title')}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                      placeholder="Leave empty to use main title"
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      SEO Keywords
                    </label>
                    <input
                      type="text"
                      {...register('seo_keywords')}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                      placeholder="Comma separated keywords"
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      SEO Description
                    </label>
                    <textarea
                      {...register('seo_description')}
                      rows={3}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                      placeholder="Meta description for search engines"
                    />
                  </div>
                </div>
              )}
            </div>

            {/* Submit Buttons */}
            <div className="flex justify-end space-x-2 sm:space-x-4 pt-6 border-t">
              <button
                type="button"
                onClick={() => navigate('/news')}
                className="px-3 sm:px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 text-sm sm:text-base"
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={isSubmitting}
                className="px-3 sm:px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 text-sm sm:text-base"
              >
                {isSubmitting ? 'Saving...' : isEdit ? 'Update' : 'Create'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}