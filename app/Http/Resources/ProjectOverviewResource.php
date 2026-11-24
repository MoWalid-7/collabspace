<?php

namespace App\Http\Resources;

use App\Data\Dashboard\ProjectOverviewData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProjectOverviewData */
class ProjectOverviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'range' => $this->range,
            'created' => $this->created,
            'completed' => $this->completed,
        ];
    }
}
