import { Check, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
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
  onStageContestantId?: number | null;
  scoresMatrix: Record<string, Record<string, AdminMatrixCell>>;
  isResolving: boolean;
  onResolveUnlock: (payload: ResolveUnlockPayload) => void;
}

export function LiveSubmissionMatrix({
  activeRound,
  contestants,
  judges,
  onStageContestantId,
  scoresMatrix,
  isResolving,
  onResolveUnlock,
}: LiveSubmissionMatrixProps) {
  const activeContestants = contestants.filter((c) => c.status === 'active');
  const activeJudges = judges.filter((j) => j.is_active);

  return (
    <div className="wf-box overflow-hidden">
      <div className="px-4 sm:px-6 py-3.5 bg-slate-900 text-white flex flex-wrap items-center justify-between gap-2 text-xs">
        <span className="font-bold font-mono-tech">
          [WF-05] Live Submission Matrix • WebSocket Telemetry
        </span>
        <div className="flex items-center gap-3 text-[11px] font-mono-tech text-slate-300">
          <span className="flex items-center gap-1">
            <span className="w-2 h-2 rounded-full bg-emerald-400" />
            Locked
          </span>
          <span className="flex items-center gap-1">
            <span className="w-2 h-2 rounded-full bg-slate-400" />
            Drafting
          </span>
          <span className="flex items-center gap-1">
            <span className="w-2 h-2 rounded-full bg-amber-400" />
            Unlock Req
          </span>
        </div>
      </div>

      <div className="overflow-x-auto p-4">
        {!activeRound ? (
          <p className="py-6 text-center text-sm text-slate-500">
            Select an active round to view the submission matrix.
          </p>
        ) : (
          <table className="w-full min-w-[680px] text-left border border-slate-200 rounded-xl overflow-hidden border-collapse">
            <thead>
              <tr className="bg-slate-100 border-b border-slate-200 text-xs">
                <th className="py-3 px-3.5 border-r border-slate-200 font-mono-tech text-slate-700 sticky-col-left bg-slate-100">
                  Contestant
                </th>
                {activeJudges.map((judge) => (
                  <th
                    key={judge.id}
                    className="py-3 px-3 border-r border-slate-200 text-center font-mono-tech min-w-[140px]"
                  >
                    <div className="font-bold text-slate-900">
                      Judge #{judge.judge_number}
                      {judge.is_chairman ? ' (Chair)' : ''}
                    </div>
                    <div className="text-[10px] text-slate-500 truncate max-w-[130px] mx-auto font-sans font-normal">
                      {judge.name}
                    </div>
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {activeContestants.map((contestant) => {
                const row = scoresMatrix[String(contestant.id)] ?? {};
                const isOnStage = contestant.id === onStageContestantId;

                return (
                  <tr
                    key={contestant.id}
                    className="border-b border-slate-200 bg-white hover:bg-slate-50/60"
                  >
                    <td className="py-2.5 px-3.5 border-r border-slate-200 font-semibold text-xs whitespace-nowrap sticky-col-left">
                      <span className="font-mono-tech font-bold text-slate-500 mr-1">
                        #{contestant.candidate_number}
                      </span>
                      <span className="text-slate-900 font-bold">{contestant.name}</span>
                      {isOnStage && (
                        <span className="ml-1.5 px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-mono-tech text-[9px] font-bold">
                          STAGE
                        </span>
                      )}
                    </td>
                    {activeJudges.map((judge) => {
                      const cell = row[String(judge.id)];
                      const status = cell?.status ?? 'DRAFT';
                      const scoreText =
                        cell && cell.weighted_total > 0
                          ? cell.weighted_total.toFixed(2)
                          : '—';

                      if (status === 'LOCKED') {
                        return (
                          <td
                            key={judge.id}
                            className="py-2.5 px-2.5 border-r border-slate-200 text-center font-mono-tech"
                          >
                            <div className="px-2.5 py-1.5 rounded-lg border border-emerald-200 bg-emerald-50/60 flex items-center justify-between text-xs">
                              <span className="font-bold text-emerald-900">{scoreText}</span>
                              <span className="text-[10px] bg-white text-emerald-700 px-1.5 py-0.5 rounded border border-emerald-200 font-bold">
                                LOCKED
                              </span>
                            </div>
                          </td>
                        );
                      }

                      if (status === 'UNLOCK_REQUESTED') {
                        return (
                          <td
                            key={judge.id}
                            className="py-2 px-2.5 border-r border-slate-200 text-center font-mono-tech bg-amber-50"
                          >
                            <div className="p-1.5 rounded-lg border border-amber-300 bg-amber-100/70 space-y-1">
                              <div className="flex items-center justify-between text-[10px] font-bold text-amber-900">
                                <span>{scoreText}</span>
                                <span>UNLOCK REQ</span>
                              </div>
                              <div className="flex items-center gap-1">
                                <Button
                                  type="button"
                                  size="sm"
                                  disabled={isResolving}
                                  className="h-6 flex-1 bg-slate-900 px-2 text-[10px] text-white hover:bg-slate-800"
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
                                  <Check className="h-3 w-3 mr-0.5" />
                                  Approve
                                </Button>
                                <Button
                                  type="button"
                                  size="sm"
                                  variant="destructive"
                                  disabled={isResolving}
                                  className="h-6 px-2 text-[10px]"
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

                      return (
                        <td
                          key={judge.id}
                          className="py-2.5 px-2.5 border-r border-slate-200 text-center font-mono-tech"
                        >
                          <div className="px-2.5 py-1.5 rounded-lg border border-dashed border-slate-300 bg-slate-50 flex items-center justify-between text-xs text-slate-600">
                            <span>{scoreText}</span>
                            <span className="text-[10px] text-slate-400">DRAFT</span>
                          </div>
                        </td>
                      );
                    })}
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}
