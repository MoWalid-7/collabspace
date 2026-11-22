<?php

namespace App\Http\Resources;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskBoardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $columns = [];

        foreach (TaskStatus::cases() as $status) {
            /** @var Collection<int, Task> $tasks */
            $tasks = $this->resource[$status->value];
            $columns[$status->value] = TaskResource::collection($tasks)->resolve($request);
        }

        return ['columns' => $columns];
    }
}
