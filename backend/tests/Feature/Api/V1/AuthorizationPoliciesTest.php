<?php

use App\Models\Contestant;
use App\Models\Criterion;
use App\Models\Event;
use App\Models\Judge;
use App\Models\Round;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

function seedCrossEventScenario(): array
{
    $tabulator = User::factory()->create([
        'email' => 'policy-tabulator@tabul8.local',
        'password' => Hash::make('secret123'),
        'role' => 'tabulator',
        'is_active' => true,
    ]);

    $event1 = Event::create([
        'title' => 'Event One',
        'event_type' => 'pageant',
        'status' => 'live',
        'created_by' => $tabulator->id,
    ]);

    $event2 = Event::create([
        'title' => 'Event Two',
        'event_type' => 'singing',
        'status' => 'live',
        'created_by' => $tabulator->id,
    ]);

    $round2 = Round::create([
        'event_id' => $event2->id,
        'name' => 'Grand Finals',
        'sequence' => 1,
        'scoring_strategy' => 'weighted',
        'weight_in_overall' => 100,
        'is_back_to_zero' => true,
        'cutoff_mode' => 'top_n_advance',
        'advancement_count' => 1,
        'eliminate_count' => 0,
        'status' => 'active',
        'is_locked' => false,
    ]);
    $event2->update(['active_round_id' => $round2->id]);

    $criterion2 = Criterion::create([
        'round_id' => $round2->id,
        'name' => 'Vocals',
        'weight_percentage' => 100,
        'min_score' => 0,
        'max_score' => 100,
        'score_step' => 1,
        'sequence' => 1,
    ]);

    $contestant2 = Contestant::create([
        'event_id' => $event2->id,
        'candidate_number' => 1,
        'name' => 'Contestant Two',
        'origin_or_group' => 'Manila',
        'category' => 'Open',
        'status' => 'active',
    ]);

    $judge1 = Judge::create([
        'event_id' => $event1->id,
        'judge_number' => 1,
        'name' => 'Judge Event One',
        'access_pin_hash' => Hash::make('111111'),
        'plain_pin_reference' => '111111',
        'is_active' => true,
    ]);

    return compact('tabulator', 'event1', 'event2', 'round2', 'criterion2', 'contestant2', 'judge1');
}

it('blocks cross-event judge replay on state hydration and score mutations while keeping public LED display open', function () {
    $data = seedCrossEventScenario();

    $this->getJson("/api/v1/display/events/{$data['event2']->id}/state")
        ->assertStatus(200)
        ->assertJsonPath('data.my_scores', null);

    Sanctum::actingAs($data['judge1'], ['role:judge']);

    $this->getJson("/api/v1/events/{$data['event1']->id}/state")
        ->assertStatus(200);

    $this->getJson("/api/v1/events/{$data['event2']->id}/state")
        ->assertStatus(403);

    $this->postJson('/api/v1/scores/upsert', [
        'round_id' => $data['round2']->id,
        'mutations' => [
            [
                'client_mutation_id' => 'cross-event-replay-1',
                'contestant_id' => $data['contestant2']->id,
                'action' => 'UPSERT_SCORE',
                'criterion_id' => $data['criterion2']->id,
                'raw_score' => 95,
                'client_timestamp' => now()->toIso8601String(),
            ],
        ],
    ])->assertStatus(403);

    $this->postJson('/api/v1/scores/lock', [
        'round_id' => $data['round2']->id,
        'lock_all' => true,
    ])->assertStatus(403);

    $this->postJson('/api/v1/scores/request-unlock', [
        'round_id' => $data['round2']->id,
        'contestant_id' => $data['contestant2']->id,
        'reason' => 'Cross-event attempt',
    ])->assertStatus(403);
});

it('blocks mc_stage from exporting official tally PDF and restricts auditor to read/export only', function () {
    $data = seedCrossEventScenario();
    $data['round2']->update([
        'status' => 'finalized',
        'is_locked' => true,
    ]);

    $mcUser = User::factory()->create([
        'email' => 'policy-mc@tabul8.local',
        'password' => Hash::make('secret123'),
        'role' => 'mc_stage',
        'is_active' => true,
    ]);

    $auditor = User::factory()->create([
        'email' => 'policy-auditor@tabul8.local',
        'password' => Hash::make('secret123'),
        'role' => 'auditor',
        'is_active' => true,
    ]);

    Sanctum::actingAs($mcUser, ['role:mc_stage']);
    $this->get("/api/v1/admin/rounds/{$data['round2']->id}/tally-pdf")
        ->assertStatus(403);

    Sanctum::actingAs($auditor, ['role:auditor']);
    $this->getJson("/api/v1/events/{$data['event2']->id}/state")
        ->assertStatus(200);

    $this->get("/api/v1/admin/rounds/{$data['round2']->id}/tally-pdf")
        ->assertStatus(200);

    $this->postJson('/api/v1/admin/deductions', [
        'round_id' => $data['round2']->id,
        'contestant_id' => $data['contestant2']->id,
        'points_deducted' => 1,
        'reason' => 'Unauthorized',
    ])->assertStatus(403);

    $this->postJson("/api/v1/admin/rounds/{$data['round2']->id}/finalize", [])
        ->assertStatus(403);
});
