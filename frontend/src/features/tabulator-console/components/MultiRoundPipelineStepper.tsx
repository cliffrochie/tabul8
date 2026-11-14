import { Lock, Play, RotateCcw, CheckCircle2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { RoundDto, RoundFunnelStep } from '@/types/api';

interface MultiRoundPipelineStepperProps {
  rounds: RoundDto[];
  roundFunnel: RoundFunnelStep[];
  activeRoundId: number | null;
  isSyncing: boolean;
  isReopening: boolean;
  onSelectRound: (roundId: number) => void;
  onReopenRound: (roundId: number) => void;
}

export function MultiRoundPipelineStepper({
  rounds,
  roundFunnel,
  activeRoundId,
  isSyncing,
  isReopening,
  onSelectRound,
  onReopenRound,
}: MultiRoundPipelineStepperProps) {
  if (rounds.length === 0) {
    return null;
  }

  const funnelByRoundId = new Map(roundFunnel.map((f) => [f.round_id, f]));

  return (
    <section
      aria-label="Multi-Round Elimination Pipeline"
      className="grid gap-3 grid-cols-[repeat(auto-fit,minmax(220px,1fr))]"
    >
      {rounds.map((round) => {
        const funnel = funnelByRoundId.get(round.id);
        const isCurrent = round.id === activeRoundId;
        const isFinalized = round.status === 'finalized';

        const statusVariant =
          isFinalized
            ? 'success'
            : round.is_locked
              ? 'warning'
              : isCurrent
                ? 'info'
                : 'default';

        return (
          <div
            key={round.id}
            className={`flex flex-col justify-between rounded-xl border p-4 transition-colors ${
              isCurrent
                ? 'border-indigo-400 bg-indigo-50/40 ring-1 ring-indigo-200'
                : 'border-slate-200 bg-white'
            }`}
          >
            <div className="space-y-2">
              <div className="flex items-center justify-between gap-2">
                <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                  Stage {round.sequence}
                </span>
                <div className="flex items-center gap-1.5">
                  <Badge variant={round.is_back_to_zero ? 'default' : 'info'}>
                    {round.is_back_to_zero ? 'Reset 0' : 'Cumulative'}
                  </Badge>
                  <Badge variant={statusVariant}>
                    {isFinalized && <CheckCircle2 className="mr-1 h-3 w-3" />}
                    {round.status}
                  </Badge>
                </div>
              </div>

              <h3 className="truncate text-sm font-semibold text-slate-900">
                {round.name}
              </h3>

              {funnel && (
                <div className="flex flex-wrap items-center gap-2 text-xs text-slate-600 tabular-nums">
                  <span>
                    <strong className="text-slate-900">
                      {funnel.starting_count}
                    </strong>{' '}
                    entering
                  </span>
                  <span aria-hidden="true">·</span>
                  <span>
                    Top{' '}
                    <strong className="text-emerald-700">
                      {funnel.advancement_count}
                    </strong>{' '}
                    advance
                  </span>
                  {funnel.eliminate_count > 0 && (
                    <>
                      <span aria-hidden="true">·</span>
                      <span className="text-rose-600">
                        -{funnel.eliminate_count} cut
                      </span>
                    </>
                  )}
                </div>
              )}
            </div>

            <div className="mt-3 flex items-center gap-2 border-t border-slate-100 pt-3">
              {!isCurrent && (
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  className="h-7 flex-1 gap-1 text-xs"
                  disabled={isSyncing}
                  onClick={() => onSelectRound(round.id)}
                >
                  {round.is_locked ? (
                    <Lock className="h-3 w-3" />
                  ) : (
                    <Play className="h-3 w-3" />
                  )}
                  Switch Stage
                </Button>
              )}
              {isCurrent && (
                <span className="flex-1 text-xs font-medium text-indigo-700">
                  Active Stage
                </span>
              )}

              {isFinalized && (
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  className="h-7 gap-1 text-xs text-amber-700 hover:bg-amber-50"
                  disabled={isReopening}
                  onClick={() => onReopenRound(round.id)}
                >
                  <RotateCcw className="h-3 w-3" />
                  Reopen Round
                </Button>
              )}
            </div>
          </div>
        );
      })}
    </section>
  );
}
