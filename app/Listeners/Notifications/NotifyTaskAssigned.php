<?php

namespace App\Listeners\Notifications;

use App\Enums\NotificationType;
use App\Events\TaskAssigned;
use App\Services\Contracts\NotificationServiceInterface;
use App\Services\NotificationRecipientResolver;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyTaskAssigned implements ShouldQueue
{
    public function __construct(
        private NotificationRecipientResolver $recipients,
        private NotificationServiceInterface $notifications,
    ) {}

    public function handle(TaskAssigned $event): void
    {
        $this->notifications->notify(
            NotificationType::TaskAssigned,
            'Task assigned',
            "You were assigned to '{$event->task->title}'.",
            (int) $event->task->workspace_id,
            (int) $event->task->project_id,
            (int) $event->task->getKey(),
            null,
            $event->actorId,
            $this->recipients->forTaskAssigned($event),
        );
    }
}
