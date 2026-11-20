<?php

namespace App\Data;

use App\Models\User;

readonly class AuthenticatedUserData
{
    public function __construct(
        public User $user,
        public IssuedApiTokenData $token,
    ) {}
}
