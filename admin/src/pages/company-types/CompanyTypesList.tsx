import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { Plus, Edit, Trash2, Search, Hash, Layers, Globe, Image } from 'lucide-react';
import { companyTypesService } from '../../services/companyTypes';
import type { CompanyType } from '../../services/companyTypes';
import { API_CONFIG } from '../../config/api';

export default function CompanyTypesList() {
  const queryClient = useQueryClient();
  const [searchTerm, setSearchTerm] = useState('');

  const { data: companyTypes = [], isLoading } = useQuery({
    queryKey: ['company-types'],
    queryFn: () => companyTypesService.getAll(),
  });

  const deleteMutation = useMutation({
    mutationFn: companyTypesService.delete,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['company-types'] });
    },
  });

  const handleDelete = async (id: number) => {
    if (confirm('Are you sure you want to delete this company type? This may affect associated companies.')) {
      deleteMutation.mutate(id);
    }
  };

  const getStatusBadge = (status: number) => {
    return status === 1 ? (
      <span className="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
        Active
      </span>
    ) : (
      <span className="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
        Inactive
      </span>
    );
  };

  const getTitle = (title: string | { az: string; en: string; ru: string }) => {
    if (typeof title === 'object') {
      return title.en || title.az || title.ru || 'Untitled';
    }
    return title || 'Untitled';
  };

  const filteredTypes = companyTypes.filter((type: CompanyType) => {
    const title = getTitle(type.title);
    return title.toLowerCase().includes(searchTerm.toLowerCase()) ||
           type.slug?.toLowerCase().includes(searchTerm.toLowerCase());
  });

  return (
    <div className="px-4 sm:px-6 lg:px-8">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-6 space-y-4 sm:space-y-0">
        <h1 className="text-xl sm:text-2xl font-bold text-gray-900">Company Types</h1>
        <Link
          to="/company-types/create"
          className="inline-flex items-center justify-center px-3 py-2 sm:px-4 bg-blue-600 text-white text-sm sm:text-base rounded-md hover:bg-blue-700 transition-colors"
        >
          <Plus className="h-4 w-4 sm:h-5 sm:w-5 mr-1 sm:mr-2" />
          <span>Add Company Type</span>
        </Link>
      </div>

      {/* Search */}
      <div className="bg-white rounded-lg shadow mb-4 sm:mb-6 p-3 sm:p-4">
        <div className="relative">
          <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4 sm:h-5 sm:w-5" />
          <input
            type="text"
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            placeholder="Search company types..."
            className="w-full pl-9 sm:pl-10 pr-3 py-2 text-sm sm:text-base border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>
      </div>

      {/* Content */}
      {isLoading ? (
        <div className="bg-white rounded-lg shadow p-8 text-center">
          <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        </div>
      ) : (
        <>
          {/* Mobile View - Cards */}
          <div className="block lg:hidden space-y-4">
            {filteredTypes.length === 0 ? (
              <div className="bg-white rounded-lg shadow p-8 text-center">
                <p className="text-gray-500">No company types found</p>
              </div>
            ) : (
              filteredTypes.map((type: CompanyType) => (
                <div key={type.id} className="bg-white rounded-lg shadow p-4">
                  {/* Type Info */}
                  <div className="flex justify-between items-start mb-3">
                    <div className="flex-1">
                      <div className="flex items-center gap-2">
                        {type.icon && (
                          <img
                            src={API_CONFIG.getImageUrl(type.icon)}
                            alt={getTitle(type.icon_alt_text || '')}
                            className="w-8 h-8 object-contain"
                          />
                        )}
                        <h3 className="text-base font-semibold text-gray-900">
                          {getTitle(type.title)}
                        </h3>
                      </div>
                      {typeof type.title === 'object' && (
                        <div className="mt-1 space-y-1">
                          <div className="flex items-center gap-1 text-xs text-gray-500">
                            <Globe className="h-3 w-3" />
                            <span>AZ: {type.title.az}</span>
                          </div>
                          <div className="flex items-center gap-1 text-xs text-gray-500">
                            <Globe className="h-3 w-3" />
                            <span>RU: {type.title.ru}</span>
                          </div>
                        </div>
                      )}
                    </div>
                    {getStatusBadge(type.status)}
                  </div>

                  {/* Meta Information */}
                  <div className="flex items-center gap-4 text-sm text-gray-600 mb-3">
                    <span className="flex items-center gap-1">
                      <Hash className="h-3 w-3" />
                      {type.slug}
                    </span>
                    <span className="flex items-center gap-1">
                      <Layers className="h-3 w-3" />
                      Order: {type.order}
                    </span>
                  </div>

                  {/* Actions */}
                  <div className="flex gap-2 pt-3 border-t">
                    <Link
                      to={`/company-types/${type.id}/edit`}
                      className="flex-1 flex items-center justify-center gap-2 px-3 py-2 bg-blue-50 text-blue-600 rounded-md hover:bg-blue-100 transition-colors"
                    >
                      <Edit className="h-4 w-4" />
                      <span>Edit</span>
                    </Link>
                    <button
                      onClick={() => handleDelete(type.id)}
                      className="flex-1 flex items-center justify-center gap-2 px-3 py-2 bg-red-50 text-red-600 rounded-md hover:bg-red-100 transition-colors"
                    >
                      <Trash2 className="h-4 w-4" />
                      <span>Delete</span>
                    </button>
                  </div>
                </div>
              ))
            )}
          </div>

          {/* Desktop View - Table */}
          <div className="hidden lg:block bg-white rounded-lg shadow overflow-hidden">
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Order
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Icon
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Title
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Slug
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Status
                    </th>
                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {filteredTypes.length === 0 ? (
                    <tr>
                      <td colSpan={6} className="px-6 py-12 text-center text-gray-500">
                        No company types found
                      </td>
                    </tr>
                  ) : (
                    filteredTypes.map((type: CompanyType) => (
                      <tr key={type.id} className="hover:bg-gray-50">
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                          {type.order}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          {type.icon ? (
                            <img
                              src={API_CONFIG.getImageUrl(type.icon)}
                              alt={getTitle(type.icon_alt_text || '')}
                              className="w-10 h-10 object-contain"
                            />
                          ) : (
                            <div className="w-10 h-10 bg-gray-100 rounded flex items-center justify-center">
                              <Image className="h-5 w-5 text-gray-400" />
                            </div>
                          )}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div>
                            <div className="text-sm font-medium text-gray-900">
                              {getTitle(type.title)}
                            </div>
                            {typeof type.title === 'object' && (
                              <div className="text-xs text-gray-500 mt-1">
                                AZ: {type.title.az} | RU: {type.title.ru}
                              </div>
                            )}
                          </div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                          {type.slug}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          {getStatusBadge(type.status)}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                          <Link
                            to={`/company-types/${type.id}/edit`}
                            className="text-blue-600 hover:text-blue-900 mr-3"
                            title="Edit"
                          >
                            <Edit className="inline h-4 w-4" />
                          </Link>
                          <button
                            onClick={() => handleDelete(type.id)}
                            className="text-red-600 hover:text-red-900"
                            title="Delete"
                          >
                            <Trash2 className="inline h-4 w-4" />
                          </button>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>

          {/* No Results with Add Button */}
          {filteredTypes.length === 0 && !searchTerm && (
            <div className="bg-white rounded-lg shadow p-8 text-center mt-4">
              <p className="text-gray-500">No company types created yet.</p>
              <Link
                to="/company-types/create"
                className="inline-flex items-center mt-4 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
              >
                <Plus className="h-5 w-5 mr-2" />
                Create First Company Type
              </Link>
            </div>
          )}
        </>
      )}
    </div>
  );
}