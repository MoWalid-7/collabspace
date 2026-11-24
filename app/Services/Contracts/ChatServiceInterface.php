<?php

namespace App\Services\Contracts;

use App\Data\Chat\ChannelFilters;
use App\Data\Chat\CreateChannelData;
use App\Data\Chat\SendMessageData;
use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ChatServiceInterface
{
    /**
     * @return Collection<int, Channel>
     */
    public function listMyChannels(User $user, int $workspaceId, ChannelFilters $filters): Collection;

    public function createPublicChannel(CreateChannelData $data, User $user, int $workspaceId): Channel;

    public function getOrCreateDirectChannel(int $targetUserId, User $user, int $workspaceId): Channel;

    public function getOrCreateProjectChannel(int $projectId, User $user, int $workspaceId): Channel;

    public function getOrCreateTeamChannel(int $teamId, User $user, int $workspaceId): Channel;

    public function getChannel(int $channelId, User $user, int $workspaceId): Channel;

    public function joinPublicChannel(int $channelId, User $user, int $workspaceId): void;

    /**
     * @param  list<int>  $memberIds
     */
    public function addMembers(int $channelId, array $memberIds, User $user, int $workspaceId): void;

    public function removeMember(int $channelId, int $targetUserId, User $user, int $workspaceId): void;

    /**
     * @return CursorPaginator<int, Message>
     */
    public function listMessages(int $channelId, ?string $cursor, User $user, int $workspaceId): CursorPaginator;

    public function sendMessage(int $channelId, SendMessageData $data, User $user, int $workspaceId): Message;

    /**
     * @return LengthAwarePaginator<int, Message>
     */
    public function listReplies(int $messageId, int $page, User $user, int $workspaceId): LengthAwarePaginator;

    public function updateMessage(int $messageId, string $body, User $user, int $workspaceId): Message;

    public function deleteMessage(int $messageId, User $user, int $workspaceId): void;

    public function markAsRead(int $channelId, User $user, int $workspaceId): void;

    /**
     * @return Collection<int, Message>
     */
    public function searchChannelMessages(int $channelId, string $term, User $user, int $workspaceId): Collection;
}
