<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class WorkspaceMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'full_name' => $this->resource->name,
            'email' => $this->resource->email,
            'role' => data_get($this->resource, 'pivot.role'),
            'joined_at' => data_get($this->resource, 'pivot.created_at'),
        ];
    }
}
