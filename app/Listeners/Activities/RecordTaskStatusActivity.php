<?php

namespace App\Listeners\Activities;

use App\Enums\ActivityEvent;
use App\Events\TaskStatusChanged;
use App\Models\Task;
use App\Services\ActivityLogger;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecordTaskStatusActivity implements ShouldQueue
{
    public function __construct(private ActivityLogger $logger) {}

    public function handle(TaskStatusChanged $event): void
    {
        $this->logger->record(
            ActivityEvent::TaskStatusChanged,
            (int) $event->task->workspace_id,
            (int) $event->task->project_id,
            $event->actorId,
            Task::class,
            (int) $event->task->getKey(),
            [
                'task_title' => $event->task->title,
                'previous_status' => $event->previousStatus->value,
                'status' => $event->status->value,
            ],
        );
    }
}
