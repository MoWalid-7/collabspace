<?php

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;

function projectTestWorkspace(User $owner, WorkspaceRole $role = WorkspaceRole::Admin): Workspace
{
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => $role->value]);

    return $workspace;
}

function addProjectTestMember(Workspace $workspace, User $user, WorkspaceRole $role = WorkspaceRole::Member): void
{
    $workspace->members()->attach($user->id, ['role' => $role->value]);
}

function makeProject(Workspace $workspace, User $creator, string $name, array $attributes = []): Project
{
    return Project::factory()->create([
        'workspace_id' => $workspace->id,
        'created_by' => $creator->id,
        'name' => $name,
        ...$attributes,
    ]);
}

it('creates projects with the creator as a member and returns detailed project stats', function () {
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $workspace = projectTestWorkspace($admin);
    addProjectTestMember($workspace, $member);
    $team = Team::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson('/api/v1/projects', [
            'name' => 'Launch',
            'description' => 'First release',
            'type' => 'SaaS',
            'priority' => ProjectPriority::High->value,
            'status' => ProjectStatus::Active->value,
            'start_date' => '2026-03-01',
            'deadline' => '2026-04-01',
            'member_ids' => [$member->id],
            'team_ids' => [$team->id],
        ])
        ->assertCreated()
        ->assertJsonPath('data.project.name', 'Launch');

    $project = Project::query()->where('name', 'Launch')->firstOrFail();
    $this->assertDatabaseHas('project_user', [
        'project_id' => $project->id,
        'user_id' => $admin->id,
    ]);
    $this->assertDatabaseHas('project_user', [
        'project_id' => $project->id,
        'user_id' => $member->id,
    ]);
    $this->assertDatabaseHas('project_team', [
        'project_id' => $project->id,
        'team_id' => $team->id,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/projects/{$project->id}")
        ->assertOk()
        ->assertJsonPath('data.project.stats.total_tasks', 0)
        ->assertJsonPath('data.project.stats.progress_percent', 0)
        ->assertJsonPath('data.project.teams.0.id', $team->id);
});

it('updates, lists, and soft deletes projects', function () {
    $admin = User::factory()->create();
    $workspace = projectTestWorkspace($admin);
    $project = makeProject($workspace, $admin, 'Old name');

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->putJson("/api/v1/projects/{$project->id}", [
            'name' => 'New name',
            'status' => ProjectStatus::Active->value,
        ])
        ->assertOk()
        ->assertJsonPath('data.project.name', 'New name');

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->deleteJson("/api/v1/projects/{$project->id}")
        ->assertNoContent();

    $this->assertSoftDeleted('projects', ['id' => $project->id]);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/projects/{$project->id}")
        ->assertNotFound();
});

it('rejects project updates whose deadline precedes the existing start date', function () {
    $admin = User::factory()->create();
    $workspace = projectTestWorkspace($admin);
    $project = makeProject($workspace, $admin, 'Date range', [
        'start_date' => '2026-03-10',
        'deadline' => '2026-04-10',
    ]);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->patchJson("/api/v1/projects/{$project->id}", ['deadline' => '2026-03-09'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['deadline']);
});

it('filters, sorts, and paginates visible projects', function () {
    $admin = User::factory()->create();
    $workspace = projectTestWorkspace($admin);
    $team = Team::factory()->create(['workspace_id' => $workspace->id]);
    $first = makeProject($workspace, $admin, 'Alpha', [
        'status' => ProjectStatus::Active,
        'priority' => ProjectPriority::High,
        'deadline' => '2026-07-01',
        'created_at' => '2026-01-01 00:00:00',
    ]);
    $second = makeProject($workspace, $admin, 'Beta', [
        'status' => ProjectStatus::Planned,
        'priority' => ProjectPriority::Low,
        'deadline' => '2026-06-01',
        'created_at' => '2026-02-01 00:00:00',
    ]);
    $first->teams()->attach($team->id);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/projects?search=Al&status=active&priority=high&team_id='.$team->id)
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.projects.0.id', $first->id);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/projects?sort=oldest&per_page=1')
        ->assertOk()
        ->assertJsonPath('data.pagination.per_page', 1)
        ->assertJsonPath('data.pagination.total', 2)
        ->assertJsonPath('data.projects.0.id', $first->id);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/projects?sort=deadline')
        ->assertOk()
        ->assertJsonPath('data.projects.0.id', $second->id);
});

it('lists, adds, and removes project members and attaches or detaches teams', function () {
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $workspace = projectTestWorkspace($admin);
    addProjectTestMember($workspace, $member);
    $project = makeProject($workspace, $admin, 'Memberships');
    $project->members()->attach($admin->id);
    $team = Team::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/projects/{$project->id}/members")
        ->assertOk()
        ->assertJsonPath('data.members.0.id', $admin->id);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/projects/{$project->id}/members", [
            'user_id' => $member->id,
            'role_title' => 'Developer',
        ])
        ->assertCreated()
        ->assertJsonPath('data.member.role_title', 'Developer');

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->deleteJson("/api/v1/projects/{$project->id}/members/{$member->id}")
        ->assertNoContent();

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/projects/{$project->id}/teams", ['team_id' => $team->id])
        ->assertOk();

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->deleteJson("/api/v1/projects/{$project->id}/teams/{$team->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('project_team', [
        'project_id' => $project->id,
        'team_id' => $team->id,
    ]);
});

it('validates project dates and prevents attaching users or teams from another workspace', function () {
    $admin = User::factory()->create();
    $other = User::factory()->create();
    $workspace = projectTestWorkspace($admin);
    $otherWorkspace = projectTestWorkspace($other);
    $foreignTeam = Team::factory()->create(['workspace_id' => $otherWorkspace->id]);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson('/api/v1/projects', [
            'name' => 'Invalid dates',
            'type' => 'SaaS',
            'priority' => ProjectPriority::Medium->value,
            'status' => ProjectStatus::Planned->value,
            'start_date' => '2026-05-02',
            'deadline' => '2026-05-01',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['deadline']);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson('/api/v1/projects', [
            'name' => 'Cross tenant',
            'type' => 'SaaS',
            'priority' => ProjectPriority::Medium->value,
            'status' => ProjectStatus::Planned->value,
            'start_date' => '2026-05-01',
            'deadline' => '2026-05-02',
            'member_ids' => [$other->id],
            'team_ids' => [$foreignTeam->id],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['member_ids.0', 'team_ids.0']);

    $project = makeProject($workspace, $admin, 'Local project');

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/projects/{$project->id}/teams", ['team_id' => $foreignTeam->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['team_id']);
});

it('shows members only projects they belong to directly or through an attached team', function () {
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $workspace = projectTestWorkspace($admin);
    addProjectTestMember($workspace, $member);
    $direct = makeProject($workspace, $admin, 'Direct member');
    $teamProject = makeProject($workspace, $admin, 'Team member');
    $private = makeProject($workspace, $admin, 'Private');
    $direct->members()->attach($member->id);
    $team = Team::factory()->create(['workspace_id' => $workspace->id]);
    $team->members()->attach($member->id);
    $teamProject->teams()->attach($team->id);

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/projects')
        ->assertOk()
        ->assertJsonCount(2, 'data.projects');

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/projects/{$private->id}")
        ->assertForbidden();

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/projects')
        ->assertOk()
        ->assertJsonCount(3, 'data.projects');
});

it('restricts project management to admins and managers and hides cross-workspace projects', function () {
    $member = User::factory()->create();
    $other = User::factory()->create();
    $workspace = projectTestWorkspace($member, WorkspaceRole::Member);
    $otherWorkspace = projectTestWorkspace($other);
    $project = makeProject($workspace, $member, 'Member project');
    $foreignProject = makeProject($otherWorkspace, $other, 'Foreign project');

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson('/api/v1/projects', [])
        ->assertForbidden();

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->putJson("/api/v1/projects/{$project->id}", ['name' => 'Denied'])
        ->assertForbidden();

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->deleteJson("/api/v1/projects/{$project->id}")
        ->assertForbidden();

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/projects/{$project->id}/teams", ['team_id' => 999])
        ->assertForbidden();

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/projects/{$foreignProject->id}")
        ->assertNotFound();
});

it('allows managers to create and update projects', function () {
    $manager = User::factory()->create();
    $workspace = projectTestWorkspace($manager, WorkspaceRole::Manager);

    $this->actingAs($manager, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson('/api/v1/projects', [
            'name' => 'Manager project',
            'type' => 'SaaS',
            'priority' => ProjectPriority::Medium->value,
            'status' => ProjectStatus::Planned->value,
            'start_date' => '2026-05-01',
            'deadline' => '2026-05-05',
        ])
        ->assertCreated();
});
