<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchResultsResource extends JsonResource
{
    /**
     * The current groups are always present; messages can be added later without
     * changing the existing result groups or item shapes.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $results */
        $results = $this->resource;

        return $results;
    }
}
