<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Comment;
use App\Models\User;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Support\CurrentWorkspace;

class CommentPolicy
{
    public function __construct(
        private CurrentWorkspace $currentWorkspace,
        private WorkspaceRepositoryInterface $workspaces,
    ) {}

    public function update(User $user, Comment $comment): bool
    {
        return (int) $comment->user_id === (int) $user->getKey() || $this->canManageWorkspace($user);
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $this->update($user, $comment);
    }

    private function canManageWorkspace(User $user): bool
    {
        $workspaceId = $this->currentWorkspace->id();

        if ($workspaceId === null) {
            return false;
        }

        return in_array($this->workspaces->roleForWorkspaceId($workspaceId, (int) $user->getKey()), [
            WorkspaceRole::Admin->value,
            WorkspaceRole::Manager->value,
        ], true);
    }
}
