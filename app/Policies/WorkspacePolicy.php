<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;

class WorkspacePolicy
{
    public function __construct(private WorkspaceRepositoryInterface $workspaces) {}

    public function view(User $user, Workspace $workspace): bool
    {
        return $this->roleFor($user, $workspace) !== null;
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return in_array($this->roleFor($user, $workspace), [
            WorkspaceRole::Admin->value,
            WorkspaceRole::Manager->value,
        ], true);
    }

    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return $this->roleFor($user, $workspace) === WorkspaceRole::Admin->value;
    }

    private function roleFor(User $user, Workspace $workspace): ?string
    {
        return $this->workspaces->roleFor($workspace, $user->getKey());
    }
}
