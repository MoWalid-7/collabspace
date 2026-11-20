<?php

namespace App\Data\Auth;

readonly class GoogleAccessTokenData
{
    public function __construct(public string $accessToken) {}
}
