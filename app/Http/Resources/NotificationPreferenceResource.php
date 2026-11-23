<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationPreferenceResource extends JsonResource
{
    /**
     * @return array{type: string, enabled: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => $this['type'],
            'enabled' => $this['enabled'],
        ];
    }
}
