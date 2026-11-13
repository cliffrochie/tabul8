import { Minus, Plus, Award, FileDown, Monitor } from 'lucide-react';
import { Link } from 'react-router-dom';
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
  ContestantDto,
  JudgeDto,
  RoundDto,
  RoundResultDto,
} from '@/types/api';
import type { ApplyDeductionPayload } from '../api/tabulatorApi';

interface MasterLeaderboardProps {
  eventId: number;
  activeRound: RoundDto | null;
  contestants: ContestantDto[];
  judges: JudgeDto[];
  leaderboard: RoundResultDto[];
  isUpdatingDeduction: boolean;
  onApplyDeduction: (payload: ApplyDeductionPayload) => void;
  onExportPdf: (roundId: number) => void;
}

export function MasterLeaderboard({
  eventId,
  activeRound,
  contestants,
  judges,
  leaderboard,
  isUpdatingDeduction,
  onApplyDeduction,
  onExportPdf,
}: MasterLeaderboardProps) {
  const contestantMap = new Map(contestants.map((c) => [c.id, c]));
  const activeJudges = judges.filter((j) => j.is_active);
  const strategy = activeRound?.scoring_strategy ?? 'weighted';
  const isFinalized = activeRound?.status === 'finalized';

  return (
    <Card>
      <CardHeader>
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div>
            <CardTitle className="text-lg">
              WF-06 · Master Official Leaderboard
            </CardTitle>
            <CardDescription>
              Live rankings computed in{' '}
              <span className="font-semibold capitalize">{strategy}</span> mode
              with judge breakdown, deductions, and tie-break indicators.
            </CardDescription>
          </div>
          {activeRound && (
            <div className="flex flex-wrap items-center gap-2">
              <Badge variant="info">
                Top {activeRound.advancement_count} Qualify
              </Badge>
              <Link
                to={`/display/${eventId}`}
                target="_blank"
                rel="noreferrer"
                className="inline-flex h-8 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 text-xs font-medium text-slate-700 hover:bg-slate-50"
              >
                <Monitor className="h-3.5 w-3.5" />
                Open LED Wall
              </Link>
              <Button
                type="button"
                size="sm"
                variant="outline"
                className="gap-1.5"
                onClick={() => onExportPdf(activeRound.id)}
              >
                <FileDown className="h-3.5 w-3.5" />
                Export Tally PDF
              </Button>
            </div>
          )}
        </div>
      </CardHeader>

      <CardContent className="overflow-x-auto">
        {!activeRound || leaderboard.length === 0 ? (
          <p className="py-6 text-center text-sm text-slate-500">
            No computed results yet for this round.
          </p>
        ) : (
          <table className="w-full min-w-[820px] border-collapse text-left text-sm">
            <thead>
              <tr className="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500">
                <th className="py-2.5 pr-3 font-semibold">Rank</th>
                <th className="py-2.5 pr-4 font-semibold">Contestant</th>
                {activeJudges.map((j) => (
                  <th
                    key={j.id}
                    className="px-2 py-2.5 text-center font-semibold"
                  >
                    J{j.judge_number}
                  </th>
                ))}
                <th className="px-3 py-2.5 text-center font-semibold">
                  {strategy === 'ordinal' ? 'Rank Sum' : 'Mean'}
                </th>
                <th className="px-3 py-2.5 text-center font-semibold">
                  Deduction
                </th>
                <th className="px-3 py-2.5 text-right font-semibold">
                  Net Composite
                </th>
                <th className="pl-3 py-2.5 text-right font-semibold">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {leaderboard.map((row) => {
                const contestant = contestantMap.get(row.contestant_id);
                const breakdownByJudge = new Map(
                  row.judge_breakdown_snapshot.map((b) => [b.judge_id, b]),
                );

                return (
                  <tr
                    key={row.contestant_id}
                    className={
                      row.is_qualified
                        ? 'bg-emerald-50/30 hover:bg-emerald-50/60'
                        : 'hover:bg-slate-50/70'
                    }
                  >
                    <td className="py-3 pr-3">
                      <div className="flex items-center gap-1.5">
                        <span className="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-900 text-xs font-bold text-white">
                          {row.final_rank}
                        </span>
                        {row.is_tied && (
                          <Badge variant="warning" className="text-[10px]">
                            TIE
                          </Badge>
                        )}
                      </div>
                    </td>

                    <td className="py-3 pr-4">
                      <div className="font-medium text-slate-900">
                        #{contestant?.candidate_number ?? row.contestant_id}{' '}
                        {contestant?.name ?? 'Unknown'}
                      </div>
                      <div className="flex items-center gap-2 text-xs text-slate-500">
                        <span>{contestant?.origin_or_group}</span>
                        {row.tie_break_rule_applied && (
                          <Badge variant="default" className="text-[10px]">
                            TB: {row.tie_break_rule_applied}
                          </Badge>
                        )}
                      </div>
                    </td>

                    {activeJudges.map((j) => {
                      const item = breakdownByJudge.get(j.id);
                      if (!item) {
                        return (
                          <td
                            key={j.id}
                            className="px-2 py-3 text-center text-xs text-slate-400"
                          >
                            —
                          </td>
                        );
                      }

                      return (
                        <td
                          key={j.id}
                          className="px-2 py-3 text-center tabular-nums"
                        >
                          <div
                            className={
                              item.is_dropped
                                ? 'text-xs text-slate-400 line-through'
                                : 'text-xs font-medium text-slate-800'
                            }
                            title={
                              item.is_dropped
                                ? 'Dropped in Trimmed Mean'
                                : undefined
                            }
                          >
                            {item.score.toFixed(2)}
                          </div>
                          {strategy === 'ordinal' && (
                            <div className="text-[11px] text-slate-400">
                              R{item.rank}
                            </div>
                          )}
                        </td>
                      );
                    })}

                    <td className="px-3 py-3 text-center tabular-nums text-slate-700">
                      {strategy === 'ordinal'
                        ? row.rank_sum.toFixed(1)
                        : strategy === 'trimmed'
                          ? row.trimmed_mean.toFixed(2)
                          : row.mean_score.toFixed(2)}
                    </td>

                    <td className="px-3 py-3 text-center">
                      <div className="inline-flex items-center gap-1">
                        <Button
                          type="button"
                          size="sm"
                          variant="outline"
                          className="h-6 w-6 p-0"
                          disabled={
                            isUpdatingDeduction ||
                            isFinalized ||
                            row.deduction_points <= 0
                          }
                          title="Reduce deduction by 0.5"
                          onClick={() =>
                            onApplyDeduction({
                              round_id: activeRound.id,
                              contestant_id: row.contestant_id,
                              points_deducted: Math.max(
                                0,
                                Number((row.deduction_points - 0.5).toFixed(2)),
                              ),
                            })
                          }
                        >
                          <Minus className="h-3 w-3" />
                        </Button>
                        <span className="min-w-[3rem] text-center text-xs font-semibold tabular-nums text-rose-600">
                          {row.deduction_points > 0
                            ? `-${row.deduction_points.toFixed(1)}`
                            : '0.0'}
                        </span>
                        <Button
                          type="button"
                          size="sm"
                          variant="outline"
                          className="h-6 w-6 p-0"
                          disabled={isUpdatingDeduction || isFinalized}
                          title="Add 0.5 deduction"
                          onClick={() =>
                            onApplyDeduction({
                              round_id: activeRound.id,
                              contestant_id: row.contestant_id,
                              points_deducted: Number(
                                (row.deduction_points + 0.5).toFixed(2),
                              ),
                            })
                          }
                        >
                          <Plus className="h-3 w-3" />
                        </Button>
                      </div>
                    </td>

                    <td className="px-3 py-3 text-right font-bold tabular-nums text-slate-900">
                      {row.net_composite.toFixed(2)}
                    </td>

                    <td className="pl-3 py-3 text-right">
                      {row.is_qualified ? (
                        <Badge variant="success" className="gap-1">
                          <Award className="h-3 w-3" />
                          Q
                        </Badge>
                      ) : (
                        <Badge variant="default">—</Badge>
                      )}
                    </td>
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
