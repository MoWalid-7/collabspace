<?php

namespace App\Listeners\Activities;

use App\Enums\ActivityEvent;
use App\Events\ProjectMemberAdded;
use App\Models\Project;
use App\Services\ActivityLogger;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecordProjectMemberAddedActivity implements ShouldQueue
{
    public function __construct(private ActivityLogger $logger) {}

    public function handle(ProjectMemberAdded $event): void
    {
        $this->logger->record(
            ActivityEvent::ProjectMemberAdded,
            (int) $event->project->workspace_id,
            (int) $event->project->getKey(),
            $event->actorId,
            Project::class,
            (int) $event->project->getKey(),
            [
                'project_name' => $event->project->name,
                'member_name' => $event->member->name,
            ],
        );
    }
}
