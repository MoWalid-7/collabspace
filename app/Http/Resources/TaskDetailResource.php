<?php

namespace App\Http\Resources;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;

/** @mixin Task */
class TaskDetailResource extends TaskResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'project' => $this->whenLoaded('project', fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
            ]),
            'comments_count' => $this->whenCounted('comments'),
        ];
    }
}
