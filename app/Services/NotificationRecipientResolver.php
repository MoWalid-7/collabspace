<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Events\CommentAdded;
use App\Events\FileUploaded;
use App\Events\ProjectMemberAdded;
use App\Events\TaskAssigned;
use App\Events\TaskStatusChanged;
use App\Repositories\Contracts\NotificationRepositoryInterface;

class NotificationRecipientResolver
{
    public function __construct(private NotificationRepositoryInterface $notifications) {}

    /**
     * @return list<int>
     */
    public function forTaskAssigned(TaskAssigned $event): array
    {
        return $this->eligible(
            $event->actorId,
            [(int) $event->assignee->getKey()],
        );
    }

    /**
     * @return list<int>
     */
    public function forTaskStatusChanged(TaskStatusChanged $event): array
    {
        $recipientId = match ($event->status) {
            TaskStatus::InReview => $this->notifications->taskReviewerId($event->task),
            TaskStatus::Completed => $this->notifications->taskCreatorId($event->task),
            default => null,
        };

        return $this->eligible(
            $event->actorId,
            $recipientId === null ? [] : [$recipientId],
        );
    }

    /**
     * @return list<int>
     */
    public function forCommentAdded(CommentAdded $event): array
    {
        return $this->eligible(
            (int) $event->comment->user_id,
            [
                ...$this->notifications->taskAssigneeIds($event->task),
                $this->notifications->taskCreatorId($event->task),
            ],
        );
    }

    /**
     * @return list<int>
     */
    public function forFileUploaded(FileUploaded $event): array
    {
        if ($event->file->task_id === null) {
            return [];
        }

        return $this->eligible(
            $event->actorId,
            $this->notifications->taskAssigneeIdsForWorkspace(
                (int) $event->file->workspace_id,
                (int) $event->file->task_id,
            ),
        );
    }

    /**
     * @return list<int>
     */
    public function forProjectMemberAdded(ProjectMemberAdded $event): array
    {
        return $this->eligible(
            $event->actorId,
            [(int) $event->member->getKey()],
        );
    }

    /**
     * @param  list<int>  $userIds
     * @return list<int>
     */
    private function eligible(int $actorId, array $userIds): array
    {
        $uniqueIds = array_values(array_unique(array_filter(
            array_map('intval', $userIds),
            fn (int $userId): bool => $userId !== $actorId,
        )));

        return $uniqueIds;
    }
}
