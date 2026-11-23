<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class FileDetailResource extends FileResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'project' => $this->whenLoaded('project', fn ($project): array => [
                'id' => $project->id,
                'name' => $project->name,
            ]),
            'task' => $this->whenLoaded('task', fn ($task) => $task === null ? null : [
                'id' => $task->id,
                'title' => $task->title,
            ]),
        ];
    }
}
