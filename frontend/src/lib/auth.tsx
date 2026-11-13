import type { ReactNode } from 'react';
import { Navigate } from 'react-router-dom';
import { useAuthStore } from '@/stores/authStore';

interface ProtectedRouteProps {
  children: ReactNode;
  allowedActor: 'judge' | 'user';
}

export function ProtectedRoute({ children, allowedActor }: ProtectedRouteProps) {
  const { isAuthenticated, actorType, hasHydrated } = useAuthStore();

  if (!hasHydrated) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-slate-50 text-slate-600 text-sm">
        Restoring session...
      </div>
    );
  }

  if (!isAuthenticated || actorType !== allowedActor) {
    return <Navigate to={allowedActor === 'judge' ? '/login/judge' : '/login/admin'} replace />;
  }

  return <>{children}</>;
}
