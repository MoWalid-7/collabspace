<?php

namespace App\Policies;

use App\Enums\ChannelType;
use App\Enums\WorkspaceRole;
use App\Models\Channel;
use App\Models\User;
use App\Repositories\Contracts\ChatRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Support\CurrentWorkspace;

class ChannelPolicy
{
    public function __construct(
        private CurrentWorkspace $currentWorkspace,
        private ChatRepositoryInterface $chat,
        private WorkspaceRepositoryInterface $workspaces,
    ) {}

    public function view(User $user, Channel $channel): bool
    {
        return $this->chat->isMember($channel, (int) $user->getKey());
    }

    public function join(User $user, Channel $channel): bool
    {
        return $channel->type === ChannelType::Public
            && $this->workspaces->roleForWorkspaceId($this->currentWorkspace->requireId(), (int) $user->getKey()) !== null;
    }

    public function manageMembers(User $user, Channel $channel): bool
    {
        $userId = (int) $user->getKey();
        if ($channel->created_by === $userId) {
            return true;
        }

        $role = $this->workspaces->roleForWorkspaceId($this->currentWorkspace->requireId(), $userId);

        return in_array($role, [WorkspaceRole::Admin->value, WorkspaceRole::Manager->value], true);
    }

    public function sendMessage(User $user, Channel $channel): bool
    {
        return $this->chat->isMember($channel, (int) $user->getKey());
    }
}
