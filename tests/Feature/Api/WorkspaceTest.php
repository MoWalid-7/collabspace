<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;

function makeWorkspaceFor(User $user, WorkspaceRole $role = WorkspaceRole::Admin): Workspace
{
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    app(WorkspaceRepositoryInterface::class)->addMember($workspace, $user, $role);

    return $workspace;
}

it('lists only workspaces belonging to the authenticated user', function () {
    $user = User::factory()->create();
    $workspace = makeWorkspaceFor($user);
    Workspace::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/workspaces')
        ->assertOk()
        ->assertJsonCount(1, 'data.workspaces')
        ->assertJsonPath('data.workspaces.0.id', $workspace->id);
});

it('creates a workspace and makes the creator its admin owner', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/workspaces', ['name' => 'Product Team'])
        ->assertCreated()
        ->assertJsonPath('data.workspace.name', 'Product Team');

    $workspace = Workspace::query()->where('owner_id', $user->id)->firstOrFail();
    $this->assertDatabaseHas('workspace_user', [
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::Admin->value,
    ]);
});

it('validates workspace creation and update names', function () {
    $user = User::factory()->create();
    $workspace = makeWorkspaceFor($user);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/workspaces', ['name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    $this->actingAs($user, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->putJson("/api/v1/workspaces/{$workspace->id}", ['name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('shows and updates a workspace selected by the current workspace header', function () {
    $user = User::factory()->create();
    $workspace = makeWorkspaceFor($user);

    $this->actingAs($user, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/workspaces/{$workspace->id}")
        ->assertOk()
        ->assertJsonPath('data.workspace.id', $workspace->id);

    $this->actingAs($user, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->putJson("/api/v1/workspaces/{$workspace->id}", ['name' => 'Renamed Team'])
        ->assertOk()
        ->assertJsonPath('data.workspace.name', 'Renamed Team');
});

it('requires a valid workspace header and hides workspaces from other tenants', function () {
    $member = User::factory()->create();
    $other = User::factory()->create();
    $memberWorkspace = makeWorkspaceFor($member);
    $otherWorkspace = makeWorkspaceFor($other);

    $this->actingAs($member, 'sanctum')
        ->getJson("/api/v1/workspaces/{$memberWorkspace->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['X-Workspace-Id']);

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $otherWorkspace->id)
        ->getJson("/api/v1/workspaces/{$otherWorkspace->id}")
        ->assertNotFound();

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $memberWorkspace->id)
        ->getJson("/api/v1/workspaces/{$otherWorkspace->id}")
        ->assertNotFound();
});

it('allows admins to add existing users to a workspace', function () {
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $workspace = makeWorkspaceFor($admin);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/workspaces/{$workspace->id}/members", [
            'email' => $member->email,
            'role' => WorkspaceRole::Member->value,
        ])
        ->assertCreated()
        ->assertJsonPath('data.member.id', $member->id)
        ->assertJsonPath('data.member.role', WorkspaceRole::Member->value);

    $this->assertDatabaseHas('workspace_user', [
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
        'role' => WorkspaceRole::Member->value,
    ]);
});

it('validates invitation email and role', function () {
    $admin = User::factory()->create();
    $workspace = makeWorkspaceFor($admin);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/workspaces/{$workspace->id}/members", [
            'email' => 'missing@example.com',
            'role' => 'owner',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'role']);
});

it('allows admins to change a member role and remove the member', function () {
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $workspace = makeWorkspaceFor($admin);
    app(WorkspaceRepositoryInterface::class)->addMember($workspace, $member, WorkspaceRole::Member);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->patchJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}", [
            'role' => WorkspaceRole::Manager->value,
        ])
        ->assertOk()
        ->assertJsonPath('data.member.role', WorkspaceRole::Manager->value);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('workspace_user', [
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
    ]);
});

it('restricts workspace member management to admins', function () {
    $manager = User::factory()->create();
    $target = User::factory()->create();
    $workspace = makeWorkspaceFor($manager, WorkspaceRole::Manager);

    $this->actingAs($manager, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/workspaces/{$workspace->id}/members", [
            'email' => $target->email,
            'role' => WorkspaceRole::Member->value,
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('workspace_user', [
        'workspace_id' => $workspace->id,
        'user_id' => $target->id,
    ]);
});

dataset('non-admin member management actions', [
    'invite member' => ['POST', fn (int $workspace): string => "/api/v1/workspaces/{$workspace}/members", [
        'email' => 'target@example.com',
        'role' => WorkspaceRole::Member->value,
    ]],
    'change member role' => ['PATCH', fn (int $workspace, int $user): string => "/api/v1/workspaces/{$workspace}/members/{$user}", [
        'role' => WorkspaceRole::Manager->value,
    ]],
    'remove member' => ['DELETE', fn (int $workspace, int $user): string => "/api/v1/workspaces/{$workspace}/members/{$user}", []],
]);

it('denies non-admins all workspace member management actions', function (
    string $method,
    Closure $uri,
    array $payload,
) {
    $manager = User::factory()->create();
    $target = User::factory()->create(['email' => 'target@example.com']);
    $workspace = makeWorkspaceFor($manager, WorkspaceRole::Manager);
    app(WorkspaceRepositoryInterface::class)->addMember($workspace, $target, WorkspaceRole::Member);

    $this->actingAs($manager, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->json($method, $uri($workspace->id, $target->id), $payload)
        ->assertForbidden();
})->with('non-admin member management actions');

it('does not allow the workspace owner to lose admin access or membership', function () {
    $owner = User::factory()->create();
    $workspace = makeWorkspaceFor($owner);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->patchJson("/api/v1/workspaces/{$workspace->id}/members/{$owner->id}", [
            'role' => WorkspaceRole::Member->value,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$owner->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user']);
});
