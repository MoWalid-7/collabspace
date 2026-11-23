<?php

namespace App\Listeners\Activities;

use App\Enums\ActivityEvent;
use App\Events\ProjectCreated;
use App\Models\Project;
use App\Services\ActivityLogger;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecordProjectCreatedActivity implements ShouldQueue
{
    public function __construct(private ActivityLogger $logger) {}

    public function handle(ProjectCreated $event): void
    {
        $this->logger->record(
            ActivityEvent::ProjectCreated,
            (int) $event->project->workspace_id,
            (int) $event->project->getKey(),
            $event->actorId,
            Project::class,
            (int) $event->project->getKey(),
            ['project_name' => $event->project->name],
        );
    }
}
