<?php

declare(strict_types=1);

namespace App\Listeners\Activities;

use App\Enums\ActivityEvent;
use App\Events\MeetingCreated;
use App\Models\Meeting;
use App\Services\ActivityLogger;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecordMeetingCreatedActivity implements ShouldQueue
{
    public function __construct(private ActivityLogger $logger) {}

    public function handle(MeetingCreated $event): void
    {
        $this->logger->record(
            ActivityEvent::MeetingCreated,
            (int) $event->meeting->workspace_id,
            $event->meeting->project_id,
            $event->actorId,
            Meeting::class,
            (int) $event->meeting->getKey(),
            ['meeting_title' => $event->meeting->title],
        );
    }
}
