import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { GripVertical, Plus, Trash2, Search, Image } from 'lucide-react';
import { slidersService } from '../../services/sliders';
import type { AvailableNews } from '../../services/sliders';
import { API_CONFIG } from '../../config/api';

export default function SliderList() {
  const queryClient = useQueryClient();
  const [showAddModal, setShowAddModal] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedLanguage, setSelectedLanguage] = useState('az');
  const [draggedItem, setDraggedItem] = useState<number | null>(null);

  // Fetch slider items
  const { data: sliders = [], isLoading } = useQuery({
    queryKey: ['sliders'],
    queryFn: slidersService.getAll,
  });

  // Fetch available news
  const { data: availableNews = [] } = useQuery({
    queryKey: ['available-news', selectedLanguage, searchTerm],
    queryFn: () => slidersService.getAvailableNews(selectedLanguage, searchTerm),
    enabled: showAddModal,
  });

  // Delete mutation
  const deleteMutation = useMutation({
    mutationFn: slidersService.delete,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sliders'] });
    },
  });

  // Add mutation
  const addMutation = useMutation({
    mutationFn: ({ news_id, order }: { news_id: number; order?: number }) =>
      slidersService.create(news_id, order),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sliders'] });
      setShowAddModal(false);
    },
  });

  // Reorder mutation
  const reorderMutation = useMutation({
    mutationFn: slidersService.reorder,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sliders'] });
    },
  });

  const handleDragStart = (e: React.DragEvent, index: number) => {
    setDraggedItem(index);
    e.dataTransfer.effectAllowed = 'move';
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
  };

  const handleDrop = (e: React.DragEvent, dropIndex: number) => {
    e.preventDefault();
    if (draggedItem === null) return;

    const draggedSlider = sliders[draggedItem];
    const newSliders = [...sliders];
    
    // Remove the dragged item
    newSliders.splice(draggedItem, 1);
    
    // Insert it at the new position
    newSliders.splice(dropIndex, 0, draggedSlider);
    
    // Update orders
    const reorderedItems = newSliders.map((slider, index) => ({
      id: slider.id,
      order: index,
    }));

    reorderMutation.mutate(reorderedItems);
    setDraggedItem(null);
  };

  const handleAddNews = (news: AvailableNews) => {
    const maxOrder = Math.max(...sliders.map(s => s.order), -1);
    addMutation.mutate({ news_id: news.id, order: maxOrder + 1 });
  };

  const handleDelete = (id: number) => {
    if (confirm('Are you sure you want to remove this item from the slider?')) {
      deleteMutation.mutate(id);
    }
  };

  const getImageUrl = (path: string | null) => {
    if (!path) return null;
    if (path.startsWith('http')) return path;
    return API_CONFIG.getImageUrl(`/storage/${path}`);
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="text-lg text-gray-600">Loading...</div>
      </div>
    );
  }

  return (
    <div className="p-4 lg:p-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-6 space-y-4 sm:space-y-0">
        <h1 className="text-xl lg:text-2xl font-bold text-gray-900">Homepage Slider</h1>
        <button
          onClick={() => setShowAddModal(true)}
          className="flex items-center justify-center space-x-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 w-full sm:w-auto"
        >
          <Plus className="h-4 w-4" />
          <span>Add News</span>
        </button>
      </div>

      {sliders.length === 0 ? (
        <div className="bg-white rounded-lg shadow p-6 lg:p-8 text-center">
          <div className="text-gray-500">
            <p className="text-lg mb-2">No items in slider</p>
            <p className="text-sm">Click "Add News" to add news to the homepage slider</p>
          </div>
        </div>
      ) : (
        <>
          {/* Mobile Card Layout */}
          <div className="block lg:hidden space-y-4">
            <div className="bg-blue-50 rounded-lg p-4 mb-4">
              <p className="text-sm text-blue-800">
                Drag and drop to reorder items. The top item will appear first in the slider.
              </p>
            </div>
            {sliders.map((slider, index) => (
              <div
                key={slider.id}
                draggable
                onDragStart={(e) => handleDragStart(e, index)}
                onDragOver={handleDragOver}
                onDrop={(e) => handleDrop(e, index)}
                className="bg-white rounded-lg shadow-sm border border-gray-200 p-4 cursor-move"
              >
                <div className="flex items-start space-x-3">
                  <div className="flex items-center space-x-2">
                    <GripVertical className="h-5 w-5 text-gray-400" />
                    <div className="text-lg font-semibold text-gray-600 min-w-[24px]">
                      {index + 1}
                    </div>
                  </div>
                  
                  {slider.news?.thumbnail_image ? (
                    <img
                      src={getImageUrl(slider.news.thumbnail_image) || ''}
                      alt=""
                      className="w-16 h-12 object-cover rounded"
                      onError={(e) => {
                        e.currentTarget.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="80" height="48" viewBox="0 0 80 48"%3E%3Crect width="80" height="48" fill="%23f3f4f6"/%3E%3Cg fill="%239ca3af"%3E%3Cpath d="M40 18c-2.761 0-5 2.239-5 5s2.239 5 5 5 5-2.239 5-5-2.239-5-5-5zm0 7c-1.104 0-2-.896-2-2s.896-2 2-2 2 .896 2 2-.896 2-2 2z"/%3E%3Cpath d="M30 14h20c1.104 0 2 .896 2 2v16c0 1.104-.896 2-2 2H30c-1.104 0-2-.896-2-2V16c0-1.104.896-2 2-2zm18 16v-6l-3-3c-.78-.78-2.04-.78-2.82 0L38 25.2l-2-2c-.78-.78-2.04-.78-2.82 0L30 26.4V30h18z"/%3E%3C/g%3E%3C/svg%3E';
                        e.currentTarget.onerror = null;
                      }}
                    />
                  ) : (
                    <div className="w-16 h-12 bg-gray-200 rounded flex items-center justify-center">
                      <Image className="h-6 w-6 text-gray-400" />
                    </div>
                  )}

                  <div className="flex-1 min-w-0">
                    <h3 className="font-medium text-gray-900 truncate">
                      {slider.news?.title || 'Unknown News'}
                    </h3>
                    <div className="mt-2 space-y-1">
                      <div className="flex items-center text-xs text-gray-500">
                        <span className="font-medium">Language:</span>
                        <span className="ml-1">{slider.news?.language?.toUpperCase()}</span>
                      </div>
                      <div className="flex items-center text-xs text-gray-500">
                        <span className="font-medium">Date:</span>
                        <span className="ml-1">
                          {slider.news?.publish_date
                            ? new Date(slider.news.publish_date).toLocaleDateString()
                            : 'N/A'}
                        </span>
                      </div>
                      <div className="flex items-center">
                        <span className={`px-2 py-0.5 rounded text-xs ${
                          slider.news?.status 
                            ? 'bg-green-100 text-green-700' 
                            : 'bg-red-100 text-red-700'
                        }`}>
                          {slider.news?.status ? 'Active' : 'Inactive'}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>

                <div className="mt-4 flex justify-end">
                  <button
                    onClick={() => handleDelete(slider.id)}
                    className="flex items-center space-x-1 px-3 py-2 text-red-600 hover:bg-red-50 rounded-md text-sm w-full justify-center border border-red-200"
                  >
                    <Trash2 className="h-4 w-4" />
                    <span>Remove</span>
                  </button>
                </div>
              </div>
            ))}
          </div>

          {/* Desktop Table Layout */}
          <div className="hidden lg:block bg-white rounded-lg shadow">
            <div className="p-4 border-b">
              <p className="text-sm text-gray-600">
                Drag and drop to reorder items. The top item will appear first in the slider.
              </p>
            </div>
            <div className="divide-y">
              {sliders.map((slider, index) => (
                <div
                  key={slider.id}
                  draggable
                  onDragStart={(e) => handleDragStart(e, index)}
                  onDragOver={handleDragOver}
                  onDrop={(e) => handleDrop(e, index)}
                  className="p-4 flex items-center space-x-4 hover:bg-gray-50 cursor-move"
                >
                  <GripVertical className="h-5 w-5 text-gray-400" />
                  
                  <div className="text-lg font-semibold text-gray-600 w-8">
                    {index + 1}
                  </div>

                  {slider.news?.thumbnail_image ? (
                    <img
                      src={getImageUrl(slider.news.thumbnail_image) || ''}
                      alt=""
                      className="w-20 h-12 object-cover rounded"
                      onError={(e) => {
                        e.currentTarget.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="80" height="48" viewBox="0 0 80 48"%3E%3Crect width="80" height="48" fill="%23f3f4f6"/%3E%3Cg fill="%239ca3af"%3E%3Cpath d="M40 18c-2.761 0-5 2.239-5 5s2.239 5 5 5 5-2.239 5-5-2.239-5-5-5zm0 7c-1.104 0-2-.896-2-2s.896-2 2-2 2 .896 2 2-.896 2-2 2z"/%3E%3Cpath d="M30 14h20c1.104 0 2 .896 2 2v16c0 1.104-.896 2-2 2H30c-1.104 0-2-.896-2-2V16c0-1.104.896-2 2-2zm18 16v-6l-3-3c-.78-.78-2.04-.78-2.82 0L38 25.2l-2-2c-.78-.78-2.04-.78-2.82 0L30 26.4V30h18z"/%3E%3C/g%3E%3C/svg%3E';
                        e.currentTarget.onerror = null;
                      }}
                    />
                  ) : (
                    <div className="w-20 h-12 bg-gray-200 rounded flex items-center justify-center">
                      <Image className="h-6 w-6 text-gray-400" />
                    </div>
                  )}

                  <div className="flex-1">
                    <h3 className="font-medium text-gray-900">
                      {slider.news?.title || 'Unknown News'}
                    </h3>
                    <div className="text-sm text-gray-500 flex items-center space-x-4">
                      <span>Language: {slider.news?.language?.toUpperCase()}</span>
                      <span>
                        Date: {slider.news?.publish_date
                          ? new Date(slider.news.publish_date).toLocaleDateString()
                          : 'N/A'}
                      </span>
                      <span className={`px-2 py-0.5 rounded text-xs ${
                        slider.news?.status 
                          ? 'bg-green-100 text-green-700' 
                          : 'bg-red-100 text-red-700'
                      }`}>
                        {slider.news?.status ? 'Active' : 'Inactive'}
                      </span>
                    </div>
                  </div>

                  <button
                    onClick={() => handleDelete(slider.id)}
                    className="p-2 text-red-600 hover:bg-red-50 rounded"
                  >
                    <Trash2 className="h-4 w-4" />
                  </button>
                </div>
              ))}
            </div>
          </div>
        </>
      )}

      {/* Add News Modal */}
      {showAddModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-3xl max-h-[80vh] overflow-hidden">
            <div className="p-6 border-b">
              <h2 className="text-xl font-bold text-gray-900">Add News to Slider</h2>
            </div>

            <div className="p-6">
              <div className="flex space-x-4 mb-4">
                <select
                  value={selectedLanguage}
                  onChange={(e) => setSelectedLanguage(e.target.value)}
                  className="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="az">Azerbaijani</option>
                  <option value="en">English</option>
                  <option value="ru">Russian</option>
                </select>

                <div className="flex-1 relative">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                  <input
                    type="text"
                    placeholder="Search news..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>
              </div>

              <div className="max-h-96 overflow-y-auto border rounded-md">
                {availableNews.length === 0 ? (
                  <div className="p-8 text-center text-gray-500">
                    No available news found
                  </div>
                ) : (
                  <div className="divide-y">
                    {availableNews.map((news) => (
                      <div
                        key={news.id}
                        className="p-4 flex items-center space-x-4 hover:bg-gray-50 cursor-pointer"
                        onClick={() => handleAddNews(news)}
                      >
                        {news.thumbnail_image ? (
                          <img
                            src={getImageUrl(news.thumbnail_image) || ''}
                            alt=""
                            className="w-16 h-10 object-cover rounded"
                            onError={(e) => {
                              e.currentTarget.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="64" height="40" viewBox="0 0 64 40"%3E%3Crect width="64" height="40" fill="%23f3f4f6"/%3E%3Cg fill="%239ca3af"%3E%3Cpath d="M32 15c-2.209 0-4 1.791-4 4s1.791 4 4 4 4-1.791 4-4-1.791-4-4-4zm0 6c-.883 0-1.6-.717-1.6-1.6s.717-1.6 1.6-1.6 1.6.717 1.6 1.6-.717 1.6-1.6 1.6z"/%3E%3Cpath d="M24 12h16c.883 0 1.6.717 1.6 1.6v12.8c0 .883-.717 1.6-1.6 1.6H24c-.883 0-1.6-.717-1.6-1.6V13.6c0-.883.717-1.6 1.6-1.6zm14.4 12.8v-4.8l-2.4-2.4c-.624-.624-1.632-.624-2.256 0L30.4 20.16l-1.6-1.6c-.624-.624-1.632-.624-2.256 0L24 21.12v3.68h14.4z"/%3E%3C/g%3E%3C/svg%3E';
                              e.currentTarget.onerror = null; // Prevent infinite loop
                            }}
                          />
                        ) : (
                          <div className="w-16 h-10 bg-gray-200 rounded flex items-center justify-center">
                            <Image className="h-5 w-5 text-gray-400" />
                          </div>
                        )}

                        <div className="flex-1">
                          <h4 className="font-medium text-gray-900">{news.title}</h4>
                          <p className="text-sm text-gray-500">
                            {new Date(news.publish_date).toLocaleDateString()}
                          </p>
                        </div>

                        <button className="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                          Add
                        </button>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>

            <div className="p-6 border-t flex justify-end">
              <button
                onClick={() => setShowAddModal(false)}
                className="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}