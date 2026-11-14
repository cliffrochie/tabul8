<?php

namespace App\Services;

use App\Events\JudgeScoreUpdated;
use App\Http\Resources\RoundResultResource;
use App\Models\Criterion;
use App\Models\Judge;
use App\Models\Round;
use App\Models\RoundResult;
use App\Models\Score;
use App\Models\ScoreAuditLog;
use App\Services\Scoring\ScoringEngineService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ScoreService
{
    public function __construct(
        private readonly ScoringEngineService $scoringEngine,
        private readonly RoundRosterService $rosterService,
    ) {}

    /**
     * Processes real-time score upserts and offline IndexedDB batch flushes.
     *
     * @param  array<int, array<string, mixed>>  $mutations
     * @return array{results: array<int, array{client_mutation_id: string, status: string}>, my_scores: array<int|string, mixed>}
     */
    public function upsertBatch(Judge $judge, Round $round, array $mutations, string $ipAddress): array
    {
        if ($round->is_locked) {
            throw new ConflictHttpException('Round is currently frozen by the Chief Tabulator.');
        }

        usort(
            $mutations,
            fn (array $a, array $b): int => strtotime($a['client_timestamp']) <=> strtotime($b['client_timestamp'])
        );

        $criteriaMap = $round->criteria()->get()->keyBy('id');
        $activeContestantIds = $this->rosterService->getActiveContestantIds($round);
        $touchedContestants = [];
        $results = [];

        DB::transaction(function () use ($judge, $round, $mutations, $criteriaMap, $activeContestantIds, $ipAddress, &$touchedContestants, &$results): void {
            foreach ($mutations as $mutation) {
                $mutationId = $mutation['client_mutation_id'];
                $contestantId = (int) $mutation['contestant_id'];
                $action = $mutation['action'];

                if (! in_array($contestantId, $activeContestantIds, true)) {
                    throw ValidationException::withMessages([
                        'contestant_id' => ['Contestant is eliminated or not qualified for this round.'],
                    ]);
                }

                if (Score::where('client_mutation_id', $mutationId)->exists()) {
                    $results[] = ['client_mutation_id' => $mutationId, 'status' => 'DUPLICATE_IGNORED'];

                    continue;
                }

                $isSheetLocked = Score::where('round_id', $round->id)
                    ->where('judge_id', $judge->id)
                    ->where('contestant_id', $contestantId)
                    ->whereIn('status', ['LOCKED', 'UNLOCK_REQUESTED'])
                    ->exists();

                if ($isSheetLocked) {
                    $results[] = ['client_mutation_id' => $mutationId, 'status' => 'REJECTED_LOCKED'];

                    continue;
                }

                if ($action === 'UPSERT_SCORE') {
                    /** @var Criterion|null $criterion */
                    $criterion = $criteriaMap->get((int) $mutation['criterion_id']);
                    if ($criterion === null) {
                        throw ValidationException::withMessages([
                            'criterion_id' => ['Criterion does not belong to the active round.'],
                        ]);
                    }

                    $raw = round((float) $mutation['raw_score'], 3, PHP_ROUND_HALF_UP);
                    if ($raw < (float) $criterion->min_score || $raw > (float) $criterion->max_score) {
                        throw ValidationException::withMessages([
                            'raw_score' => ["Raw score must be between {$criterion->min_score} and {$criterion->max_score}."],
                        ]);
                    }

                    $weighted = round($raw * ((float) $criterion->weight_percentage / 100.0), 3, PHP_ROUND_HALF_UP);

                    Score::updateOrCreate(
                        [
                            'round_id' => $round->id,
                            'criterion_id' => $criterion->id,
                            'contestant_id' => $contestantId,
                            'judge_id' => $judge->id,
                        ],
                        [
                            'raw_score' => $raw,
                            'weighted_score' => $weighted,
                            'status' => 'DRAFT',
                            'client_mutation_id' => $mutationId,
                        ]
                    );

                    $touchedContestants[$contestantId] = 'DRAFT';
                    $results[] = ['client_mutation_id' => $mutationId, 'status' => 'APPLIED'];
                } elseif ($action === 'LOCK_SHEET') {
                    $this->lockContestantSheet($judge, $round, $contestantId, $ipAddress, $mutationId);
                    $touchedContestants[$contestantId] = 'LOCKED';
                    $results[] = ['client_mutation_id' => $mutationId, 'status' => 'APPLIED'];
                }
            }
        });

        $leaderboard = $this->scoringEngine->tabulateRound($round);
        $this->broadcastTouchedContestants($judge, $round, $touchedContestants, $leaderboard);

        return [
            'results' => $results,
            'my_scores' => $this->buildJudgeScoresMap($judge, $round),
        ];
    }

    /**
     * @param  array<int, int>  $contestantIds
     * @return array<int|string, mixed>
     */
    public function lockSheets(Judge $judge, Round $round, array $contestantIds, bool $lockAll, string $ipAddress): array
    {
        if ($round->is_locked) {
            throw new ConflictHttpException('Round is currently frozen by the Chief Tabulator.');
        }

        $activeContestantIds = $this->rosterService->getActiveContestantIds($round);

        if ($lockAll) {
            $contestantIds = $activeContestantIds;
        } else {
            $contestantIds = array_values(array_intersect(
                array_map('intval', $contestantIds),
                $activeContestantIds
            ));
        }

        $touched = [];
        DB::transaction(function () use ($judge, $round, $contestantIds, $ipAddress, &$touched): void {
            foreach ($contestantIds as $contestantId) {
                $this->lockContestantSheet($judge, $round, (int) $contestantId, $ipAddress);
                $touched[(int) $contestantId] = 'LOCKED';
            }
        });

        $leaderboard = $this->scoringEngine->tabulateRound($round);
        $this->broadcastTouchedContestants($judge, $round, $touched, $leaderboard);

        return $this->buildJudgeScoresMap($judge, $round);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function requestUnlock(Judge $judge, Round $round, int $contestantId, ?string $reason, string $ipAddress): array
    {
        if ($round->is_locked) {
            throw new ConflictHttpException('Round is currently frozen by the Chief Tabulator.');
        }

        DB::transaction(function () use ($judge, $round, $contestantId, $reason, $ipAddress): void {
            $updated = Score::where('round_id', $round->id)
                ->where('judge_id', $judge->id)
                ->where('contestant_id', $contestantId)
                ->where('status', 'LOCKED')
                ->update(['status' => 'UNLOCK_REQUESTED']);

            if ($updated === 0) {
                throw new ConflictHttpException('Score sheet is not currently locked.');
            }

            ScoreAuditLog::create([
                'event_id' => $round->event_id,
                'round_id' => $round->id,
                'judge_id' => $judge->id,
                'contestant_id' => $contestantId,
                'action' => 'UNLOCK_REQ',
                'reason' => $reason ?? 'Judge requested unlock',
                'ip_address' => $ipAddress,
            ]);
        });

        $leaderboard = $this->scoringEngine->tabulateRound($round);
        $this->broadcastTouchedContestants($judge, $round, [$contestantId => 'UNLOCK_REQUESTED'], $leaderboard);

        return $this->buildJudgeScoresMap($judge, $round);
    }

    /**
     * Builds the `my_scores` dictionary keyed by contestant_id for Judge hydration.
     *
     * @return array<int|string, array<string, mixed>>
     */
    public function buildJudgeScoresMap(Judge $judge, Round $round): array
    {
        $contestants = $this->rosterService->getQualifiedContestants($round);
        $criteria = $round->criteria()->orderBy('sequence')->get();
        $scores = Score::where('round_id', $round->id)
            ->where('judge_id', $judge->id)
            ->whereIn('contestant_id', $contestants->pluck('id'))
            ->get()
            ->groupBy('contestant_id');

        $totals = [];
        $map = [];

        foreach ($contestants as $contestant) {
            /** @var Collection<int, Score> $cScores */
            $cScores = $scores->get($contestant->id, collect())->keyBy('criterion_id');
            $critValues = [];
            $weightedTotal = 0.0;
            $status = 'DRAFT';
            $updatedAt = null;

            foreach ($criteria as $crit) {
                $scoreRow = $cScores->get($crit->id);
                $raw = $scoreRow ? (float) $scoreRow->raw_score : (float) $crit->min_score;
                $critValues[(string) $crit->id] = $raw;
                $weightedTotal += round($raw * ((float) $crit->weight_percentage / 100.0), 3, PHP_ROUND_HALF_UP);

                if ($scoreRow !== null) {
                    $status = $scoreRow->status;
                    $updatedAt = $scoreRow->updated_at?->toIso8601String();
                }
            }

            $roundedTotal = round($weightedTotal, 2, PHP_ROUND_HALF_UP);
            $totals[$contestant->id] = $roundedTotal;

            $map[(string) $contestant->id] = [
                'status' => $status,
                'updated_at' => $updatedAt,
                'weighted_total' => $roundedTotal,
                'personal_rank' => 1.0,
                'is_tied' => false,
                'criteria' => $critValues,
            ];
        }

        // Compute personal ordinal ranks for this judge
        arsort($totals, SORT_NUMERIC);
        $orderedIds = array_keys($totals);
        $count = count($orderedIds);
        $i = 0;

        while ($i < $count) {
            $j = $i;
            while ($j + 1 < $count && abs($totals[$orderedIds[$j + 1]] - $totals[$orderedIds[$i]]) < 0.005) {
                $j++;
            }
            $avgRank = round((($i + 1) + ($j + 1)) / 2.0, 1, PHP_ROUND_HALF_UP);
            $isTied = ($j > $i);

            for ($k = $i; $k <= $j; $k++) {
                $cid = (string) $orderedIds[$k];
                $map[$cid]['personal_rank'] = $avgRank;
                $map[$cid]['is_tied'] = $isTied;
            }
            $i = $j + 1;
        }

        return $map;
    }

    private function lockContestantSheet(
        Judge $judge,
        Round $round,
        int $contestantId,
        string $ipAddress,
        ?string $mutationId = null
    ): void {
        $criteria = $round->criteria()->get();
        $existing = Score::where('round_id', $round->id)
            ->where('judge_id', $judge->id)
            ->where('contestant_id', $contestantId)
            ->get()
            ->keyBy('criterion_id');

        $now = now();
        foreach ($criteria as $criterion) {
            $row = $existing->get($criterion->id);
            $raw = $row ? (float) $row->raw_score : (float) $criterion->min_score;
            $weighted = round($raw * ((float) $criterion->weight_percentage / 100.0), 3, PHP_ROUND_HALF_UP);

            Score::updateOrCreate(
                [
                    'round_id' => $round->id,
                    'criterion_id' => $criterion->id,
                    'contestant_id' => $contestantId,
                    'judge_id' => $judge->id,
                ],
                [
                    'raw_score' => $raw,
                    'weighted_score' => $weighted,
                    'status' => 'LOCKED',
                    'submitted_at' => $now,
                    'client_mutation_id' => $row?->client_mutation_id ?? $mutationId,
                ]
            );
        }

        ScoreAuditLog::create([
            'event_id' => $round->event_id,
            'round_id' => $round->id,
            'judge_id' => $judge->id,
            'contestant_id' => $contestantId,
            'action' => 'LOCKED',
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * @param  array<int, string>  $touchedContestants
     * @param  Collection<int, RoundResult>  $leaderboard
     */
    private function broadcastTouchedContestants(
        Judge $judge,
        Round $round,
        array $touchedContestants,
        Collection $leaderboard
    ): void {
        $leaderboardPayload = RoundResultResource::collection($leaderboard)->resolve();

        foreach ($touchedContestants as $contestantId => $status) {
            $weightedTotal = (float) Score::where('round_id', $round->id)
                ->where('judge_id', $judge->id)
                ->where('contestant_id', $contestantId)
                ->sum('weighted_score');

            broadcast(new JudgeScoreUpdated(
                eventId: $round->event_id,
                roundId: $round->id,
                judgeId: $judge->id,
                contestantId: $contestantId,
                weightedTotal: round($weightedTotal, 2, PHP_ROUND_HALF_UP),
                status: $status,
                updatedAt: now()->toIso8601String(),
                leaderboard: $leaderboardPayload,
            ));
        }
    }
}
