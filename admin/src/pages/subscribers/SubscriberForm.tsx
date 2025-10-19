import { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useQuery, useMutation } from '@tanstack/react-query';
import { subscribersService } from '../../services/subscribers';

export default function SubscriberForm() {
  const navigate = useNavigate();
  const { id } = useParams();
  const isEdit = !!id;

  const [formData, setFormData] = useState({
    email: '',
    language: 'az' as 'az' | 'en' | 'ru',
    status: 'active' as 'active' | 'unsubscribed'
  });

  const [errors, setErrors] = useState<Record<string, string>>({});

  // Fetch subscriber if editing
  const { data: subscriber } = useQuery({
    queryKey: ['subscriber', id],
    queryFn: () => subscribersService.getOne(Number(id)),
    enabled: isEdit
  });

  useEffect(() => {
    if (subscriber?.data) {
      setFormData({
        email: subscriber.data.email,
        language: subscriber.data.language,
        status: subscriber.data.status
      });
    }
  }, [subscriber]);

  // Create/Update mutation
  const mutation = useMutation({
    mutationFn: (data: typeof formData) => {
      if (isEdit) {
        return subscribersService.update(Number(id), data);
      }
      return subscribersService.create(data);
    },
    onSuccess: () => {
      navigate('/subscribers');
    },
    onError: (error: any) => {
      if (error.response?.data?.errors) {
        setErrors(error.response.data.errors);
      }
    }
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setErrors({});
    
    // Basic validation
    if (!formData.email) {
      setErrors({ email: 'Email is required' });
      return;
    }
    
    if (!formData.email.includes('@')) {
      setErrors({ email: 'Please enter a valid email' });
      return;
    }

    mutation.mutate(formData);
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    setFormData({
      ...formData,
      [e.target.name]: e.target.value
    });
  };

  return (
    <div className="p-6">
      <div className="max-w-2xl mx-auto">
        <div className="mb-6">
          <h1 className="text-2xl font-bold text-gray-900">
            {isEdit ? 'Edit Subscriber' : 'Add New Subscriber'}
          </h1>
        </div>

        <form onSubmit={handleSubmit} className="bg-white shadow-sm rounded-lg p-6">
          <div className="mb-4">
            <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-2">
              Email Address
            </label>
            <input
              type="email"
              id="email"
              name="email"
              value={formData.email}
              onChange={handleChange}
              className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                errors.email ? 'border-red-500' : 'border-gray-300'
              }`}
              placeholder="subscriber@example.com"
            />
            {errors.email && (
              <p className="mt-1 text-sm text-red-600">{errors.email}</p>
            )}
          </div>

          <div className="mb-4">
            <label htmlFor="language" className="block text-sm font-medium text-gray-700 mb-2">
              Language
            </label>
            <select
              id="language"
              name="language"
              value={formData.language}
              onChange={handleChange}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="az">Azerbaijani</option>
              <option value="en">English</option>
              <option value="ru">Russian</option>
            </select>
          </div>

          <div className="mb-6">
            <label htmlFor="status" className="block text-sm font-medium text-gray-700 mb-2">
              Status
            </label>
            <select
              id="status"
              name="status"
              value={formData.status}
              onChange={handleChange}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="active">Active</option>
              <option value="unsubscribed">Unsubscribed</option>
            </select>
          </div>

          {isEdit && subscriber?.data && (
            <div className="mb-6 p-4 bg-gray-50 rounded-lg">
              <h3 className="text-sm font-medium text-gray-700 mb-2">Additional Information</h3>
              <div className="space-y-1 text-sm text-gray-600">
                <p><strong>Token:</strong> {subscriber.data.token}</p>
                <p><strong>Subscribed At:</strong> {subscriber.data.subscribed_at || 'N/A'}</p>
                <p><strong>IP Address:</strong> {subscriber.data.ip_address || 'N/A'}</p>
                <p><strong>Created At:</strong> {subscriber.data.created_at}</p>
                <p><strong>Updated At:</strong> {subscriber.data.updated_at}</p>
              </div>
            </div>
          )}

          <div className="flex justify-end gap-2">
            <button
              type="button"
              onClick={() => navigate('/subscribers')}
              className="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={mutation.isPending}
              className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
            >
              {mutation.isPending ? 'Saving...' : (isEdit ? 'Update' : 'Create')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}