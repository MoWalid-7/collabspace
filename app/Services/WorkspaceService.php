<?php

namespace App\Services;

use App\Data\Workspaces\CreateWorkspaceData;
use App\Data\Workspaces\InviteWorkspaceMemberData;
use App\Data\Workspaces\RemoveWorkspaceMemberData;
use App\Data\Workspaces\UpdateWorkspaceData;
use App\Data\Workspaces\UpdateWorkspaceMemberRoleData;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Services\Contracts\WorkspaceServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkspaceService implements WorkspaceServiceInterface
{
    public function __construct(
        private WorkspaceRepositoryInterface $workspaces,
        private UserRepositoryInterface $users,
    ) {}

    /**
     * @return Collection<int, Workspace>
     */
    public function listForUser(int $userId): Collection
    {
        return $this->workspaces->forUser($userId);
    }

    public function create(CreateWorkspaceData $data): Workspace
    {
        return DB::transaction(function () use ($data): Workspace {
            $slug = Str::slug($data->name);
            $suffix = 1;

            while ($this->workspaces->slugExists($slug)) {
                $suffix++;
                $slug = Str::slug($data->name).'-'.$suffix;
            }

            $workspace = $this->workspaces->createWorkspace([
                'name' => $data->name,
                'slug' => $slug,
                'owner_id' => $data->ownerId,
            ]);
            $owner = $this->users->findUserOrFail($data->ownerId);

            $this->workspaces->addMember($workspace, $owner, WorkspaceRole::Admin);

            return $workspace;
        });
    }

    public function show(int $workspaceId, int $userId): Workspace
    {
        return $this->workspaces->findWorkspaceForMemberOrFail($workspaceId, $userId);
    }

    public function update(UpdateWorkspaceData $data): Workspace
    {
        $workspace = $this->workspaces->findWorkspaceForMemberOrFail(
            $data->workspaceId,
            $data->userId,
        );

        return $this->workspaces->updateWorkspace($workspace, ['name' => $data->name]);
    }

    public function invite(InviteWorkspaceMemberData $data): User
    {
        $workspace = $this->workspaces->findWorkspaceForMemberOrFail(
            $data->workspaceId,
            $data->invitedBy,
        );
        $member = $this->users->findByEmail($data->email);

        if ($member === null) {
            throw ValidationException::withMessages([
                'email' => ['The selected email is invalid.'],
            ]);
        }

        if ($this->workspaces->isMember($workspace, $member->getKey())) {
            throw ValidationException::withMessages([
                'email' => ['This user is already a member of the workspace.'],
            ]);
        }

        $this->workspaces->addMember($workspace, $member, $data->role);

        return $this->workspaces->findMemberOrFail($workspace, $member->getKey());
    }

    public function updateMemberRole(UpdateWorkspaceMemberRoleData $data): User
    {
        $workspace = $this->workspaces->findWorkspaceForMemberOrFail(
            $data->workspaceId,
            $data->changedBy,
        );
        $member = $this->workspaces->findMemberOrFail($workspace, $data->memberId);

        if ((int) $member->getKey() === (int) $workspace->owner_id) {
            throw ValidationException::withMessages([
                'role' => ["The workspace owner's role cannot be changed."],
            ]);
        }

        $this->workspaces->updateMemberRole($workspace, $member->getKey(), $data->role);

        return $this->workspaces->findMemberOrFail($workspace, $member->getKey());
    }

    public function removeMember(RemoveWorkspaceMemberData $data): void
    {
        $workspace = $this->workspaces->findWorkspaceForMemberOrFail(
            $data->workspaceId,
            $data->removedBy,
        );
        $member = $this->workspaces->findMemberOrFail($workspace, $data->memberId);

        if ((int) $member->getKey() === (int) $workspace->owner_id) {
            throw ValidationException::withMessages([
                'user' => ['The workspace owner cannot be removed.'],
            ]);
        }

        $this->workspaces->removeMember($workspace, $member->getKey());
    }
}
