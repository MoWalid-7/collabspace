<?php

namespace App\Listeners\Activities;

use App\Enums\ActivityEvent;
use App\Events\TaskCreated;
use App\Models\Task;
use App\Services\ActivityLogger;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecordTaskCreatedActivity implements ShouldQueue
{
    public function __construct(private ActivityLogger $logger) {}

    public function handle(TaskCreated $event): void
    {
        $this->logger->record(
            ActivityEvent::TaskCreated,
            (int) $event->task->workspace_id,
            (int) $event->task->project_id,
            (int) $event->task->created_by,
            Task::class,
            (int) $event->task->getKey(),
            ['task_title' => $event->task->title],
        );
    }
}
