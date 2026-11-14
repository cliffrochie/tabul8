import { Check, X, Lock, Clock, AlertTriangle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import type {
  AdminMatrixCell,
  ContestantDto,
  JudgeDto,
  RoundDto,
} from '@/types/api';
import type { ResolveUnlockPayload } from '../api/tabulatorApi';

interface LiveSubmissionMatrixProps {
  activeRound: RoundDto | null;
  contestants: ContestantDto[];
  judges: JudgeDto[];
  scoresMatrix: Record<string, Record<string, AdminMatrixCell>>;
  isResolving: boolean;
  onResolveUnlock: (payload: ResolveUnlockPayload) => void;
}

export function LiveSubmissionMatrix({
  activeRound,
  contestants,
  judges,
  scoresMatrix,
  isResolving,
  onResolveUnlock,
}: LiveSubmissionMatrixProps) {
  const activeContestants = contestants.filter(
    (c) => c.status === 'active' && c.is_qualified_in_active_round !== false,
  );
  const activeJudges = judges.filter((j) => j.is_active);
  const totalCells = activeContestants.length * activeJudges.length;
  let lockedCells = 0;
  for (const c of activeContestants) {
    const row = scoresMatrix[String(c.id)] ?? {};
    for (const j of activeJudges) {
      if (row[String(j.id)]?.status === 'LOCKED') {
        lockedCells += 1;
      }
    }
  }

  return (
    <Card>
      <CardHeader>
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div>
            <CardTitle className="text-lg">
              WF-05 · Live Submission Matrix
            </CardTitle>
            <CardDescription>
              Real-time judge lock status per contestant. Approve or reject
              unlock requests in one click.
            </CardDescription>
          </div>
          <div className="flex flex-wrap items-center gap-2 text-xs">
            <Badge
              variant={
                totalCells > 0 && lockedCells === totalCells
                  ? 'success'
                  : 'info'
              }
            >
              Quorum: {lockedCells} / {totalCells} Sheets Locked
            </Badge>
            <Badge variant="success">LOCKED</Badge>
            <Badge variant="default">DRAFT</Badge>
            <Badge variant="warning">UNLOCK REQUESTED</Badge>
          </div>
        </div>
      </CardHeader>

      <CardContent className="overflow-x-auto">
        {!activeRound ? (
          <p className="py-6 text-center text-sm text-slate-500">
            Select an active round to view the submission matrix.
          </p>
        ) : (
          <table className="w-full min-w-[680px] border-collapse text-left text-sm">
            <thead>
              <tr className="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500">
                <th className="py-2.5 pr-4 font-semibold">Contestant</th>
                {activeJudges.map((judge) => (
                  <th
                    key={judge.id}
                    className="px-3 py-2.5 text-center font-semibold"
                  >
                    <div>
                      J{judge.judge_number}
                      {judge.is_chairman ? ' (Chair)' : ''}
                    </div>
                    <div className="text-[11px] font-normal normal-case text-slate-400">
                      {judge.name}
                    </div>
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {activeContestants.map((contestant) => {
                const row = scoresMatrix[String(contestant.id)] ?? {};
                return (
                  <tr key={contestant.id} className="hover:bg-slate-50/70">
                    <td className="py-3 pr-4">
                      <div className="font-medium text-slate-900">
                        #{contestant.candidate_number} {contestant.name}
                      </div>
                      <div className="text-xs text-slate-500">
                        {contestant.origin_or_group}
                      </div>
                    </td>
                    {activeJudges.map((judge) => {
                      const cell = row[String(judge.id)];
                      const status = cell?.status ?? 'DRAFT';
                      const scoreText =
                        cell && cell.weighted_total > 0
                          ? cell.weighted_total.toFixed(2)
                          : '—';

                      if (status === 'UNLOCK_REQUESTED') {
                        return (
                          <td
                            key={judge.id}
                            className="px-2 py-2 text-center align-middle"
                          >
                            <div className="inline-flex flex-col items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 p-2">
                              <div className="flex items-center gap-1 text-xs font-semibold text-amber-800">
                                <AlertTriangle className="h-3.5 w-3.5" />
                                <span>{scoreText}</span>
                              </div>
                              <div className="flex items-center gap-1">
                                <Button
                                  type="button"
                                  size="sm"
                                  disabled={isResolving}
                                  className="h-6 bg-emerald-600 px-2 text-xs hover:bg-emerald-700"
                                  title="Approve Unlock"
                                  onClick={() =>
                                    onResolveUnlock({
                                      round_id: activeRound.id,
                                      contestant_id: contestant.id,
                                      judge_id: judge.id,
                                      decision: 'APPROVE',
                                    })
                                  }
                                >
                                  <Check className="h-3 w-3" />
                                </Button>
                                <Button
                                  type="button"
                                  size="sm"
                                  variant="destructive"
                                  disabled={isResolving}
                                  className="h-6 px-2 text-xs"
                                  title="Reject Unlock"
                                  onClick={() =>
                                    onResolveUnlock({
                                      round_id: activeRound.id,
                                      contestant_id: contestant.id,
                                      judge_id: judge.id,
                                      decision: 'REJECT',
                                    })
                                  }
                                >
                                  <X className="h-3 w-3" />
                                </Button>
                              </div>
                            </div>
                          </td>
                        );
                      }

                      if (status === 'LOCKED') {
                        return (
                          <td key={judge.id} className="px-3 py-3 text-center">
                            <span className="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                              <Lock className="h-3 w-3" />
                              {scoreText}
                            </span>
                          </td>
                        );
                      }

                      return (
                        <td key={judge.id} className="px-3 py-3 text-center">
                          <span className="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                            <Clock className="h-3 w-3 text-slate-400" />
                            {scoreText}
                          </span>
                        </td>
                      );
                    })}
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </CardContent>
    </Card>
  );
}
