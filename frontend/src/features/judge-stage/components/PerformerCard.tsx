import { Badge } from '@/components/ui/badge';
import type { ContestantDto, JudgeScoreSheet } from '@/types/api';

interface PerformerCardProps {
  contestant: ContestantDto;
  isOnStage: boolean;
  sheet?: JudgeScoreSheet;
}

export function PerformerCard({ contestant, isOnStage, sheet }: PerformerCardProps) {
  const status = sheet?.status ?? 'DRAFT';
  const statusVariant =
    status === 'LOCKED'
      ? 'success'
      : status === 'UNLOCK_REQUESTED'
        ? 'warning'
        : 'secondary';

  return (
    <div className="wf-box overflow-hidden">
      <div className="bg-slate-900 text-white px-4 py-3 flex items-center justify-between font-mono-tech text-xs">
        <span className="font-bold">[WF-01] Candidate #{contestant.candidate_number}</span>
        {isOnStage ? (
          <span className="px-2 py-0.5 rounded-full bg-emerald-400 text-slate-950 font-bold text-[10px]">
            ON STAGE NOW
          </span>
        ) : (
          <span className="px-2 py-0.5 rounded bg-slate-800 text-slate-300 text-[10px]">
            {contestant.category}
          </span>
        )}
      </div>

      <div className="p-4 space-y-3.5">
        <div className="grid grid-cols-3 gap-3.5 items-start">
          <div className="col-span-1">
            <div className="h-28 w-full rounded-lg bg-slate-100 border border-slate-200 flex flex-col items-center justify-center text-xs font-mono-tech font-medium text-slate-400 overflow-hidden">
              {contestant.photo_url ? (
                <img
                  src={contestant.photo_url}
                  alt={contestant.name}
                  className="w-full h-full object-cover"
                />
              ) : (
                <>
                  <span className="text-base font-bold text-slate-600">
                    #{contestant.candidate_number}
                  </span>
                  <span className="text-[10px] text-slate-400 mt-0.5">4:5</span>
                </>
              )}
            </div>
          </div>
          <div className="col-span-2 min-w-0">
            <div className="text-[11px] font-mono-tech uppercase text-slate-400 font-semibold">
              Candidate #{String(contestant.candidate_number).padStart(2, '0')}
            </div>
            <h2 className="text-lg font-extrabold text-slate-900 tracking-tight truncate mt-0.5">
              {contestant.name}
            </h2>
            <p className="text-xs text-slate-600 font-medium mt-0.5 truncate">
              Origin: <strong className="text-slate-900">{contestant.origin_or_group}</strong>
            </p>
            {contestant.performance_meta && (
              <div className="mt-2.5 px-2.5 py-1.5 rounded-lg bg-slate-50 border border-slate-200 text-[11px] font-mono-tech text-slate-700 truncate">
                {contestant.performance_meta}
              </div>
            )}
          </div>
        </div>

        <div className="grid grid-cols-3 gap-2 pt-3 border-t border-slate-100 font-mono-tech text-center">
          <div className="p-2.5 rounded-xl bg-slate-50 border border-slate-200/80">
            <span className="block text-[9px] text-slate-400 uppercase font-semibold">
              Composite
            </span>
            <span className="text-base font-bold text-slate-900">
              {(sheet?.weighted_total ?? 0).toFixed(2)}
            </span>
          </div>
          <div className="p-2.5 rounded-xl bg-slate-50 border border-slate-200/80">
            <span className="block text-[9px] text-slate-400 uppercase font-semibold">
              Your Rank
            </span>
            <span className="text-base font-bold text-slate-900">
              #{sheet?.personal_rank ?? '—'}
              {sheet?.is_tied ? ' (T)' : ''}
            </span>
          </div>
          <div className="p-2.5 rounded-xl bg-slate-50 border border-slate-200/80 flex flex-col items-center justify-center">
            <span className="block text-[9px] text-slate-400 uppercase font-semibold mb-0.5">
              Sheet State
            </span>
            <Badge variant={statusVariant}>{status}</Badge>
          </div>
        </div>
      </div>
    </div>
  );
}
