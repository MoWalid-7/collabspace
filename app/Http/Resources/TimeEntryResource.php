<?php

namespace App\Http\Resources;

use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TimeEntry
 */
class TimeEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'project_id' => $this->project_id,
            'task_id' => $this->task_id,
            'started_at' => $this->started_at->toISOString(),
            'ended_at' => $this->ended_at?->toISOString(),
            'duration_seconds' => $this->duration_seconds,
            'note' => $this->note,
            'project' => [
                'id' => $this->project->id,
                'name' => $this->project->name,
            ],
            'task' => $this->task ? [
                'id' => $this->task->id,
                'title' => $this->task->title,
            ] : null,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
