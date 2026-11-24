<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Support\CurrentWorkspace;

class ProjectPolicy
{
    public function __construct(
        private CurrentWorkspace $currentWorkspace,
        private ProjectRepositoryInterface $projects,
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

    public function view(User $user, Project $project): bool
    {
        return $this->projects->isVisibleTo(
            $project,
            (int) $user->getKey(),
            $this->currentWorkspace->requireId(),
        );
    }

    public function viewActivities(User $user, Project $project): bool
    {
        $role = $this->workspaces->roleForWorkspaceId(
            (int) $project->workspace_id,
            (int) $user->getKey(),
        );

        return $role !== null && (
            in_array($role, [WorkspaceRole::Admin->value, WorkspaceRole::Manager->value], true)
            || $this->projects->isMember($project, (int) $user->getKey())
        );
    }

    public function update(User $user, Project $project): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->canManage($user);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $this->canManage($user);
    }

    public function manageTeams(User $user, Project $project): bool
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
