<?php

use App\Models\Contestant;
use App\Models\Criterion;
use App\Models\Event;
use App\Models\Round;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

function seedSetupEventWithContestants(int $contestantCount = 16): array
{
    $admin = User::factory()->create([
        'email' => 'tabulator-rc@tabul8.local',
        'password' => Hash::make('secret123'),
        'role' => 'tabulator',
        'is_active' => true,
    ]);

    $event = Event::create([
        'title' => 'Setup Funnel Event',
        'event_type' => 'pageant',
        'status' => 'setup',
        'created_by' => $admin->id,
    ]);

    for ($i = 1; $i <= $contestantCount; $i++) {
        Contestant::create([
            'event_id' => $event->id,
            'candidate_number' => $i,
            'name' => "Candidate {$i}",
            'origin_or_group' => 'Metro',
            'status' => 'active',
        ]);
    }

    return compact('admin', 'event');
}

it('generates a progressive N-round funnel and supports CRUD on rounds', function () {
    $fx = seedSetupEventWithContestants(16);
    Sanctum::actingAs($fx['admin'], ['role:tabulator']);

    $res = $this->postJson("/api/v1/admin/events/{$fx['event']->id}/rounds/generate-funnel", [
        'round_count' => 4,
        'final_winners_count' => 3,
        'final_back_to_zero' => true,
    ])->assertStatus(200);

    $rounds = Round::where('event_id', $fx['event']->id)->orderBy('sequence')->get();
    expect($rounds)->toHaveCount(4)
        ->and($rounds->last()->advancement_count)->toBe(3)
        ->and($rounds->last()->is_back_to_zero)->toBeTrue()
        ->and($rounds->first()->advancement_count)->toBeLessThanOrEqual(16)
        ->and($rounds->first()->advancement_count)->toBeGreaterThanOrEqual(3);

    // Create a 5th upcoming round manually
    $createRes = $this->postJson("/api/v1/admin/events/{$fx['event']->id}/rounds", [
        'name' => 'Bonus Tiebreaker',
        'scoring_strategy' => 'ordinal',
        'weight_in_overall' => 100.00,
        'is_back_to_zero' => true,
        'cutoff_mode' => 'top_n_advance',
        'advancement_count' => 1,
    ])->assertStatus(201);

    $bonusId = (int) $createRes->json('data.round.id');
    expect(Round::where('event_id', $fx['event']->id)->count())->toBe(5);

    // Update round
    $this->putJson("/api/v1/admin/rounds/{$bonusId}", [
        'name' => 'Sudden Death Final',
        'advancement_count' => 2,
    ])->assertStatus(200)->assertJsonPath('data.round.name', 'Sudden Death Final');

    // Reject impossible advancement count exceeding entering pool (16)
    $firstRound = $rounds->first();
    $this->putJson("/api/v1/admin/rounds/{$firstRound->id}", [
        'advancement_count' => 99,
    ])->assertStatus(422);

    // Delete upcoming round cleanly
    $this->deleteJson("/api/v1/admin/rounds/{$bonusId}")->assertStatus(200);
    expect(Round::find($bonusId))->toBeNull();
});

it('performs two-phase transactional round reorder without unique constraint collisions and blocks active round mutations with 409', function () {
    $fx = seedSetupEventWithContestants(8);
    Sanctum::actingAs($fx['admin'], ['role:tabulator']);

    $r1 = Round::create([
        'event_id' => $fx['event']->id,
        'name' => 'R1',
        'sequence' => 1,
        'status' => 'upcoming',
        'advancement_count' => 6,
    ]);
    $r2 = Round::create([
        'event_id' => $fx['event']->id,
        'name' => 'R2',
        'sequence' => 2,
        'status' => 'upcoming',
        'advancement_count' => 4,
    ]);
    $r3 = Round::create([
        'event_id' => $fx['event']->id,
        'name' => 'R3',
        'sequence' => 3,
        'status' => 'upcoming',
        'advancement_count' => 2,
    ]);

    // Reorder [3, 1, 2]
    $this->putJson("/api/v1/admin/events/{$fx['event']->id}/rounds/reorder", [
        'round_ids' => [$r3->id, $r1->id, $r2->id],
    ])->assertStatus(200);

    expect($r3->fresh()->sequence)->toBe(1)
        ->and($r1->fresh()->sequence)->toBe(2)
        ->and($r2->fresh()->sequence)->toBe(3);

    // Mark r3 active -> block deleting and reordering with 409
    $r3->update(['status' => 'active']);

    $this->deleteJson("/api/v1/admin/rounds/{$r3->id}")->assertStatus(409);
    $this->putJson("/api/v1/admin/events/{$fx['event']->id}/rounds/reorder", [
        'round_ids' => [$r1->id, $r2->id, $r3->id],
    ])->assertStatus(409);

    expect($r3->fresh()->sequence)->toBe(1);
});

it('supports nested parent/child criteria CRUD and computes leaf weights without double-counting parents', function () {
    $fx = seedSetupEventWithContestants(4);
    Sanctum::actingAs($fx['admin'], ['role:tabulator']);

    $round = Round::create([
        'event_id' => $fx['event']->id,
        'name' => 'R1',
        'sequence' => 1,
        'status' => 'upcoming',
        'advancement_count' => 2,
    ]);

    // Parent group criterion (weight 0 or 50, children are leaves!)
    $parentRes = $this->postJson("/api/v1/admin/rounds/{$round->id}/criteria", [
        'name' => 'Presentation Group',
        'weight_percentage' => 50.00,
        'min_score' => 70,
        'max_score' => 100,
        'score_step' => 0.5,
    ])->assertStatus(201);
    $parentId = (int) $parentRes->json('data.criterion.id');

    // Add 2 leaf children (25 + 25 = 50) + 1 sibling criterion (50) => total leaf sum = 100.00
    $this->postJson("/api/v1/admin/rounds/{$round->id}/criteria", [
        'parent_id' => $parentId,
        'name' => 'Runway Walk',
        'weight_percentage' => 25.00,
        'min_score' => 70,
        'max_score' => 100,
        'score_step' => 0.5,
    ])->assertStatus(201);

    $this->postJson("/api/v1/admin/rounds/{$round->id}/criteria", [
        'parent_id' => $parentId,
        'name' => 'Stage Eye Contact',
        'weight_percentage' => 25.00,
        'min_score' => 70,
        'max_score' => 100,
        'score_step' => 0.5,
    ])->assertStatus(201);

    $siblingRes = $this->postJson("/api/v1/admin/rounds/{$round->id}/criteria", [
        'name' => 'Vocal Articulation',
        'weight_percentage' => 40.00,
        'min_score' => 70,
        'max_score' => 100,
        'score_step' => 0.5,
    ])->assertStatus(201);

    expect((float) $siblingRes->json('data.leaf_weight_sum'))->toBe(90.0);

    $siblingId = (int) $siblingRes->json('data.criterion.id');
    $updRes = $this->putJson("/api/v1/admin/criteria/{$siblingId}", [
        'weight_percentage' => 50.00,
    ])->assertStatus(200);

    expect((float) $updRes->json('data.leaf_weight_sum'))->toBe(100.0);

    // Preflight criteria_weights check should recognize 100.00% leaf sum without double-counting parent!
    $preflightRes = $this->getJson("/api/v1/admin/events/{$fx['event']->id}/preflight")
        ->assertStatus(200);

    $weightCheck = collect($preflightRes->json('data.checks'))->firstWhere('key', 'criteria_weights');
    expect($weightCheck['ok'])->toBeTrue();

    // Delete criterion
    $this->deleteJson("/api/v1/admin/criteria/{$siblingId}")->assertStatus(200);
    expect(Criterion::find($siblingId))->toBeNull();
});

it('distributes cumulative funnel weights cleanly for preflight and guards active round & parent criterion mutations', function () {
    $fx = seedSetupEventWithContestants(8);
    Sanctum::actingAs($fx['admin'], ['role:tabulator']);

    // Generate 3-round cumulative funnel (final_back_to_zero = false => all 3 rounds share 1 segment)
    $this->postJson("/api/v1/admin/events/{$fx['event']->id}/rounds/generate-funnel", [
        'round_count' => 3,
        'final_winners_count' => 3,
        'final_back_to_zero' => false,
    ])->assertStatus(200);

    $preflightRes = $this->getJson("/api/v1/admin/events/{$fx['event']->id}/preflight")
        ->assertStatus(200);
    $segCheck = collect($preflightRes->json('data.checks'))->firstWhere('key', 'segment_weights');
    expect($segCheck['ok'])->toBeTrue();

    $round1 = $fx['event']->rounds()->orderBy('sequence')->first();
    $round2 = $fx['event']->rounds()->orderBy('sequence')->skip(1)->first();

    // Create parent group in round1 and try cross-round reparenting
    $parentId = (int) $this->postJson("/api/v1/admin/rounds/{$round1->id}/criteria", [
        'name' => 'Group A',
        'weight_percentage' => 0,
        'min_score' => 70,
        'max_score' => 100,
        'score_step' => 1,
    ])->assertStatus(201)->json('data.criterion.id');

    $round2CritId = (int) $round2->criteria()->first()->id;
    $this->putJson("/api/v1/admin/criteria/{$round2CritId}", [
        'parent_id' => $parentId,
    ])->assertStatus(422);

    // Mutating an active round via setup PUT /admin/rounds/{round} returns 409
    $round1->update(['status' => 'active']);
    $this->putJson("/api/v1/admin/rounds/{$round1->id}", [
        'weight_in_overall' => 50,
    ])->assertStatus(409);
});
