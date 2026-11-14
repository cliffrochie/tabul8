<?php

namespace App\Services;

use App\Events\JudgeScoreUpdated;
use App\Events\LedWallUpdated;
use App\Events\StageStateUpdated;
use App\Events\UnlockRequestResolved;
use App\Exceptions\UnresolvedCutoffTieException;
use App\Http\Resources\ContestantResource;
use App\Http\Resources\RoundResultResource;
use App\Models\Contestant;
use App\Models\Deduction;
use App\Models\Event;
use App\Models\Judge;
use App\Models\Round;
use App\Models\RoundResult;
use App\Models\Score;
use App\Models\ScoreAuditLog;
use App\Models\User;
use App\Services\Scoring\ScoringEngineService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class AdminTabulationService
{
    public function __construct(
        private readonly ScoringEngineService $scoringEngine,
        private readonly RoundRosterService $rosterService,
    ) {}

    /**
     * @return Collection<int, RoundResult>
     */
    public function resolveUnlock(
        User $actor,
        Round $round,
        int $contestantId,
        int $judgeId,
        string $decision,
        ?string $reason,
        string $ipAddress
    ): Collection {
        $newStatus = $decision === 'APPROVE' ? 'DRAFT' : 'LOCKED';
        $auditAction = $decision === 'APPROVE' ? 'UNLOCK_APPROVED' : 'UNLOCK_REJECTED';

        DB::transaction(function () use ($actor, $round, $contestantId, $judgeId, $decision, $newStatus, $auditAction, $reason, $ipAddress): void {
            $updated = Score::where('round_id', $round->id)
                ->where('contestant_id', $contestantId)
                ->where('judge_id', $judgeId)
                ->where('status', 'UNLOCK_REQUESTED')
                ->update([
                    'status' => $newStatus,
                    'submitted_at' => $newStatus === 'LOCKED' ? now() : null,
                ]);

            if ($updated === 0) {
                throw new ConflictHttpException('No pending unlock request found for this judge and contestant.');
            }

            ScoreAuditLog::create([
                'event_id' => $round->event_id,
                'round_id' => $round->id,
                'judge_id' => $judgeId,
                'contestant_id' => $contestantId,
                'actor_user_id' => $actor->id,
                'action' => $auditAction,
                'reason' => $reason ?? ($decision === 'APPROVE' ? 'Approved by Tabulator' : 'Rejected by Tabulator'),
                'ip_address' => $ipAddress,
            ]);
        });

        $leaderboard = $this->scoringEngine->tabulateRound($round);
        $weightedTotal = (float) Score::where('round_id', $round->id)
            ->where('judge_id', $judgeId)
            ->where('contestant_id', $contestantId)
            ->sum('weighted_score');

        broadcast(new UnlockRequestResolved(
            judgeId: $judgeId,
            roundId: $round->id,
            contestantId: $contestantId,
            decision: $decision,
            newStatus: $newStatus,
            resolvedBy: $actor->name,
            timestamp: now()->toIso8601String(),
        ));

        broadcast(new JudgeScoreUpdated(
            eventId: $round->event_id,
            roundId: $round->id,
            judgeId: $judgeId,
            contestantId: $contestantId,
            weightedTotal: round($weightedTotal, 2, PHP_ROUND_HALF_UP),
            status: $newStatus,
            updatedAt: now()->toIso8601String(),
            leaderboard: RoundResultResource::collection($leaderboard)->resolve(),
        ));

        return $leaderboard;
    }

    /**
     * @return Collection<int, RoundResult>
     */
    public function applyDeduction(
        User $actor,
        Round $round,
        int $contestantId,
        float $points,
        ?string $reason,
        string $ipAddress
    ): Collection {
        DB::transaction(function () use ($actor, $round, $contestantId, $points, $reason, $ipAddress): void {
            $existing = Deduction::where('round_id', $round->id)
                ->where('contestant_id', $contestantId)
                ->first();

            $oldPoints = $existing ? (float) $existing->points_deducted : 0.0;

            if ($points <= 0.0) {
                $existing?->delete();
            } else {
                Deduction::updateOrCreate(
                    [
                        'round_id' => $round->id,
                        'contestant_id' => $contestantId,
                    ],
                    [
                        'applied_by' => $actor->id,
                        'points_deducted' => round($points, 2, PHP_ROUND_HALF_UP),
                        'reason' => $reason ?: 'Technical Rule Penalty',
                    ]
                );
            }

            ScoreAuditLog::create([
                'event_id' => $round->event_id,
                'round_id' => $round->id,
                'contestant_id' => $contestantId,
                'actor_user_id' => $actor->id,
                'action' => 'DEDUCTION_APPLIED',
                'old_value' => $oldPoints,
                'new_value' => round($points, 3, PHP_ROUND_HALF_UP),
                'reason' => $reason ?: 'Technical Rule Penalty',
                'ip_address' => $ipAddress,
            ]);
        });

        return $this->scoringEngine->tabulateRound($round);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{event: Event, active_round: Round|null, leaderboard: Collection<int, RoundResult>}
     */
    public function syncStage(User $actor, Event $event, array $payload, string $ipAddress): array
    {
        DB::transaction(function () use ($actor, $event, $payload, $ipAddress): void {
            if (array_key_exists('active_round_id', $payload)) {
                $event->active_round_id = $payload['active_round_id'];
                Round::where('id', $payload['active_round_id'])
                    ->where('event_id', $event->id)
                    ->where('status', 'upcoming')
                    ->update(['status' => 'active']);
            }

            if (array_key_exists('on_stage_contestant_id', $payload)) {
                $event->on_stage_contestant_id = $payload['on_stage_contestant_id'];
            }

            if (array_key_exists('led_mode', $payload)) {
                $event->led_mode = $payload['led_mode'];
            }

            if (array_key_exists('revealed_contestant_ids', $payload)) {
                $event->revealed_contestant_ids = $payload['revealed_contestant_ids'];
            }

            $event->save();

            $activeRound = $event->active_round_id ? Round::find($event->active_round_id) : null;
            if ($activeRound !== null) {
                if (array_key_exists('scoring_strategy', $payload) && $activeRound->scoring_strategy !== $payload['scoring_strategy']) {
                    $oldStrat = $activeRound->scoring_strategy;
                    $activeRound->scoring_strategy = $payload['scoring_strategy'];
                    $activeRound->save();

                    ScoreAuditLog::create([
                        'event_id' => $event->id,
                        'round_id' => $activeRound->id,
                        'actor_user_id' => $actor->id,
                        'action' => 'STRATEGY_CHANGED',
                        'metadata' => ['old' => $oldStrat, 'new' => $activeRound->scoring_strategy],
                        'ip_address' => $ipAddress,
                    ]);
                }

                if (array_key_exists('is_round_frozen', $payload)) {
                    $frozen = (bool) $payload['is_round_frozen'];
                    if ($activeRound->status === 'finalized' && ! $frozen) {
                        throw new ConflictHttpException('Finalized rounds cannot be unfrozen.');
                    }

                    $activeRound->is_locked = $frozen;
                    $activeRound->status = $frozen ? 'locked' : 'active';
                    $activeRound->locked_at = $frozen ? now() : null;
                    $activeRound->save();

                    ScoreAuditLog::create([
                        'event_id' => $event->id,
                        'round_id' => $activeRound->id,
                        'actor_user_id' => $actor->id,
                        'action' => $frozen ? 'ROUND_FROZEN' : 'ROUND_UNFROZEN',
                        'ip_address' => $ipAddress,
                    ]);
                }
            }
        });

        $event->refresh();
        $activeRound = $event->active_round_id ? Round::with('criteria')->find($event->active_round_id) : null;
        $leaderboard = $activeRound ? $this->scoringEngine->tabulateRound($activeRound) : collect();

        broadcast(new StageStateUpdated(
            eventId: $event->id,
            activeRoundId: $event->active_round_id,
            scoringStrategy: $activeRound?->scoring_strategy ?? 'weighted',
            onStageContestantId: $event->on_stage_contestant_id,
            isRoundFrozen: $activeRound?->is_locked ?? false,
            ledMode: $event->led_mode,
            revealedContestantIds: $event->revealed_contestant_ids ?? [],
        ));

        $onStage = $event->on_stage_contestant_id ? Contestant::find($event->on_stage_contestant_id) : null;
        $revealed = Contestant::whereIn('id', $event->revealed_contestant_ids ?? [])->get();

        broadcast(new LedWallUpdated(
            eventId: $event->id,
            ledMode: $event->led_mode,
            onStageContestant: $onStage ? (new ContestantResource($onStage))->resolve() : null,
            revealedContestantIds: $event->revealed_contestant_ids ?? [],
            finalistsPublicCards: ContestantResource::collection($revealed)->resolve(),
        ));

        return [
            'event' => $event,
            'active_round' => $activeRound,
            'leaderboard' => $leaderboard,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return Collection<int, RoundResult>
     */
    public function finalizeRound(User $actor, Round $round, string $ipAddress, array $options = []): Collection
    {
        if ($round->status === 'finalized') {
            throw new ConflictHttpException('Round is already finalized.');
        }

        $activeJudges = $round->event->judges()->where('is_active', true)->pluck('id');
        $activeContestants = collect($this->rosterService->getActiveContestantIds($round));
        $leafCriteria = $round->leafCriteria();

        if ($activeJudges->isEmpty() || $activeContestants->isEmpty() || $leafCriteria->isEmpty()) {
            throw new ConflictHttpException(
                'Cannot finalize round: active judges, qualified contestants, and judging criteria are required.'
            );
        }

        $unlockedCount = Score::where('round_id', $round->id)
            ->whereIn('judge_id', $activeJudges)
            ->whereIn('contestant_id', $activeContestants)
            ->where('status', '!=', 'LOCKED')
            ->count();

        $expectedCriteriaCount = $leafCriteria->count();
        $lockedCount = Score::where('round_id', $round->id)
            ->whereIn('judge_id', $activeJudges)
            ->whereIn('contestant_id', $activeContestants)
            ->whereIn('criterion_id', $leafCriteria->pluck('id'))
            ->where('status', 'LOCKED')
            ->count();

        $expectedTotalCells = $activeJudges->count() * $activeContestants->count() * $expectedCriteriaCount;

        if ($unlockedCount > 0 || $lockedCount < $expectedTotalCells) {
            throw new ConflictHttpException(
                'Cannot finalize round: all active judges must lock their score sheets first.'
            );
        }

        $leaderboard = $this->scoringEngine->tabulateRound($round);

        /** @var array<int, int>|null $qualifiedIdsOverride */
        $qualifiedIdsOverride = isset($options['qualified_contestant_ids']) && is_array($options['qualified_contestant_ids'])
            ? array_values(array_unique(array_intersect(
                array_map('intval', $options['qualified_contestant_ids']),
                $activeContestants->map('intval')->all()
            )))
            : null;
        $overrideReason = isset($options['override_reason']) && is_string($options['override_reason'])
            ? trim($options['override_reason']) ?: null
            : null;
        $activateNextRound = (bool) ($options['activate_next_round'] ?? false);
        $pushToLedReveal = (bool) ($options['push_to_led_reveal'] ?? false);

        if ($qualifiedIdsOverride === null) {
            if ($this->scoringEngine->hasUnresolvedCutoffTie($round, $leaderboard)) {
                $k = (int) $round->advancement_count;
                $atCutoff = $leaderboard->sortBy('final_rank')->values()->get($k - 1);
                $tiedIds = $atCutoff !== null
                    ? $leaderboard->where('final_rank', $atCutoff->final_rank)
                        ->pluck('contestant_id')
                        ->map('intval')
                        ->values()
                        ->all()
                    : [];

                throw new UnresolvedCutoffTieException($round->id, $k, $tiedIds);
            }
        } elseif ($qualifiedIdsOverride === []) {
            throw new ConflictHttpException('At least one active contestant must be selected to advance.');
        }

        $autoQualifierIds = $leaderboard->where('is_qualified', true)
            ->pluck('contestant_id')
            ->map('intval')
            ->values()
            ->all();

        DB::transaction(function () use (
            $actor,
            $round,
            &$leaderboard,
            $ipAddress,
            $qualifiedIdsOverride,
            $overrideReason,
            $activateNextRound,
            $pushToLedReveal,
            $autoQualifierIds
        ): void {
            $round->update([
                'status' => 'finalized',
                'is_locked' => true,
                'finalized_at' => now(),
            ]);

            if ($qualifiedIdsOverride !== null) {
                RoundResult::where('round_id', $round->id)->each(function (RoundResult $row) use ($qualifiedIdsOverride): void {
                    $shouldQualify = in_array((int) $row->contestant_id, $qualifiedIdsOverride, true);
                    if ((bool) $row->is_qualified !== $shouldQualify) {
                        $row->update(['is_qualified' => $shouldQualify]);
                    }
                });

                $leaderboard = RoundResult::where('round_id', $round->id)
                    ->orderBy('final_rank')
                    ->orderByDesc('cumulative_score')
                    ->get();
            }

            $qualifiers = $qualifiedIdsOverride !== null
                ? $leaderboard->whereIn('contestant_id', $qualifiedIdsOverride)->sortBy('final_rank')->values()
                : $leaderboard->where('is_qualified', true)->sortBy('final_rank')->values();

            $nextRound = Round::where('event_id', $round->event_id)
                ->where('sequence', '>', $round->sequence)
                ->orderBy('sequence')
                ->first();

            if ($nextRound !== null) {
                $syncData = [];
                foreach ($qualifiers as $idx => $result) {
                    $syncData[$result->contestant_id] = [
                        'performance_order' => $idx + 1,
                        'qualified_from_rank' => $result->final_rank,
                        'qualification_method' => $qualifiedIdsOverride !== null ? 'MANUAL_WILDCARD' : 'AUTO_TOP_N',
                        'override_reason' => $qualifiedIdsOverride !== null ? $overrideReason : null,
                    ];
                }
                $nextRound->contestants()->sync($syncData);

                if ($qualifiers->isNotEmpty() && (int) $nextRound->advancement_count > $qualifiers->count()) {
                    $nextRound->advancement_count = $qualifiers->count();
                    $nextRound->eliminate_count = max(0, $qualifiers->count() - (int) $nextRound->advancement_count);
                }

                if ($activateNextRound) {
                    $nextRound->status = 'active';
                    $nextRound->is_locked = false;
                }

                $nextRound->save();
            }

            $event = $round->event;
            $eventChanged = false;
            if ($activateNextRound && $nextRound !== null) {
                $event->active_round_id = $nextRound->id;
                $event->on_stage_contestant_id = $qualifiers->first()?->contestant_id ?? $event->on_stage_contestant_id;
                $eventChanged = true;
            }
            if ($pushToLedReveal) {
                $event->led_mode = 'reveal';
                $event->revealed_contestant_ids = $qualifiers->pluck('contestant_id')->map('intval')->values()->all();
                $eventChanged = true;
            }
            if ($eventChanged) {
                $event->save();
            }

            ScoreAuditLog::create([
                'event_id' => $round->event_id,
                'round_id' => $round->id,
                'actor_user_id' => $actor->id,
                'action' => 'ROUND_FINALIZED',
                'reason' => $overrideReason ?: 'Round finalized and qualifiers advanced',
                'metadata' => [
                    'qualified_contestant_ids' => $qualifiers->pluck('contestant_id')->map('intval')->values()->all(),
                    'activate_next_round' => $activateNextRound,
                    'push_to_led_reveal' => $pushToLedReveal,
                ],
                'ip_address' => $ipAddress,
            ]);

            if ($qualifiedIdsOverride !== null) {
                ScoreAuditLog::create([
                    'event_id' => $round->event_id,
                    'round_id' => $round->id,
                    'actor_user_id' => $actor->id,
                    'action' => 'QUALIFIER_OVERRIDE',
                    'reason' => $overrideReason ?: 'Manual qualifier override during round finalization',
                    'metadata' => [
                        'auto_qualifier_ids' => $autoQualifierIds,
                        'selected_qualifier_ids' => $qualifiers->pluck('contestant_id')->map('intval')->values()->all(),
                    ],
                    'ip_address' => $ipAddress,
                ]);
            }
        });

        $event = $round->event->fresh();
        $activeRound = $event->active_round_id ? Round::find($event->active_round_id) : $round->fresh();

        broadcast(new StageStateUpdated(
            eventId: $event->id,
            activeRoundId: $event->active_round_id,
            scoringStrategy: $activeRound?->scoring_strategy ?? $round->scoring_strategy,
            onStageContestantId: $event->on_stage_contestant_id,
            isRoundFrozen: $activeRound?->is_locked ?? $round->is_locked,
            ledMode: $event->led_mode,
            revealedContestantIds: $event->revealed_contestant_ids ?? [],
        ));

        if ($pushToLedReveal) {
            $onStage = $event->on_stage_contestant_id ? Contestant::find($event->on_stage_contestant_id) : null;
            $revealed = Contestant::whereIn('id', $event->revealed_contestant_ids ?? [])->get();

            broadcast(new LedWallUpdated(
                eventId: $event->id,
                ledMode: $event->led_mode,
                onStageContestant: $onStage ? (new ContestantResource($onStage))->resolve() : null,
                revealedContestantIds: $event->revealed_contestant_ids ?? [],
                finalistsPublicCards: ContestantResource::collection($revealed)->resolve(),
            ));
        }

        return $leaderboard;
    }

    public function reopenRound(User $actor, Round $round, string $ipAddress, ?string $reason = null): Round
    {
        if ($round->status !== 'finalized') {
            throw new ConflictHttpException('Only finalized rounds can be reopened.');
        }

        $laterRoundIds = Round::where('event_id', $round->event_id)
            ->where('sequence', '>', $round->sequence)
            ->pluck('id');

        if ($laterRoundIds->isNotEmpty()) {
            $hasLockedSubsequent = Score::whereIn('round_id', $laterRoundIds)
                ->whereIn('status', ['LOCKED', 'UNLOCK_REQUESTED'])
                ->exists();

            $hasFinalizedSubsequent = Round::whereIn('id', $laterRoundIds)
                ->where('status', 'finalized')
                ->exists();

            if ($hasLockedSubsequent || $hasFinalizedSubsequent) {
                throw new ConflictHttpException(
                    'Cannot reopen round: a subsequent round already has locked judge scores or has been finalized.'
                );
            }
        }

        DB::transaction(function () use ($actor, $round, $ipAddress, $reason, $laterRoundIds): void {
            $round->update([
                'status' => 'active',
                'is_locked' => false,
                'finalized_at' => null,
            ]);

            if ($laterRoundIds->isNotEmpty()) {
                $laterRounds = Round::whereIn('id', $laterRoundIds)->get();
                foreach ($laterRounds as $laterRound) {
                    $laterRound->contestants()->detach();
                    Score::where('round_id', $laterRound->id)->where('status', 'DRAFT')->delete();
                    RoundResult::where('round_id', $laterRound->id)->delete();
                    $laterRound->update([
                        'status' => 'upcoming',
                        'is_locked' => false,
                    ]);
                }
            }

            $event = $round->event;
            $event->active_round_id = $round->id;
            $event->save();

            ScoreAuditLog::create([
                'event_id' => $round->event_id,
                'round_id' => $round->id,
                'actor_user_id' => $actor->id,
                'action' => 'ROUND_REOPENED',
                'reason' => $reason ?: 'Finalized round reopened by Chief Tabulator',
                'ip_address' => $ipAddress,
            ]);
        });

        $event = $round->event->fresh();
        $reopened = $round->fresh();

        broadcast(new StageStateUpdated(
            eventId: $event->id,
            activeRoundId: $event->active_round_id,
            scoringStrategy: $reopened->scoring_strategy,
            onStageContestantId: $event->on_stage_contestant_id,
            isRoundFrozen: $reopened->is_locked,
            ledMode: $event->led_mode,
            revealedContestantIds: $event->revealed_contestant_ids ?? [],
        ));

        return $reopened;
    }

    /**
     * @return Collection<int, RoundResult>
     */
    public function excludeJudge(User $actor, Round $round, Judge $judge, string $reason, string $ipAddress): Collection
    {
        DB::transaction(function () use ($actor, $round, $judge, $reason, $ipAddress): void {
            $judge->update(['is_active' => false]);

            ScoreAuditLog::create([
                'event_id' => $round->event_id,
                'round_id' => $round->id,
                'judge_id' => $judge->id,
                'actor_user_id' => $actor->id,
                'action' => 'JUDGE_EXCLUDED',
                'reason' => $reason,
                'ip_address' => $ipAddress,
            ]);
        });

        return $this->scoringEngine->tabulateRound($round);
    }
}
