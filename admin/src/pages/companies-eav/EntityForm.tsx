import React, { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { ChevronLeft, Save } from 'lucide-react';
import { companiesEavApi } from '../../services/companies-eav';

interface EntityAttribute {
  key: string;
  name: string;
  data_type: string;
  is_required: boolean;
  is_translatable: boolean;
  default_value?: any;
}

const EntityForm: React.FC = () => {
  const { companyId, entityType, entityId } = useParams<{ 
    companyId: string; 
    entityType: string;
    entityId?: string;
  }>();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const isEditMode = !!entityId;
  const companyIdNum = Number(companyId);
  const entityIdNum = entityId ? Number(entityId) : undefined;

  const [formData, setFormData] = useState<any>({
    entity_type: entityType,
    name: '',
    code: '',
    is_active: true,
    display_order: 0,
    attributes: {},
  });

  const [activeLang, setActiveLang] = useState<'en' | 'az' | 'ru'>('en');
  const [entityAttributes, setEntityAttributes] = useState<EntityAttribute[]>([]);

  // Fetch company data
  const { data: companyData } = useQuery({
    queryKey: ['company', companyIdNum],
    queryFn: () => companiesEavApi.getCompany(companyIdNum),
  });

  // Fetch entity data if editing
  const { data: entityData, isLoading: isLoadingEntity } = useQuery({
    queryKey: ['entity', companyIdNum, entityIdNum],
    queryFn: () => entityIdNum ? companiesEavApi.getEntity(companyIdNum, entityIdNum) : null,
    enabled: isEditMode && !!entityIdNum,
  });

  // Define entity type attributes
  useEffect(() => {
    const attributes = getEntityTypeAttributes(entityType || '');
    setEntityAttributes(attributes);
  }, [entityType]);

  // Load entity data when editing
  useEffect(() => {
    if (entityData?.data) {
      const entity = entityData.data;
      setFormData({
        entity_type: entity.entity_type || entityType,
        name: entity.name || '',
        code: entity.code || '',
        is_active: entity.is_active !== undefined ? entity.is_active : true,
        display_order: entity.display_order || 0,
        attributes: entity.attributes || {},
      });
    }
  }, [entityData, entityType]);

  // Create/Update mutation
  const mutation = useMutation({
    mutationFn: async (data: any) => {
      if (isEditMode && entityIdNum) {
        return companiesEavApi.updateEntity(companyIdNum, entityIdNum, data);
      }
      return companiesEavApi.createEntity(companyIdNum, data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['company-entities', companyIdNum] });
      navigate(`/companies-eav/${companyId}/entities`);
    },
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    mutation.mutate(formData);
  };

  const handleAttributeChange = (key: string, value: any) => {
    setFormData((prev: any) => ({
      ...prev,
      attributes: {
        ...prev.attributes,
        [key]: value,
      },
    }));
  };

  const handleTranslatableChange = (key: string, value: string) => {
    const existingValue = formData.attributes[key] || {};
    const newValue = typeof existingValue === 'object' && existingValue !== null
      ? { ...existingValue, [activeLang]: value }
      : { [activeLang]: value };
    handleAttributeChange(key, newValue);
  };

  const getAttributeValue = (key: string, isTranslatable: boolean) => {
    const value = formData.attributes[key];
    
    if (value === undefined || value === null) return '';
    
    if (isTranslatable && typeof value === 'object' && value !== null) {
      return value[activeLang] || '';
    }
    
    return value;
  };

  const renderAttributeField = (attr: EntityAttribute) => {
    const value = getAttributeValue(attr.key, attr.is_translatable);
    
    switch (attr.data_type) {
      case 'string':
      case 'text':
        return attr.is_translatable ? (
          <input
            type="text"
            value={value}
            onChange={(e) => handleTranslatableChange(attr.key, e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            required={attr.is_required}
            placeholder={`${attr.name} (${activeLang.toUpperCase()})`}
          />
        ) : (
          <input
            type="text"
            value={value}
            onChange={(e) => handleAttributeChange(attr.key, e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            required={attr.is_required}
            placeholder={attr.name}
          />
        );
      
      case 'number':
      case 'decimal':
        return (
          <input
            type="number"
            step={attr.data_type === 'decimal' ? '0.01' : '1'}
            value={value}
            onChange={(e) => handleAttributeChange(attr.key, parseFloat(e.target.value) || 0)}
            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            required={attr.is_required}
            placeholder={attr.name}
          />
        );
      
      case 'boolean':
        return (
          <label className="flex items-center space-x-2 cursor-pointer">
            <input
              type="checkbox"
              checked={Boolean(value)}
              onChange={(e) => handleAttributeChange(attr.key, e.target.checked)}
              className="w-4 h-4 text-blue-600 rounded focus:ring-blue-500"
            />
            <span className="text-sm font-medium text-gray-700">{attr.name}</span>
          </label>
        );
      
      case 'select':
        return (
          <select
            value={value}
            onChange={(e) => handleAttributeChange(attr.key, e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            required={attr.is_required}
          >
            <option value="">Select {attr.name}</option>
            {getSelectOptions(attr.key).map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        );
      
      default:
        return (
          <input
            type="text"
            value={value}
            onChange={(e) => handleAttributeChange(attr.key, e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            required={attr.is_required}
            placeholder={attr.name}
          />
        );
    }
  };

  const getEntityTypeLabel = () => {
    switch (entityType) {
      case 'branch':
        return 'Branch';
      case 'credit_card':
        return 'Credit Card';
      case 'deposit':
        return 'Deposit';
      case 'loan':
        return 'Loan';
      case 'insurance_product':
        return 'Insurance Product';
      default:
        return 'Entity';
    }
  };

  if (isLoadingEntity) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  return (
    <div className="max-w-4xl mx-auto">
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center space-x-4">
          <Link
            to={`/companies-eav/${companyId}/entities`}
            className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <ChevronLeft className="w-5 h-5" />
          </Link>
          <div>
            <h1 className="text-2xl font-bold text-gray-900">
              {isEditMode ? `Edit ${getEntityTypeLabel()}` : `Create New ${getEntityTypeLabel()}`}
            </h1>
            <p className="text-sm text-gray-500 mt-1">
              {companyData?.data?.name}
            </p>
          </div>
        </div>

        <div className="flex items-center space-x-3">
          <button
            type="button"
            onClick={() => navigate(`/companies-eav/${companyId}/entities`)}
            className="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
          >
            Cancel
          </button>
          <button
            onClick={handleSubmit}
            disabled={mutation.isPending}
            className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center space-x-2 disabled:opacity-50"
          >
            <Save className="w-4 h-4" />
            <span>{mutation.isPending ? 'Saving...' : 'Save'}</span>
          </button>
        </div>
      </div>

      {/* Form */}
      <div className="bg-white rounded-lg shadow-sm border border-gray-200">
        <form onSubmit={handleSubmit} className="p-6">
          <div className="space-y-6">
            {/* Basic Fields */}
            <div className="grid grid-cols-2 gap-6">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Name <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                  required
                />
              </div>

              {(entityType === 'branch' || entityType === 'credit_card') && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Code
                  </label>
                  <input
                    type="text"
                    value={formData.code}
                    onChange={(e) => setFormData({ ...formData, code: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>
              )}

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Display Order
                </label>
                <input
                  type="number"
                  value={formData.display_order ?? ''}
                  onChange={(e) => setFormData({ ...formData, display_order: e.target.value === '' ? 0 : parseInt(e.target.value) })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="0"
                />
              </div>

              <div className="flex items-center">
                <label className="flex items-center space-x-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={formData.is_active}
                    onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
                    className="w-4 h-4 text-blue-600 rounded focus:ring-blue-500"
                  />
                  <span className="text-sm font-medium text-gray-700">Active</span>
                </label>
              </div>
            </div>

            {/* Entity-specific Attributes */}
            {entityAttributes.length > 0 && (
              <div className="border-t pt-6">
                <h3 className="text-lg font-medium text-gray-900 mb-4">
                  {getEntityTypeLabel()} Details
                </h3>

                {/* Language Tabs for translatable fields */}
                {entityAttributes.some(attr => attr.is_translatable) && (
                  <div className="flex space-x-2 mb-4">
                    {['en', 'az', 'ru'].map((lang) => (
                      <button
                        key={lang}
                        type="button"
                        onClick={() => setActiveLang(lang as 'en' | 'az' | 'ru')}
                        className={`px-3 py-1 text-sm rounded-md transition-colors ${
                          activeLang === lang
                            ? 'bg-blue-100 text-blue-700'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                        }`}
                      >
                        {lang.toUpperCase()}
                      </button>
                    ))}
                  </div>
                )}

                <div className="grid grid-cols-2 gap-6">
                  {entityAttributes.map((attr) => (
                    <div key={attr.key} className={attr.data_type === 'boolean' ? 'col-span-2' : ''}>
                      {attr.data_type !== 'boolean' && (
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                          {attr.name}
                          {attr.is_required && <span className="text-red-500 ml-1">*</span>}
                          {attr.is_translatable && <span className="text-blue-500 ml-1">({activeLang.toUpperCase()})</span>}
                        </label>
                      )}
                      {renderAttributeField(attr)}
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>
        </form>
      </div>
    </div>
  );
};

// Helper function to get entity type attributes
function getEntityTypeAttributes(entityType: string): EntityAttribute[] {
  switch (entityType) {
    case 'branch':
      return [
        { key: 'branch_name', name: 'Branch Name', data_type: 'string', is_required: true, is_translatable: true },
        { key: 'branch_code', name: 'Branch Code', data_type: 'string', is_required: true, is_translatable: false },
        { key: 'address', name: 'Address', data_type: 'text', is_required: true, is_translatable: true },
        { key: 'phone', name: 'Phone', data_type: 'string', is_required: true, is_translatable: false },
        { key: 'email', name: 'Email', data_type: 'string', is_required: false, is_translatable: false },
        { key: 'working_hours', name: 'Working Hours', data_type: 'string', is_required: false, is_translatable: false },
        { key: 'latitude', name: 'Latitude', data_type: 'decimal', is_required: false, is_translatable: false },
        { key: 'longitude', name: 'Longitude', data_type: 'decimal', is_required: false, is_translatable: false },
        { key: 'is_24_7', name: '24/7 Service', data_type: 'boolean', is_required: false, is_translatable: false },
      ];
    
    case 'credit_card':
      return [
        { key: 'card_name', name: 'Card Name', data_type: 'string', is_required: true, is_translatable: true },
        { key: 'card_network', name: 'Card Network', data_type: 'select', is_required: true, is_translatable: false },
        { key: 'card_level', name: 'Card Level', data_type: 'select', is_required: true, is_translatable: false },
        { key: 'annual_fee', name: 'Annual Fee', data_type: 'decimal', is_required: true, is_translatable: false },
        { key: 'cashback_rate', name: 'Cashback Rate (%)', data_type: 'decimal', is_required: false, is_translatable: false },
        { key: 'grace_period', name: 'Grace Period (days)', data_type: 'number', is_required: false, is_translatable: false },
        { key: 'credit_limit_min', name: 'Min Credit Limit', data_type: 'decimal', is_required: false, is_translatable: false },
        { key: 'credit_limit_max', name: 'Max Credit Limit', data_type: 'decimal', is_required: false, is_translatable: false },
      ];
    
    case 'deposit':
      return [
        { key: 'product_name', name: 'Product Name', data_type: 'string', is_required: true, is_translatable: true },
        { key: 'interest_rate', name: 'Interest Rate (%)', data_type: 'decimal', is_required: true, is_translatable: false },
        { key: 'min_amount', name: 'Minimum Amount', data_type: 'decimal', is_required: true, is_translatable: false },
        { key: 'max_amount', name: 'Maximum Amount', data_type: 'decimal', is_required: false, is_translatable: false },
        { key: 'term_months', name: 'Term (months)', data_type: 'number', is_required: true, is_translatable: false },
        { key: 'currency', name: 'Currency', data_type: 'select', is_required: true, is_translatable: false },
        { key: 'early_withdrawal', name: 'Early Withdrawal', data_type: 'boolean', is_required: false, is_translatable: false },
        { key: 'capitalization', name: 'Interest Capitalization', data_type: 'boolean', is_required: false, is_translatable: false },
      ];
    
    case 'loan':
      return [
        { key: 'loan_name', name: 'Loan Name', data_type: 'string', is_required: true, is_translatable: true },
        { key: 'loan_type', name: 'Loan Type', data_type: 'select', is_required: true, is_translatable: false },
        { key: 'min_amount', name: 'Minimum Amount', data_type: 'decimal', is_required: true, is_translatable: false },
        { key: 'max_amount', name: 'Maximum Amount', data_type: 'decimal', is_required: true, is_translatable: false },
        { key: 'interest_rate_min', name: 'Min Interest Rate (%)', data_type: 'decimal', is_required: true, is_translatable: false },
        { key: 'interest_rate_max', name: 'Max Interest Rate (%)', data_type: 'decimal', is_required: true, is_translatable: false },
        { key: 'term_months_min', name: 'Min Term (months)', data_type: 'number', is_required: true, is_translatable: false },
        { key: 'term_months_max', name: 'Max Term (months)', data_type: 'number', is_required: true, is_translatable: false },
        { key: 'collateral_required', name: 'Collateral Required', data_type: 'boolean', is_required: false, is_translatable: false },
      ];
    
    case 'insurance_product':
      return [
        { key: 'product_name', name: 'Product Name', data_type: 'string', is_required: true, is_translatable: true },
        { key: 'insurance_type', name: 'Insurance Type', data_type: 'select', is_required: true, is_translatable: false },
        { key: 'coverage_amount_min', name: 'Min Coverage', data_type: 'decimal', is_required: true, is_translatable: false },
        { key: 'coverage_amount_max', name: 'Max Coverage', data_type: 'decimal', is_required: true, is_translatable: false },
        { key: 'premium_monthly', name: 'Monthly Premium', data_type: 'decimal', is_required: false, is_translatable: false },
        { key: 'premium_annual', name: 'Annual Premium', data_type: 'decimal', is_required: false, is_translatable: false },
        { key: 'deductible', name: 'Deductible', data_type: 'decimal', is_required: false, is_translatable: false },
        { key: 'waiting_period_days', name: 'Waiting Period (days)', data_type: 'number', is_required: false, is_translatable: false },
      ];
    
    default:
      return [];
  }
}

// Helper function to get select options
function getSelectOptions(key: string): { value: string; label: string }[] {
  switch (key) {
    case 'card_network':
      return [
        { value: 'Visa', label: 'Visa' },
        { value: 'Mastercard', label: 'Mastercard' },
        { value: 'American Express', label: 'American Express' },
        { value: 'UnionPay', label: 'UnionPay' },
      ];
    
    case 'card_level':
      return [
        { value: 'Classic', label: 'Classic' },
        { value: 'Gold', label: 'Gold' },
        { value: 'Platinum', label: 'Platinum' },
        { value: 'Business', label: 'Business' },
        { value: 'Infinite', label: 'Infinite' },
      ];
    
    case 'currency':
      return [
        { value: 'AZN', label: 'AZN' },
        { value: 'USD', label: 'USD' },
        { value: 'EUR', label: 'EUR' },
        { value: 'GBP', label: 'GBP' },
      ];
    
    case 'loan_type':
      return [
        { value: 'personal', label: 'Personal' },
        { value: 'business', label: 'Business' },
        { value: 'mortgage', label: 'Mortgage' },
        { value: 'auto', label: 'Auto' },
        { value: 'education', label: 'Education' },
      ];
    
    case 'insurance_type':
      return [
        { value: 'life', label: 'Life Insurance' },
        { value: 'health', label: 'Health Insurance' },
        { value: 'auto', label: 'Auto Insurance' },
        { value: 'property', label: 'Property Insurance' },
        { value: 'travel', label: 'Travel Insurance' },
        { value: 'liability', label: 'Liability Insurance' },
      ];
    
    default:
      return [];
  }
}

export default EntityForm;