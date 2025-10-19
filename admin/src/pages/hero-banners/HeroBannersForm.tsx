import React, { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { heroBannersService } from '../../services/heroBanners';
import { ArrowLeft, Upload, X } from 'lucide-react';
import { API_CONFIG } from '../../config/api';

const HeroBannersForm: React.FC = () => {
  const navigate = useNavigate();
  const { id } = useParams();
  const isEdit = !!id;

  const [formData, setFormData] = useState<{
    title: { az: string; en: string; ru: string };
    description: { az: string; en: string; ru: string };
    link: string;
    link_text: { az: string; en: string; ru: string };
    is_active: boolean;
    order: number;
  }>({
    title: { az: '', en: '', ru: '' },
    description: { az: '', en: '', ru: '' },
    link: '',
    link_text: { az: '', en: '', ru: '' },
    is_active: true,
    order: 0,
  });

  const [imageFile, setImageFile] = useState<File | null>(null);
  const [imagePreview, setImagePreview] = useState<string>('');
  const [existingImage, setExistingImage] = useState<string>('');
  const [loading, setLoading] = useState(false);
  const [activeTab, setActiveTab] = useState<'az' | 'en' | 'ru'>('az');

  const languages = [
    { code: 'az', label: 'Azərbaycan' },
    { code: 'en', label: 'English' },
    { code: 'ru', label: 'Русский' },
  ];

  useEffect(() => {
    if (isEdit) {
      fetchBanner();
    }
  }, [id]);

  const fetchBanner = async () => {
    if (!id) return;
    
    setLoading(true);
    try {
      const banner = await heroBannersService.getById(parseInt(id));
      setFormData({
        title: {
          az: banner.title?.az || '',
          en: banner.title?.en || '',
          ru: banner.title?.ru || ''
        },
        description: {
          az: banner.description?.az || '',
          en: banner.description?.en || '',
          ru: banner.description?.ru || ''
        },
        link: banner.link || '',
        link_text: {
          az: banner.link_text?.az || '',
          en: banner.link_text?.en || '',
          ru: banner.link_text?.ru || ''
        },
        is_active: banner.is_active,
        order: banner.order,
      });
      
      if (banner.image) {
        setExistingImage(banner.image);
      }
    } catch (error) {
      console.error('Error fetching hero banner:', error);
      alert('Failed to fetch hero banner');
      navigate('/hero-banners');
    } finally {
      setLoading(false);
    }
  };

  const handleInputChange = (field: string, value: any, lang?: 'az' | 'en' | 'ru') => {
    if (lang) {
      setFormData(prev => ({
        ...prev,
        [field]: {
          ...(prev[field as keyof typeof prev] as any),
          [lang]: value
        }
      }));
    } else {
      setFormData(prev => ({ ...prev, [field]: value }));
    }
  };

  const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      if (file.size > 5 * 1024 * 1024) {
        alert('Image size must be less than 5MB');
        return;
      }

      setImageFile(file);
      const reader = new FileReader();
      reader.onloadend = () => {
        setImagePreview(reader.result as string);
      };
      reader.readAsDataURL(file);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!formData.title.az.trim()) {
      alert('Title in Azerbaijani is required');
      return;
    }

    setLoading(true);

    try {
      const submitData = new FormData();
      
      // Add translatable fields
      submitData.append('title[az]', formData.title.az);
      if (formData.title.en) submitData.append('title[en]', formData.title.en);
      if (formData.title.ru) submitData.append('title[ru]', formData.title.ru);
      
      if (formData.description.az) submitData.append('description[az]', formData.description.az);
      if (formData.description.en) submitData.append('description[en]', formData.description.en);
      if (formData.description.ru) submitData.append('description[ru]', formData.description.ru);
      
      if (formData.link_text.az) submitData.append('link_text[az]', formData.link_text.az);
      if (formData.link_text.en) submitData.append('link_text[en]', formData.link_text.en);
      if (formData.link_text.ru) submitData.append('link_text[ru]', formData.link_text.ru);
      
      // Add regular fields
      if (formData.link) submitData.append('link', formData.link);
      submitData.append('order', formData.order.toString());
      submitData.append('is_active', formData.is_active ? '1' : '0');
      
      // Add image if present
      if (imageFile) {
        submitData.append('image', imageFile);
      }

      if (isEdit) {
        await heroBannersService.update(parseInt(id!), submitData);
        alert('Hero banner updated successfully');
      } else {
        await heroBannersService.create(submitData);
        alert('Hero banner created successfully');
      }

      navigate('/hero-banners');
    } catch (error: any) {
      console.error('Error saving hero banner:', error);
      if (error.response?.data?.errors) {
        const errors = error.response.data.errors;
        const firstError = Object.values(errors)[0];
        alert(`Validation error: ${firstError}`);
      } else {
        alert('Failed to save hero banner');
      }
    } finally {
      setLoading(false);
    }
  };

  const removeImage = () => {
    setImageFile(null);
    setImagePreview('');
    setExistingImage('');
  };

  if (loading && isEdit) {
    return (
      <div className="flex justify-center items-center h-64">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
      </div>
    );
  }

  return (
    <div className="p-6 max-w-4xl mx-auto">
      <button
        onClick={() => navigate('/hero-banners')}
        className="mb-6 flex items-center text-gray-600 hover:text-gray-900"
      >
        <ArrowLeft className="w-5 h-5 mr-2" />
        Back to Hero Banners
      </button>

      <h1 className="text-2xl font-bold text-gray-900 mb-6">
        {isEdit ? 'Edit Hero Banner' : 'Create New Hero Banner'}
      </h1>

      <form onSubmit={handleSubmit} className="bg-white shadow-md rounded-lg p-6">
        {/* Language Tabs */}
        <div className="mb-6">
          <div className="border-b border-gray-200">
            <nav className="-mb-px flex space-x-8">
              {languages.map(lang => (
                <button
                  key={lang.code}
                  type="button"
                  onClick={() => setActiveTab(lang.code as 'az' | 'en' | 'ru')}
                  className={`py-2 px-1 border-b-2 font-medium text-sm ${
                    activeTab === lang.code
                      ? 'border-indigo-500 text-indigo-600'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                  }`}
                >
                  {lang.label}
                </button>
              ))}
            </nav>
          </div>
        </div>

        {/* Translatable Fields */}
        <div className="space-y-6">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Title {activeTab === 'az' && <span className="text-red-500">*</span>}
            </label>
            <input
              type="text"
              value={formData.title[activeTab]}
              onChange={(e) => handleInputChange('title', e.target.value, activeTab)}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
              placeholder={`Enter title in ${languages.find(l => l.code === activeTab)?.label}`}
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Description
            </label>
            <textarea
              value={formData.description[activeTab]}
              onChange={(e) => handleInputChange('description', e.target.value, activeTab)}
              rows={3}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
              placeholder={`Enter description in ${languages.find(l => l.code === activeTab)?.label}`}
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Button Text
            </label>
            <input
              type="text"
              value={formData.link_text[activeTab]}
              onChange={(e) => handleInputChange('link_text', e.target.value, activeTab)}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
              placeholder={`Enter button text in ${languages.find(l => l.code === activeTab)?.label}`}
            />
          </div>
        </div>

        {/* Non-translatable Fields */}
        <div className="mt-6 space-y-6">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Link URL
            </label>
            <input
              type="text"
              value={formData.link}
              onChange={(e) => handleInputChange('link', e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
              placeholder="https://example.com or /page-path"
            />
            <p className="text-xs text-gray-500 mt-1">External links should start with http:// or https://</p>
          </div>

          <div className="grid grid-cols-2 gap-6">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Display Order
              </label>
              <input
                type="number"
                value={formData.order}
                onChange={(e) => handleInputChange('order', parseInt(e.target.value) || 0)}
                min="0"
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
              <p className="text-xs text-gray-500 mt-1">Lower numbers appear first</p>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Status
              </label>
              <label className="flex items-center">
                <input
                  type="checkbox"
                  checked={formData.is_active}
                  onChange={(e) => handleInputChange('is_active', e.target.checked)}
                  className="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50"
                />
                <span className="ml-2 text-sm text-gray-700">Active</span>
              </label>
              <p className="text-xs text-gray-500 mt-1">Inactive banners will not be displayed</p>
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Banner Image
            </label>
            <div className="mt-2">
              {(imagePreview || existingImage) ? (
                <div className="relative inline-block">
                  <img
                    src={imagePreview || API_CONFIG.getImageUrl(existingImage)}
                    alt="Banner preview"
                    className="max-w-md max-h-64 rounded-lg shadow-md"
                  />
                  <button
                    type="button"
                    onClick={removeImage}
                    className="absolute top-2 right-2 bg-red-500 text-white rounded-full p-1 hover:bg-red-600"
                  >
                    <X className="w-4 h-4" />
                  </button>
                </div>
              ) : (
                <label className="flex flex-col items-center justify-center w-full h-64 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                  <div className="flex flex-col items-center justify-center pt-5 pb-6">
                    <Upload className="w-10 h-10 mb-3 text-gray-400" />
                    <p className="mb-2 text-sm text-gray-500">
                      <span className="font-semibold">Click to upload</span> or drag and drop
                    </p>
                    <p className="text-xs text-gray-500">PNG, JPG, GIF, SVG up to 5MB</p>
                  </div>
                  <input
                    type="file"
                    className="hidden"
                    accept="image/*"
                    onChange={handleImageChange}
                  />
                </label>
              )}
            </div>
          </div>
        </div>

        <div className="mt-8 flex justify-end space-x-4">
          <button
            type="button"
            onClick={() => navigate('/hero-banners')}
            className="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={loading}
            className="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {loading ? 'Saving...' : isEdit ? 'Update Hero Banner' : 'Create Hero Banner'}
          </button>
        </div>
      </form>
    </div>
  );
};

export default HeroBannersForm;