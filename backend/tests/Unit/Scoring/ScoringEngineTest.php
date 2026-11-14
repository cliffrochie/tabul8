<?php

use App\Exceptions\ImmutableAuditLogException;
use App\Exceptions\InsufficientJudgesForTrimmedMeanException;
use App\Models\Contestant;
use App\Models\Criterion;
use App\Models\Deduction;
use App\Models\Event;
use App\Models\Judge;
use App\Models\Round;
use App\Models\Score;
use App\Models\ScoreAuditLog;
use App\Models\User;
use App\Services\Scoring\ScoringEngineService;
use Illuminate\Support\Facades\Hash;

/**
 * Helper to scaffold an Event with N judges, M contestants, and criteria.
 *
 * @param  array<int, array{name: string, weight: float, min?: float, max?: float}>  $criteriaSpecs
 * @return array{event: Event, round: Round, judges: array<int, Judge>, contestants: array<int, Contestant>, criteria: array<int, Criterion>}
 */
function createScoringFixture(
    string $strategy = 'weighted',
    int $judgeCount = 5,
    int $contestantCount = 3,
    array $criteriaSpecs = [
        ['name' => 'Vocal Technique', 'weight' => 50.0],
        ['name' => 'Musicality', 'weight' => 30.0],
        ['name' => 'Stage Presence', 'weight' => 20.0],
    ],
    bool $isBackToZero = false,
    int $advancementCount = 2,
): array {
    $event = Event::create([
        'title' => 'Test Competition',
        'event_type' => 'singing',
        'status' => 'live',
    ]);

    $round = Round::create([
        'event_id' => $event->id,
        'name' => 'Round 1',
        'sequence' => 1,
        'scoring_strategy' => $strategy,
        'weight_in_overall' => 100.00,
        'is_back_to_zero' => $isBackToZero,
        'advancement_count' => $advancementCount,
        'status' => 'active',
    ]);

    $criteria = [];
    foreach ($criteriaSpecs as $idx => $spec) {
        $criteria[] = Criterion::create([
            'round_id' => $round->id,
            'name' => $spec['name'],
            'weight_percentage' => $spec['weight'],
            'min_score' => $spec['min'] ?? 70.00,
            'max_score' => $spec['max'] ?? 100.00,
            'score_step' => 0.50,
            'sequence' => $idx + 1,
        ]);
    }

    $judges = [];
    for ($j = 1; $j <= $judgeCount; $j++) {
        $pin = str_pad((string) (100000 + $j), 6, '0', STR_PAD_LEFT);
        $judges[] = Judge::create([
            'event_id' => $event->id,
            'judge_number' => $j,
            'name' => "Judge #{$j}",
            'access_pin_hash' => Hash::make($pin),
            'plain_pin_reference' => $pin,
            'is_chairman' => $j === 1,
            'is_active' => true,
        ]);
    }

    $contestants = [];
    for ($c = 1; $c <= $contestantCount; $c++) {
        $contestants[] = Contestant::create([
            'event_id' => $event->id,
            'candidate_number' => $c,
            'name' => "Contestant #{$c}",
            'origin_or_group' => "City {$c}",
            'status' => 'active',
        ]);
    }

    return compact('event', 'round', 'judges', 'contestants', 'criteria');
}

function recordScoresForContestant(
    Round $round,
    Contestant $contestant,
    array $judges,
    array $criteria,
    array $scoresByJudgeIndex
): void {
    foreach ($judges as $jIdx => $judge) {
        $rawList = $scoresByJudgeIndex[$jIdx];
        foreach ($criteria as $cIdx => $criterion) {
            $raw = $rawList[$cIdx];
            Score::create([
                'round_id' => $round->id,
                'criterion_id' => $criterion->id,
                'contestant_id' => $contestant->id,
                'judge_id' => $judge->id,
                'raw_score' => $raw,
                'weighted_score' => round($raw * ((float) $criterion->weight_percentage / 100.0), 3, PHP_ROUND_HALF_UP),
                'status' => 'LOCKED',
            ]);
        }
    }
}

test('T-ALG-01: weighted average computes exact decimal composite', function () {
    $fx = createScoringFixture('weighted', 5, 2);

    // C1: all 5 judges give 90, 80, 100 -> 90*0.5 + 80*0.3 + 100*0.2 = 45 + 24 + 20 = 89.000
    recordScoresForContestant($fx['round'], $fx['contestants'][0], $fx['judges'], $fx['criteria'], [
        [90, 80, 100],
        [90, 80, 100],
        [90, 80, 100],
        [90, 80, 100],
        [90, 80, 100],
    ]);

    // C2: all 5 judges give 95, 90, 90 -> 47.5 + 27 + 18 = 92.500
    recordScoresForContestant($fx['round'], $fx['contestants'][1], $fx['judges'], $fx['criteria'], [
        [95, 90, 90],
        [95, 90, 90],
        [95, 90, 90],
        [95, 90, 90],
        [95, 90, 90],
    ]);

    $results = app(ScoringEngineService::class)->tabulateRound($fx['round']);

    expect((float) $results[0]->net_composite)->toBe(92.5)
        ->and($results[0]->contestant_id)->toBe($fx['contestants'][1]->id)
        ->and($results[0]->final_rank)->toBe(1)
        ->and((float) $results[1]->net_composite)->toBe(89.0)
        ->and($results[1]->final_rank)->toBe(2);
});

test('T-ALG-02: ordinal ranking assigns fractional ranks on two-way and three-way ties', function () {
    $fx = createScoringFixture('ordinal', 1, 6, [['name' => 'Overall', 'weight' => 100.0]]);

    // Judge 1 gives: C1=94.5, C2=94.5 (tied 1st/2nd -> 1.5),
    // C3=90, C4=90, C5=90 (tied 3rd/4th/5th -> 4.0), C6=85 (6th -> 6.0)
    $scores = [94.5, 94.5, 90.0, 90.0, 90.0, 85.0];
    foreach ($fx['contestants'] as $idx => $contestant) {
        recordScoresForContestant($fx['round'], $contestant, $fx['judges'], $fx['criteria'], [[$scores[$idx]]]);
    }

    $results = app(ScoringEngineService::class)->tabulateRound($fx['round'])->keyBy('contestant_id');

    $sumRanks = $results->sum(fn ($r) => (float) $r->rank_sum);

    expect((float) $results[$fx['contestants'][0]->id]->rank_sum)->toBe(1.5)
        ->and((float) $results[$fx['contestants'][1]->id]->rank_sum)->toBe(1.5)
        ->and((float) $results[$fx['contestants'][2]->id]->rank_sum)->toBe(4.0)
        ->and((float) $results[$fx['contestants'][3]->id]->rank_sum)->toBe(4.0)
        ->and((float) $results[$fx['contestants'][4]->id]->rank_sum)->toBe(4.0)
        ->and((float) $results[$fx['contestants'][5]->id]->rank_sum)->toBe(6.0)
        ->and($sumRanks)->toBe(21.0);
});

test('T-ALG-03: trimmed mean drops single min and single max only', function () {
    $fx = createScoringFixture('trimmed', 5, 1, [['name' => 'Execution', 'weight' => 100.0]]);

    // 5 judges score 85.0, 85.0, 88.0, 91.0, 95.0 -> drops one 85 and one 95 -> avg(85, 88, 91) = 88.000
    recordScoresForContestant($fx['round'], $fx['contestants'][0], $fx['judges'], $fx['criteria'], [
        [85.0],
        [85.0],
        [88.0],
        [91.0],
        [95.0],
    ]);

    $results = app(ScoringEngineService::class)->tabulateRound($fx['round']);

    $droppedCount = collect($results[0]->judge_breakdown_snapshot)
        ->where('is_dropped', true)
        ->count();

    expect((float) $results[0]->trimmed_mean)->toBe(88.0)
        ->and((float) $results[0]->net_composite)->toBe(88.0)
        ->and($droppedCount)->toBe(2);
});

test('T-ALG-04: trimmed mean throws domain exception when fewer than five judges', function () {
    $fx = createScoringFixture('trimmed', 3, 1, [['name' => 'Execution', 'weight' => 100.0]]);

    expect(fn () => app(ScoringEngineService::class)->tabulateRound($fx['round']))
        ->toThrow(InsufficientJudgesForTrimmedMeanException::class);
});

test('T-ALG-05: technical deduction subtracts from net composite and floors at zero', function () {
    $fx = createScoringFixture('weighted', 5, 2, [['name' => 'Overall', 'weight' => 100.0]]);
    $admin = User::factory()->create(['role' => 'tabulator']);

    recordScoresForContestant($fx['round'], $fx['contestants'][0], $fx['judges'], $fx['criteria'], [
        [89.5], [89.5], [89.5], [89.5], [89.5],
    ]);
    recordScoresForContestant($fx['round'], $fx['contestants'][1], $fx['judges'], $fx['criteria'], [
        [1.0], [1.0], [1.0], [1.0], [1.0],
    ]);

    Deduction::create([
        'round_id' => $fx['round']->id,
        'contestant_id' => $fx['contestants'][0]->id,
        'applied_by' => $admin->id,
        'points_deducted' => 1.50,
        'reason' => 'Overtime penalty',
    ]);
    Deduction::create([
        'round_id' => $fx['round']->id,
        'contestant_id' => $fx['contestants'][1]->id,
        'applied_by' => $admin->id,
        'points_deducted' => 5.00,
        'reason' => 'Floor at zero check',
    ]);

    $results = app(ScoringEngineService::class)->tabulateRound($fx['round'])->keyBy('contestant_id');

    expect((float) $results[$fx['contestants'][0]->id]->net_composite)->toBe(88.0)
        ->and((float) $results[$fx['contestants'][1]->id]->net_composite)->toBe(0.0);
});

test('T-ALG-06: weighted tie breaker resolves via primary criterion average', function () {
    $fx = createScoringFixture('weighted', 2, 2, [
        ['name' => 'Primary Crit', 'weight' => 50.0],
        ['name' => 'Secondary Crit', 'weight' => 50.0],
    ]);

    // Both net 92.500: C1 = 95*0.5 + 90*0.5, C2 = 91*0.5 + 94*0.5
    recordScoresForContestant($fx['round'], $fx['contestants'][0], $fx['judges'], $fx['criteria'], [
        [95, 90], [95, 90],
    ]);
    recordScoresForContestant($fx['round'], $fx['contestants'][1], $fx['judges'], $fx['criteria'], [
        [91, 94], [91, 94],
    ]);

    $results = app(ScoringEngineService::class)->tabulateRound($fx['round']);

    expect($results[0]->contestant_id)->toBe($fx['contestants'][0]->id)
        ->and($results[0]->final_rank)->toBe(1)
        ->and($results[0]->tie_break_rule_applied)->toBe('PRIMARY_CRITERION')
        ->and($results[1]->final_rank)->toBe(2);
});

test('T-ALG-07: ordinal tie breaker resolves by majority first place ranks then net composite', function () {
    $fx = createScoringFixture('ordinal', 3, 2, [['name' => 'Overall', 'weight' => 100.0]]);

    // J1: C1=95 (1), C2=90 (2)
    // J2: C1=95 (1), C2=90 (2)
    // J3: C1=80 (2), C2=95 (1)
    // Wait: both need same rank_sum! With 3 judges and 2 contestants:
    // Make 4 judges so rank_sum can tie at 6.0:
    // J1, J2, J3, J4: C1 gets ranks [1, 1, 2, 2] -> sum 6.0 (two #1s);
    // Actually with 2 contestants, if C1 gets [1,1,2,2] then C2 gets [2,2,1,1] (also two #1s).
    // So use 3 contestants with 3 judges: C1 ranks [1, 1, 3] = 5.0 (two #1s), C2 ranks [2, 2, 1] = 5.0 (one #1).
    $fx3 = createScoringFixture('ordinal', 3, 3, [['name' => 'Overall', 'weight' => 100.0]]);
    // J1: C1=96(1), C2=92(2), C3=88(3)
    // J2: C1=96(1), C2=92(2), C3=88(3)
    // J3: C1=85(3), C2=95(1), C3=90(2)
    recordScoresForContestant($fx3['round'], $fx3['contestants'][0], $fx3['judges'], $fx3['criteria'], [
        [96], [96], [85],
    ]);
    recordScoresForContestant($fx3['round'], $fx3['contestants'][1], $fx3['judges'], $fx3['criteria'], [
        [92], [92], [95],
    ]);
    recordScoresForContestant($fx3['round'], $fx3['contestants'][2], $fx3['judges'], $fx3['criteria'], [
        [88], [88], [90],
    ]);

    $results = app(ScoringEngineService::class)->tabulateRound($fx3['round']);

    expect((float) $results[0]->rank_sum)->toBe(5.0)
        ->and((float) $results[1]->rank_sum)->toBe(5.0)
        ->and($results[0]->contestant_id)->toBe($fx3['contestants'][0]->id)
        ->and($results[0]->first_place_count)->toBe(2)
        ->and($results[0]->tie_break_rule_applied)->toBe('FIRST_PLACE_COUNT');
});

test('T-ALG-08: cumulative round combines prior round weights accurately', function () {
    $fx = createScoringFixture('weighted', 2, 1, [['name' => 'Overall', 'weight' => 100.0]], false);
    $fx['round']->update(['weight_in_overall' => 30.00]);

    $round2 = Round::create([
        'event_id' => $fx['event']->id,
        'name' => 'Round 2',
        'sequence' => 2,
        'scoring_strategy' => 'weighted',
        'weight_in_overall' => 70.00,
        'is_back_to_zero' => false,
        'advancement_count' => 1,
        'status' => 'active',
    ]);
    $crit2 = Criterion::create([
        'round_id' => $round2->id,
        'name' => 'Overall',
        'weight_percentage' => 100.00,
        'min_score' => 70.00,
        'max_score' => 100.00,
        'score_step' => 0.50,
        'sequence' => 1,
    ]);

    $round2->contestants()->sync([$fx['contestants'][0]->id => ['performance_order' => 1]]);

    recordScoresForContestant($fx['round'], $fx['contestants'][0], $fx['judges'], $fx['criteria'], [[90], [90]]);
    recordScoresForContestant($round2, $fx['contestants'][0], $fx['judges'], [$crit2], [[95], [95]]);

    $engine = app(ScoringEngineService::class);
    $engine->tabulateRound($fx['round']);
    $r2Results = $engine->tabulateRound($round2);

    // 90 * 0.30 + 95 * 0.70 = 27.00 + 66.50 = 93.500
    expect((float) $r2Results[0]->cumulative_score)->toBe(93.5);
});

test('T-ALG-09: back to zero round ignores prior round scores', function () {
    $fx = createScoringFixture('weighted', 2, 1, [['name' => 'Overall', 'weight' => 100.0]], false);
    $fx['round']->update(['weight_in_overall' => 50.00]);

    $round2 = Round::create([
        'event_id' => $fx['event']->id,
        'name' => 'Final Q&A',
        'sequence' => 2,
        'scoring_strategy' => 'weighted',
        'weight_in_overall' => 100.00,
        'is_back_to_zero' => true,
        'advancement_count' => 1,
        'status' => 'active',
    ]);
    $crit2 = Criterion::create([
        'round_id' => $round2->id,
        'name' => 'Q&A',
        'weight_percentage' => 100.00,
        'min_score' => 70.00,
        'max_score' => 100.00,
        'score_step' => 0.50,
        'sequence' => 1,
    ]);

    $round2->contestants()->sync([$fx['contestants'][0]->id => ['performance_order' => 1]]);

    recordScoresForContestant($fx['round'], $fx['contestants'][0], $fx['judges'], $fx['criteria'], [[99], [99]]);
    recordScoresForContestant($round2, $fx['contestants'][0], $fx['judges'], [$crit2], [[85], [85]]);

    $engine = app(ScoringEngineService::class);
    $engine->tabulateRound($fx['round']);
    $r2Results = $engine->tabulateRound($round2);

    expect((float) $r2Results[0]->net_composite)->toBe(85.0)
        ->and($r2Results[0]->cumulative_score)->toBeNull();
});

test('score audit logs are immutable and reject update or delete', function () {
    $fx = createScoringFixture('weighted', 1, 1, [['name' => 'Overall', 'weight' => 100.0]]);

    $log = ScoreAuditLog::create([
        'event_id' => $fx['event']->id,
        'round_id' => $fx['round']->id,
        'judge_id' => $fx['judges'][0]->id,
        'contestant_id' => $fx['contestants'][0]->id,
        'action' => 'LOCKED',
        'ip_address' => '127.0.0.1',
    ]);

    expect(fn () => $log->update(['reason' => 'Tamper attempt']))
        ->toThrow(ImmutableAuditLogException::class)
        ->and(fn () => $log->delete())
        ->toThrow(ImmutableAuditLogException::class);
});

test('T-ALG-10: multi-round elimination evaluates only qualified round contestants', function () {
    $fx = createScoringFixture('ordinal', 2, 15, [['name' => 'Overall', 'weight' => 100.0]], false, 10);

    // Give all 15 contestants scores in Round 1
    foreach ($fx['contestants'] as $idx => $contestant) {
        $score = 99.0 - $idx; // C1=99 down to C15=85
        recordScoresForContestant($fx['round'], $contestant, $fx['judges'], $fx['criteria'], [[$score], [$score]]);
    }

    $engine = app(ScoringEngineService::class);
    $r1Results = $engine->tabulateRound($fx['round']);
    expect($r1Results)->toHaveCount(15);

    // Seed Round 2 with Top 10 qualifiers only
    $round2 = Round::create([
        'event_id' => $fx['event']->id,
        'name' => 'Round 2: Semi-Finals',
        'sequence' => 2,
        'scoring_strategy' => 'ordinal',
        'weight_in_overall' => 60.00,
        'is_back_to_zero' => true,
        'advancement_count' => 5,
        'eliminate_count' => 5,
        'status' => 'active',
    ]);
    $crit2 = Criterion::create([
        'round_id' => $round2->id,
        'name' => 'Overall',
        'weight_percentage' => 100.00,
        'min_score' => 70.00,
        'max_score' => 100.00,
        'score_step' => 0.50,
        'sequence' => 1,
    ]);

    $syncData = [];
    foreach ($r1Results->where('is_qualified', true)->values() as $idx => $res) {
        $syncData[$res->contestant_id] = [
            'performance_order' => $idx + 1,
            'qualified_from_rank' => $res->final_rank,
            'qualification_method' => 'AUTO_TOP_N',
        ];
    }
    $round2->contestants()->sync($syncData);

    // Record Round 2 scores ONLY for the 10 qualifiers; eliminated 5 have no scores
    foreach (array_slice($fx['contestants'], 0, 10) as $idx => $contestant) {
        $score = 95.0 - ($idx * 0.5);
        recordScoresForContestant($round2, $contestant, $fx['judges'], [$crit2], [[$score], [$score]]);
    }

    $r2Results = $engine->tabulateRound($round2);
    $maxRankSum = $r2Results->max(fn ($r) => (float) $r->rank_sum);

    expect($r2Results)->toHaveCount(10)
        ->and($maxRankSum)->toBe(20.0) // 2 judges * rank 10 = 20.0 (not 30.0)
        ->and($r2Results->where('is_qualified', true))->toHaveCount(5);
});

test('T-ALG-11: detects unbroken tie straddling advancement cutoff', function () {
    $fx = createScoringFixture('weighted', 2, 4, [['name' => 'Overall', 'weight' => 100.0]], false, 2);

    // C1=98, C2=92, C3=92 (identical across all criteria & judges -> straddles Top 2), C4=85
    recordScoresForContestant($fx['round'], $fx['contestants'][0], $fx['judges'], $fx['criteria'], [[98], [98]]);
    recordScoresForContestant($fx['round'], $fx['contestants'][1], $fx['judges'], $fx['criteria'], [[92], [92]]);
    recordScoresForContestant($fx['round'], $fx['contestants'][2], $fx['judges'], $fx['criteria'], [[92], [92]]);
    recordScoresForContestant($fx['round'], $fx['contestants'][3], $fx['judges'], $fx['criteria'], [[85], [85]]);

    $engine = app(ScoringEngineService::class);
    $results = $engine->tabulateRound($fx['round']);

    expect($engine->hasUnresolvedCutoffTie($fx['round'], $results))->toBeTrue();
});

test('T-ALG-12: dynamic four-round segmented cumulative and back-to-zero chain isolates segments and ranks by cumulative score', function () {
    $fx = createScoringFixture('weighted', 2, 2, [['name' => 'Overall', 'weight' => 100.0]], false, 2);
    $r1 = $fx['round'];
    $r1->update(['weight_in_overall' => 40.00, 'is_back_to_zero' => false]);

    $r2 = Round::create([
        'event_id' => $fx['event']->id,
        'name' => 'Round 2',
        'sequence' => 2,
        'scoring_strategy' => 'weighted',
        'weight_in_overall' => 60.00,
        'is_back_to_zero' => false,
        'advancement_count' => 2,
        'status' => 'active',
    ]);
    $c2 = Criterion::create(['round_id' => $r2->id, 'name' => 'Overall', 'weight_percentage' => 100.0, 'min_score' => 70.0, 'max_score' => 100.0, 'score_step' => 0.5, 'sequence' => 1]);

    $r3 = Round::create([
        'event_id' => $fx['event']->id,
        'name' => 'Round 3 (Reset Segment)',
        'sequence' => 3,
        'scoring_strategy' => 'weighted',
        'weight_in_overall' => 30.00,
        'is_back_to_zero' => true,
        'advancement_count' => 2,
        'status' => 'active',
    ]);
    $c3 = Criterion::create(['round_id' => $r3->id, 'name' => 'Overall', 'weight_percentage' => 100.0, 'min_score' => 70.0, 'max_score' => 100.0, 'score_step' => 0.5, 'sequence' => 1]);

    $r4 = Round::create([
        'event_id' => $fx['event']->id,
        'name' => 'Round 4',
        'sequence' => 4,
        'scoring_strategy' => 'weighted',
        'weight_in_overall' => 70.00,
        'is_back_to_zero' => false,
        'advancement_count' => 1,
        'status' => 'active',
    ]);
    $c4 = Criterion::create(['round_id' => $r4->id, 'name' => 'Overall', 'weight_percentage' => 100.0, 'min_score' => 70.0, 'max_score' => 100.0, 'score_step' => 0.5, 'sequence' => 1]);

    $cIds = [
        $fx['contestants'][0]->id => ['performance_order' => 1],
        $fx['contestants'][1]->id => ['performance_order' => 2],
    ];
    $r2->contestants()->sync($cIds);
    $r3->contestants()->sync($cIds);
    $r4->contestants()->sync($cIds);

    // C1 dominates R1+R2 (95, 95) -> R2 cumulative 95.0; C2 has (80, 80) -> R2 cumulative 80.0
    recordScoresForContestant($r1, $fx['contestants'][0], $fx['judges'], $fx['criteria'], [[95], [95]]);
    recordScoresForContestant($r1, $fx['contestants'][1], $fx['judges'], $fx['criteria'], [[80], [80]]);
    recordScoresForContestant($r2, $fx['contestants'][0], $fx['judges'], [$c2], [[95], [95]]);
    recordScoresForContestant($r2, $fx['contestants'][1], $fx['judges'], [$c2], [[80], [80]]);

    // R3 resets: C1=80, C2=90. R4: C1=90, C2=88
    // Segment 2 (R3 30% + R4 70%):
    // C1 = 80*0.30 + 90*0.70 = 24 + 63 = 87.000
    // C2 = 90*0.30 + 88*0.70 = 27 + 61.6 = 88.600 -> C2 must rank #1 in R4 despite C1 having higher R4 raw (90 vs 88)!
    recordScoresForContestant($r3, $fx['contestants'][0], $fx['judges'], [$c3], [[80], [80]]);
    recordScoresForContestant($r3, $fx['contestants'][1], $fx['judges'], [$c3], [[90], [90]]);
    recordScoresForContestant($r4, $fx['contestants'][0], $fx['judges'], [$c4], [[90], [90]]);
    recordScoresForContestant($r4, $fx['contestants'][1], $fx['judges'], [$c4], [[88], [88]]);

    $engine = app(ScoringEngineService::class);
    $engine->tabulateRound($r1);
    $r2Res = $engine->tabulateRound($r2)->keyBy('contestant_id');
    $r3Res = $engine->tabulateRound($r3)->keyBy('contestant_id');
    $r4Res = $engine->tabulateRound($r4)->values();

    expect((float) $r2Res[$fx['contestants'][0]->id]->cumulative_score)->toBe(95.0)
        ->and($r3Res[$fx['contestants'][0]->id]->cumulative_score)->toBeNull()
        ->and($r4Res[0]->contestant_id)->toBe($fx['contestants'][1]->id)
        ->and($r4Res[0]->final_rank)->toBe(1)
        ->and($r4Res[0]->is_qualified)->toBeTrue()
        ->and((float) $r4Res[0]->cumulative_score)->toBe(88.6)
        ->and((float) $r4Res[1]->cumulative_score)->toBe(87.0)
        ->and($r4Res[1]->final_rank)->toBe(2)
        ->and($r4Res[1]->is_qualified)->toBeFalse();
});

test('mid-segment cumulative round normalizes by accumulated segment weight onto 0-100 scale', function () {
    $fx = createScoringFixture('weighted', 2, 1, [['name' => 'Overall', 'weight' => 100.0]], false, 1);
    $fx['round']->update(['weight_in_overall' => 40.00]);

    $r2 = Round::create([
        'event_id' => $fx['event']->id,
        'name' => 'Round 2 (Mid-Segment)',
        'sequence' => 2,
        'scoring_strategy' => 'weighted',
        'weight_in_overall' => 30.00,
        'is_back_to_zero' => false,
        'advancement_count' => 1,
        'status' => 'active',
    ]);
    $c2 = Criterion::create(['round_id' => $r2->id, 'name' => 'Overall', 'weight_percentage' => 100.0, 'min_score' => 70.0, 'max_score' => 100.0, 'score_step' => 0.5, 'sequence' => 1]);

    $r2->contestants()->sync([$fx['contestants'][0]->id => ['performance_order' => 1]]);

    // R1 = 80 (weight 40), R2 = 94 (weight 30) -> (80*40 + 94*30) / 70 = (3200 + 2820) / 70 = 6020 / 70 = 86.000
    recordScoresForContestant($fx['round'], $fx['contestants'][0], $fx['judges'], $fx['criteria'], [[80], [80]]);
    recordScoresForContestant($r2, $fx['contestants'][0], $fx['judges'], [$c2], [[94], [94]]);

    $engine = app(ScoringEngineService::class);
    $engine->tabulateRound($fx['round']);
    $r2Res = $engine->tabulateRound($r2);

    expect((float) $r2Res[0]->cumulative_score)->toBe(86.0);
});
