import { Minus, Plus, FileDown, Monitor } from 'lucide-react';
import { Link } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { cn } from '@/components/ui/cn';
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
    <div className="wf-box overflow-hidden">
      <div className="px-4 sm:px-6 py-4 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
        <div>
          <span className="text-[11px] font-mono-tech font-bold uppercase text-slate-500">
            [WF-06] Master Tabulation Engine
          </span>
          <h3 className="text-base sm:text-lg font-extrabold text-slate-900 tracking-tight">
            Official Leaderboard — Active Strategy:{' '}
            <span className="font-mono-tech uppercase text-indigo-600">{strategy}</span>
          </h3>
        </div>
        {activeRound && (
          <div className="flex flex-wrap items-center gap-2 w-full sm:w-auto">
            <Button
              type="button"
              size="sm"
              variant="outline"
              className="gap-1.5 flex-1 sm:flex-initial"
              onClick={() => onExportPdf(activeRound.id)}
            >
              <FileDown className="h-3.5 w-3.5" />
              Print Tally PDF
            </Button>
            <Link
              to={`/display/${eventId}`}
              target="_blank"
              rel="noreferrer"
              className="inline-flex h-8 flex-1 sm:flex-initial items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-3.5 text-xs font-semibold text-white hover:bg-slate-800 transition-colors"
            >
              <Monitor className="h-3.5 w-3.5" />
              LED Stage Wall &rarr;
            </Link>
          </div>
        )}
      </div>

      {!activeRound || leaderboard.length === 0 ? (
        <p className="py-8 text-center text-sm text-slate-500">
          No computed results yet for this round.
        </p>
      ) : (
        <>
          {/* Mobile Leaderboard Cards (< md) */}
          <div className="md:hidden p-4 space-y-3">
            {leaderboard.map((row) => {
              const contestant = contestantMap.get(row.contestant_id);
              return (
                <div
                  key={row.contestant_id}
                  className={cn(
                    'wf-box-sm p-4 space-y-3',
                    row.is_qualified ? 'bg-white border-slate-200' : 'wf-hatch opacity-85'
                  )}
                >
                  <div className="flex items-center justify-between gap-2">
                    <div className="flex items-center gap-2.5 min-w-0">
                      <span
                        className={cn(
                          'inline-flex items-center justify-center w-8 h-8 rounded-xl font-mono-tech font-bold text-xs shrink-0',
                          row.is_qualified
                            ? 'bg-slate-900 text-white'
                            : 'bg-slate-200 text-slate-700'
                        )}
                      >
                        #{row.final_rank}
                      </span>
                      <div className="min-w-0">
                        <div className="font-bold text-sm text-slate-900 truncate">
                          #{contestant?.candidate_number ?? row.contestant_id}{' '}
                          {contestant?.name ?? 'Unknown'}
                        </div>
                        <div className="text-xs text-slate-500 truncate">
                          {contestant?.origin_or_group}
                        </div>
                      </div>
                    </div>
                    <div className="shrink-0">
                      {row.is_qualified ? (
                        <span className="px-2.5 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 font-mono-tech text-[10px] font-bold">
                          TOP {activeRound.advancement_count}
                        </span>
                      ) : (
                        <span className="px-2 py-0.5 rounded-full bg-slate-100 border border-slate-200 text-slate-500 font-mono-tech text-[10px]">
                          ELIMINATED
                        </span>
                      )}
                    </div>
                  </div>

                  <div className="grid grid-cols-3 gap-2 pt-2 border-t border-slate-100 font-mono-tech text-center">
                    <div className="p-2 rounded-lg bg-slate-50">
                      <span className="block text-[9px] text-slate-400 uppercase">
                        Net Composite
                      </span>
                      <span className="text-sm font-bold text-slate-900">
                        {row.net_composite.toFixed(2)}
                      </span>
                    </div>
                    <div className="p-2 rounded-lg bg-slate-50">
                      <span className="block text-[9px] text-slate-400 uppercase">
                        Rank Sum
                      </span>
                      <span className="text-sm font-bold text-slate-900">
                        {row.rank_sum.toFixed(1)}
                      </span>
                    </div>
                    <div className="p-2 rounded-lg bg-slate-50">
                      <span className="block text-[9px] text-slate-400 uppercase">
                        Penalty
                      </span>
                      <div className="inline-flex items-center gap-1 mt-0.5">
                        <button
                          type="button"
                          disabled={
                            isUpdatingDeduction ||
                            isFinalized ||
                            row.deduction_points <= 0
                          }
                          onClick={() =>
                            onApplyDeduction({
                              round_id: activeRound.id,
                              contestant_id: row.contestant_id,
                              points_deducted: Math.max(
                                0,
                                Number((row.deduction_points - 0.5).toFixed(2))
                              ),
                            })
                          }
                          className="w-5 h-5 rounded border border-slate-300 bg-white text-xs font-bold leading-none flex items-center justify-center disabled:opacity-40 cursor-pointer"
                        >
                          -
                        </button>
                        <span
                          className={cn(
                            'text-xs font-bold',
                            row.deduction_points > 0 ? 'text-rose-600' : 'text-slate-400'
                          )}
                        >
                          {row.deduction_points > 0
                            ? `-${row.deduction_points.toFixed(1)}`
                            : '0.0'}
                        </span>
                        <button
                          type="button"
                          disabled={isUpdatingDeduction || isFinalized}
                          onClick={() =>
                            onApplyDeduction({
                              round_id: activeRound.id,
                              contestant_id: row.contestant_id,
                              points_deducted: Number(
                                (row.deduction_points + 0.5).toFixed(2)
                              ),
                            })
                          }
                          className="w-5 h-5 rounded border border-slate-300 bg-white text-xs font-bold leading-none flex items-center justify-center disabled:opacity-40 cursor-pointer"
                        >
                          +
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>

          {/* Desktop Leaderboard Table (>= md) */}
          <div className="hidden md:block overflow-x-auto p-4">
            <table className="w-full min-w-[820px] text-left border border-slate-200 rounded-xl overflow-hidden border-collapse">
              <thead>
                <tr className="bg-slate-100 border-b border-slate-200 text-[11px] font-mono-tech uppercase text-slate-600">
                  <th className="py-3 px-3 border-r border-slate-200 text-center w-16">
                    Rank
                  </th>
                  <th className="py-3 px-3.5 border-r border-slate-200 sticky-col-left bg-slate-100">
                    Candidate / Group
                  </th>
                  {activeJudges.map((j) => (
                    <th
                      key={j.id}
                      className="py-3 px-2.5 border-r border-slate-200 text-center"
                    >
                      J#{j.judge_number}
                      <span className="block text-[9px] font-normal text-slate-400">
                        Score(Rnk)
                      </span>
                    </th>
                  ))}
                  <th className="py-3 px-2.5 border-r border-slate-200 text-center">
                    Tech Deduct
                  </th>
                  <th className="py-3 px-3 border-r border-slate-200 text-center">
                    Net Composite
                  </th>
                  <th className="py-3 px-3 border-r border-slate-200 text-center">
                    Rank Sum
                  </th>
                  <th className="py-3 px-3 text-center">Qualification</th>
                </tr>
              </thead>
              <tbody>
                {leaderboard.map((row) => {
                  const contestant = contestantMap.get(row.contestant_id);
                  const breakdownByJudge = new Map(
                    row.judge_breakdown_snapshot.map((b) => [b.judge_id, b])
                  );

                  return (
                    <tr
                      key={row.contestant_id}
                      className={cn(
                        'border-b border-slate-200',
                        row.is_qualified
                          ? 'bg-white hover:bg-slate-50/70'
                          : 'wf-hatch text-slate-500'
                      )}
                    >
                      <td className="py-3 px-3 border-r border-slate-200 font-mono-tech text-center">
                        <span
                          className={cn(
                            'inline-flex items-center justify-center w-7 h-7 rounded-lg font-bold text-xs',
                            row.is_qualified
                              ? 'bg-slate-900 text-white'
                              : 'bg-slate-100 text-slate-700 border border-slate-200'
                          )}
                        >
                          #{row.final_rank}
                        </span>
                      </td>

                      <td className="py-3 px-3.5 border-r border-slate-200 sticky-col-left">
                        <div className="font-bold text-sm text-slate-900">
                          #{contestant?.candidate_number ?? row.contestant_id}{' '}
                          {contestant?.name ?? 'Unknown'}
                        </div>
                        <div className="text-xs text-slate-500">
                          {contestant?.origin_or_group}
                        </div>
                      </td>

                      {activeJudges.map((j) => {
                        const item = breakdownByJudge.get(j.id);
                        if (!item) {
                          return (
                            <td
                              key={j.id}
                              className="py-3 px-2.5 border-r border-slate-200 text-center font-mono-tech text-xs text-slate-400"
                            >
                              —
                            </td>
                          );
                        }

                        return (
                          <td
                            key={j.id}
                            className="py-3 px-2.5 border-r border-slate-200 text-center font-mono-tech text-xs"
                          >
                            <span
                              className={
                                item.is_dropped
                                  ? 'line-through text-slate-400'
                                  : 'font-bold text-slate-900'
                              }
                              title={
                                item.is_dropped
                                  ? 'Dropped in Trimmed Mean'
                                  : undefined
                              }
                            >
                              {item.score.toFixed(2)}
                            </span>
                            <span className="block text-[10px] text-slate-400">
                              (#{item.rank})
                            </span>
                          </td>
                        );
                      })}

                      <td className="py-3 px-2.5 border-r border-slate-200 text-center font-mono-tech">
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
                                  Number((row.deduction_points - 0.5).toFixed(2))
                                ),
                              })
                            }
                          >
                            <Minus className="h-3 w-3" />
                          </Button>
                          <span
                            className={cn(
                              'w-11 text-center font-bold text-xs',
                              row.deduction_points > 0 ? 'text-rose-600' : 'text-slate-400'
                            )}
                          >
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
                                  (row.deduction_points + 0.5).toFixed(2)
                                ),
                              })
                            }
                          >
                            <Plus className="h-3 w-3" />
                          </Button>
                        </div>
                      </td>

                      <td
                        className={cn(
                          'py-3 px-3 border-r border-slate-200 text-center font-mono-tech text-base font-bold',
                          strategy !== 'ordinal'
                            ? 'bg-slate-100/90 text-slate-900'
                            : 'text-slate-700'
                        )}
                      >
                        {row.net_composite.toFixed(2)}
                        {strategy === 'trimmed' && (
                          <span className="block text-[9px] font-normal text-slate-500">
                            TRIMMED
                          </span>
                        )}
                      </td>

                      <td
                        className={cn(
                          'py-3 px-3 border-r border-slate-200 text-center font-mono-tech text-base font-bold',
                          strategy === 'ordinal'
                            ? 'bg-slate-100/90 text-slate-900'
                            : 'text-slate-700'
                        )}
                      >
                        {row.rank_sum.toFixed(1)}
                        <span className="block text-[9px] font-normal text-slate-400">
                          LOW WINS
                        </span>
                      </td>

                      <td className="py-3 px-3 text-center font-mono-tech">
                        {row.is_qualified ? (
                          <span className="px-2.5 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 text-[11px] font-bold">
                            TOP {activeRound.advancement_count} QUALIFIER
                          </span>
                        ) : (
                          <span className="px-2 py-0.5 rounded-full bg-slate-100 border border-slate-200 text-slate-500 text-[10px]">
                            ELIMINATED
                          </span>
                        )}
                        {row.is_tied && row.tie_break_rule_applied && (
                          <div className="text-[9px] font-bold mt-1 text-indigo-600">
                            TB: {row.tie_break_rule_applied}
                          </div>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  );
}
