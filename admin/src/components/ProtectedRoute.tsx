import { Navigate, useLocation } from 'react-router-dom';
import { authService } from '../services/auth';

interface ProtectedRouteProps {
  children: React.ReactNode;
}

export default function ProtectedRoute({ children }: ProtectedRouteProps) {
  const isAuthenticated = authService.isAuthenticated();
  const location = useLocation();

  if (!isAuthenticated) {
    // Store the attempted location
    sessionStorage.setItem('redirectTo', location.pathname);
    return <Navigate to="/login" replace />;
  }

  return <>{children}</>;
}