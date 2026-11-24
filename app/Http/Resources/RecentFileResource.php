<?php

namespace App\Http\Resources;

use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin File */
class RecentFileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'uploader' => [
                'id' => $this->uploader->id,
                'name' => $this->uploader->name,
                'avatar' => $this->uploader->avatar,
            ],
            'project' => [
                'id' => $this->project->id,
                'name' => $this->project->name,
            ],
            'size' => $this->currentVersion?->size,
            'mime' => $this->currentVersion?->mime,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
