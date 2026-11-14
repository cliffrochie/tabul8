<?php

use App\Events\LedWallUpdated;
use App\Events\StageStateUpdated;
use App\Models\Contestant;
use App\Models\Criterion;
use App\Models\Event;
use App\Models\Judge;
use App\Models\Round;
use App\Models\Score;
use App\Models\ScoreAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

function seedMultiRoundEvent(int $round2Advancement = 3): array
{
    $admin = User::factory()->create([
        'email' => 'tabulator-m1@tabul8.local',
        'password' => Hash::make('secret123'),
        'role' => 'tabulator',
        'is_active' => true,
    ]);

    $event = Event::create([
        'title' => 'Multi-Round Championship 2026',
        'event_type' => 'pageant',
        'status' => 'live',
        'created_by' => $admin->id,
    ]);

    $round1 = Round::create([
        'event_id' => $event->id,
        'name' => 'Round 1: Preliminaries',
        'sequence' => 1,
        'scoring_strategy' => 'weighted',
        'weight_in_overall' => 50.00,
        'is_back_to_zero' => true,
        'cutoff_mode' => 'top_n_advance',
        'advancement_count' => 1,
        'status' => 'active',
    ]);

    $round2 = Round::create([
        'event_id' => $event->id,
        'name' => 'Round 2: Top Finalists',
        'sequence' => 2,
        'scoring_strategy' => 'weighted',
        'weight_in_overall' => 50.00,
        'is_back_to_zero' => false,
        'cutoff_mode' => 'top_n_advance',
        'advancement_count' => $round2Advancement,
        'status' => 'upcoming',
    ]);

    $event->update(['active_round_id' => $round1->id]);

    $c1Crit = Criterion::create([
        'round_id' => $round1->id,
        'name' => 'Poise',
        'weight_percentage' => 100.00,
        'min_score' => 70.00,
        'max_score' => 100.00,
        'score_step' => 0.50,
        'sequence' => 1,
    ]);

    $c2Crit = Criterion::create([
        'round_id' => $round2->id,
        'name' => 'Q&A Delivery',
        'weight_percentage' => 100.00,
        'min_score' => 70.00,
        'max_score' => 100.00,
        'score_step' => 0.50,
        'sequence' => 1,
    ]);

    $judge = Judge::create([
        'event_id' => $event->id,
        'name' => 'Judge Elena',
        'judge_number' => 1,
        'access_pin_hash' => Hash::make('111111'),
        'plain_pin_reference' => '111111',
        'is_active' => true,
    ]);

    $c1 = Contestant::create([
        'event_id' => $event->id,
        'candidate_number' => 1,
        'name' => 'Candidate 1',
        'origin_or_group' => 'Manila',
        'status' => 'active',
    ]);
    $c2 = Contestant::create([
        'event_id' => $event->id,
        'candidate_number' => 2,
        'name' => 'Candidate 2',
        'origin_or_group' => 'Cebu',
        'status' => 'active',
    ]);
    $c3 = Contestant::create([
        'event_id' => $event->id,
        'candidate_number' => 3,
        'name' => 'Candidate 3',
        'origin_or_group' => 'Davao',
        'status' => 'active',
    ]);

    $round1->contestants()->sync([
        $c1->id => ['performance_order' => 1],
        $c2->id => ['performance_order' => 2],
        $c3->id => ['performance_order' => 3],
    ]);

    return compact('admin', 'event', 'round1', 'round2', 'c1Crit', 'c2Crit', 'judge', 'c1', 'c2', 'c3');
}

function lockAllScoresForRound(Round $round, Criterion $crit, Judge $judge, array $scoresByContestantId): void
{
    foreach ($scoresByContestantId as $cid => $raw) {
        Score::create([
            'round_id' => $round->id,
            'criterion_id' => $crit->id,
            'contestant_id' => $cid,
            'judge_id' => $judge->id,
            'raw_score' => $raw,
            'weighted_score' => $raw,
            'status' => 'LOCKED',
            'submitted_at' => now(),
        ]);
    }
}

it('returns 409 with errors.cutoff_tie when finalizing a round tied at the cutoff', function () {
    $fx = seedMultiRoundEvent();
    lockAllScoresForRound($fx['round1'], $fx['c1Crit'], $fx['judge'], [
        $fx['c1']->id => 95.0,
        $fx['c2']->id => 95.0,
        $fx['c3']->id => 80.0,
    ]);

    Sanctum::actingAs($fx['admin'], ['role:tabulator']);

    $res = $this->postJson("/api/v1/admin/rounds/{$fx['round1']->id}/finalize")
        ->assertStatus(409)
        ->assertJsonPath('data', null)
        ->assertJsonPath('errors.cutoff_tie.advancement_count', 1);

    $tiedIds = $res->json('errors.cutoff_tie.tied_contestant_ids');
    expect($tiedIds)->toEqualCanonicalizing([$fx['c1']->id, $fx['c2']->id]);
    expect($fx['round1']->fresh()->status)->toBe('active');
});

it('finalizes with wildcard override, clamps next round advancement, activates next round, and broadcasts StageStateUpdated', function () {
    EventFacade::fake([StageStateUpdated::class, LedWallUpdated::class]);

    $fx = seedMultiRoundEvent(round2Advancement: 3);
    lockAllScoresForRound($fx['round1'], $fx['c1Crit'], $fx['judge'], [
        $fx['c1']->id => 95.0,
        $fx['c2']->id => 95.0,
        $fx['c3']->id => 80.0,
    ]);

    Sanctum::actingAs($fx['admin'], ['role:tabulator']);

    $res = $this->postJson("/api/v1/admin/rounds/{$fx['round1']->id}/finalize", [
        'activate_next_round' => true,
        'push_to_led_reveal' => true,
        'qualified_contestant_ids' => [$fx['c1']->id, $fx['c2']->id],
        'override_reason' => 'Wildcard tie exemption granted by chair',
    ])->assertStatus(200);

    expect($res->json('errors'))->toBeNull();

    expect(ScoreAuditLog::where('round_id', $fx['round1']->id)->where('action', 'ROUND_FINALIZED')->count())->toBe(1)
        ->and(ScoreAuditLog::where('round_id', $fx['round1']->id)->where('action', 'QUALIFIER_OVERRIDE')->count())->toBe(1);

    $pivots = $fx['round2']->contestants()->get()->keyBy('id');
    expect($pivots->keys()->all())->toEqualCanonicalizing([$fx['c1']->id, $fx['c2']->id])
        ->and($pivots[$fx['c1']->id]->pivot->qualification_method)->toBe('MANUAL_WILDCARD')
        ->and($pivots[$fx['c1']->id]->pivot->override_reason)->toBe('Wildcard tie exemption granted by chair')
        ->and($pivots[$fx['c2']->id]->pivot->qualification_method)->toBe('MANUAL_WILDCARD');

    // Clamped from 3 -> 2 because only 2 qualifiers entered Round 2
    expect($fx['round2']->fresh()->advancement_count)->toBe(2)
        ->and($fx['round2']->fresh()->status)->toBe('active')
        ->and($fx['event']->fresh()->active_round_id)->toBe($fx['round2']->id)
        ->and($fx['event']->fresh()->led_mode)->toBe('reveal')
        ->and($fx['event']->fresh()->revealed_contestant_ids)->toEqualCanonicalizing([$fx['c1']->id, $fx['c2']->id]);

    EventFacade::assertDispatched(StageStateUpdated::class, function (StageStateUpdated $e) use ($fx) {
        return $e->eventId === $fx['event']->id
            && $e->activeRoundId === $fx['round2']->id
            && $e->ledMode === 'reveal';
    });
});

it('scopes judge event state, contestant lineage, round funnel, my_scores ranks, and lock_all to Round 2 qualifiers', function () {
    $fx = seedMultiRoundEvent(round2Advancement: 1);
    lockAllScoresForRound($fx['round1'], $fx['c1Crit'], $fx['judge'], [
        $fx['c1']->id => 95.0,
        $fx['c2']->id => 90.0,
        $fx['c3']->id => 80.0,
    ]);

    // Finalize with wildcard c1 & c2, activating Round 2
    Sanctum::actingAs($fx['admin'], ['role:tabulator']);
    $this->postJson("/api/v1/admin/rounds/{$fx['round1']->id}/finalize", [
        'activate_next_round' => true,
        'qualified_contestant_ids' => [$fx['c1']->id, $fx['c2']->id],
        'override_reason' => 'Top 2 wildcard finalists',
    ])->assertStatus(200);

    // Judge enters scores in Round 2 for c1 and c2
    Sanctum::actingAs($fx['judge'], ['role:judge']);
    $this->postJson('/api/v1/scores/upsert', [
        'round_id' => $fx['round2']->id,
        'mutations' => [
            [
                'client_mutation_id' => 'm-r2-c1',
                'contestant_id' => $fx['c1']->id,
                'criterion_id' => $fx['c2Crit']->id,
                'action' => 'UPSERT_SCORE',
                'raw_score' => 92.0,
                'client_timestamp' => now()->toIso8601String(),
            ],
            [
                'client_mutation_id' => 'm-r2-c2',
                'contestant_id' => $fx['c2']->id,
                'criterion_id' => $fx['c2Crit']->id,
                'action' => 'UPSERT_SCORE',
                'raw_score' => 96.0,
                'client_timestamp' => now()->toIso8601String(),
            ],
        ],
    ])->assertStatus(200);

    $stateRes = $this->getJson("/api/v1/events/{$fx['event']->id}/state")
        ->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'round_funnel',
                'my_scores',
                'contestants',
            ],
        ]);

    $contestants = collect($stateRes->json('data.contestants'))->keyBy('id');
    expect($contestants[$fx['c1']->id]['is_qualified_in_active_round'])->toBeTrue()
        ->and($contestants[$fx['c1']->id]['qualification_method'])->toBe('MANUAL_WILDCARD')
        ->and($contestants[$fx['c1']->id]['eliminated_in_round_name'])->toBeNull()
        ->and($contestants[$fx['c3']->id]['is_qualified_in_active_round'])->toBeFalse()
        ->and($contestants[$fx['c3']->id]['eliminated_in_round_name'])->toBe('Round 1: Preliminaries');

    $myScores = $stateRes->json('data.my_scores');
    expect(array_keys($myScores))->toEqualCanonicalizing([(string) $fx['c1']->id, (string) $fx['c2']->id])
        ->and(array_key_exists((string) $fx['c3']->id, $myScores))->toBeFalse()
        ->and($myScores[(string) $fx['c2']->id]['personal_rank'])->toEqual(1.0)
        ->and($myScores[(string) $fx['c1']->id]['personal_rank'])->toEqual(2.0);

    $funnel = collect($stateRes->json('data.round_funnel'))->keyBy('round_id');
    expect($funnel[$fx['round1']->id]['starting_count'])->toBe(3)
        ->and($funnel[$fx['round1']->id]['advancement_count'])->toBe(1)
        ->and($funnel[$fx['round1']->id]['eliminate_count'])->toBe(2)
        ->and($funnel[$fx['round2']->id]['starting_count'])->toBe(2);

    // lock_all only locks Round 2 qualifiers, never creating scores for eliminated c3
    $this->postJson('/api/v1/scores/lock', [
        'round_id' => $fx['round2']->id,
        'lock_all' => true,
    ])->assertStatus(200);

    expect(Score::where('round_id', $fx['round2']->id)->where('contestant_id', $fx['c3']->id)->exists())->toBeFalse()
        ->and(Score::where('round_id', $fx['round2']->id)->where('contestant_id', $fx['c1']->id)->first()->status)->toBe('LOCKED')
        ->and(Score::where('round_id', $fx['round2']->id)->where('contestant_id', $fx['c2']->id)->first()->status)->toBe('LOCKED');
});

it('reopens a finalized round only when the next round has zero locked scores', function () {
    $fx = seedMultiRoundEvent();
    lockAllScoresForRound($fx['round1'], $fx['c1Crit'], $fx['judge'], [
        $fx['c1']->id => 95.0,
        $fx['c2']->id => 90.0,
        $fx['c3']->id => 80.0,
    ]);

    Sanctum::actingAs($fx['admin'], ['role:tabulator']);
    $this->postJson("/api/v1/admin/rounds/{$fx['round1']->id}/finalize", [
        'activate_next_round' => true,
    ])->assertStatus(200);

    expect($fx['round2']->contestants()->count())->toBe(1);

    // Round 2 has 0 locked scores -> reopen succeeds
    $this->postJson("/api/v1/admin/rounds/{$fx['round1']->id}/reopen")
        ->assertStatus(200);

    expect($fx['round1']->fresh()->status)->toBe('active')
        ->and($fx['round1']->fresh()->is_locked)->toBeFalse()
        ->and($fx['round2']->contestants()->count())->toBe(0)
        ->and($fx['event']->fresh()->active_round_id)->toBe($fx['round1']->id)
        ->and(ScoreAuditLog::where('round_id', $fx['round1']->id)->where('action', 'ROUND_REOPENED')->count())->toBe(1);

    // Re-finalize Round 1 and lock a score in Round 2 -> reopen returns 409
    $this->postJson("/api/v1/admin/rounds/{$fx['round1']->id}/finalize", [
        'activate_next_round' => true,
    ])->assertStatus(200);

    Score::updateOrCreate(
        [
            'round_id' => $fx['round2']->id,
            'criterion_id' => $fx['c2Crit']->id,
            'contestant_id' => $fx['c1']->id,
            'judge_id' => $fx['judge']->id,
        ],
        [
            'raw_score' => 94.0,
            'weighted_score' => 94.0,
            'status' => 'LOCKED',
            'submitted_at' => now(),
        ]
    );

    $this->postJson("/api/v1/admin/rounds/{$fx['round1']->id}/reopen")
        ->assertStatus(409);
});
