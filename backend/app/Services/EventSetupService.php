<?php

namespace App\Services;

use App\Models\Criterion;
use App\Models\Event;
use App\Models\Round;
use App\Models\Score;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class EventSetupService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createEvent(User $actor, array $data): Event
    {
        return DB::transaction(function () use ($actor, $data): Event {
            $event = Event::create([
                'title' => $data['title'],
                'subtitle' => $data['subtitle'] ?? null,
                'event_type' => $data['event_type'],
                'status' => 'setup',
                'led_mode' => 'on_stage',
                'revealed_contestant_ids' => [],
                'settings' => $data['settings'] ?? [
                    'auto_lock_on_cutoff' => false,
                    'default_deduction_presets' => [
                        ['label' => 'Time Overtime (-1.0)', 'points' => 1.0],
                        ['label' => 'Stage Violation (-2.0)', 'points' => 2.0],
                        ['label' => 'Late Call Time (-0.5)', 'points' => 0.5],
                    ],
                ],
                'created_by' => $actor->id,
            ]);

            if (! empty($data['clone_from_event_id'])) {
                $source = Event::with(['rounds.criteria'])->findOrFail((int) $data['clone_from_event_id']);
                $this->cloneStructure($source, $event);
            } elseif (! empty($data['preset'])) {
                $this->applyBlueprint($event, (string) $data['preset']);
            }

            return $event->fresh(['rounds.criteria']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateEvent(Event $event, array $data): Event
    {
        $targetStatus = $data['status'] ?? $event->status;

        if ($targetStatus === 'live' && $event->status !== 'live') {
            $preflight = $this->evaluatePreFlight($event);
            if (! $preflight['ready']) {
                $failingMessages = collect($preflight['checks'])
                    ->where('ok', false)
                    ->pluck('message')
                    ->values()
                    ->all();

                throw ValidationException::withMessages([
                    'status' => ['Pre-flight checks failed. Resolve all setup issues before going live.'],
                    'preflight' => $failingMessages,
                ]);
            }
        }

        return DB::transaction(function () use ($event, $data, $targetStatus): Event {
            $updates = array_intersect_key($data, array_flip([
                'title',
                'subtitle',
                'event_type',
                'status',
                'settings',
            ]));

            if ($targetStatus === 'live' && $event->active_round_id === null) {
                $firstRound = $event->rounds()->orderBy('sequence')->first();
                if ($firstRound !== null) {
                    $updates['active_round_id'] = $firstRound->id;
                    if ($firstRound->status === 'upcoming') {
                        $firstRound->update(['status' => 'active']);
                    }
                }
            }

            $event->update($updates);

            return $event->fresh();
        });
    }

    public function deleteEvent(Event $event): void
    {
        if ($event->status !== 'setup') {
            throw new ConflictHttpException(
                "Only events in 'setup' status can be deleted (current status: {$event->status})."
            );
        }

        $event->delete();
    }

    /**
     * @return array{ready: bool, checks: array<int, array{key: string, ok: bool, message: string}>}
     */
    public function evaluatePreFlight(Event $event): array
    {
        $rounds = $event->rounds()->with('criteria')->orderBy('sequence')->get();
        $activeJudgesCount = $event->judges()->where('is_active', true)->count();
        $activeContestantsCount = $event->contestants()->where('status', 'active')->count();

        $checks = [];

        $hasRounds = $rounds->isNotEmpty();
        $checks[] = [
            'key' => 'rounds_exist',
            'ok' => $hasRounds,
            'message' => $hasRounds
                ? "{$rounds->count()} round(s) configured."
                : 'At least one round is required.',
        ];

        $badWeightRounds = [];
        foreach ($rounds as $round) {
            $leafSum = $this->computeLeafCriteriaWeightSum($round->criteria);
            if ($round->criteria->isEmpty() || abs($leafSum - 100.0) > 0.01) {
                $badWeightRounds[] = "{$round->name} ({$leafSum}%)";
            }
        }
        $checks[] = [
            'key' => 'criteria_weights',
            'ok' => $hasRounds && $badWeightRounds === [],
            'message' => $badWeightRounds === []
                ? 'All rounds have leaf criteria summing to 100.00%.'
                : 'Leaf criteria must sum to 100.00% for: '.implode(', ', $badWeightRounds),
        ];

        $badSegments = $this->findInvalidCumulativeSegments($rounds);
        $checks[] = [
            'key' => 'segment_weights',
            'ok' => $badSegments === [],
            'message' => $badSegments === []
                ? 'Contiguous cumulative round segments sum to 100.00%.'
                : 'Cumulative round weights must sum to 100.00% for: '.implode(', ', $badSegments),
        ];

        $needsFiveJudges = $rounds->contains(fn (Round $r): bool => $r->scoring_strategy === 'trimmed');
        $minJudges = $needsFiveJudges ? 5 : 1;
        $checks[] = [
            'key' => 'judges_quorum',
            'ok' => $activeJudgesCount >= $minJudges,
            'message' => $activeJudgesCount >= $minJudges
                ? "{$activeJudgesCount} active judge(s) ready."
                : ($needsFiveJudges
                    ? 'Trimmed Mean strategy requires at least 5 active judges.'
                    : 'At least 1 active judge is required.'),
        ];

        $checks[] = [
            'key' => 'contestants_minimum',
            'ok' => $activeContestantsCount >= 2,
            'message' => $activeContestantsCount >= 2
                ? "{$activeContestantsCount} active contestant(s) registered."
                : 'At least 2 active contestants are required.',
        ];

        $ready = collect($checks)->every(fn (array $c): bool => $c['ok']);

        return [
            'ready' => $ready,
            'checks' => $checks,
        ];
    }

    /**
     * Sums leaf criteria weight percentages (ignoring parent groups that have child sub-criteria).
     *
     * @param  Collection<int, Criterion>  $criteria
     */
    public function computeLeafCriteriaWeightSum(Collection $criteria): float
    {
        if ($criteria->isEmpty()) {
            return 0.0;
        }

        $parentIds = $criteria->pluck('parent_id')->filter()->unique()->all();
        $leafSum = $criteria
            ->reject(fn (Criterion $c): bool => in_array($c->id, $parentIds, true))
            ->sum(fn (Criterion $c): float => (float) $c->weight_percentage);

        return round($leafSum, 2);
    }

    /**
     * @param  Collection<int, Round>  $rounds
     * @return array<int, string>
     */
    private function findInvalidCumulativeSegments(Collection $rounds): array
    {
        if ($rounds->isEmpty()) {
            return [];
        }

        $segments = [];
        $currentSegment = [];

        foreach ($rounds as $round) {
            if ($round->is_back_to_zero && $currentSegment !== []) {
                $segments[] = $currentSegment;
                $currentSegment = [];
            }
            $currentSegment[] = $round;
        }
        if ($currentSegment !== []) {
            $segments[] = $currentSegment;
        }

        $invalid = [];
        foreach ($segments as $segment) {
            if (count($segment) < 2) {
                continue;
            }
            $sum = round(array_sum(array_map(
                fn (Round $r): float => (float) $r->weight_in_overall,
                $segment
            )), 2);

            if (abs($sum - 100.0) > 0.01) {
                $names = implode(' + ', array_map(fn (Round $r): string => $r->name, $segment));
                $invalid[] = "{$names} ({$sum}%)";
            }
        }

        return $invalid;
    }

    private function cloneStructure(Event $source, Event $target): void
    {
        foreach ($source->rounds()->with('criteria')->orderBy('sequence')->get() as $srcRound) {
            $clonedRound = Round::create([
                'event_id' => $target->id,
                'name' => $srcRound->name,
                'sequence' => $srcRound->sequence,
                'scoring_strategy' => $srcRound->scoring_strategy,
                'weight_in_overall' => $srcRound->weight_in_overall,
                'is_back_to_zero' => $srcRound->is_back_to_zero,
                'cutoff_mode' => $srcRound->cutoff_mode ?? 'top_n_advance',
                'advancement_count' => $srcRound->advancement_count,
                'eliminate_count' => $srcRound->eliminate_count ?? 0,
                'status' => 'upcoming',
                'is_locked' => false,
                'settings' => $srcRound->settings ?? [],
            ]);

            $parentMap = [];
            // Clone top-level criteria first
            foreach ($srcRound->criteria->whereNull('parent_id')->sortBy('sequence') as $srcCrit) {
                $clonedCrit = Criterion::create([
                    'round_id' => $clonedRound->id,
                    'parent_id' => null,
                    'name' => $srcCrit->name,
                    'description' => $srcCrit->description,
                    'weight_percentage' => $srcCrit->weight_percentage,
                    'min_score' => $srcCrit->min_score,
                    'max_score' => $srcCrit->max_score,
                    'score_step' => $srcCrit->score_step,
                    'sequence' => $srcCrit->sequence,
                ]);
                $parentMap[$srcCrit->id] = $clonedCrit->id;
            }

            foreach ($srcRound->criteria->whereNotNull('parent_id')->sortBy('sequence') as $srcChild) {
                Criterion::create([
                    'round_id' => $clonedRound->id,
                    'parent_id' => $parentMap[$srcChild->parent_id] ?? null,
                    'name' => $srcChild->name,
                    'description' => $srcChild->description,
                    'weight_percentage' => $srcChild->weight_percentage,
                    'min_score' => $srcChild->min_score,
                    'max_score' => $srcChild->max_score,
                    'score_step' => $srcChild->score_step,
                    'sequence' => $srcChild->sequence,
                ]);
            }
        }
    }

    private function applyBlueprint(Event $event, string $preset): void
    {
        $definitions = match ($preset) {
            'pageant' => [
                [
                    'name' => 'Round 1: Preliminary Showcase',
                    'sequence' => 1,
                    'scoring_strategy' => 'weighted',
                    'weight_in_overall' => 50.00,
                    'is_back_to_zero' => true,
                    'advancement_count' => 10,
                    'criteria' => [
                        ['name' => 'Poise & Stage Presence', 'weight' => 40.00],
                        ['name' => 'Swimwear & Walk', 'weight' => 30.00],
                        ['name' => 'Interview & Poise', 'weight' => 30.00],
                    ],
                ],
                [
                    'name' => 'Round 2: Semi-Final Evening Gown',
                    'sequence' => 2,
                    'scoring_strategy' => 'weighted',
                    'weight_in_overall' => 50.00,
                    'is_back_to_zero' => false,
                    'advancement_count' => 5,
                    'criteria' => [
                        ['name' => 'Evening Gown Presentation', 'weight' => 50.00],
                        ['name' => 'Theme Wear & Elegance', 'weight' => 50.00],
                    ],
                ],
                [
                    'name' => 'Round 3: Grand Finals Q&A',
                    'sequence' => 3,
                    'scoring_strategy' => 'ordinal',
                    'weight_in_overall' => 100.00,
                    'is_back_to_zero' => true,
                    'advancement_count' => 3,
                    'criteria' => [
                        ['name' => 'Intelligence & Content', 'weight' => 60.00],
                        ['name' => 'Composure & Delivery', 'weight' => 40.00],
                    ],
                ],
            ],
            'singing' => [
                [
                    'name' => 'Elimination Round',
                    'sequence' => 1,
                    'scoring_strategy' => 'weighted',
                    'weight_in_overall' => 50.00,
                    'is_back_to_zero' => true,
                    'advancement_count' => 8,
                    'criteria' => [
                        ['name' => 'Vocal Quality & Technique', 'weight' => 50.00],
                        ['name' => 'Pitch & Timing', 'weight' => 30.00],
                        ['name' => 'Stage Presence', 'weight' => 20.00],
                    ],
                ],
                [
                    'name' => 'Grand Finals',
                    'sequence' => 2,
                    'scoring_strategy' => 'weighted',
                    'weight_in_overall' => 50.00,
                    'is_back_to_zero' => false,
                    'advancement_count' => 3,
                    'criteria' => [
                        ['name' => 'Vocal Mastery', 'weight' => 60.00],
                        ['name' => 'Interpretation & Audience Impact', 'weight' => 40.00],
                    ],
                ],
            ],
            'dance' => [
                [
                    'name' => 'Preliminaries',
                    'sequence' => 1,
                    'scoring_strategy' => 'weighted',
                    'weight_in_overall' => 40.00,
                    'is_back_to_zero' => true,
                    'advancement_count' => 6,
                    'criteria' => [
                        ['name' => 'Choreography & Creativity', 'weight' => 40.00],
                        ['name' => 'Execution & Synchronization', 'weight' => 40.00],
                        ['name' => 'Costume & Showmanship', 'weight' => 20.00],
                    ],
                ],
                [
                    'name' => 'Final Showdown',
                    'sequence' => 2,
                    'scoring_strategy' => 'weighted',
                    'weight_in_overall' => 60.00,
                    'is_back_to_zero' => false,
                    'advancement_count' => 3,
                    'criteria' => [
                        ['name' => 'Difficulty & Technique', 'weight' => 50.00],
                        ['name' => 'Performance Energy', 'weight' => 50.00],
                    ],
                ],
            ],
            default => [
                [
                    'name' => 'Round 1',
                    'sequence' => 1,
                    'scoring_strategy' => 'weighted',
                    'weight_in_overall' => 100.00,
                    'is_back_to_zero' => true,
                    'advancement_count' => 3,
                    'criteria' => [
                        ['name' => 'Overall Performance', 'weight' => 100.00],
                    ],
                ],
            ],
        };

        foreach ($definitions as $def) {
            $round = Round::create([
                'event_id' => $event->id,
                'name' => $def['name'],
                'sequence' => $def['sequence'],
                'scoring_strategy' => $def['scoring_strategy'],
                'weight_in_overall' => $def['weight_in_overall'],
                'is_back_to_zero' => $def['is_back_to_zero'],
                'cutoff_mode' => 'top_n_advance',
                'advancement_count' => $def['advancement_count'],
                'eliminate_count' => 0,
                'status' => 'upcoming',
            ]);

            foreach ($def['criteria'] as $cIdx => $crit) {
                Criterion::create([
                    'round_id' => $round->id,
                    'name' => $crit['name'],
                    'weight_percentage' => $crit['weight'],
                    'min_score' => 70.00,
                    'max_score' => 100.00,
                    'score_step' => 0.50,
                    'sequence' => $cIdx + 1,
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createRound(Event $event, array $data): Round
    {
        $maxSeq = (int) ($event->rounds()->max('sequence') ?? 0);
        $nextSeq = $maxSeq + 1;
        $entering = $this->estimateEnteringPool($event, $nextSeq);

        [$cutoffMode, $advancement, $eliminate] = $this->resolveDualSyncedCutoff(
            $data['cutoff_mode'] ?? 'top_n_advance',
            isset($data['advancement_count']) ? (int) $data['advancement_count'] : null,
            isset($data['eliminate_count']) ? (int) $data['eliminate_count'] : null,
            $entering
        );

        return Round::create([
            'event_id' => $event->id,
            'name' => $data['name'],
            'sequence' => $nextSeq,
            'scoring_strategy' => $data['scoring_strategy'] ?? 'weighted',
            'weight_in_overall' => $data['weight_in_overall'] ?? 100.00,
            'is_back_to_zero' => (bool) ($data['is_back_to_zero'] ?? ($nextSeq === 1)),
            'cutoff_mode' => $cutoffMode,
            'advancement_count' => $advancement,
            'eliminate_count' => $eliminate,
            'status' => 'upcoming',
            'is_locked' => false,
            'settings' => $data['settings'] ?? [],
        ])->fresh('criteria');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateRound(Round $round, array $data): Round
    {
        if ($round->status === 'finalized') {
            throw new ConflictHttpException('Finalized rounds cannot be edited unless reopened.');
        }

        $entering = $this->estimateEnteringPool($round->event, $round->sequence);
        $cutoffMode = $data['cutoff_mode'] ?? $round->cutoff_mode ?? 'top_n_advance';
        $advancementInput = array_key_exists('advancement_count', $data)
            ? (int) $data['advancement_count']
            : ($cutoffMode === 'eliminate_n' && array_key_exists('eliminate_count', $data)
                ? null
                : (int) $round->advancement_count);
        $eliminateInput = array_key_exists('eliminate_count', $data)
            ? (int) $data['eliminate_count']
            : (int) ($round->eliminate_count ?? 0);

        if (
            array_key_exists('advancement_count', $data)
            || array_key_exists('eliminate_count', $data)
            || array_key_exists('cutoff_mode', $data)
        ) {
            [$cutoffMode, $advancement, $eliminate] = $this->resolveDualSyncedCutoff(
                $cutoffMode,
                $advancementInput,
                $eliminateInput,
                $entering
            );
            $data['cutoff_mode'] = $cutoffMode;
            $data['advancement_count'] = $advancement;
            $data['eliminate_count'] = $eliminate;
        }

        $allowed = array_intersect_key($data, array_flip([
            'name',
            'scoring_strategy',
            'weight_in_overall',
            'is_back_to_zero',
            'cutoff_mode',
            'advancement_count',
            'eliminate_count',
            'settings',
        ]));

        $round->update($allowed);

        return $round->fresh('criteria');
    }

    public function deleteRound(Round $round): void
    {
        $this->assertRoundMutable($round);

        DB::transaction(function () use ($round): void {
            $event = $round->event;
            $round->delete();

            $remaining = $event->rounds()->orderBy('sequence')->get();
            foreach ($remaining as $idx => $r) {
                $r->update(['sequence' => 10000 + $idx]);
            }
            foreach ($remaining as $idx => $r) {
                $r->update(['sequence' => $idx + 1]);
            }
        });
    }

    /**
     * @return Collection<int, Round>
     */
    public function generateFunnel(
        Event $event,
        int $roundCount,
        int $finalWinnersCount,
        bool $finalBackToZero
    ): Collection {
        $existingRounds = $event->rounds()->get();
        foreach ($existingRounds as $r) {
            $this->assertRoundMutable($r);
        }

        $activeContestants = $event->contestants()->where('status', 'active')->count();
        $startingPool = max($finalWinnersCount, $activeContestants > 0 ? $activeContestants : max(12, $finalWinnersCount * 4));

        return DB::transaction(function () use (
            $event,
            $roundCount,
            $finalWinnersCount,
            $finalBackToZero,
            $startingPool
        ): Collection {
            $event->rounds()->delete();

            $totalCut = max(0, $startingPool - $finalWinnersCount);
            $step = $roundCount > 0 ? $totalCut / $roundCount : 0;
            $runningEntering = $startingPool;
            $created = collect();

            for ($i = 1; $i <= $roundCount; $i++) {
                $advancement = $i === $roundCount
                    ? $finalWinnersCount
                    : max($finalWinnersCount, (int) round($startingPool - ($step * $i)));
                $advancement = min($runningEntering, $advancement);
                $eliminate = max(0, $runningEntering - $advancement);

                $isLast = $i === $roundCount;
                $name = $roundCount === 1
                    ? 'Grand Finals'
                    : ($i === 1 ? 'Round 1: Preliminaries' : ($isLast ? "Round {$i}: Grand Finals" : "Round {$i}: Eliminations"));

                $round = Round::create([
                    'event_id' => $event->id,
                    'name' => $name,
                    'sequence' => $i,
                    'scoring_strategy' => $isLast ? 'ordinal' : 'weighted',
                    'weight_in_overall' => 100.00,
                    'is_back_to_zero' => $i === 1 ? true : ($isLast ? $finalBackToZero : false),
                    'cutoff_mode' => 'top_n_advance',
                    'advancement_count' => max(1, $advancement),
                    'eliminate_count' => $eliminate,
                    'status' => 'upcoming',
                ]);

                Criterion::create([
                    'round_id' => $round->id,
                    'name' => 'Overall Performance',
                    'weight_percentage' => 100.00,
                    'min_score' => 70.00,
                    'max_score' => 100.00,
                    'score_step' => 0.50,
                    'sequence' => 1,
                ]);

                $created->push($round->fresh('criteria'));
                $runningEntering = max(1, $advancement);
            }

            return $created;
        });
    }

    /**
     * Two-phase sequence update inside a single transaction to prevent UNIQUE(event_id, sequence) collisions.
     *
     * @param  array<int, int>  $roundIds
     * @return Collection<int, Round>
     */
    public function reorderRounds(Event $event, array $roundIds): Collection
    {
        $rounds = $event->rounds()->orderBy('sequence')->get();
        foreach ($rounds as $r) {
            $this->assertRoundMutable($r);
        }

        $existingIds = $rounds->pluck('id')->map('intval')->sort()->values()->all();
        $incomingIds = collect($roundIds)->map('intval')->sort()->values()->all();

        if ($existingIds !== $incomingIds) {
            throw ValidationException::withMessages([
                'round_ids' => ['round_ids must contain the exact set of round IDs belonging to this event.'],
            ]);
        }

        DB::transaction(function () use ($rounds, $roundIds): void {
            // Phase 1: shift to safe positive temporary offsets to avoid UNIQUE(event_id, sequence) collision
            foreach ($rounds as $idx => $r) {
                $r->update(['sequence' => 10000 + $idx]);
            }

            // Phase 2: assign final contiguous 1..R sequence
            foreach (array_values($roundIds) as $seqIdx => $roundId) {
                Round::where('id', (int) $roundId)->update(['sequence' => $seqIdx + 1]);
            }
        });

        return $event->rounds()->with('criteria')->orderBy('sequence')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{criterion: Criterion, leaf_weight_sum: float}
     */
    public function createCriterion(Round $round, array $data): array
    {
        $this->assertRoundCriteriaEditable($round);

        if (! empty($data['parent_id'])) {
            $parent = Criterion::where('id', (int) $data['parent_id'])
                ->where('round_id', $round->id)
                ->first();

            if ($parent === null) {
                throw ValidationException::withMessages([
                    'parent_id' => ['Parent criterion must belong to the same round.'],
                ]);
            }
            if ($parent->parent_id !== null) {
                throw ValidationException::withMessages([
                    'parent_id' => ['Criteria hierarchy supports a maximum depth of two levels.'],
                ]);
            }
        }

        $minScore = (float) ($data['min_score'] ?? 70.00);
        $maxScore = (float) ($data['max_score'] ?? 100.00);
        if ($minScore >= $maxScore) {
            throw ValidationException::withMessages([
                'min_score' => ['Minimum score must be strictly less than maximum score.'],
            ]);
        }

        $nextSeq = (int) ($round->criteria()->max('sequence') ?? 0) + 1;

        $criterion = Criterion::create([
            'round_id' => $round->id,
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'weight_percentage' => $data['weight_percentage'],
            'min_score' => $minScore,
            'max_score' => $maxScore,
            'score_step' => $data['score_step'] ?? 0.50,
            'sequence' => $data['sequence'] ?? $nextSeq,
        ]);

        return [
            'criterion' => $criterion->fresh(),
            'leaf_weight_sum' => $this->computeLeafCriteriaWeightSum($round->criteria()->get()),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{criterion: Criterion, leaf_weight_sum: float}
     */
    public function updateCriterion(Criterion $criterion, array $data): array
    {
        $round = $criterion->round;
        $this->assertRoundCriteriaEditable($round);

        $minScore = (float) ($data['min_score'] ?? $criterion->min_score);
        $maxScore = (float) ($data['max_score'] ?? $criterion->max_score);
        if ($minScore >= $maxScore) {
            throw ValidationException::withMessages([
                'min_score' => ['Minimum score must be strictly less than maximum score.'],
            ]);
        }

        if (array_key_exists('parent_id', $data) && (int) $data['parent_id'] === $criterion->id) {
            throw ValidationException::withMessages([
                'parent_id' => ['A criterion cannot be its own parent.'],
            ]);
        }

        $allowed = array_intersect_key($data, array_flip([
            'parent_id',
            'name',
            'description',
            'weight_percentage',
            'min_score',
            'max_score',
            'score_step',
            'sequence',
        ]));

        $criterion->update($allowed);

        return [
            'criterion' => $criterion->fresh(),
            'leaf_weight_sum' => $this->computeLeafCriteriaWeightSum($round->criteria()->get()),
        ];
    }

    public function deleteCriterion(Criterion $criterion): float
    {
        $round = $criterion->round;
        $this->assertRoundCriteriaEditable($round);

        $criterion->delete();

        return $this->computeLeafCriteriaWeightSum($round->criteria()->get());
    }

    private function assertRoundMutable(Round $round): void
    {
        if (in_array($round->status, ['active', 'locked', 'finalized'], true)) {
            throw new ConflictHttpException(
                "Round '{$round->name}' cannot be modified or deleted while {$round->status}."
            );
        }

        $hasLockedScores = Score::where('round_id', $round->id)
            ->where('status', 'LOCKED')
            ->exists();

        if ($hasLockedScores) {
            throw new ConflictHttpException(
                "Round '{$round->name}' has locked judge scores and cannot be modified or deleted."
            );
        }
    }

    private function assertRoundCriteriaEditable(Round $round): void
    {
        if ($round->status === 'finalized' || $round->is_locked) {
            throw new ConflictHttpException('Criteria cannot be modified once a round is frozen or finalized.');
        }

        $hasLockedScores = Score::where('round_id', $round->id)
            ->where('status', 'LOCKED')
            ->exists();

        if ($hasLockedScores) {
            throw new ConflictHttpException('Criteria cannot be modified after judge scores have been locked.');
        }
    }

    private function estimateEnteringPool(Event $event, int $sequence): int
    {
        $totalActive = $event->contestants()->where('status', 'active')->count();
        if ($sequence <= 1) {
            return $totalActive;
        }

        $prevRound = $event->rounds()
            ->where('sequence', '<', $sequence)
            ->orderByDesc('sequence')
            ->first();

        if ($prevRound !== null) {
            return $totalActive > 0
                ? min($totalActive, max(1, (int) $prevRound->advancement_count))
                : max(1, (int) $prevRound->advancement_count);
        }

        return $totalActive;
    }

    /**
     * @return array{0: string, 1: int, 2: int}
     */
    private function resolveDualSyncedCutoff(
        string $cutoffMode,
        ?int $advancementCount,
        ?int $eliminateCount,
        int $enteringPool
    ): array {
        if ($cutoffMode === 'eliminate_n' && $eliminateCount !== null && $enteringPool > 0 && $advancementCount === null) {
            $computedAdvancement = $enteringPool - $eliminateCount;
            if ($computedAdvancement < 1 || $computedAdvancement > $enteringPool) {
                throw ValidationException::withMessages([
                    'eliminate_count' => ["Eliminate count must leave between 1 and {$enteringPool} advancing finalists."],
                ]);
            }

            return [$cutoffMode, $computedAdvancement, $eliminateCount];
        }

        $adv = max(1, $advancementCount ?? 3);
        if ($enteringPool > 0 && $adv > $enteringPool) {
            throw ValidationException::withMessages([
                'advancement_count' => ["Advancement count ({$adv}) cannot exceed entering contestants ({$enteringPool})."],
            ]);
        }

        $elim = $enteringPool > 0 ? max(0, $enteringPool - $adv) : max(0, $eliminateCount ?? 0);

        return [$cutoffMode, $adv, $elim];
    }
}
