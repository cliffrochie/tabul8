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
    <div className="min-h-screen text-slate-900 selection:bg-slate-900 selection:text-white">
      <header className="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur-md">
        <div className="mx-auto flex max-w-[1600px] flex-wrap items-center justify-between gap-3 px-3 py-3 sm:px-6">
          <div className="flex items-center gap-3 min-w-0">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-900 font-mono-tech text-sm font-bold text-white shadow-sm">
              T8
            </div>
            <div className="min-w-0">
              <div className="flex items-center gap-2">
                <h1 className="truncate text-sm sm:text-base font-extrabold leading-tight tracking-tight text-slate-900">
                  {eventTitle}
                </h1>
                <span className="hidden sm:inline-block rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 font-mono-tech text-[10px] font-semibold text-emerald-700">
                  CONSOLE
                </span>
              </div>
              <p className="text-[11px] text-slate-500 truncate">
                Real-Time Tabulation &amp; Stage Control
              </p>
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            {totalJudgesCount > 0 && (
              <Badge variant="info" className="gap-1.5 font-mono-tech">
                <span className="h-2 w-2 rounded-full bg-blue-600" />
                <span className="hidden sm:inline">Judges Online:</span>
                {onlineJudgesCount}/{totalJudgesCount}
              </Badge>
            )}

            {unlockRequestCount > 0 && (
              <Badge variant="warning" className="gap-1.5 font-mono-tech">
                <AlertCircle className="h-3.5 w-3.5" />
                {unlockRequestCount} Unlock{' '}
                {unlockRequestCount === 1 ? 'Req' : 'Reqs'}
              </Badge>
            )}

            {user && (
              <Badge variant="default" className="hidden md:inline-flex gap-1.5 font-mono-tech">
                <ShieldCheck className="h-3.5 w-3.5 text-emerald-400" />
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
              <span className="hidden sm:inline">Sign out</span>
            </Button>
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-[1600px] p-3 sm:p-6">{children}</main>
    </div>
  );
}
