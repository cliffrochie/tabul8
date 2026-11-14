import { useEffect, useMemo } from 'react';
import { JudgeLayout } from '@/components/layouts/JudgeLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
  ContestantRoster,
  CriterionScoreCard,
  EliminatedRosterDrawer,
  PerformerCard,
  RoundStageBanner,
  useEventState,
  useOfflineAutoFlush,
  useScoreMutations,
} from '@/features/judge-stage';
import { useAuthStore } from '@/stores/authStore';
import { useStageStore } from '@/stores/stageStore';

export function JudgeStagePage() {
  const eventId = useAuthStore((s) => s.judge?.event_id);
  const { data: state, isLoading } = useEventState(eventId);
  const { selectedContestantId, setSelectedContestantId } = useStageStore();

  const round = state?.active_round ?? null;
  const { flushQueue } = useOfflineAutoFlush(eventId, round?.id);
  const { upsertScore, lockSheets, requestUnlock } = useScoreMutations(eventId ?? 0, round?.id ?? 0);

  const activeQualifiers = useMemo(
    () =>
      (state?.contestants ?? []).filter(
        (c) =>
          c.status === 'active' && c.is_qualified_in_active_round !== false,
      ),
    [state?.contestants],
  );

  const eliminatedContestants = useMemo(
    () =>
      (state?.contestants ?? []).filter(
        (c) => c.is_qualified_in_active_round === false,
      ),
    [state?.contestants],
  );

  useEffect(() => {
    if (
      activeQualifiers.length > 0 &&
      !activeQualifiers.some((c) => c.id === selectedContestantId)
    ) {
      setSelectedContestantId(activeQualifiers[0].id);
    }
  }, [activeQualifiers, selectedContestantId, setSelectedContestantId]);

  if (isLoading || !state || !round) {
    return (
      <JudgeLayout>
        <div className="py-16 text-center text-sm text-slate-500">Loading active event state...</div>
      </JudgeLayout>
    );
  }

  const activeContestant =
    activeQualifiers.find((c) => c.id === selectedContestantId) ??
    activeQualifiers.find((c) => c.id === state.event.on_stage_contestant_id) ??
    activeQualifiers[0];

  if (!activeContestant) {
    return (
      <JudgeLayout eventTitle={state.event.title} onFlushQueue={flushQueue}>
        <RoundStageBanner
          round={round}
          totalRounds={state.rounds.length}
          activeQualifierCount={0}
        />
        <div className="py-16 text-center text-sm text-slate-500">No active qualifiers found for this round.</div>
        <EliminatedRosterDrawer contestants={eliminatedContestants} />
      </JudgeLayout>
    );
  }

  const sheet = state.my_scores?.[String(activeContestant.id)];
  const status = sheet?.status ?? 'DRAFT';
  const isLocked = status !== 'DRAFT' || round.is_locked;
  const criteria = round.criteria ?? [];

  return (
    <JudgeLayout eventTitle={state.event.title} onFlushQueue={flushQueue}>
      <RoundStageBanner
        round={round}
        totalRounds={state.rounds.length}
        activeQualifierCount={activeQualifiers.length}
      />

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div className="lg:col-span-4 flex flex-col gap-5">
          <PerformerCard
            contestant={activeContestant}
            isOnStage={activeContestant.id === state.event.on_stage_contestant_id}
            sheet={sheet}
          />
          <ContestantRoster
            contestants={activeQualifiers}
            selectedId={activeContestant.id}
            onStageId={state.event.on_stage_contestant_id}
            myScores={state.my_scores}
            onSelect={setSelectedContestantId}
          />
          <EliminatedRosterDrawer contestants={eliminatedContestants} />
        </div>

        <div className="lg:col-span-8 flex flex-col">
          <Card className="flex-1 flex flex-col justify-between overflow-hidden">
            <div className="px-6 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2 bg-white">
              <div>
                <span className="text-xs font-medium text-slate-500 uppercase tracking-wider">
                  {round.name}
                </span>
                <h3 className="text-base font-bold text-slate-900">
                  Scoring Sheet — #{activeContestant.candidate_number} {activeContestant.name}
                </h3>
              </div>
              <Badge variant={status === 'LOCKED' ? 'success' : status === 'UNLOCK_REQUESTED' ? 'warning' : 'secondary'}>
                {round.is_locked ? 'ROUND FROZEN' : status}
              </Badge>
            </div>

            <div className="p-5 space-y-4 flex-1 bg-slate-50/50">
              {criteria.map((crit, idx) => (
                <CriterionScoreCard
                  key={crit.id}
                  index={idx}
                  criterion={crit}
                  value={sheet?.criteria?.[String(crit.id)] ?? crit.min_score}
                  disabled={isLocked}
                  onChange={(nextVal) =>
                    upsertScore.mutate({
                      contestantId: activeContestant.id,
                      criterionId: crit.id,
                      rawScore: nextVal,
                    })
                  }
                />
              ))}
            </div>

            <div className="bg-slate-900 text-white px-6 py-4 flex flex-wrap items-center justify-between gap-4">
              <div className="flex items-center gap-6 tabular-nums">
                <div>
                  <span className="block text-[10px] uppercase tracking-wider text-slate-400">
                    Weighted Total
                  </span>
                  <span className="text-2xl font-bold">
                    {(sheet?.weighted_total ?? 0).toFixed(2)}{' '}
                    <span className="text-xs font-normal text-slate-400">/ 100.00</span>
                  </span>
                </div>
                <div className="h-8 w-px bg-slate-700 hidden sm:block" />
                <div className="hidden sm:block">
                  <span className="block text-[10px] uppercase tracking-wider text-slate-400">
                    Personal Rank
                  </span>
                  <span className="text-lg font-semibold">
                    #{sheet?.personal_rank ?? '—'} of {activeQualifiers.length}
                  </span>
                </div>
              </div>

              <div>
                {status === 'DRAFT' && (
                  <Button
                    variant="secondary"
                    size="lg"
                    disabled={round.is_locked || lockSheets.isPending}
                    onClick={() => lockSheets.mutate({ contestantIds: [activeContestant.id] })}
                  >
                    Lock & Submit Score
                  </Button>
                )}
                {status === 'LOCKED' && (
                  <Button
                    variant="outline"
                    size="lg"
                    disabled={round.is_locked || requestUnlock.isPending}
                    onClick={() => requestUnlock.mutate({ contestantId: activeContestant.id })}
                  >
                    Request Unlock from Tabulator
                  </Button>
                )}
                {status === 'UNLOCK_REQUESTED' && (
                  <Badge variant="warning" className="px-3 py-1.5 text-xs">
                    Pending Tabulator Approval
                  </Badge>
                )}
              </div>
            </div>
          </Card>
        </div>
      </div>
    </JudgeLayout>
  );
}
