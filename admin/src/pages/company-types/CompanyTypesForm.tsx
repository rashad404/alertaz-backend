import { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery } from '@tanstack/react-query';
import { ArrowLeft, Save, Upload, X } from 'lucide-react';
import { companyTypesService } from '../../services/companyTypes';
import type { CompanyTypeFormData } from '../../services/companyTypes';
import { API_CONFIG } from '../../config/api';

export default function CompanyTypesForm() {
  const navigate = useNavigate();
  const { id } = useParams();
  const isEditMode = Boolean(id);

  const [formData, setFormData] = useState<CompanyTypeFormData>({
    title: { az: '', en: '', ru: '' },
    slug: '',
    icon_alt_text: { az: '', en: '', ru: '' },
    seo_title: { az: '', en: '', ru: '' },
    seo_keywords: { az: '', en: '', ru: '' },
    seo_description: { az: '', en: '', ru: '' },
    order: 0,
    status: 1,
  });

  const [iconFile, setIconFile] = useState<File | null>(null);
  const [iconPreview, setIconPreview] = useState<string>('');
  const [activeTab, setActiveTab] = useState<'az' | 'en' | 'ru'>('az');

  // Fetch existing data if in edit mode
  const { data: existingType } = useQuery({
    queryKey: ['company-type', id],
    queryFn: () => companyTypesService.getById(Number(id)),
    enabled: isEditMode,
  });

  useEffect(() => {
    if (existingType) {
      setFormData({
        title: existingType.title || { az: '', en: '', ru: '' },
        slug: existingType.slug || '',
        icon_alt_text: existingType.icon_alt_text || { az: '', en: '', ru: '' },
        seo_title: existingType.seo_title || { az: '', en: '', ru: '' },
        seo_keywords: existingType.seo_keywords || { az: '', en: '', ru: '' },
        seo_description: existingType.seo_description || { az: '', en: '', ru: '' },
        order: existingType.order || 0,
        status: existingType.status,
      });
      
      if (existingType.icon) {
        setIconPreview(API_CONFIG.getImageUrl(existingType.icon));
      }
    }
  }, [existingType]);

  const createMutation = useMutation({
    mutationFn: (data: CompanyTypeFormData) => companyTypesService.create(data),
    onSuccess: () => {
      navigate('/company-types');
    },
  });

  const updateMutation = useMutation({
    mutationFn: (data: CompanyTypeFormData) => companyTypesService.update(Number(id), data),
    onSuccess: () => {
      navigate('/company-types');
    },
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    
    const dataToSubmit = {
      ...formData,
      icon: iconFile || formData.icon,
    };

    if (isEditMode) {
      updateMutation.mutate(dataToSubmit);
    } else {
      createMutation.mutate(dataToSubmit);
    }
  };

  const handleIconChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      setIconFile(file);
      const reader = new FileReader();
      reader.onloadend = () => {
        setIconPreview(reader.result as string);
      };
      reader.readAsDataURL(file);
    }
  };

  const removeIcon = () => {
    setIconFile(null);
    setIconPreview('');
  };

  const generateSlug = (text: string) => {
    return text
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
  };

  const handleTitleChange = (lang: 'az' | 'en' | 'ru', value: string) => {
    setFormData(prev => ({
      ...prev,
      title: { ...prev.title, [lang]: value }
    }));
    
    // Auto-generate slug from English title
    if (lang === 'en' && !formData.slug) {
      setFormData(prev => ({ ...prev, slug: generateSlug(value) }));
    }
  };

  return (
    <div className="px-4 sm:px-6 lg:px-8">
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-4">
          <button
            onClick={() => navigate('/company-types')}
            className="p-2 hover:bg-gray-100 rounded-md transition-colors"
          >
            <ArrowLeft className="h-5 w-5" />
          </button>
          <h1 className="text-2xl font-bold text-gray-900">
            {isEditMode ? 'Edit Company Type' : 'Create Company Type'}
          </h1>
        </div>
      </div>

      {/* Form */}
      <form onSubmit={handleSubmit} className="bg-white rounded-lg shadow">
        <div className="p-6 space-y-6">
          {/* Language Tabs */}
          <div className="border-b border-gray-200">
            <nav className="-mb-px flex space-x-8">
              {(['az', 'en', 'ru'] as const).map((lang) => (
                <button
                  key={lang}
                  type="button"
                  onClick={() => setActiveTab(lang)}
                  className={`py-2 px-1 border-b-2 font-medium text-sm ${
                    activeTab === lang
                      ? 'border-blue-500 text-blue-600'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                  }`}
                >
                  {lang === 'az' ? 'Azərbaycan' : lang === 'en' ? 'English' : 'Русский'}
                </button>
              ))}
            </nav>
          </div>

          {/* Title */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Title ({activeTab.toUpperCase()}) *
            </label>
            <input
              type="text"
              value={formData.title[activeTab]}
              onChange={(e) => handleTitleChange(activeTab, e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              required={activeTab === 'en'}
            />
          </div>

          {/* Slug */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Slug *
            </label>
            <input
              type="text"
              value={formData.slug}
              onChange={(e) => setFormData({ ...formData, slug: e.target.value })}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              required
              placeholder="e.g., banks, credit-organizations"
            />
          </div>

          {/* Icon Upload */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Icon
            </label>
            <div className="flex items-center gap-4">
              {iconPreview ? (
                <div className="relative">
                  <img
                    src={iconPreview}
                    alt="Icon preview"
                    className="w-20 h-20 object-contain border rounded"
                  />
                  <button
                    type="button"
                    onClick={removeIcon}
                    className="absolute -top-2 -right-2 p-1 bg-red-500 text-white rounded-full hover:bg-red-600"
                  >
                    <X className="h-3 w-3" />
                  </button>
                </div>
              ) : (
                <div className="w-20 h-20 border-2 border-dashed border-gray-300 rounded flex items-center justify-center">
                  <Upload className="h-8 w-8 text-gray-400" />
                </div>
              )}
              <input
                type="file"
                onChange={handleIconChange}
                accept="image/*"
                className="hidden"
                id="icon-upload"
              />
              <label
                htmlFor="icon-upload"
                className="px-4 py-2 bg-blue-50 text-blue-600 rounded-md hover:bg-blue-100 cursor-pointer"
              >
                Choose Icon
              </label>
            </div>
          </div>

          {/* Icon Alt Text */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Icon Alt Text ({activeTab.toUpperCase()})
            </label>
            <input
              type="text"
              value={formData.icon_alt_text?.[activeTab] || ''}
              onChange={(e) => setFormData({
                ...formData,
                icon_alt_text: {
                  az: formData.icon_alt_text?.az || '',
                  en: formData.icon_alt_text?.en || '',
                  ru: formData.icon_alt_text?.ru || '',
                  [activeTab]: e.target.value
                } as { az: string; en: string; ru: string }
              })}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          {/* SEO Title */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              SEO Title ({activeTab.toUpperCase()})
            </label>
            <input
              type="text"
              value={formData.seo_title?.[activeTab] || ''}
              onChange={(e) => setFormData({
                ...formData,
                seo_title: {
                  az: formData.seo_title?.az || '',
                  en: formData.seo_title?.en || '',
                  ru: formData.seo_title?.ru || '',
                  [activeTab]: e.target.value
                } as { az: string; en: string; ru: string }
              })}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          {/* SEO Keywords */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              SEO Keywords ({activeTab.toUpperCase()})
            </label>
            <textarea
              value={formData.seo_keywords?.[activeTab] || ''}
              onChange={(e) => setFormData({
                ...formData,
                seo_keywords: {
                  az: formData.seo_keywords?.az || '',
                  en: formData.seo_keywords?.en || '',
                  ru: formData.seo_keywords?.ru || '',
                  [activeTab]: e.target.value
                } as { az: string; en: string; ru: string }
              })}
              rows={3}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Separate keywords with commas"
            />
          </div>

          {/* SEO Description */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              SEO Description ({activeTab.toUpperCase()})
            </label>
            <textarea
              value={formData.seo_description?.[activeTab] || ''}
              onChange={(e) => setFormData({
                ...formData,
                seo_description: {
                  az: formData.seo_description?.az || '',
                  en: formData.seo_description?.en || '',
                  ru: formData.seo_description?.ru || '',
                  [activeTab]: e.target.value
                } as { az: string; en: string; ru: string }
              })}
              rows={4}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          {/* Order */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Display Order
            </label>
            <input
              type="number"
              value={formData.order}
              onChange={(e) => setFormData({ ...formData, order: parseInt(e.target.value) || 0 })}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              min="0"
            />
          </div>

          {/* Status */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Status
            </label>
            <select
              value={formData.status}
              onChange={(e) => setFormData({ ...formData, status: parseInt(e.target.value) })}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value={1}>Active</option>
              <option value={0}>Inactive</option>
            </select>
          </div>
        </div>

        {/* Form Actions */}
        <div className="px-6 py-4 bg-gray-50 flex justify-end gap-3 rounded-b-lg">
          <button
            type="button"
            onClick={() => navigate('/company-types')}
            className="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={createMutation.isPending || updateMutation.isPending}
            className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center gap-2 disabled:opacity-50"
          >
            <Save className="h-4 w-4" />
            {createMutation.isPending || updateMutation.isPending ? 'Saving...' : 'Save'}
          </button>
        </div>
      </form>
    </div>
  );
}