<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Message;
use App\Models\User;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Support\CurrentWorkspace;

class MessagePolicy
{
    public function __construct(
        private CurrentWorkspace $currentWorkspace,
        private WorkspaceRepositoryInterface $workspaces,
    ) {}

    public function update(User $user, Message $message): bool
    {
        return $message->user_id === (int) $user->getKey();
    }

    public function delete(User $user, Message $message): bool
    {
        if ($message->user_id === (int) $user->getKey()) {
            return true;
        }

        $role = $this->workspaces->roleForWorkspaceId($this->currentWorkspace->requireId(), (int) $user->getKey());

        return in_array($role, [WorkspaceRole::Admin->value, WorkspaceRole::Manager->value], true);
    }
}
