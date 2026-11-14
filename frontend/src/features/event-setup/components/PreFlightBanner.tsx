import { AlertTriangle, CheckCircle2, Play } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { EventDto } from '@/types/api';
import type { PreflightStatus } from '../api/setupApi';

interface PreFlightBannerProps {
  event: EventDto;
  preflight?: PreflightStatus;
  isUpdating: boolean;
  onGoLive: () => void;
}

export function PreFlightBanner({
  event,
  preflight,
  isUpdating,
  onGoLive,
}: PreFlightBannerProps) {
  const isLive = event.status === 'live';
  const ready = Boolean(preflight?.ready);

  return (
    <div
      className={`rounded-2xl border p-4 ${
        isLive
          ? 'border-emerald-200 bg-emerald-50/70'
          : ready
            ? 'border-indigo-200 bg-indigo-50/50'
            : 'border-amber-300 bg-amber-50/80'
      }`}
    >
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="space-y-1">
          <div className="flex items-center gap-2">
            <Badge
              variant={
                isLive ? 'success' : ready ? 'info' : 'warning'
              }
            >
              Status: {event.status.toUpperCase()}
            </Badge>
            <h2 className="text-sm font-bold text-slate-900">
              {isLive
                ? 'Competition Live on Stage'
                : ready
                  ? 'Pre-Flight Checks Passed — Ready to Launch'
                  : 'Pre-Flight Checklist Incomplete'}
            </h2>
          </div>

          {preflight && (
            <div className="flex flex-wrap gap-2 pt-1">
              {preflight.checks.map((chk) => (
                <span
                  key={chk.key}
                  className={`inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs ${
                    chk.ok
                      ? 'bg-emerald-100/80 text-emerald-800'
                      : 'bg-amber-200/70 text-amber-950 font-medium'
                  }`}
                >
                  {chk.ok ? (
                    <CheckCircle2 className="h-3 w-3" />
                  ) : (
                    <AlertTriangle className="h-3 w-3" />
                  )}
                  {chk.message}
                </span>
              ))}
            </div>
          )}
        </div>

        {!isLive && (
          <Button
            type="button"
            disabled={!ready || isUpdating}
            onClick={onGoLive}
            className="gap-1.5"
          >
            <Play className="h-4 w-4" />
            {isUpdating ? 'Launching...' : 'Set Event Live'}
          </Button>
        )}
      </div>
    </div>
  );
}
