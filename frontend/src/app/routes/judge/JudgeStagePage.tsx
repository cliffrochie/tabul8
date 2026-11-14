import { useState } from 'react';
import { JudgeLayout } from '@/components/layouts/JudgeLayout';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/components/ui/cn';
import {
  ContestantRoster,
  CriterionScoreCard,
  PerformerCard,
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
  const [mobileRosterOpen, setMobileRosterOpen] = useState(false);

  const round = state?.active_round ?? null;
  const { flushQueue } = useOfflineAutoFlush(eventId, round?.id);
  const { upsertScore, lockSheets, requestUnlock } = useScoreMutations(
    eventId ?? 0,
    round?.id ?? 0
  );

  if (isLoading || !state || !round) {
    return (
      <JudgeLayout>
        <div className="py-16 text-center text-sm text-slate-500 font-mono-tech">
          Loading active event state...
        </div>
      </JudgeLayout>
    );
  }

  const activeContestant =
    state.contestants.find((c) => c.id === selectedContestantId) ??
    state.contestants.find((c) => c.id === state.event.on_stage_contestant_id) ??
    state.contestants[0];

  if (!activeContestant) {
    return (
      <JudgeLayout eventTitle={state.event.title} onFlushQueue={flushQueue}>
        <div className="py-16 text-center text-sm text-slate-500">
          No active contestants found.
        </div>
      </JudgeLayout>
    );
  }

  const sheet = state.my_scores?.[String(activeContestant.id)];
  const status = sheet?.status ?? 'DRAFT';
  const isLocked = status !== 'DRAFT' || round.is_locked;
  const criteria = round.criteria ?? [];

  return (
    <JudgeLayout eventTitle={state.event.title} onFlushQueue={flushQueue}>
      <div className="space-y-4">
        {/* Mobile Quick-Switch Candidate Strip (Visible on < lg screens) */}
        <div className="lg:hidden wf-box p-2.5 bg-white">
          <div className="flex items-center justify-between mb-2 px-1">
            <span className="text-[11px] font-mono-tech font-bold uppercase text-slate-500">
              Quick Switch Candidate ({state.contestants.length})
            </span>
            <button
              type="button"
              onClick={() => setMobileRosterOpen((prev) => !prev)}
              className="text-[11px] font-semibold text-slate-900 underline cursor-pointer"
            >
              {mobileRosterOpen ? 'Hide Full Roster' : 'Expand Roster'}
            </button>
          </div>
          <div className="flex items-center gap-2 overflow-x-auto no-scrollbar pb-1">
            {state.contestants.map((c) => {
              const cSheet = state.my_scores?.[String(c.id)];
              const isSelected = c.id === activeContestant.id;
              const isOnStage = c.id === state.event.on_stage_contestant_id;
              const dotColor =
                cSheet?.status === 'LOCKED'
                  ? 'bg-emerald-500'
                  : cSheet?.status === 'UNLOCK_REQUESTED'
                    ? 'bg-amber-500'
                    : 'bg-slate-300';

              return (
                <button
                  key={c.id}
                  type="button"
                  onClick={() => setSelectedContestantId(c.id)}
                  className={cn(
                    'shrink-0 px-3 py-2 rounded-xl border text-xs font-semibold flex items-center gap-2 transition-all cursor-pointer',
                    isSelected
                      ? 'bg-slate-900 text-white border-slate-900 shadow-sm'
                      : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'
                  )}
                >
                  <span className="font-mono-tech font-bold">#{c.candidate_number}</span>
                  <span className="truncate max-w-[110px]">{c.name}</span>
                  {isOnStage && (
                    <span
                      className={cn(
                        'px-1.5 py-0.5 rounded text-[9px] font-mono-tech font-bold',
                        isSelected
                          ? 'bg-emerald-400 text-slate-950'
                          : 'bg-emerald-100 text-emerald-800'
                      )}
                    >
                      STAGE
                    </span>
                  )}
                  <span className={cn('w-2 h-2 rounded-full', dotColor)} />
                </button>
              );
            })}
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
          {/* LEFT COLUMN: PERFORMER PROFILE & ROSTER (4 COLS) */}
          <div className="lg:col-span-4 flex flex-col gap-5">
            <PerformerCard
              contestant={activeContestant}
              isOnStage={activeContestant.id === state.event.on_stage_contestant_id}
              sheet={sheet}
            />
            <ContestantRoster
              contestants={state.contestants}
              selectedId={activeContestant.id}
              onStageId={state.event.on_stage_contestant_id}
              myScores={state.my_scores}
              onSelect={setSelectedContestantId}
              isOpenMobile={mobileRosterOpen}
            />
          </div>

          {/* RIGHT COLUMN: CRITERIA SLIDERS & LOCK BAR (8 COLS) */}
          <div className="lg:col-span-8 flex flex-col">
            <div className="wf-box flex-1 flex flex-col justify-between overflow-hidden">
              <div className="px-4 sm:px-6 py-4 bg-slate-50/80 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2">
                <div>
                  <span className="text-[11px] font-mono-tech font-semibold uppercase text-slate-500">
                    [WF-03] Evaluation Form • {round.name}
                  </span>
                  <h3 className="text-base sm:text-lg font-extrabold text-slate-900 tracking-tight">
                    Scoring Sheet — #{activeContestant.candidate_number}{' '}
                    {activeContestant.name}
                  </h3>
                </div>
                <Badge
                  variant={
                    round.is_locked
                      ? 'warning'
                      : status === 'LOCKED'
                        ? 'success'
                        : status === 'UNLOCK_REQUESTED'
                          ? 'warning'
                          : 'secondary'
                  }
                >
                  {round.is_locked ? 'ROUND FROZEN' : status}
                </Badge>
              </div>

              <div className="p-4 sm:p-6 space-y-4 flex-1">
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

              {/* Bottom Lock & Submit Bar */}
              <div className="bg-slate-900 text-white p-4 sm:px-6 border-t border-slate-800 flex flex-wrap items-center justify-between gap-4">
                <div className="flex items-center gap-5 font-mono-tech">
                  <div>
                    <span className="block text-[10px] text-slate-400 uppercase">
                      Weighted Total
                    </span>
                    <span className="text-2xl sm:text-3xl font-bold text-white">
                      {(sheet?.weighted_total ?? 0).toFixed(2)}{' '}
                      <span className="text-xs font-normal text-slate-400">
                        / 100.00
                      </span>
                    </span>
                  </div>
                  <div className="h-8 w-px bg-slate-700" />
                  <div>
                    <span className="block text-[10px] text-slate-400 uppercase">
                      Personal Rank
                    </span>
                    <span className="text-base sm:text-lg font-bold text-emerald-400">
                      Rank #{sheet?.personal_rank ?? '—'} of{' '}
                      {state.contestants.length}
                    </span>
                  </div>
                </div>

                <div className="w-full sm:w-auto">
                  {status === 'DRAFT' && (
                    <button
                      type="button"
                      disabled={round.is_locked || lockSheets.isPending}
                      onClick={() =>
                        lockSheets.mutate({ contestantIds: [activeContestant.id] })
                      }
                      className="w-full sm:w-auto rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-950 border border-emerald-400 px-5 py-2.5 text-xs font-bold shadow-sm transition-all cursor-pointer disabled:opacity-45 disabled:cursor-not-allowed"
                    >
                      {lockSheets.isPending
                        ? 'Locking...'
                        : 'Lock & Submit Score Sheet'}
                    </button>
                  )}

                  {status === 'LOCKED' && (
                    <button
                      type="button"
                      disabled={round.is_locked || requestUnlock.isPending}
                      onClick={() =>
                        requestUnlock.mutate({ contestantId: activeContestant.id })
                      }
                      className="w-full sm:w-auto rounded-lg bg-slate-800 hover:bg-slate-700 text-white border border-slate-600 px-4 py-2.5 text-xs font-semibold transition-all cursor-pointer disabled:opacity-45 disabled:cursor-not-allowed"
                    >
                      {requestUnlock.isPending
                        ? 'Requesting...'
                        : 'Request Unlock from Tabulator'}
                    </button>
                  )}

                  {status === 'UNLOCK_REQUESTED' && (
                    <span className="w-full sm:w-auto text-center px-3.5 py-2 rounded-lg border border-dashed border-amber-400/60 bg-amber-500/10 text-amber-300 font-mono-tech text-xs inline-block">
                      Pending Tabulator Unlock Approval...
                    </span>
                  )}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </JudgeLayout>
  );
}
