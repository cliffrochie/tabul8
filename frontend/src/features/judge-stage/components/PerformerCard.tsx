import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import type { ContestantDto, JudgeScoreSheet } from '@/types/api';

interface PerformerCardProps {
  contestant: ContestantDto;
  isOnStage: boolean;
  sheet?: JudgeScoreSheet;
}

export function PerformerCard({ contestant, isOnStage, sheet }: PerformerCardProps) {
  const statusVariant =
    sheet?.status === 'LOCKED'
      ? 'success'
      : sheet?.status === 'UNLOCK_REQUESTED'
        ? 'warning'
        : 'secondary';

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between gap-2">
        <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
          Candidate #{contestant.candidate_number}
        </span>
        {isOnStage ? (
          <Badge variant="default">On Stage Now</Badge>
        ) : (
          <Badge variant="secondary">{contestant.category}</Badge>
        )}
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="flex items-start gap-4">
          <div className="w-20 h-24 rounded-lg bg-slate-100 border border-slate-200 flex items-center justify-center text-xs font-medium text-slate-400 shrink-0 overflow-hidden">
            {contestant.photo_url ? (
              <img
                src={contestant.photo_url}
                alt={contestant.name}
                className="w-full h-full object-cover"
              />
            ) : (
              <span>#{contestant.candidate_number}</span>
            )}
          </div>
          <div className="min-w-0 flex-1">
            <h2 className="text-lg font-bold text-slate-900 truncate">{contestant.name}</h2>
            <p className="text-xs text-slate-500 mt-0.5">{contestant.origin_or_group}</p>
            {contestant.performance_meta && (
              <div className="mt-2 rounded-md bg-slate-50 border border-slate-200 px-2.5 py-1.5 text-xs text-slate-700 truncate">
                {contestant.performance_meta}
              </div>
            )}
          </div>
        </div>

        <div className="grid grid-cols-3 gap-2 pt-3 border-t border-slate-100 text-center">
          <div className="rounded-lg bg-slate-50 p-2">
            <div className="text-[10px] font-medium text-slate-500 uppercase">Composite</div>
            <div className="text-base font-bold text-slate-900 tabular-nums">
              {(sheet?.weighted_total ?? 0).toFixed(2)}
            </div>
          </div>
          <div className="rounded-lg bg-slate-50 p-2">
            <div className="text-[10px] font-medium text-slate-500 uppercase">Your Rank</div>
            <div className="text-base font-bold text-slate-900 tabular-nums">
              #{sheet?.personal_rank ?? '—'}
              {sheet?.is_tied ? ' (T)' : ''}
            </div>
          </div>
          <div className="rounded-lg bg-slate-50 p-2 flex flex-col items-center justify-center">
            <div className="text-[10px] font-medium text-slate-500 uppercase mb-1">Status</div>
            <Badge variant={statusVariant}>{sheet?.status ?? 'DRAFT'}</Badge>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
