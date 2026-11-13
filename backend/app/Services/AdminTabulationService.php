<?php

namespace App\Services;

use App\Events\JudgeScoreUpdated;
use App\Events\LedWallUpdated;
use App\Events\StageStateUpdated;
use App\Events\UnlockRequestResolved;
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
     * @return Collection<int, RoundResult>
     */
    public function finalizeRound(User $actor, Round $round, string $ipAddress): Collection
    {
        $activeJudges = $round->event->judges()->where('is_active', true)->pluck('id');
        $activeContestants = $round->contestants()->where('contestants.status', 'active')->pluck('contestants.id');
        if ($activeContestants->isEmpty()) {
            $activeContestants = $round->event->contestants()->where('status', 'active')->pluck('id');
        }

        $unlockedCount = Score::where('round_id', $round->id)
            ->whereIn('judge_id', $activeJudges)
            ->whereIn('contestant_id', $activeContestants)
            ->where('status', '!=', 'LOCKED')
            ->count();

        $expectedCriteriaCount = $round->criteria()->count();
        $lockedCount = Score::where('round_id', $round->id)
            ->whereIn('judge_id', $activeJudges)
            ->whereIn('contestant_id', $activeContestants)
            ->where('status', 'LOCKED')
            ->count();

        $expectedTotalCells = $activeJudges->count() * $activeContestants->count() * $expectedCriteriaCount;

        if ($unlockedCount > 0 || $lockedCount < $expectedTotalCells) {
            throw new ConflictHttpException(
                'Cannot finalize round: all active judges must lock their score sheets first.'
            );
        }

        $leaderboard = $this->scoringEngine->tabulateRound($round);

        DB::transaction(function () use ($actor, $round, $leaderboard, $ipAddress): void {
            $round->update([
                'status' => 'finalized',
                'is_locked' => true,
                'finalized_at' => now(),
            ]);

            $nextRound = Round::where('event_id', $round->event_id)
                ->where('sequence', '>', $round->sequence)
                ->orderBy('sequence')
                ->first();

            if ($nextRound !== null) {
                $qualifiers = $leaderboard->where('is_qualified', true)->values();
                $syncData = [];
                foreach ($qualifiers as $idx => $result) {
                    $syncData[$result->contestant_id] = [
                        'performance_order' => $idx + 1,
                        'qualified_from_rank' => $result->final_rank,
                    ];
                }
                $nextRound->contestants()->sync($syncData);
            }

            ScoreAuditLog::create([
                'event_id' => $round->event_id,
                'round_id' => $round->id,
                'actor_user_id' => $actor->id,
                'action' => 'ROUND_FROZEN',
                'reason' => 'Round finalized and qualifiers advanced',
                'ip_address' => $ipAddress,
            ]);
        });

        return $leaderboard;
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
