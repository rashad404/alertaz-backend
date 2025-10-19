import { Outlet, Link, useNavigate, useLocation } from 'react-router-dom';
import { Home, FileText, LogOut, Menu, X, Mail, FolderOpen, Users, Images, Megaphone, Monitor, Building2, List } from 'lucide-react';
import { useState } from 'react';
import { authService } from '../services/auth';

export default function Layout() {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const navigate = useNavigate();
  const location = useLocation();

  const handleLogout = async () => {
    try {
      await authService.logout();
      navigate('/login');
    } catch (error) {
      console.error('Logout failed:', error);
    }
  };

  const menuItems = [
    { path: '/dashboard', icon: Home, label: 'Dashboard' },
    { path: '/news', icon: FileText, label: 'News' },
    { path: '/users', icon: Users, label: 'Users' },
    { path: '/companies-eav', icon: Building2, label: 'Companies' },
    { path: '/company-types', icon: List, label: 'Company Types' },
    { path: '/news-categories', icon: FolderOpen, label: 'News Categories' },
    { path: '/sliders', icon: Images, label: 'Homepage Slider' },
    { path: '/hero-banners', icon: Monitor, label: 'Hero Banners' },
    { path: '/ads', icon: Megaphone, label: 'Ads' },
    { path: '/menus', icon: List, label: 'Menus' },
    { path: '/subscribers', icon: Mail, label: 'Subscribers' },
  ];

  const isActive = (path: string) => location.pathname.startsWith(path);

  return (
    <div className="min-h-screen bg-gray-100">
      {/* Mobile sidebar toggle */}
      <div className="lg:hidden fixed top-4 left-4 z-50">
        <button
          onClick={() => setSidebarOpen(!sidebarOpen)}
          className="p-2 rounded-md bg-white shadow-md"
        >
          {sidebarOpen ? <X size={24} /> : <Menu size={24} />}
        </button>
      </div>

      {/* Sidebar */}
      <div
        className={`fixed inset-y-0 left-0 z-40 w-64 bg-white shadow-lg transform transition-transform lg:translate-x-0 ${
          sidebarOpen ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        <div className="flex items-center justify-center h-16 border-b">
          <h1 className="text-xl font-bold text-gray-800">Kredit Admin</h1>
        </div>
        <nav className="mt-8">
          {menuItems.map((item) => {
            const Icon = item.icon;
            return (
              <Link
                key={item.path}
                to={item.path}
                className={`flex items-center px-6 py-3 text-gray-700 hover:bg-gray-100 hover:text-gray-900 ${
                  isActive(item.path) ? 'bg-gray-100 border-l-4 border-blue-500' : ''
                }`}
                onClick={() => setSidebarOpen(false)}
              >
                <Icon size={20} className="mr-3" />
                {item.label}
              </Link>
            );
          })}
        </nav>
        <div className="absolute bottom-0 w-full p-4">
          <button
            onClick={handleLogout}
            className="flex items-center w-full px-6 py-3 text-gray-700 hover:bg-gray-100 hover:text-gray-900 rounded-md"
          >
            <LogOut size={20} className="mr-3" />
            Logout
          </button>
        </div>
      </div>

      {/* Main content */}
      <div className="lg:ml-64">
        <main className="p-4 sm:p-6 lg:p-8 pt-16 lg:pt-8">
          <Outlet />
        </main>
      </div>

      {/* Mobile sidebar overlay */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 z-30 bg-black bg-opacity-50 lg:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}
    </div>
  );
}