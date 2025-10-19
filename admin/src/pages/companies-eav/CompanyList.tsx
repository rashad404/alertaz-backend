import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { API_CONFIG } from '../../config/api';
import {
  PlusIcon,
  PencilIcon,
  TrashIcon,
  EyeIcon,
  BuildingOfficeIcon,
  MagnifyingGlassIcon,
  FunnelIcon,
  ArrowsUpDownIcon,
  ChevronUpIcon,
  ChevronDownIcon,
} from '@heroicons/react/24/outline';
import { companiesEavApi } from '../../services/companies-eav';
import { toast } from 'react-hot-toast';

interface Company {
  id: number;
  name: string | { [key: string]: string };
  slug: string;
  logo?: string;
  company_type_id: number;
  companyType?: {
    id: number;
    type_name: string;
    description?: string;
  };
  is_active: boolean;
  display_order: number;
  created_at?: string;
  updated_at?: string;
  eav_attributes?: Record<string, {
    value: any;
    name: string;
    data_type: string;
    is_required: boolean;
    group?: string;
  }>;
  entity_counts?: {
    branches?: number;
    deposits?: number;
    credit_cards?: number;
    insurance_products?: number;
    total?: number;
  };
}

export default function CompanyList() {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedType, setSelectedType] = useState<string>('all');
  const [sortBy, setSortBy] = useState<'name' | 'display_order' | 'created_at'>('display_order');
  const [sortOrder, setSortOrder] = useState<'asc' | 'desc'>('asc');
  const [currentPage, setCurrentPage] = useState(1);
  const [expandedRows, setExpandedRows] = useState<Set<number>>(new Set());

  // Fetch company types
  const { data: companyTypes } = useQuery({
    queryKey: ['company-types'],
    queryFn: companiesEavApi.getTypes,
  });

  // Fetch companies
  const { data: companiesData, isLoading, error } = useQuery({
    queryKey: ['companies-eav', currentPage, searchTerm, selectedType, sortBy, sortOrder],
    queryFn: () => companiesEavApi.getCompanies({
      page: currentPage,
      search: searchTerm,
      type_id: selectedType !== 'all' ? Number(selectedType) : undefined,
      sort_by: sortBy,
      sort_order: sortOrder,
    }),
  });

  // Delete mutation
  const deleteMutation = useMutation({
    mutationFn: (id: number) => companiesEavApi.deleteCompany(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['companies-eav'] });
      toast.success('Company deleted successfully');
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Failed to delete company');
    },
  });

  // Toggle active status mutation
  const toggleActiveMutation = useMutation({
    mutationFn: ({ id, is_active }: { id: number; is_active: boolean }) =>
      companiesEavApi.updateCompany(id, { is_active }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['companies-eav'] });
      toast.success('Status updated successfully');
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Failed to update status');
    },
  });

  const handleDelete = (id: number, name: string | { [key: string]: string }) => {
    const displayName = typeof name === 'string' ? name : (name?.en || name?.az || 'this company');
    if (confirm(`Are you sure you want to delete "${displayName}"?`)) {
      deleteMutation.mutate(id);
    }
  };

  const toggleRowExpansion = (id: number) => {
    setExpandedRows(prev => {
      const newSet = new Set(prev);
      if (newSet.has(id)) {
        newSet.delete(id);
      } else {
        newSet.add(id);
      }
      return newSet;
    });
  };

  const getTypeColor = (typeName: string) => {
    const colors: Record<string, string> = {
      bank: 'bg-blue-100 text-blue-800',
      insurance: 'bg-green-100 text-green-800',
      credit_organization: 'bg-yellow-100 text-yellow-800',
      investment: 'bg-purple-100 text-purple-800',
      leasing: 'bg-orange-100 text-orange-800',
      payment_system: 'bg-pink-100 text-pink-800',
    };
    return colors[typeName] || 'bg-gray-100 text-gray-800';
  };

  if (error) {
    return (
      <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
        Error loading companies: {(error as any).message}
      </div>
    );
  }

  const companies = companiesData?.data?.data || [];
  const pagination = companiesData?.data || { total: 0, per_page: 20, current_page: 1 };
  const totalPages = Math.ceil(pagination.total / pagination.per_page);

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Companies Management</h1>
          <p className="text-gray-600 mt-1">Manage companies with EAV attributes and entities</p>
        </div>
        <div className="flex gap-3">
          <Link
            to="/companies-eav/create"
            className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700"
          >
            <PlusIcon className="h-5 w-5 mr-2" />
            Add Company
          </Link>
        </div>
      </div>

      {/* Filters and Search */}
      <div className="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          {/* Search */}
          <div className="relative">
            <MagnifyingGlassIcon className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400" />
            <input
              type="text"
              placeholder="Search companies..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="pl-10 pr-3 py-2 border border-gray-300 rounded-md w-full focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>

          {/* Type Filter */}
          <div className="relative">
            <FunnelIcon className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400" />
            <select
              value={selectedType}
              onChange={(e) => setSelectedType(e.target.value)}
              className="pl-10 pr-3 py-2 border border-gray-300 rounded-md w-full focus:outline-none focus:ring-2 focus:ring-indigo-500 appearance-none"
            >
              <option value="all">All Types</option>
              {companyTypes?.data?.map((type: any) => {
                // Handle type_name whether it's a string or JSON
                let displayName = type.type_name;
                if (typeof type.type_name === 'string' && type.type_name.startsWith('{')) {
                  try {
                    const parsed = JSON.parse(type.type_name);
                    displayName = parsed.en || parsed.az || parsed.ru || type.type_name;
                  } catch {
                    // If parsing fails, use as is
                  }
                } else if (typeof type.type_name === 'object' && type.type_name !== null) {
                  const typeNameObj = type.type_name as { en?: string; az?: string; ru?: string };
                  displayName = typeNameObj.en || typeNameObj.az || typeNameObj.ru || 'Unknown';
                }
                return (
                  <option key={type.id} value={type.id}>
                    {displayName}
                  </option>
                );
              })}
            </select>
          </div>

          {/* Sort By */}
          <div className="relative">
            <ArrowsUpDownIcon className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400" />
            <select
              value={sortBy}
              onChange={(e) => setSortBy(e.target.value as any)}
              className="pl-10 pr-3 py-2 border border-gray-300 rounded-md w-full focus:outline-none focus:ring-2 focus:ring-indigo-500 appearance-none"
            >
              <option value="display_order">Display Order</option>
              <option value="name">Name</option>
              <option value="created_at">Date Added</option>
            </select>
          </div>

          {/* Sort Order */}
          <button
            onClick={() => setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc')}
            className="flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50"
          >
            {sortOrder === 'asc' ? (
              <>
                <ChevronUpIcon className="h-5 w-5 mr-2" />
                Ascending
              </>
            ) : (
              <>
                <ChevronDownIcon className="h-5 w-5 mr-2" />
                Descending
              </>
            )}
          </button>
        </div>
      </div>

      {/* Companies Table */}
      <div className="bg-white shadow-sm rounded-lg overflow-hidden">
        {isLoading ? (
          <div className="p-8 text-center">
            <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
            <p className="mt-2 text-gray-600">Loading companies...</p>
          </div>
        ) : companies.length === 0 ? (
          <div className="p-8 text-center">
            <BuildingOfficeIcon className="mx-auto h-12 w-12 text-gray-400" />
            <p className="mt-2 text-gray-600">No companies found</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Order
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Company
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Type
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Contact
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Entities
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
                {companies.map((company: Company) => (
                  <React.Fragment key={company.id}>
                    <tr className="hover:bg-gray-50">
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        #{company.display_order}
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex items-center">
                          {company.logo && (
                            <img
                              src={API_CONFIG.getImageUrl(company.logo)}
                              alt={typeof company.name === 'string' ? company.name : (company.name?.en || '')}
                              className="h-10 w-10 rounded-full mr-3 object-cover"
                            />
                          )}
                          <div>
                            <div className="text-sm font-medium text-gray-900">
                              {typeof company.name === 'string' ? company.name : (company.name?.en || company.name?.az || 'Unnamed')}
                            </div>
                            <div className="text-sm text-gray-500">{company.slug}</div>
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        {(() => {
                          let displayName = 'Unknown';
                          let typeName = company.companyType?.type_name;

                          if (typeName) {
                            if (typeof typeName === 'string' && typeName.startsWith('{')) {
                              try {
                                const parsed = JSON.parse(typeName);
                                displayName = parsed.en || parsed.az || parsed.ru || typeName;
                              } catch {
                                displayName = typeName;
                              }
                            } else if (typeof typeName === 'object' && typeName !== null) {
                              const typeObj = typeName as { en?: string; az?: string; ru?: string };
                              displayName = typeObj.en || typeObj.az || typeObj.ru || 'Unknown';
                            } else {
                              displayName = typeName;
                            }
                          }

                          // Extract base type for color (remove JSON, get first word lowercase)
                          const baseType = displayName.toLowerCase().split(' ')[0];

                          return (
                            <span className={`px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${getTypeColor(baseType)}`}>
                              {displayName}
                            </span>
                          );
                        })()}
                      </td>
                      <td className="px-6 py-4">
                        <div className="text-sm text-gray-900">
                          {company.eav_attributes?.email?.value && <div>{company.eav_attributes.email.value}</div>}
                          {company.eav_attributes?.phone?.value && <div>{company.eav_attributes.phone.value}</div>}
                          {company.eav_attributes?.website?.value && (
                            <a href={company.eav_attributes.website.value} target="_blank" rel="noopener noreferrer" className="text-indigo-600 hover:text-indigo-900">
                              Website
                            </a>
                          )}
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <button
                          onClick={() => toggleRowExpansion(company.id)}
                          className="text-sm text-indigo-600 hover:text-indigo-900"
                        >
                          {company.entity_counts?.total || 0} entities
                          {expandedRows.has(company.id) ? ' ▼' : ' ▶'}
                        </button>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <button
                          onClick={() => toggleActiveMutation.mutate({ id: company.id, is_active: !company.is_active })}
                          className={`px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full cursor-pointer ${
                            company.is_active
                              ? 'bg-green-100 text-green-800 hover:bg-green-200'
                              : 'bg-red-100 text-red-800 hover:bg-red-200'
                          }`}
                        >
                          {company.is_active ? 'Active' : 'Inactive'}
                        </button>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <div className="flex justify-end gap-2">
                          <button
                            onClick={() => navigate(`/companies-eav/${company.id}/view`)}
                            className="text-gray-600 hover:text-gray-900"
                            title="View"
                          >
                            <EyeIcon className="h-5 w-5" />
                          </button>
                          <button
                            onClick={() => navigate(`/companies-eav/${company.id}/edit`)}
                            className="text-indigo-600 hover:text-indigo-900"
                            title="Edit"
                          >
                            <PencilIcon className="h-5 w-5" />
                          </button>
                          <button
                            onClick={() => handleDelete(company.id, company.name)}
                            className="text-red-600 hover:text-red-900"
                            title="Delete"
                          >
                            <TrashIcon className="h-5 w-5" />
                          </button>
                        </div>
                      </td>
                    </tr>
                    {expandedRows.has(company.id) && (
                      <tr>
                        <td colSpan={7} className="px-6 py-4 bg-gray-50">
                          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                            {company.entity_counts?.branches && (
                              <div className="text-sm">
                                <span className="font-medium">Branches:</span> {company.entity_counts.branches}
                              </div>
                            )}
                            {company.entity_counts?.deposits && (
                              <div className="text-sm">
                                <span className="font-medium">Deposits:</span> {company.entity_counts.deposits}
                              </div>
                            )}
                            {company.entity_counts?.credit_cards && (
                              <div className="text-sm">
                                <span className="font-medium">Credit Cards:</span> {company.entity_counts.credit_cards}
                              </div>
                            )}
                            {company.entity_counts?.insurance_products && (
                              <div className="text-sm">
                                <span className="font-medium">Insurance:</span> {company.entity_counts.insurance_products}
                              </div>
                            )}
                            <div className="col-span-2">
                              <Link
                                to={`/companies-eav/${company.id}/entities`}
                                className="text-indigo-600 hover:text-indigo-900 text-sm"
                              >
                                Manage Entities →
                              </Link>
                            </div>
                          </div>
                        </td>
                      </tr>
                    )}
                  </React.Fragment>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination */}
        {totalPages > 1 && (
          <div className="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
            <div className="flex items-center justify-between">
              <div className="text-sm text-gray-700">
                Showing page {currentPage} of {totalPages} ({pagination.total} total)
              </div>
              <div className="flex gap-2">
                <button
                  onClick={() => setCurrentPage(currentPage - 1)}
                  disabled={currentPage === 1}
                  className="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Previous
                </button>
                {Array.from({ length: Math.min(5, totalPages) }, (_, i) => {
                  const page = i + 1;
                  return (
                    <button
                      key={page}
                      onClick={() => setCurrentPage(page)}
                      className={`px-3 py-1 border rounded-md text-sm font-medium ${
                        currentPage === page
                          ? 'border-indigo-500 bg-indigo-50 text-indigo-600'
                          : 'border-gray-300 text-gray-700 bg-white hover:bg-gray-50'
                      }`}
                    >
                      {page}
                    </button>
                  );
                })}
                <button
                  onClick={() => setCurrentPage(currentPage + 1)}
                  disabled={currentPage === totalPages}
                  className="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Next
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}