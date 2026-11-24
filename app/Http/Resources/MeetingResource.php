<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Meeting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Meeting
 */
class MeetingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'project_id' => $this->project_id,
            'title' => $this->title,
            'description' => $this->description,
            'starts_at' => $this->starts_at->toISOString(),
            'ends_at' => $this->ends_at->toISOString(),
            'join_url' => $this->join_url,
            'status' => $this->status->value,
            'created_by' => $this->created_by,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'project' => new ProjectResource($this->whenLoaded('project')),
            'participants' => $this->whenLoaded('participants', function () {
                return $this->participants->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'response' => $user->pivot->response ?? 'pending',
                    ];
                });
            }),
            'reminder_sent_at' => $this->reminder_sent_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
