import React, { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { adsService } from '../../services/ads';
import { ArrowLeft, Upload, X, Code, Image as ImageIcon, Link as LinkIcon } from 'lucide-react';
import { API_CONFIG } from '../../config/api';

const AdsForm: React.FC = () => {
  const navigate = useNavigate();
  const { id } = useParams();
  const isEdit = !!id;

  const [formData, setFormData] = useState({
    place: 'hero_section',
    iframe: '',
    url: '',
    is_active: true,
    order: 0,
  });

  const [imageFile, setImageFile] = useState<File | null>(null);
  const [imagePreview, setImagePreview] = useState<string>('');
  const [existingImage, setExistingImage] = useState<string>('');
  const [loading, setLoading] = useState(false);
  const [adType, setAdType] = useState<'image' | 'iframe' | 'link'>('image');

  const places = [
    { value: 'hero_section', label: 'Hero Section', description: 'Main page hero section ad (right side)' },
    { value: 'home_slider', label: 'Home Slider', description: 'Main page slider ad' },
    { value: 'sidebar', label: 'Sidebar', description: 'Sidebar advertisement' },
    { value: 'banner', label: 'Banner', description: 'Top or bottom banner' },
    { value: 'footer', label: 'Footer', description: 'Footer advertisement' },
    { value: 'popup', label: 'Popup', description: 'Popup advertisement' },
  ];

  useEffect(() => {
    if (isEdit) {
      fetchAd();
    }
  }, [id]);

  const fetchAd = async () => {
    if (!id) return;
    
    setLoading(true);
    try {
      const ad = await adsService.getById(parseInt(id));
      setFormData({
        place: ad.place,
        iframe: ad.iframe || '',
        url: ad.url || '',
        is_active: ad.is_active,
        order: ad.order,
      });
      
      if (ad.image) {
        setExistingImage(ad.image);
        setAdType('image');
      } else if (ad.iframe) {
        setAdType('iframe');
      } else {
        setAdType('link');
      }
    } catch (error) {
      console.error('Error fetching ad:', error);
      alert('Failed to fetch ad');
      navigate('/ads');
    } finally {
      setLoading(false);
    }
  };

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    const { name, value, type } = e.target;
    if (type === 'checkbox') {
      const target = e.target as HTMLInputElement;
      setFormData(prev => ({ ...prev, [name]: target.checked }));
    } else {
      setFormData(prev => ({ ...prev, [name]: value }));
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
    setLoading(true);

    try {
      const submitData = new FormData();
      submitData.append('place', formData.place);
      submitData.append('is_active', formData.is_active ? '1' : '0');
      submitData.append('order', formData.order.toString());

      if (adType === 'image' && imageFile) {
        submitData.append('image', imageFile);
      } else if (adType === 'iframe') {
        submitData.append('iframe', formData.iframe);
      }
      
      if (formData.url) {
        submitData.append('url', formData.url);
      }

      if (isEdit) {
        await adsService.update(parseInt(id!), submitData);
        alert('Ad updated successfully');
      } else {
        await adsService.create(submitData);
        alert('Ad created successfully');
      }

      navigate('/ads');
    } catch (error) {
      console.error('Error saving ad:', error);
      alert('Failed to save ad');
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
        onClick={() => navigate('/ads')}
        className="mb-6 flex items-center text-gray-600 hover:text-gray-900"
      >
        <ArrowLeft className="w-5 h-5 mr-2" />
        Back to Ads
      </button>

      <h1 className="text-2xl font-bold text-gray-900 mb-6">
        {isEdit ? 'Edit Ad' : 'Create New Ad'}
      </h1>

      <form onSubmit={handleSubmit} className="bg-white shadow-md rounded-lg p-6">
        <div className="mb-6">
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Ad Type
          </label>
          <div className="flex space-x-4">
            <button
              type="button"
              onClick={() => setAdType('image')}
              className={`flex items-center px-4 py-2 rounded-md ${
                adType === 'image'
                  ? 'bg-indigo-600 text-white'
                  : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
              }`}
            >
              <ImageIcon className="w-4 h-4 mr-2" />
              Image Ad
            </button>
            <button
              type="button"
              onClick={() => setAdType('iframe')}
              className={`flex items-center px-4 py-2 rounded-md ${
                adType === 'iframe'
                  ? 'bg-indigo-600 text-white'
                  : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
              }`}
            >
              <Code className="w-4 h-4 mr-2" />
              iFrame Ad
            </button>
            <button
              type="button"
              onClick={() => setAdType('link')}
              className={`flex items-center px-4 py-2 rounded-md ${
                adType === 'link'
                  ? 'bg-indigo-600 text-white'
                  : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
              }`}
            >
              <LinkIcon className="w-4 h-4 mr-2" />
              Link Only
            </button>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label htmlFor="place" className="block text-sm font-medium text-gray-700 mb-2">
              Placement
            </label>
            <select
              id="place"
              name="place"
              value={formData.place}
              onChange={handleInputChange}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
              required
            >
              {places.map(place => (
                <option key={place.value} value={place.value}>
                  {place.label} - {place.description}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label htmlFor="order" className="block text-sm font-medium text-gray-700 mb-2">
              Display Order
            </label>
            <input
              type="number"
              id="order"
              name="order"
              value={formData.order}
              onChange={handleInputChange}
              min="0"
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
            <p className="text-xs text-gray-500 mt-1">Lower numbers appear first</p>
          </div>
        </div>

        {adType === 'image' && (
          <div className="mt-6">
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Ad Image
            </label>
            <div className="mt-2">
              {(imagePreview || existingImage) ? (
                <div className="relative inline-block">
                  <img
                    src={imagePreview || API_CONFIG.getImageUrl(existingImage)}
                    alt="Ad preview"
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
        )}

        {adType === 'iframe' && (
          <div className="mt-6">
            <label htmlFor="iframe" className="block text-sm font-medium text-gray-700 mb-2">
              iFrame Code
            </label>
            <textarea
              id="iframe"
              name="iframe"
              value={formData.iframe}
              onChange={handleInputChange}
              rows={6}
              placeholder='<iframe src="https://example.com" width="300" height="250"></iframe>'
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-sm"
              required={adType === 'iframe'}
            />
            <p className="text-xs text-gray-500 mt-1">Paste the complete iFrame embed code</p>
          </div>
        )}

        <div className="mt-6">
          <label htmlFor="url" className="block text-sm font-medium text-gray-700 mb-2">
            Click URL {adType === 'link' ? '(Required)' : '(Optional)'}
          </label>
          <input
            type="url"
            id="url"
            name="url"
            value={formData.url}
            onChange={handleInputChange}
            placeholder="https://example.com"
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
            required={adType === 'link'}
          />
          <p className="text-xs text-gray-500 mt-1">
            {adType === 'link' 
              ? 'The URL where users will be redirected when clicking'
              : 'Leave empty if the ad should not be clickable'}
          </p>
        </div>

        <div className="mt-6">
          <label className="flex items-center">
            <input
              type="checkbox"
              name="is_active"
              checked={formData.is_active}
              onChange={handleInputChange}
              className="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50"
            />
            <span className="ml-2 text-sm text-gray-700">Active</span>
          </label>
          <p className="text-xs text-gray-500 mt-1">
            Inactive ads will not be displayed on the website
          </p>
        </div>

        <div className="mt-8 flex justify-end space-x-4">
          <button
            type="button"
            onClick={() => navigate('/ads')}
            className="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={loading}
            className="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {loading ? 'Saving...' : isEdit ? 'Update Ad' : 'Create Ad'}
          </button>
        </div>
      </form>
    </div>
  );
};

export default AdsForm;