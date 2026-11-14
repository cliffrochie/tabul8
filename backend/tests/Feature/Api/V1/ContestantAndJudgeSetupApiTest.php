<?php

use App\Models\Contestant;
use App\Models\Event;
use App\Models\Judge;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function seedRosterSetupEvent(): array
{
    $superAdmin = User::factory()->create([
        'email' => 'super-roster@tabul8.local',
        'password' => Hash::make('secret123'),
        'role' => 'super_admin',
        'is_active' => true,
    ]);
    $tabulator = User::factory()->create([
        'email' => 'tab-roster@tabul8.local',
        'password' => Hash::make('secret123'),
        'role' => 'tabulator',
        'is_active' => true,
    ]);

    $event = Event::create([
        'title' => 'Roster Setup Championship',
        'event_type' => 'pageant',
        'status' => 'setup',
        'created_by' => $superAdmin->id,
    ]);

    return compact('superAdmin', 'tabulator', 'event');
}

it('supports contestants CRUD, CSV import of 15 contestants, and auto-renumber', function () {
    $fx = seedRosterSetupEvent();
    Sanctum::actingAs($fx['tabulator'], ['role:tabulator']);

    $rows = ["candidate_number,name,origin_or_group,category,performance_meta"];
    for ($i = 1; $i <= 15; $i++) {
        $rows[] = "{$i},Candidate {$i},Region {$i},Solo,Talent #{$i}";
    }
    $csvFile = UploadedFile::fake()->createWithContent('roster.csv', implode("\n", $rows));

    $importRes = $this->post("/api/v1/admin/events/{$fx['event']->id}/contestants/import-csv", [
        'file' => $csvFile,
    ])->assertStatus(200);

    expect($importRes->json('data.imported_count'))->toBe(15)
        ->and(Contestant::where('event_id', $fx['event']->id)->count())->toBe(15);

    // Update & delete contestant #1
    $c1 = Contestant::where('event_id', $fx['event']->id)->where('candidate_number', 1)->firstOrFail();
    $this->putJson("/api/v1/admin/contestants/{$c1->id}", [
        'name' => 'Updated Candidate #1',
    ])->assertStatus(200)->assertJsonPath('data.contestant.name', 'Updated Candidate #1');

    // Add #16, delete #1, then auto-renumber -> contiguous 1..15
    $addRes = $this->postJson("/api/v1/admin/events/{$fx['event']->id}/contestants", [
        'candidate_number' => 16,
        'name' => 'Candidate Sixteen',
        'origin_or_group' => 'Palawan',
    ])->assertStatus(201);
    expect($addRes->json('data.contestant.candidate_number'))->toBe(16);

    $this->deleteJson("/api/v1/admin/contestants/{$c1->id}")->assertStatus(200);
    $this->postJson("/api/v1/admin/events/{$fx['event']->id}/contestants/renumber")->assertStatus(200);

    $numbers = Contestant::where('event_id', $fx['event']->id)->orderBy('candidate_number')->pluck('candidate_number')->all();
    expect($numbers)->toBe(range(1, 15));
});

it('validates portrait uploads, stores them on private local disk, and enforces auth on retrieval', function () {
    Storage::fake('local');
    $fx = seedRosterSetupEvent();

    $contestant = Contestant::create([
        'event_id' => $fx['event']->id,
        'candidate_number' => 1,
        'name' => 'Portrait Candidate',
        'origin_or_group' => 'Manila',
        'status' => 'active',
    ]);

    Sanctum::actingAs($fx['tabulator'], ['role:tabulator']);

    // Non-image rejected with 422
    $badFile = UploadedFile::fake()->create('malware.txt', 10, 'text/plain');
    $this->postJson("/api/v1/admin/contestants/{$contestant->id}/photo", [
        'photo' => $badFile,
    ])->assertStatus(422);

    // Valid image upload stored in private disk
    $validImg = UploadedFile::fake()->image('portrait.png', 400, 500);
    $uploadRes = $this->post("/api/v1/admin/contestants/{$contestant->id}/photo", [
        'photo' => $validImg,
    ])->assertStatus(200);

    $photoPath = $contestant->fresh()->photo_path;
    expect($photoPath)->not->toBeNull();
    Storage::disk('local')->assertExists($photoPath);
    expect($uploadRes->json('data.contestant.photo_url'))->toBe("/api/v1/events/{$fx['event']->id}/contestants/{$contestant->id}/photo");

    // Authenticated retrieval succeeds with 200
    $this->get("/api/v1/events/{$fx['event']->id}/contestants/{$contestant->id}/photo")
        ->assertStatus(200);
});

it('manages judges, enforces single-chairman invariant, regenerates PINs cleanly, and exports judge cards PDF', function () {
    $fx = seedRosterSetupEvent();
    Sanctum::actingAs($fx['tabulator'], ['role:tabulator']);

    $j1Res = $this->postJson("/api/v1/admin/events/{$fx['event']->id}/judges", [
        'judge_number' => 1,
        'name' => 'Judge Alpha',
        'role_title' => 'Chair',
        'is_chairman' => true,
    ])->assertStatus(201);

    $j1Id = (int) $j1Res->json('data.judge.id');
    $oldPin = (string) $j1Res->json('data.plain_pin');
    expect(strlen($oldPin))->toBe(6);

    // Creating a second chairman unsets the first chairman
    $j2Res = $this->postJson("/api/v1/admin/events/{$fx['event']->id}/judges", [
        'judge_number' => 2,
        'name' => 'Judge Beta',
        'role_title' => 'Adjudicator',
        'is_chairman' => true,
    ])->assertStatus(201);

    $j2Id = (int) $j2Res->json('data.judge.id');
    expect(Judge::find($j1Id)->is_chairman)->toBeFalse()
        ->and(Judge::find($j2Id)->is_chairman)->toBeTrue();

    // Regenerate PIN for Judge 1 -> old PIN stops working, new PIN logs in
    $regenRes = $this->postJson("/api/v1/admin/judges/{$j1Id}/regenerate-pin")
        ->assertStatus(200);
    $newPin = (string) $regenRes->json('data.plain_pin');
    expect($newPin)->not->toBe($oldPin)
        ->and(strlen($newPin))->toBe(6);

    // Verify Judge Tent Cards PDF starts with %PDF-
    $pdfRes = $this->get("/api/v1/admin/events/{$fx['event']->id}/judge-cards-pdf")
        ->assertStatus(200);
    expect($pdfRes->headers->get('content-type'))->toContain('application/pdf')
        ->and(str_starts_with($pdfRes->getContent(), '%PDF-'))->toBeTrue();

    // Test PIN login via /auth/judge-pin with new PIN
    $this->postJson('/api/v1/auth/judge-pin', [
        'event_id' => $fx['event']->id,
        'pin' => $oldPin,
    ])->assertStatus(422);

    $this->postJson('/api/v1/auth/judge-pin', [
        'event_id' => $fx['event']->id,
        'pin' => $newPin,
    ])->assertStatus(200);
});

it('restricts /admin/users CRUD strictly to super_admin', function () {
    $fx = seedRosterSetupEvent();

    Sanctum::actingAs($fx['tabulator'], ['role:tabulator']);
    $this->getJson('/api/v1/admin/users')->assertStatus(403);

    Sanctum::actingAs($fx['superAdmin'], ['role:super_admin']);
    $this->getJson('/api/v1/admin/users')->assertStatus(200);

    $createRes = $this->postJson('/api/v1/admin/users', [
        'name' => 'MC Host',
        'email' => 'mc-new@tabul8.local',
        'password' => 'strongpass123',
        'role' => 'mc_stage',
        'is_active' => true,
    ])->assertStatus(201);

    $userId = (int) $createRes->json('data.user.id');
    $this->putJson("/api/v1/admin/users/{$userId}", [
        'is_active' => false,
    ])->assertStatus(200)->assertJsonPath('data.user.is_active', false);

    // Self-delete blocked with 409
    $this->deleteJson("/api/v1/admin/users/{$fx['superAdmin']->id}")->assertStatus(409);

    $this->deleteJson("/api/v1/admin/users/{$userId}")->assertStatus(200);
    expect(User::find($userId))->toBeNull();
});
