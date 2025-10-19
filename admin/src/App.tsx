import { useEffect, useState } from 'react';
import axios from 'axios';

function App() {
  const [apiMessage, setApiMessage] = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchApiMessage();
  }, []);

  const fetchApiMessage = async () => {
    try {
      const apiUrl = import.meta.env.VITE_API_URL || 'http://100.89.150.50:8007/api';
      const response = await axios.get(`${apiUrl}/hello`);
      setApiMessage(response.data.message);
    } catch (error) {
      console.error('API Error:', error);
      setApiMessage('Failed to fetch from API');
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '100vh' }}>
        <p>Loading...</p>
      </div>
    );
  }

  return (
    <div style={{
      display: 'flex',
      justifyContent: 'center',
      alignItems: 'center',
      minHeight: '100vh',
      background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
      fontFamily: 'system-ui, -apple-system, sans-serif'
    }}>
      <div style={{
        textAlign: 'center',
        padding: '48px',
        backgroundColor: 'white',
        borderRadius: '16px',
        boxShadow: '0 20px 60px rgba(0,0,0,0.3)',
        maxWidth: '600px',
        margin: '16px'
      }}>
        <h1 style={{
          fontSize: '48px',
          fontWeight: 'bold',
          marginBottom: '24px',
          color: '#1a202c'
        }}>
          Alert.az Admin Panel
        </h1>
        <p style={{
          fontSize: '24px',
          color: '#4a5568',
          marginBottom: '16px'
        }}>
          Hello World from Admin!
        </p>
        <div style={{
          marginTop: '32px',
          padding: '16px',
          backgroundColor: '#f7fafc',
          borderRadius: '8px',
          border: '1px solid #e2e8f0'
        }}>
          <p style={{
            fontSize: '14px',
            color: '#718096',
            marginBottom: '8px'
          }}>
            API Response:
          </p>
          <p style={{
            fontSize: '18px',
            fontWeight: '600',
            color: '#667eea'
          }}>
            {apiMessage}
          </p>
        </div>
        <div style={{
          marginTop: '24px',
          fontSize: '14px',
          color: '#718096'
        }}>
          <p>Port: 4007</p>
          <p>Backend API: Port 8007</p>
          <p>Path: /admin/</p>
        </div>
      </div>
    </div>
  );
}

export default App;
