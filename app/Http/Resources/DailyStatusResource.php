<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DailyStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DailyStatus
 */
class DailyStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'user_id' => $this->user_id,
            'project_id' => $this->project_id,
            'date' => $this->date,
            'done_yesterday' => $this->done_yesterday,
            'plan_today' => $this->plan_today,
            'blockers' => $this->blockers,
            'user' => new UserResource($this->whenLoaded('user')),
            'project' => new ProjectResource($this->whenLoaded('project')),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
