import { useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { toast } from 'sonner';
import { ConsoleLayout } from '@/components/layouts/ConsoleLayout';
import {
  downloadTallyPdf,
  FinalizeRoundModal,
  LiveSubmissionMatrix,
  MasterLeaderboard,
  MultiRoundPipelineStepper,
  StageControlCards,
  useAdminEventState,
  useAdminMutations,
} from '@/features/tabulator-console';

export function TabulatorConsolePage() {
  const [searchParams] = useSearchParams();
  const eventId = Number(searchParams.get('eventId')) || 1;
  const [isFinalizeModalOpen, setIsFinalizeModalOpen] = useState(false);

  const { data: state, isLoading, isError, onlineJudgeIds } =
    useAdminEventState(eventId);
  const {
    resolveUnlock,
    applyDeduction,
    syncStage,
    finalizeRound,
    reopenRound,
    excludeJudge,
  } = useAdminMutations(eventId, state?.active_round?.id);

  const judges = state?.judges ?? [];
  const activeJudgesCount = judges.filter((j) => j.is_active).length;
  const scoresMatrix = useMemo(
    () => state?.scores_matrix ?? {},
    [state?.scores_matrix],
  );

  const unlockRequestCount = useMemo(() => {
    let count = 0;
    for (const row of Object.values(scoresMatrix)) {
      for (const cell of Object.values(row)) {
        if (cell.status === 'UNLOCK_REQUESTED') {
          count += 1;
        }
      }
    }
    return count;
  }, [scoresMatrix]);

  if (isLoading) {
    return (
      <ConsoleLayout>
        <div className="py-16 text-center text-sm text-slate-500">
          Loading Tabulator Console...
        </div>
      </ConsoleLayout>
    );
  }

  if (isError || !state) {
    return (
      <ConsoleLayout>
        <div className="rounded-xl border border-rose-200 bg-rose-50 p-6 text-center text-sm text-rose-700">
          Unable to load event state for Event #{eventId}. Ensure the backend
          API is running and your account has permission.
        </div>
      </ConsoleLayout>
    );
  }

  const activeRound = state.active_round;
  const hasNextRound =
    activeRound !== null &&
    state.rounds.some((r) => r.sequence > activeRound.sequence);

  return (
    <ConsoleLayout
      eventTitle={state.event.title}
      unlockRequestCount={unlockRequestCount}
      onlineJudgesCount={onlineJudgeIds.length}
      totalJudgesCount={activeJudgesCount}
    >
      <div className="space-y-6">
        <MultiRoundPipelineStepper
          rounds={state.rounds}
          roundFunnel={state.round_funnel ?? []}
          activeRoundId={state.active_round?.id ?? null}
          isSyncing={syncStage.isPending}
          isReopening={reopenRound.isPending}
          onSelectRound={(roundId) =>
            syncStage.mutate({
              event_id: state.event.id,
              active_round_id: roundId,
            })
          }
          onReopenRound={(roundId) => reopenRound.mutate({ roundId })}
        />

        <StageControlCards
          event={state.event}
          rounds={state.rounds}
          activeRound={state.active_round}
          contestants={state.contestants}
          onlineJudgesCount={onlineJudgeIds.length}
          totalJudgesCount={activeJudgesCount}
          isSyncing={syncStage.isPending}
          isFinalizing={finalizeRound.isPending}
          onSyncStage={(payload) => syncStage.mutate(payload)}
          onOpenFinalizeModal={() => setIsFinalizeModalOpen(true)}
        />

        <LiveSubmissionMatrix
          activeRound={state.active_round}
          contestants={state.contestants}
          judges={judges}
          scoresMatrix={scoresMatrix}
          isResolving={resolveUnlock.isPending}
          onResolveUnlock={(payload) => resolveUnlock.mutate(payload)}
        />

        <MasterLeaderboard
          eventId={state.event.id}
          activeRound={state.active_round}
          contestants={state.contestants}
          judges={judges}
          leaderboard={state.leaderboard ?? []}
          isUpdatingDeduction={applyDeduction.isPending}
          onApplyDeduction={(payload) => applyDeduction.mutate(payload)}
          onExportPdf={(roundId) => {
            void downloadTallyPdf(roundId).catch(() => {
              toast.error('Failed to export official tally PDF');
            });
          }}
        />
      </div>

      {isFinalizeModalOpen && activeRound && (
        <FinalizeRoundModal
          key={activeRound.id}
          isOpen={isFinalizeModalOpen}
          round={activeRound}
          hasNextRound={hasNextRound}
          contestants={state.contestants}
          judges={judges}
          scoresMatrix={scoresMatrix}
          leaderboard={state.leaderboard ?? []}
          isFinalizing={finalizeRound.isPending}
          isExcludingJudge={excludeJudge.isPending}
          onClose={() => setIsFinalizeModalOpen(false)}
          onExcludeJudge={(payload) => excludeJudge.mutate(payload)}
          onConfirmFinalize={async (payload) => {
            await finalizeRound.mutateAsync(payload);
          }}
        />
      )}
    </ConsoleLayout>
  );
}
