<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Meeting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Meeting
 */
class UpcomingMeetingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'project_id' => $this->project_id,
            'project' => new ProjectResource($this->whenLoaded('project')),
            'starts_at' => $this->starts_at->toISOString(),
            'ends_at' => $this->ends_at->toISOString(),
            'join_url' => $this->join_url,
            'status' => $this->status->value,
            'participants_count' => $this->participants->count(),
        ];
    }
}
