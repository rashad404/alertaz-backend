import React, { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { Save, X, Globe, Link, ChevronDown } from 'lucide-react';
import menuService, { Menu, MenuFormData } from '../../services/menus';
import { toast } from 'react-hot-toast';

const MenusForm: React.FC = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const isEditMode = !!id;
  const [loading, setLoading] = useState(false);
  const [parentMenus, setParentMenus] = useState<Menu[]>([]);
  const [activeTab, setActiveTab] = useState<'az' | 'en' | 'ru'>('az');

  const [formData, setFormData] = useState<MenuFormData>({
    title: {
      az: '',
      en: '',
      ru: ''
    },
    slug: '',
    url: '',
    parent_id: null,
    position: 0,
    target: '_self',
    has_dropdown: false,
    is_active: true,
    menu_location: 'header',
    icon: '',
    meta: {}
  });

  useEffect(() => {
    fetchParentMenus();
    if (isEditMode) {
      fetchMenu();
    }
  }, [isEditMode, id]);

  const fetchParentMenus = async () => {
    try {
      const response = await menuService.getMenus(1, '', '');
      const menus = response.data || [];
      setParentMenus(menus.filter((menu: Menu) => !menu.parent_id && menu.id !== Number(id)));
    } catch (error) {
      console.error('Error fetching parent menus:', error);
    }
  };

  const fetchMenu = async () => {
    try {
      setLoading(true);
      const menu = await menuService.getMenu(Number(id));
      
      let title = menu.title;
      if (typeof title === 'string') {
        try {
          title = JSON.parse(title);
        } catch {
          title = { az: title, en: title, ru: title };
        }
      }

      setFormData({
        title: title || { az: '', en: '', ru: '' },
        slug: menu.slug || '',
        url: menu.url || '',
        parent_id: menu.parent_id || null,
        position: menu.position || 0,
        target: menu.target || '_self',
        has_dropdown: menu.has_dropdown || false,
        is_active: menu.is_active !== undefined ? menu.is_active : true,
        menu_location: menu.menu_location || 'header',
        icon: menu.icon || '',
        meta: menu.meta || {}
      });
    } catch (error) {
      console.error('Error fetching menu:', error);
      toast.error('Failed to load menu');
      navigate('/menus');
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!formData.title.az || !formData.title.en || !formData.title.ru) {
      toast.error('Please fill in all title translations');
      return;
    }

    if (!formData.slug) {
      toast.error('Please enter a slug');
      return;
    }

    try {
      setLoading(true);
      
      if (isEditMode) {
        await menuService.updateMenu(Number(id), formData);
        toast.success('Menu updated successfully');
      } else {
        await menuService.createMenu(formData);
        toast.success('Menu created successfully');
      }
      
      navigate('/menus');
    } catch (error: any) {
      console.error('Error saving menu:', error);
      if (error.response?.data?.errors) {
        const errors = error.response.data.errors;
        Object.keys(errors).forEach(key => {
          toast.error(errors[key][0]);
        });
      } else {
        toast.error(isEditMode ? 'Failed to update menu' : 'Failed to create menu');
      }
    } finally {
      setLoading(false);
    }
  };

  const generateSlug = (text: string) => {
    return text
      .toLowerCase()
      .replace(/[^a-z0-9\s-]/g, '')
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-')
      .trim();
  };

  const handleTitleChange = (lang: 'az' | 'en' | 'ru', value: string) => {
    setFormData(prev => ({
      ...prev,
      title: {
        ...prev.title,
        [lang]: value
      }
    }));

    if (lang === 'en' && !formData.slug) {
      setFormData(prev => ({
        ...prev,
        slug: generateSlug(value)
      }));
    }
  };

  const renderParentMenuTitle = (menu: Menu) => {
    if (typeof menu.title === 'string') {
      try {
        const parsed = JSON.parse(menu.title);
        return parsed.az || parsed.en || parsed.ru || menu.title;
      } catch {
        return menu.title;
      }
    }
    return menu.title?.az || menu.title?.en || menu.title?.ru || 'Untitled';
  };

  if (loading && isEditMode) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="text-lg">Loading menu...</div>
      </div>
    );
  }

  return (
    <div className="p-6">
      <div className="max-w-4xl mx-auto">
        <div className="flex items-center justify-between mb-6">
          <h1 className="text-2xl font-bold">
            {isEditMode ? 'Edit Menu' : 'Add New Menu'}
          </h1>
          <button
            onClick={() => navigate('/menus')}
            className="text-gray-600 hover:text-gray-900"
          >
            <X className="w-6 h-6" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-6">
          <div className="bg-white rounded-lg shadow p-6">
            <h2 className="text-lg font-semibold mb-4 flex items-center gap-2">
              <Globe className="w-5 h-5" />
              Menu Title
            </h2>

            <div className="border-b mb-4">
              <nav className="flex space-x-8">
                {(['az', 'en', 'ru'] as const).map((lang) => (
                  <button
                    key={lang}
                    type="button"
                    onClick={() => setActiveTab(lang)}
                    className={`py-2 px-1 border-b-2 font-medium text-sm ${
                      activeTab === lang
                        ? 'border-blue-500 text-blue-600'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                    }`}
                  >
                    {lang.toUpperCase()}
                  </button>
                ))}
              </nav>
            </div>

            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Title ({activeTab.toUpperCase()})
                </label>
                <input
                  type="text"
                  value={formData.title[activeTab]}
                  onChange={(e) => handleTitleChange(activeTab, e.target.value)}
                  className="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder={`Enter menu title in ${activeTab === 'az' ? 'Azerbaijani' : activeTab === 'en' ? 'English' : 'Russian'}`}
                />
              </div>
            </div>
          </div>

          <div className="bg-white rounded-lg shadow p-6">
            <h2 className="text-lg font-semibold mb-4 flex items-center gap-2">
              <Link className="w-5 h-5" />
              Menu Properties
            </h2>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Slug <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  value={formData.slug}
                  onChange={(e) => setFormData(prev => ({ ...prev, slug: e.target.value }))}
                  className="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="menu-slug"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  URL (Optional)
                </label>
                <input
                  type="text"
                  value={formData.url || ''}
                  onChange={(e) => setFormData(prev => ({ ...prev, url: e.target.value }))}
                  className="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="/page-url or https://external-url.com"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Parent Menu
                </label>
                <select
                  value={formData.parent_id || ''}
                  onChange={(e) => setFormData(prev => ({ 
                    ...prev, 
                    parent_id: e.target.value ? Number(e.target.value) : null 
                  }))}
                  className="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="">No Parent (Top Level)</option>
                  {parentMenus.map((menu) => (
                    <option key={menu.id} value={menu.id}>
                      {renderParentMenuTitle(menu)}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Menu Location <span className="text-red-500">*</span>
                </label>
                <select
                  value={formData.menu_location}
                  onChange={(e) => setFormData(prev => ({ 
                    ...prev, 
                    menu_location: e.target.value as 'header' | 'footer' | 'both'
                  }))}
                  className="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                  required
                >
                  <option value="header">Header</option>
                  <option value="footer">Footer</option>
                  <option value="both">Both</option>
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Position (Order)
                </label>
                <input
                  type="number"
                  value={formData.position}
                  onChange={(e) => setFormData(prev => ({ ...prev, position: Number(e.target.value) }))}
                  className="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                  min="0"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Link Target
                </label>
                <select
                  value={formData.target}
                  onChange={(e) => setFormData(prev => ({ 
                    ...prev, 
                    target: e.target.value as '_self' | '_blank'
                  }))}
                  className="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="_self">Same Window</option>
                  <option value="_blank">New Window</option>
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Icon Class (Optional)
                </label>
                <input
                  type="text"
                  value={formData.icon || ''}
                  onChange={(e) => setFormData(prev => ({ ...prev, icon: e.target.value }))}
                  className="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="lucide-icon-name or custom-class"
                />
              </div>
            </div>

            <div className="mt-4 space-y-4">
              <label className="flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={formData.has_dropdown}
                  onChange={(e) => setFormData(prev => ({ ...prev, has_dropdown: e.target.checked }))}
                  className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                />
                <span className="text-sm font-medium text-gray-700 flex items-center gap-1">
                  <ChevronDown className="w-4 h-4" />
                  Has Dropdown Menu
                </span>
              </label>

              <label className="flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={formData.is_active}
                  onChange={(e) => setFormData(prev => ({ ...prev, is_active: e.target.checked }))}
                  className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                />
                <span className="text-sm font-medium text-gray-700">
                  Active (Show in navigation)
                </span>
              </label>
            </div>
          </div>

          <div className="flex justify-end gap-4">
            <button
              type="button"
              onClick={() => navigate('/menus')}
              className="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={loading}
              className="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2"
            >
              <Save className="w-5 h-5" />
              {loading ? 'Saving...' : (isEditMode ? 'Update Menu' : 'Create Menu')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default MenusForm;