import type { ReactNode } from 'react';
import { LogOut, ShieldCheck, AlertCircle } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { resetEcho } from '@/lib/echo';
import { useAuthStore } from '@/stores/authStore';

interface ConsoleLayoutProps {
  eventTitle?: string;
  unlockRequestCount?: number;
  onlineJudgesCount?: number;
  totalJudgesCount?: number;
  children: ReactNode;
}

export function ConsoleLayout({
  eventTitle = 'TABUL8 Console',
  unlockRequestCount = 0,
  onlineJudgesCount = 0,
  totalJudgesCount = 0,
  children,
}: ConsoleLayoutProps) {
  const navigate = useNavigate();
  const user = useAuthStore((state) => state.user);
  const clearAuth = useAuthStore((state) => state.clearAuth);

  const handleLogout = () => {
    resetEcho();
    clearAuth();
    navigate('/login/admin', { replace: true });
  };

  return (
    <div className="min-h-screen bg-slate-50 text-slate-900">
      <header className="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
          <div className="flex items-center gap-3">
            <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-900 text-sm font-bold text-white">
              T8
            </div>
            <div>
              <h1 className="text-base font-semibold leading-tight text-slate-900">
                {eventTitle}
              </h1>
              <p className="text-xs text-slate-500">
                Real-Time Tabulation &amp; Stage Control
              </p>
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-2.5">
            {totalJudgesCount > 0 && (
              <Badge variant="info" className="gap-1.5">
                <span className="h-2 w-2 rounded-full bg-blue-600" />
                Judges Online: {onlineJudgesCount}/{totalJudgesCount}
              </Badge>
            )}

            {unlockRequestCount > 0 && (
              <Badge variant="warning" className="gap-1.5">
                <AlertCircle className="h-3.5 w-3.5" />
                {unlockRequestCount} Unlock{' '}
                {unlockRequestCount === 1 ? 'Request' : 'Requests'}
              </Badge>
            )}

            {user && (
              <Badge variant="default" className="gap-1.5">
                <ShieldCheck className="h-3.5 w-3.5 text-emerald-600" />
                {user.name} ({user.role})
              </Badge>
            )}

            <Button
              variant="outline"
              size="sm"
              onClick={handleLogout}
              className="gap-1.5"
            >
              <LogOut className="h-3.5 w-3.5" />
              Sign out
            </Button>
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-7xl px-4 py-6 sm:px-6">{children}</main>
    </div>
  );
}
