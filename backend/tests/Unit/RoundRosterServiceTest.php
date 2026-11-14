<?php

use App\Models\Contestant;
use App\Models\Event;
use App\Models\Round;
use App\Services\RoundRosterService;

test('Round 1 falls back to all active event contestants when round_contestants is empty', function (): void {
    $event = Event::create(['title' => 'Pageant 2026', 'event_type' => 'pageant', 'status' => 'live']);
    $r1 = Round::create([
        'event_id' => $event->id,
        'name' => 'Round 1',
        'sequence' => 1,
        'scoring_strategy' => 'weighted',
        'advancement_count' => 2,
        'status' => 'active',
    ]);

    $c1 = Contestant::create(['event_id' => $event->id, 'candidate_number' => 1, 'name' => 'C1', 'origin_or_group' => 'A', 'status' => 'active']);
    $c2 = Contestant::create(['event_id' => $event->id, 'candidate_number' => 2, 'name' => 'C2', 'origin_or_group' => 'B', 'status' => 'active']);
    Contestant::create(['event_id' => $event->id, 'candidate_number' => 3, 'name' => 'C3', 'origin_or_group' => 'C', 'status' => 'withdrawn']);

    $service = app(RoundRosterService::class);
    $qualified = $service->getQualifiedContestants($r1);

    expect($qualified->pluck('id')->all())->toBe([$c1->id, $c2->id])
        ->and($service->getActiveContestantIds($r1))->toBe([$c1->id, $c2->id]);
});

test('Round 2 returns strictly seeded round_contestants with lineage and identifies eliminated contestants', function (): void {
    $event = Event::create(['title' => 'Pageant 2026', 'event_type' => 'pageant', 'status' => 'live']);
    $r1 = Round::create([
        'event_id' => $event->id,
        'name' => 'Round 1: Prelims',
        'sequence' => 1,
        'scoring_strategy' => 'weighted',
        'advancement_count' => 2,
        'status' => 'finalized',
    ]);
    $r2 = Round::create([
        'event_id' => $event->id,
        'name' => 'Round 2: Semis',
        'sequence' => 2,
        'scoring_strategy' => 'weighted',
        'advancement_count' => 1,
        'status' => 'active',
    ]);

    $c1 = Contestant::create(['event_id' => $event->id, 'candidate_number' => 1, 'name' => 'C1', 'origin_or_group' => 'A', 'status' => 'active']);
    $c2 = Contestant::create(['event_id' => $event->id, 'candidate_number' => 2, 'name' => 'C2', 'origin_or_group' => 'B', 'status' => 'active']);
    $c3 = Contestant::create(['event_id' => $event->id, 'candidate_number' => 3, 'name' => 'C3', 'origin_or_group' => 'C', 'status' => 'active']);

    $r2->contestants()->sync([
        $c1->id => ['performance_order' => 1, 'qualified_from_rank' => 1, 'qualification_method' => 'AUTO_TOP_N'],
        $c3->id => ['performance_order' => 2, 'qualified_from_rank' => 2, 'qualification_method' => 'MANUAL_WILDCARD', 'override_reason' => 'Committee wildcard'],
    ]);

    $service = app(RoundRosterService::class);
    $qualified = $service->getQualifiedContestants($r2);
    $eliminated = $service->getEliminatedContestants($r2);

    expect($qualified->pluck('id')->all())->toBe([$c1->id, $c3->id])
        ->and($qualified[1]->pivot->qualification_method)->toBe('MANUAL_WILDCARD')
        ->and($qualified[1]->pivot->qualified_from_rank)->toBe(2)
        ->and($eliminated)->toHaveCount(1)
        ->and($eliminated[0]['contestant']->id)->toBe($c2->id)
        ->and($eliminated[0]['eliminated_in_round_name'])->toBe('Round 1: Prelims');
});

test('buildRoundFunnel computes cascading starting, eliminate, and advancement counts across 3 rounds', function (): void {
    $event = Event::create(['title' => 'Pageant 15', 'event_type' => 'pageant', 'status' => 'live']);
    for ($i = 1; $i <= 15; $i++) {
        Contestant::create(['event_id' => $event->id, 'candidate_number' => $i, 'name' => "C{$i}", 'origin_or_group' => 'City', 'status' => 'active']);
    }

    $r1 = Round::create(['event_id' => $event->id, 'name' => 'R1', 'sequence' => 1, 'advancement_count' => 10, 'eliminate_count' => 5, 'status' => 'active']);
    $r2 = Round::create(['event_id' => $event->id, 'name' => 'R2', 'sequence' => 2, 'advancement_count' => 5, 'eliminate_count' => 5, 'status' => 'upcoming']);
    $r3 = Round::create(['event_id' => $event->id, 'name' => 'R3', 'sequence' => 3, 'advancement_count' => 3, 'eliminate_count' => 2, 'status' => 'upcoming']);

    $funnel = app(RoundRosterService::class)->buildRoundFunnel($event);

    expect($funnel)->toHaveCount(3)
        ->and($funnel[0])->toMatchArray(['round_id' => $r1->id, 'starting_count' => 15, 'eliminate_count' => 5, 'advancement_count' => 10])
        ->and($funnel[1])->toMatchArray(['round_id' => $r2->id, 'starting_count' => 10, 'eliminate_count' => 5, 'advancement_count' => 5])
        ->and($funnel[2])->toMatchArray(['round_id' => $r3->id, 'starting_count' => 5, 'eliminate_count' => 2, 'advancement_count' => 3]);
});
