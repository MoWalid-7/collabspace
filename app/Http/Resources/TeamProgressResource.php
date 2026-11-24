<?php

namespace App\Http\Resources;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Team */
class TeamProgressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'members_count' => $this->members_count,
            'tasks_completed' => $this->tasks_completed,
            'tasks_total' => $this->tasks_total,
            'completion_percent' => $this->completion_percent,
        ];
    }
}
