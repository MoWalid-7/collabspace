<?php

use App\Enums\MeetingParticipantResponse;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->admin = User::factory()->create();
    $this->workspace->members()->attach($this->admin->id, ['role' => 'admin']);

    $this->member = User::factory()->create();
    $this->workspace->members()->attach($this->member->id, ['role' => 'member']);

    $this->otherMember = User::factory()->create();
    $this->workspace->members()->attach($this->otherMember->id, ['role' => 'member']);
});

it('allows workspace member to create a meeting', function () {
    $startsAt = CarbonImmutable::now()->addDay();
    $endsAt = $startsAt->addHour();

    $response = $this->actingAs($this->member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
        ->postJson('/api/v1/meetings', [
            'title' => 'Sprint Planning',
            'description' => 'Planning for next sprint',
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
            'join_url' => 'https://meet.google.com/abc-defg-hij',
            'participant_ids' => [$this->otherMember->id],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Sprint Planning')
        ->assertJsonPath('data.status', MeetingStatus::Scheduled->value);

    $this->assertDatabaseHas('meetings', [
        'workspace_id' => $this->workspace->id,
        'title' => 'Sprint Planning',
        'created_by' => $this->member->id,
    ]);

    $meetingId = $response->json('data.id');
    $this->assertDatabaseHas('meeting_participants', [
        'meeting_id' => $meetingId,
        'user_id' => $this->member->id,
        'response' => MeetingParticipantResponse::Accepted->value,
    ]);
    $this->assertDatabaseHas('meeting_participants', [
        'meeting_id' => $meetingId,
        'user_id' => $this->otherMember->id,
        'response' => MeetingParticipantResponse::Pending->value,
    ]);
});

it('rejects meeting creation if ends_at is before starts_at', function () {
    $startsAt = CarbonImmutable::now()->addDay();
    $endsAt = $startsAt->subHour();

    $this->actingAs($this->member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
        ->postJson('/api/v1/meetings', [
            'title' => 'Invalid Meeting',
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
        ])
        ->assertStatus(422);
});

it('rejects meeting creation if participant is not in the workspace', function () {
    $outsider = User::factory()->create();

    $this->actingAs($this->member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
        ->postJson('/api/v1/meetings', [
            'title' => 'Meeting with outsider',
            'starts_at' => CarbonImmutable::now()->addDay()->toIso8601String(),
            'ends_at' => CarbonImmutable::now()->addDay()->addHour()->toIso8601String(),
            'participant_ids' => [$outsider->id],
        ])
        ->assertStatus(422);
});

it('allows participants to respond to meeting', function () {
    $startsAt = CarbonImmutable::now()->addDay();
    $endsAt = $startsAt->addHour();

    $meeting = Meeting::create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->member->id,
        'title' => 'Team Sync',
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'status' => MeetingStatus::Scheduled,
    ]);
    $meeting->participants()->attach($this->member->id, ['response' => 'accepted']);
    $meeting->participants()->attach($this->otherMember->id, ['response' => 'pending']);

    $this->actingAs($this->otherMember, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
        ->postJson("/api/v1/meetings/{$meeting->id}/respond", [
            'response' => MeetingParticipantResponse::Accepted->value,
        ])
        ->assertOk();

    $this->assertDatabaseHas('meeting_participants', [
        'meeting_id' => $meeting->id,
        'user_id' => $this->otherMember->id,
        'response' => MeetingParticipantResponse::Accepted->value,
    ]);
});

it('allows participant to get join url', function () {
    $meeting = Meeting::create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->member->id,
        'title' => 'Sync',
        'starts_at' => CarbonImmutable::now()->addDay(),
        'ends_at' => CarbonImmutable::now()->addDay()->addHour(),
        'join_url' => 'https://zoom.us/j/123456789',
        'status' => MeetingStatus::Scheduled,
    ]);
    $meeting->participants()->attach($this->member->id, ['response' => 'accepted']);

    $this->actingAs($this->member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
        ->postJson("/api/v1/meetings/{$meeting->id}/join")
        ->assertOk()
        ->assertJsonPath('data.join_url', 'https://zoom.us/j/123456789');
});

it('returns 422 if meeting has no join url when joining', function () {
    $meeting = Meeting::create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->member->id,
        'title' => 'Physical Sync',
        'starts_at' => CarbonImmutable::now()->addDay(),
        'ends_at' => CarbonImmutable::now()->addDay()->addHour(),
        'join_url' => null,
        'status' => MeetingStatus::Scheduled,
    ]);
    $meeting->participants()->attach($this->member->id, ['response' => 'accepted']);

    $this->actingAs($this->member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
        ->postJson("/api/v1/meetings/{$meeting->id}/join")
        ->assertStatus(422);
});

it('allows creator or admin to cancel meeting', function () {
    $meeting = Meeting::create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->member->id,
        'title' => 'Meeting to cancel',
        'starts_at' => CarbonImmutable::now()->addDay(),
        'ends_at' => CarbonImmutable::now()->addDay()->addHour(),
        'status' => MeetingStatus::Scheduled,
    ]);
    $meeting->participants()->attach($this->member->id, ['response' => 'accepted']);

    $this->actingAs($this->member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
        ->postJson("/api/v1/meetings/{$meeting->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', MeetingStatus::Cancelled->value);

    expect($meeting->fresh()->status)->toBe(MeetingStatus::Cancelled);
});

it('prevents non-creator non-admin from cancelling or deleting meeting', function () {
    $meeting = Meeting::create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->member->id,
        'title' => 'Protected Meeting',
        'starts_at' => CarbonImmutable::now()->addDay(),
        'ends_at' => CarbonImmutable::now()->addDay()->addHour(),
        'status' => MeetingStatus::Scheduled,
    ]);
    $meeting->participants()->attach($this->member->id, ['response' => 'accepted']);
    $meeting->participants()->attach($this->otherMember->id, ['response' => 'pending']);

    $this->actingAs($this->otherMember, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
        ->postJson("/api/v1/meetings/{$meeting->id}/cancel")
        ->assertStatus(403);
});

it('supports meeting notes management', function () {
    $meeting = Meeting::create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->member->id,
        'title' => 'Meeting with notes',
        'starts_at' => CarbonImmutable::now()->addDay(),
        'ends_at' => CarbonImmutable::now()->addDay()->addHour(),
        'status' => MeetingStatus::Scheduled,
    ]);
    $meeting->participants()->attach($this->member->id, ['response' => 'accepted']);

    // Create note
    $createResponse = $this->actingAs($this->member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
        ->postJson("/api/v1/meetings/{$meeting->id}/notes", [
            'body' => 'Important key takeaway.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Important key takeaway.');

    $noteId = $createResponse->json('data.id');

    // List notes
    $this->actingAs($this->member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
        ->getJson("/api/v1/meetings/{$meeting->id}/notes")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    // Update note
    $this->actingAs($this->member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
        ->putJson("/api/v1/meetings/{$meeting->id}/notes/{$noteId}", [
            'body' => 'Updated key takeaway.',
        ])
        ->assertOk()
        ->assertJsonPath('data.body', 'Updated key takeaway.');

    // Delete note
    $this->actingAs($this->member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
        ->deleteJson("/api/v1/meetings/{$meeting->id}/notes/{$noteId}")
        ->assertNoContent();
});

it('supports polymorphic comments on meetings', function () {
    $meeting = Meeting::create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->member->id,
        'title' => 'Meeting with comments',
        'starts_at' => CarbonImmutable::now()->addDay(),
        'ends_at' => CarbonImmutable::now()->addDay()->addHour(),
        'status' => MeetingStatus::Scheduled,
    ]);
    $meeting->participants()->attach($this->member->id, ['response' => 'accepted']);

    $this->actingAs($this->member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
        ->postJson("/api/v1/meetings/{$meeting->id}/comments", [
            'body' => 'Looking forward to this!',
        ])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Looking forward to this!');

    $this->actingAs($this->member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
        ->getJson("/api/v1/meetings/{$meeting->id}/comments")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('fetches upcoming meetings for dashboard', function () {
    $futureMeeting = Meeting::create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->member->id,
        'title' => 'Future Meeting',
        'starts_at' => CarbonImmutable::now()->addHours(2),
        'ends_at' => CarbonImmutable::now()->addHours(3),
        'status' => MeetingStatus::Scheduled,
    ]);
    $futureMeeting->participants()->attach($this->member->id, ['response' => 'accepted']);

    $cancelledMeeting = Meeting::create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->member->id,
        'title' => 'Cancelled Future Meeting',
        'starts_at' => CarbonImmutable::now()->addHours(4),
        'ends_at' => CarbonImmutable::now()->addHours(5),
        'status' => MeetingStatus::Cancelled,
    ]);
    $cancelledMeeting->participants()->attach($this->member->id, ['response' => 'accepted']);

    $response = $this->actingAs($this->member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $this->workspace->id)
        ->getJson('/api/v1/dashboard/upcoming-meetings?limit=5');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $futureMeeting->id);
});
