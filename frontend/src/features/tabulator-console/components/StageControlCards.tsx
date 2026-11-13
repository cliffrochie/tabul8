import { Lock, Unlock, Radio, CheckCircle2, Sparkles } from 'lucide-react';
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
  EventDto,
  LedMode,
  RoundDto,
  ScoringStrategy,
} from '@/types/api';
import type { StageSyncPayload } from '../api/tabulatorApi';

interface StageControlCardsProps {
  event: EventDto;
  rounds: RoundDto[];
  activeRound: RoundDto | null;
  contestants: ContestantDto[];
  onlineJudgesCount: number;
  totalJudgesCount: number;
  isSyncing: boolean;
  isFinalizing: boolean;
  onSyncStage: (payload: StageSyncPayload) => void;
  onFinalizeRound: (roundId: number) => void;
}

const STRATEGIES: { value: ScoringStrategy; label: string; hint: string }[] = [
  { value: 'weighted', label: 'Weighted', hint: 'Criteria sum (0–100)' },
  { value: 'ordinal', label: 'Ordinal', hint: 'Rank sum (low wins)' },
  { value: 'trimmed', label: 'Trimmed', hint: 'Drop hi/lo (J≥5)' },
];

const LED_MODES: { value: LedMode; label: string }[] = [
  { value: 'on_stage', label: 'On-Stage' },
  { value: 'criteria', label: 'Criteria' },
  { value: 'reveal', label: 'Reveal' },
];

export function StageControlCards({
  event,
  rounds,
  activeRound,
  contestants,
  onlineJudgesCount,
  totalJudgesCount,
  isSyncing,
  isFinalizing,
  onSyncStage,
  onFinalizeRound,
}: StageControlCardsProps) {
  const activeContestants = contestants.filter((c) => c.status === 'active');

  return (
    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      {/* CFG-01: Round Switcher */}
      <Card>
        <CardHeader className="pb-3">
          <div className="flex items-center justify-between">
            <CardDescription className="text-xs font-medium uppercase tracking-wider">
              CFG-01 · Active Round
            </CardDescription>
            {activeRound && (
              <Badge
                variant={
                  activeRound.status === 'finalized'
                    ? 'success'
                    : activeRound.is_locked
                      ? 'warning'
                      : 'info'
                }
              >
                {activeRound.status}
              </Badge>
            )}
          </div>
          <CardTitle className="text-base">
            {activeRound ? activeRound.name : 'No Round Selected'}
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-2">
          <label className="sr-only" htmlFor="round-selector">
            Active Round
          </label>
          <select
            id="round-selector"
            value={activeRound?.id ?? ''}
            disabled={isSyncing}
            onChange={(e) => {
              const roundId = Number(e.target.value);
              if (!roundId) return;
              onSyncStage({ event_id: event.id, active_round_id: roundId });
            }}
            className="h-9 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-slate-500 focus:outline-none"
          >
            {rounds.map((r) => (
              <option key={r.id} value={r.id}>
                {r.sequence}. {r.name} (Top {r.advancement_count})
              </option>
            ))}
          </select>
          <p className="text-xs text-slate-500">
            Weight in overall: {activeRound?.weight_in_overall ?? 0}%
          </p>
        </CardContent>
      </Card>

      {/* CFG-02: Scoring Strategy */}
      <Card>
        <CardHeader className="pb-3">
          <CardDescription className="text-xs font-medium uppercase tracking-wider">
            CFG-02 · Scoring Strategy
          </CardDescription>
          <CardTitle className="text-base capitalize">
            {activeRound?.scoring_strategy ?? 'weighted'} Mode
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-2">
          <div className="grid grid-cols-3 gap-1.5">
            {STRATEGIES.map((strategy) => {
              const isSelected =
                activeRound?.scoring_strategy === strategy.value;
              const disabled =
                isSyncing ||
                !activeRound ||
                activeRound.status === 'finalized' ||
                (strategy.value === 'trimmed' && totalJudgesCount < 5);
              return (
                <Button
                  key={strategy.value}
                  type="button"
                  size="sm"
                  variant={isSelected ? 'default' : 'outline'}
                  disabled={disabled}
                  title={strategy.hint}
                  onClick={() => {
                    if (!activeRound) return;
                    onSyncStage({
                      event_id: event.id,
                      active_round_id: activeRound.id,
                      scoring_strategy: strategy.value,
                    });
                  }}
                >
                  {strategy.label}
                </Button>
              );
            })}
          </div>
          <p className="text-xs text-slate-500">
            {totalJudgesCount < 5
              ? 'Trimmed mean requires at least 5 active judges.'
              : 'Recomputes leaderboard immediately on switch.'}
          </p>
        </CardContent>
      </Card>

      {/* CFG-03: On-Stage & LED Wall Push */}
      <Card>
        <CardHeader className="pb-3">
          <div className="flex items-center justify-between">
            <CardDescription className="text-xs font-medium uppercase tracking-wider">
              CFG-03 · Stage &amp; LED Sync
            </CardDescription>
            <Badge variant="info" className="gap-1">
              <Radio className="h-3 w-3" />
              {onlineJudgesCount}/{totalJudgesCount}
            </Badge>
          </div>
          <CardTitle className="text-base">Spotlight &amp; Wall</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2">
          <select
            aria-label="On-Stage Contestant"
            value={event.on_stage_contestant_id ?? ''}
            disabled={isSyncing}
            onChange={(e) => {
              const val = e.target.value ? Number(e.target.value) : null;
              onSyncStage({
                event_id: event.id,
                on_stage_contestant_id: val,
              });
            }}
            className="h-9 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-slate-500 focus:outline-none"
          >
            <option value="">-- No Contestant On Stage --</option>
            {activeContestants.map((c) => (
              <option key={c.id} value={c.id}>
                #{c.candidate_number} {c.name} ({c.origin_or_group})
              </option>
            ))}
          </select>

          <div className="grid grid-cols-3 gap-1.5">
            {LED_MODES.map((mode) => (
              <Button
                key={mode.value}
                type="button"
                size="sm"
                variant={event.led_mode === mode.value ? 'default' : 'outline'}
                disabled={isSyncing}
                onClick={() =>
                  onSyncStage({ event_id: event.id, led_mode: mode.value })
                }
              >
                {mode.label}
              </Button>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* CFG-04: Freeze & Finalize Round */}
      <Card>
        <CardHeader className="pb-3">
          <CardDescription className="text-xs font-medium uppercase tracking-wider">
            CFG-04 · Round Gate
          </CardDescription>
          <CardTitle className="text-base">
            {activeRound?.is_locked ? 'Round Frozen' : 'Scoring Open'}
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-2">
          <div className="flex gap-2">
            <Button
              type="button"
              size="sm"
              variant={activeRound?.is_locked ? 'outline' : 'secondary'}
              className="flex-1 gap-1.5"
              disabled={
                isSyncing ||
                !activeRound ||
                activeRound.status === 'finalized'
              }
              onClick={() => {
                if (!activeRound) return;
                onSyncStage({
                  event_id: event.id,
                  active_round_id: activeRound.id,
                  is_round_frozen: !activeRound.is_locked,
                });
              }}
            >
              {activeRound?.is_locked ? (
                <>
                  <Unlock className="h-3.5 w-3.5" />
                  Unfreeze
                </>
              ) : (
                <>
                  <Lock className="h-3.5 w-3.5" />
                  Freeze Round
                </>
              )}
            </Button>

            <Button
              type="button"
              size="sm"
              variant="default"
              className="flex-1 gap-1.5"
              disabled={
                isFinalizing ||
                !activeRound ||
                activeRound.status === 'finalized'
              }
              onClick={() => {
                if (!activeRound) return;
                onFinalizeRound(activeRound.id);
              }}
            >
              {activeRound?.status === 'finalized' ? (
                <>
                  <CheckCircle2 className="h-3.5 w-3.5" />
                  Finalized
                </>
              ) : (
                <>
                  <Sparkles className="h-3.5 w-3.5" />
                  Finalize Top {activeRound?.advancement_count ?? 0}
                </>
              )}
            </Button>
          </div>
          <p className="text-xs text-slate-500">
            Finalizing requires all active judges to lock every contestant.
          </p>
        </CardContent>
      </Card>
    </div>
  );
}
