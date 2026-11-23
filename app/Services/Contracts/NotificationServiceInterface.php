<?php

namespace App\Services\Contracts;

use App\Enums\NotificationType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface NotificationServiceInterface
{
    public function list(int $userId, bool $unreadOnly, int $perPage): LengthAwarePaginator;

    public function unreadCount(int $userId): int;

    public function markRead(string $notificationId, int $userId): void;

    public function markAllRead(int $userId): void;

    public function delete(string $notificationId, int $userId): void;

    /**
     * @return list<array{type: string, enabled: bool}>
     */
    public function preferences(int $userId): array;

    /**
     * @param  array<string, bool>  $preferences
     * @return list<array{type: string, enabled: bool}>
     */
    public function updatePreferences(int $userId, array $preferences): array;

    /**
     * @param  list<int>  $recipientIds
     */
    public function notify(
        NotificationType $type,
        string $title,
        string $body,
        int $workspaceId,
        ?int $projectId,
        ?int $taskId,
        ?int $fileId,
        int $actorId,
        array $recipientIds,
    ): void;
}
