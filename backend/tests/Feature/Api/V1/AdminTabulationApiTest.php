<?php

use App\Models\Contestant;
use App\Models\Criterion;
use App\Models\Event;
use App\Models\Judge;
use App\Models\Round;
use App\Models\Score;
use App\Models\ScoreAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

function seedAdminEvent(): array
{
    $admin = User::factory()->create([
        'email' => 'tabulator@tabul8.local',
        'password' => Hash::make('secret123'),
        'role' => 'tabulator',
        'is_active' => true,
    ]);

    $event = Event::create([
        'title' => 'Miss Grand Universe 2026',
        'event_type' => 'pageant',
        'status' => 'live',
        'created_by' => $admin->id,
    ]);

    $round1 = Round::create([
        'event_id' => $event->id,
        'name' => 'Round 1: Preliminaries',
        'sequence' => 1,
        'scoring_strategy' => 'weighted',
        'advancement_count' => 1,
        'status' => 'active',
    ]);

    $round2 = Round::create([
        'event_id' => $event->id,
        'name' => 'Round 2: Final Q&A',
        'sequence' => 2,
        'scoring_strategy' => 'ordinal',
        'is_back_to_zero' => true,
        'advancement_count' => 1,
        'status' => 'upcoming',
    ]);

    $event->update(['active_round_id' => $round1->id]);

    $criterion = Criterion::create([
        'round_id' => $round1->id,
        'name' => 'Poise',
        'weight_percentage' => 100.00,
        'min_score' => 70.00,
        'max_score' => 100.00,
        'score_step' => 0.50,
        'sequence' => 1,
    ]);

    $judge = Judge::create([
        'event_id' => $event->id,
        'judge_number' => 1,
        'name' => 'Judge 1',
        'access_pin_hash' => Hash::make('104928'),
        'plain_pin_reference' => '104928',
        'is_chairman' => true,
        'is_active' => true,
    ]);

    $c1 = Contestant::create([
        'event_id' => $event->id,
        'candidate_number' => 1,
        'name' => 'Ariana Vance',
        'origin_or_group' => 'Manila',
        'status' => 'active',
    ]);
    $c2 = Contestant::create([
        'event_id' => $event->id,
        'candidate_number' => 2,
        'name' => 'Beatrice Solis',
        'origin_or_group' => 'Cebu',
        'status' => 'active',
    ]);

    return compact('admin', 'event', 'round1', 'round2', 'criterion', 'judge', 'c1', 'c2');
}

it('authenticates admin via /auth/login and returns 422 on wrong password', function () {
    $fx = seedAdminEvent();

    $this->postJson('/api/v1/auth/login', [
        'email' => $fx['admin']->email,
        'password' => 'wrong-pass',
    ])->assertStatus(422)->assertJsonStructure(['data', 'message', 'errors' => ['email']]);

    $this->postJson('/api/v1/auth/login', [
        'email' => $fx['admin']->email,
        'password' => 'secret123',
    ])->assertStatus(200)->assertJsonPath('data.user.role', 'tabulator');
});

it('forbids judges from calling admin endpoints', function () {
    $fx = seedAdminEvent();
    Sanctum::actingAs($fx['judge'], ['role:judge']);

    $this->postJson('/api/v1/admin/stage-sync', [
        'event_id' => $fx['event']->id,
        'on_stage_contestant_id' => $fx['c1']->id,
    ])->assertStatus(403);
});

it('approves unlock requests, applies deductions, and logs audit entries', function () {
    $fx = seedAdminEvent();

    Score::create([
        'round_id' => $fx['round1']->id,
        'criterion_id' => $fx['criterion']->id,
        'contestant_id' => $fx['c1']->id,
        'judge_id' => $fx['judge']->id,
        'raw_score' => 95.0,
        'weighted_score' => 95.0,
        'status' => 'UNLOCK_REQUESTED',
    ]);
    Score::create([
        'round_id' => $fx['round1']->id,
        'criterion_id' => $fx['criterion']->id,
        'contestant_id' => $fx['c2']->id,
        'judge_id' => $fx['judge']->id,
        'raw_score' => 90.0,
        'weighted_score' => 90.0,
        'status' => 'LOCKED',
    ]);

    Sanctum::actingAs($fx['admin'], ['role:tabulator']);

    $this->postJson('/api/v1/admin/scores/resolve-unlock', [
        'round_id' => $fx['round1']->id,
        'contestant_id' => $fx['c1']->id,
        'judge_id' => $fx['judge']->id,
        'decision' => 'APPROVE',
    ])->assertStatus(200);

    expect(Score::where('contestant_id', $fx['c1']->id)->first()->status)->toBe('DRAFT')
        ->and(ScoreAuditLog::where('action', 'UNLOCK_APPROVED')->count())->toBe(1);

    // Apply 6.0 deduction to c1 (95 - 6 = 89 < 90) so c2 becomes Rank #1
    $dedRes = $this->postJson('/api/v1/admin/deductions', [
        'round_id' => $fx['round1']->id,
        'contestant_id' => $fx['c1']->id,
        'points_deducted' => 6.0,
        'reason' => 'Overtime',
    ])->assertStatus(200);

    expect($dedRes->json('data.leaderboard.0.contestant_id'))->toBe($fx['c2']->id)
        ->and($dedRes->json('data.leaderboard.0.final_rank'))->toBe(1);
});

it('blocks round finalization when sheets are unlocked and advances qualifiers when all locked', function () {
    $fx = seedAdminEvent();
    Sanctum::actingAs($fx['admin'], ['role:tabulator']);

    Score::create([
        'round_id' => $fx['round1']->id,
        'criterion_id' => $fx['criterion']->id,
        'contestant_id' => $fx['c1']->id,
        'judge_id' => $fx['judge']->id,
        'raw_score' => 95.0,
        'weighted_score' => 95.0,
        'status' => 'DRAFT',
    ]);
    Score::create([
        'round_id' => $fx['round1']->id,
        'criterion_id' => $fx['criterion']->id,
        'contestant_id' => $fx['c2']->id,
        'judge_id' => $fx['judge']->id,
        'raw_score' => 90.0,
        'weighted_score' => 90.0,
        'status' => 'LOCKED',
    ]);

    // Quorum incomplete -> 409
    $this->postJson("/api/v1/admin/rounds/{$fx['round1']->id}/finalize")
        ->assertStatus(409);

    // Lock remaining sheet
    Score::where('contestant_id', $fx['c1']->id)->update(['status' => 'LOCKED']);

    $this->postJson("/api/v1/admin/rounds/{$fx['round1']->id}/finalize")
        ->assertStatus(200);

    expect($fx['round1']->fresh()->status)->toBe('finalized')
        ->and($fx['round2']->contestants()->pluck('contestants.id')->all())->toBe([$fx['c1']->id]);
});
