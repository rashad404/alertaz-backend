import React, { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { heroBannersService, HeroBanner } from '../../services/heroBanners';
import { Trash2, Edit, Plus, Image, Move } from 'lucide-react';
import { API_CONFIG } from '../../config/api';

const HeroBannersList: React.FC = () => {
  const [banners, setBanners] = useState<HeroBanner[]>([]);
  const [loading, setLoading] = useState(true);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [selectedStatus, setSelectedStatus] = useState<string>('');
  const [draggedItem, setDraggedItem] = useState<number | null>(null);
  const navigate = useNavigate();

  const fetchBanners = async () => {
    setLoading(true);
    try {
      const params: any = {
        page: currentPage,
        per_page: 10,
      };
      
      if (selectedStatus !== '') {
        params.is_active = selectedStatus === 'active';
      }

      const response = await heroBannersService.getAll(params);
      setBanners(response.data);
      setTotalPages(response.last_page);
    } catch (error) {
      console.error('Error fetching hero banners:', error);
      alert('Failed to fetch hero banners');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchBanners();
  }, [currentPage, selectedStatus]);

  const handleDelete = async (id: number) => {
    if (window.confirm('Are you sure you want to delete this hero banner?')) {
      try {
        await heroBannersService.delete(id);
        alert('Hero banner deleted successfully');
        fetchBanners();
      } catch (error) {
        console.error('Error deleting hero banner:', error);
        alert('Failed to delete hero banner');
      }
    }
  };

  const handleToggleStatus = async (id: number) => {
    // Optimistically update the UI
    setBanners(prevBanners => 
      prevBanners.map(banner => 
        banner.id === id ? { ...banner, is_active: !banner.is_active } : banner
      )
    );

    try {
      await heroBannersService.toggleStatus(id);
      // Don't refresh - we already updated the UI
    } catch (error) {
      console.error('Error toggling hero banner status:', error);
      alert('Failed to toggle hero banner status');
      // Revert the optimistic update on error
      setBanners(prevBanners => 
        prevBanners.map(banner => 
          banner.id === id ? { ...banner, is_active: !banner.is_active } : banner
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

    const newBanners = [...banners];
    const draggedBanner = newBanners[draggedItem];
    newBanners.splice(draggedItem, 1);
    newBanners.splice(dropIndex, 0, draggedBanner);

    // Update order values
    const reorderData = newBanners.map((banner, index) => ({
      id: banner.id,
      order: index,
    }));

    try {
      await heroBannersService.reorder(reorderData);
      setBanners(newBanners);
      setDraggedItem(null);
    } catch (error) {
      console.error('Error reordering hero banners:', error);
      alert('Failed to reorder hero banners');
      fetchBanners();
    }
  };

  const getTitleText = (banner: HeroBanner) => {
    if (typeof banner.title === 'object' && banner.title !== null) {
      return banner.title.az || '';
    }
    return banner.title || '';
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
        <h1 className="text-xl lg:text-2xl font-bold text-gray-900">Hero Banners Management</h1>
        <Link
          to="/hero-banners/new"
          className="flex items-center justify-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 w-full sm:w-auto"
        >
          <Plus className="w-5 h-5" />
          Add New Hero Banner
        </Link>
      </div>

      {/* Filters */}
      <div className="mb-6">
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

      {banners.length === 0 ? (
        <div className="bg-white rounded-lg shadow p-6 lg:p-8 text-center">
          <div className="text-gray-500">
            <p className="text-lg mb-2">No hero banners found</p>
            <p className="text-sm">Create your first hero banner!</p>
          </div>
        </div>
      ) : (
        <>
          {/* Mobile Card Layout */}
          <div className="block lg:hidden space-y-4">
            {banners.map((banner, index) => (
              <div
                key={banner.id}
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
                      {banner.order}
                    </div>
                  </div>
                  
                  {banner.image ? (
                    <img
                      src={API_CONFIG.getImageUrl(banner.image)}
                      alt="Banner preview"
                      className="w-16 h-12 object-cover rounded"
                    />
                  ) : (
                    <div className="w-16 h-12 bg-gray-200 rounded flex items-center justify-center">
                      <Image className="w-6 h-6 text-gray-400" />
                    </div>
                  )}

                  <div className="flex-1 min-w-0">
                    <h3 className="font-medium text-gray-900 truncate">
                      {getTitleText(banner)}
                    </h3>
                    <div className="mt-2 space-y-1">
                      {banner.link && (
                        <div className="text-xs text-gray-500">
                          <span className="font-medium">Link:</span>
                          <span className="ml-1 truncate block">
                            {banner.link.startsWith('http') ? (
                              <a
                                href={banner.link}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-indigo-600 hover:text-indigo-900"
                              >
                                {banner.link}
                              </a>
                            ) : (
                              banner.link
                            )}
                          </span>
                        </div>
                      )}
                      <div className="flex items-center">
                        <label className="relative inline-flex items-center cursor-pointer">
                          <input
                            type="checkbox"
                            checked={banner.is_active}
                            onChange={() => handleToggleStatus(banner.id)}
                            className="sr-only peer"
                          />
                          <div className="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-green-600"></div>
                          <span className="ml-2 text-xs font-medium text-gray-900">
                            {banner.is_active ? 'Active' : 'Inactive'}
                          </span>
                        </label>
                      </div>
                    </div>
                  </div>
                </div>

                <div className="mt-4 flex space-x-2">
                  <button
                    onClick={() => navigate(`/hero-banners/edit/${banner.id}`)}
                    className="flex items-center justify-center space-x-1 px-3 py-2 text-indigo-600 hover:bg-indigo-50 rounded-md text-sm flex-1 border border-indigo-200"
                  >
                    <Edit className="w-4 h-4" />
                    <span>Edit</span>
                  </button>
                  <button
                    onClick={() => handleDelete(banner.id)}
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
                    Title
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Link
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
                {banners.map((banner, index) => (
                  <tr
                    key={banner.id}
                    draggable
                    onDragStart={(e) => handleDragStart(e, index)}
                    onDragOver={handleDragOver}
                    onDrop={(e) => handleDrop(e, index)}
                    className="hover:bg-gray-50 cursor-move"
                  >
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="flex items-center">
                        <Move className="w-4 h-4 text-gray-400 mr-2" />
                        <span className="text-sm text-gray-900">{banner.order}</span>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      {banner.image ? (
                        <img
                          src={API_CONFIG.getImageUrl(banner.image)}
                          alt="Banner preview"
                          className="h-12 w-20 object-cover rounded"
                        />
                      ) : (
                        <div className="h-12 w-20 bg-gray-200 rounded flex items-center justify-center">
                          <Image className="w-6 h-6 text-gray-400" />
                        </div>
                      )}
                    </td>
                    <td className="px-6 py-4">
                      <div className="text-sm text-gray-900 max-w-xs truncate">
                        {getTitleText(banner)}
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      {banner.link ? (
                        banner.link.startsWith('http') ? (
                          <a
                            href={banner.link}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-sm text-indigo-600 hover:text-indigo-900 truncate block max-w-xs"
                          >
                            {banner.link}
                          </a>
                        ) : (
                          <span className="text-sm text-gray-600 truncate block max-w-xs">
                            {banner.link}
                          </span>
                        )
                      ) : (
                        <span className="text-sm text-gray-400">No link</span>
                      )}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <label className="relative inline-flex items-center cursor-pointer">
                        <input
                          type="checkbox"
                          checked={banner.is_active}
                          onChange={() => handleToggleStatus(banner.id)}
                          className="sr-only peer"
                        />
                        <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-green-600"></div>
                        <span className="ml-3 text-sm font-medium text-gray-900 dark:text-gray-300">
                          {banner.is_active ? 'Active' : 'Inactive'}
                        </span>
                      </label>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      <div className="flex space-x-2">
                        <button
                          onClick={() => navigate(`/hero-banners/edit/${banner.id}`)}
                          className="text-indigo-600 hover:text-indigo-900"
                        >
                          <Edit className="w-5 h-5" />
                        </button>
                        <button
                          onClick={() => handleDelete(banner.id)}
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

export default HeroBannersList;