<?php

declare(strict_types=1);

namespace App\Listeners\Activities;

use App\Enums\ActivityEvent;
use App\Events\MeetingCancelled;
use App\Models\Meeting;
use App\Services\ActivityLogger;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecordMeetingCancelledActivity implements ShouldQueue
{
    public function __construct(private ActivityLogger $logger) {}

    public function handle(MeetingCancelled $event): void
    {
        $this->logger->record(
            ActivityEvent::MeetingCancelled,
            (int) $event->meeting->workspace_id,
            $event->meeting->project_id,
            $event->actorId,
            Meeting::class,
            (int) $event->meeting->getKey(),
            ['meeting_title' => $event->meeting->title],
        );
    }
}
