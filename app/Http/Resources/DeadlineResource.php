<?php

namespace App\Http\Resources;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Task */
class DeadlineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'priority' => $this->priority->value,
            'due_date' => $this->due_date?->toDateString(),
            'is_overdue' => $this->due_date !== null
                && $this->due_date->lt(today())
                && $this->status !== TaskStatus::Completed,
            'is_due_today' => $this->due_date?->isSameDay(today()) ?? false,
            'project' => [
                'id' => $this->project->id,
                'name' => $this->project->name,
            ],
            'assignees' => $this->assignees->map(fn ($assignee): array => [
                'id' => $assignee->id,
                'name' => $assignee->name,
                'avatar' => $assignee->avatar,
            ])->all(),
        ];
    }
}
