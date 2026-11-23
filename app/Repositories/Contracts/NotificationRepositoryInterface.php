<?php

namespace App\Repositories\Contracts;

use App\Enums\NotificationType;
use App\Models\NotificationPreference;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;

interface NotificationRepositoryInterface
{
    public function paginateForUser(int $userId, bool $unreadOnly, int $perPage): LengthAwarePaginator;

    public function unreadCount(int $userId): int;

    public function findForUserOrFail(string $notificationId, int $userId): DatabaseNotification;

    public function markRead(DatabaseNotification $notification): void;

    public function markAllRead(int $userId): void;

    public function delete(DatabaseNotification $notification): void;

    /**
     * @return Collection<int, NotificationPreference>
     */
    public function preferencesFor(int $userId): Collection;

    public function isEnabled(int $userId, NotificationType $type): bool;

    /**
     * @param  array<string, bool>  $preferences
     */
    public function savePreferences(int $userId, array $preferences): void;

    /**
     * @param  list<int>  $userIds
     * @return list<int>
     */
    public function workspaceMemberIds(int $workspaceId, array $userIds): array;

    /**
     * @return list<int>
     */
    public function taskAssigneeIds(Task $task): array;

    public function taskCreatorId(Task $task): int;

    public function taskReviewerId(Task $task): ?int;

    /**
     * @return list<int>
     */
    public function taskAssigneeIdsForWorkspace(int $workspaceId, int $taskId): array;

    public function findUserOrFail(int $userId): User;
}
