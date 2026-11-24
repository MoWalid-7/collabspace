<?php

namespace App\Repositories\Contracts;

use App\Data\Chat\ChannelFilters;
use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ChatRepositoryInterface
{
    /**
     * @return Collection<int, Channel>
     */
    public function getMyChannels(int $workspaceId, int $userId, ChannelFilters $filters): Collection;

    public function findChannelInWorkspaceOrFail(int $workspaceId, int $channelId): Channel;

    public function findProjectChannel(int $workspaceId, int $projectId): ?Channel;

    public function findTeamChannel(int $workspaceId, int $teamId): ?Channel;

    public function findDirectChannel(int $workspaceId, string $directKey): ?Channel;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $memberIds
     */
    public function createChannel(array $attributes, array $memberIds = []): Channel;

    public function addMember(Channel $channel, int $userId): void;

    public function removeMember(Channel $channel, int $userId): void;

    public function isMember(Channel $channel, int $userId): bool;

    public function markAsRead(Channel $channel, int $userId): void;

    /**
     * Cursor paginate top-level messages in channel (newest first).
     *
     * @return CursorPaginator<int, Message>
     */
    public function paginateMessages(Channel $channel, ?string $cursor = null, int $perPage = 25): CursorPaginator;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createMessage(array $attributes): Message;

    public function findMessageInWorkspaceOrFail(int $workspaceId, int $messageId): Message;

    public function updateMessage(Message $message, array $attributes): Message;

    public function deleteMessage(Message $message): void;

    /**
     * @return LengthAwarePaginator<int, Message>
     */
    public function paginateReplies(Message $message, int $page = 1, int $perPage = 20): LengthAwarePaginator;

    /**
     * @return Collection<int, Message>
     */
    public function searchChannelMessages(Channel $channel, string $term, int $limit = 20): Collection;

    /**
     * Search messages visible across all channels the user is a member of.
     *
     * @return Collection<int, Message>
     */
    public function searchVisibleMessages(string $term, int $limit, int $userId, int $workspaceId): Collection;
}
