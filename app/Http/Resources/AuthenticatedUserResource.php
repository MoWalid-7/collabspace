<?php

namespace App\Http\Resources;

use App\Data\AuthenticatedUserData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuthenticatedUserData */
class AuthenticatedUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $this->token->expiresAt->toISOString(),
            'user' => UserResource::make($this->user),
        ];
    }
}
