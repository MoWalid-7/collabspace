<?php

namespace App\Http\Resources;

use App\Data\Dashboard\DashboardStatsData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DashboardStatsData */
class DashboardStatsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->cards;
    }
}
