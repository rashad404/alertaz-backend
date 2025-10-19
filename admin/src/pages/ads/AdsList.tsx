import React, { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { adsService, Ad } from '../../services/ads';
import { Trash2, Edit, Plus, Image, Link as LinkIcon, Code, Move } from 'lucide-react';
import { API_CONFIG } from '../../config/api';

const AdsList: React.FC = () => {
  const [ads, setAds] = useState<Ad[]>([]);
  const [loading, setLoading] = useState(true);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [selectedPlace, setSelectedPlace] = useState<string>('');
  const [selectedStatus, setSelectedStatus] = useState<string>('');
  const [draggedItem, setDraggedItem] = useState<number | null>(null);
  const navigate = useNavigate();

  const places = [
    { value: 'hero_section', label: 'Hero Section' },
    { value: 'home_slider', label: 'Home Slider' },
    { value: 'sidebar', label: 'Sidebar' },
    { value: 'banner', label: 'Banner' },
    { value: 'footer', label: 'Footer' },
    { value: 'popup', label: 'Popup' },
  ];

  const fetchAds = async () => {
    setLoading(true);
    try {
      const params: any = {
        page: currentPage,
        per_page: 10,
      };
      
      if (selectedPlace) {
        params.place = selectedPlace;
      }
      
      if (selectedStatus !== '') {
        params.is_active = selectedStatus === 'active';
      }

      console.log('Fetching ads with params:', params);
      const response = await adsService.getAll(params);
      console.log('Ads response:', response);
      
      // Safely access the data
      if (response && response.data) {
        setAds(response.data);
        setTotalPages(response.last_page || 1);
      } else {
        setAds([]);
        setTotalPages(1);
      }
    } catch (error: any) {
      console.error('Error fetching ads:', error);
      console.error('Error response:', error.response);
      // Check if it's an auth error
      if (error.response?.status === 401) {
        alert('Session expired. Please login again.');
        window.location.href = '/admin/login';
      } else {
        alert('Failed to fetch ads. Please try again.');
      }
      setAds([]);
      setTotalPages(1);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchAds();
  }, [currentPage, selectedPlace, selectedStatus]);

  const handleDelete = async (id: number) => {
    if (window.confirm('Are you sure you want to delete this ad?')) {
      try {
        await adsService.delete(id);
        alert('Ad deleted successfully');
        fetchAds();
      } catch (error) {
        console.error('Error deleting ad:', error);
        alert('Failed to delete ad');
      }
    }
  };

  const handleToggleStatus = async (id: number) => {
    // Optimistically update the UI
    setAds(prevAds => 
      prevAds.map(ad => 
        ad.id === id ? { ...ad, is_active: !ad.is_active } : ad
      )
    );

    try {
      await adsService.toggleStatus(id);
      // Don't refresh - we already updated the UI
    } catch (error) {
      console.error('Error toggling ad status:', error);
      alert('Failed to toggle ad status');
      // Revert the optimistic update on error
      setAds(prevAds => 
        prevAds.map(ad => 
          ad.id === id ? { ...ad, is_active: !ad.is_active } : ad
        )
      );
    }
  };

  const handleDragStart = (_e: React.DragEvent, index: number) => {
    setDraggedItem(index);
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
  };

  const handleDrop = async (e: React.DragEvent, dropIndex: number) => {
    e.preventDefault();
    if (draggedItem === null || draggedItem === dropIndex) return;

    const newAds = [...ads];
    const draggedAd = newAds[draggedItem];
    newAds.splice(draggedItem, 1);
    newAds.splice(dropIndex, 0, draggedAd);

    // Update order values
    const reorderData = newAds.map((ad, index) => ({
      id: ad.id,
      order: index,
    }));

    try {
      await adsService.reorder(reorderData);
      setAds(newAds);
      setDraggedItem(null);
    } catch (error) {
      console.error('Error reordering ads:', error);
      alert('Failed to reorder ads');
      fetchAds();
    }
  };

  const getPlaceLabel = (place: string) => {
    const found = places.find(p => p.value === place);
    return found ? found.label : place;
  };

  const getAdTypeIcon = (ad: Ad) => {
    if (ad.iframe) return <Code className="w-4 h-4" />;
    if (ad.image) return <Image className="w-4 h-4" />;
    return <LinkIcon className="w-4 h-4" />;
  };

  if (loading) {
    return (
      <div className="flex justify-center items-center h-64">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
      </div>
    );
  }

  return (
    <div className="p-4 lg:p-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-6 space-y-4 sm:space-y-0">
        <h1 className="text-xl lg:text-2xl font-bold text-gray-900">Ads Management</h1>
        <Link
          to="/ads/new"
          className="flex items-center justify-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 w-full sm:w-auto"
        >
          <Plus className="w-5 h-5" />
          Add New Ad
        </Link>
      </div>

      {/* Filters */}
      <div className="mb-6 flex flex-col sm:flex-row gap-4">
        <select
          value={selectedPlace}
          onChange={(e) => {
            setSelectedPlace(e.target.value);
            setCurrentPage(1);
          }}
          className="w-full sm:w-auto px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
          <option value="">All Places</option>
          {places.map(place => (
            <option key={place.value} value={place.value}>
              {place.label}
            </option>
          ))}
        </select>

        <select
          value={selectedStatus}
          onChange={(e) => {
            setSelectedStatus(e.target.value);
            setCurrentPage(1);
          }}
          className="w-full sm:w-auto px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
          <option value="">All Status</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>

      {ads.length === 0 ? (
        <div className="bg-white rounded-lg shadow p-6 lg:p-8 text-center">
          <div className="text-gray-500">
            <p className="text-lg mb-2">No ads found</p>
            <p className="text-sm">Create your first ad!</p>
          </div>
        </div>
      ) : (
        <>
          {/* Mobile Card Layout */}
          <div className="block lg:hidden space-y-4">
            {ads.map((ad, index) => (
              <div
                key={ad.id}
                draggable
                onDragStart={(e) => handleDragStart(e, index)}
                onDragOver={handleDragOver}
                onDrop={(e) => handleDrop(e, index)}
                className="bg-white rounded-lg shadow-sm border border-gray-200 p-4 cursor-move"
              >
                <div className="flex items-start space-x-3">
                  <div className="flex items-center space-x-2">
                    <Move className="w-4 h-4 text-gray-400" />
                    <div className="text-lg font-semibold text-gray-600 min-w-[24px]">
                      {ad.order}
                    </div>
                  </div>
                  
                  {ad.image ? (
                    <img
                      src={API_CONFIG.getImageUrl(ad.image)}
                      alt="Ad preview"
                      className="w-16 h-12 object-cover rounded"
                    />
                  ) : ad.iframe ? (
                    <div className="w-16 h-12 bg-gray-200 rounded flex items-center justify-center">
                      <Code className="w-6 h-6 text-gray-400" />
                    </div>
                  ) : (
                    <div className="w-16 h-12 bg-gray-200 rounded flex items-center justify-center">
                      <LinkIcon className="w-6 h-6 text-gray-400" />
                    </div>
                  )}

                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-2">
                      {getAdTypeIcon(ad)}
                      <span className="text-sm font-medium text-gray-900">
                        {ad.iframe ? 'iFrame' : ad.image ? 'Image' : 'Link'}
                      </span>
                    </div>
                    <div className="mt-2 space-y-1">
                      <div className="flex items-center">
                        <span className="px-2 py-0.5 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                          {getPlaceLabel(ad.place)}
                        </span>
                      </div>
                      {ad.url && (
                        <div className="text-xs text-gray-500">
                          <span className="font-medium">URL:</span>
                          <a
                            href={ad.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="ml-1 text-indigo-600 hover:text-indigo-900 truncate block"
                          >
                            {ad.url}
                          </a>
                        </div>
                      )}
                      <div className="flex items-center">
                        <label className="relative inline-flex items-center cursor-pointer">
                          <input
                            type="checkbox"
                            checked={ad.is_active}
                            onChange={() => handleToggleStatus(ad.id)}
                            className="sr-only peer"
                          />
                          <div className="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-green-600"></div>
                          <span className="ml-2 text-xs font-medium text-gray-900">
                            {ad.is_active ? 'Active' : 'Inactive'}
                          </span>
                        </label>
                      </div>
                    </div>
                  </div>
                </div>

                <div className="mt-4 flex space-x-2">
                  <button
                    onClick={() => navigate(`/ads/edit/${ad.id}`)}
                    className="flex items-center justify-center space-x-1 px-3 py-2 text-indigo-600 hover:bg-indigo-50 rounded-md text-sm flex-1 border border-indigo-200"
                  >
                    <Edit className="w-4 h-4" />
                    <span>Edit</span>
                  </button>
                  <button
                    onClick={() => handleDelete(ad.id)}
                    className="flex items-center justify-center space-x-1 px-3 py-2 text-red-600 hover:bg-red-50 rounded-md text-sm flex-1 border border-red-200"
                  >
                    <Trash2 className="w-4 h-4" />
                    <span>Delete</span>
                  </button>
                </div>
              </div>
            ))}
          </div>

          {/* Desktop Table Layout */}
          <div className="hidden lg:block bg-white shadow-md rounded-lg overflow-hidden">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Order
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Preview
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Type
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Place
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    URL
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Status
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {ads.map((ad, index) => (
                  <tr
                    key={ad.id}
                    draggable
                    onDragStart={(e) => handleDragStart(e, index)}
                    onDragOver={handleDragOver}
                    onDrop={(e) => handleDrop(e, index)}
                    className="hover:bg-gray-50 cursor-move"
                  >
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="flex items-center">
                        <Move className="w-4 h-4 text-gray-400 mr-2" />
                        <span className="text-sm text-gray-900">{ad.order}</span>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      {ad.image ? (
                        <img
                          src={API_CONFIG.getImageUrl(ad.image)}
                          alt="Ad preview"
                          className="h-12 w-20 object-cover rounded"
                        />
                      ) : ad.iframe ? (
                        <div className="h-12 w-20 bg-gray-200 rounded flex items-center justify-center">
                          <Code className="w-6 h-6 text-gray-400" />
                        </div>
                      ) : (
                        <div className="h-12 w-20 bg-gray-200 rounded flex items-center justify-center">
                          <LinkIcon className="w-6 h-6 text-gray-400" />
                        </div>
                      )}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="flex items-center gap-2">
                        {getAdTypeIcon(ad)}
                        <span className="text-sm text-gray-900">
                          {ad.iframe ? 'iFrame' : ad.image ? 'Image' : 'Link'}
                        </span>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                        {getPlaceLabel(ad.place)}
                      </span>
                    </td>
                    <td className="px-6 py-4">
                      {ad.url ? (
                        <a
                          href={ad.url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="text-sm text-indigo-600 hover:text-indigo-900 truncate block max-w-xs"
                        >
                          {ad.url}
                        </a>
                      ) : (
                        <span className="text-sm text-gray-400">No URL</span>
                      )}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <label className="relative inline-flex items-center cursor-pointer">
                        <input
                          type="checkbox"
                          checked={ad.is_active}
                          onChange={() => handleToggleStatus(ad.id)}
                          className="sr-only peer"
                        />
                        <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-green-600"></div>
                        <span className="ml-3 text-sm font-medium text-gray-900 dark:text-gray-300">
                          {ad.is_active ? 'Active' : 'Inactive'}
                        </span>
                      </label>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      <div className="flex space-x-2">
                        <button
                          onClick={() => navigate(`/ads/edit/${ad.id}`)}
                          className="text-indigo-600 hover:text-indigo-900"
                        >
                          <Edit className="w-5 h-5" />
                        </button>
                        <button
                          onClick={() => handleDelete(ad.id)}
                          className="text-red-600 hover:text-red-900"
                        >
                          <Trash2 className="w-5 h-5" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}

      {totalPages > 1 && (
        <div className="mt-6 flex justify-center space-x-2">
          <button
            onClick={() => setCurrentPage(prev => Math.max(prev - 1, 1))}
            disabled={currentPage === 1}
            className="px-3 py-1 border rounded-md disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Previous
          </button>
          <span className="px-3 py-1">
            Page {currentPage} of {totalPages}
          </span>
          <button
            onClick={() => setCurrentPage(prev => Math.min(prev + 1, totalPages))}
            disabled={currentPage === totalPages}
            className="px-3 py-1 border rounded-md disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Next
          </button>
        </div>
      )}
    </div>
  );
};

export default AdsList;