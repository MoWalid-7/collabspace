<?php

namespace App\Services;

use App\Data\Chat\ChannelFilters;
use App\Data\Chat\CreateChannelData;
use App\Data\Chat\SendMessageData;
use App\Enums\ChannelType;
use App\Enums\WorkspaceRole;
use App\Events\MessageSent;
use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use App\Repositories\Contracts\ChatRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\TeamRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Services\Contracts\ChatServiceInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ChatService implements ChatServiceInterface
{
    public function __construct(
        private ChatRepositoryInterface $chat,
        private WorkspaceRepositoryInterface $workspaces,
        private ProjectRepositoryInterface $projects,
        private TeamRepositoryInterface $teams,
    ) {}

    public function listMyChannels(User $user, int $workspaceId, ChannelFilters $filters): Collection
    {
        return $this->chat->getMyChannels($workspaceId, (int) $user->getKey(), $filters);
    }

    public function createPublicChannel(CreateChannelData $data, User $user, int $workspaceId): Channel
    {
        $userId = (int) $user->getKey();
        $memberIds = array_unique(array_merge([$userId], $data->memberIds));

        // Validate that all memberIds belong to the current workspace
        foreach ($memberIds as $mid) {
            $this->workspaces->findWorkspaceMemberOrFail($workspaceId, $mid);
        }

        return $this->chat->createChannel([
            'workspace_id' => $workspaceId,
            'type' => ChannelType::Public->value,
            'name' => $data->name,
            'project_id' => null,
            'team_id' => null,
            'direct_key' => null,
            'created_by' => $userId,
        ], $memberIds);
    }

    public function getOrCreateDirectChannel(int $targetUserId, User $user, int $workspaceId): Channel
    {
        $userId = (int) $user->getKey();
        if ($targetUserId === $userId) {
            throw ValidationException::withMessages([
                'user_id' => ['You cannot start a direct chat with yourself.'],
            ]);
        }

        // Verify target belongs to workspace
        $this->workspaces->findWorkspaceMemberOrFail($workspaceId, $targetUserId);

        $min = min($userId, $targetUserId);
        $max = max($userId, $targetUserId);
        $directKey = "{$min}:{$max}";

        $existing = $this->chat->findDirectChannel($workspaceId, $directKey);
        if ($existing !== null) {
            return $existing;
        }

        return $this->chat->createChannel([
            'workspace_id' => $workspaceId,
            'type' => ChannelType::Direct->value,
            'name' => null,
            'project_id' => null,
            'team_id' => null,
            'direct_key' => $directKey,
            'created_by' => $userId,
        ], [$userId, $targetUserId]);
    }

    public function getOrCreateProjectChannel(int $projectId, User $user, int $workspaceId): Channel
    {
        $project = $this->projects->findInCurrentWorkspaceOrFail($projectId);
        $userId = (int) $user->getKey();

        // Must be visible/member
        if (! $this->projects->isVisibleTo($project, $userId, $workspaceId)) {
            throw new NotFoundHttpException('Project not found or not visible.');
        }

        $existing = $this->chat->findProjectChannel($workspaceId, $projectId);
        if ($existing !== null) {
            // Ensure requesting user is in channel_user if they are a member
            if (! $this->chat->isMember($existing, $userId)) {
                $this->chat->addMember($existing, $userId);
            }

            return $existing;
        }

        // Project members
        $projectMemberIds = $project->members()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
        if (! in_array($userId, $projectMemberIds, true)) {
            $projectMemberIds[] = $userId;
        }

        return $this->chat->createChannel([
            'workspace_id' => $workspaceId,
            'type' => ChannelType::Project->value,
            'name' => $project->name,
            'project_id' => $project->id,
            'team_id' => null,
            'direct_key' => null,
            'created_by' => $userId,
        ], $projectMemberIds);
    }

    public function getOrCreateTeamChannel(int $teamId, User $user, int $workspaceId): Channel
    {
        $team = $this->teams->findInCurrentWorkspaceOrFail($teamId);
        $userId = (int) $user->getKey();

        $existing = $this->chat->findTeamChannel($workspaceId, $teamId);
        if ($existing !== null) {
            if ($this->teams->isMember($team, $userId) && ! $this->chat->isMember($existing, $userId)) {
                $this->chat->addMember($existing, $userId);
            }

            return $existing;
        }

        $teamMemberIds = $team->members()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
        if (! in_array($userId, $teamMemberIds, true)) {
            $teamMemberIds[] = $userId;
        }

        return $this->chat->createChannel([
            'workspace_id' => $workspaceId,
            'type' => ChannelType::Team->value,
            'name' => $team->name,
            'project_id' => null,
            'team_id' => $team->id,
            'direct_key' => null,
            'created_by' => $userId,
        ], $teamMemberIds);
    }

    public function getChannel(int $channelId, User $user, int $workspaceId): Channel
    {
        $channel = $this->chat->findChannelInWorkspaceOrFail($workspaceId, $channelId);
        $this->ensureMember($channel, $user);

        return $channel;
    }

    public function joinPublicChannel(int $channelId, User $user, int $workspaceId): void
    {
        $channel = $this->chat->findChannelInWorkspaceOrFail($workspaceId, $channelId);

        if ($channel->type !== ChannelType::Public) {
            throw new AuthorizationException('Only public channels can be joined directly.');
        }

        $this->chat->addMember($channel, (int) $user->getKey());
    }

    public function addMembers(int $channelId, array $memberIds, User $user, int $workspaceId): void
    {
        $channel = $this->chat->findChannelInWorkspaceOrFail($workspaceId, $channelId);
        $this->ensureCanManageMembers($channel, $user, $workspaceId);

        foreach ($memberIds as $mid) {
            $this->workspaces->findWorkspaceMemberOrFail($workspaceId, $mid);
            $this->chat->addMember($channel, $mid);
        }
    }

    public function removeMember(int $channelId, int $targetUserId, User $user, int $workspaceId): void
    {
        $channel = $this->chat->findChannelInWorkspaceOrFail($workspaceId, $channelId);
        $this->ensureCanManageMembers($channel, $user, $workspaceId);

        $this->chat->removeMember($channel, $targetUserId);
    }

    public function listMessages(int $channelId, ?string $cursor, User $user, int $workspaceId): CursorPaginator
    {
        $channel = $this->chat->findChannelInWorkspaceOrFail($workspaceId, $channelId);
        $this->ensureMember($channel, $user);

        return $this->chat->paginateMessages($channel, $cursor);
    }

    public function sendMessage(int $channelId, SendMessageData $data, User $user, int $workspaceId): Message
    {
        $channel = $this->chat->findChannelInWorkspaceOrFail($workspaceId, $channelId);
        $this->ensureMember($channel, $user);

        if ($data->parentId !== null) {
            $parent = $this->chat->findMessageInWorkspaceOrFail($workspaceId, $data->parentId);

            if ($parent->channel_id !== $channel->id) {
                throw ValidationException::withMessages([
                    'parent_id' => ['The parent message belongs to another channel.'],
                ]);
            }

            if ($parent->parent_id !== null) {
                throw ValidationException::withMessages([
                    'parent_id' => ['Replies cannot have nested replies.'],
                ]);
            }
        }

        $message = $this->chat->createMessage([
            'workspace_id' => $workspaceId,
            'channel_id' => $channel->id,
            'user_id' => (int) $user->getKey(),
            'parent_id' => $data->parentId,
            'body' => $data->body,
        ]);

        // Auto-mark sender as read up to this moment
        $this->chat->markAsRead($channel, (int) $user->getKey());

        MessageSent::dispatch($message);

        return $message;
    }

    public function listReplies(int $messageId, int $page, User $user, int $workspaceId): LengthAwarePaginator
    {
        $message = $this->chat->findMessageInWorkspaceOrFail($workspaceId, $messageId);
        $this->ensureMember($message->channel, $user);

        return $this->chat->paginateReplies($message, $page);
    }

    public function updateMessage(int $messageId, string $body, User $user, int $workspaceId): Message
    {
        $message = $this->chat->findMessageInWorkspaceOrFail($workspaceId, $messageId);

        if ($message->user_id !== (int) $user->getKey()) {
            throw new AuthorizationException('Only the author can edit this message.');
        }

        return $this->chat->updateMessage($message, [
            'body' => $body,
            'edited_at' => now(),
        ]);
    }

    public function deleteMessage(int $messageId, User $user, int $workspaceId): void
    {
        $message = $this->chat->findMessageInWorkspaceOrFail($workspaceId, $messageId);

        $isAuthor = $message->user_id === (int) $user->getKey();
        $roleValue = $this->workspaces->roleForWorkspaceId($workspaceId, (int) $user->getKey());
        $isAdminOrManager = in_array($roleValue, [WorkspaceRole::Admin->value, WorkspaceRole::Manager->value], true);

        if (! $isAuthor && ! $isAdminOrManager) {
            throw new AuthorizationException('You are not authorized to delete this message.');
        }

        $this->chat->deleteMessage($message);
    }

    public function markAsRead(int $channelId, User $user, int $workspaceId): void
    {
        $channel = $this->chat->findChannelInWorkspaceOrFail($workspaceId, $channelId);
        $this->ensureMember($channel, $user);

        $this->chat->markAsRead($channel, (int) $user->getKey());
    }

    public function searchChannelMessages(int $channelId, string $term, User $user, int $workspaceId): Collection
    {
        $channel = $this->chat->findChannelInWorkspaceOrFail($workspaceId, $channelId);
        $this->ensureMember($channel, $user);

        return $this->chat->searchChannelMessages($channel, $term);
    }

    private function ensureMember(Channel $channel, User $user): void
    {
        if (! $this->chat->isMember($channel, (int) $user->getKey())) {
            throw new AuthorizationException('You are not a member of this channel.');
        }
    }

    private function ensureCanManageMembers(Channel $channel, User $user, int $workspaceId): void
    {
        $userId = (int) $user->getKey();
        if ($channel->created_by === $userId) {
            return;
        }

        $roleValue = $this->workspaces->roleForWorkspaceId($workspaceId, $userId);
        if (in_array($roleValue, [WorkspaceRole::Admin->value, WorkspaceRole::Manager->value], true)) {
            return;
        }

        throw new AuthorizationException('You are not authorized to manage members of this channel.');
    }
}
