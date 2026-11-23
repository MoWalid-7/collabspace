<?php

namespace App\Listeners\Notifications;

use App\Enums\NotificationType;
use App\Enums\TaskStatus;
use App\Events\TaskStatusChanged;
use App\Services\Contracts\NotificationServiceInterface;
use App\Services\NotificationRecipientResolver;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyTaskStatusChanged implements ShouldQueue
{
    public function __construct(
        private NotificationRecipientResolver $recipients,
        private NotificationServiceInterface $notifications,
    ) {}

    public function handle(TaskStatusChanged $event): void
    {
        $type = match ($event->status) {
            TaskStatus::InReview => NotificationType::TaskInReview,
            TaskStatus::Completed => NotificationType::TaskCompleted,
            default => null,
        };

        if ($type === null) {
            return;
        }

        $body = $event->status === TaskStatus::InReview
            ? "Task '{$event->task->title}' is ready for review."
            : "Task '{$event->task->title}' has been completed.";

        $this->notifications->notify(
            $type,
            $event->status === TaskStatus::InReview ? 'Task in review' : 'Task completed',
            $body,
            (int) $event->task->workspace_id,
            (int) $event->task->project_id,
            (int) $event->task->getKey(),
            null,
            $event->actorId,
            $this->recipients->forTaskStatusChanged($event),
        );
    }
}
