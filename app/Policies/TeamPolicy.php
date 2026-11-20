<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Team;
use App\Models\User;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Support\CurrentWorkspace;

class TeamPolicy
{
    public function __construct(
        private CurrentWorkspace $currentWorkspace,
        private WorkspaceRepositoryInterface $workspaces,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->currentRole($user) !== null;
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, Team $team): bool
    {
        return $this->currentRole($user) !== null;
    }

    public function update(User $user, Team $team): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Team $team): bool
    {
        return $this->canManage($user);
    }

    public function manageMembers(User $user, Team $team): bool
    {
        return $this->canManage($user);
    }

    private function canManage(User $user): bool
    {
        return in_array($this->currentRole($user), [
            WorkspaceRole::Admin->value,
            WorkspaceRole::Manager->value,
        ], true);
    }

    private function currentRole(User $user): ?string
    {
        $workspaceId = $this->currentWorkspace->id();

        return $workspaceId === null
            ? null
            : $this->workspaces->roleForWorkspaceId($workspaceId, (int) $user->getKey());
    }
}
