<?php

namespace App\Http\Resources;

use App\Data\Projects\ProjectDetailsData;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProjectDetailsData|Project */
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $details = $this->resource;
        $project = $details instanceof ProjectDetailsData ? $details->project : $details;

        $resource = [
            'id' => $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'type' => $project->type,
            'priority' => $project->priority->value,
            'status' => $project->status->value,
            'start_date' => $project->start_date?->toDateString(),
            'deadline' => $project->deadline?->toDateString(),
            'created_by' => $project->created_by,
            'members' => MemberResource::collection($project->members),
            'teams' => TeamResource::collection($project->teams),
        ];

        if ($details instanceof ProjectDetailsData) {
            $resource['stats'] = [
                'total_tasks' => $details->stats->totalTasks,
                'pending' => $details->stats->pending,
                'in_progress' => $details->stats->inProgress,
                'in_review' => $details->stats->inReview,
                'completed' => $details->stats->completed,
                'progress_percent' => $details->stats->progressPercent,
            ];
        }

        return $resource;
    }
}
