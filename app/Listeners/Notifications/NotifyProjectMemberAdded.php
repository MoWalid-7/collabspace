<?php

namespace App\Listeners\Notifications;

use App\Enums\NotificationType;
use App\Events\ProjectMemberAdded;
use App\Services\Contracts\NotificationServiceInterface;
use App\Services\NotificationRecipientResolver;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyProjectMemberAdded implements ShouldQueue
{
    public function __construct(
        private NotificationRecipientResolver $recipients,
        private NotificationServiceInterface $notifications,
    ) {}

    public function handle(ProjectMemberAdded $event): void
    {
        $this->notifications->notify(
            NotificationType::ProjectMemberAdded,
            'Added to project',
            "You were added to '{$event->project->name}'.",
            (int) $event->project->workspace_id,
            (int) $event->project->getKey(),
            null,
            null,
            $event->actorId,
            $this->recipients->forProjectMemberAdded($event),
        );
    }
}
