import { useState } from 'react';
import { ArrowDown, ArrowUp, Plus, Sparkles, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import type { CutoffMode, RoundDto, ScoringStrategy } from '@/types/api';

interface RoundsFunnelPanelProps {
  eventId: number;
  rounds: RoundDto[];
  totalContestants: number;
  isBusy: boolean;
  onGenerateFunnel: (payload: {
    round_count: number;
    final_winners_count: number;
    final_back_to_zero: boolean;
  }) => void;
  onUpdateRound: (payload: {
    roundId: number;
    name?: string;
    scoring_strategy?: ScoringStrategy;
    weight_in_overall?: number;
    is_back_to_zero?: boolean;
    cutoff_mode?: CutoffMode;
    advancement_count?: number;
    eliminate_count?: number;
  }) => void;
  onCreateRound: (payload: {
    name: string;
    scoring_strategy: ScoringStrategy;
    weight_in_overall: number;
    is_back_to_zero: boolean;
    cutoff_mode: CutoffMode;
    advancement_count: number;
    eliminate_count?: number;
  }) => void;
  onDeleteRound: (roundId: number) => void;
  onReorderRounds: (roundIds: number[]) => void;
}

interface CutoffOverride {
  advancement_count: number;
  eliminate_count: number;
  weight_in_overall: number;
}

export function RoundsFunnelPanel({
  rounds,
  totalContestants,
  isBusy,
  onGenerateFunnel,
  onUpdateRound,
  onCreateRound,
  onDeleteRound,
  onReorderRounds,
}: RoundsFunnelPanelProps) {
  const [roundCount, setRoundCount] = useState(3);
  const [finalWinnersCount, setFinalWinnersCount] = useState(3);
  const [finalBackToZero, setFinalBackToZero] = useState(true);
  const [newRoundName, setNewRoundName] = useState('');
  const [cutoffOverrides, setCutoffOverrides] = useState<
    Record<number, CutoffOverride>
  >({});

  const sortedRounds = [...rounds].sort((a, b) => a.sequence - b.sequence);

  const { pool: runningPool, rows: computedRows } = sortedRounds.reduce<{
    pool: number;
    rows: {
      round: RoundDto;
      entering: number;
      advancement: number;
      eliminate: number;
      weight: number;
      dirty: boolean;
    }[];
  }>(
    (acc, round) => {
      const entering = acc.pool;
      const override = cutoffOverrides[round.id];
      const effectiveAdv = override
        ? override.advancement_count
        : round.advancement_count;
      const effectiveElim = override
        ? override.eliminate_count
        : (round.eliminate_count ?? Math.max(0, entering - effectiveAdv));
      const effectiveWeight = override
        ? override.weight_in_overall
        : Number(round.weight_in_overall ?? 100);

      return {
        pool: Math.max(1, effectiveAdv),
        rows: [
          ...acc.rows,
          {
            round,
            entering,
            advancement: effectiveAdv,
            eliminate: effectiveElim,
            weight: effectiveWeight,
            dirty: Boolean(override),
          },
        ],
      };
    },
    { pool: totalContestants, rows: [] },
  );

  const handleMove = (index: number, direction: -1 | 1) => {
    const targetIdx = index + direction;
    if (targetIdx < 0 || targetIdx >= sortedRounds.length) return;
    const nextOrder = sortedRounds.map((r) => r.id);
    const [moved] = nextOrder.splice(index, 1);
    nextOrder.splice(targetIdx, 0, moved);
    onReorderRounds(nextOrder);
  };

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle className="text-base">
            WF-10 · Progressive N-Round Elimination Funnel Generator
          </CardTitle>
          <CardDescription>
            Auto-scaffold N balanced elimination stages from your registered pool (
            {totalContestants} active contestants).
          </CardDescription>
        </CardHeader>
        <CardContent className="flex flex-wrap items-end gap-4">
          <div className="space-y-1">
            <label className="text-xs font-medium text-slate-700" htmlFor="gen-rounds">
              Number of Rounds
            </label>
            <Input
              id="gen-rounds"
              type="number"
              min={1}
              max={10}
              value={roundCount}
              onChange={(e) => setRoundCount(Math.max(1, Number(e.target.value) || 1))}
              className="w-28"
            />
          </div>
          <div className="space-y-1">
            <label className="text-xs font-medium text-slate-700" htmlFor="gen-winners">
              Final Winners
            </label>
            <Input
              id="gen-winners"
              type="number"
              min={1}
              max={50}
              value={finalWinnersCount}
              onChange={(e) =>
                setFinalWinnersCount(Math.max(1, Number(e.target.value) || 1))
              }
              className="w-28"
            />
          </div>
          <label className="flex items-center gap-2 pb-2 text-xs font-medium text-slate-700 cursor-pointer">
            <input
              type="checkbox"
              checked={finalBackToZero}
              onChange={(e) => setFinalBackToZero(e.target.checked)}
              className="h-4 w-4 rounded border-slate-300"
            />
            Reset to Zero on Final Round
          </label>
          <Button
            type="button"
            disabled={isBusy}
            onClick={() =>
              onGenerateFunnel({
                round_count: roundCount,
                final_winners_count: finalWinnersCount,
                final_back_to_zero: finalBackToZero,
              })
            }
            className="gap-1.5"
          >
            <Sparkles className="h-4 w-4" />
            Generate {roundCount}-Round Funnel
          </Button>
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex flex-wrap flex-row items-center justify-between gap-2">
          <div>
            <CardTitle className="text-base">Configured Stages &amp; Cutoffs</CardTitle>
            <CardDescription>
              Edit Advance Top / Eliminate counts — downstream entering pools cascade automatically.
            </CardDescription>
          </div>
          <div className="flex items-center gap-2">
            <Input
              placeholder="New stage name..."
              value={newRoundName}
              onChange={(e) => setNewRoundName(e.target.value)}
              className="h-8 w-44 text-xs"
            />
            <Button
              type="button"
              size="sm"
              variant="outline"
              disabled={isBusy || newRoundName.trim().length < 2}
              onClick={() => {
                onCreateRound({
                  name: newRoundName.trim(),
                  scoring_strategy: 'weighted',
                  weight_in_overall: 100,
                  is_back_to_zero: sortedRounds.length === 0,
                  cutoff_mode: 'top_n_advance',
                  advancement_count: Math.max(1, Math.min(3, runningPool || 3)),
                });
                setNewRoundName('');
              }}
              className="gap-1"
            >
              <Plus className="h-3.5 w-3.5" />
              Add Round
            </Button>
          </div>
        </CardHeader>
        <CardContent className="space-y-3">
          {computedRows.map(
            ({ round, entering, advancement, eliminate, weight, dirty }, idx) => {
              const isMutable = round.status === 'upcoming';
              return (
                <div
                  key={round.id}
                  className="rounded-xl border border-slate-200 bg-white p-4 space-y-3"
                >
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                      <Badge variant="info">R{round.sequence}</Badge>
                      <span className="font-semibold text-slate-900">
                        {round.name}
                      </span>
                      <Badge variant={isMutable ? 'default' : 'warning'}>
                        {round.status}
                      </Badge>
                      <span
                        data-testid={`round-entering-${round.sequence}`}
                        className="text-xs font-medium text-slate-500 tabular-nums"
                      >
                        {entering} entering
                      </span>
                    </div>

                    <div className="flex items-center gap-1.5">
                      <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        disabled={isBusy || !isMutable || idx === 0}
                        onClick={() => handleMove(idx, -1)}
                        title="Move up"
                      >
                        <ArrowUp className="h-3.5 w-3.5" />
                      </Button>
                      <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        disabled={
                          isBusy || !isMutable || idx === sortedRounds.length - 1
                        }
                        onClick={() => handleMove(idx, 1)}
                        title="Move down"
                      >
                        <ArrowDown className="h-3.5 w-3.5" />
                      </Button>
                      <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        disabled={isBusy || !isMutable}
                        onClick={() => onDeleteRound(round.id)}
                        className="text-rose-600 hover:bg-rose-50"
                        title="Delete round"
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </Button>
                    </div>
                  </div>

                  <div className="grid gap-3 sm:grid-cols-6 items-end">
                    <div className="space-y-1">
                      <label className="text-[11px] font-medium text-slate-600">
                        Strategy
                      </label>
                      <select
                        value={round.scoring_strategy}
                        disabled={isBusy || !isMutable}
                        onChange={(e) =>
                          onUpdateRound({
                            roundId: round.id,
                            scoring_strategy: e.target.value as ScoringStrategy,
                          })
                        }
                        className="h-8 w-full rounded-lg border border-slate-300 bg-white px-2 text-xs disabled:bg-slate-100"
                      >
                        <option value="weighted">Weighted</option>
                        <option value="ordinal">Ordinal</option>
                        <option value="trimmed">Trimmed</option>
                      </select>
                    </div>

                    <div className="space-y-1">
                      <label className="text-[11px] font-medium text-slate-600">
                        Reset Mode
                      </label>
                      <Button
                        type="button"
                        size="sm"
                        variant={round.is_back_to_zero ? 'outline' : 'secondary'}
                        className="h-8 w-full text-xs"
                        disabled={isBusy || !isMutable}
                        onClick={() =>
                          onUpdateRound({
                            roundId: round.id,
                            is_back_to_zero: !round.is_back_to_zero,
                          })
                        }
                      >
                        {round.is_back_to_zero ? 'Reset 0' : 'Cumulative'}
                      </Button>
                    </div>

                    <div className="space-y-1">
                      <label
                        className="text-[11px] font-medium text-slate-600"
                        htmlFor={`weight-${round.id}`}
                      >
                        Weight (%)
                      </label>
                      <Input
                        id={`weight-${round.id}`}
                        type="number"
                        step="0.01"
                        min={0}
                        max={100}
                        disabled={isBusy || !isMutable}
                        value={weight}
                        onChange={(e) => {
                          const nextWeight = Math.max(
                            0,
                            Math.min(100, Number(e.target.value) || 0),
                          );
                          setCutoffOverrides((prev) => ({
                            ...prev,
                            [round.id]: {
                              advancement_count: advancement,
                              eliminate_count: eliminate,
                              weight_in_overall: nextWeight,
                            },
                          }));
                        }}
                        className="h-8 text-xs tabular-nums"
                      />
                    </div>

                    <div className="space-y-1">
                      <label
                        className="text-[11px] font-medium text-slate-600"
                        htmlFor={`adv-${round.id}`}
                      >
                        Round {round.sequence} Advance Top
                      </label>
                      <Input
                        id={`adv-${round.id}`}
                        aria-label={`Round ${round.sequence} Advance Top`}
                        type="number"
                        min={1}
                        max={entering > 0 ? entering : 999}
                        disabled={isBusy || !isMutable}
                        value={advancement}
                        onChange={(e) => {
                          const nextAdv = Math.max(
                            1,
                            Math.min(
                              entering > 0 ? entering : 999,
                              Number(e.target.value) || 1,
                            ),
                          );
                          const nextElim =
                            entering > 0 ? Math.max(0, entering - nextAdv) : 0;
                          setCutoffOverrides((prev) => ({
                            ...prev,
                            [round.id]: {
                              advancement_count: nextAdv,
                              eliminate_count: nextElim,
                              weight_in_overall: weight,
                            },
                          }));
                        }}
                        className="h-8 text-xs tabular-nums"
                      />
                    </div>

                    <div className="space-y-1">
                      <label
                        className="text-[11px] font-medium text-slate-600"
                        htmlFor={`elim-${round.id}`}
                      >
                        Round {round.sequence} Eliminate
                      </label>
                      <Input
                        id={`elim-${round.id}`}
                        aria-label={`Round ${round.sequence} Eliminate`}
                        type="number"
                        min={0}
                        max={entering > 0 ? Math.max(0, entering - 1) : 999}
                        disabled={isBusy || !isMutable}
                        value={eliminate}
                        onChange={(e) => {
                          const maxElim =
                            entering > 0 ? Math.max(0, entering - 1) : 999;
                          const nextElim = Math.max(
                            0,
                            Math.min(maxElim, Number(e.target.value) || 0),
                          );
                          const nextAdv =
                            entering > 0
                              ? Math.max(1, entering - nextElim)
                              : advancement;
                          setCutoffOverrides((prev) => ({
                            ...prev,
                            [round.id]: {
                              advancement_count: nextAdv,
                              eliminate_count: nextElim,
                              weight_in_overall: weight,
                            },
                          }));
                        }}
                        className="h-8 text-xs tabular-nums"
                      />
                    </div>

                    <div>
                      <Button
                        type="button"
                        size="sm"
                        disabled={isBusy || !dirty || !isMutable}
                        onClick={() => {
                          onUpdateRound({
                            roundId: round.id,
                            advancement_count: advancement,
                            eliminate_count: eliminate,
                            weight_in_overall: weight,
                            cutoff_mode: 'top_n_advance',
                          });
                          setCutoffOverrides((prev) => {
                            const copy = { ...prev };
                            delete copy[round.id];
                            return copy;
                          });
                        }}
                        className="h-8 w-full text-xs"
                      >
                        {dirty ? 'Save Cutoffs' : 'Synced'}
                      </Button>
                    </div>
                  </div>
                </div>
              );
            },
          )}
        </CardContent>
      </Card>
    </div>
  );
}
