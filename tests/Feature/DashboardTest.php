<?php

use App\Enums\TaskStatus;
use App\Enums\WorkspaceRole;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;

function phaseFiveDashboardWorkspace(User $owner, WorkspaceRole $role = WorkspaceRole::Admin): Workspace
{
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => $role->value]);

    return $workspace;
}

function phaseFiveDashboardProject(
    Workspace $workspace,
    User $creator,
    string $name,
    array $members = [],
): Project {
    $project = Project::factory()->create([
        'workspace_id' => $workspace->id,
        'created_by' => $creator->id,
        'name' => $name,
    ]);
    $project->members()->attach($creator->id);

    foreach ($members as $member) {
        $project->members()->attach($member->id);
    }

    return $project;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function phaseFiveDashboardTask(Project $project, User $creator, string $title, array $attributes = []): Task
{
    return Task::factory()->create([
        'workspace_id' => $project->workspace_id,
        'project_id' => $project->id,
        'created_by' => $creator->id,
        'title' => $title,
        ...$attributes,
    ]);
}

afterEach(function (): void {
    Carbon::setTestNow();
});

it('aggregates dashboard task counts, overdue tasks, and calendar-month changes', function () {
    Carbon::setTestNow('2026-04-15 12:00:00');
    $admin = User::factory()->create();
    $workspace = phaseFiveDashboardWorkspace($admin);
    $project = phaseFiveDashboardProject($workspace, $admin, 'Metrics');

    phaseFiveDashboardTask($project, $admin, 'Pending overdue', [
        'status' => TaskStatus::Todo,
        'due_date' => '2026-04-14',
        'created_at' => '2026-04-02 09:00:00',
    ]);
    phaseFiveDashboardTask($project, $admin, 'In progress', [
        'status' => TaskStatus::InProgress,
        'created_at' => '2026-03-02 09:00:00',
    ]);
    phaseFiveDashboardTask($project, $admin, 'In review', [
        'status' => TaskStatus::InReview,
        'created_at' => '2026-03-03 09:00:00',
    ]);
    phaseFiveDashboardTask($project, $admin, 'Completed this month', [
        'status' => TaskStatus::Completed,
        'completed_at' => '2026-04-12 09:00:00',
        'created_at' => '2026-04-05 09:00:00',
        'due_date' => '2026-04-10',
    ]);
    phaseFiveDashboardTask($project, $admin, 'Completed previous month', [
        'status' => TaskStatus::Completed,
        'completed_at' => '2026-03-12 09:00:00',
        'created_at' => '2026-03-04 09:00:00',
        'due_date' => '2026-04-14',
    ]);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/dashboard/stats')
        ->assertOk()
        ->assertJsonPath('data.stats.total_tasks.value', 5)
        ->assertJsonPath('data.stats.total_tasks.change_percent', -33)
        ->assertJsonPath('data.stats.pending_tasks.value', 1)
        ->assertJsonPath('data.stats.pending_tasks.change_percent', null)
        ->assertJsonPath('data.stats.active_tasks.value', 2)
        ->assertJsonPath('data.stats.completed_tasks.value', 2)
        ->assertJsonPath('data.stats.completed_tasks.change_percent', 0)
        ->assertJsonPath('data.stats.overdue_tasks.value', 1)
        ->assertJsonPath('data.stats.completion_rate.value', 40)
        ->assertJsonPath('data.stats.completion_rate.change_percent', null);
});

it('returns a zero completion rate and null changes when there is no previous activity', function () {
    Carbon::setTestNow('2026-04-15 12:00:00');
    $admin = User::factory()->create();
    $workspace = phaseFiveDashboardWorkspace($admin);
    $project = phaseFiveDashboardProject($workspace, $admin, 'Empty');

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/dashboard/stats')
        ->assertOk()
        ->assertJsonPath('data.stats.completion_rate.value', 0)
        ->assertJsonPath('data.stats.total_tasks.change_percent', null)
        ->assertJsonPath('data.stats.completed_tasks.change_percent', null);

    phaseFiveDashboardTask($project, $admin, 'Created this month', [
        'created_at' => '2026-04-01 09:00:00',
        'status' => TaskStatus::Completed,
        'completed_at' => '2026-04-02 09:00:00',
    ]);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/dashboard/stats')
        ->assertOk()
        ->assertJsonPath('data.stats.total_tasks.change_percent', null)
        ->assertJsonPath('data.stats.completed_tasks.change_percent', null);
});

it('applies mine and visible project filters to dashboard statistics', function () {
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $assignee = User::factory()->create();
    $workspace = phaseFiveDashboardWorkspace($admin);
    $workspace->members()->attach($member->id, ['role' => WorkspaceRole::Member->value]);
    $workspace->members()->attach($assignee->id, ['role' => WorkspaceRole::Member->value]);
    $selectedProject = phaseFiveDashboardProject($workspace, $admin, 'Selected', [$member]);
    $otherProject = phaseFiveDashboardProject($workspace, $admin, 'Other');
    $selectedTask = phaseFiveDashboardTask($selectedProject, $admin, 'Mine');
    $selectedTask->assignees()->attach($member->id);
    $otherAssigneeTask = phaseFiveDashboardTask($selectedProject, $admin, 'Not mine');
    $otherAssigneeTask->assignees()->attach($assignee->id);
    phaseFiveDashboardTask($otherProject, $admin, 'Other project');

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/dashboard/stats?project_id='.$selectedProject->id.'&mine=true')
        ->assertOk()
        ->assertJsonPath('data.stats.total_tasks.value', 1);

    $invisibleProject = phaseFiveDashboardProject($workspace, $admin, 'Not visible');

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/dashboard/stats?project_id='.$invisibleProject->id)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['project_id']);
});

it('shows admins all workspace projects and members only directly or team-visible projects', function () {
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $otherOwner = User::factory()->create();
    $workspace = phaseFiveDashboardWorkspace($admin);
    $workspace->members()->attach($member->id, ['role' => WorkspaceRole::Member->value]);
    $directProject = phaseFiveDashboardProject($workspace, $admin, 'Direct');
    $directProject->members()->attach($member->id);
    $teamProject = phaseFiveDashboardProject($workspace, $admin, 'By team');
    $privateProject = phaseFiveDashboardProject($workspace, $admin, 'Private');
    $team = Team::factory()->create(['workspace_id' => $workspace->id]);
    $team->members()->attach($member->id);
    $teamProject->teams()->attach($team->id);
    phaseFiveDashboardTask($directProject, $admin, 'Direct task');
    phaseFiveDashboardTask($teamProject, $admin, 'Team task');
    phaseFiveDashboardTask($privateProject, $admin, 'Private task');

    $otherWorkspace = phaseFiveDashboardWorkspace($otherOwner);
    $foreignProject = phaseFiveDashboardProject($otherWorkspace, $otherOwner, 'Foreign');
    $otherWorkspace->members()->attach($member->id, ['role' => WorkspaceRole::Member->value]);
    $foreignProject->members()->attach($member->id);
    phaseFiveDashboardTask($foreignProject, $otherOwner, 'Foreign task');

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/dashboard/stats')
        ->assertOk()
        ->assertJsonPath('data.stats.total_tasks.value', 2);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/dashboard/stats')
        ->assertOk()
        ->assertJsonPath('data.stats.total_tasks.value', 3);
});

it('zero-fills weekly created and completed task buckets including both boundary days', function () {
    Carbon::setTestNow('2026-04-15 12:00:00');
    $admin = User::factory()->create();
    $workspace = phaseFiveDashboardWorkspace($admin);
    $project = phaseFiveDashboardProject($workspace, $admin, 'Week');

    phaseFiveDashboardTask($project, $admin, 'First day', [
        'created_at' => '2026-04-09 23:59:59',
        'status' => TaskStatus::Completed,
        'completed_at' => '2026-04-09 23:59:59',
    ]);
    phaseFiveDashboardTask($project, $admin, 'Last day', [
        'created_at' => '2026-04-15 00:00:01',
        'status' => TaskStatus::Completed,
        'completed_at' => '2026-04-15 00:00:01',
    ]);
    phaseFiveDashboardTask($project, $admin, 'Outside range', [
        'created_at' => '2026-04-08 23:59:59',
        'status' => TaskStatus::Completed,
        'completed_at' => '2026-04-08 23:59:59',
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/dashboard/project-overview?range=week')
        ->assertOk();

    expect($response->json('data.project_overview.created'))->toHaveCount(7)
        ->and($response->json('data.project_overview.created.0'))->toBe([
            'date' => '2026-04-09',
            'label' => 'Thu',
            'count' => 1,
        ])
        ->and($response->json('data.project_overview.created.6'))->toBe([
            'date' => '2026-04-15',
            'label' => 'Wed',
            'count' => 1,
        ])
        ->and(array_column($response->json('data.project_overview.created'), 'count'))
        ->toBe([1, 0, 0, 0, 0, 0, 1])
        ->and(array_column($response->json('data.project_overview.completed'), 'count'))
        ->toBe([1, 0, 0, 0, 0, 0, 1]);
});

it('zero-fills all twelve current-year month buckets and includes year boundaries', function () {
    Carbon::setTestNow('2026-04-15 12:00:00');
    $admin = User::factory()->create();
    $workspace = phaseFiveDashboardWorkspace($admin);
    $project = phaseFiveDashboardProject($workspace, $admin, 'Year');

    phaseFiveDashboardTask($project, $admin, 'January', [
        'created_at' => '2026-01-01 00:00:00',
        'status' => TaskStatus::Completed,
        'completed_at' => '2026-01-01 00:00:00',
    ]);
    phaseFiveDashboardTask($project, $admin, 'December', [
        'created_at' => '2026-12-31 23:59:59',
        'status' => TaskStatus::Completed,
        'completed_at' => '2026-12-31 23:59:59',
    ]);
    phaseFiveDashboardTask($project, $admin, 'Previous year', [
        'created_at' => '2025-12-31 23:59:59',
        'status' => TaskStatus::Completed,
        'completed_at' => '2025-12-31 23:59:59',
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/dashboard/project-overview?range=year')
        ->assertOk();
    $created = $response->json('data.project_overview.created');

    expect($created)->toHaveCount(12)
        ->and($created[0])->toBe(['date' => '2026-01', 'label' => 'Jan', 'count' => 1])
        ->and($created[11])->toBe(['date' => '2026-12', 'label' => 'Dec', 'count' => 1])
        ->and(array_sum(array_column($created, 'count')))->toBe(2)
        ->and(array_sum(array_column($response->json('data.project_overview.completed'), 'count')))->toBe(2);
});

it('orders deadlines, distinguishes overdue and today, excludes completed, and applies the limit', function () {
    Carbon::setTestNow('2026-04-15 12:00:00');
    $admin = User::factory()->create();
    $assignee = User::factory()->create();
    $workspace = phaseFiveDashboardWorkspace($admin);
    $workspace->members()->attach($assignee->id, ['role' => WorkspaceRole::Member->value]);
    $project = phaseFiveDashboardProject($workspace, $admin, 'Due dates');
    $overdue = phaseFiveDashboardTask($project, $admin, 'Overdue', ['due_date' => '2026-04-14']);
    $overdue->assignees()->attach($assignee->id);
    $todayTask = phaseFiveDashboardTask($project, $admin, 'Today', ['due_date' => '2026-04-15']);
    phaseFiveDashboardTask($project, $admin, 'Upcoming', ['due_date' => '2026-04-29']);
    phaseFiveDashboardTask($project, $admin, 'Too far away', ['due_date' => '2026-04-30']);
    phaseFiveDashboardTask($project, $admin, 'Completed', [
        'due_date' => '2026-04-13',
        'status' => TaskStatus::Completed,
    ]);

    $limitedResponse = $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/dashboard/deadlines?limit=2')
        ->assertOk()
        ->assertJsonCount(2, 'data.deadlines')
        ->assertJsonPath('data.deadlines.0.id', $overdue->id)
        ->assertJsonPath('data.deadlines.1.id', $todayTask->id)
        ->assertJsonPath('data.deadlines.0.is_overdue', true)
        ->assertJsonPath('data.deadlines.0.is_due_today', false)
        ->assertJsonPath('data.deadlines.0.assignees.0.id', $assignee->id);

    expect($limitedResponse->json('data.deadlines.0.project'))->toBe([
        'id' => $project->id,
        'name' => $project->name,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/dashboard/deadlines')
        ->assertOk()
        ->assertJsonPath('data.deadlines.1.is_due_today', true)
        ->assertJsonCount(3, 'data.deadlines');
});

it('counts team progress once per assigned visible task and reports member totals', function () {
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $secondMember = User::factory()->create();
    $workspace = phaseFiveDashboardWorkspace($admin);
    $workspace->members()->attach($member->id, ['role' => WorkspaceRole::Member->value]);
    $workspace->members()->attach($secondMember->id, ['role' => WorkspaceRole::Member->value]);
    $team = Team::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Build team']);
    $otherOwner = User::factory()->create();
    $otherWorkspace = phaseFiveDashboardWorkspace($otherOwner);
    Team::factory()->create(['workspace_id' => $otherWorkspace->id, 'name' => 'Foreign team']);
    $team->members()->attach([$member->id, $secondMember->id]);

    $visibleProject = phaseFiveDashboardProject($workspace, $admin, 'Team-visible');
    $visibleProject->teams()->attach($team->id);
    $completed = phaseFiveDashboardTask($visibleProject, $admin, 'Completed', [
        'status' => TaskStatus::Completed,
    ]);
    $completed->assignees()->attach([$member->id, $secondMember->id]);
    $pending = phaseFiveDashboardTask($visibleProject, $admin, 'Pending');
    $pending->assignees()->attach([$member->id, $secondMember->id]);

    $hiddenProject = phaseFiveDashboardProject($workspace, $admin, 'Hidden');
    $hiddenTask = phaseFiveDashboardTask($hiddenProject, $admin, 'Hidden completed', [
        'status' => TaskStatus::Completed,
    ]);
    $hiddenTask->assignees()->attach($member->id);

    $response = $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/dashboard/teams')
        ->assertOk()
        ->assertJsonPath('data.teams.0.id', $team->id)
        ->assertJsonPath('data.teams.0.members_count', 2)
        ->assertJsonPath('data.teams.0.tasks_completed', 1)
        ->assertJsonPath('data.teams.0.tasks_total', 2)
        ->assertJsonPath('data.teams.0.completion_percent', 50);

    expect($response->json('data.teams'))->toHaveCount(1);
});

it('returns recent files from visible projects with uploader, project, size, and MIME type', function () {
    $admin = User::factory()->create(['name' => 'Uploader']);
    $workspace = phaseFiveDashboardWorkspace($admin);
    $project = phaseFiveDashboardProject($workspace, $admin, 'File project');
    $file = File::factory()->create([
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'uploaded_by' => $admin->id,
        'name' => 'recent.pdf',
        'created_at' => '2026-04-15 12:00:00',
    ]);
    FileVersion::factory()->create([
        'file_id' => $file->id,
        'uploaded_by' => $admin->id,
        'size' => 4096,
        'mime' => 'application/pdf',
    ]);
    $assignedTask = phaseFiveDashboardTask($project, $admin, 'Assigned task');
    $assignedTask->assignees()->attach($admin->id);
    $taskFile = File::factory()->create([
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'task_id' => $assignedTask->id,
        'uploaded_by' => $admin->id,
        'name' => 'task-file.pdf',
        'created_at' => '2026-04-14 12:00:00',
    ]);
    FileVersion::factory()->create([
        'file_id' => $taskFile->id,
        'uploaded_by' => $admin->id,
        'size' => 2048,
        'mime' => 'application/pdf',
    ]);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/dashboard/recent-files')
        ->assertOk()
        ->assertJsonPath('data.files.0.id', $file->id)
        ->assertJsonPath('data.files.0.uploader.name', 'Uploader')
        ->assertJsonPath('data.files.0.project.name', 'File project')
        ->assertJsonPath('data.files.0.size', 4096)
        ->assertJsonPath('data.files.0.mime', 'application/pdf')
        ->assertJsonPath('data.files.0.created_at', $file->created_at->toISOString());

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/dashboard/recent-files?mine=true')
        ->assertOk()
        ->assertJsonCount(1, 'data.files')
        ->assertJsonPath('data.files.0.id', $taskFile->id);
});
