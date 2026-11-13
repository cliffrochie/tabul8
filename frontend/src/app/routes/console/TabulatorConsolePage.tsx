import { useMemo } from 'react';
import { useSearchParams } from 'react-router-dom';
import { toast } from 'sonner';
import { ConsoleLayout } from '@/components/layouts/ConsoleLayout';
import {
  downloadTallyPdf,
  LiveSubmissionMatrix,
  MasterLeaderboard,
  StageControlCards,
  useAdminEventState,
  useAdminMutations,
} from '@/features/tabulator-console';

export function TabulatorConsolePage() {
  const [searchParams] = useSearchParams();
  const eventId = Number(searchParams.get('eventId')) || 1;

  const { data: state, isLoading, isError, onlineJudgeIds } =
    useAdminEventState(eventId);
  const { resolveUnlock, applyDeduction, syncStage, finalizeRound } =
    useAdminMutations(eventId, state?.active_round?.id);

  const judges = state?.judges ?? [];
  const activeJudgesCount = judges.filter((j) => j.is_active).length;
  const scoresMatrix = state?.scores_matrix ?? {};

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

  return (
    <ConsoleLayout
      eventTitle={state.event.title}
      unlockRequestCount={unlockRequestCount}
      onlineJudgesCount={onlineJudgeIds.length}
      totalJudgesCount={activeJudgesCount}
    >
      <div className="space-y-6">
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
          onFinalizeRound={(roundId) => finalizeRound.mutate(roundId)}
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
    </ConsoleLayout>
  );
}
