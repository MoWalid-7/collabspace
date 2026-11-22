<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Support\CurrentWorkspace;

class TaskPolicy
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

    public function view(User $user, Task $task): bool
    {
        return $this->projects->isVisibleTo($task->project, (int) $user->getKey());
    }

    public function viewTasks(User $user, Project $project): bool
    {
        return $this->projects->isVisibleTo($project, (int) $user->getKey());
    }

    public function createTask(User $user, Project $project): bool
    {
        return $this->projects->isMember($project, (int) $user->getKey());
    }

    public function update(User $user, Task $task): bool
    {
        return $this->projects->isMember($task->project, (int) $user->getKey());
    }

    public function move(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    public function updateProgress(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    public function manageAssignees(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    public function comment(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return (int) $task->created_by === (int) $user->getKey() || $this->canManageWorkspace($user);
    }

    private function canManageWorkspace(User $user): bool
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
