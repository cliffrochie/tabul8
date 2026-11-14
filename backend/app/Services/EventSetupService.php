<?php

namespace App\Services;

use App\Models\Criterion;
use App\Models\Event;
use App\Models\Round;
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
}
