<?php

use App\Enums\TaskStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;

function timeTrackingWorkspace(User $owner, array $members = []): Workspace
{
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => WorkspaceRole::Admin->value]);

    foreach ($members as $member) {
        $workspace->members()->attach($member['user']->id, ['role' => $member['role']->value]);
    }

    return $workspace;
}

function timeTrackingProject(Workspace $workspace, User $creator, array $members = []): Project
{
    $project = Project::factory()->create([
        'workspace_id' => $workspace->id,
        'created_by' => $creator->id,
    ]);
    $project->members()->attach($creator->id);

    foreach ($members as $member) {
        $project->members()->attach($member->id);
    }

    return $project;
}

it('starts and stops a timer, rejecting a second running timer with 409', function () {
    Carbon::setTestNow('2026-11-24 10:00:00');

    $admin = User::factory()->create(['name' => 'Owner']);
    $workspace = timeTrackingWorkspace($admin);
    $project = timeTrackingProject($workspace, $admin);
    $task = Task::factory()->create([
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'created_by' => $admin->id,
    ]);

    // Start timer
    $response = $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/tasks/{$task->id}/time/start", [
            'note' => 'Starting task work',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.task_id', $task->id)
        ->assertJsonPath('data.note', 'Starting task work')
        ->assertJsonPath('data.ended_at', null);

    // Attempt second timer -> 409 Conflict
    $secondResponse = $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/tasks/{$task->id}/time/start");

    $secondResponse->assertStatus(409);

    // Current timer check
    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/time/current')
        ->assertOk()
        ->assertJsonPath('data.task_id', $task->id);

    // Advance 30 minutes and stop timer
    Carbon::setTestNow('2026-11-24 10:30:00');

    $stopResponse = $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson('/api/v1/time/stop');

    $stopResponse->assertOk()
        ->assertJsonPath('data.duration_seconds', 1800);

    // Verify current timer is now null
    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/time/current')
        ->assertOk()
        ->assertJsonPath('data', null);
});

it('validates manual time entry bounds and sane maximum of 24h', function () {
    Carbon::setTestNow('2026-11-24 10:00:00');

    $admin = User::factory()->create();
    $workspace = timeTrackingWorkspace($admin);
    $project = timeTrackingProject($workspace, $admin);
    $task = Task::factory()->create([
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
    ]);

    // ended_at before or equal to started_at
    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson('/api/v1/time-entries', [
            'task_id' => $task->id,
            'started_at' => '2026-11-24 12:00:00',
            'ended_at' => '2026-11-24 10:00:00',
        ])
        ->assertUnprocessable();

    // exceeding 24h
    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson('/api/v1/time-entries', [
            'task_id' => $task->id,
            'started_at' => '2026-11-24 10:00:00',
            'ended_at' => '2026-11-25 11:00:00', // 25 hours
        ])
        ->assertUnprocessable();

    // Valid manual entry
    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson('/api/v1/time-entries', [
            'task_id' => $task->id,
            'started_at' => '2026-11-24 10:00:00',
            'ended_at' => '2026-11-24 12:00:00',
            'note' => '2 hours design review',
        ])
        ->assertCreated()
        ->assertJsonPath('data.duration_seconds', 7200);
});

it('enforces member vs admin permissions and visibility on time entries', function () {
    Carbon::setTestNow('2026-11-24 10:00:00');

    $admin = User::factory()->create();
    $member1 = User::factory()->create();
    $member2 = User::factory()->create();

    $workspace = timeTrackingWorkspace($admin, [
        ['user' => $member1, 'role' => WorkspaceRole::Member],
        ['user' => $member2, 'role' => WorkspaceRole::Member],
    ]);

    $visibleProject = timeTrackingProject($workspace, $admin, [$member1]);
    $hiddenProject = timeTrackingProject($workspace, $admin, [$member2]);

    $visibleTask = Task::factory()->create(['workspace_id' => $workspace->id, 'project_id' => $visibleProject->id]);
    $hiddenTask = Task::factory()->create(['workspace_id' => $workspace->id, 'project_id' => $hiddenProject->id]);

    // Member 1 cannot start timer on hidden project task
    $this->actingAs($member1, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/tasks/{$hiddenTask->id}/time/start")
        ->assertNotFound();

    // Member 1 creates manual entry on visible task
    $entry1 = TimeEntry::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $member1->id,
        'project_id' => $visibleProject->id,
        'task_id' => $visibleTask->id,
        'started_at' => '2026-11-24 08:00:00',
        'ended_at' => '2026-11-24 09:00:00',
        'duration_seconds' => 3600,
    ]);

    // Member 2 creates entry on hidden project task
    $entry2 = TimeEntry::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $member2->id,
        'project_id' => $hiddenProject->id,
        'task_id' => $hiddenTask->id,
        'started_at' => '2026-11-24 08:00:00',
        'ended_at' => '2026-11-24 09:00:00',
        'duration_seconds' => 3600,
    ]);

    // Member 1 listing entries: forced to own entries and visible projects only
    $listResponse = $this->actingAs($member1, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/time-entries?user_id={$member2->id}");

    $listResponse->assertOk()
        ->assertJsonCount(1, 'data.time_entries')
        ->assertJsonPath('data.time_entries.0.id', $entry1->id)
        ->assertJsonPath('meta.total_seconds', 3600);

    // Member 1 cannot edit or delete Member 2's entry
    $this->actingAs($member1, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->putJson("/api/v1/time-entries/{$entry2->id}", ['note' => 'Hacked note'])
        ->assertForbidden();

    $this->actingAs($member1, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->deleteJson("/api/v1/time-entries/{$entry2->id}")
        ->assertForbidden();

    // Admin can edit and delete any entry
    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->putJson("/api/v1/time-entries/{$entry2->id}", ['note' => 'Admin updated note'])
        ->assertOk()
        ->assertJsonPath('data.note', 'Admin updated note');

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->deleteJson("/api/v1/time-entries/{$entry2->id}")
        ->assertNoContent();
});

it('calculates profile stats with completed tasks and hours worked rounded to 1 decimal', function () {
    $user = User::factory()->create();
    $workspace = timeTrackingWorkspace($user);
    $project = timeTrackingProject($workspace, $user);

    $completedTask = Task::factory()->create([
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'status' => TaskStatus::Completed->value,
    ]);
    $completedTask->assignees()->attach($user->id);

    $todoTask = Task::factory()->create([
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'status' => TaskStatus::Todo->value,
    ]);
    $todoTask->assignees()->attach($user->id);

    // 5400 seconds = 1.5 hours
    TimeEntry::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'project_id' => $project->id,
        'task_id' => $completedTask->id,
        'duration_seconds' => 5400,
    ]);

    $this->actingAs($user, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/profile/stats')
        ->assertOk()
        ->assertJsonPath('data.completed_tasks', 1)
        ->assertJsonPath('data.hours_worked', 1.5);
});
