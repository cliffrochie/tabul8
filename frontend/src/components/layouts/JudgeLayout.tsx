import type { ReactNode } from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import { LogOut, Wifi, WifiOff } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/components/ui/cn';
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
    <div className="min-h-screen flex flex-col pb-20 lg:pb-6 selection:bg-slate-900 selection:text-white">
      {/* Top Global Header */}
      <header className="bg-white/95 backdrop-blur-md border-b border-slate-200 sticky top-0 z-40">
        <div className="max-w-[1600px] mx-auto px-3 sm:px-6 h-16 flex items-center justify-between gap-3">
          <div className="flex items-center gap-3 min-w-0">
            <div className="w-10 h-10 rounded-xl bg-slate-900 text-white flex items-center justify-center font-mono-tech font-bold text-sm shrink-0 shadow-sm">
              T8
            </div>
            <div className="min-w-0">
              <div className="flex items-center gap-2">
                <span className="font-extrabold text-sm sm:text-base tracking-tight text-slate-900 truncate">
                  TABUL8
                </span>
                <span className="text-[10px] font-mono-tech px-2 py-0.5 rounded-full border border-emerald-200 bg-emerald-50 text-emerald-700 font-semibold whitespace-nowrap">
                  JUDGE TERMINAL
                </span>
              </div>
              <p className="text-[11px] text-slate-500 hidden xl:block truncate">
                {eventTitle ?? 'LAN-First Competition Tabulator • Real-Time Scoring'}
              </p>
            </div>
          </div>

          {/* Desktop View Switcher (Hidden on Mobile, replaced by Bottom Nav) */}
          <nav
            aria-label="Judge Views"
            className="hidden lg:flex items-center gap-1 bg-slate-100/80 p-1 rounded-xl border border-slate-200/80"
          >
            <NavLink
              to="/judge/stage"
              className={({ isActive }) =>
                cn(
                  'px-3 py-1.5 text-xs font-semibold rounded-lg whitespace-nowrap transition-all',
                  isActive
                    ? 'bg-slate-900 text-white shadow-sm'
                    : 'text-slate-600 hover:text-slate-900'
                )
              }
            >
              1. Judge Stage
            </NavLink>
            <NavLink
              to="/judge/matrix"
              className={({ isActive }) =>
                cn(
                  'px-3 py-1.5 text-xs font-semibold rounded-lg whitespace-nowrap transition-all',
                  isActive
                    ? 'bg-slate-900 text-white shadow-sm'
                    : 'text-slate-600 hover:text-slate-900'
                )
              }
            >
              2. Judge Matrix
            </NavLink>
          </nav>

          {/* Right Status & Controls */}
          <div className="flex items-center gap-1.5 sm:gap-2 shrink-0">
            <span
              className={cn(
                'inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border text-xs font-mono-tech font-semibold',
                isOnline
                  ? 'bg-white border-slate-200 text-slate-800'
                  : 'bg-amber-50 border-amber-300 text-amber-800'
              )}
            >
              <span
                className={cn(
                  'w-2 h-2 rounded-full inline-block',
                  isOnline ? 'bg-emerald-500' : 'bg-amber-500 animate-pulse'
                )}
              />
              {isOnline ? <Wifi className="w-3.5 h-3.5 hidden sm:inline text-emerald-600" /> : <WifiOff className="w-3.5 h-3.5 hidden sm:inline text-amber-600" />}
              <span className="hidden sm:inline">{isOnline ? 'LAN SYNC: ON' : 'OFFLINE'}</span>
              <span className="sm:hidden text-[10px] font-bold">{isOnline ? 'LAN' : 'OFF'}</span>
            </span>

            <Button
              variant="outline"
              size="sm"
              onClick={handleLogout}
              title="Sign Out"
              aria-label="Sign Out"
              className="gap-1.5"
            >
              <LogOut className="w-4 h-4" />
              <span className="hidden sm:inline text-xs">Sign Out</span>
            </Button>
          </div>
        </div>
      </header>

      {/* Offline Queue Banner */}
      {(!isOnline || queue.length > 0) && (
        <div className="bg-amber-950 text-amber-50 border-b border-amber-800 px-3 sm:px-6 py-2.5">
          <div className="max-w-[1600px] mx-auto flex flex-wrap items-center justify-between gap-2 text-xs font-mono-tech">
            <div className="flex items-center gap-2">
              <span className="px-2 py-0.5 rounded bg-amber-500 text-slate-950 font-bold">
                {!isOnline ? 'WARN: WS_DISCONNECTED' : 'SYNC_PENDING'}
              </span>
              <span>
                {!isOnline
                  ? 'Wi-Fi drop active: score mutations are buffered locally (IndexedDB).'
                  : `${queue.length} queued score packet(s) pending synchronization.`}
              </span>
            </div>
            <div className="flex items-center gap-3">
              <span className="px-2.5 py-1 rounded bg-amber-900/80 border border-amber-700">
                QUEUED: <strong>{queue.length}</strong>
              </span>
              {onFlushQueue && (
                <button
                  type="button"
                  disabled={isFlushing}
                  onClick={onFlushQueue}
                  className="px-3 py-1 rounded-md bg-white text-slate-900 font-bold hover:bg-amber-100 cursor-pointer transition-colors disabled:opacity-50"
                >
                  {isFlushing ? 'Syncing...' : 'Flush Queue'}
                </button>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Judge Session Sub-Bar */}
      <div className="bg-slate-100/90 border-b border-slate-200 px-3 sm:px-6 py-2">
        <div className="max-w-[1600px] mx-auto flex flex-wrap items-center justify-between gap-2.5 text-xs">
          <div className="flex flex-wrap items-center gap-2 sm:gap-3 min-w-0">
            <span className="font-mono-tech font-bold uppercase text-slate-500 text-[11px]">
              Judge Terminal:
            </span>
            <span className="bg-white border border-slate-300 px-2.5 py-1 font-semibold text-slate-900 rounded-lg text-xs truncate">
              Judge #{judge?.judge_number ?? '—'} • {judge?.name ?? 'Guest'}
            </span>
            {eventTitle && (
              <span className="hidden md:inline text-slate-600 text-xs truncate">
                {eventTitle}
              </span>
            )}
          </div>

          <div className="flex items-center gap-2.5 font-mono-tech text-[11px]">
            <label className="flex items-center gap-1.5 cursor-pointer select-none bg-white px-2.5 py-1 rounded-lg border border-slate-200">
              <input
                type="checkbox"
                checked={followStageLock}
                onChange={(e) => setFollowStageLock(e.target.checked)}
                className="w-3.5 h-3.5 accent-slate-900 rounded"
              />
              <span className="font-medium text-slate-700">Auto-Sync Stage</span>
            </label>
          </div>
        </div>
      </div>

      {/* Main Workspace */}
      <main className="flex-1 max-w-[1600px] w-full mx-auto p-3 sm:p-6">{children}</main>

      {/* Mobile Bottom Navigation Bar (< lg) */}
      <nav
        aria-label="Mobile Judge Navigation"
        className="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-white/95 backdrop-blur-md border-t border-slate-200 px-3 py-1.5 shadow-[0_-4px_16px_rgba(15,23,42,0.06)]"
      >
        <div className="grid grid-cols-2 gap-2 max-w-md mx-auto">
          <NavLink
            to="/judge/stage"
            className={({ isActive }) =>
              cn(
                'flex flex-col items-center justify-center py-1.5 px-2 rounded-lg text-[11px] font-bold transition-colors',
                isActive ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'
              )
            }
          >
            <span className="font-mono-tech text-xs leading-none mb-0.5">01</span>
            <span>Stage View</span>
          </NavLink>
          <NavLink
            to="/judge/matrix"
            className={({ isActive }) =>
              cn(
                'flex flex-col items-center justify-center py-1.5 px-2 rounded-lg text-[11px] font-bold transition-colors',
                isActive ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'
              )
            }
          >
            <span className="font-mono-tech text-xs leading-none mb-0.5">02</span>
            <span>Matrix Grid</span>
          </NavLink>
        </div>
      </nav>
    </div>
  );
}
