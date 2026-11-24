<?php

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\DomainNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->user = User::factory()->create();
    $this->workspace->members()->attach($this->user->id, ['role' => 'member']);
});

it('sends meeting reminder command for meetings starting within 15 minutes', function () {
    $now = CarbonImmutable::now();
    $participant = User::factory()->create();
    $this->workspace->members()->attach($participant->id, ['role' => 'member']);

    $meeting = Meeting::create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'title' => 'Urgent Sync',
        'starts_at' => $now->addMinutes(5)->format('Y-m-d H:i:s'),
        'ends_at' => $now->addMinutes(35)->format('Y-m-d H:i:s'),
        'status' => MeetingStatus::Scheduled,
    ]);
    $meeting->participants()->attach($this->user->id, ['response' => 'accepted']);
    $meeting->participants()->attach($participant->id, ['response' => 'accepted']);

    Artisan::call('meetings:send-reminders');

    expect($meeting->fresh()->reminder_sent_at)->not->toBeNull();

    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $participant->id,
        'type' => DomainNotification::class,
    ]);
});

it('sends daily status reminder command to members missing status for today', function () {
    Artisan::call('daily-status:remind');

    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $this->user->id,
        'type' => DomainNotification::class,
    ]);
});
