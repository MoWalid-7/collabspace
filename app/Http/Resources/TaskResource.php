<?php

namespace App\Http\Resources;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Task */
class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'created_by' => $this->created_by,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'due_date' => $this->due_date?->toDateString(),
            'position' => $this->position,
            'progress' => $this->progress,
            'completed_at' => $this->completed_at?->toISOString(),
            'is_overdue' => $this->due_date !== null
                && $this->due_date->lt(today())
                && $this->status !== TaskStatus::Completed,
            'assignees' => UserResource::collection($this->whenLoaded('assignees')),
            'reviewer' => UserResource::make($this->whenLoaded('reviewer')),
        ];
    }
}
