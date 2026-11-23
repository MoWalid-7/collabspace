<?php

namespace App\Repositories\Eloquent;

use App\Enums\NotificationType;
use App\Models\NotificationPreference;
use App\Models\Task;
use App\Models\User;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;

class NotificationRepository implements NotificationRepositoryInterface
{
    public function paginateForUser(int $userId, bool $unreadOnly, int $perPage): LengthAwarePaginator
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $userId)
            ->when($unreadOnly, fn ($query) => $query->whereNull('read_at'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function unreadCount(int $userId): int
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function findForUserOrFail(string $notificationId, int $userId): DatabaseNotification
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $userId)
            ->findOrFail($notificationId);
    }

    public function markRead(DatabaseNotification $notification): void
    {
        $notification->markAsRead();
    }

    public function markAllRead(int $userId): void
    {
        DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function delete(DatabaseNotification $notification): void
    {
        $notification->delete();
    }

    public function preferencesFor(int $userId): Collection
    {
        return NotificationPreference::query()
            ->where('user_id', $userId)
            ->orderBy('type')
            ->get();
    }

    public function isEnabled(int $userId, NotificationType $type): bool
    {
        $enabled = NotificationPreference::query()
            ->where('user_id', $userId)
            ->where('type', $type->value)
            ->value('enabled');

        return $enabled === null || (bool) $enabled;
    }

    public function savePreferences(int $userId, array $preferences): void
    {
        foreach ($preferences as $type => $enabled) {
            NotificationPreference::query()->updateOrCreate(
                ['user_id' => $userId, 'type' => $type],
                ['enabled' => $enabled],
            );
        }
    }

    public function workspaceMemberIds(int $workspaceId, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return User::query()
            ->whereIn('users.id', $userIds)
            ->whereHas('workspaces', fn ($workspaces) => $workspaces->whereKey($workspaceId))
            ->orderBy('users.id')
            ->pluck('users.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function taskAssigneeIds(Task $task): array
    {
        return $task->assignees()
            ->orderBy('users.id')
            ->pluck('users.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function taskAssigneeIdsForWorkspace(int $workspaceId, int $taskId): array
    {
        $task = Task::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->whereNull('deleted_at')
            ->findOrFail($taskId);

        return $this->taskAssigneeIds($task);
    }

    public function taskCreatorId(Task $task): int
    {
        return (int) $task->created_by;
    }

    public function taskReviewerId(Task $task): ?int
    {
        return $task->reviewer_id === null ? null : (int) $task->reviewer_id;
    }

    public function findUserOrFail(int $userId): User
    {
        return User::query()->findOrFail($userId);
    }
}
