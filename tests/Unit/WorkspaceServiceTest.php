<?php

use App\Data\Workspaces\CreateWorkspaceData;
use App\Data\Workspaces\UpdateWorkspaceMemberRoleData;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Services\WorkspaceService;
use Illuminate\Validation\ValidationException;
use Mockery;

it('checks slug collisions and assigns the creator as workspace admin', function () {
    $owner = User::factory()->make();
    $owner->id = 8;
    $workspace = new Workspace;
    $workspace->id = 9;
    $workspaces = Mockery::mock(WorkspaceRepositoryInterface::class);
    $users = Mockery::mock(UserRepositoryInterface::class);

    $workspaces->shouldReceive('slugExists')
        ->once()
        ->with('product-team')
        ->andReturnTrue();
    $workspaces->shouldReceive('slugExists')
        ->once()
        ->with('product-team-2')
        ->andReturnFalse();
    $workspaces->shouldReceive('createWorkspace')
        ->once()
        ->with([
            'name' => 'Product Team',
            'slug' => 'product-team-2',
            'owner_id' => 8,
        ])
        ->andReturn($workspace);
    $users->shouldReceive('findUserOrFail')->once()->with(8)->andReturn($owner);
    $workspaces->shouldReceive('addMember')
        ->once()
        ->with($workspace, $owner, WorkspaceRole::Admin);

    $created = (new WorkspaceService($workspaces, $users))->create(
        new CreateWorkspaceData('Product Team', 8),
    );

    expect($created)->toBe($workspace);
});

it('prevents the workspace owner from losing the admin role', function () {
    $owner = User::factory()->make();
    $owner->id = 8;
    $workspace = new Workspace;
    $workspace->owner_id = 8;
    $workspaces = Mockery::mock(WorkspaceRepositoryInterface::class);
    $users = Mockery::mock(UserRepositoryInterface::class);
    $workspaces->shouldReceive('findWorkspaceForMemberOrFail')->once()->with(9, 8)->andReturn($workspace);
    $workspaces->shouldReceive('findMemberOrFail')->once()->with($workspace, 8)->andReturn($owner);

    expect(fn () => (new WorkspaceService($workspaces, $users))->updateMemberRole(
        new UpdateWorkspaceMemberRoleData(9, 8, 8, WorkspaceRole::Member),
    ))->toThrow(ValidationException::class);
});
