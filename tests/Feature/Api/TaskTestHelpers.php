<?php

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;

function taskTestWorkspace(User $owner, WorkspaceRole $role = WorkspaceRole::Admin): Workspace
{
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => $role->value]);

    return $workspace;
}

function addTaskTestWorkspaceMember(
    Workspace $workspace,
    User $user,
    WorkspaceRole $role = WorkspaceRole::Member,
): void {
    $workspace->members()->attach($user->id, ['role' => $role->value]);
}

/**
 * @param  list<User>  $members
 */
function taskTestProject(Workspace $workspace, User $creator, array $members = []): Project
{
    $project = Project::factory()->create([
        'workspace_id' => $workspace->id,
        'created_by' => $creator->id,
        'priority' => ProjectPriority::Medium,
        'status' => ProjectStatus::Planned,
    ]);

    $project->members()->syncWithoutDetaching([$creator->id]);

    foreach ($members as $member) {
        $project->members()->syncWithoutDetaching([$member->id]);
    }

    return $project;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function taskTestRecord(Project $project, User $creator, string $title, array $attributes = []): Task
{
    return Task::factory()->create([
        'workspace_id' => $project->workspace_id,
        'project_id' => $project->id,
        'created_by' => $creator->id,
        'title' => $title,
        ...$attributes,
    ]);
}
