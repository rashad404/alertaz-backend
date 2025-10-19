import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { Plus, Edit, Trash2, Search, ChevronLeft, ChevronRight, Eye, Calendar, User, Image } from 'lucide-react';
import { newsService } from '../../services/news';
import type { NewsFilters } from '../../services/news';
import type { News } from '../../types';
import { API_CONFIG } from '../../config/api';

export default function NewsList() {
  const queryClient = useQueryClient();
  const [filters, setFilters] = useState<NewsFilters>({
    page: 1,
    per_page: 10,
  });
  const [searchTerm, setSearchTerm] = useState('');

  const { data, isLoading } = useQuery({
    queryKey: ['news', filters],
    queryFn: () => newsService.getAll(filters),
  });

  const deleteMutation = useMutation({
    mutationFn: newsService.delete,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['news'] });
    },
  });

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    setFilters({ ...filters, search: searchTerm, page: 1 });
  };

  const handleDelete = async (id: number) => {
    if (confirm('Are you sure you want to delete this news item?')) {
      deleteMutation.mutate(id);
    }
  };

  const handlePageChange = (newPage: number) => {
    setFilters({ ...filters, page: newPage });
  };

  const formatDate = (date: string) => {
    // Date comes as "2025-09-26T02:47:00" from backend (Azerbaijan time)
    // Just parse and format it as-is
    const [datePart, timePart] = date.split('T');
    const [year, month, day] = datePart.split('-');
    const [hour, minute] = timePart ? timePart.split(':') : ['00', '00'];

    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const monthName = months[parseInt(month, 10) - 1];
    const dayNum = parseInt(day, 10);

    // Format: "Sep 26, 2025, 02:47 AM"
    const hourNum = parseInt(hour, 10);
    const isPM = hourNum >= 12;
    const displayHour = hourNum === 0 ? 12 : hourNum > 12 ? hourNum - 12 : hourNum;
    const ampm = isPM ? 'PM' : 'AM';

    return `${monthName} ${dayNum}, ${year}, ${displayHour.toString().padStart(2, '0')}:${minute} ${ampm}`;
  };

  const getStatusBadge = (status: boolean) => {
    return status ? (
      <span className="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
        Active
      </span>
    ) : (
      <span className="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
        Inactive
      </span>
    );
  };

  const getLanguageBadge = (lang: string) => {
    const colors = {
      az: 'bg-blue-100 text-blue-800',
      en: 'bg-purple-100 text-purple-800',
      ru: 'bg-orange-100 text-orange-800',
    };
    return (
      <span className={`px-2 py-1 text-xs font-semibold rounded-full ${colors[lang as keyof typeof colors]}`}>
        {lang.toUpperCase()}
      </span>
    );
  };

  const getCategoryName = (category: any) => {
    if (!category) return '-';
    if (typeof category.title === 'object') {
      return category.title.en || category.title.az || category.title.ru || '-';
    }
    return category.title || '-';
  };

  const getImageUrl = (imagePath: string | null | undefined) => {
    if (!imagePath) return null;

    // If it's already a full URL, return as is
    if (imagePath.startsWith('http')) {
      return imagePath;
    }

    // Otherwise, use centralized API config
    return API_CONFIG.getImageUrl(`/storage/${imagePath}`);
  };

  return (
    <div className="px-4 sm:px-6 lg:px-8">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-6 space-y-4 sm:space-y-0">
        <h1 className="text-xl sm:text-2xl font-bold text-gray-900">News Management</h1>
        <Link
          to="/news/create"
          className="inline-flex items-center justify-center px-3 py-2 sm:px-4 bg-blue-600 text-white text-sm sm:text-base rounded-md hover:bg-blue-700 transition-colors"
        >
          <Plus className="h-4 w-4 sm:h-5 sm:w-5 mr-1 sm:mr-2" />
          <span>Add News</span>
        </Link>
      </div>

      {/* Search and Filters */}
      <div className="bg-white rounded-lg shadow mb-4 sm:mb-6 p-3 sm:p-4">
        <form onSubmit={handleSearch} className="space-y-3">
          <div className="flex gap-2 sm:gap-4">
            <div className="flex-1">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4 sm:h-5 sm:w-5" />
                <input
                  type="text"
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  placeholder="Search news..."
                  className="w-full pl-9 sm:pl-10 pr-3 py-2 text-sm sm:text-base border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
            </div>
            <button
              type="submit"
              className="px-3 py-2 sm:px-4 bg-blue-600 text-white text-sm sm:text-base rounded-md hover:bg-blue-700 transition-colors"
            >
              <span className="hidden sm:inline">Search</span>
              <Search className="h-4 w-4 sm:hidden" />
            </button>
          </div>

          {/* Filters Row */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label className="block text-xs font-medium text-gray-700 mb-1">Language</label>
              <select
                value={filters.language || ''}
                onChange={(e) => setFilters({ ...filters, language: e.target.value || undefined, page: 1 })}
                className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">All Languages</option>
                <option value="az">Azərbaycan</option>
                <option value="en">English</option>
                <option value="ru">Русский</option>
              </select>
            </div>

            <div>
              <label className="block text-xs font-medium text-gray-700 mb-1">Status</label>
              <select
                value={filters.status !== undefined ? String(filters.status) : ''}
                onChange={(e) => {
                  const value = e.target.value;
                  setFilters({
                    ...filters,
                    status: value === '' ? undefined : (value === 'true' || value === '1'),
                    page: 1
                  });
                }}
                className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">All Status</option>
                <option value="true">Active</option>
                <option value="false">Inactive</option>
              </select>
            </div>

            <div>
              <label className="block text-xs font-medium text-gray-700 mb-1">Author</label>
              <input
                type="text"
                value={filters.author || ''}
                onChange={(e) => setFilters({ ...filters, author: e.target.value || undefined, page: 1 })}
                placeholder="Filter by author..."
                className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
          </div>
        </form>
      </div>

      {/* Content */}
      {isLoading ? (
        <div className="bg-white rounded-lg shadow p-8 text-center">
          <div className="text-gray-500">Loading...</div>
        </div>
      ) : (
        <>
          {/* Mobile View - Cards */}
          <div className="block lg:hidden space-y-4">
            {data?.data.map((news: News) => (
              <div key={news.id} className="bg-white rounded-lg shadow p-4">
                <div className="flex gap-3">
                  {/* Thumbnail */}
                  <div className="flex-shrink-0 flex items-center justify-center">
                    {news.thumbnail_image ? (
                      <img
                        src={getImageUrl(news.thumbnail_image) || ''}
                        alt={news.title}
                        className="w-20 h-20 object-cover rounded"
                        onError={(e) => {
                          const target = e.target as HTMLImageElement;
                          target.onerror = null;
                          target.style.display = 'none';
                          target.parentElement?.classList.add('bg-gray-100', 'flex', 'items-center', 'justify-center');
                          const icon = document.createElement('div');
                          icon.innerHTML = '<svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>';
                          target.parentElement?.appendChild(icon);
                        }}
                      />
                    ) : (
                      <div className="w-20 h-20 bg-gray-100 rounded flex items-center justify-center">
                        <Image className="w-8 h-8 text-gray-400" />
                      </div>
                    )}
                  </div>

                  <div className="flex-1">
                    {/* Title and Status */}
                    <div className="flex justify-between items-start mb-3">
                      <div className="flex-1 pr-2">
                        <Link
                          to={`/news/${news.id}/edit`}
                          className="text-base font-semibold text-gray-900 hover:text-blue-600 line-clamp-2"
                        >
                          {news.title}
                        </Link>
                      </div>
                      {getStatusBadge(news.status)}
                    </div>

                    {/* Meta Information */}
                    <div className="space-y-2 text-sm text-gray-600 mb-3">
                      <div className="flex items-center gap-4">
                        <span className={`flex items-center gap-1 ${news.is_scheduled ? 'text-orange-600 font-semibold' : ''}`}>
                          <Calendar className="h-3 w-3" />
                          {formatDate(news.publish_date)}
                          {news.is_scheduled && (
                            <span className="ml-1 px-1.5 py-0.5 text-xs bg-orange-100 text-orange-700 rounded">
                              Planlanmış
                            </span>
                          )}
                        </span>
                        <span className="flex items-center gap-1">
                          <Eye className="h-3 w-3" />
                          {news.views} views
                        </span>
                      </div>

                      {news.author && (
                        <div className="flex items-center gap-1">
                          <User className="h-3 w-3" />
                          {news.author}
                        </div>
                      )}

                      <div className="flex items-center gap-2 flex-wrap">
                        {getLanguageBadge(news.language)}
                        <span className="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded">
                          {getCategoryName(news.category)}
                        </span>
                        {news.is_ai_generated && (
                          <span className="text-xs bg-purple-100 text-purple-800 px-2 py-1 rounded font-semibold">
                            AI
                          </span>
                        )}
                      </div>
                    </div>

                    {/* Actions */}
                    <div className="flex gap-2 pt-3 border-t">
                      <Link
                        to={`/news/${news.id}/edit`}
                        className="flex-1 flex items-center justify-center gap-2 px-3 py-2 bg-blue-50 text-blue-600 rounded-md hover:bg-blue-100 transition-colors"
                      >
                        <Edit className="h-4 w-4" />
                        <span>Edit</span>
                      </Link>
                      <button
                        onClick={() => handleDelete(news.id)}
                        className="flex-1 flex items-center justify-center gap-2 px-3 py-2 bg-red-50 text-red-600 rounded-md hover:bg-red-100 transition-colors"
                        disabled={deleteMutation.isPending}
                      >
                        <Trash2 className="h-4 w-4" />
                        <span>Delete</span>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>

          {/* Desktop View - Table */}
          <div className="hidden lg:block bg-white rounded-lg shadow overflow-hidden">
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Image
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Title
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Author
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Language
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Category
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Status
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      AI Generated
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Date
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Views
                    </th>
                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {data?.data.map((news: News) => (
                    <tr key={news.id} className="hover:bg-gray-50">
                      <td className="px-6 py-4">
                        <div className="flex items-center justify-center">
                          {news.thumbnail_image ? (
                            <img
                              src={getImageUrl(news.thumbnail_image) || ''}
                              alt={news.title}
                              className="w-12 h-12 object-cover rounded"
                            onError={(e) => {
                              const target = e.target as HTMLImageElement;
                              target.onerror = null;
                              target.style.display = 'none';
                              target.parentElement?.classList.add('bg-gray-100', 'flex', 'items-center', 'justify-center', 'w-12', 'h-12', 'rounded');
                              const icon = document.createElement('div');
                              icon.innerHTML = '<svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>';
                              target.parentElement?.appendChild(icon);
                            }}
                            />
                          ) : (
                            <div className="w-12 h-12 bg-gray-100 rounded flex items-center justify-center">
                              <Image className="w-6 h-6 text-gray-400" />
                            </div>
                          )}
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <Link
                          to={`/news/${news.id}/edit`}
                          className="text-sm font-medium text-gray-900 hover:text-blue-600 hover:underline block"
                        >
                          {news.title}
                        </Link>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="flex items-center gap-2 text-sm text-gray-600">
                          {news.author ? (
                            <>
                              <User className="h-4 w-4 text-gray-400" />
                              <span>{news.author}</span>
                            </>
                          ) : (
                            <span className="text-gray-400">-</span>
                          )}
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        {getLanguageBadge(news.language)}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {getCategoryName(news.category)}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        {getStatusBadge(news.status)}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        {news.is_ai_generated ? (
                          <span className="px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">
                            AI
                          </span>
                        ) : (
                          <span className="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600">
                            Human
                          </span>
                        )}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className={`text-sm ${news.is_scheduled ? 'text-orange-600 font-semibold' : 'text-gray-500'}`}>
                          {formatDate(news.publish_date)}
                          {news.is_scheduled && (
                            <div className="mt-1">
                              <span className="px-2 py-1 text-xs bg-orange-100 text-orange-700 rounded">
                                Planlanmış
                              </span>
                            </div>
                          )}
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {news.views}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <Link
                          to={`/news/${news.id}/edit`}
                          className="text-blue-600 hover:text-blue-900 mr-3"
                          title="Edit"
                        >
                          <Edit className="h-4 w-4 inline" />
                        </Link>
                        <button
                          onClick={() => handleDelete(news.id)}
                          className="text-red-600 hover:text-red-900"
                          disabled={deleteMutation.isPending}
                          title="Delete"
                        >
                          <Trash2 className="h-4 w-4 inline" />
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          {/* Pagination */}
          {data && data.last_page > 1 && (
            <div className="bg-white rounded-lg shadow px-4 py-3 mt-4 sm:mt-6">
              {/* Mobile Pagination */}
              <div className="flex items-center justify-between sm:hidden">
                <button
                  onClick={() => handlePageChange(data.current_page - 1)}
                  disabled={data.current_page === 1}
                  className="relative inline-flex items-center px-3 py-2 text-sm border border-gray-300 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <ChevronLeft className="h-4 w-4 mr-1" />
                  Previous
                </button>
                <span className="text-sm text-gray-700">
                  {data.current_page} / {data.last_page}
                </span>
                <button
                  onClick={() => handlePageChange(data.current_page + 1)}
                  disabled={data.current_page === data.last_page}
                  className="relative inline-flex items-center px-3 py-2 text-sm border border-gray-300 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Next
                  <ChevronRight className="h-4 w-4 ml-1" />
                </button>
              </div>

              {/* Desktop Pagination */}
              <div className="hidden sm:flex sm:items-center sm:justify-between">
                <div>
                  <p className="text-sm text-gray-700">
                    Showing <span className="font-medium">{data.from}</span> to{' '}
                    <span className="font-medium">{data.to}</span> of{' '}
                    <span className="font-medium">{data.total}</span> results
                  </p>
                </div>
                <div>
                  <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                    <button
                      onClick={() => handlePageChange(data.current_page - 1)}
                      disabled={data.current_page === 1}
                      className="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      <ChevronLeft className="h-5 w-5" />
                    </button>
                    
                    {/* Page Numbers */}
                    {[...Array(Math.min(5, data.last_page))].map((_, idx) => {
                      let pageNum;
                      if (data.last_page <= 5) {
                        pageNum = idx + 1;
                      } else if (data.current_page <= 3) {
                        pageNum = idx + 1;
                      } else if (data.current_page >= data.last_page - 2) {
                        pageNum = data.last_page - 4 + idx;
                      } else {
                        pageNum = data.current_page - 2 + idx;
                      }
                      
                      return (
                        <button
                          key={idx}
                          onClick={() => handlePageChange(pageNum)}
                          className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium ${
                            pageNum === data.current_page
                              ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
                              : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                          }`}
                        >
                          {pageNum}
                        </button>
                      );
                    })}
                    
                    <button
                      onClick={() => handlePageChange(data.current_page + 1)}
                      disabled={data.current_page === data.last_page}
                      className="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      <ChevronRight className="h-5 w-5" />
                    </button>
                  </nav>
                </div>
              </div>
            </div>
          )}

          {/* No Results */}
          {data?.data.length === 0 && (
            <div className="bg-white rounded-lg shadow p-8 text-center">
              <p className="text-gray-500">No news items found.</p>
              <Link
                to="/news/create"
                className="inline-flex items-center mt-4 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
              >
                <Plus className="h-5 w-5 mr-2" />
                Create First News
              </Link>
            </div>
          )}
        </>
      )}
    </div>
  );
}