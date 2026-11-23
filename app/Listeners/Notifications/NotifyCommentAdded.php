<?php

namespace App\Listeners\Notifications;

use App\Enums\NotificationType;
use App\Events\CommentAdded;
use App\Services\Contracts\NotificationServiceInterface;
use App\Services\NotificationRecipientResolver;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyCommentAdded implements ShouldQueue
{
    public function __construct(
        private NotificationRecipientResolver $recipients,
        private NotificationServiceInterface $notifications,
    ) {}

    public function handle(CommentAdded $event): void
    {
        $this->notifications->notify(
            NotificationType::CommentAdded,
            'New comment',
            "A new comment was added to '{$event->task->title}'.",
            (int) $event->task->workspace_id,
            (int) $event->task->project_id,
            (int) $event->task->getKey(),
            null,
            (int) $event->comment->user_id,
            $this->recipients->forCommentAdded($event),
        );
    }
}
