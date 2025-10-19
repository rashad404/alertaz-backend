import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { 
  Plus, 
  Edit, 
  Trash2, 
  Search, 
  ChevronDown, 
  Menu as MenuIcon, 
  ExternalLink,
  Eye,
  EyeOff,
  ArrowUpDown
} from 'lucide-react';
import menuService, { Menu } from '../../services/menus';
import { toast } from 'react-hot-toast';

const MenusList: React.FC = () => {
  const [menus, setMenus] = useState<Menu[]>([]);
  const [loading, setLoading] = useState(true);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [searchTerm, setSearchTerm] = useState('');
  const [locationFilter, setLocationFilter] = useState('');
  const [expandedItems, setExpandedItems] = useState<number[]>([]);

  useEffect(() => {
    fetchMenus();
  }, [currentPage, searchTerm, locationFilter]);

  const fetchMenus = async () => {
    try {
      setLoading(true);
      const response = await menuService.getMenus(currentPage, searchTerm, locationFilter);
      // Handle both array response and object with data property
      const menusData = Array.isArray(response) ? response : (response.data || []);
      setMenus(Array.isArray(menusData) ? menusData : []);
      setTotalPages(response.last_page || 1);
    } catch (error) {
      console.error('Error fetching menus:', error);
      toast.error('Failed to load menus');
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = async (id: number, title: string) => {
    if (window.confirm(`Are you sure you want to delete "${title}"?`)) {
      try {
        await menuService.deleteMenu(id);
        toast.success('Menu deleted successfully');
        fetchMenus();
      } catch (error: any) {
        if (error.response?.data?.message) {
          toast.error(error.response.data.message);
        } else {
          toast.error('Failed to delete menu');
        }
      }
    }
  };

  const handleToggleStatus = async (id: number) => {
    try {
      await menuService.toggleMenuStatus(id);
      toast.success('Menu status updated successfully');
      fetchMenus();
    } catch (error) {
      toast.error('Failed to update menu status');
    }
  };

  const handleReorder = async (menuId: number, direction: 'up' | 'down') => {
    const menuIndex = menus.findIndex(m => m.id === menuId);
    if (menuIndex === -1) return;

    const newIndex = direction === 'up' ? menuIndex - 1 : menuIndex + 1;
    if (newIndex < 0 || newIndex >= menus.length) return;

    const newMenus = [...menus];
    [newMenus[menuIndex], newMenus[newIndex]] = [newMenus[newIndex], newMenus[menuIndex]];

    const reorderData = newMenus.map((menu, index) => ({
      id: menu.id,
      position: index
    }));

    try {
      await menuService.reorderMenus(reorderData);
      setMenus(newMenus);
      toast.success('Menu order updated successfully');
    } catch (error) {
      toast.error('Failed to reorder menus');
      fetchMenus();
    }
  };

  const toggleExpand = (id: number) => {
    setExpandedItems(prev => 
      prev.includes(id) 
        ? prev.filter(item => item !== id)
        : [...prev, id]
    );
  };

  const getLocationBadge = (location: string) => {
    const colors = {
      header: 'bg-blue-100 text-blue-800',
      footer: 'bg-purple-100 text-purple-800',
      both: 'bg-green-100 text-green-800'
    };
    return (
      <span className={`px-2 py-1 text-xs rounded-full ${colors[location as keyof typeof colors]}`}>
        {location}
      </span>
    );
  };

  const renderMenuTitle = (menu: Menu) => {
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

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="text-lg">Loading menus...</div>
      </div>
    );
  }

  return (
    <div className="p-6">
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold">Menus</h1>
        <Link
          to="/menus/new"
          className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2"
        >
          <Plus className="w-5 h-5" />
          Add Menu
        </Link>
      </div>

      <div className="mb-6 flex flex-col sm:flex-row gap-4">
        <div className="flex-1">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5" />
            <input
              type="text"
              placeholder="Search menus..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="w-full pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
        </div>
        <select
          value={locationFilter}
          onChange={(e) => setLocationFilter(e.target.value)}
          className="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">All Locations</option>
          <option value="header">Header</option>
          <option value="footer">Footer</option>
          <option value="both">Both</option>
        </select>
      </div>

      <div className="hidden md:block bg-white rounded-lg shadow overflow-hidden">
        <table className="w-full">
          <thead className="bg-gray-50">
            <tr>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Menu
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Slug / URL
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Location
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Status
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Order
              </th>
              <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                Actions
              </th>
            </tr>
          </thead>
          <tbody className="bg-white divide-y divide-gray-200">
            {menus.map((menu, index) => (
              <React.Fragment key={menu.id}>
                <tr className="hover:bg-gray-50">
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="flex items-center">
                      {menu.icon && (
                        <MenuIcon className="w-5 h-5 text-gray-400 mr-2" />
                      )}
                      <div>
                        <div className="text-sm font-medium text-gray-900">
                          {renderMenuTitle(menu)}
                        </div>
                        {menu.parent && (
                          <div className="text-xs text-gray-500">
                            Parent: {renderMenuTitle(menu.parent)}
                          </div>
                        )}
                        {menu.children && menu.children.length > 0 && (
                          <button
                            onClick={() => toggleExpand(menu.id)}
                            className="text-xs text-blue-600 hover:text-blue-800 flex items-center gap-1 mt-1"
                          >
                            <ChevronDown 
                              className={`w-3 h-3 transform transition-transform ${
                                expandedItems.includes(menu.id) ? 'rotate-180' : ''
                              }`}
                            />
                            {menu.children.length} sub-items
                          </button>
                        )}
                      </div>
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm text-gray-900">{menu.slug}</div>
                    {menu.url && (
                      <div className="text-xs text-gray-500 flex items-center gap-1">
                        <ExternalLink className="w-3 h-3" />
                        {menu.url}
                      </div>
                    )}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    {getLocationBadge(menu.menu_location)}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <button
                      onClick={() => handleToggleStatus(menu.id)}
                      className={`flex items-center gap-1 px-2 py-1 rounded-full text-xs ${
                        menu.is_active
                          ? 'bg-green-100 text-green-800'
                          : 'bg-gray-100 text-gray-800'
                      }`}
                    >
                      {menu.is_active ? (
                        <>
                          <Eye className="w-3 h-3" />
                          Active
                        </>
                      ) : (
                        <>
                          <EyeOff className="w-3 h-3" />
                          Inactive
                        </>
                      )}
                    </button>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="flex items-center gap-1">
                      <button
                        onClick={() => handleReorder(menu.id, 'up')}
                        disabled={index === 0}
                        className="p-1 text-gray-600 hover:text-gray-900 disabled:opacity-50 disabled:cursor-not-allowed"
                      >
                        <ArrowUpDown className="w-4 h-4 rotate-180" />
                      </button>
                      <span className="text-sm text-gray-600 min-w-[20px] text-center">
                        {menu.position}
                      </span>
                      <button
                        onClick={() => handleReorder(menu.id, 'down')}
                        disabled={index === menus.length - 1}
                        className="p-1 text-gray-600 hover:text-gray-900 disabled:opacity-50 disabled:cursor-not-allowed"
                      >
                        <ArrowUpDown className="w-4 h-4" />
                      </button>
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <div className="flex items-center justify-end gap-2">
                      <Link
                        to={`/menus/${menu.id}/edit`}
                        className="text-blue-600 hover:text-blue-900"
                      >
                        <Edit className="w-5 h-5" />
                      </Link>
                      <button
                        onClick={() => handleDelete(menu.id, renderMenuTitle(menu))}
                        className="text-red-600 hover:text-red-900"
                      >
                        <Trash2 className="w-5 h-5" />
                      </button>
                    </div>
                  </td>
                </tr>
                {expandedItems.includes(menu.id) && menu.children && menu.children.map((child) => (
                  <tr key={child.id} className="bg-gray-50">
                    <td className="px-6 py-3 pl-12">
                      <div className="text-sm text-gray-700">
                        ↳ {renderMenuTitle(child)}
                      </div>
                    </td>
                    <td className="px-6 py-3">
                      <div className="text-sm text-gray-600">{child.slug}</div>
                      {child.url && (
                        <div className="text-xs text-gray-500">{child.url}</div>
                      )}
                    </td>
                    <td className="px-6 py-3">
                      {getLocationBadge(child.menu_location)}
                    </td>
                    <td className="px-6 py-3">
                      <span className={`text-xs ${child.is_active ? 'text-green-600' : 'text-gray-500'}`}>
                        {child.is_active ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                    <td className="px-6 py-3">
                      <span className="text-sm text-gray-600">{child.position}</span>
                    </td>
                    <td className="px-6 py-3 text-right">
                      <div className="flex items-center justify-end gap-2">
                        <Link
                          to={`/menus/${child.id}/edit`}
                          className="text-blue-600 hover:text-blue-900"
                        >
                          <Edit className="w-4 h-4" />
                        </Link>
                        <button
                          onClick={() => handleDelete(child.id, renderMenuTitle(child))}
                          className="text-red-600 hover:text-red-900"
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </React.Fragment>
            ))}
          </tbody>
        </table>
      </div>

      <div className="md:hidden space-y-4">
        {menus.map((menu) => (
          <div key={menu.id} className="bg-white rounded-lg shadow p-4">
            <div className="flex justify-between items-start mb-2">
              <div className="flex-1">
                <h3 className="font-medium text-gray-900">
                  {renderMenuTitle(menu)}
                </h3>
                <p className="text-sm text-gray-500">{menu.slug}</p>
                {menu.url && (
                  <p className="text-xs text-gray-500 flex items-center gap-1 mt-1">
                    <ExternalLink className="w-3 h-3" />
                    {menu.url}
                  </p>
                )}
                {menu.parent && (
                  <p className="text-xs text-gray-500 mt-1">
                    Parent: {renderMenuTitle(menu.parent)}
                  </p>
                )}
              </div>
              <div className="flex gap-2">
                <Link
                  to={`/menus/${menu.id}/edit`}
                  className="text-blue-600"
                >
                  <Edit className="w-5 h-5" />
                </Link>
                <button
                  onClick={() => handleDelete(menu.id, renderMenuTitle(menu))}
                  className="text-red-600"
                >
                  <Trash2 className="w-5 h-5" />
                </button>
              </div>
            </div>
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                {getLocationBadge(menu.menu_location)}
                <button
                  onClick={() => handleToggleStatus(menu.id)}
                  className={`px-2 py-1 rounded-full text-xs ${
                    menu.is_active
                      ? 'bg-green-100 text-green-800'
                      : 'bg-gray-100 text-gray-800'
                  }`}
                >
                  {menu.is_active ? 'Active' : 'Inactive'}
                </button>
              </div>
              <span className="text-sm text-gray-600">
                Position: {menu.position}
              </span>
            </div>
            {menu.children && menu.children.length > 0 && (
              <div className="mt-3 pt-3 border-t">
                <button
                  onClick={() => toggleExpand(menu.id)}
                  className="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1"
                >
                  <ChevronDown 
                    className={`w-4 h-4 transform transition-transform ${
                      expandedItems.includes(menu.id) ? 'rotate-180' : ''
                    }`}
                  />
                  {menu.children.length} sub-items
                </button>
                {expandedItems.includes(menu.id) && (
                  <div className="mt-2 space-y-2">
                    {menu.children.map((child) => (
                      <div key={child.id} className="pl-4 border-l-2 border-gray-200">
                        <div className="flex justify-between items-center">
                          <div>
                            <p className="text-sm font-medium">{renderMenuTitle(child)}</p>
                            <p className="text-xs text-gray-500">{child.slug}</p>
                          </div>
                          <div className="flex gap-1">
                            <Link
                              to={`/menus/${child.id}/edit`}
                              className="text-blue-600"
                            >
                              <Edit className="w-4 h-4" />
                            </Link>
                            <button
                              onClick={() => handleDelete(child.id, renderMenuTitle(child))}
                              className="text-red-600"
                            >
                              <Trash2 className="w-4 h-4" />
                            </button>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            )}
          </div>
        ))}
      </div>

      {totalPages > 1 && (
        <div className="mt-6 flex justify-center gap-2">
          <button
            onClick={() => setCurrentPage(currentPage - 1)}
            disabled={currentPage === 1}
            className="px-4 py-2 border rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Previous
          </button>
          <span className="px-4 py-2">
            Page {currentPage} of {totalPages}
          </span>
          <button
            onClick={() => setCurrentPage(currentPage + 1)}
            disabled={currentPage === totalPages}
            className="px-4 py-2 border rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Next
          </button>
        </div>
      )}
    </div>
  );
};

export default MenusList;