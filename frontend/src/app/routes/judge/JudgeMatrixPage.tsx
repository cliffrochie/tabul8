import { JudgeLayout } from '@/components/layouts/JudgeLayout';
import { JudgeMatrixGrid } from '@/features/judge-matrix';
import {
  EliminatedRosterDrawer,
  RoundStageBanner,
  useEventState,
  useOfflineAutoFlush,
  useScoreMutations,
} from '@/features/judge-stage';
import { useAuthStore } from '@/stores/authStore';

export function JudgeMatrixPage() {
  const eventId = useAuthStore((s) => s.judge?.event_id);
  const { data: state, isLoading } = useEventState(eventId);
  const round = state?.active_round ?? null;

  const { flushQueue } = useOfflineAutoFlush(eventId, round?.id);
  const { upsertScore, lockSheets, requestUnlock } = useScoreMutations(eventId ?? 0, round?.id ?? 0);

  if (isLoading || !state || !round) {
    return (
      <JudgeLayout>
        <div className="py-16 text-center text-sm text-slate-500">Loading comparative matrix...</div>
      </JudgeLayout>
    );
  }

  const activeQualifiers = state.contestants.filter(
    (c) => c.status === 'active' && c.is_qualified_in_active_round !== false,
  );
  const eliminatedContestants = state.contestants.filter(
    (c) => c.is_qualified_in_active_round === false,
  );

  return (
    <JudgeLayout eventTitle={state.event.title} onFlushQueue={flushQueue}>
      <RoundStageBanner
        round={round}
        totalRounds={state.rounds.length}
        activeQualifierCount={activeQualifiers.length}
      />
      <JudgeMatrixGrid
        roundName={round.name}
        isRoundFrozen={round.is_locked}
        contestants={activeQualifiers}
        criteria={round.criteria ?? []}
        myScores={state.my_scores}
        onScoreChange={(contestantId, criterionId, nextVal) =>
          upsertScore.mutate({ contestantId, criterionId, rawScore: nextVal })
        }
        onLockRow={(contestantId) => lockSheets.mutate({ contestantIds: [contestantId] })}
        onLockAll={() => lockSheets.mutate({ lockAll: true })}
        onRequestUnlock={(contestantId) => requestUnlock.mutate({ contestantId })}
      />
      <EliminatedRosterDrawer contestants={eliminatedContestants} />
    </JudgeLayout>
  );
}
