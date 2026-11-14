import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader } from '@/components/ui/card';
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
  const [confirmingLockAll, setConfirmingLockAll] = useState(false);

  const activeQualifiers = contestants.filter(
    (c) => c.status === 'active' && c.is_qualified_in_active_round !== false,
  );

  const hasMinScoreDrafts = activeQualifiers.some((c) => {
    const sheet = myScores?.[String(c.id)];
    const status = sheet?.status ?? 'DRAFT';
    if (status !== 'DRAFT') return false;
    return criteria.some((crit) => {
      const val = sheet?.criteria?.[String(crit.id)] ?? crit.min_score;
      return val <= crit.min_score;
    });
  });

  const handleLockAllClick = () => {
    if (hasMinScoreDrafts) {
      setConfirmingLockAll(true);
      return;
    }
    onLockAll();
  };

  return (
    <Card className="overflow-hidden">
      <CardHeader className="flex flex-wrap items-center justify-between gap-4 bg-slate-900 text-white">
        <div>
          <span className="text-[11px] uppercase tracking-wider text-slate-300">{roundName}</span>
          <h2 className="text-base sm:text-lg font-semibold mt-0.5">
            Comparative Candidate Evaluation Matrix
          </h2>
        </div>
        <Button
          variant="secondary"
          size="sm"
          disabled={isRoundFrozen || activeQualifiers.length === 0}
          onClick={handleLockAllClick}
        >
          Lock & Submit Entire Round Sheet
        </Button>
      </CardHeader>

      {confirmingLockAll && (
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-950">
          <span className="font-medium">
            Some draft score sheets still have criteria at the minimum score.
            Lock all {activeQualifiers.length} qualifiers anyway?
          </span>
          <div className="flex items-center gap-2">
            <Button
              type="button"
              size="sm"
              variant="outline"
              onClick={() => setConfirmingLockAll(false)}
            >
              Cancel
            </Button>
            <Button
              type="button"
              size="sm"
              onClick={() => {
                setConfirmingLockAll(false);
                onLockAll();
              }}
            >
              Confirm Lock All
            </Button>
          </div>
        </div>
      )}

      <div className="overflow-x-auto">
        <table className="w-full text-left border-collapse text-sm">
          <thead>
            <tr className="bg-slate-50 border-b border-slate-200 text-xs text-slate-600 uppercase">
              <th className="py-3 px-4 w-14 text-center">#</th>
              <th className="py-3 px-4 min-w-[200px]">Candidate / Origin</th>
              {criteria.map((crit) => (
                <th key={crit.id} className="py-3 px-3 text-center min-w-[165px]">
                  <div className="font-semibold text-slate-900">{crit.name}</div>
                  <div className="text-[11px] font-normal text-slate-500">
                    {crit.weight_percentage}% ({crit.min_score}–{crit.max_score})
                  </div>
                </th>
              ))}
              <th className="py-3 px-4 text-center w-28">Weighted</th>
              <th className="py-3 px-4 text-center w-32">Rank</th>
              <th className="py-3 px-4 text-center w-36">Status</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {activeQualifiers.map((c) => {
              const sheet = myScores?.[String(c.id)];
              const status = sheet?.status ?? 'DRAFT';
              const isLocked = status === 'LOCKED' || status === 'UNLOCK_REQUESTED' || isRoundFrozen;

              return (
                <tr key={c.id} className={cn(isLocked ? 'bg-slate-50/70' : 'bg-white hover:bg-slate-50/50')}>
                  <td className="py-3 px-4 text-center font-semibold text-slate-700 tabular-nums">
                    #{c.candidate_number}
                  </td>
                  <td className="py-3 px-4">
                    <div className="flex flex-wrap items-center gap-1.5">
                      <span className="font-semibold text-slate-900">{c.name}</span>
                      {c.qualification_method === 'MANUAL_WILDCARD' && (
                        <Badge variant="warning" className="text-[10px]">
                          Wildcard
                        </Badge>
                      )}
                      {c.qualified_from_rank !== null &&
                        c.qualified_from_rank !== undefined && (
                          <Badge variant="info" className="text-[10px]">
                            Qualified #{c.qualified_from_rank}
                          </Badge>
                        )}
                    </div>
                    <div className="text-xs text-slate-500">{c.origin_or_group}</div>
                  </td>

                  {criteria.map((crit) => {
                    const val = sheet?.criteria?.[String(crit.id)] ?? crit.min_score;
                    const clamp = (raw: number) =>
                      Math.min(crit.max_score, Math.max(crit.min_score, Number(raw.toFixed(2))));

                    return (
                      <td key={crit.id} className="py-3 px-3 text-center">
                        <div className="inline-flex items-center gap-1">
                          <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={isLocked || val <= crit.min_score}
                            onClick={() => onScoreChange(c.id, crit.id, clamp(val - crit.score_step))}
                            className="w-7 h-8 p-0"
                          >
                            -
                          </Button>
                          <input
                            type="number"
                            disabled={isLocked}
                            min={crit.min_score}
                            max={crit.max_score}
                            step={crit.score_step}
                            value={val}
                            onChange={(e) => {
                              const parsed = parseFloat(e.target.value);
                              if (!Number.isNaN(parsed)) onScoreChange(c.id, crit.id, clamp(parsed));
                            }}
                            className="w-16 h-8 text-center font-semibold text-sm border border-slate-200 rounded-md bg-white disabled:bg-slate-100 tabular-nums"
                          />
                          <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={isLocked || val >= crit.max_score}
                            onClick={() => onScoreChange(c.id, crit.id, clamp(val + crit.score_step))}
                            className="w-7 h-8 p-0"
                          >
                            +
                          </Button>
                        </div>
                      </td>
                    );
                  })}

                  <td className="py-3 px-4 text-center font-bold text-slate-900 tabular-nums">
                    {(sheet?.weighted_total ?? 0).toFixed(2)}
                  </td>
                  <td className="py-3 px-4 text-center tabular-nums">
                    <Badge variant={sheet?.personal_rank === 1 ? 'default' : 'secondary'}>
                      #{sheet?.personal_rank ?? '—'} of {activeQualifiers.length}
                      {sheet?.is_tied ? ' (T)' : ''}
                    </Badge>
                  </td>
                  <td className="py-3 px-4 text-center">
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
                        Unlock?
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
    </Card>
  );
}
