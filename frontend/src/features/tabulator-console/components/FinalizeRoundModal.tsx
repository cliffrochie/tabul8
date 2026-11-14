import { useMemo, useState } from 'react';
import { AlertTriangle, CheckCircle2, ShieldAlert, UserMinus, X } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type {
  AdminMatrixCell,
  ContestantDto,
  JudgeDto,
  RoundDto,
  RoundResultDto,
} from '@/types/api';
import type {
  ExcludeJudgePayload,
  FinalizeRoundPayload,
} from '../api/tabulatorApi';

interface FinalizeRoundModalProps {
  isOpen: boolean;
  round: RoundDto | null;
  hasNextRound: boolean;
  contestants: ContestantDto[];
  judges: JudgeDto[];
  scoresMatrix: Record<string, Record<string, AdminMatrixCell>>;
  leaderboard: RoundResultDto[];
  isFinalizing: boolean;
  isExcludingJudge: boolean;
  onClose: () => void;
  onExcludeJudge: (payload: ExcludeJudgePayload) => void;
  onConfirmFinalize: (payload: FinalizeRoundPayload) => Promise<void>;
}

export function FinalizeRoundModal({
  isOpen,
  round,
  hasNextRound,
  contestants,
  judges,
  scoresMatrix,
  leaderboard,
  isFinalizing,
  isExcludingJudge,
  onClose,
  onExcludeJudge,
  onConfirmFinalize,
}: FinalizeRoundModalProps) {
  const activeQualifiers = useMemo(
    () =>
      contestants.filter(
        (c) =>
          c.status === 'active' && c.is_qualified_in_active_round !== false,
      ),
    [contestants],
  );
  const activeJudges = useMemo(
    () => judges.filter((j) => j.is_active),
    [judges],
  );

  const autoQualifierIds = useMemo(
    () =>
      leaderboard
        .filter((r) => r.is_qualified)
        .map((r) => r.contestant_id),
    [leaderboard],
  );

  const hasCutoffTie = useMemo(() => {
    if (!round) return false;
    const k = round.advancement_count;
    if (k <= 0 || leaderboard.length <= k) return false;
    const sorted = [...leaderboard].sort((a, b) => a.final_rank - b.final_rank);
    return sorted[k - 1]?.final_rank === sorted[k]?.final_rank;
  }, [round, leaderboard]);

  const [selectedIds, setSelectedIds] = useState<number[]>(autoQualifierIds);
  const [overrideReason, setOverrideReason] = useState('');
  const [activateNextRound, setActivateNextRound] = useState(hasNextRound);
  const [pushToLedReveal, setPushToLedReveal] = useState(false);
  const [excludeTargetJudgeId, setExcludeTargetJudgeId] = useState<number | null>(
    null,
  );
  const [excludeReason, setExcludeReason] = useState('');

  if (!isOpen || !round) {
    return null;
  }

  const incompleteJudges = activeJudges.filter((judge) =>
    activeQualifiers.some(
      (c) => scoresMatrix[String(c.id)]?.[String(judge.id)]?.status !== 'LOCKED',
    ),
  );

  const isQuorumComplete =
    incompleteJudges.length === 0 &&
    activeJudges.length > 0 &&
    activeQualifiers.length > 0;

  const autoSet = new Set(autoQualifierIds);
  const selectionChanged =
    selectedIds.length !== autoQualifierIds.length ||
    selectedIds.some((id) => !autoSet.has(id));

  const requiresOverride = hasCutoffTie || selectionChanged;
  const trimmedReason = overrideReason.trim();
  const canSubmit =
    isQuorumComplete &&
    selectedIds.length > 0 &&
    (!requiresOverride || trimmedReason.length >= 3) &&
    !isFinalizing;

  const contestantMap = new Map(activeQualifiers.map((c) => [c.id, c]));

  const toggleQualifier = (contestantId: number) => {
    setSelectedIds((prev) =>
      prev.includes(contestantId)
        ? prev.filter((id) => id !== contestantId)
        : [...prev, contestantId],
    );
  };

  const handleSubmit = async () => {
    const payload: FinalizeRoundPayload = {
      roundId: round.id,
      activate_next_round: activateNextRound,
      push_to_led_reveal: pushToLedReveal,
    };
    if (requiresOverride) {
      payload.qualified_contestant_ids = selectedIds;
      payload.override_reason = trimmedReason;
    }
    try {
      await onConfirmFinalize(payload);
      onClose();
    } catch {
      // Error toast is handled by mutation onError
    }
  };

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby="finalize-modal-title"
      className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-xs"
    >
      <div className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-xl">
        <div className="flex items-start justify-between border-b border-slate-200 px-6 py-4">
          <div>
            <p className="text-xs font-semibold uppercase tracking-wider text-indigo-600">
              WF-06B · Committee Finalization Gate
            </p>
            <h2
              id="finalize-modal-title"
              className="text-lg font-bold text-slate-900"
            >
              Finalize {round.name}
            </h2>
          </div>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={onClose}
            aria-label="Close Finalize Modal"
          >
            <X className="h-4 w-4" />
          </Button>
        </div>

        <div className="space-y-5 p-6">
          {/* Quorum Status */}
          <div
            className={`rounded-xl border p-4 ${
              isQuorumComplete
                ? 'border-emerald-200 bg-emerald-50/70'
                : 'border-amber-300 bg-amber-50'
            }`}
          >
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                {isQuorumComplete ? (
                  <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                ) : (
                  <AlertTriangle className="h-4 w-4 text-amber-600" />
                )}
                <span className="text-sm font-semibold text-slate-900">
                  {isQuorumComplete
                    ? `Quorum Verified: All ${activeJudges.length} active judges locked all ${activeQualifiers.length} contestants.`
                    : `Quorum Incomplete: ${incompleteJudges.length} judge(s) still have unlocked or pending sheets.`}
                </span>
              </div>
              <Badge variant={isQuorumComplete ? 'success' : 'warning'}>
                {activeJudges.length - incompleteJudges.length}/
                {activeJudges.length} Judges Locked
              </Badge>
            </div>

            {incompleteJudges.length > 0 && (
              <div className="mt-3 space-y-2 border-t border-amber-200 pt-3">
                {incompleteJudges.map((judge) => (
                  <div
                    key={judge.id}
                    className="rounded-lg border border-amber-200 bg-white p-3"
                  >
                    <div className="flex items-center justify-between gap-2">
                      <span className="text-xs font-medium text-slate-800">
                        Judge #{judge.judge_number} · {judge.name}
                      </span>
                      <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="h-7 gap-1 text-xs text-rose-700 hover:bg-rose-50"
                        onClick={() => {
                          setExcludeTargetJudgeId(
                            excludeTargetJudgeId === judge.id ? null : judge.id,
                          );
                          setExcludeReason('');
                        }}
                      >
                        <UserMinus className="h-3 w-3" />
                        Exclude Offline Judge
                      </Button>
                    </div>

                    {excludeTargetJudgeId === judge.id && (
                      <div className="mt-2 flex gap-2">
                        <input
                          type="text"
                          value={excludeReason}
                          onChange={(e) => setExcludeReason(e.target.value)}
                          placeholder="Reason for excluding judge (e.g. hardware failure)"
                          className="h-8 flex-1 rounded-lg border border-slate-300 px-2.5 text-xs focus:border-slate-500 focus:outline-none"
                        />
                        <Button
                          type="button"
                          size="sm"
                          variant="destructive"
                          className="h-8 text-xs"
                          disabled={
                            isExcludingJudge || excludeReason.trim().length < 3
                          }
                          onClick={() => {
                            onExcludeJudge({
                              roundId: round.id,
                              judgeId: judge.id,
                              reason: excludeReason.trim(),
                            });
                            setExcludeTargetJudgeId(null);
                            setExcludeReason('');
                          }}
                        >
                          Confirm Exclude
                        </Button>
                      </div>
                    )}
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Tie-at-cutoff Alert */}
          {hasCutoffTie && (
            <div className="flex items-start gap-3 rounded-xl border border-rose-300 bg-rose-50 p-4 text-rose-900">
              <ShieldAlert className="mt-0.5 h-5 w-5 shrink-0 text-rose-600" />
              <div className="text-xs leading-relaxed">
                <p className="font-bold uppercase tracking-wide">
                  TIE_AT_CUTOFF_ALERT · Top {round.advancement_count} Boundary
                </p>
                <p className="mt-1">
                  Multiple contestants are tied across the Rank #
                  {round.advancement_count} cutoff after all tie-breakers.
                  Explicitly verify the advancing qualifiers below and record
                  the committee&apos;s override reason.
                </p>
              </div>
            </div>
          )}

          {/* Qualifier Selection Roster */}
          <div className="space-y-2">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold text-slate-900">
                Advancing Qualifiers ({selectedIds.length} selected · Target Top{' '}
                {round.advancement_count})
              </h3>
              {selectionChanged && (
                <Badge variant="warning">Wildcard Override Active</Badge>
              )}
            </div>

            <div className="max-h-56 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200">
              {leaderboard.map((row) => {
                const contestant = contestantMap.get(row.contestant_id);
                const checked = selectedIds.includes(row.contestant_id);
                return (
                  <label
                    key={row.contestant_id}
                    className={`flex cursor-pointer items-center justify-between px-3 py-2.5 text-sm hover:bg-slate-50 ${
                      checked ? 'bg-emerald-50/40' : ''
                    }`}
                  >
                    <div className="flex items-center gap-3">
                      <input
                        type="checkbox"
                        checked={checked}
                        onChange={() => toggleQualifier(row.contestant_id)}
                        className="h-4 w-4 rounded border-slate-300 text-indigo-600"
                      />
                      <span className="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-900 text-xs font-bold text-white">
                        {row.final_rank}
                      </span>
                      <div>
                        <div className="font-medium text-slate-900">
                          #{contestant?.candidate_number ?? row.contestant_id}{' '}
                          {contestant?.name ?? 'Unknown'}
                        </div>
                        <div className="text-xs text-slate-500">
                          {contestant?.origin_or_group}
                        </div>
                      </div>
                    </div>
                    <div className="flex items-center gap-2 text-xs tabular-nums">
                      {row.is_tied && <Badge variant="warning">TIE</Badge>}
                      <span className="font-semibold text-slate-800">
                        {row.net_composite.toFixed(2)}
                      </span>
                    </div>
                  </label>
                );
              })}
            </div>
          </div>

          {requiresOverride && (
            <div className="space-y-1.5">
              <label
                htmlFor="override-reason-input"
                className="block text-xs font-semibold text-slate-700"
              >
                Committee Override / Tie-Break Reason (required)
              </label>
              <input
                id="override-reason-input"
                type="text"
                value={overrideReason}
                onChange={(e) => setOverrideReason(e.target.value)}
                placeholder="e.g. Board of Judges approved wildcard slot for tied #2 finalists"
                className="h-9 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-indigo-500 focus:outline-none"
              />
            </div>
          )}

          {/* Post-Finalization Stage Flags */}
          <div className="grid gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4 sm:grid-cols-2">
            {hasNextRound && (
              <label className="flex cursor-pointer items-start gap-2.5 text-xs">
                <input
                  type="checkbox"
                  checked={activateNextRound}
                  onChange={(e) => setActivateNextRound(e.target.checked)}
                  className="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600"
                />
                <span>
                  <strong className="block text-slate-900">
                    Activate Next Round Immediately
                  </strong>
                  <span className="text-slate-500">
                    Switches judges and console directly to the next stage.
                  </span>
                </span>
              </label>
            )}

            <label className="flex cursor-pointer items-start gap-2.5 text-xs">
              <input
                type="checkbox"
                checked={pushToLedReveal}
                onChange={(e) => setPushToLedReveal(e.target.checked)}
                className="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600"
              />
              <span>
                <strong className="block text-slate-900">
                  Push Qualifiers to LED Reveal
                </strong>
                <span className="text-slate-500">
                  Switches Arena Wall to finalist reveal spotlight cards.
                </span>
              </span>
            </label>
          </div>
        </div>

        <div className="flex items-center justify-end gap-2 border-t border-slate-200 px-6 py-4">
          <Button type="button" variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button
            type="button"
            disabled={!canSubmit}
            onClick={() => {
              void handleSubmit();
            }}
          >
            {isFinalizing
              ? 'Finalizing...'
              : `Confirm Finalize (${selectedIds.length} Advancing)`}
          </Button>
        </div>
      </div>
    </div>
  );
}
