<?php

use App\Enums\WorkspaceRole;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;

function phaseTwoWorkspaceFor(User $owner, WorkspaceRole $role = WorkspaceRole::Admin): Workspace
{
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => $role->value]);

    return $workspace;
}

function addPhaseTwoWorkspaceMember(Workspace $workspace, User $user, WorkspaceRole $role = WorkspaceRole::Member): void
{
    $workspace->members()->attach($user->id, ['role' => $role->value]);
}

function phaseTwoTeam(Workspace $workspace, string $name = 'Design'): Team
{
    return Team::factory()->create([
        'workspace_id' => $workspace->id,
        'name' => $name,
    ]);
}

it('lists, creates, shows, updates, and deletes teams in the selected workspace', function () {
    $admin = User::factory()->create();
    $workspace = phaseTwoWorkspaceFor($admin);
    $team = phaseTwoTeam($workspace, 'Design');
    phaseTwoTeam($workspace, 'Engineering');

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/teams?search=Design')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.teams.0.name', 'Design');

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson('/api/v1/teams', [
            'name' => 'Research',
            'description' => 'Research and discovery',
        ])
        ->assertCreated()
        ->assertJsonPath('data.team.name', 'Research');

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/teams/{$team->id}")
        ->assertOk()
        ->assertJsonPath('data.team.id', $team->id);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->putJson("/api/v1/teams/{$team->id}", ['name' => 'Product Design'])
        ->assertOk()
        ->assertJsonPath('data.team.name', 'Product Design');

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->deleteJson("/api/v1/teams/{$team->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('teams', ['id' => $team->id]);
});

it('adds, updates, and removes workspace members from a team', function () {
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $workspace = phaseTwoWorkspaceFor($admin);
    addPhaseTwoWorkspaceMember($workspace, $member);
    $team = phaseTwoTeam($workspace);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/teams/{$team->id}/members", [
            'user_id' => $member->id,
            'role_title' => 'UI Designer',
        ])
        ->assertCreated()
        ->assertJsonPath('data.member.role_title', 'UI Designer');

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->patchJson("/api/v1/teams/{$team->id}/members/{$member->id}", [
            'role_title' => 'Lead Designer',
        ])
        ->assertOk()
        ->assertJsonPath('data.member.role_title', 'Lead Designer');

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->deleteJson("/api/v1/teams/{$team->id}/members/{$member->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('team_user', [
        'team_id' => $team->id,
        'user_id' => $member->id,
    ]);
});

it('validates team names and restricts team membership to the current workspace', function () {
    $admin = User::factory()->create();
    $otherUser = User::factory()->create();
    $workspace = phaseTwoWorkspaceFor($admin);
    phaseTwoWorkspaceFor($otherUser);
    $team = phaseTwoTeam($workspace);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson('/api/v1/teams', ['name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/teams/{$team->id}/members", [
            'user_id' => $otherUser->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_id']);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson('/api/v1/teams', [
            'name' => 'Foreign lead',
            'lead_id' => $otherUser->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lead_id']);

});

it('allows workspace members to view teams but restricts team management to admins and managers', function () {
    $member = User::factory()->create();
    $workspace = phaseTwoWorkspaceFor($member, WorkspaceRole::Member);
    $team = phaseTwoTeam($workspace);

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/teams')
        ->assertOk();

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson('/api/v1/teams', ['name' => 'Forbidden'])
        ->assertForbidden();

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->deleteJson("/api/v1/teams/{$team->id}")
        ->assertForbidden();
});

it('allows managers to manage teams', function () {
    $manager = User::factory()->create();
    $workspace = phaseTwoWorkspaceFor($manager, WorkspaceRole::Manager);

    $this->actingAs($manager, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson('/api/v1/teams', ['name' => 'Managed'])
        ->assertCreated();
});

it('hides teams belonging to another workspace', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $workspace = phaseTwoWorkspaceFor($user);
    $otherWorkspace = phaseTwoWorkspaceFor($other);
    $otherTeam = phaseTwoTeam($otherWorkspace);

    $this->actingAs($user, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/teams/{$otherTeam->id}")
        ->assertNotFound();
});
