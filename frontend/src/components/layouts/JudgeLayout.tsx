import type { ReactNode } from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import { LogOut, Wifi, WifiOff } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useAuthStore } from '@/stores/authStore';
import { useOfflineQueueStore } from '@/stores/offlineQueueStore';
import { useStageStore } from '@/stores/stageStore';
import { apiClient } from '@/lib/api-client';
import { resetEcho } from '@/lib/echo';

interface JudgeLayoutProps {
  eventTitle?: string;
  onFlushQueue?: () => void;
  children: ReactNode;
}

export function JudgeLayout({ eventTitle, onFlushQueue, children }: JudgeLayoutProps) {
  const navigate = useNavigate();
  const { judge, clearAuth } = useAuthStore();
  const { isOnline, queue, isFlushing } = useOfflineQueueStore();
  const { followStageLock, setFollowStageLock } = useStageStore();

  const handleLogout = async () => {
    try {
      await apiClient.delete('/auth/logout');
    } catch {
      // Ignore network errors on logout
    } finally {
      resetEcho();
      clearAuth();
      navigate('/login/judge');
    }
  };

  return (
    <div className="min-h-screen flex flex-col bg-slate-50">
      <header className="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur-xs">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 h-14 flex items-center justify-between gap-4">
          <div className="flex items-center gap-3 min-w-0">
            <div className="h-8 w-8 rounded-lg bg-slate-900 text-white font-semibold text-xs flex items-center justify-center shrink-0">
              T8
            </div>
            <div className="min-w-0">
              <div className="text-sm font-semibold text-slate-900 truncate">
                {eventTitle ?? 'TABUL8 Judge Terminal'}
              </div>
              <div className="text-xs text-slate-500 truncate">
                Judge #{judge?.judge_number} • {judge?.name}
              </div>
            </div>
          </div>

          <nav className="flex items-center gap-1 bg-slate-100 p-1 rounded-lg">
            <NavLink
              to="/judge/stage"
              className={({ isActive }) =>
                `px-3 py-1.5 text-xs font-medium rounded-md transition-colors ${
                  isActive ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900'
                }`
              }
            >
              Stage View
            </NavLink>
            <NavLink
              to="/judge/matrix"
              className={({ isActive }) =>
                `px-3 py-1.5 text-xs font-medium rounded-md transition-colors ${
                  isActive ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900'
                }`
              }
            >
              Matrix Grid
            </NavLink>
          </nav>

          <div className="flex items-center gap-3">
            <label className="hidden md:flex items-center gap-1.5 text-xs text-slate-600 cursor-pointer select-none">
              <input
                type="checkbox"
                checked={followStageLock}
                onChange={(e) => setFollowStageLock(e.target.checked)}
                className="rounded border-slate-300 text-slate-900 focus:ring-slate-900"
              />
              Auto-Sync Stage
            </label>

            <Badge variant={isOnline ? 'success' : 'warning'} className="gap-1">
              {isOnline ? <Wifi className="w-3 h-3" /> : <WifiOff className="w-3 h-3" />}
              {isOnline ? 'LAN Live' : 'Offline'}
            </Badge>

            <Button variant="ghost" size="sm" onClick={handleLogout} title="Sign Out">
              <LogOut className="w-4 h-4" />
            </Button>
          </div>
        </div>
      </header>

      {(!isOnline || queue.length > 0) && (
        <div className="bg-amber-50 border-b border-amber-200 px-4 py-2">
          <div className="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-2 text-xs text-amber-900">
            <span>
              {!isOnline
                ? 'Wi-Fi disconnected. Scores are safely queued in IndexedDB and will sync automatically.'
                : `${queue.length} queued score packet(s) pending synchronization.`}
            </span>
            <div className="flex items-center gap-2">
              <Badge variant="warning" className="tabular-nums">
                Queued: {queue.length}
              </Badge>
              {onFlushQueue && (
                <Button size="sm" variant="outline" disabled={isFlushing} onClick={onFlushQueue}>
                  {isFlushing ? 'Syncing...' : 'Sync Now'}
                </Button>
              )}
            </div>
          </div>
        </div>
      )}

      <main className="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6">{children}</main>
    </div>
  );
}
