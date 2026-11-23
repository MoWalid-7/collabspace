<?php

namespace App\Listeners\Activities;

use App\Enums\ActivityEvent;
use App\Events\TaskAssigned;
use App\Models\Task;
use App\Services\ActivityLogger;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecordTaskAssignedActivity implements ShouldQueue
{
    public function __construct(private ActivityLogger $logger) {}

    public function handle(TaskAssigned $event): void
    {
        $this->logger->record(
            ActivityEvent::TaskAssigned,
            (int) $event->task->workspace_id,
            (int) $event->task->project_id,
            $event->actorId,
            Task::class,
            (int) $event->task->getKey(),
            [
                'task_title' => $event->task->title,
                'assignee_name' => $event->assignee->name,
            ],
        );
    }
}
