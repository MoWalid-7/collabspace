<?php

namespace App\Http\Resources;

use App\Models\FileVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FileVersion */
class FileVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version_number' => $this->version_number,
            'original_name' => $this->original_name,
            'mime' => $this->mime,
            'size' => $this->size,
            'checksum' => $this->checksum,
            'uploaded_by' => $this->uploaded_by,
            'uploader' => UserResource::make($this->whenLoaded('uploader')),
            'note' => $this->note,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
