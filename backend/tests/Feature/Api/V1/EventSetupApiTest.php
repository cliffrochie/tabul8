<?php

use App\Models\Contestant;
use App\Models\Criterion;
use App\Models\Event;
use App\Models\Judge;
use App\Models\Round;
use App\Models\User;
use Database\Seeders\CompetitionDemoSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

function createTabulatorUser(string $role = 'tabulator'): User
{
    return User::factory()->create([
        'email' => "{$role}-setup@tabul8.local",
        'password' => Hash::make('secret123'),
        'role' => $role,
        'is_active' => true,
    ]);
}

it('lists paginated events via ApiCollection and blocks judges with 403', function () {
    $admin = createTabulatorUser('tabulator');
    $event = Event::create([
        'title' => 'Binibining Tabul8',
        'event_type' => 'pageant',
        'status' => 'setup',
        'created_by' => $admin->id,
    ]);

    $judge = Judge::create([
        'event_id' => $event->id,
        'judge_number' => 1,
        'name' => 'Judge One',
        'access_pin_hash' => Hash::make('123456'),
        'plain_pin_reference' => '123456',
        'is_active' => true,
    ]);

    Sanctum::actingAs($judge, ['role:judge']);
    $this->getJson('/api/v1/admin/events')->assertStatus(403);

    Sanctum::actingAs($admin, ['role:tabulator']);
    $this->getJson('/api/v1/admin/events')
        ->assertStatus(200)
        ->assertJsonStructure([
            'data' => [['id', 'title', 'event_type', 'status']],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            'message',
            'errors',
        ])
        ->assertJsonPath('meta.total', 1);
});

it('creates an event from pageant blueprint and clones from an existing event', function () {
    $admin = createTabulatorUser('super_admin');
    Sanctum::actingAs($admin, ['role:super_admin']);

    $res = $this->postJson('/api/v1/admin/events', [
        'title' => 'Miss Luzon 2026',
        'subtitle' => 'Regional Finals',
        'event_type' => 'pageant',
        'preset' => 'pageant',
    ])->assertStatus(201);

    $eventId = (int) $res->json('data.event.id');
    expect($res->json('data.event.status'))->toBe('setup');

    $rounds = Round::where('event_id', $eventId)->orderBy('sequence')->get();
    expect($rounds)->toHaveCount(3)
        ->and($rounds->every(fn (Round $r) => abs((float) $r->criteria()->whereNull('parent_id')->sum('weight_percentage') - 100.0) < 0.01))->toBeTrue();

    // Clone from created event
    $cloneRes = $this->postJson('/api/v1/admin/events', [
        'title' => 'Miss Visayas 2026',
        'event_type' => 'pageant',
        'clone_from_event_id' => $eventId,
    ])->assertStatus(201);

    $cloneId = (int) $cloneRes->json('data.event.id');
    expect(Round::where('event_id', $cloneId)->count())->toBe(3);
});

it('forbids deleting live events with 409 and allows deleting setup events', function () {
    $admin = createTabulatorUser();
    Sanctum::actingAs($admin, ['role:tabulator']);

    $live = Event::create([
        'title' => 'Live Event',
        'event_type' => 'singing',
        'status' => 'live',
        'created_by' => $admin->id,
    ]);
    $setup = Event::create([
        'title' => 'Draft Event',
        'event_type' => 'dance',
        'status' => 'setup',
        'created_by' => $admin->id,
    ]);

    $this->deleteJson("/api/v1/admin/events/{$live->id}")->assertStatus(409);
    $this->deleteJson("/api/v1/admin/events/{$setup->id}")->assertStatus(200);
    expect(Event::find($setup->id))->toBeNull();
});

it('evaluates preflight checks and blocks going live until satisfied', function () {
    $admin = createTabulatorUser();
    Sanctum::actingAs($admin, ['role:tabulator']);

    $event = Event::create([
        'title' => 'Preflight Check Event',
        'event_type' => 'custom',
        'status' => 'setup',
        'created_by' => $admin->id,
    ]);
    $round = Round::create([
        'event_id' => $event->id,
        'name' => 'Round 1',
        'sequence' => 1,
        'scoring_strategy' => 'weighted',
        'weight_in_overall' => 100.00,
        'is_back_to_zero' => true,
        'advancement_count' => 1,
        'status' => 'upcoming',
    ]);
    Criterion::create([
        'round_id' => $round->id,
        'name' => 'Incomplete Weight',
        'weight_percentage' => 60.00,
        'min_score' => 70,
        'max_score' => 100,
        'score_step' => 0.5,
        'sequence' => 1,
    ]);

    $this->getJson("/api/v1/admin/events/{$event->id}/preflight")
        ->assertStatus(200)
        ->assertJsonPath('data.ready', false);

    // Blocking going live
    $this->putJson("/api/v1/admin/events/{$event->id}", ['status' => 'live'])
        ->assertStatus(422);

    // Fix preflight requirements: 100% weights, >= 1 judge, >= 2 contestants
    Criterion::create([
        'round_id' => $round->id,
        'name' => 'Completion Weight',
        'weight_percentage' => 40.00,
        'min_score' => 70,
        'max_score' => 100,
        'score_step' => 0.5,
        'sequence' => 2,
    ]);
    Judge::create([
        'event_id' => $event->id,
        'judge_number' => 1,
        'name' => 'Judge 1',
        'access_pin_hash' => Hash::make('111222'),
        'plain_pin_reference' => '111222',
        'is_active' => true,
    ]);
    foreach ([1, 2] as $num) {
        Contestant::create([
            'event_id' => $event->id,
            'candidate_number' => $num,
            'name' => "Candidate {$num}",
            'origin_or_group' => 'Metro',
            'status' => 'active',
        ]);
    }

    $this->getJson("/api/v1/admin/events/{$event->id}/preflight")
        ->assertStatus(200)
        ->assertJsonPath('data.ready', true);

    $this->putJson("/api/v1/admin/events/{$event->id}", ['status' => 'live'])
        ->assertStatus(200)
        ->assertJsonPath('data.event.status', 'live')
        ->assertJsonPath('data.event.active_round_id', $round->id);
});

it('runs CompetitionDemoSeeder with 15 contestants, 3 elimination rounds, 5 judges, and passing preflight', function () {
    $this->seed(CompetitionDemoSeeder::class);

    $event = Event::first();
    expect($event)->not->toBeNull()
        ->and($event->contestants()->count())->toBe(15)
        ->and($event->rounds()->count())->toBe(3)
        ->and($event->judges()->count())->toBe(5)
        ->and(User::where('role', 'super_admin')->exists())->toBeTrue()
        ->and(User::where('role', 'tabulator')->exists())->toBeTrue()
        ->and(User::where('role', 'mc_stage')->exists())->toBeTrue();

    $tabulator = User::where('role', 'tabulator')->firstOrFail();
    Sanctum::actingAs($tabulator, ['role:tabulator']);

    $this->getJson("/api/v1/admin/events/{$event->id}/preflight")
        ->assertStatus(200)
        ->assertJsonPath('data.ready', true);
});
