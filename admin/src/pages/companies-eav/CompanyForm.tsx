import React, { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { ChevronLeft, Save, Upload, X } from 'lucide-react';
import { companiesEavApi, Company } from '../../services/companies-eav';
import { API_CONFIG } from '../../config/api';

const CompanyForm: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const isEditMode = !!id;

  const [formData, setFormData] = useState<Partial<Company>>({
    name: '',
    company_type_id: 0,
    is_active: true,
    display_order: 0,
    attributes: {},
  });

  const [logoFile, setLogoFile] = useState<File | null>(null);
  const [logoPreview, setLogoPreview] = useState<string>('');
  const [attributeDefinitions, setAttributeDefinitions] = useState<any[]>([]);

  const [activeLang, setActiveLang] = useState<'en' | 'az' | 'ru'>('en');

  // Fetch company types
  const { data: typesData } = useQuery({
    queryKey: ['company-types'],
    queryFn: companiesEavApi.getTypes,
  });

  // Fetch company data if editing
  const { data: companyData, isLoading: isLoadingCompany } = useQuery({
    queryKey: ['company', id],
    queryFn: () => companiesEavApi.getCompany(Number(id)),
    enabled: isEditMode,
  });

  // Attributes are stored as JSON in the company record, no need for separate fetch

  // Load company data when editing
  useEffect(() => {
    if (companyData?.data) {
      const company = companyData.data;
      
      // Transform EAV attributes to simple key-value pairs
      const transformedAttributes: Record<string, any> = {};
      if (company.eav_attributes) {
        Object.entries(company.eav_attributes).forEach(([key, attrData]: [string, any]) => {
          // attrData has structure: { value, name, data_type, is_required, group }
          transformedAttributes[key] = attrData.value;
        });
      }
      
      setFormData({
        company_type_id: company.company_type_id || 0,
        name: company.name,
        is_active: company.is_active !== undefined ? company.is_active : true,
        display_order: company.display_order ?? 0,
        attributes: transformedAttributes,
      });

      // Set logo preview if exists
      if (company.logo) {
        setLogoPreview(API_CONFIG.getImageUrl(company.logo));
      }
    }
  }, [companyData]);

  // Load attribute definitions when company type changes
  useEffect(() => {
    if (formData.company_type_id && formData.company_type_id > 0) {
      companiesEavApi.getAttributeDefinitions(formData.company_type_id)
        .then(response => {
          if (response.status === 'success') {
            // The data is already a flat array now
            console.log('Attribute definitions received:', response.data);
            setAttributeDefinitions(response.data || []);
          }
        })
        .catch(error => {
          console.error('Failed to load attribute definitions:', error);
        });
    } else {
      setAttributeDefinitions([]);
    }
  }, [formData.company_type_id]);

  // Create/Update mutation
  const mutation = useMutation({
    mutationFn: async (data: any) => {
      // Create FormData if we have a logo file
      let dataToSend: FormData | Partial<Company>;

      if (data.logo instanceof File) {
        dataToSend = new FormData();

        // Add _method field for PUT requests
        if (isEditMode) {
          dataToSend.append('_method', 'PUT');
        }

        dataToSend.append('name', data.name);
        dataToSend.append('company_type_id', data.company_type_id.toString());
        dataToSend.append('is_active', data.is_active ? '1' : '0');
        dataToSend.append('display_order', data.display_order.toString());
        dataToSend.append('logo', data.logo);

        // Append attributes as JSON string
        if (data.attributes) {
          dataToSend.append('attributes', JSON.stringify(data.attributes));
        }
      } else {
        // No file, send as JSON
        const { logo, ...jsonData } = data;
        dataToSend = jsonData;
      }

      if (isEditMode) {
        return companiesEavApi.updateCompany(Number(id), dataToSend);
      }
      return companiesEavApi.createCompany(dataToSend);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['companies-eav'] });
      navigate('/companies-eav');
    },
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    const dataToSend = {
      company_type_id: formData.company_type_id,
      name: formData.name,
      is_active: formData.is_active,
      display_order: formData.display_order || 0,
      attributes: formData.attributes || {},
      logo: logoFile || undefined,
    };

    console.log('Submitting data:', dataToSend);
    console.log('Attributes being sent:', dataToSend.attributes);

    mutation.mutate(dataToSend);
  };

  const handleLogoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      setLogoFile(file);
      const reader = new FileReader();
      reader.onloadend = () => {
        setLogoPreview(reader.result as string);
      };
      reader.readAsDataURL(file);
    }
  };

  const removeLogo = () => {
    setLogoFile(null);
    setLogoPreview('');
  };

  const handleAttributeChange = (key: string, value: any) => {
    setFormData(prev => ({
      ...prev,
      attributes: {
        ...prev.attributes,
        [key]: value,
      },
    }));
  };

  const handleTranslatableChange = (field: string, value: string) => {
    if (field === 'name') {
      setFormData(prev => ({ ...prev, name: value }));
    } else {
      // Handle translatable attributes
      const existingValue = (formData.attributes?.[field] as any) || {};
      const newValue = typeof existingValue === 'object' && existingValue !== null 
        ? { ...existingValue, [activeLang]: value }
        : { [activeLang]: value };
      handleAttributeChange(field, newValue);
    }
  };




  const getAttributeValue = (key: string, isTranslatable: boolean) => {
    const value = formData.attributes?.[key];
    
    // If no value exists, return empty string
    if (value === undefined || value === null) return '';
    
    // For translatable fields, extract the language-specific value
    if (isTranslatable && typeof value === 'object' && value !== null) {
      return value[activeLang] || '';
    }
    
    // If value is still an object (shouldn't happen for non-translatable), return empty
    if (typeof value === 'object') {
      console.warn(`Unexpected object value for non-translatable field ${key}:`, value);
      return '';
    }
    
    // Return the value as is (string, number, boolean, etc.)
    return value;
  };

  const renderAttributeField = (attr: any) => {
    const value = getAttributeValue(attr.attribute_key, attr.is_translatable);
    const isRequired = attr.is_required;
    
    // Debug: Log the attribute to see its structure
    console.log('Rendering attribute:', attr);
    
    // Get attribute name as string
    const attrName = typeof attr.attribute_name === 'object' 
      ? (attr.attribute_name[activeLang] || attr.attribute_name.en || attr.attribute_name.az || JSON.stringify(attr.attribute_name))
      : (attr.attribute_name || 'Unknown');
    
    switch (attr.data_type) {
      case 'string':
      case 'text':
        return attr.is_translatable ? (
          <input
            type="text"
            value={value}
            onChange={(e) => handleTranslatableChange(attr.attribute_key, e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            required={isRequired}
            placeholder={`${attrName} (${activeLang.toUpperCase()})`}
          />
        ) : (
          <input
            type="text"
            value={value}
            onChange={(e) => handleAttributeChange(attr.attribute_key, e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            required={isRequired}
            placeholder={attrName}
          />
        );
      case 'number':
      case 'decimal':
        return (
          <input
            type="number"
            step={attr.data_type === 'decimal' ? '0.01' : '1'}
            value={value}
            onChange={(e) => handleAttributeChange(attr.attribute_key, parseFloat(e.target.value) || 0)}
            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            required={isRequired}
            placeholder={attrName}
          />
        );
      case 'boolean':
        return (
          <label className="flex items-center space-x-2 cursor-pointer">
            <input
              type="checkbox"
              checked={Boolean(value)}
              onChange={(e) => handleAttributeChange(attr.attribute_key, e.target.checked)}
              className="w-4 h-4 text-blue-600 rounded focus:ring-blue-500"
            />
            <span className="text-sm font-medium text-gray-700">{attrName}</span>
          </label>
        );
      case 'date':
        return (
          <input
            type="date"
            value={value}
            onChange={(e) => handleAttributeChange(attr.attribute_key, e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            required={isRequired}
          />
        );
      default:
        return (
          <input
            type="text"
            value={value}
            onChange={(e) => handleAttributeChange(attr.attribute_key, e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            required={isRequired}
            placeholder={attrName}
          />
        );
    }
  };

  if (isLoadingCompany) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  return (
    <div className="max-w-6xl mx-auto">
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center space-x-4">
          <button
            onClick={() => navigate('/companies-eav')}
            className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <ChevronLeft className="w-5 h-5" />
          </button>
          <h1 className="text-2xl font-bold text-gray-900">
            {isEditMode ? 'Edit Company' : 'Create New Company'}
          </h1>
        </div>
        
        <div className="flex items-center space-x-3">
          <button
            type="button"
            onClick={() => navigate('/companies-eav')}
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
            <span>{mutation.isPending ? 'Saving...' : 'Save Company'}</span>
          </button>
        </div>
      </div>

      {/* Form */}
      <div className="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
        <form onSubmit={handleSubmit} className="p-6">
          <div className="space-y-6">
            {/* Company Type */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Company Type <span className="text-red-500">*</span>
              </label>
              <select
                value={formData.company_type_id}
                onChange={(e) => setFormData({ ...formData, company_type_id: Number(e.target.value) })}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                required
              >
                <option value="">Select Type...</option>
                {typesData?.data?.map((type: any) => {
                  // Parse type_name if it's a JSON string
                  let displayName = type.type_name;
                  if (typeof type.type_name === 'string' && type.type_name.startsWith('{')) {
                    try {
                      const parsed = JSON.parse(type.type_name);
                      displayName = parsed.en || parsed.az || parsed.ru || type.type_name;
                    } catch {
                      // Keep original if parsing fails
                    }
                  } else if (typeof type.type_name === 'object' && type.type_name !== null) {
                    displayName = type.type_name.en || type.type_name.az || type.type_name.ru || 'Unknown';
                  }

                  return (
                    <option key={type.id} value={type.id}>
                      {displayName}
                    </option>
                  );
                })}
              </select>
            </div>

            {/* Company Name */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Company Name <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                value={typeof formData.name === 'string' ? formData.name : (formData.name?.[activeLang] || '')}
                onChange={(e) => handleTranslatableChange('name', e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                required
              />
            </div>

            {/* Display Order */}
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

            {/* Logo Upload */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Company Logo
              </label>
              <div className="flex items-center gap-4">
                {logoPreview ? (
                  <div className="relative">
                    <img
                      src={logoPreview}
                      alt="Logo preview"
                      className="w-32 h-20 object-contain border rounded"
                    />
                    <button
                      type="button"
                      onClick={removeLogo}
                      className="absolute -top-2 -right-2 p-1 bg-red-500 text-white rounded-full hover:bg-red-600"
                    >
                      <X className="h-3 w-3" />
                    </button>
                  </div>
                ) : (
                  <div className="w-32 h-20 border-2 border-dashed border-gray-300 rounded flex items-center justify-center">
                    <Upload className="h-8 w-8 text-gray-400" />
                  </div>
                )}
                <input
                  type="file"
                  onChange={handleLogoChange}
                  accept="image/*"
                  className="hidden"
                  id="logo-upload"
                />
                <label
                  htmlFor="logo-upload"
                  className="px-4 py-2 bg-blue-50 text-blue-600 rounded-md hover:bg-blue-100 cursor-pointer"
                >
                  Choose Logo
                </label>
              </div>
            </div>

            {/* Status */}
            <div className="flex items-center space-x-6">
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

            {/* EAV Attributes */}
            {attributeDefinitions.length > 0 && (
              <div className="border-t pt-6">
                <h3 className="text-lg font-medium text-gray-900 mb-4">Company Attributes</h3>
                
                {/* Language Tabs for translatable fields */}
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

                <div className="space-y-4">
                  {attributeDefinitions.map((attr) => {
                    // Debug: Check what attr.attribute_name contains
                    const attrName = typeof attr.attribute_name === 'object' 
                      ? (attr.attribute_name[activeLang] || attr.attribute_name.en || attr.attribute_name.az || 'Unknown Attribute')
                      : (attr.attribute_name || 'Unknown Attribute');
                    
                    return (
                      <div key={attr.id}>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                          {attrName}
                          {Boolean(attr.is_required) && <span className="text-red-500 ml-1">*</span>}
                          {Boolean(attr.is_translatable) && <span className="text-blue-500 ml-1">({activeLang.toUpperCase()})</span>}
                        </label>
                        {renderAttributeField(attr)}
                      </div>
                    );
                  })}
                </div>
              </div>
            )}
          </div>
        </form>
      </div>
    </div>
  );
};

export default CompanyForm;