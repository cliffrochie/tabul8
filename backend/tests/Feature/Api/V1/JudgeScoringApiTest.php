<?php

use App\Models\Contestant;
use App\Models\Criterion;
use App\Models\Event;
use App\Models\Judge;
use App\Models\Round;
use App\Models\Score;
use App\Models\ScoreAuditLog;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

function seedLiveJudgeEvent(): array
{
    $event = Event::create([
        'title' => 'Miss Grand Universe 2026',
        'event_type' => 'pageant',
        'status' => 'live',
    ]);

    $round = Round::create([
        'event_id' => $event->id,
        'name' => 'Round 1: Swimwear',
        'sequence' => 1,
        'scoring_strategy' => 'ordinal',
        'advancement_count' => 3,
        'status' => 'active',
    ]);

    $event->update(['active_round_id' => $round->id]);

    $criterion = Criterion::create([
        'round_id' => $round->id,
        'name' => 'Poise & Bearing',
        'weight_percentage' => 100.00,
        'min_score' => 70.00,
        'max_score' => 100.00,
        'score_step' => 0.50,
        'sequence' => 1,
    ]);

    $judge = Judge::create([
        'event_id' => $event->id,
        'judge_number' => 1,
        'name' => 'Hon. Victoria Sterling',
        'access_pin_hash' => Hash::make('104928'),
        'plain_pin_reference' => '104928',
        'is_chairman' => true,
        'is_active' => true,
    ]);

    $contestant = Contestant::create([
        'event_id' => $event->id,
        'candidate_number' => 1,
        'name' => 'Ariana Vance',
        'origin_or_group' => 'Metro Manila',
        'status' => 'active',
    ]);

    return compact('event', 'round', 'criterion', 'judge', 'contestant');
}

it('authenticates judge via 6-digit PIN and returns standard spec-vault envelope', function () {
    $fx = seedLiveJudgeEvent();

    $response = $this->postJson('/api/v1/auth/judge-pin', [
        'event_id' => $fx['event']->id,
        'pin' => '104928',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => ['token', 'actor_type', 'judge' => ['id', 'judge_number', 'name']],
            'message',
            'errors',
        ])
        ->assertJsonPath('errors', null)
        ->assertJsonPath('data.actor_type', 'judge');
});

it('returns 422 with errors.pin on invalid judge PIN', function () {
    $fx = seedLiveJudgeEvent();

    $response = $this->postJson('/api/v1/auth/judge-pin', [
        'event_id' => $fx['event']->id,
        'pin' => '999999',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('data', null)
        ->assertJsonStructure(['data', 'message', 'errors' => ['pin']]);
});

it('returns 401 in standard envelope when unauthenticated', function () {
    $fx = seedLiveJudgeEvent();

    $this->getJson("/api/v1/events/{$fx['event']->id}/state")
        ->assertStatus(401)
        ->assertJsonPath('data', null)
        ->assertJsonPath('message', 'Unauthenticated.');
});

it('processes offline batch upsert, idempotency, lock, and unlock request', function () {
    $fx = seedLiveJudgeEvent();
    Sanctum::actingAs($fx['judge'], ['role:judge']);

    // 1. Batch upsert + lock
    $upsertRes = $this->postJson('/api/v1/scores/upsert', [
        'round_id' => $fx['round']->id,
        'mutations' => [
            [
                'client_mutation_id' => 'mut-score-1',
                'contestant_id' => $fx['contestant']->id,
                'criterion_id' => $fx['criterion']->id,
                'raw_score' => 94.5,
                'action' => 'UPSERT_SCORE',
                'client_timestamp' => '2026-11-13T09:30:00+08:00',
            ],
            [
                'client_mutation_id' => 'mut-lock-1',
                'contestant_id' => $fx['contestant']->id,
                'action' => 'LOCK_SHEET',
                'client_timestamp' => '2026-11-13T09:30:05+08:00',
            ],
        ],
    ]);

    $upsertRes->assertStatus(200)
        ->assertJsonPath('data.results.0.status', 'APPLIED')
        ->assertJsonPath('data.results.1.status', 'APPLIED')
        ->assertJsonPath("data.my_scores.{$fx['contestant']->id}.status", 'LOCKED');

    // 2. Duplicate replay returns DUPLICATE_IGNORED; new mutation on locked sheet returns REJECTED_LOCKED
    $replayRes = $this->postJson('/api/v1/scores/upsert', [
        'round_id' => $fx['round']->id,
        'mutations' => [
            [
                'client_mutation_id' => 'mut-score-1',
                'contestant_id' => $fx['contestant']->id,
                'criterion_id' => $fx['criterion']->id,
                'raw_score' => 94.5,
                'action' => 'UPSERT_SCORE',
                'client_timestamp' => '2026-11-13T09:30:00+08:00',
            ],
            [
                'client_mutation_id' => 'mut-score-2',
                'contestant_id' => $fx['contestant']->id,
                'criterion_id' => $fx['criterion']->id,
                'raw_score' => 88.0,
                'action' => 'UPSERT_SCORE',
                'client_timestamp' => '2026-11-13T09:30:10+08:00',
            ],
        ],
    ]);

    $replayRes->assertStatus(200)
        ->assertJsonPath('data.results.0.status', 'DUPLICATE_IGNORED')
        ->assertJsonPath('data.results.1.status', 'REJECTED_LOCKED');

    // 3. Request unlock transitions to UNLOCK_REQUESTED and logs UNLOCK_REQ
    $unlockRes = $this->postJson('/api/v1/scores/request-unlock', [
        'round_id' => $fx['round']->id,
        'contestant_id' => $fx['contestant']->id,
        'reason' => 'Typo fix',
    ]);

    $unlockRes->assertStatus(200)
        ->assertJsonPath("data.my_scores.{$fx['contestant']->id}.status", 'UNLOCK_REQUESTED');

    expect(ScoreAuditLog::where('action', 'UNLOCK_REQ')->count())->toBe(1)
        ->and((float) Score::first()->raw_score)->toBe(94.5);
});

it('rejects score mutations with 409 when round is frozen', function () {
    $fx = seedLiveJudgeEvent();
    $fx['round']->update(['is_locked' => true, 'status' => 'locked']);
    Sanctum::actingAs($fx['judge'], ['role:judge']);

    $this->postJson('/api/v1/scores/upsert', [
        'round_id' => $fx['round']->id,
        'mutations' => [
            [
                'client_mutation_id' => 'mut-frozen-1',
                'contestant_id' => $fx['contestant']->id,
                'criterion_id' => $fx['criterion']->id,
                'raw_score' => 90.0,
                'action' => 'UPSERT_SCORE',
                'client_timestamp' => '2026-11-13T09:30:00+08:00',
            ],
        ],
    ])->assertStatus(409)->assertJsonPath('data', null);
});
