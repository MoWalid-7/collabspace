<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\File;
use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;

class FilePolicy
{
    public function __construct(
        private ProjectRepositoryInterface $projects,
        private WorkspaceRepositoryInterface $workspaces,
    ) {}

    public function viewAny(User $user, Project $project): bool
    {
        return $this->canViewProject($user, $project);
    }

    public function upload(User $user, Project $project): bool
    {
        return $this->canViewProject($user, $project);
    }

    public function view(User $user, File $file): bool
    {
        return $this->canViewFile($user, $file);
    }

    public function download(User $user, File $file): bool
    {
        return $this->canViewFile($user, $file);
    }

    public function uploadVersion(User $user, File $file): bool
    {
        return $this->canViewFile($user, $file);
    }

    public function update(User $user, File $file): bool
    {
        return (int) $file->uploaded_by === (int) $user->getKey()
            || $this->isWorkspaceManager($user, (int) $file->workspace_id);
    }

    public function delete(User $user, File $file): bool
    {
        return $this->update($user, $file);
    }

    private function canViewFile(User $user, File $file): bool
    {
        $project = $this->projects->findInCurrentWorkspaceOrFail((int) $file->project_id);

        return $this->canViewProject($user, $project);
    }

    private function canViewProject(User $user, Project $project): bool
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

    private function isWorkspaceManager(User $user, int $workspaceId): bool
    {
        return in_array($this->workspaces->roleForWorkspaceId($workspaceId, (int) $user->getKey()), [
            WorkspaceRole::Admin->value,
            WorkspaceRole::Manager->value,
        ], true);
    }
}
