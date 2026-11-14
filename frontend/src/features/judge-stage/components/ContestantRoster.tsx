import { Badge } from '@/components/ui/badge';
import { cn } from '@/components/ui/cn';
import type { ContestantDto, JudgeScoreSheet } from '@/types/api';

interface ContestantRosterProps {
  contestants: ContestantDto[];
  selectedId: number;
  onStageId: number | null;
  myScores?: Record<string, JudgeScoreSheet>;
  onSelect: (id: number) => void;
  isOpenMobile?: boolean;
}

export function ContestantRoster({
  contestants,
  selectedId,
  onStageId,
  myScores,
  onSelect,
  isOpenMobile = false,
}: ContestantRosterProps) {
  return (
    <div
      className={cn(
        'wf-box overflow-hidden flex-1 flex-col',
        isOpenMobile ? 'flex' : 'hidden lg:flex'
      )}
    >
      <div className="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between font-mono-tech text-xs">
        <span className="font-bold text-slate-800">
          [WF-02] Contestant Roster ({contestants.length})
        </span>
        <span className="text-[10px] text-slate-500">Tap to Select</span>
      </div>
      <div className="divide-y divide-slate-100 overflow-y-auto max-h-[380px]">
        {contestants.map((c) => {
          const sheet = myScores?.[String(c.id)];
          const isSelected = c.id === selectedId;
          const isOnStage = c.id === onStageId;
          const status = sheet?.status ?? 'DRAFT';

          return (
            <button
              key={c.id}
              type="button"
              onClick={() => onSelect(c.id)}
              className={cn(
                'w-full text-left px-4 py-3 flex items-center justify-between gap-2 transition-colors cursor-pointer',
                isSelected
                  ? 'bg-slate-900 text-white'
                  : 'bg-white hover:bg-slate-50 text-slate-900'
              )}
            >
              <div className="flex items-center gap-3 min-w-0">
                <span
                  className={cn(
                    'w-8 h-8 rounded-lg border font-mono-tech text-xs font-bold flex items-center justify-center shrink-0',
                    isSelected
                      ? 'border-slate-700 bg-slate-800 text-white'
                      : 'border-slate-200 bg-slate-100 text-slate-900'
                  )}
                >
                  #{c.candidate_number}
                </span>
                <div className="min-w-0">
                  <div className="flex items-center gap-1.5">
                    <span className="text-xs font-bold truncate">{c.name}</span>
                    {isOnStage && (
                      <span
                        className={cn(
                          'text-[9px] font-mono-tech px-1.5 py-0.5 rounded-full font-bold',
                          isSelected
                            ? 'bg-emerald-400 text-slate-950'
                            : 'bg-emerald-100 text-emerald-800'
                        )}
                      >
                        STAGE
                      </span>
                    )}
                  </div>
                  <div
                    className={cn(
                      'text-[11px] truncate',
                      isSelected ? 'text-slate-300' : 'text-slate-500'
                    )}
                  >
                    {c.origin_or_group}
                  </div>
                </div>
              </div>

              <div className="flex items-center gap-2.5 shrink-0 font-mono-tech">
                <div className="text-right">
                  <div className="text-xs font-bold">
                    {(sheet?.weighted_total ?? 0).toFixed(2)}
                  </div>
                  <div
                    className={cn(
                      'text-[10px]',
                      isSelected ? 'text-slate-300' : 'text-slate-500'
                    )}
                  >
                    Rank #{sheet?.personal_rank ?? '—'}
                  </div>
                </div>
                <Badge
                  variant={
                    status === 'LOCKED'
                      ? 'success'
                      : status === 'UNLOCK_REQUESTED'
                        ? 'warning'
                        : 'secondary'
                  }
                  className="text-[10px]"
                >
                  {status}
                </Badge>
              </div>
            </button>
          );
        })}
      </div>
    </div>
  );
}
