import { useEffect, useState } from 'react';
import { FileText, Users, Package, BookOpen } from 'lucide-react';
import { Link } from 'react-router-dom';
import api from '../services/api';

interface DashboardStats {
  total_news: number;
  active_news: number;
  total_users: number;
  total_offers: number;
  total_blogs: number;
  recent_news: any[];
}

export default function Dashboard() {
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchDashboardStats();
  }, []);

  const fetchDashboardStats = async () => {
    try {
      const response = await api.get<DashboardStats>('/admin/dashboard');
      setStats(response.data);
    } catch (error) {
      console.error('Failed to fetch dashboard stats:', error);
    } finally {
      setLoading(false);
    }
  };

  const statCards = [
    { 
      label: 'Total News', 
      value: stats?.total_news || 0, 
      icon: FileText, 
      color: 'bg-blue-500',
      link: '/news'
    },
    { 
      label: 'Total Users', 
      value: stats?.total_users || 0, 
      icon: Users, 
      color: 'bg-green-500' 
    },
    { 
      label: 'Total Offers', 
      value: stats?.total_offers || 0, 
      icon: Package, 
      color: 'bg-purple-500' 
    },
    { 
      label: 'Total Blogs', 
      value: stats?.total_blogs || 0, 
      icon: BookOpen, 
      color: 'bg-orange-500' 
    },
  ];

  return (
    <div>
      <h1 className="text-2xl font-bold text-gray-900 mb-8">Dashboard</h1>
      
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8">
        {statCards.map((stat) => {
          const Icon = stat.icon;
          const content = (
            <div className="flex items-center">
              <div className={`rounded-full p-3 ${stat.color}`}>
                <Icon className="h-6 w-6 text-white" />
              </div>
              <div className="ml-4">
                <p className="text-sm font-medium text-gray-600">{stat.label}</p>
                <p className="text-2xl font-bold text-gray-900">
                  {loading ? '...' : stat.value}
                </p>
              </div>
            </div>
          );

          return stat.link ? (
            <Link key={stat.label} to={stat.link} className="bg-white rounded-lg shadow p-4 md:p-6 hover:shadow-lg transition-shadow">
              {content}
            </Link>
          ) : (
            <div key={stat.label} className="bg-white rounded-lg shadow p-4 md:p-6">
              {content}
            </div>
          );
        })}
      </div>

      <div className="bg-white rounded-lg shadow p-6">
        <h2 className="text-lg font-bold text-gray-900 mb-4">Recent News</h2>
        {loading ? (
          <p className="text-gray-600">Loading...</p>
        ) : stats?.recent_news && stats.recent_news.length > 0 ? (
          <div className="space-y-4">
            {stats.recent_news.slice(0, 5).map((news) => (
              <div key={news.id} className="border-b pb-3 last:border-0">
                <Link 
                  to={`/news/${news.id}/edit`}
                  className="font-semibold text-gray-800 hover:text-blue-600 hover:underline"
                >
                  {news.title}
                </Link>
                <p className="text-sm text-gray-600">
                  {new Date(news.publish_date).toLocaleDateString()} • {news.language.toUpperCase()}
                </p>
              </div>
            ))}
          </div>
        ) : (
          <p className="text-gray-600">No recent news to display.</p>
        )}
      </div>
    </div>
  );
}