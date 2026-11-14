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

  const unlockRequests = useMemo(() => {
    const matrix = state?.scores_matrix;
    const list: {
      contestantId: number;
      contestantNumber: number;
      contestantName: string;
      judgeId: number;
      judgeNumber: number;
      judgeName: string;
    }[] = [];

    if (!matrix || !state) return list;

    const contestantMap = new Map((state.contestants ?? []).map((c) => [c.id, c]));
    const judgeMap = new Map((state.judges ?? []).map((j) => [j.id, j]));

    for (const [cIdStr, row] of Object.entries(matrix)) {
      const cId = Number(cIdStr);
      const contestant = contestantMap.get(cId);
      for (const [jIdStr, cell] of Object.entries(row)) {
        if (cell.status === 'UNLOCK_REQUESTED') {
          const jId = Number(jIdStr);
          const judge = judgeMap.get(jId);
          list.push({
            contestantId: cId,
            contestantNumber: contestant?.candidate_number ?? cId,
            contestantName: contestant?.name ?? 'Unknown',
            judgeId: jId,
            judgeNumber: judge?.judge_number ?? jId,
            judgeName: judge?.name ?? 'Judge',
          });
        }
      }
    }
    return list;
  }, [state]);

  if (isLoading) {
    return (
      <ConsoleLayout>
        <div className="py-16 text-center text-sm text-slate-500 font-mono-tech">
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
      unlockRequestCount={unlockRequests.length}
      onlineJudgesCount={onlineJudgeIds.length}
      totalJudgesCount={activeJudgesCount}
    >
      <div className="space-y-6">
        {unlockRequests.length > 0 && state.active_round && (
          <div className="wf-box p-4 bg-amber-50/90 border-amber-300 flex flex-wrap items-center justify-between gap-3">
            <div className="flex items-center gap-2.5">
              <span className="w-2.5 h-2.5 rounded-full bg-amber-500 animate-ping shrink-0" />
              <div className="text-xs">
                <strong className="font-bold text-amber-950 uppercase font-mono-tech">
                  {unlockRequests.length} Pending Unlock Request(s):
                </strong>
                <span className="text-amber-900 ml-1">
                  {unlockRequests
                    .map(
                      (u) =>
                        `Judge #${u.judgeNumber} (${u.judgeName}) on #${u.contestantNumber} ${u.contestantName}`
                    )
                    .join(' • ')}
                </span>
              </div>
            </div>
            <div className="flex flex-wrap gap-2">
              {unlockRequests.map((u) => (
                <button
                  key={`${u.contestantId}-${u.judgeId}`}
                  type="button"
                  disabled={resolveUnlock.isPending}
                  onClick={() =>
                    resolveUnlock.mutate({
                      round_id: state.active_round!.id,
                      contestant_id: u.contestantId,
                      judge_id: u.judgeId,
                      decision: 'APPROVE',
                    })
                  }
                  className="px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 border border-amber-500 text-xs font-bold transition-colors cursor-pointer disabled:opacity-50"
                >
                  Approve J#{u.judgeNumber} (#{u.contestantNumber})
                </button>
              ))}
            </div>
          </div>
        )}

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
          onStageContestantId={state.event.on_stage_contestant_id}
          scoresMatrix={state.scores_matrix ?? {}}
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
