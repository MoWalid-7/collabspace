<?php

namespace App\Http\Resources;

use App\Models\Activity;
use App\Services\ActivityDescriber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Activity */
class ActivityResource extends JsonResource
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
            'causer_id' => $this->causer_id,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'event' => $this->event->value,
            'message' => ActivityDescriber::describe($this->event, $this->properties),
            'properties' => $this->properties,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
