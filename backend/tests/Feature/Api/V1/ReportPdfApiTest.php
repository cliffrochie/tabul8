<?php

use App\Models\Contestant;
use App\Models\Criterion;
use App\Models\Event;
use App\Models\Judge;
use App\Models\Round;
use App\Models\Score;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;

uses(RefreshDatabase::class);

test('tabulator can export A4 landscape official tally PDF', function (): void {
    $admin = User::factory()->create(['role' => 'tabulator']);
    $event = Event::create([
        'title' => 'Grand Finals 2026',
        'subtitle' => 'City Arena',
        'event_type' => 'pageant',
        'status' => 'live',
        'led_mode' => 'on_stage',
        'revealed_contestant_ids' => [],
        'settings' => [],
    ]);
    $round = Round::create([
        'event_id' => $event->id,
        'name' => 'Evening Gown',
        'sequence' => 1,
        'scoring_strategy' => 'weighted',
        'weight_in_overall' => 100,
        'is_back_to_zero' => true,
        'advancement_count' => 1,
        'status' => 'active',
        'is_locked' => false,
    ]);
    $event->update(['active_round_id' => $round->id]);

    $criterion = Criterion::create([
        'round_id' => $round->id,
        'name' => 'Poise',
        'weight_percentage' => 100,
        'min_score' => 0,
        'max_score' => 100,
        'score_step' => 0.5,
        'sequence' => 1,
    ]);
    $judge = Judge::create([
        'event_id' => $event->id,
        'judge_number' => 1,
        'name' => 'Judge One',
        'role_title' => 'Chair',
        'access_pin_hash' => bcrypt('123456'),
        'plain_pin_reference' => '123456',
        'is_chairman' => true,
        'is_active' => true,
    ]);
    $contestant = Contestant::create([
        'event_id' => $event->id,
        'candidate_number' => 1,
        'name' => 'Ariana Vance',
        'origin_or_group' => 'Metro Manila',
        'category' => 'Senior',
        'asset_ratio' => 'PORTRAIT_4:5',
        'status' => 'active',
    ]);
    Score::create([
        'round_id' => $round->id,
        'contestant_id' => $contestant->id,
        'judge_id' => $judge->id,
        'criterion_id' => $criterion->id,
        'raw_score' => 94.5,
        'weighted_score' => 94.5,
        'status' => 'LOCKED',
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->get("/api/v1/admin/rounds/{$round->id}/tally-pdf");

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect($response->getContent())->toStartWith('%PDF-');
});

test('judge token cannot export official tally PDF', function (): void {
    $event = Event::create([
        'title' => 'Grand Finals 2026',
        'event_type' => 'pageant',
        'status' => 'live',
        'led_mode' => 'on_stage',
        'revealed_contestant_ids' => [],
        'settings' => [],
    ]);
    $round = Round::create([
        'event_id' => $event->id,
        'name' => 'Evening Gown',
        'sequence' => 1,
        'scoring_strategy' => 'weighted',
        'weight_in_overall' => 100,
        'is_back_to_zero' => true,
        'advancement_count' => 1,
        'status' => 'active',
        'is_locked' => false,
    ]);
    $judge = Judge::create([
        'event_id' => $event->id,
        'judge_number' => 1,
        'name' => 'Judge One',
        'role_title' => 'Panelist',
        'access_pin_hash' => bcrypt('123456'),
        'plain_pin_reference' => '123456',
        'is_chairman' => false,
        'is_active' => true,
    ]);

    $this->actingAs($judge, 'sanctum')
        ->getJson("/api/v1/admin/rounds/{$round->id}/tally-pdf")
        ->assertForbidden();
});

test('incomplete quorum returns 409 unless ?preview=1 and sets DRAFT PREVIEW watermark', function (): void {
    $admin = User::factory()->create(['role' => 'tabulator']);
    $event = Event::create([
        'title' => 'Pageant Preview Test',
        'event_type' => 'pageant',
        'status' => 'live',
        'led_mode' => 'on_stage',
        'revealed_contestant_ids' => [],
        'settings' => [],
    ]);
    $round = Round::create([
        'event_id' => $event->id,
        'name' => 'Semi Finals',
        'sequence' => 2,
        'scoring_strategy' => 'weighted',
        'weight_in_overall' => 50,
        'is_back_to_zero' => false,
        'advancement_count' => 1,
        'status' => 'active',
        'is_locked' => false,
    ]);
    $event->update(['active_round_id' => $round->id]);

    $criterion = Criterion::create([
        'round_id' => $round->id,
        'name' => 'Stage Presence',
        'weight_percentage' => 100,
        'min_score' => 0,
        'max_score' => 100,
        'score_step' => 1,
        'sequence' => 1,
    ]);
    $judge = Judge::create([
        'event_id' => $event->id,
        'judge_number' => 1,
        'name' => 'Judge Alpha',
        'access_pin_hash' => bcrypt('123456'),
        'plain_pin_reference' => '123456',
        'is_active' => true,
    ]);
    $contestant = Contestant::create([
        'event_id' => $event->id,
        'candidate_number' => 1,
        'name' => 'Candidate Beta',
        'origin_or_group' => 'Cebu',
        'category' => 'Open',
        'status' => 'active',
    ]);
    $round->contestants()->attach($contestant->id, [
        'qualification_method' => 'AUTO_TOP_N',
        'qualified_from_rank' => 1,
    ]);
    Score::create([
        'round_id' => $round->id,
        'contestant_id' => $contestant->id,
        'judge_id' => $judge->id,
        'criterion_id' => $criterion->id,
        'raw_score' => 88,
        'weighted_score' => 88,
        'status' => 'DRAFT',
    ]);

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/v1/admin/rounds/{$round->id}/tally-pdf")
        ->assertStatus(409)
        ->assertJsonStructure(['errors' => ['quorum']]);

    $capturedData = [];
    View::composer('pdf.official-tally', function ($view) use (&$capturedData): void {
        $capturedData = $view->getData();
    });

    $previewResponse = $this->actingAs($admin, 'sanctum')
        ->get("/api/v1/admin/rounds/{$round->id}/tally-pdf?preview=1")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect($previewResponse->getContent())->toStartWith('%PDF-');
    expect($capturedData['watermarkText'] ?? null)->toBe('DRAFT PREVIEW');
    expect($capturedData['verificationHash'] ?? '')->toMatch('/^[a-f0-9]{64}$/');
});

test('finalized round tally PDF includes 64-char SHA-256 verificationHash and FINAL & OFFICIAL watermark', function (): void {
    $auditor = User::factory()->create(['role' => 'auditor']);
    $event = Event::create([
        'title' => 'Championship Finals',
        'event_type' => 'pageant',
        'status' => 'live',
        'led_mode' => 'on_stage',
        'revealed_contestant_ids' => [],
        'settings' => [],
    ]);
    $round = Round::create([
        'event_id' => $event->id,
        'name' => 'Final Segment',
        'sequence' => 2,
        'scoring_strategy' => 'weighted',
        'weight_in_overall' => 50,
        'is_back_to_zero' => false,
        'advancement_count' => 1,
        'status' => 'finalized',
        'is_locked' => true,
    ]);
    $criterion = Criterion::create([
        'round_id' => $round->id,
        'name' => 'Q&A',
        'weight_percentage' => 100,
        'min_score' => 0,
        'max_score' => 100,
        'score_step' => 1,
        'sequence' => 1,
    ]);
    $judge = Judge::create([
        'event_id' => $event->id,
        'judge_number' => 1,
        'name' => 'Judge Omega',
        'access_pin_hash' => bcrypt('654321'),
        'plain_pin_reference' => '654321',
        'is_active' => true,
    ]);
    $contestant = Contestant::create([
        'event_id' => $event->id,
        'candidate_number' => 1,
        'name' => 'Candidate One',
        'origin_or_group' => 'Davao',
        'category' => 'Open',
        'status' => 'active',
    ]);
    $round->contestants()->attach($contestant->id, [
        'qualification_method' => 'AUTO_TOP_N',
        'qualified_from_rank' => 1,
    ]);
    Score::create([
        'round_id' => $round->id,
        'contestant_id' => $contestant->id,
        'judge_id' => $judge->id,
        'criterion_id' => $criterion->id,
        'raw_score' => 97,
        'weighted_score' => 97,
        'status' => 'LOCKED',
    ]);

    $capturedData = [];
    View::composer('pdf.official-tally', function ($view) use (&$capturedData): void {
        $capturedData = $view->getData();
    });

    $response = $this->actingAs($auditor, 'sanctum')
        ->get("/api/v1/admin/rounds/{$round->id}/tally-pdf")
        ->assertOk();

    expect($response->getContent())->toStartWith('%PDF-');
    expect($capturedData['watermarkText'] ?? null)->toBe('FINAL & OFFICIAL');
    expect($capturedData['verificationHash'] ?? '')->toMatch('/^[a-f0-9]{64}$/');
});
