<?php

use App\Enums\ReportStatus;
use App\Enums\TaskStatus;
use App\Enums\WorkspaceRole;
use App\Jobs\GenerateReportJob;
use App\Models\Project;
use App\Models\Report;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function reportsWorkspace(User $owner, array $members = []): Workspace
{
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => WorkspaceRole::Admin->value]);

    foreach ($members as $member) {
        $workspace->members()->attach($member['user']->id, ['role' => $member['role']->value]);
    }

    return $workspace;
}

function reportsProject(Workspace $workspace, User $creator, array $members = []): Project
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

it('denies members access to reports endpoints with 403', function () {
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $workspace = reportsWorkspace($admin, [
        ['user' => $member, 'role' => WorkspaceRole::Member],
    ]);

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/reports/overview')
        ->assertForbidden();

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/reports/projects-progress')
        ->assertForbidden();

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/reports/task-completion-vs-deadline')
        ->assertForbidden();

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/reports/worker-activity')
        ->assertForbidden();

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson('/api/v1/reports', [
            'type' => 'tasks',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
        ])
        ->assertForbidden();
});

it('calculates reports overview rates with deterministic baseline changes', function () {
    Carbon::setTestNow('2026-11-24 12:00:00');

    $admin = User::factory()->create();
    $workspace = reportsWorkspace($admin);
    $project = reportsProject($workspace, $admin);

    // 1 task completed out of 2 created this month
    Task::factory()->create([
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'status' => TaskStatus::Completed->value,
        'created_at' => '2026-11-10 10:00:00',
    ]);
    Task::factory()->create([
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'status' => TaskStatus::Todo->value,
        'created_at' => '2026-11-12 10:00:00',
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/reports/overview?from=2026-11-01&to=2026-11-30');

    $response->assertOk()
        ->assertJsonPath('data.task_completion_rate.value', 50)
        ->assertJsonPath('data.task_completion_rate.change_percent', null); // baseline 0
});

it('returns zero-filled buckets for task completion vs deadline for week and year', function () {
    Carbon::setTestNow('2026-11-24 12:00:00');

    $admin = User::factory()->create();
    $workspace = reportsWorkspace($admin);
    $project = reportsProject($workspace, $admin);

    // Task completed on time
    Task::factory()->create([
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'status' => TaskStatus::Completed->value,
        'due_date' => '2026-11-24',
        'completed_at' => '2026-11-24 10:00:00',
    ]);

    // Week range: exactly 7 buckets
    $weekResp = $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/reports/task-completion-vs-deadline?range=week');

    $weekResp->assertOk()
        ->assertJsonCount(7, 'data')
        ->assertJsonPath('data.6.on_time', 1)
        ->assertJsonPath('data.6.late', 0);

    // Year range: exactly 12 month buckets
    $yearResp = $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/reports/task-completion-vs-deadline?range=year');

    $yearResp->assertOk()
        ->assertJsonCount(12, 'data');
});

it('queues a report generation job and executes CSV export successfully', function () {
    Queue::fake();

    $admin = User::factory()->create();
    $workspace = reportsWorkspace($admin);
    $project = reportsProject($workspace, $admin);

    Task::factory()->create([
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'title' => 'Exportable task',
        'status' => TaskStatus::Completed->value,
        'created_at' => '2026-11-10 10:00:00',
    ]);

    $resp = $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson('/api/v1/reports', [
            'type' => 'tasks',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'project_id' => $project->id,
            'note' => 'Quarterly report',
        ]);

    $resp->assertStatus(202)
        ->assertJsonPath('data.status', 'pending');

    $reportId = $resp->json('data.id');
    $report = Report::query()->findOrFail($reportId);

    Queue::assertPushed(GenerateReportJob::class, function (GenerateReportJob $job) use ($report): bool {
        return $job->report->id === $report->id;
    });

    // Attempt download before ready -> 409
    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/reports/{$report->id}/download")
        ->assertStatus(409);

    // Execute job with mocked storage
    Storage::fake('private');
    config()->set('collabspace.files.disk', 'private');

    $job = new GenerateReportJob($report);
    app()->call([$job, 'handle']);

    $report->refresh();
    expect($report->status)->toBe(ReportStatus::Ready)
        ->and($report->file_path)->not->toBeNull();

    // Now download succeeds
    $downloadResp = $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->get("/api/v1/reports/{$report->id}/download");

    $downloadResp->assertOk();
    $csvContent = $downloadResp->streamedContent();
    expect($csvContent)->toContain('Exportable task');
});
