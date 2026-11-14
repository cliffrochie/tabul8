import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/components/ui/cn';
import type { ContestantDto, CriterionDto, JudgeScoreSheet } from '@/types/api';

interface JudgeMatrixGridProps {
  roundName: string;
  isRoundFrozen: boolean;
  contestants: ContestantDto[];
  criteria: CriterionDto[];
  myScores?: Record<string, JudgeScoreSheet>;
  onScoreChange: (contestantId: number, criterionId: number, nextVal: number) => void;
  onLockRow: (contestantId: number) => void;
  onLockAll: () => void;
  onRequestUnlock: (contestantId: number) => void;
}

export function JudgeMatrixGrid({
  roundName,
  isRoundFrozen,
  contestants,
  criteria,
  myScores,
  onScoreChange,
  onLockRow,
  onLockAll,
  onRequestUnlock,
}: JudgeMatrixGridProps) {
  return (
    <div className="wf-box overflow-hidden">
      <div className="p-4 sm:px-6 bg-slate-900 text-white flex flex-wrap items-center justify-between gap-4">
        <div>
          <span className="text-[11px] font-mono-tech uppercase text-slate-400">
            [WF-04] Comparative Matrix Grid • {roundName}
          </span>
          <h2 className="text-base sm:text-lg font-extrabold tracking-tight mt-0.5">
            Side-by-Side Candidate Evaluation Matrix
          </h2>
        </div>
        <button
          type="button"
          disabled={isRoundFrozen}
          onClick={onLockAll}
          className="w-full sm:w-auto rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-950 border border-emerald-400 px-4 py-2 text-xs font-bold transition-all cursor-pointer disabled:opacity-45 disabled:cursor-not-allowed"
        >
          Lock &amp; Submit Entire Round Sheet
        </button>
      </div>

      {/* Mobile Card Stack (< md) */}
      <div className="md:hidden p-4 space-y-3">
        {contestants.map((c) => {
          const sheet = myScores?.[String(c.id)];
          const status = sheet?.status ?? 'DRAFT';
          const isLocked = status === 'LOCKED' || status === 'UNLOCK_REQUESTED' || isRoundFrozen;

          return (
            <div
              key={c.id}
              className={cn('wf-box-sm p-4 space-y-3', isLocked ? 'wf-hatch' : 'bg-white')}
            >
              <div className="flex items-center justify-between gap-2 pb-2 border-b border-slate-200">
                <div className="flex items-center gap-2.5 min-w-0">
                  <span className="w-7 h-7 rounded-lg bg-slate-900 text-white font-mono-tech text-xs font-bold flex items-center justify-center shrink-0">
                    #{c.candidate_number}
                  </span>
                  <div className="min-w-0">
                    <div className="font-bold text-sm text-slate-900 truncate">{c.name}</div>
                    <div className="text-[11px] text-slate-500 truncate">{c.origin_or_group}</div>
                  </div>
                </div>
                <div className="text-right font-mono-tech shrink-0">
                  <div className="text-sm font-extrabold text-slate-900">
                    {(sheet?.weighted_total ?? 0).toFixed(2)}
                  </div>
                  <div
                    className={cn(
                      'text-[10px] font-bold',
                      sheet?.personal_rank === 1 ? 'text-emerald-600' : 'text-slate-500'
                    )}
                  >
                    Rank #{sheet?.personal_rank ?? '—'}
                    {sheet?.is_tied ? ' (Tied)' : ''}
                  </div>
                </div>
              </div>

              <div>
                {criteria.map((crit) => {
                  const val = sheet?.criteria?.[String(crit.id)] ?? crit.min_score;
                  const clamp = (raw: number) =>
                    Math.min(crit.max_score, Math.max(crit.min_score, Number(raw.toFixed(2))));

                  return (
                    <div
                      key={crit.id}
                      className="flex items-center justify-between gap-2 py-2 border-b border-slate-100 last:border-b-0"
                    >
                      <div className="min-w-0">
                        <div className="text-xs font-semibold text-slate-800 truncate">
                          {crit.name}
                        </div>
                        <div className="text-[10px] font-mono-tech text-slate-400">
                          Weight: {crit.weight_percentage}%
                        </div>
                      </div>
                      <div className="inline-flex items-center gap-1.5 shrink-0">
                        <Button
                          type="button"
                          size="sm"
                          variant="outline"
                          disabled={isLocked || val <= crit.min_score}
                          onClick={() => onScoreChange(c.id, crit.id, clamp(val - crit.score_step))}
                          className="w-8 h-8 p-0 font-mono-tech font-bold"
                        >
                          -
                        </Button>
                        <input
                          type="number"
                          aria-label={`${c.name} ${crit.name}`}
                          disabled={isLocked}
                          min={crit.min_score}
                          max={crit.max_score}
                          step={crit.score_step}
                          value={val}
                          onChange={(e) => {
                            const parsed = parseFloat(e.target.value);
                            if (!Number.isNaN(parsed)) onScoreChange(c.id, crit.id, clamp(parsed));
                          }}
                          className="w-16 text-center font-mono-tech font-bold text-xs py-1 border border-slate-300 rounded-lg bg-white disabled:bg-slate-100"
                        />
                        <Button
                          type="button"
                          size="sm"
                          variant="outline"
                          disabled={isLocked || val >= crit.max_score}
                          onClick={() => onScoreChange(c.id, crit.id, clamp(val + crit.score_step))}
                          className="w-8 h-8 p-0 font-mono-tech font-bold"
                        >
                          +
                        </Button>
                      </div>
                    </div>
                  );
                })}
              </div>

              <div className="flex items-center justify-between pt-2 border-t border-slate-100">
                <Badge
                  variant={
                    status === 'LOCKED'
                      ? 'success'
                      : status === 'UNLOCK_REQUESTED'
                        ? 'warning'
                        : 'secondary'
                  }
                >
                  {status}
                </Badge>
                <div>
                  {status === 'DRAFT' && (
                    <Button
                      size="sm"
                      disabled={isRoundFrozen}
                      onClick={() => onLockRow(c.id)}
                    >
                      Lock Row
                    </Button>
                  )}
                  {status === 'LOCKED' && (
                    <Button
                      size="sm"
                      variant="outline"
                      disabled={isRoundFrozen}
                      onClick={() => onRequestUnlock(c.id)}
                    >
                      Request Unlock
                    </Button>
                  )}
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {/* Desktop / Tablet Matrix Table (>= md) */}
      <div className="hidden md:block overflow-x-auto p-4">
        <table className="w-full text-left border border-slate-200 rounded-xl overflow-hidden border-collapse text-sm">
          <thead>
            <tr className="bg-slate-100 border-b border-slate-200 text-[11px] font-mono-tech uppercase text-slate-600">
              <th className="py-3 px-3 border-r border-slate-200 w-14 text-center">#</th>
              <th className="py-3 px-3.5 border-r border-slate-200 min-w-[200px] sticky-col-left bg-slate-100">
                Candidate / Origin
              </th>
              {criteria.map((crit) => (
                <th key={crit.id} className="py-3 px-3 border-r border-slate-200 text-center min-w-[165px]">
                  <div className="font-bold text-slate-900 font-sans text-xs">{crit.name}</div>
                  <div className="text-[10px] font-mono-tech text-slate-500">
                    Weight: {crit.weight_percentage}% ({crit.min_score}–{crit.max_score})
                  </div>
                </th>
              ))}
              <th className="py-3 px-3 border-r border-slate-200 text-center w-32 bg-slate-200/70 text-slate-900">
                Weighted Tot
              </th>
              <th className="py-3 px-3 border-r border-slate-200 text-center w-32">Ordinal Rank</th>
              <th className="py-3 px-3 text-center w-36">Row Status</th>
            </tr>
          </thead>
          <tbody>
            {contestants.map((c) => {
              const sheet = myScores?.[String(c.id)];
              const status = sheet?.status ?? 'DRAFT';
              const isLocked = status === 'LOCKED' || status === 'UNLOCK_REQUESTED' || isRoundFrozen;

              return (
                <tr
                  key={c.id}
                  className={cn(
                    'border-b border-slate-200',
                    isLocked ? 'wf-hatch' : 'bg-white hover:bg-slate-50/80'
                  )}
                >
                  <td className="py-3 px-3 border-r border-slate-200 font-mono-tech font-bold text-center text-slate-700">
                    #{c.candidate_number}
                  </td>
                  <td className="py-3 px-3.5 border-r border-slate-200 sticky-col-left">
                    <div className="font-bold text-sm text-slate-900">{c.name}</div>
                    <div className="text-xs text-slate-500">{c.origin_or_group}</div>
                  </td>

                  {criteria.map((crit) => {
                    const val = sheet?.criteria?.[String(crit.id)] ?? crit.min_score;
                    const clamp = (raw: number) =>
                      Math.min(crit.max_score, Math.max(crit.min_score, Number(raw.toFixed(2))));

                    return (
                      <td key={crit.id} className="py-3 px-3 border-r border-slate-200 text-center">
                        <div className="inline-flex items-center gap-1">
                          <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={isLocked || val <= crit.min_score}
                            onClick={() => onScoreChange(c.id, crit.id, clamp(val - crit.score_step))}
                            className="w-7 h-7 p-0 font-mono-tech font-bold"
                          >
                            -
                          </Button>
                          <input
                            type="number"
                            aria-label={`${c.name} ${crit.name}`}
                            disabled={isLocked}
                            min={crit.min_score}
                            max={crit.max_score}
                            step={crit.score_step}
                            value={val}
                            onChange={(e) => {
                              const parsed = parseFloat(e.target.value);
                              if (!Number.isNaN(parsed)) onScoreChange(c.id, crit.id, clamp(parsed));
                            }}
                            className="w-16 text-center font-mono-tech font-bold text-sm py-1 border border-slate-300 rounded-lg bg-white disabled:bg-slate-100"
                          />
                          <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={isLocked || val >= crit.max_score}
                            onClick={() => onScoreChange(c.id, crit.id, clamp(val + crit.score_step))}
                            className="w-7 h-7 p-0 font-mono-tech font-bold"
                          >
                            +
                          </Button>
                        </div>
                      </td>
                    );
                  })}

                  <td className="py-3 px-3 border-r border-slate-200 text-center font-mono-tech font-bold text-base bg-slate-50 text-slate-900">
                    {(sheet?.weighted_total ?? 0).toFixed(2)}
                  </td>
                  <td className="py-3 px-3 border-r border-slate-200 text-center font-mono-tech">
                    <span
                      className={cn(
                        'px-2.5 py-1 rounded-full text-xs font-bold',
                        sheet?.personal_rank === 1
                          ? 'bg-slate-900 text-white'
                          : 'bg-slate-100 text-slate-800 border border-slate-200'
                      )}
                    >
                      #{sheet?.personal_rank ?? '—'}
                      {sheet?.is_tied ? ' (Tied)' : ''}
                    </span>
                  </td>
                  <td className="py-3 px-3 text-center">
                    {status === 'DRAFT' && (
                      <Button
                        size="sm"
                        disabled={isRoundFrozen}
                        onClick={() => onLockRow(c.id)}
                      >
                        Lock Row
                      </Button>
                    )}
                    {status === 'LOCKED' && (
                      <Button
                        size="sm"
                        variant="outline"
                        disabled={isRoundFrozen}
                        onClick={() => onRequestUnlock(c.id)}
                      >
                        Locked • Unlock?
                      </Button>
                    )}
                    {status === 'UNLOCK_REQUESTED' && (
                      <Badge variant="warning">Pending</Badge>
                    )}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
}
