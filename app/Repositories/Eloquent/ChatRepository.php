<?php

namespace App\Repositories\Eloquent;

use App\Data\Chat\ChannelFilters;
use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use App\Repositories\Contracts\ChatRepositoryInterface;
use App\Support\LikePattern;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ChatRepository implements ChatRepositoryInterface
{
    public function getMyChannels(int $workspaceId, int $userId, ChannelFilters $filters): Collection
    {
        $pattern = $filters->search !== null ? LikePattern::contains($filters->search) : null;

        /** @var Collection<int, Channel> $channels */
        $channels = Channel::query()
            ->where('workspace_id', $workspaceId)
            ->whereHas('members', fn (Builder $q) => $q->where('users.id', $userId))
            ->when($filters->type !== null, fn (Builder $q) => $q->where('type', $filters->type))
            ->when($pattern !== null, fn (Builder $q) => $q->whereRaw('LOWER(channels.name) LIKE ? ESCAPE ?', [$pattern, '\\']))
            ->with([
                'members' => fn ($q) => $q->select('users.id', 'users.name', 'users.avatar'),
                'project:id,name',
                'team:id,name',
            ])
            ->get();

        if ($channels->isEmpty()) {
            return new Collection;
        }

        $channelIds = $channels->pluck('id')->all();

        // Fetch last message for each channel
        $lastMessages = Message::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('channel_id', $channelIds)
            ->whereNull('parent_id')
            ->orderByDesc('id')
            ->with('user:id,name,avatar')
            ->get()
            ->unique('channel_id')
            ->keyBy('channel_id');

        // Compute unread counts per channel in ONE grouped query
        // channel_user holds last_read_at for this user
        $unreadCounts = Message::query()
            ->where('messages.workspace_id', $workspaceId)
            ->whereIn('messages.channel_id', $channelIds)
            ->where('messages.user_id', '!=', $userId)
            ->join('channel_user', function ($join) use ($userId): void {
                $join->on('messages.channel_id', '=', 'channel_user.channel_id')
                    ->where('channel_user.user_id', '=', $userId);
            })
            ->where(function ($q): void {
                $q->whereNull('channel_user.last_read_at')
                    ->orWhereColumn('messages.created_at', '>', 'channel_user.last_read_at');
            })
            ->groupBy('messages.channel_id')
            ->selectRaw('messages.channel_id, count(*) as unread_count')
            ->pluck('unread_count', 'channel_id');

        foreach ($channels as $channel) {
            /** @var Message|null $lastMsg */
            $lastMsg = $lastMessages->get($channel->id);
            $channel->setRelation('last_message_relation', $lastMsg);
            $channel->setAttribute('unread_count', (int) ($unreadCounts[$channel->id] ?? 0));
            $latestActivity = $lastMsg !== null ? $lastMsg->created_at : $channel->created_at;
            $channel->setAttribute('latest_activity', $latestActivity);
        }

        return $channels->sortByDesc(fn (Channel $c) => $c->getAttribute('latest_activity'))->values();
    }

    public function findChannelInWorkspaceOrFail(int $workspaceId, int $channelId): Channel
    {
        return Channel::query()
            ->where('workspace_id', $workspaceId)
            ->with(['members', 'project', 'team', 'creator'])
            ->findOrFail($channelId);
    }

    public function findProjectChannel(int $workspaceId, int $projectId): ?Channel
    {
        return Channel::query()
            ->where('workspace_id', $workspaceId)
            ->where('project_id', $projectId)
            ->with(['members', 'project', 'team', 'creator'])
            ->first();
    }

    public function findTeamChannel(int $workspaceId, int $teamId): ?Channel
    {
        return Channel::query()
            ->where('workspace_id', $workspaceId)
            ->where('team_id', $teamId)
            ->with(['members', 'project', 'team', 'creator'])
            ->first();
    }

    public function findDirectChannel(int $workspaceId, string $directKey): ?Channel
    {
        return Channel::query()
            ->where('workspace_id', $workspaceId)
            ->where('direct_key', $directKey)
            ->with(['members', 'project', 'team', 'creator'])
            ->first();
    }

    public function createChannel(array $attributes, array $memberIds = []): Channel
    {
        $channel = Channel::query()->create($attributes);

        if ($memberIds !== []) {
            $now = now();
            $attachData = [];
            foreach (array_unique($memberIds) as $mid) {
                $attachData[$mid] = ['created_at' => $now, 'updated_at' => $now];
            }
            $channel->members()->attach($attachData);
        }

        return $channel->load(['members', 'project', 'team', 'creator']);
    }

    public function addMember(Channel $channel, int $userId): void
    {
        $channel->members()->syncWithoutDetaching([$userId]);
    }

    public function removeMember(Channel $channel, int $userId): void
    {
        $channel->members()->detach($userId);
    }

    public function isMember(Channel $channel, int $userId): bool
    {
        return $channel->members()->where('users.id', $userId)->exists();
    }

    public function markAsRead(Channel $channel, int $userId): void
    {
        $channel->members()->updateExistingPivot($userId, [
            'last_read_at' => now(),
        ]);
    }

    public function paginateMessages(Channel $channel, ?string $cursor = null, int $perPage = 25): CursorPaginator
    {
        return Message::query()
            ->where('channel_id', $channel->id)
            ->whereNull('parent_id')
            ->with(['user:id,name,avatar'])
            ->withCount('replies')
            ->withMax('replies as last_reply_at', 'created_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $cursor);
    }

    public function createMessage(array $attributes): Message
    {
        return Message::query()->create($attributes)->load('user:id,name,avatar');
    }

    public function findMessageInWorkspaceOrFail(int $workspaceId, int $messageId): Message
    {
        return Message::query()
            ->where('workspace_id', $workspaceId)
            ->with(['channel', 'user'])
            ->findOrFail($messageId);
    }

    public function updateMessage(Message $message, array $attributes): Message
    {
        $message->update($attributes);

        return $message->fresh(['user', 'channel']) ?? $message;
    }

    public function deleteMessage(Message $message): void
    {
        $message->delete();
    }

    public function paginateReplies(Message $message, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        return Message::query()
            ->where('parent_id', $message->id)
            ->with('user:id,name,avatar')
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function searchChannelMessages(Channel $channel, string $term, int $limit = 20): Collection
    {
        return Message::query()
            ->where('channel_id', $channel->id)
            ->whereRaw('LOWER(messages.body) LIKE ? ESCAPE ?', [LikePattern::contains($term), '\\'])
            ->with('user:id,name,avatar')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function searchVisibleMessages(string $term, int $limit, int $userId, int $workspaceId): Collection
    {
        return Message::query()
            ->where('workspace_id', $workspaceId)
            ->whereHas('channel.members', fn (Builder $q) => $q->where('users.id', $userId))
            ->whereRaw('LOWER(messages.body) LIKE ? ESCAPE ?', [LikePattern::contains($term), '\\'])
            ->with(['user:id,name,avatar', 'channel:id,name,type'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
