<?php

namespace App\Services\Contracts;

use App\Data\Workspaces\CreateWorkspaceData;
use App\Data\Workspaces\InviteWorkspaceMemberData;
use App\Data\Workspaces\RemoveWorkspaceMemberData;
use App\Data\Workspaces\UpdateWorkspaceData;
use App\Data\Workspaces\UpdateWorkspaceMemberRoleData;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;

interface WorkspaceServiceInterface
{
    /**
     * @return Collection<int, Workspace>
     */
    public function listForUser(int $userId): Collection;

    public function create(CreateWorkspaceData $data): Workspace;

    public function show(int $workspaceId, int $userId): Workspace;

    public function update(UpdateWorkspaceData $data): Workspace;

    public function invite(InviteWorkspaceMemberData $data): User;

    public function updateMemberRole(UpdateWorkspaceMemberRoleData $data): User;

    public function removeMember(RemoveWorkspaceMemberData $data): void;
}
