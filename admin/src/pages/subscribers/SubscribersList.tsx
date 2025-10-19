import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { PlusIcon, PencilIcon, TrashIcon, ArrowDownTrayIcon, ChartBarIcon } from '@heroicons/react/24/outline';
import { subscribersService, type Subscriber } from '../../services/subscribers';

export default function SubscribersList() {
  const queryClient = useQueryClient();
  const [currentPage, setCurrentPage] = useState(1);
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [languageFilter, setLanguageFilter] = useState('');
  const [showStats, setShowStats] = useState(false);

  // Fetch subscribers
  const { data: subscribersData, isLoading } = useQuery({
    queryKey: ['subscribers', currentPage, searchTerm, statusFilter, languageFilter],
    queryFn: () => subscribersService.getAll({
      page: currentPage,
      per_page: 10,
      search: searchTerm,
      status: statusFilter,
      language: languageFilter
    })
  });

  // Fetch stats
  const { data: stats } = useQuery({
    queryKey: ['subscriber-stats'],
    queryFn: () => subscribersService.getStats(),
    enabled: showStats
  });

  // Delete mutation
  const deleteMutation = useMutation({
    mutationFn: (id: number) => subscribersService.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['subscribers'] });
      queryClient.invalidateQueries({ queryKey: ['subscriber-stats'] });
    }
  });

  const handleDelete = (id: number) => {
    if (confirm('Are you sure you want to delete this subscriber?')) {
      deleteMutation.mutate(id);
    }
  };

  const handleExport = async () => {
    await subscribersService.exportToCsv({
      status: statusFilter,
      language: languageFilter
    });
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    setCurrentPage(1);
  };

  return (
    <div className="p-4 lg:p-6">
      <div className="mb-6">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-6 space-y-4 sm:space-y-0">
          <h1 className="text-xl lg:text-2xl font-bold text-gray-900">Newsletter Subscribers</h1>
          <div className="flex flex-col sm:flex-row gap-2">
            <button
              onClick={() => setShowStats(!showStats)}
              className="flex items-center justify-center px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700"
            >
              <ChartBarIcon className="h-5 w-5 mr-2" />
              {showStats ? 'Hide Stats' : 'Show Stats'}
            </button>
            <button
              onClick={handleExport}
              className="flex items-center justify-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700"
            >
              <ArrowDownTrayIcon className="h-5 w-5 mr-2" />
              Export CSV
            </button>
            <Link
              to="/subscribers/create"
              className="flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
            >
              <PlusIcon className="h-5 w-5 mr-2" />
              Add Subscriber
            </Link>
          </div>
        </div>

        {/* Statistics */}
        {showStats && stats && (
          <div className="bg-gray-50 p-4 rounded-lg mb-4">
            <h2 className="text-lg font-semibold mb-3">Statistics</h2>
            <div className="grid grid-cols-2 lg:grid-cols-5 gap-4">
              <div>
                <p className="text-sm text-gray-600">Total</p>
                <p className="text-2xl font-bold">{stats.total}</p>
              </div>
              <div>
                <p className="text-sm text-gray-600">Active</p>
                <p className="text-2xl font-bold text-green-600">{stats.active}</p>
              </div>
              <div>
                <p className="text-sm text-gray-600">Unsubscribed</p>
                <p className="text-2xl font-bold text-red-600">{stats.unsubscribed}</p>
              </div>
              <div>
                <p className="text-sm text-gray-600">Last 30 Days</p>
                <p className="text-2xl font-bold text-blue-600">{stats.recent_30_days}</p>
              </div>
              <div className="col-span-2 lg:col-span-1">
                <p className="text-sm text-gray-600">By Language</p>
                <div className="flex gap-2 text-sm">
                  <span>AZ: {stats.by_language?.az || 0}</span>
                  <span>EN: {stats.by_language?.en || 0}</span>
                  <span>RU: {stats.by_language?.ru || 0}</span>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Filters */}
        <form onSubmit={handleSearch} className="space-y-4 lg:space-y-0 lg:flex lg:gap-2 mb-4">
          <input
            type="text"
            placeholder="Search by email..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full lg:flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          <select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
            className="w-full lg:w-auto px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="unsubscribed">Unsubscribed</option>
          </select>
          <select
            value={languageFilter}
            onChange={(e) => setLanguageFilter(e.target.value)}
            className="w-full lg:w-auto px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">All Languages</option>
            <option value="az">Azerbaijani</option>
            <option value="en">English</option>
            <option value="ru">Russian</option>
          </select>
          <button
            type="submit"
            className="w-full lg:w-auto px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
          >
            Search
          </button>
        </form>
      </div>

      {/* Content */}
      {isLoading ? (
        <div className="flex justify-center py-8">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        </div>
      ) : (
        <>
          {subscribersData?.data?.length === 0 ? (
            <div className="bg-white rounded-lg shadow p-6 lg:p-8 text-center">
              <div className="text-gray-500">
                <p className="text-lg mb-2">No subscribers found</p>
                <p className="text-sm">Add your first subscriber!</p>
              </div>
            </div>
          ) : (
            <>
              {/* Mobile Card Layout */}
              <div className="block lg:hidden space-y-4">
                {subscribersData?.data?.map((subscriber: Subscriber) => (
                  <div
                    key={subscriber.id}
                    className="bg-white rounded-lg shadow-sm border border-gray-200 p-4"
                  >
                    <div className="flex items-start justify-between">
                      <div className="flex-1 min-w-0">
                        <h3 className="font-medium text-gray-900 truncate">
                          {subscriber.email}
                        </h3>
                        <div className="mt-2 space-y-2">
                          <div className="flex items-center space-x-2">
                            <span className="px-2 py-0.5 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                              {subscriber.language.toUpperCase()}
                            </span>
                            <span className={`px-2 py-0.5 text-xs font-semibold rounded-full ${
                              subscriber.status === 'active' 
                                ? 'bg-green-100 text-green-800' 
                                : 'bg-red-100 text-red-800'
                            }`}>
                              {subscriber.status}
                            </span>
                          </div>
                          <div className="text-xs text-gray-500">
                            <div className="flex items-center">
                              <span className="font-medium">Subscribed:</span>
                              <span className="ml-1">
                                {subscriber.subscribed_at ? new Date(subscriber.subscribed_at).toLocaleDateString() : '-'}
                              </span>
                            </div>
                            {subscriber.ip_address && (
                              <div className="flex items-center mt-1">
                                <span className="font-medium">IP:</span>
                                <span className="ml-1">{subscriber.ip_address}</span>
                              </div>
                            )}
                          </div>
                        </div>
                      </div>
                    </div>

                    <div className="mt-4 flex space-x-2">
                      <Link
                        to={`/subscribers/${subscriber.id}/edit`}
                        className="flex items-center justify-center space-x-1 px-3 py-2 text-blue-600 hover:bg-blue-50 rounded-md text-sm flex-1 border border-blue-200"
                      >
                        <PencilIcon className="h-4 w-4" />
                        <span>Edit</span>
                      </Link>
                      <button
                        onClick={() => handleDelete(subscriber.id)}
                        className="flex items-center justify-center space-x-1 px-3 py-2 text-red-600 hover:bg-red-50 rounded-md text-sm flex-1 border border-red-200"
                      >
                        <TrashIcon className="h-4 w-4" />
                        <span>Delete</span>
                      </button>
                    </div>
                  </div>
                ))}
              </div>

              {/* Desktop Table Layout */}
              <div className="hidden lg:block bg-white shadow-sm rounded-lg overflow-hidden">
                <table className="min-w-full divide-y divide-gray-200">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Email
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Language
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Status
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Subscribed At
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        IP Address
                      </th>
                      <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                    {subscribersData?.data?.map((subscriber: Subscriber) => (
                      <tr key={subscriber.id}>
                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                          {subscriber.email}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                            {subscriber.language.toUpperCase()}
                          </span>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                            subscriber.status === 'active' 
                              ? 'bg-green-100 text-green-800' 
                              : 'bg-red-100 text-red-800'
                          }`}>
                            {subscriber.status}
                          </span>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          {subscriber.subscribed_at ? new Date(subscriber.subscribed_at).toLocaleDateString() : '-'}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          {subscriber.ip_address || '-'}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                          <Link
                            to={`/subscribers/${subscriber.id}/edit`}
                            className="text-blue-600 hover:text-blue-900 mr-3"
                          >
                            <PencilIcon className="h-5 w-5 inline" />
                          </Link>
                          <button
                            onClick={() => handleDelete(subscriber.id)}
                            className="text-red-600 hover:text-red-900"
                          >
                            <TrashIcon className="h-5 w-5 inline" />
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </>
          )}

          {/* Pagination */}
          {subscribersData?.last_page > 1 && (
            <div className="mt-6 flex justify-center">
              <nav className="flex gap-2 flex-wrap">
                {Array.from({ length: subscribersData.last_page }, (_, i) => i + 1).map(page => (
                  <button
                    key={page}
                    onClick={() => setCurrentPage(page)}
                    className={`px-3 py-1 rounded ${
                      currentPage === page
                        ? 'bg-blue-600 text-white'
                        : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                    }`}
                  >
                    {page}
                  </button>
                ))}
              </nav>
            </div>
          )}
        </>
      )}
    </div>
  );
}