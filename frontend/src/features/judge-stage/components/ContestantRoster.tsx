import { Badge } from '@/components/ui/badge';
import { Card, CardHeader } from '@/components/ui/card';
import { cn } from '@/components/ui/cn';
import type { ContestantDto, JudgeScoreSheet } from '@/types/api';

interface ContestantRosterProps {
  contestants: ContestantDto[];
  selectedId: number;
  onStageId: number | null;
  myScores?: Record<string, JudgeScoreSheet>;
  onSelect: (id: number) => void;
}

export function ContestantRoster({
  contestants,
  selectedId,
  onStageId,
  myScores,
  onSelect,
}: ContestantRosterProps) {
  return (
    <Card className="flex-1 flex flex-col overflow-hidden">
      <CardHeader className="flex flex-row items-center justify-between">
        <span className="text-xs font-semibold text-slate-700 uppercase tracking-wider">
          Contestant Lineup ({contestants.length})
        </span>
      </CardHeader>
      <div className="divide-y divide-slate-100 overflow-y-auto max-h-[380px]">
        {contestants.map((c) => {
          const sheet = myScores?.[String(c.id)];
          const isSelected = c.id === selectedId;
          const isOnStage = c.id === onStageId;

          return (
            <button
              key={c.id}
              type="button"
              onClick={() => onSelect(c.id)}
              className={cn(
                'w-full text-left px-4 py-3 flex items-center justify-between gap-3 transition-colors cursor-pointer',
                isSelected ? 'bg-slate-900 text-white' : 'bg-white hover:bg-slate-50 text-slate-900'
              )}
            >
              <div className="flex items-center gap-3 min-w-0">
                <span
                  className={cn(
                    'w-7 h-7 rounded-md text-xs font-semibold flex items-center justify-center shrink-0 tabular-nums',
                    isSelected ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-700'
                  )}
                >
                  #{c.candidate_number}
                </span>
                <div className="min-w-0">
                  <div className="flex items-center gap-1.5">
                    <span className="text-sm font-medium truncate">{c.name}</span>
                    {isOnStage && (
                      <Badge variant={isSelected ? 'secondary' : 'default'} className="text-[10px]">
                        Stage
                      </Badge>
                    )}
                  </div>
                  <div className={cn('text-xs truncate', isSelected ? 'text-slate-300' : 'text-slate-500')}>
                    {c.origin_or_group}
                  </div>
                </div>
              </div>

              <div className="text-right shrink-0 tabular-nums">
                <div className="text-xs font-semibold">{(sheet?.weighted_total ?? 0).toFixed(2)}</div>
                <div className={cn('text-[10px]', isSelected ? 'text-slate-300' : 'text-slate-500')}>
                  {sheet?.status ?? 'DRAFT'}
                </div>
              </div>
            </button>
          );
        })}
      </div>
    </Card>
  );
}
