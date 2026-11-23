<?php

namespace App\Services;

use App\Data\Notifications\NotificationData;
use App\Enums\NotificationType;
use App\Notifications\DomainNotification;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Services\Contracts\NotificationServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationService implements NotificationServiceInterface
{
    public function __construct(private NotificationRepositoryInterface $notifications) {}

    public function list(int $userId, bool $unreadOnly, int $perPage): LengthAwarePaginator
    {
        return $this->notifications->paginateForUser($userId, $unreadOnly, $perPage);
    }

    public function unreadCount(int $userId): int
    {
        return $this->notifications->unreadCount($userId);
    }

    public function markRead(string $notificationId, int $userId): void
    {
        $this->notifications->markRead($this->notifications->findForUserOrFail($notificationId, $userId));
    }

    public function markAllRead(int $userId): void
    {
        $this->notifications->markAllRead($userId);
    }

    public function delete(string $notificationId, int $userId): void
    {
        $this->notifications->delete($this->notifications->findForUserOrFail($notificationId, $userId));
    }

    /**
     * @return list<array{type: string, enabled: bool}>
     */
    public function preferences(int $userId): array
    {
        $saved = $this->notifications->preferencesFor($userId)
            ->keyBy(fn ($preference): string => $preference->type->value);

        return array_map(
            fn (NotificationType $type): array => [
                'type' => $type->value,
                'enabled' => $saved->has($type->value)
                    ? (bool) $saved->get($type->value)->enabled
                    : true,
            ],
            NotificationType::cases(),
        );
    }

    /**
     * @param  array<string, bool>  $preferences
     * @return list<array{type: string, enabled: bool}>
     */
    public function updatePreferences(int $userId, array $preferences): array
    {
        $this->notifications->savePreferences($userId, $preferences);

        return $this->preferences($userId);
    }

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
    ): void {
        $actor = $this->notifications->findUserOrFail($actorId);
        $eligibleRecipientIds = $this->notifications->workspaceMemberIds(
            $workspaceId,
            array_values(array_unique(array_filter(
                array_map('intval', $recipientIds),
                fn (int $userId): bool => $userId !== $actorId,
            ))),
        );
        $data = new NotificationData(
            type: $type,
            title: $title,
            body: $body,
            projectId: $projectId,
            taskId: $taskId,
            fileId: $fileId,
            actor: ['id' => (int) $actor->getKey(), 'name' => $actor->name],
        );
        $notification = new DomainNotification($data);

        foreach ($eligibleRecipientIds as $recipientId) {
            if (! $this->notifications->isEnabled($recipientId, $type)) {
                continue;
            }

            $recipient = $this->notifications->findUserOrFail($recipientId);
            $recipient->notify($notification);
        }
    }
}
