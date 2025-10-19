import React from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { ChevronLeft, Plus, Edit2, Trash2, Building2, CreditCard, Shield, Package } from 'lucide-react';
import { companiesEavApi } from '../../services/companies-eav';

const CompanyEntities: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const queryClient = useQueryClient();
  const companyId = Number(id);

  // Fetch company data
  const { data: companyData, isLoading: isLoadingCompany } = useQuery({
    queryKey: ['company', companyId],
    queryFn: () => companiesEavApi.getCompany(companyId),
  });

  // Fetch entities
  const { data: entitiesData, isLoading: isLoadingEntities } = useQuery({
    queryKey: ['company-entities', companyId],
    queryFn: () => companiesEavApi.getEntities(companyId),
  });

  // Delete mutation
  const deleteMutation = useMutation({
    mutationFn: (entityId: number) => companiesEavApi.deleteEntity(companyId, entityId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['company-entities', companyId] });
    },
  });

  const handleDelete = (entityId: number, entityName: string) => {
    if (window.confirm(`Are you sure you want to delete "${entityName}"?`)) {
      deleteMutation.mutate(entityId);
    }
  };

  const getEntityIcon = (entityType: string) => {
    switch (entityType) {
      case 'branch':
        return <Building2 className="w-5 h-5" />;
      case 'credit_card':
        return <CreditCard className="w-5 h-5" />;
      case 'deposit':
        return <Package className="w-5 h-5" />;
      case 'insurance_product':
        return <Shield className="w-5 h-5" />;
      default:
        return <Package className="w-5 h-5" />;
    }
  };

  const getEntityTypeLabel = (entityType: string) => {
    switch (entityType) {
      case 'branch':
        return 'Branches';
      case 'credit_card':
        return 'Credit Cards';
      case 'deposit':
        return 'Deposits';
      case 'insurance_product':
        return 'Insurance Products';
      default:
        return entityType;
    }
  };

  if (isLoadingCompany || isLoadingEntities) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  const company = companyData?.data;
  const entities = entitiesData?.data || {};

  return (
    <div className="max-w-7xl mx-auto">
      {/* Header */}
      <div className="mb-6">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-4">
            <Link
              to="/companies-eav"
              className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
            >
              <ChevronLeft className="w-5 h-5" />
            </Link>
            <div>
              <h1 className="text-2xl font-bold text-gray-900">
                {company?.name} - Entities
              </h1>
              <p className="text-sm text-gray-500 mt-1">
                Manage branches, products, and services
              </p>
            </div>
          </div>
        </div>

        {/* Quick Actions */}
        <div className="mt-4 flex flex-wrap gap-2">
          <Link
            to={`/companies-eav/${companyId}/entities/branch/create`}
            className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center space-x-2"
          >
            <Plus className="w-4 h-4" />
            <span>Add Branch</span>
          </Link>
          {company?.company_type?.type_name === 'bank' && (
            <>
              <Link
                to={`/companies-eav/${companyId}/entities/credit_card/create`}
                className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center space-x-2"
              >
                <Plus className="w-4 h-4" />
                <span>Add Credit Card</span>
              </Link>
              <Link
                to={`/companies-eav/${companyId}/entities/deposit/create`}
                className="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center space-x-2"
              >
                <Plus className="w-4 h-4" />
                <span>Add Deposit</span>
              </Link>
            </>
          )}
          {company?.company_type?.type_name === 'insurance' && (
            <Link
              to={`/companies-eav/${companyId}/entities/insurance_product/create`}
              className="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors flex items-center space-x-2"
            >
              <Plus className="w-4 h-4" />
              <span>Add Insurance Product</span>
            </Link>
          )}
        </div>
      </div>

      {/* Entity Sections */}
      <div className="space-y-6">
        {Object.entries(entities).map(([entityType, entityList]: [string, any]) => (
          <div key={entityType} className="bg-white rounded-lg shadow-sm border border-gray-200">
            <div className="px-6 py-4 border-b border-gray-200">
              <div className="flex items-center space-x-2">
                {getEntityIcon(entityType)}
                <h2 className="text-lg font-semibold text-gray-900">
                  {getEntityTypeLabel(entityType)}
                </h2>
                <span className="text-sm text-gray-500">
                  ({Array.isArray(entityList) ? entityList.length : 0})
                </span>
              </div>
            </div>

            {Array.isArray(entityList) && entityList.length > 0 ? (
              <div className="divide-y divide-gray-200">
                {entityList.map((entity: any) => (
                  <div key={entity.id} className="px-6 py-4 hover:bg-gray-50">
                    <div className="flex items-center justify-between">
                      <div className="flex-1">
                        <h3 className="text-base font-medium text-gray-900">
                          {entity.name || 'Unnamed Entity'}
                        </h3>
                        {entity.attributes && (
                          <div className="mt-2 grid grid-cols-2 gap-2 text-sm text-gray-600">
                            {Object.entries(entity.attributes).slice(0, 4).map(([key, value]: [string, any]) => {
                              let displayValue = value;
                              if (typeof value === 'object' && value !== null) {
                                if (value.en || value.az || value.ru) {
                                  displayValue = value.en || value.az || value.ru;
                                } else {
                                  displayValue = JSON.stringify(value);
                                }
                              }
                              return (
                                <div key={key}>
                                  <span className="font-medium">{key.replace(/_/g, ' ')}:</span>{' '}
                                  <span>{String(displayValue)}</span>
                                </div>
                              );
                            })}
                          </div>
                        )}
                        <div className="mt-2 flex items-center space-x-4 text-xs text-gray-500">
                          {entity.code && <span>Code: {entity.code}</span>}
                          <span
                            className={`px-2 py-1 rounded-full ${
                              entity.is_active
                                ? 'bg-green-100 text-green-800'
                                : 'bg-gray-100 text-gray-800'
                            }`}
                          >
                            {entity.is_active ? 'Active' : 'Inactive'}
                          </span>
                        </div>
                      </div>

                      <div className="flex items-center space-x-2">
                        <Link
                          to={`/companies-eav/${companyId}/entities/${entityType}/${entity.id}/edit`}
                          className="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                          title="Edit"
                        >
                          <Edit2 className="w-4 h-4" />
                        </Link>
                        <button
                          onClick={() => handleDelete(entity.id, entity.name)}
                          className="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                          title="Delete"
                          disabled={deleteMutation.isPending}
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="px-6 py-8 text-center text-gray-500">
                <p>No {getEntityTypeLabel(entityType).toLowerCase()} found</p>
                <Link
                  to={`/companies-eav/${companyId}/entities/${entityType}/create`}
                  className="mt-2 text-blue-600 hover:text-blue-700 text-sm inline-flex items-center space-x-1"
                >
                  <Plus className="w-4 h-4" />
                  <span>Add first {getEntityTypeLabel(entityType).toLowerCase().slice(0, -1)}</span>
                </Link>
              </div>
            )}
          </div>
        ))}
      </div>

      {/* Empty State */}
      {Object.keys(entities).length === 0 && (
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 px-6 py-12">
          <div className="text-center">
            <Package className="w-12 h-12 text-gray-400 mx-auto mb-4" />
            <h3 className="text-lg font-medium text-gray-900 mb-2">No entities yet</h3>
            <p className="text-gray-500 mb-6">
              Start by adding branches, products, or services for this company
            </p>
            <Link
              to={`/companies-eav/${companyId}/entities/branch/create`}
              className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors inline-flex items-center space-x-2"
            >
              <Plus className="w-4 h-4" />
              <span>Add First Entity</span>
            </Link>
          </div>
        </div>
      )}
    </div>
  );
};

export default CompanyEntities;