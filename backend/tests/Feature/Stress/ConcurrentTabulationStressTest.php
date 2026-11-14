<?php

use App\Models\Contestant;
use App\Models\Criterion;
use App\Models\Event;
use App\Models\Judge;
use App\Models\Round;
use App\Models\RoundResult;
use App\Models\Score;
use App\Models\User;
use App\Services\ScoreService;
use App\Services\Scoring\ScoringEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('handles 10 interleaved judges x 30 contestants x 4 criteria with idempotent outbox replay and deterministic ranking', function (): void {
    $admin = User::factory()->create(['role' => 'tabulator']);

    $event = Event::create([
        'title' => 'Phase 6 Stress Invitational',
        'event_type' => 'pageant',
        'status' => 'live',
        'led_mode' => 'on_stage',
        'revealed_contestant_ids' => [],
        'settings' => [],
        'created_by' => $admin->id,
    ]);

    $round = Round::create([
        'event_id' => $event->id,
        'name' => 'Grand Finals Stress Round',
        'sequence' => 1,
        'scoring_strategy' => 'weighted',
        'weight_in_overall' => 100,
        'is_back_to_zero' => true,
        'cutoff_mode' => 'top_n_advance',
        'advancement_count' => 5,
        'eliminate_count' => 25,
        'status' => 'active',
        'is_locked' => false,
    ]);
    $event->update(['active_round_id' => $round->id]);

    $criteria = collect(range(1, 4))->map(fn (int $i) => Criterion::create([
        'round_id' => $round->id,
        'name' => "Criterion {$i}",
        'weight_percentage' => 25,
        'min_score' => 0,
        'max_score' => 100,
        'score_step' => 0.5,
        'sequence' => $i,
    ]));

    $contestants = collect(range(1, 30))->map(fn (int $i) => Contestant::create([
        'event_id' => $event->id,
        'candidate_number' => $i,
        'name' => "Candidate #{$i}",
        'origin_or_group' => "Delegation {$i}",
        'category' => 'Open',
        'status' => 'active',
    ]));

    $judges = collect(range(1, 10))->map(fn (int $j) => Judge::create([
        'event_id' => $event->id,
        'judge_number' => $j,
        'name' => "Judge #{$j}",
        'role_title' => $j === 1 ? 'Chair of Judges' : 'Panelist',
        'access_pin_hash' => Hash::make(str_pad((string) $j, 6, '0', STR_PAD_LEFT)),
        'plain_pin_reference' => str_pad((string) $j, 6, '0', STR_PAD_LEFT),
        'is_chairman' => $j === 1,
        'is_active' => true,
    ]));

    /** @var ScoreService $scoreService */
    $scoreService = app(ScoreService::class);
    /** @var ScoringEngineService $scoringEngine */
    $scoringEngine = app(ScoringEngineService::class);

    $mutationHistoryByJudge = [];

    // Interleave writes across 10 judges in 5-contestant stage waves (60 batches = 1,200 cells)
    foreach ($contestants->chunk(5) as $waveIdx => $waveContestants) {
        foreach ($judges as $jIdx => $judge) {
            $batch = [];
            foreach ($waveContestants as $cIdx => $contestant) {
                foreach ($criteria as $crIdx => $criterion) {
                    $score = 68.0 + ($cIdx * 0.9) + (($jIdx % 3) * 0.25) + ($crIdx * 0.1);
                    $mutationId = "stress-j{$judge->id}-c{$contestant->id}-k{$criterion->id}";
                    $batch[] = [
                        'client_mutation_id' => $mutationId,
                        'contestant_id' => $contestant->id,
                        'action' => 'UPSERT_SCORE',
                        'criterion_id' => $criterion->id,
                        'raw_score' => round($score, 2),
                        'client_timestamp' => now()->toIso8601String(),
                    ];
                }
            }

            $response = $scoreService->upsertBatch($judge, $round, $batch, '192.168.50.10');
            expect($response['results'])->toHaveCount(20);
            $mutationHistoryByJudge[$judge->id][] = $batch;
        }

        // Trigger intermediate live recalculation per wave to verify read/write safety
        if ($waveIdx % 2 === 1) {
            $scoringEngine->tabulateRound($round);
        }
    }

    // Replay every offline outbox packet and assert DUPLICATE_IGNORED idempotency
    foreach ($judges as $judge) {
        foreach ($mutationHistoryByJudge[$judge->id] as $batch) {
            $replay = $scoreService->upsertBatch($judge, $round, $batch, '192.168.50.10');
            foreach ($replay['results'] as $ack) {
                expect($ack['status'])->toBe('DUPLICATE_IGNORED');
            }
        }
    }

    // Lock all 10 judge score sheets and run final tabulation
    foreach ($judges as $judge) {
        $scoreService->lockSheets($judge, $round, [], true, '192.168.50.10');
    }

    $leaderboard = $scoringEngine->tabulateRound($round);

    expect(Score::where('round_id', $round->id)->count())->toBe(1200)
        ->and(Score::where('round_id', $round->id)->where('status', 'LOCKED')->count())->toBe(1200)
        ->and($leaderboard)->toHaveCount(30)
        ->and(RoundResult::where('round_id', $round->id)->count())->toBe(30);

    $ranks = RoundResult::where('round_id', $round->id)
        ->orderBy('final_rank')
        ->pluck('final_rank')
        ->all();

    expect($ranks[0])->toBe(1)
        ->and(RoundResult::where('round_id', $round->id)->where('is_qualified', true)->count())->toBe(5);
});
