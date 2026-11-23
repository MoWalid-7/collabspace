<?php

namespace App\Listeners\Notifications;

use App\Enums\NotificationType;
use App\Events\FileUploaded;
use App\Services\Contracts\NotificationServiceInterface;
use App\Services\NotificationRecipientResolver;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyFileUploaded implements ShouldQueue
{
    public function __construct(
        private NotificationRecipientResolver $recipients,
        private NotificationServiceInterface $notifications,
    ) {}

    public function handle(FileUploaded $event): void
    {
        if ($event->file->task_id === null) {
            return;
        }

        $this->notifications->notify(
            NotificationType::FileUploaded,
            'File uploaded',
            "A file was uploaded to '{$event->taskTitle}'.",
            (int) $event->file->workspace_id,
            (int) $event->file->project_id,
            (int) $event->file->task_id,
            (int) $event->file->getKey(),
            $event->actorId,
            $this->recipients->forFileUploaded($event),
        );
    }
}
