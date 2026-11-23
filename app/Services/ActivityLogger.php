<?php

namespace App\Services;

use App\Enums\ActivityEvent;
use App\Repositories\Contracts\ActivityRepositoryInterface;

class ActivityLogger
{
    public function __construct(private ActivityRepositoryInterface $activities) {}

    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(
        ActivityEvent $event,
        int $workspaceId,
        ?int $projectId,
        int $causerId,
        string $subjectType,
        int $subjectId,
        array $properties,
    ): void {
        $this->activities->create([
            'workspace_id' => $workspaceId,
            'project_id' => $projectId,
            'causer_id' => $causerId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'event' => $event,
            'properties' => [
                ...$properties,
                'actor_name' => $this->activities->causerName($causerId),
            ],
            'created_at' => now(),
        ]);
    }
}
